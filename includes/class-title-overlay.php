<?php
/**
 * Runtime post_title overlay for WEM Multilingual.
 *
 * Step 7 of v0.1.0 Experimental Core.
 *
 * Source data remains clean in wp_posts. Spanish title output is applied only
 * at runtime when all publication and freshness conditions are satisfied.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WEM_ML_Title_Overlay {

    /** @var string */
    private static $last_state = '';

    /**
     * Register runtime hooks.
     */
    public static function init() {
        add_filter( 'the_title', array( __CLASS__, 'filter_title' ), 20, 2 );
        add_filter( 'wp_headers', array( __CLASS__, 'add_debug_header' ) );
    }

    /**
     * Overlay a Page/Post title in Spanish context when the translation is
     * published, reviewed and current against the live source title.
     *
     * @param string $title   Current title.
     * @param int    $post_id Post ID.
     * @return string
     */
    public static function filter_title( $title, $post_id = 0 ) {
        if ( is_admin() || 'es' !== WEM_ML_Language_Context::get_current_language() ) {
            return $title;
        }

        $post_id = absint( $post_id );

        if ( $post_id <= 0 ) {
            return $title;
        }

        $post = get_post( $post_id );

        if ( ! $post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
            return $title;
        }

        if ( ! WEM_ML_Object_State::is_published( $post->post_type, $post_id, 'es' ) ) {
            self::$last_state = 'not-published';
            return $title;
        }

        $context_key = sprintf( 'post:%d:title', $post_id );
        $source      = WEM_ML_Translation_Repository::get_source_by_context( $context_key );

        if ( ! $source || 'active' !== $source->state ) {
            self::$last_state = 'missing-source';
            return $title;
        }

        $translation = WEM_ML_Translation_Repository::get_translation( (int) $source->id, 'es' );

        if ( ! $translation || 'reviewed' !== $translation->status || '' === trim( (string) $translation->translated_text ) ) {
            self::$last_state = 'missing-translation';
            return $title;
        }

        // Protect against an unsynchronised source edit. The live WordPress
        // title must still match the source version represented by the unit.
        $live_source_hash = WEM_ML_Translation_Repository::hash_text( (string) $post->post_title );

        if ( ! hash_equals( (string) $source->source_hash, $live_source_hash ) ) {
            self::$last_state = 'source-drift';
            return $title;
        }

        if ( WEM_ML_Translation_Repository::is_stale( $source, $translation ) ) {
            self::$last_state = 'stale';
            return $title;
        }

        self::$last_state = 'applied';

        return (string) $translation->translated_text;
    }

    /**
     * Diagnostic header for the current request.
     *
     * @param array<string,string> $headers Response headers.
     * @return array<string,string>
     */
    public static function add_debug_header( $headers ) {
        if ( 'es' === WEM_ML_Language_Context::get_current_language() && '' !== self::$last_state ) {
            $headers['X-WEM-ML-Title-Overlay'] = self::$last_state;
        }

        return $headers;
    }
}
