<?php
/**
 * Slug repository for WEM Multilingual.
 *
 * Step 4A of v0.1.0 Experimental Core.
 *
 * Route source of truth:
 * object_type + object_id + language + translated_slug
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WEM_ML_Slug_Repository {

    /**
     * Save or update one translated slug.
     *
     * @param string $object_type     Object type. v0.1.0: page or post.
     * @param int    $object_id       WordPress object ID.
     * @param string $language        Target language code.
     * @param string $translated_slug Target-language slug.
     * @return true|WP_Error
     */
    public static function save( $object_type, $object_id, $language, $translated_slug ) {
        global $wpdb;

        $object_type     = sanitize_key( $object_type );
        $object_id       = absint( $object_id );
        $language        = sanitize_key( $language );
        $translated_slug = sanitize_title( $translated_slug );

        if ( ! in_array( $object_type, array( 'page', 'post' ), true ) ) {
            return new WP_Error( 'wem_ml_invalid_object_type', 'v0.1.0 仅支持 Page / Post。' );
        }

        if ( $object_id <= 0 || '' === $translated_slug ) {
            return new WP_Error( 'wem_ml_invalid_slug_data', 'Object ID 和 translated slug 不能为空。' );
        }

        if ( 'es' !== $language ) {
            return new WP_Error( 'wem_ml_invalid_language', 'v0.1.0 当前只验证 Spanish (es)。' );
        }

        $post = get_post( $object_id );

        if ( ! $post || $object_type !== $post->post_type ) {
            return new WP_Error( 'wem_ml_object_not_found', '没有找到与 Object ID / Object Type 匹配的 WordPress 对象。' );
        }

        $table = $wpdb->prefix . 'wem_ml_object_slugs';

        // One translated slug may resolve to only one object in the same language.
        $collision_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT object_id
                 FROM {$table}
                 WHERE language = %s
                   AND translated_slug = %s
                   AND NOT ( object_type = %s AND object_id = %d )
                 LIMIT 1",
                $language,
                $translated_slug,
                $object_type,
                $object_id
            )
        );

        if ( null !== $collision_id ) {
            return new WP_Error(
                'wem_ml_slug_collision',
                sprintf( 'Slug "%s" 已被 Object #%d 使用。', $translated_slug, (int) $collision_id )
            );
        }

        $existing_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id
                 FROM {$table}
                 WHERE object_type = %s
                   AND object_id = %d
                   AND language = %s
                 LIMIT 1",
                $object_type,
                $object_id,
                $language
            )
        );

        $now = current_time( 'mysql', true );

        if ( $existing_id ) {
            $updated = $wpdb->update(
                $table,
                array(
                    'translated_slug' => $translated_slug,
                    'updated_at'      => $now,
                ),
                array( 'id' => (int) $existing_id ),
                array( '%s', '%s' ),
                array( '%d' )
            );

            if ( false === $updated ) {
                return new WP_Error( 'wem_ml_slug_update_failed', 'Slug 更新失败。' );
            }

            return true;
        }

        $inserted = $wpdb->insert(
            $table,
            array(
                'object_type'     => $object_type,
                'object_id'       => $object_id,
                'language'        => $language,
                'translated_slug' => $translated_slug,
                'created_at'      => $now,
                'updated_at'      => $now,
            ),
            array( '%s', '%d', '%s', '%s', '%s', '%s' )
        );

        if ( false === $inserted ) {
            return new WP_Error( 'wem_ml_slug_insert_failed', 'Slug 保存失败。' );
        }

        return true;
    }

    /**
     * Get translated slug by WordPress object identity.
     *
     * @param string $object_type Object type.
     * @param int    $object_id   Object ID.
     * @param string $language    Language code.
     * @return string|null
     */
    public static function get_slug( $object_type, $object_id, $language ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wem_ml_object_slugs';

        $slug = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT translated_slug
                 FROM {$table}
                 WHERE object_type = %s
                   AND object_id = %d
                   AND language = %s
                 LIMIT 1",
                sanitize_key( $object_type ),
                absint( $object_id ),
                sanitize_key( $language )
            )
        );

        return null === $slug ? null : (string) $slug;
    }

    /**
     * Resolve object identity from target-language slug.
     *
     * @param string $language        Language code.
     * @param string $translated_slug Translated slug.
     * @return object|null Row containing object_type and object_id.
     */
    public static function resolve( $language, $translated_slug ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wem_ml_object_slugs';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT object_type, object_id, language, translated_slug
                 FROM {$table}
                 WHERE language = %s
                   AND translated_slug = %s
                 LIMIT 1",
                sanitize_key( $language ),
                sanitize_title( $translated_slug )
            )
        );
    }

    /**
     * Return mappings for the experimental admin table.
     *
     * @return array<int,object>
     */
    public static function get_all() {
        global $wpdb;

        $table = $wpdb->prefix . 'wem_ml_object_slugs';

        return $wpdb->get_results(
            "SELECT id, object_type, object_id, language, translated_slug, created_at, updated_at
             FROM {$table}
             ORDER BY id DESC"
        );
    }
}
