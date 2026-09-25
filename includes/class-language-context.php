<?php
/**
 * Language context for WEM Multilingual.
 *
 * v0.1.0 Experimental Core supports only:
 * - English as the source language.
 * - Spanish as the target language using the /es/ URL prefix.
 *
 * This class only detects the current language. It does not perform routing.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WEM_ML_Language_Context {

    /**
     * Source language code.
     */
    private const SOURCE_LANGUAGE = 'en';

    /**
     * Supported target language prefixes for v0.1.0.
     *
     * @var string[]
     */
    private const TARGET_LANGUAGES = array( 'es' );

    /**
     * Current request language.
     *
     * @var string
     */
    private static $current_language = self::SOURCE_LANGUAGE;

    /**
     * Initialize language detection for the current request.
     */
    public static function init() {
        self::$current_language = self::detect_from_request_uri();

        add_filter( 'wp_headers', array( __CLASS__, 'add_debug_header' ) );
    }

    /**
     * Return the source language.
     *
     * @return string
     */
    public static function get_source_language() {
        return self::SOURCE_LANGUAGE;
    }

    /**
     * Return the current request language.
     *
     * @return string
     */
    public static function get_current_language() {
        return self::$current_language;
    }

    /**
     * Whether the current request is using the source language.
     *
     * @return bool
     */
    public static function is_source_language() {
        return self::SOURCE_LANGUAGE === self::$current_language;
    }

    /**
     * Detect the language from the first public URL path segment.
     *
     * Important: this method reads REQUEST_URI but never mutates it.
     * Routing remains the responsibility of the Router layer.
     *
     * @return string
     */
    private static function detect_from_request_uri() {
        $request_uri = isset( $_SERVER['REQUEST_URI'] )
            ? wp_unslash( $_SERVER['REQUEST_URI'] )
            : '/';

        $request_path = wp_parse_url( $request_uri, PHP_URL_PATH );
        $home_path    = wp_parse_url( home_url( '/' ), PHP_URL_PATH );

        $request_path = is_string( $request_path ) ? $request_path : '/';
        $home_path    = is_string( $home_path ) ? $home_path : '/';

        $relative_path = self::strip_home_path( $request_path, $home_path );
        $segments      = array_values(
            array_filter(
                explode( '/', trim( $relative_path, '/' ) ),
                'strlen'
            )
        );

        if ( empty( $segments ) ) {
            return self::SOURCE_LANGUAGE;
        }

        $first_segment = strtolower( sanitize_key( $segments[0] ) );

        if ( in_array( $first_segment, self::TARGET_LANGUAGES, true ) ) {
            return $first_segment;
        }

        return self::SOURCE_LANGUAGE;
    }

    /**
     * Remove the WordPress home path when the site lives in a subdirectory.
     *
     * Example:
     * /wordpress/es/about-us/ with home path /wordpress/
     * becomes /es/about-us/.
     *
     * @param string $request_path Current request path.
     * @param string $home_path    WordPress home path.
     * @return string
     */
    private static function strip_home_path( $request_path, $home_path ) {
        $normalized_home = '/' . trim( $home_path, '/' );

        if ( '/' === $normalized_home ) {
            return $request_path;
        }

        if ( $request_path === $normalized_home ) {
            return '/';
        }

        $prefix = trailingslashit( $normalized_home );

        if ( 0 === strpos( trailingslashit( $request_path ), $prefix ) ) {
            return '/' . ltrim( substr( $request_path, strlen( $normalized_home ) ), '/' );
        }

        return $request_path;
    }

    /**
     * Experimental diagnostic header used only to verify Language Context.
     *
     * Adding the value through WordPress' wp_headers filter is more reliable
     * than sending an additional raw PHP header later in the request lifecycle.
     *
     * @param array $headers Response headers prepared by WordPress.
     * @return array
     */
    public static function add_debug_header( $headers ) {
        $headers['X-WEM-ML-Language'] = self::$current_language;

        return $headers;
    }
}
