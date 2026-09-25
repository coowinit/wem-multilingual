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

    /**
     * Register runtime hooks.
     */
    public static function init() {
        add_filter( 'the_title', array( __CLASS__, 'filter_title' ), 20, 2 );
    }

    /**
     * Evaluate whether a post_title translation is safe to overlay.
     *
     * This method is deliberately independent of the current request context so
     * the exact same decision engine can be reused by wp-admin diagnostics.
     *
     * @param int    $post_id  WordPress Page/Post ID.
     * @param string $language Target language. v0.1.0: es.
     * @return array<string,mixed>
     */
    public static function evaluate( $post_id, $language = 'es' ) {
        $post_id  = absint( $post_id );
        $language = sanitize_key( $language );
        $post     = $post_id > 0 ? get_post( $post_id ) : null;

        $result = array(
            'state'                  => 'invalid-object',
            'can_overlay'            => false,
            'source_title'           => '',
            'translated_title'       => '',
            'live_source_hash'       => '',
            'stored_source_hash'     => '',
            'translated_from_hash'   => '',
            'object_language_status' => 'draft',
        );

        if ( ! $post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
            return $result;
        }

        $result['source_title']     = (string) $post->post_title;
        $result['live_source_hash'] = WEM_ML_Translation_Repository::hash_text( (string) $post->post_title );

        if ( 'es' !== $language ) {
            $result['state'] = 'unsupported-language';
            return $result;
        }

        $status = WEM_ML_Object_State::get_status( $post->post_type, $post_id, $language );
        $result['object_language_status'] = $status;

        if ( 'published' !== $status ) {
            $result['state'] = 'not-published';
            return $result;
        }

        $context_key = sprintf( 'post:%d:title', $post_id );
        $source      = WEM_ML_Translation_Repository::get_source_by_context( $context_key );

        if ( ! $source || 'active' !== $source->state ) {
            $result['state'] = 'missing-source';
            return $result;
        }

        $result['stored_source_hash'] = (string) $source->source_hash;

        $translation = WEM_ML_Translation_Repository::get_translation( (int) $source->id, $language );

        if ( ! $translation || 'reviewed' !== $translation->status || '' === trim( (string) $translation->translated_text ) ) {
            $result['state'] = 'missing-translation';
            return $result;
        }

        $result['translated_title']     = (string) $translation->translated_text;
        $result['translated_from_hash'] = (string) $translation->translated_from_hash;

        // Protect against an unsynchronised source edit.
        if ( ! hash_equals( (string) $source->source_hash, (string) $result['live_source_hash'] ) ) {
            $result['state'] = 'source-drift';
            return $result;
        }

        if ( WEM_ML_Translation_Repository::is_stale( $source, $translation ) ) {
            $result['state'] = 'stale';
            return $result;
        }

        $result['state']       = 'applied';
        $result['can_overlay'] = true;

        return $result;
    }

    /**
     * Overlay a Page/Post title in Spanish context.
     *
     * @param string $title   Current title.
     * @param int    $post_id Post ID.
     * @return string
     */
    public static function filter_title( $title, $post_id = 0 ) {
        if ( is_admin() || 'es' !== WEM_ML_Language_Context::get_current_language() ) {
            return $title;
        }

        $evaluation = self::evaluate( $post_id, 'es' );

        if ( ! empty( $evaluation['can_overlay'] ) ) {
            return (string) $evaluation['translated_title'];
        }

        return $title;
    }
}
