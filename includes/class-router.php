<?php
/**
 * Native routing layer for WEM Multilingual.
 *
 * Step 3 of v0.1.0 Experimental Core.
 *
 * This stage intentionally uses a source-path pass-through:
 *
 * /about-us/    -> source request
 * /es/about-us/ -> same WordPress object in Spanish context
 *
 * The source path is NOT persisted as multilingual route truth.
 * Translated slug resolution is introduced separately in Step 4.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WEM_ML_Router {

    /** @var bool */
    private static $route_matched = false;

    /** @var int */
    private static $resolved_object_id = 0;

    /**
     * Register hooks.
     */
    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_rewrite_rules' ) );
        add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
        add_filter( 'request', array( __CLASS__, 'resolve_prefixed_request' ) );
        add_filter( 'redirect_canonical', array( __CLASS__, 'preserve_multilingual_route' ), 10, 2 );
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
     * Resolve /es/{source-path}/ back to the existing source object.
     *
     * This is only an Experimental Core bridge used before translated
     * slug lookup is introduced. The source path is never persisted.
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
            return $query_vars;
        }

        // Resolve through WordPress's own permalink/rewrite knowledge.
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

        // Remove the temporary path so it cannot influence the main query.
        unset( $query_vars['wem_ml_path'] );

        if ( 'page' === $post->post_type ) {
            $query_vars['page_id'] = (int) $object_id;
        } else {
            $query_vars['p'] = (int) $object_id;
        }

        return $query_vars;
    }

    /**
     * Prevent WordPress from redirecting a successfully resolved WEM target
     * language request back to the source-language permalink.
     *
     * This is deliberately narrow: canonical redirects remain untouched for
     * ordinary/source-language requests and for unresolved WEM routes.
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
     * Experimental diagnostic headers for Step 3 validation.
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
