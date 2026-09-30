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

    /** @var array<int,array<string,mixed>> */
    private static $validation_trace = array();

    public static function init() {
        add_filter( 'elementor/element/is_dynamic_content', array( __CLASS__, 'mark_managed_heading_dynamic' ), 20, 3 );
        add_filter( 'elementor/widget/render_content', array( __CLASS__, 'filter_widget_content' ), 20, 3 );
        add_action( 'shutdown', array( __CLASS__, 'print_validation_trace' ), 9999 );
    }

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

        $object_id  = self::get_current_object_id();
        $post       = $object_id > 0 ? get_post( $object_id ) : null;
        $element_id = (string) $element->get_id();
        $context_key = sprintf( 'elementor:%d:%s:heading:title', $object_id, $element_id );

        if ( ! $post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
            self::trace( 'is_dynamic_content', array(
                'object_id'   => $object_id,
                'element_id'  => $element_id,
                'context_key' => $context_key,
                'result'      => 'invalid-object',
            ) );
            return $is_dynamic;
        }

        $source = WEM_ML_Source_Unit_Repository::get_by_context( $context_key );

        if ( ! $source || 'elementor_widget_field' !== (string) $source->context_type || 'active' !== (string) $source->state ) {
            self::trace( 'is_dynamic_content', array(
                'object_id'   => $object_id,
                'element_id'  => $element_id,
                'context_key' => $context_key,
                'result'      => 'missing-source',
            ) );
            return $is_dynamic;
        }

        self::trace( 'is_dynamic_content', array(
            'object_id'      => $object_id,
            'element_id'     => $element_id,
            'context_key'    => $context_key,
            'source_unit_id' => (int) $source->id,
            'result'         => 'dynamic-true',
        ) );

        return true;
    }

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
        $element_id = method_exists( $widget, 'get_id' ) ? (string) $widget->get_id() : '';
        $evaluation = self::evaluate_heading( $widget, $object_id, 'es' );

        self::trace( 'render_content', array(
            'object_id'      => $object_id,
            'element_id'     => $element_id,
            'context_key'    => isset( $evaluation['context_key'] ) ? $evaluation['context_key'] : '',
            'state'          => isset( $evaluation['state'] ) ? $evaluation['state'] : '',
            'can_overlay'    => ! empty( $evaluation['can_overlay'] ),
            'content_length' => strlen( (string) $widget_content ),
        ) );

        if ( empty( $evaluation['can_overlay'] ) ) {
            return $widget_content;
        }

        $translated_text = (string) $evaluation['translated_text'];

        if ( '' === trim( $translated_text ) ) {
            return $widget_content;
        }

        $pattern = '~(<h([1-6])\\b[^>]*\\bclass=(["\\\'])[^"\\\']*\\belementor-heading-title\\b[^"\\\']*\\3[^>]*>)(.*?)(</h\\2>)~is';

        $replace_count = 0;
        $replaced = preg_replace_callback(
            $pattern,
            static function ( $matches ) use ( $translated_text ) {
                return $matches[1] . esc_html( $translated_text ) . $matches[5];
            },
            (string) $widget_content,
            1,
            $replace_count
        );

        self::trace( 'render_content_replace', array(
            'object_id'     => $object_id,
            'element_id'    => $element_id,
            'replace_count' => (int) $replace_count,
            'result'        => $replace_count > 0 ? 'replaced' : 'pattern-miss',
        ) );

        return is_string( $replaced ) ? $replaced : $widget_content;
    }

    private static function get_current_object_id() {
        $object_id = get_queried_object_id();

        if ( $object_id <= 0 ) {
            $object_id = get_the_ID();
        }

        return absint( $object_id );
    }

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

    private static function is_validation_request() {
        return isset( $_GET['wem_ml_validation'] ) && '' !== sanitize_text_field( wp_unslash( (string) $_GET['wem_ml_validation'] ) );
    }

    private static function trace( $hook, $data = array() ) {
        if ( ! self::is_validation_request() ) {
            return;
        }

        self::$validation_trace[] = array_merge(
            array(
                'hook'     => (string) $hook,
                'language' => WEM_ML_Language_Context::get_current_language(),
            ),
            is_array( $data ) ? $data : array()
        );
    }

    public static function print_validation_trace() {
        if ( ! self::is_validation_request() ) {
            return;
        }

        self::trace( 'runtime_trace_shutdown', array(
            'object_id' => self::get_current_object_id(),
            'result'    => 'overlay-loaded',
        ) );

        $payload = wp_json_encode( self::$validation_trace, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

        if ( ! is_string( $payload ) ) {
            $payload = '[]';
        }

        echo "\n<!-- WEM_ML_RUNTIME_TRACE " . esc_html( $payload ) . " -->\n";
    }
}
