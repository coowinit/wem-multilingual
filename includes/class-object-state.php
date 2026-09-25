<?php
/**
 * Object language state repository for WEM Multilingual.
 *
 * Step 6A of v0.1.0 Experimental Core.
 *
 * Important:
 * - Translation existence does not imply publication.
 * - Missing state rows are treated as draft, but are not auto-created.
 * - v0.1.0 supports only Page/Post + Spanish (es).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WEM_ML_Object_State {

    /**
     * Return the current language status for an object.
     *
     * Missing rows are conservatively treated as draft.
     *
     * @param string $object_type Object type.
     * @param int    $object_id   Object ID.
     * @param string $language    Language code.
     * @return string draft|published
     */
    public static function get_status( $object_type, $object_id, $language ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wem_ml_object_state';

        $status = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT status
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

        return in_array( $status, array( 'draft', 'published' ), true )
            ? (string) $status
            : 'draft';
    }

    /**
     * Whether a target-language object is published.
     *
     * @param string $object_type Object type.
     * @param int    $object_id   Object ID.
     * @param string $language    Language code.
     * @return bool
     */
    public static function is_published( $object_type, $object_id, $language ) {
        return 'published' === self::get_status( $object_type, $object_id, $language );
    }

    /**
     * Save or update a target-language object status.
     *
     * @param string $object_type Object type. v0.1.0: page or post.
     * @param int    $object_id   WordPress object ID.
     * @param string $language    Target language. v0.1.0: es.
     * @param string $status      draft|published.
     * @return true|WP_Error
     */
    public static function save( $object_type, $object_id, $language, $status ) {
        global $wpdb;

        $object_type = sanitize_key( $object_type );
        $object_id   = absint( $object_id );
        $language    = sanitize_key( $language );
        $status      = sanitize_key( $status );

        if ( ! in_array( $object_type, array( 'page', 'post' ), true ) ) {
            return new WP_Error( 'wem_ml_invalid_state_object_type', 'v0.1.0 仅支持 Page / Post。' );
        }

        if ( 'es' !== $language ) {
            return new WP_Error( 'wem_ml_invalid_state_language', 'v0.1.0 当前只验证 Spanish (es)。' );
        }

        if ( ! in_array( $status, array( 'draft', 'published' ), true ) ) {
            return new WP_Error( 'wem_ml_invalid_state_status', '状态只能是 draft 或 published。' );
        }

        $post = get_post( $object_id );

        if ( ! $post || $object_type !== $post->post_type ) {
            return new WP_Error( 'wem_ml_state_object_not_found', '没有找到与 Object ID / Object Type 匹配的 WordPress 对象。' );
        }

        $table = $wpdb->prefix . 'wem_ml_object_state';
        $now   = current_time( 'mysql', true );

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

        if ( $existing_id ) {
            $updated = $wpdb->update(
                $table,
                array(
                    'status'     => $status,
                    'updated_at' => $now,
                ),
                array( 'id' => (int) $existing_id ),
                array( '%s', '%s' ),
                array( '%d' )
            );

            if ( false === $updated ) {
                return new WP_Error( 'wem_ml_state_update_failed', '语言状态更新失败。' );
            }

            return true;
        }

        $inserted = $wpdb->insert(
            $table,
            array(
                'object_type' => $object_type,
                'object_id'   => $object_id,
                'language'    => $language,
                'status'      => $status,
                'created_at'  => $now,
                'updated_at'  => $now,
            ),
            array( '%s', '%d', '%s', '%s', '%s', '%s' )
        );

        if ( false === $inserted ) {
            return new WP_Error( 'wem_ml_state_insert_failed', '语言状态保存失败。' );
        }

        return true;
    }

    /**
     * Return all explicit language-state rows for the experimental admin UI.
     *
     * @return array<int,object>
     */
    public static function get_all() {
        global $wpdb;

        $table = $wpdb->prefix . 'wem_ml_object_state';

        return $wpdb->get_results(
            "SELECT id, object_type, object_id, language, status, created_at, updated_at
             FROM {$table}
             ORDER BY id DESC"
        );
    }
}
