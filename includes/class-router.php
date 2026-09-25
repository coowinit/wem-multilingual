<?php
/**
 * Native routing layer for WEM Multilingual.
 *
 * v0.1.0 Experimental Core.
 *
 * Route source of truth:
 * object_id + language + translated_slug
 *
 * Step 6B adds Object Language State as the publication gate:
 * draft     -> target-language route is not public
 * published -> target-language route may resolve and generate permalinks
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

    /** @var string */
    private static $language_state = '';

    /** @var bool */
    private static $route_gated = false;

    /**
     * Register hooks.
     */
    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_rewrite_rules' ) );
        add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
        add_filter( 'request', array( __CLASS__, 'resolve_prefixed_request' ) );
        add_filter( 'pre_handle_404', array( __CLASS__, 'force_gated_route_404' ), 10, 2 );
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
     * 1. translated slug repository
     * 2. source-path pass-through (temporary experimental fallback)
     *
     * Every resolved target-language object must pass Object Language State.
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

        if ( '' === $path ) {
            self::resolve_front_page( $query_vars );
            return $query_vars;
        }

        if ( false === strpos( $path, '/' ) ) {
            $resolved = WEM_ML_Slug_Repository::resolve( 'es', $path );

            if ( $resolved ) {
                $post = get_post( (int) $resolved->object_id );

                if (
                    $post
                    && $post->post_type === $resolved->object_type
                    && in_array( $post->post_type, array( 'page', 'post' ), true )
                ) {
                    self::$resolution_mode = 'translated-slug';

                    if ( ! self::allow_target_object( $post ) ) {
                        return $query_vars;
                    }

                    self::$resolved_object_id = (int) $post->ID;
                    self::apply_object_query( $query_vars, $post );
                    return $query_vars;
                }
            }
        }

        // Temporary Step 3 fallback: resolve the prefixed source path through WordPress.
        $source_url = home_url( user_trailingslashit( $path ) );
        $object_id  = url_to_postid( $source_url );

        if ( $object_id <= 0 ) {
            return $query_vars;
        }

        $post = get_post( $object_id );

        if ( ! $post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
            return $query_vars;
        }

        self::$resolution_mode = 'source-path';

        if ( ! self::allow_target_object( $post ) ) {
            return $query_vars;
        }

        self::$resolved_object_id = (int) $post->ID;
        self::apply_object_query( $query_vars, $post );

        return $query_vars;
    }

    /**
     * Apply Object Language State publication gate.
     *
     * Missing rows are treated as draft by WEM_ML_Object_State.
     *
     * @param WP_Post $post Source WordPress object.
     * @return bool
     */
    private static function allow_target_object( $post ) {
        $status = WEM_ML_Object_State::get_status(
            $post->post_type,
            (int) $post->ID,
            'es'
        );

        self::$language_state = $status;

        if ( 'published' !== $status ) {
            self::$route_gated = true;
            return false;
        }

        return true;
    }

    /**
     * Force a gated multilingual route into WordPress's real 404 state.
     *
     * Merely declining to resolve the object is not sufficient on all themes
     * and permalink setups. This filter makes the publication gate explicit.
     *
     * @param bool|null $preempt  Whether WordPress 404 handling is preempted.
     * @param WP_Query  $wp_query Main query.
     * @return bool|null
     */
    public static function force_gated_route_404( $preempt, $wp_query ) {
        if ( ! self::$route_gated || 'es' !== WEM_ML_Language_Context::get_current_language() ) {
            return $preempt;
        }

        if ( $wp_query instanceof WP_Query ) {
            $wp_query->set_404();
        }

        status_header( 404 );
        nocache_headers();

        return true;
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
     * Preserve WEM target-language routes from WordPress canonical redirects.
     *
     * Resolved published routes and explicitly gated draft routes must both
     * remain on the requested multilingual URL. Gated routes are handled as
     * real 404 responses by force_gated_route_404().
     *
     * @param string|false $redirect_url  Canonical redirect URL.
     * @param string       $requested_url Requested URL.
     * @return string|false
     */
    public static function preserve_multilingual_route( $redirect_url, $requested_url ) {
        if ( ! self::$route_matched ) {
            return $redirect_url;
        }

        if ( 'es' !== WEM_ML_Language_Context::get_current_language() ) {
            return $redirect_url;
        }

        if ( self::$route_gated || self::$resolved_object_id > 0 ) {
            return false;
        }

        return $redirect_url;
    }

    /**
     * Filter Page permalinks in Spanish context.
     *
     * @param string $url     Original permalink.
     * @param int    $post_id Page ID.
     * @param bool   $sample  Whether this is a sample permalink.
     * @return string
     */
    public static function filter_page_link( $url, $post_id, $sample ) {
        if ( $sample ) {
            return $url;
        }

        if ( 'es' !== WEM_ML_Language_Context::get_current_language() ) {
            return $url;
        }

        return self::get_localized_permalink( 'page', (int) $post_id, 'es', $url );
    }

    /**
     * Filter Post permalinks in Spanish context.
     *
     * @param string  $url       Original permalink.
     * @param WP_Post $post      Post object.
     * @param bool    $leavename Whether to keep the post name token.
     * @return string
     */
    public static function filter_post_link( $url, $post, $leavename ) {
        if ( $leavename || ! $post instanceof WP_Post ) {
            return $url;
        }

        if ( 'es' !== WEM_ML_Language_Context::get_current_language() ) {
            return $url;
        }

        return self::get_localized_permalink( 'post', (int) $post->ID, 'es', $url );
    }

    /**
     * Build a localized permalink independently of the current request context.
     *
     * This is the single reusable URL-generation rule used by both frontend
     * permalink filters and the admin diagnostics lab.
     *
     * @param string      $object_type  page or post.
     * @param int         $object_id    WordPress object ID.
     * @param string      $language     Target language code.
     * @param string|null $fallback_url Optional source permalink fallback.
     * @return string
     */
    public static function get_localized_permalink( $object_type, $object_id, $language = 'es', $fallback_url = null ) {
        $object_type = sanitize_key( $object_type );
        $object_id   = absint( $object_id );
        $language    = sanitize_key( $language );

        if ( null === $fallback_url ) {
            $fallback_url = get_permalink( $object_id );
        }

        if ( 'es' !== $language ) {
            return (string) $fallback_url;
        }

        if ( ! WEM_ML_Object_State::is_published( $object_type, $object_id, $language ) ) {
            return (string) $fallback_url;
        }

        $slug = WEM_ML_Slug_Repository::get_slug( $object_type, $object_id, $language );

        if ( null === $slug || '' === $slug ) {
            return (string) $fallback_url;
        }

        return home_url( user_trailingslashit( $language . '/' . $slug ) );
    }

    /**
     * Resolve /es/ to the configured front page when published for Spanish.
     *
     * @param array<string,mixed> $query_vars Parsed request vars, by reference.
     */
    private static function resolve_front_page( &$query_vars ) {
        if ( 'page' !== get_option( 'show_on_front' ) ) {
            return;
        }

        $front_page_id = (int) get_option( 'page_on_front' );

        if ( $front_page_id <= 0 ) {
            return;
        }

        $post = get_post( $front_page_id );

        if ( ! $post || 'page' !== $post->post_type ) {
            return;
        }

        self::$resolution_mode = 'front-page';

        if ( ! self::allow_target_object( $post ) ) {
            return;
        }

        self::$resolved_object_id = $front_page_id;
        unset( $query_vars['wem_ml_path'] );
        $query_vars['page_id'] = $front_page_id;
    }

    /**
     * Experimental diagnostic headers for routing/state validation.
     *
     * @param array<string,string> $headers Response headers.
     * @return array<string,string>
     */
    public static function add_debug_headers( $headers ) {
        if ( ! self::$route_matched ) {
            return $headers;
        }

        if ( self::$route_gated ) {
            $headers['X-WEM-ML-Route'] = 'gated';
        } else {
            $headers['X-WEM-ML-Route'] = self::$resolved_object_id > 0
                ? 'resolved'
                : 'unresolved';
        }

        if ( self::$resolved_object_id > 0 ) {
            $headers['X-WEM-ML-Object-ID'] = (string) self::$resolved_object_id;
        }

        if ( '' !== self::$resolution_mode ) {
            $headers['X-WEM-ML-Route-Mode'] = self::$resolution_mode;
        }

        if ( '' !== self::$language_state ) {
            $headers['X-WEM-ML-State'] = self::$language_state;
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
