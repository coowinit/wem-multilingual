<?php
/**
 * Targeted cache invalidation service.
 *
 * v0.1.1 Step 5C-2:
 * - Resolves the source and Spanish URLs for one WEM object.
 * - Prefers WP Rocket URL-level purge via rocket_clean_files().
 * - Falls back to SiteGround URL purge only when WP Rocket URL purge is unavailable.
 * - Never performs a full-domain purge.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WEM_ML_Cache_Invalidator {

    /**
     * Resolve the cache URLs owned by one WordPress object.
     *
     * @param int $object_id WordPress object ID.
     * @return array|WP_Error
     */
    public static function resolve_object_urls( $object_id ) {
        $object_id = absint( $object_id );
        $post      = $object_id > 0 ? get_post( $object_id ) : null;

        if ( ! $post || 'revision' === $post->post_type ) {
            return new WP_Error( 'wem_ml_cache_object_missing', '没有找到可清理缓存的 WordPress Content Object。' );
        }

        $source_url = WEM_ML_Router::get_source_permalink( $object_id );
        $slug       = WEM_ML_Slug_Repository::get_slug( $post->post_type, $object_id, 'es' );
        $spanish_url = $slug ? home_url( user_trailingslashit( 'es/' . $slug ) ) : '';

        $urls = array();

        if ( $source_url ) {
            $urls[] = esc_url_raw( $source_url );
        }

        if ( $spanish_url ) {
            $urls[] = esc_url_raw( $spanish_url );
        }

        $urls = array_values( array_unique( array_filter( $urls ) ) );

        if ( empty( $urls ) ) {
            return new WP_Error( 'wem_ml_cache_urls_missing', '当前对象没有可用于缓存清理的 EN / ES URL。' );
        }

        return array(
            'object_id'   => $object_id,
            'object_type' => (string) $post->post_type,
            'source_url'  => $source_url ? esc_url_raw( $source_url ) : '',
            'spanish_url' => $spanish_url ? esc_url_raw( $spanish_url ) : '',
            'urls'        => $urls,
        );
    }

    /**
     * Purge only the URLs associated with one object.
     *
     * @param int $object_id WordPress object ID.
     * @return array|WP_Error
     */
    public static function purge_object( $object_id, $extra_urls = array() ) {
        $resolved = self::resolve_object_urls( $object_id );

        if ( is_wp_error( $resolved ) ) {
            return $resolved;
        }

        $urls = array_merge(
            $resolved['urls'],
            is_array( $extra_urls ) ? $extra_urls : array()
        );

        $urls = array_values(
            array_unique(
                array_filter(
                    array_map( 'esc_url_raw', $urls )
                )
            )
        );

        return self::purge_urls( $urls, $resolved );
    }

    /**
     * Purge a specific list of URLs without ever falling back to a full-domain purge.
     *
     * @param array $urls     Absolute URLs.
     * @param array $context Optional result context.
     * @return array|WP_Error
     */
    public static function purge_urls( $urls, $context = array() ) {
        $urls = array_values(
            array_unique(
                array_filter(
                    array_map( 'esc_url_raw', is_array( $urls ) ? $urls : array() )
                )
            )
        );

        if ( empty( $urls ) ) {
            return new WP_Error(
                'wem_ml_cache_urls_missing',
                '没有可用于缓存清理的目标 URL。'
            );
        }

        if ( function_exists( 'rocket_clean_files' ) ) {
            rocket_clean_files( $urls );

            return array_merge(
                $context,
                array(
                    'provider' => 'wp-rocket',
                    'method'   => 'rocket_clean_files',
                    'purged'   => $urls,
                )
            );
        }

        if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
            foreach ( $urls as $url ) {
                sg_cachepress_purge_cache( $url );
            }

            return array_merge(
                $context,
                array(
                    'provider' => 'siteground',
                    'method'   => 'sg_cachepress_purge_cache',
                    'purged'   => $urls,
                )
            );
        }

        return new WP_Error(
            'wem_ml_cache_provider_unavailable',
            '没有检测到可用的目标 URL 缓存清理接口。'
        );
    }
}
