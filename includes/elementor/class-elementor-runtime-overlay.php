<?php
/**
 * Elementor widget-scoped runtime overlay.
 *
 * v0.1.1 Step 5A/5B:
 * - Heading only.
 * - Spanish only.
 * - Current/reviewed translations only.
 * - Runtime mutation is limited to the current Heading widget HTML.
 * - WEM-managed Spanish Heading output is treated as dynamic to avoid
 *   Elementor reusing stale language-dependent element HTML.
 * - Never writes _elementor_data or Elementor documents.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WEM_ML_Elementor_Runtime_Overlay {

    public static function init() {
        add_filter( 'elementor/element/is_dynamic_content', array( __CLASS__, 'mark_managed_heading_dynamic' ), 20, 3 );
        add_filter( 'elementor/widget/render_content', array( __CLASS__, 'filter_widget_content' ), 20, 3 );
    }

    /**
     * Tell Elementor not to reuse static Element Cache HTML for a Heading whose
     * frontend output is language/repository-state dependent in Spanish.
     *
     * This does not disable Elementor caching globally. It only marks a Heading
     * as dynamic when the current Spanish request can be mapped to an existing
     * WEM Elementor Source Unit.
     *
     * @param bool   $is_dynamic Existing dynamic-content decision.
     * @param array  $raw_data   Elementor raw element data.
     * @param object $element    Elementor element instance.
     * @return bool
     */
    public static function mark_managed_heading_dynamic( $is_dynamic, $raw_data, $element ) {
        if ( $is_dynamic || is_admin() || 'es' !== WEM_ML_Language_Context::get_current_language() ) {
            return $is_dynamic;
        }

        if ( self::is_elementor_editor_or_preview() ) {
            return $is_dynamic;
        }

        if ( ! is_object( $element ) || ! method_exists( $element, 'get_name' ) || ! method_exists( $element, 'get_id' ) ) {
            return $is_dynamic;
        }

        if ( 'heading' !== (string) $element->get_name() ) {
            return $is_dynamic;
        }

        $object_id = self::get_current_object_id();
        $post      = $object_id > 0 ? get_post( $object_id ) : null;

        if ( ! $post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
            return $is_dynamic;
        }

        $context_key = sprintf(
            'elementor:%d:%s:heading:title',
            $object_id,
            (string) $element->get_id()
        );

        $source = WEM_ML_Source_Unit_Repository::get_by_context( $context_key );

        if ( ! $source || 'elementor_widget_field' !== (string) $source->context_type || 'active' !== (string) $source->state ) {
            return $is_dynamic;
        }

        return true;
    }

    /**
     * Evaluate one Heading widget for a safe Spanish runtime overlay.
     *
     * @param object $widget    Elementor widget instance.
     * @param int    $object_id Current WordPress object ID.
     * @param string $language  Target language.
     * @return array<string,mixed>
     */
    public static function evaluate_heading( $widget, $object_id, $language = 'es' ) {
        $result = array(
            'state'                => 'invalid-widget',
            'can_overlay'          => false,
            'context_key'          => '',
            'source_text'          => '',
            'translated_text'      => '',
            'live_source_hash'     => '',
            'stored_source_hash'   => '',
            'translated_from_hash' => '',
        );

        if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) || ! method_exists( $widget, 'get_id' ) ) {
            return $result;
        }

        if ( 'heading' !== (string) $widget->get_name() ) {
            $result['state'] = 'unsupported-widget';
            return $result;
        }

        $object_id = absint( $object_id );
        $post      = $object_id > 0 ? get_post( $object_id ) : null;

        if ( ! $post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
            $result['state'] = 'invalid-object';
            return $result;
        }

        $language = sanitize_key( $language );

        if ( 'es' !== $language ) {
            $result['state'] = 'unsupported-language';
            return $result;
        }

        if ( ! WEM_ML_Object_State::is_published( $post->post_type, $object_id, $language ) ) {
            $result['state'] = 'not-published';
            return $result;
        }

        if ( ! method_exists( $widget, 'get_settings' ) ) {
            $result['state'] = 'settings-unavailable';
            return $result;
        }

        $source_text = (string) $widget->get_settings( 'title' );

        if ( '' === trim( $source_text ) ) {
            $result['state'] = 'empty-source';
            return $result;
        }

        $element_id  = (string) $widget->get_id();
        $context_key = sprintf( 'elementor:%d:%s:heading:title', $object_id, $element_id );

        $result['context_key']      = $context_key;
        $result['source_text']      = $source_text;
        $result['live_source_hash'] = WEM_ML_Translation_Repository::hash_text( $source_text );

        $source = WEM_ML_Source_Unit_Repository::get_by_context( $context_key );

        if ( ! $source || 'elementor_widget_field' !== (string) $source->context_type || 'active' !== (string) $source->state ) {
            $result['state'] = 'missing-source';
            return $result;
        }

        $result['stored_source_hash'] = (string) $source->source_hash;

        if ( ! hash_equals( (string) $source->source_hash, (string) $result['live_source_hash'] ) ) {
            $result['state'] = 'source-drift';
            return $result;
        }

        $translation = WEM_ML_Translation_Repository::get_translation( (int) $source->id, $language );

        if ( ! $translation || 'reviewed' !== (string) $translation->status || '' === trim( (string) $translation->translated_text ) ) {
            $result['state'] = 'missing-translation';
            return $result;
        }

        $result['translated_text']      = (string) $translation->translated_text;
        $result['translated_from_hash'] = (string) $translation->translated_from_hash;

        if ( WEM_ML_Translation_Repository::is_stale( $source, $translation ) ) {
            $result['state'] = 'stale';
            return $result;
        }

        $result['state']       = 'applied';
        $result['can_overlay'] = true;

        return $result;
    }

    /**
     * Overlay only the current Heading widget's rendered HTML.
     *
     * Elementor can route dynamic elements through its shortcode/cache render
     * path. Filtering the final widget content is more stable than mutating the
     * widget settings earlier in the render lifecycle, while remaining fully
     * widget-scoped (not a whole-page DOM/string translator).
     *
     * @param string $widget_content Rendered widget HTML.
     * @param object $widget         Elementor widget instance.
     * @param array  $args           Elementor render metadata.
     * @return string
     */
    public static function filter_widget_content( $widget_content, $widget, $args = array() ) {
        if ( is_admin() || 'es' !== WEM_ML_Language_Context::get_current_language() ) {
            return $widget_content;
        }

        if ( self::is_elementor_editor_or_preview() ) {
            return $widget_content;
        }

        if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) || 'heading' !== (string) $widget->get_name() ) {
            return $widget_content;
        }

        $object_id  = self::get_current_object_id();
        $evaluation = self::evaluate_heading( $widget, $object_id, 'es' );

        if ( empty( $evaluation['can_overlay'] ) ) {
            return $widget_content;
        }

        $translated_text = (string) $evaluation['translated_text'];

        if ( '' === trim( $translated_text ) ) {
            return $widget_content;
        }

        // Heading widget output is a single h1-h6 element with the
        // elementor-heading-title class. Replace only that element's inner HTML.
        $pattern = '~(<h([1-6])\\b[^>]*\\bclass=(["\\\'])[^"\\\']*\\belementor-heading-title\\b[^"\\\']*\\3[^>]*>)(.*?)(</h\\2>)~is';

        $replaced = preg_replace_callback(
            $pattern,
            static function ( $matches ) use ( $translated_text ) {
                return $matches[1] . esc_html( $translated_text ) . $matches[5];
            },
            (string) $widget_content,
            1
        );

        return is_string( $replaced ) ? $replaced : $widget_content;
    }

    /**
     * Return the current queried WordPress object ID.
     *
     * @return int
     */
    private static function get_current_object_id() {
        $object_id = get_queried_object_id();

        if ( $object_id <= 0 ) {
            $object_id = get_the_ID();
        }

        return absint( $object_id );
    }

    /**
     * Keep Elementor editor/preview source-facing and free from runtime overlays.
     *
     * @return bool
     */
    private static function is_elementor_editor_or_preview() {
        if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
            return false;
        }

        $plugin = \Elementor\Plugin::$instance;

        if ( isset( $plugin->editor ) && method_exists( $plugin->editor, 'is_edit_mode' ) && $plugin->editor->is_edit_mode() ) {
            return true;
        }

        if ( isset( $plugin->preview ) && method_exists( $plugin->preview, 'is_preview_mode' ) && $plugin->preview->is_preview_mode() ) {
            return true;
        }

        return false;
    }
}
