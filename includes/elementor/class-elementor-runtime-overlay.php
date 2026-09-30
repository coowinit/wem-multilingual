<?php
/**
 * Elementor widget-scoped runtime overlay.
 *
 * v0.1.1 Step 5A:
 * - Heading only.
 * - Spanish only.
 * - Current/reviewed translations only.
 * - Runtime mutation of the current Elementor widget instance only.
 * - Never writes _elementor_data or Elementor documents.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WEM_ML_Elementor_Runtime_Overlay {

    public static function init() {
        add_action( 'elementor/frontend/widget/before_render', array( __CLASS__, 'before_widget_render' ), 20 );
    }

    /**
     * Evaluate one Heading widget for a safe Spanish runtime overlay.
     *
     * @param object $widget Elementor widget instance.
     * @param int    $object_id Current WordPress object ID.
     * @param string $language Target language.
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

        // Elementor changed but the WEM Source Unit has not been synchronized yet.
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
     * Temporarily replace one Heading title in Spanish frontend requests.
     *
     * @param object $widget Elementor widget instance.
     * @return void
     */
    public static function before_widget_render( $widget ) {
        if ( is_admin() || 'es' !== WEM_ML_Language_Context::get_current_language() ) {
            return;
        }

        if ( self::is_elementor_editor_or_preview() ) {
            return;
        }

        if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) || 'heading' !== (string) $widget->get_name() ) {
            return;
        }

        $object_id = get_queried_object_id();

        if ( $object_id <= 0 ) {
            $object_id = get_the_ID();
        }

        $evaluation = self::evaluate_heading( $widget, $object_id, 'es' );

        if ( empty( $evaluation['can_overlay'] ) || ! method_exists( $widget, 'set_settings' ) ) {
            return;
        }

        // Runtime-only mutation of this PHP widget instance.
        // No Elementor document save or post meta write occurs here.
        $widget->set_settings( 'title', (string) $evaluation['translated_text'] );
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

        $plugin = \\Elementor\\Plugin::$instance;

        if ( isset( $plugin->editor ) && method_exists( $plugin->editor, 'is_edit_mode' ) && $plugin->editor->is_edit_mode() ) {
            return true;
        }

        if ( isset( $plugin->preview ) && method_exists( $plugin->preview, 'is_preview_mode' ) && $plugin->preview->is_preview_mode() ) {
            return true;
        }

        return false;
    }
}
