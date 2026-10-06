<?php
/**
 * Elementor structured runtime overlay.
 *
 * v0.1.1 Step 5A-5E:
 * - Heading.title + Button.text.
 * - Spanish only.
 * - Current/reviewed translations only.
 * - Exact widget identity: object_id + element_id + widget_type + setting_path.
 * - Widget hooks are retained for diagnostics, while the stable structured overlay
 *   also runs at elementor/frontend/the_content for Elementor cached render paths.
 * - Never writes _elementor_data or Elementor documents.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WEM_ML_Elementor_Runtime_Overlay {

    /** @var array<int,array<string,mixed>> */
    private static $validation_trace = array();

    public static function init() {
        add_filter( 'elementor/element/is_dynamic_content', array( __CLASS__, 'mark_managed_widget_dynamic' ), 20, 3 );
        add_filter( 'elementor/widget/render_content', array( __CLASS__, 'filter_widget_content' ), 20, 3 );
        add_filter( 'elementor/frontend/the_content', array( __CLASS__, 'filter_elementor_content' ), 20 );
        add_action( 'shutdown', array( __CLASS__, 'print_validation_trace' ), 9999 );
    }

    public static function mark_managed_widget_dynamic( $is_dynamic, $raw_data, $element ) {
        if ( $is_dynamic || is_admin() || 'es' !== WEM_ML_Language_Context::get_current_language() ) {
            return $is_dynamic;
        }

        if ( self::is_elementor_editor_or_preview() ) {
            return $is_dynamic;
        }

        if ( ! is_object( $element ) || ! method_exists( $element, 'get_name' ) || ! method_exists( $element, 'get_id' ) ) {
            return $is_dynamic;
        }

        $widget_type = sanitize_key( (string) $element->get_name() );
        $fields      = WEM_ML_Elementor_Adapter_Registry::get_fields_for_widget( $widget_type );

        if ( empty( $fields ) ) {
            return $is_dynamic;
        }

        $object_id  = self::get_current_object_id();
        $post       = $object_id > 0 ? get_post( $object_id ) : null;
        $element_id = (string) $element->get_id();

        if ( ! $post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
            return $is_dynamic;
        }

        foreach ( $fields as $setting_path => $definition ) {
            $context_key = sprintf(
                'elementor:%d:%s:%s:%s',
                $object_id,
                $element_id,
                $widget_type,
                $setting_path
            );

            $source = WEM_ML_Source_Unit_Repository::get_by_context( $context_key );

            if ( $source && 'elementor_widget_field' === (string) $source->context_type && 'active' === (string) $source->state ) {
                self::trace( 'is_dynamic_content', array(
                    'object_id'      => $object_id,
                    'element_id'     => $element_id,
                    'context_key'    => $context_key,
                    'source_unit_id' => (int) $source->id,
                    'result'         => 'dynamic-true',
                ) );

                return true;
            }
        }

        return $is_dynamic;
    }

    public static function evaluate_heading( $widget, $object_id, $language = 'es' ) {
        return self::evaluate_widget_field( $widget, $object_id, 'heading', 'title', $language );
    }

    public static function evaluate_button( $widget, $object_id, $language = 'es' ) {
        return self::evaluate_widget_field( $widget, $object_id, 'button', 'text', $language );
    }

    private static function evaluate_widget_field( $widget, $object_id, $widget_type, $setting_path, $language = 'es' ) {
        $result = array(
            'state'                => 'invalid-widget',
            'can_overlay'          => false,
            'context_key'          => '',
            'source_text'          => '',
            'translated_text'      => '',
            'live_source_hash'     => '',
            'stored_source_hash'   => '',
            'translated_from_hash' => '',
            'widget_type'          => $widget_type,
            'setting_path'         => $setting_path,
        );

        if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) || ! method_exists( $widget, 'get_id' ) ) {
            return $result;
        }

        if ( $widget_type !== sanitize_key( (string) $widget->get_name() ) ) {
            $result['state'] = 'unsupported-widget';
            return $result;
        }

        $registered = WEM_ML_Elementor_Adapter_Registry::get_fields_for_widget( $widget_type );
        if ( ! isset( $registered[ $setting_path ] ) ) {
            $result['state'] = 'unsupported-field';
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

        $source_text = (string) $widget->get_settings( $setting_path );
        if ( '' === trim( $source_text ) ) {
            $result['state'] = 'empty-source';
            return $result;
        }

        $element_id  = (string) $widget->get_id();
        $context_key = sprintf(
            'elementor:%d:%s:%s:%s',
            $object_id,
            $element_id,
            $widget_type,
            $setting_path
        );

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

        if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) ) {
            return $widget_content;
        }

        $widget_type = sanitize_key( (string) $widget->get_name() );

        if ( 'heading' === $widget_type ) {
            $setting_path = 'title';
        } elseif ( 'button' === $widget_type ) {
            $setting_path = 'text';
        } else {
            return $widget_content;
        }

        $object_id  = self::get_current_object_id();
        $element_id = method_exists( $widget, 'get_id' ) ? (string) $widget->get_id() : '';
        $evaluation = self::evaluate_widget_field( $widget, $object_id, $widget_type, $setting_path, 'es' );

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

        $replace_count = 0;
        $replaced      = self::replace_structured_field_inner_html(
            (string) $widget_content,
            '',
            $widget_type,
            $setting_path,
            (string) $evaluation['translated_text'],
            $replace_count
        );

        self::trace( 'render_content_replace', array(
            'object_id'     => $object_id,
            'element_id'    => $element_id,
            'context_key'   => isset( $evaluation['context_key'] ) ? $evaluation['context_key'] : '',
            'replace_count' => (int) $replace_count,
            'result'        => $replace_count > 0 ? 'replaced' : 'pattern-miss',
        ) );

        return $replaced;
    }

    /**
     * Structured Elementor-content overlay.
     *
     * This is not a global text translator. It first discovers adapter-approved
     * fields, resolves their WEM Source Unit/Translation state, then targets only
     * the exact Elementor wrapper identified by data-id and the registered field DOM.
     */
    public static function filter_elementor_content( $content ) {
        if ( is_admin() || 'es' !== WEM_ML_Language_Context::get_current_language() ) {
            return $content;
        }

        if ( self::is_elementor_editor_or_preview() ) {
            return $content;
        }

        $object_id = self::get_current_object_id();
        $post      = $object_id > 0 ? get_post( $object_id ) : null;

        if ( ! $post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
            return $content;
        }

        if ( ! WEM_ML_Object_State::is_published( $post->post_type, $object_id, 'es' ) ) {
            self::trace( 'frontend_the_content', array(
                'object_id' => $object_id,
                'state'     => 'not-published',
                'result'    => 'fallback-source',
            ) );
            return $content;
        }

        $discovery = WEM_ML_Elementor_Source_Discovery::discover( $object_id );
        if ( is_wp_error( $discovery ) ) {
            self::trace( 'frontend_the_content', array(
                'object_id' => $object_id,
                'state'     => 'discovery-error',
                'result'    => $discovery->get_error_code(),
            ) );
            return $content;
        }

        $output = (string) $content;

        foreach ( $discovery['fields'] as $field ) {
            if ( ! self::is_runtime_supported_field( $field ) ) {
                continue;
            }

            $source = WEM_ML_Source_Unit_Repository::get_by_context( (string) $field['context_key'] );

            if ( ! $source || 'elementor_widget_field' !== (string) $source->context_type || 'active' !== (string) $source->state ) {
                self::trace_content_field( $object_id, $field, 'missing-source', 0 );
                continue;
            }

            if ( ! hash_equals( (string) $source->source_hash, (string) $field['source_hash'] ) ) {
                self::trace_content_field( $object_id, $field, 'source-drift', 0 );
                continue;
            }

            $translation = WEM_ML_Translation_Repository::get_translation( (int) $source->id, 'es' );

            if ( ! $translation || 'reviewed' !== (string) $translation->status || '' === trim( (string) $translation->translated_text ) ) {
                self::trace_content_field( $object_id, $field, 'missing-translation', 0 );
                continue;
            }

            if ( WEM_ML_Translation_Repository::is_stale( $source, $translation ) ) {
                self::trace_content_field( $object_id, $field, 'stale', 0 );
                continue;
            }

            $replace_count = 0;
            $output        = self::replace_structured_field_inner_html(
                $output,
                (string) $field['element_id'],
                (string) $field['widget_type'],
                (string) $field['setting_path'],
                (string) $translation->translated_text,
                $replace_count
            );

            self::trace_content_field( $object_id, $field, 'applied', $replace_count );
        }

        return $output;
    }

    private static function is_runtime_supported_field( $field ) {
        if ( ! isset( $field['widget_type'], $field['setting_path'] ) ) {
            return false;
        }

        return (
            'heading' === (string) $field['widget_type']
            && 'title' === (string) $field['setting_path']
        ) || (
            'button' === (string) $field['widget_type']
            && 'text' === (string) $field['setting_path']
        );
    }

    private static function trace_content_field( $object_id, $field, $state, $replace_count ) {
        self::trace( 'frontend_the_content', array(
            'object_id'     => (int) $object_id,
            'element_id'    => isset( $field['element_id'] ) ? (string) $field['element_id'] : '',
            'context_key'   => isset( $field['context_key'] ) ? (string) $field['context_key'] : '',
            'state'         => (string) $state,
            'replace_count' => (int) $replace_count,
            'result'        => $replace_count > 0 ? 'replaced' : 'fallback-source',
        ) );
    }

    private static function replace_structured_field_inner_html( $html, $element_id, $widget_type, $setting_path, $translated_text, &$replace_count ) {
        if ( 'heading' === $widget_type && 'title' === $setting_path ) {
            return self::replace_heading_inner_html( $html, $element_id, $translated_text, $replace_count );
        }

        if ( 'button' === $widget_type && 'text' === $setting_path ) {
            return self::replace_button_text_inner_html( $html, $element_id, $translated_text, $replace_count );
        }

        $replace_count = 0;
        return (string) $html;
    }

    private static function replace_heading_inner_html( $html, $element_id, $translated_text, &$replace_count ) {
        $replace_count = 0;
        $html          = (string) $html;

        if ( '' === trim( (string) $translated_text ) || '' === $html ) {
            return $html;
        }

        if ( '' !== $element_id ) {
            $pattern = '~(?P<prefix><[^>]+\\bdata-id=["\\\']' . preg_quote( $element_id, '~' ) . '["\\\'][^>]*>[\\s\\S]{0,20000}?<h[1-6]\\b[^>]*\\bclass=["\\\'][^"\\\']*\\belementor-heading-title\\b[^"\\\']*["\\\'][^>]*>)(?P<inner>.*?)(?P<suffix></h[1-6]>)~is';
        } else {
            $pattern = '~(?P<prefix><h[1-6]\\b[^>]*\\bclass=["\\\'][^"\\\']*\\belementor-heading-title\\b[^"\\\']*["\\\'][^>]*>)(?P<inner>.*?)(?P<suffix></h[1-6]>)~is';
        }

        return self::replace_inner_html_by_pattern( $html, $pattern, $translated_text, $replace_count );
    }

    private static function replace_button_text_inner_html( $html, $element_id, $translated_text, &$replace_count ) {
        $replace_count = 0;
        $html          = (string) $html;

        if ( '' === trim( (string) $translated_text ) || '' === $html ) {
            return $html;
        }

        if ( '' !== $element_id ) {
            $pattern = '~(?P<prefix><[^>]+\\bdata-id=["\\\']' . preg_quote( $element_id, '~' ) . '["\\\'][^>]*>[\\s\\S]{0,20000}?<span\\b[^>]*\\bclass=["\\\'][^"\\\']*\\belementor-button-text\\b[^"\\\']*["\\\'][^>]*>)(?P<inner>.*?)(?P<suffix></span>)~is';
        } else {
            $pattern = '~(?P<prefix><span\\b[^>]*\\bclass=["\\\'][^"\\\']*\\belementor-button-text\\b[^"\\\']*["\\\'][^>]*>)(?P<inner>.*?)(?P<suffix></span>)~is';
        }

        return self::replace_inner_html_by_pattern( $html, $pattern, $translated_text, $replace_count );
    }

    private static function replace_inner_html_by_pattern( $html, $pattern, $translated_text, &$replace_count ) {
        $replaced = preg_replace_callback(
            $pattern,
            static function ( $matches ) use ( $translated_text ) {
                return $matches['prefix'] . esc_html( $translated_text ) . $matches['suffix'];
            },
            (string) $html,
            1,
            $replace_count
        );

        return is_string( $replaced ) ? $replaced : (string) $html;
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

        $plugin = \\Elementor\\Plugin::$instance;

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
