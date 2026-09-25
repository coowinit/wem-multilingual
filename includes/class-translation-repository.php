<?php
/**
 * Translation repository for WEM Multilingual.
 *
 * Step 5 of v0.1.0 Experimental Core.
 *
 * Identity rule:
 * Context defines identity; hash defines source version.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WEM_ML_Translation_Repository {

    /**
     * Normalize source text before generating normalized_hash.
     *
     * v0.1.0 keeps this deliberately conservative.
     *
     * @param string $text Source text.
     * @return string
     */
    public static function normalize_text( $text ) {
        $text = wp_strip_all_tags( (string) $text );
        $text = preg_replace( '/\s+/u', ' ', $text );

        return trim( (string) $text );
    }

    /**
     * Generate deterministic SHA-256 hash.
     *
     * @param string $text Text to hash.
     * @return string
     */
    public static function hash_text( $text ) {
        return hash( 'sha256', (string) $text );
    }

    /**
     * Register or refresh a post_title source unit.
     *
     * Context identity remains stable even when source text changes.
     *
     * @param int $object_id WordPress post/page ID.
     * @return object|WP_Error Source unit row.
     */
    public static function sync_post_title_source( $object_id ) {
        global $wpdb;

        $object_id = absint( $object_id );
        $post      = get_post( $object_id );

        if ( ! $post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
            return new WP_Error( 'wem_ml_source_object_not_found', '没有找到可用于 v0.1.0 Translation Lab 的 Page / Post。' );
        }

        $source_text     = (string) $post->post_title;
        $source_hash     = self::hash_text( $source_text );
        $normalized_hash = self::hash_text( self::normalize_text( $source_text ) );
        $context_key     = sprintf( 'post:%d:title', $object_id );
        $table           = $wpdb->prefix . 'wem_ml_strings';
        $now             = current_time( 'mysql', true );

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE context_key = %s LIMIT 1",
                $context_key
            )
        );

        if ( $existing ) {
            $updated = $wpdb->update(
                $table,
                array(
                    'source_text'     => $source_text,
                    'source_hash'     => $source_hash,
                    'normalized_hash' => $normalized_hash,
                    'object_type'     => $post->post_type,
                    'object_id'       => $object_id,
                    'field_key'       => 'post_title',
                    'state'           => 'active',
                    'updated_at'      => $now,
                ),
                array( 'id' => (int) $existing->id ),
                array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ),
                array( '%d' )
            );

            if ( false === $updated ) {
                return new WP_Error( 'wem_ml_source_update_failed', 'Source Unit 更新失败。' );
            }
        } else {
            $inserted = $wpdb->insert(
                $table,
                array(
                    'source_language' => 'en',
                    'source_text'     => $source_text,
                    'source_hash'     => $source_hash,
                    'normalized_hash' => $normalized_hash,
                    'context_type'    => 'post_field',
                    'context_key'     => $context_key,
                    'object_type'     => $post->post_type,
                    'object_id'       => $object_id,
                    'field_key'       => 'post_title',
                    'state'           => 'active',
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ),
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
            );

            if ( false === $inserted ) {
                return new WP_Error( 'wem_ml_source_insert_failed', 'Source Unit 创建失败。' );
            }
        }

        return self::get_source_by_context( $context_key );
    }

    /**
     * Save/update one Spanish translation for a source unit.
     *
     * translated_from_hash is captured from the current source_hash at save time.
     *
     * @param int    $string_id       Source Unit ID.
     * @param string $translated_text Spanish translation.
     * @return object|WP_Error Translation row.
     */
    public static function save_spanish_translation( $string_id, $translated_text ) {
        global $wpdb;

        $string_id       = absint( $string_id );
        $translated_text = trim( wp_unslash( (string) $translated_text ) );

        if ( $string_id <= 0 || '' === $translated_text ) {
            return new WP_Error( 'wem_ml_invalid_translation', 'Source Unit 和 Spanish Translation 不能为空。' );
        }

        $source = self::get_source( $string_id );

        if ( ! $source ) {
            return new WP_Error( 'wem_ml_source_not_found', 'Source Unit 不存在。' );
        }

        $table = $wpdb->prefix . 'wem_ml_translations';
        $now   = current_time( 'mysql', true );

        $existing_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table}
                 WHERE string_id = %d AND target_language = %s
                 LIMIT 1",
                $string_id,
                'es'
            )
        );

        $data = array(
            'translated_text'      => $translated_text,
            'translated_from_hash' => $source->source_hash,
            'status'               => 'reviewed',
            'origin'               => 'manual',
            'provider'             => '',
            'provider_model'       => '',
            'reviewed_by'          => get_current_user_id(),
            'reviewed_at'          => $now,
            'updated_at'           => $now,
        );

        if ( $existing_id ) {
            $updated = $wpdb->update(
                $table,
                $data,
                array( 'id' => (int) $existing_id ),
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ),
                array( '%d' )
            );

            if ( false === $updated ) {
                return new WP_Error( 'wem_ml_translation_update_failed', 'Translation 更新失败。' );
            }
        } else {
            $data['string_id']       = $string_id;
            $data['target_language'] = 'es';
            $data['created_at']      = $now;

            $inserted = $wpdb->insert(
                $table,
                $data,
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
            );

            if ( false === $inserted ) {
                return new WP_Error( 'wem_ml_translation_insert_failed', 'Translation 保存失败。' );
            }
        }

        return self::get_translation( $string_id, 'es' );
    }

    /** @return object|null */
    public static function get_source( $string_id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wem_ml_strings';

        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $string_id ) )
        );
    }

    /** @return object|null */
    public static function get_source_by_context( $context_key ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wem_ml_strings';

        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE context_key = %s LIMIT 1", sanitize_text_field( $context_key ) )
        );
    }

    /** @return object|null */
    public static function get_translation( $string_id, $language ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wem_ml_translations';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE string_id = %d AND target_language = %s
                 LIMIT 1",
                absint( $string_id ),
                sanitize_key( $language )
            )
        );
    }

    /**
     * A translation is stale when it was produced from an older source hash.
     *
     * @param object $source      Source row.
     * @param object $translation Translation row.
     * @return bool
     */
    public static function is_stale( $source, $translation ) {
        if ( ! $source || ! $translation ) {
            return false;
        }

        return ! hash_equals(
            (string) $source->source_hash,
            (string) $translation->translated_from_hash
        );
    }

    /**
     * Return post_title units for the experimental admin table.
     *
     * @return array<int,object>
     */
    public static function get_title_units() {
        global $wpdb;

        $strings      = $wpdb->prefix . 'wem_ml_strings';
        $translations = $wpdb->prefix . 'wem_ml_translations';

        return $wpdb->get_results(
            "SELECT
                s.id,
                s.object_type,
                s.object_id,
                s.context_key,
                s.source_text,
                s.source_hash,
                s.updated_at,
                t.translated_text,
                t.translated_from_hash,
                t.status AS translation_status,
                t.origin AS translation_origin
             FROM {$strings} s
             LEFT JOIN {$translations} t
               ON t.string_id = s.id
              AND t.target_language = 'es'
             WHERE s.field_key = 'post_title'
             ORDER BY s.id DESC"
        );
    }
}
