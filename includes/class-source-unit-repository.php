<?php
/**
 * Generic Source Unit repository for WEM Multilingual.
 *
 * v0.1.1 Step 3A introduces structured source syncing beyond post_title.
 * This class writes only to WEM's wp_wem_ml_strings table.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WEM_ML_Source_Unit_Repository {

    /**
     * Insert or refresh one structured Source Unit by context identity.
     *
     * Context defines identity; source_hash defines source version.
     *
     * @param array $args Source unit data.
     * @return object|WP_Error
     */
    public static function sync( $args ) {
        global $wpdb;

        $defaults = array(
            'source_language' => 'en',
            'source_text'     => '',
            'context_type'    => '',
            'context_key'     => '',
            'object_type'     => '',
            'object_id'       => 0,
            'field_key'       => '',
            'state'           => 'active',
        );

        $args = wp_parse_args( $args, $defaults );

        $source_language = sanitize_key( (string) $args['source_language'] );
        $source_text     = (string) $args['source_text'];
        $context_type    = sanitize_key( (string) $args['context_type'] );
        $context_key     = sanitize_text_field( (string) $args['context_key'] );
        $object_type     = sanitize_key( (string) $args['object_type'] );
        $object_id       = absint( $args['object_id'] );
        $field_key       = sanitize_text_field( (string) $args['field_key'] );
        $state           = sanitize_key( (string) $args['state'] );

        if (
            '' === $source_language ||
            '' === $source_text ||
            '' === $context_type ||
            '' === $context_key ||
            '' === $object_type ||
            $object_id <= 0 ||
            '' === $field_key
        ) {
            return new WP_Error( 'wem_ml_invalid_source_unit', 'Source Unit 参数不完整。' );
        }

        if ( strlen( $context_key ) > 191 ) {
            return new WP_Error( 'wem_ml_context_key_too_long', 'Source Unit context_key 超过 191 字符。' );
        }

        if ( strlen( $field_key ) > 100 ) {
            return new WP_Error( 'wem_ml_field_key_too_long', 'Source Unit field_key 超过 100 字符。' );
        }

        $table           = $wpdb->prefix . 'wem_ml_strings';
        $source_hash     = WEM_ML_Translation_Repository::hash_text( $source_text );
        $normalized_hash = WEM_ML_Translation_Repository::hash_text(
            WEM_ML_Translation_Repository::normalize_text( $source_text )
        );
        $now = current_time( 'mysql', true );

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE context_key = %s LIMIT 1",
                $context_key
            )
        );

        $data = array(
            'source_language' => $source_language,
            'source_text'     => $source_text,
            'source_hash'     => $source_hash,
            'normalized_hash' => $normalized_hash,
            'context_type'    => $context_type,
            'context_key'     => $context_key,
            'object_type'     => $object_type,
            'object_id'       => $object_id,
            'field_key'       => $field_key,
            'state'           => $state ? $state : 'active',
            'updated_at'      => $now,
        );

        if ( $existing ) {
            $updated = $wpdb->update(
                $table,
                $data,
                array( 'id' => (int) $existing->id ),
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ),
                array( '%d' )
            );

            if ( false === $updated ) {
                return new WP_Error( 'wem_ml_source_unit_update_failed', 'Source Unit 更新失败。' );
            }
        } else {
            $data['created_at'] = $now;

            $inserted = $wpdb->insert(
                $table,
                $data,
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
            );

            if ( false === $inserted ) {
                return new WP_Error( 'wem_ml_source_unit_insert_failed', 'Source Unit 创建失败。' );
            }
        }

        return self::get_by_context( $context_key );
    }

    /**
     * Get one Source Unit by context identity.
     *
     * @param string $context_key Context key.
     * @return object|null
     */
    public static function get_by_context( $context_key ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wem_ml_strings';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE context_key = %s LIMIT 1",
                sanitize_text_field( (string) $context_key )
            )
        );
    }

    /**
     * Get structured Elementor Source Units for one object.
     *
     * @param int $object_id WordPress object ID.
     * @return array<int,object>
     */
    public static function get_elementor_units_for_object( $object_id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wem_ml_strings';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE object_id = %d
                   AND context_type = %s
                 ORDER BY id ASC",
                absint( $object_id ),
                'elementor_widget_field'
            )
        );
    }
}
