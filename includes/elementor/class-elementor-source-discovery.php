<?php
/**
 * Read-only Elementor source discovery service.
 *
 * Converts _elementor_data into adapter-approved structured source fields.
 * Never writes Elementor or WEM data.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WEM_ML_Elementor_Source_Discovery {

    /**
     * Discover adapter-approved source fields for one WordPress object.
     *
     * @param int $object_id WordPress object ID.
     * @return array|WP_Error
     */
    public static function discover( $object_id ) {
        $object_id = absint( $object_id );
        $post      = get_post( $object_id );

        if ( ! $post || 'revision' === $post->post_type ) {
            return new WP_Error( 'wem_ml_elementor_object_missing', '没有找到可测试的 WordPress Content Object。' );
        }

        $raw_data = get_post_meta( $object_id, '_elementor_data', true );

        if ( ! is_string( $raw_data ) || '' === trim( $raw_data ) ) {
            return new WP_Error( 'wem_ml_elementor_source_missing', '没有找到 _elementor_data。' );
        }

        $decoded = json_decode( $raw_data, true );

        if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
            return new WP_Error( 'wem_ml_elementor_json_invalid', 'Elementor JSON 无法解析：' . json_last_error_msg() );
        }

        $fields = array();
        self::walk( $decoded, $fields, array(), $object_id );

        return array(
            'object_id'        => $object_id,
            'object_type'      => $post->post_type,
            'source_json_hash' => hash( 'sha256', $raw_data ),
            'fields'           => $fields,
        );
    }

    private static function walk( $elements, &$fields, $path, $object_id ) {
        if ( ! is_array( $elements ) ) {
            return;
        }

        foreach ( $elements as $index => $element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }

            $element_id   = isset( $element['id'] ) ? (string) $element['id'] : '';
            $element_type = isset( $element['elType'] ) ? (string) $element['elType'] : '';
            $widget_type  = isset( $element['widgetType'] ) ? sanitize_key( (string) $element['widgetType'] ) : '';
            $settings     = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();

            $current_path   = $path;
            $current_path[] = (string) $index;
            $tree_path      = implode( '.', $current_path );

            if ( 'widget' === $element_type || '' !== $widget_type ) {
                $discovered = WEM_ML_Elementor_Adapter_Registry::discover_fields( $widget_type, $settings );

                foreach ( $discovered as $field ) {
                    $setting_path = (string) $field['setting_path'];
                    $source_text  = (string) $field['source_text'];
                    $context_key  = sprintf(
                        'elementor:%d:%s:%s:%s',
                        $object_id,
                        $element_id,
                        $widget_type,
                        $setting_path
                    );

                    $fields[] = array(
                        'element_id'      => $element_id,
                        'widget_type'     => $widget_type,
                        'tree_path'       => $tree_path,
                        'setting_path'    => $setting_path,
                        'mode'            => (string) $field['mode'],
                        'source_text'     => $source_text,
                        'source_hash'     => WEM_ML_Translation_Repository::hash_text( $source_text ),
                        'normalized_hash' => WEM_ML_Translation_Repository::hash_text(
                            WEM_ML_Translation_Repository::normalize_text( $source_text )
                        ),
                        'context_key'     => $context_key,
                        'field_key'       => sprintf( 'elementor.%s.%s', $widget_type, $setting_path ),
                    );
                }
            }

            if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
                self::walk( $element['elements'], $fields, $current_path, $object_id );
            }
        }
    }
}
