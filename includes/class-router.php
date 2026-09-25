<?php
/**
 * Native routing layer for WEM Multilingual.
 *
 * v0.1.0 Experimental Core.
 *
 * Step 4B adds the real translated-slug route model:
 *
 * /about-evodek/       -> source request
 * /es/acerca-de-evodek/ -> same WordPress object in Spanish context
 *
 * Route source of truth remains:
 * object_id + language + translated_slug
 *
 * The Step 3 source-path pass-through remains as a temporary experimental
 * fallback so already validated routes keep working during development.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WEM_ML_Router {

    /** @var bool */
    private static $route_matched = false;

    /** @var int */
    private static $resolved_object_id = 0;

    /** @var string */
    private static $resolution_mode = '';

    /**
     * Register hooks.
     */
    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_rewrite_rules' ) );
        add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
        add_filter( 'request', array( __CLASS__, 'resolve_prefixed_request' ) );
        add_filter( 'redirect_canonical', array( __CLASS__, 'preserve_multilingual_route' ), 10, 2 );

        // Outgoing target-language permalinks.
        add_filter( 'page_link', array( __CLASS__, 'filter_page_link' ), 10, 3 );
        add_filter( 'post_link', array( __CLASS__, 'filter_post_link' ), 10, 3 );

        add_filter( 'wp_headers', array( __CLASS__, 'add_debug_headers' ) );
    }

    /**
     * Register the minimal Spanish-prefix rewrite rules.
     *
     * Important: these rules do not mutate REQUEST_URI.
     */
    public static function register_rewrite_rules() {
        add_rewrite_rule(
            '^es/?$',
            'index.php?wem_ml_lang=es&wem_ml_path=',
            'top'
        );

        add_rewrite_rule(
            '^es/(.+?)/?$',
            'index.php?wem_ml_lang=es&wem_ml_path=$matches[1]',
            'top'
        );
    }

    /**
     * Whitelist WEM query vars.
     *
     * @param string[] $vars Public query vars.
     * @return string[]
     */
    public static function register_query_vars( $vars ) {
        $vars[] = 'wem_ml_lang';
        $vars[] = 'wem_ml_path';

        return $vars;
    }

    /**
     * Resolve /es/{path}/ to the existing source object.
     *
     * Resolution order:
     * 1. translated slug repository (real v0.1.0 route model)
     * 2. source-path pass-through (Step 3 experimental fallback)
     *
     * @param array<string,mixed> $query_vars Parsed request vars.
     * @return array<string,mixed>
     */
    public static function resolve_prefixed_request( $query_vars ) {
        if ( empty( $query_vars['wem_ml_lang'] ) || 'es' !== $query_vars['wem_ml_lang'] ) {
            return $query_vars;
        }

        self::$route_matched = true;

        $path = isset( $query_vars['wem_ml_path'] )
            ? trim( (string) $query_vars['wem_ml_path'], '/' )
            : '';

        // /es/ represents the same front-page request in Spanish context.
        if ( '' === $path ) {
            self::apply_front_page_query( $query_vars );
            self::$resolution_mode = self::$resolved_object_id > 0 ? 'front-page' : '';
            return $query_vars;
        }

        // Step 4B: translated slugs currently represent one object slug.
        // Multi-level translated parent paths are deliberately deferred.
        if ( false === strpos( $path, '/' ) ) {
            $resolved = WEM_ML_Slug_Repository::resolve( 'es', $path );

            if ( $resolved ) {
                $post = get_post( (int) $resolved->object_id );

                if (
                    $post
                    && $post->post_type === $resolved->object_type
                    && in_array( $post->post_type, array( 'page', 'post' ), true )
                ) {
                    self::$resolved_object_id = (int) $resolved->object_id;
                    self::$resolution_mode    = 'translated-slug';
                    self::apply_object_query( $query_vars, $post );
                    return $query_vars;
                }
            }
        }

        // Step 3 fallback: resolve the prefixed source path through WordPress.
        $source_url = home_url( user_trailingslashit( $path ) );
        $object_id  = url_to_postid( $source_url );

        if ( $object_id <= 0 ) {
            return $query_vars;
        }

        $post = get_post( $object_id );

        if ( ! $post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
            return $query_vars;
        }

        self::$resolved_object_id = (int) $object_id;
        self::$resolution_mode    = 'source-path';
        self::apply_object_query( $query_vars, $post );

        return $query_vars;
    }

    /**
     * Apply a resolved Page/Post object to the main WordPress query.
     *
     * @param array<string,mixed> $query_vars Parsed request vars, by reference.
     * @param WP_Post             $post       Resolved source object.
     */
    private static function apply_object_query( &$query_vars, $post ) {
        unset( $query_vars['wem_ml_path'] );

        if ( 'page' === $post->post_type ) {
            $query_vars['page_id'] = (int) $post->ID;
            unset( $query_vars['p'] );
            return;
        }

        $query_vars['p'] = (int) $post->ID;
        unset( $query_vars['page_id'] );
    }

    /**
     * Prevent WordPress from redirecting a successfully resolved WEM target
     * language request back to the source-language permalink.
     *
     * @param string|false $redirect_url  Canonical redirect URL.
     * @param string       $requested_url Requested URL.
     * @return string|false
     */
    public static function preserve_multilingual_route( $redirect_url, $requested_url ) {
        if ( ! self::$route_matched || self::$resolved_object_id <= 0 ) {
            return $redirect_url;
        }

        if ( 'es' !== WEM_ML_Language_Context::get_current_language() ) {
            return $redirect_url;
        }

        return false;
    }

    /**
     * Filter Page permalinks in Spanish context.
     *
     * @param string $url       Original permalink.
     * @param int    $post_id   Page ID.
     * @param bool   $sample    Whether this is a sample permalink.
     * @return string
     */
    public static function filter_page_link( $url, $post_id, $sample ) {
        if ( $sample ) {
            return $url;
        }

        return self::get_target_permalink( $url, 'page', (int) $post_id );
    }

    /**
     * Filter Post permalinks in Spanish context.
     *
     * @param string  $url   Original permalink.
     * @param WP_Post $post  Post object.
     * @param bool    $leavename Whether to keep the post name token.
     * @return string
     */
    public static function filter_post_link( $url, $post, $leavename ) {
        if ( $leavename || ! $post instanceof WP_Post ) {
            return $url;
        }

        return self::get_target_permalink( $url, 'post', (int) $post->ID );
    }

    /**
     * Build the target-language permalink when a translated slug exists.
     *
     * No mapping means the original WordPress permalink is preserved.
     * This avoids inventing multilingual URLs for objects that have not been
     * explicitly registered in the route repository.
     *
     * @param string $original_url Original WordPress permalink.
     * @param string $object_type  page or post.
     * @param int    $object_id    Object ID.
     * @return string
     */
    private static function get_target_permalink( $original_url, $object_type, $object_id ) {
        if ( 'es' !== WEM_ML_Language_Context::get_current_language() ) {
            return $original_url;
        }

        $slug = WEM_ML_Slug_Repository::get_slug( $object_type, $object_id, 'es' );

        if ( null === $slug || '' === $slug ) {
            return $original_url;
        }

        return home_url( user_trailingslashit( 'es/' . $slug ) );
    }

    /**
     * Make /es/ reuse the site's existing front-page configuration.
     *
     * @param array<string,mixed> $query_vars Parsed request vars, by reference.
     */
    private static function apply_front_page_query( &$query_vars ) {
        unset( $query_vars['wem_ml_path'] );

        if ( 'page' !== get_option( 'show_on_front' ) ) {
            return;
        }

        $front_page_id = (int) get_option( 'page_on_front' );

        if ( $front_page_id <= 0 ) {
            return;
        }

        self::$resolved_object_id = $front_page_id;
        $query_vars['page_id']    = $front_page_id;
    }

    /**
     * Experimental diagnostic headers for routing validation.
     *
     * @param array<string,string> $headers Response headers.
     * @return array<string,string>
     */
    public static function add_debug_headers( $headers ) {
        if ( ! self::$route_matched ) {
            return $headers;
        }

        $headers['X-WEM-ML-Route'] = self::$resolved_object_id > 0
            ? 'resolved'
            : 'unresolved';

        if ( self::$resolved_object_id > 0 ) {
            $headers['X-WEM-ML-Object-ID'] = (string) self::$resolved_object_id;
        }

        if ( '' !== self::$resolution_mode ) {
            $headers['X-WEM-ML-Route-Mode'] = self::$resolution_mode;
        }

        return $headers;
    }

    /**
     * Activation task: install current rewrite rules and flush once.
     */
    public static function activate() {
        self::register_rewrite_rules();
        flush_rewrite_rules();
    }

    /**
     * Deactivation task: remove WEM rules from the persisted rewrite set.
     */
    public static function deactivate() {
        flush_rewrite_rules();
    }
}
