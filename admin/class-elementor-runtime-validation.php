<?php
/**
 * Read-only Elementor Runtime Validation Lab.
 *
 * v0.1.1 Step 5B diagnostics:
 * - No writes, no cache purge, no translation/state mutation.
 * - Compares repository/runtime decision with actual EN/ES frontend output.
 * - Heading only for the current experiment scope.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WEM_ML_Elementor_Runtime_Validation {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
    }

    public static function register_menu() {
        add_management_page(
            'WEM ML Elementor Runtime Validation',
            'WEM ML Elementor Runtime Validation',
            'manage_options',
            'wem-multilingual-elementor-runtime-validation',
            array( __CLASS__, 'render_page' )
        );
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $object_id = isset( $_GET['object_id'] ) ? absint( $_GET['object_id'] ) : 0;
        $report    = null;

        if ( $object_id > 0 ) {
            $report = self::build_report( $object_id );
        }
        ?>
        <div class="wrap">
            <h1>WEM ML Elementor Runtime Validation</h1>
            <p><strong>v0.1.1 · Step 5 Runtime Validation</strong></p>
            <p>一键只读检查 Routing / State / Source Unit / Translation / Runtime Decision，并请求 EN / ES 前台页面核对实际 Heading 输出。不会清缓存，也不会修改任何数据。</p>

            <form method="get" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>">
                <input type="hidden" name="page" value="wem-multilingual-elementor-runtime-validation">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="wem-ml-runtime-validation-object-id">Content Object ID</label></th>
                        <td>
                            <input id="wem-ml-runtime-validation-object-id" name="object_id" type="number" min="1" required class="small-text" value="<?php echo $object_id ? esc_attr( (string) $object_id ) : ''; ?>">
                            <p class="description">当前 Step 5 建议测试 Page <code>#1730</code>。</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( '运行 Runtime Validation', 'primary' ); ?>
            </form>

            <?php if ( is_wp_error( $report ) ) : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html( $report->get_error_message() ); ?></p></div>
            <?php elseif ( is_array( $report ) ) : ?>
                <?php self::render_report( $report ); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function build_report( $object_id ) {
        $post = get_post( $object_id );

        if ( ! $post || 'revision' === $post->post_type ) {
            return new WP_Error( 'wem_ml_validation_object_missing', '没有找到可验证的 WordPress Content Object。' );
        }

        if ( ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
            return new WP_Error( 'wem_ml_validation_object_type', 'Step 5 Runtime Validation 当前只验证 Page / Post。' );
        }

        $discovery = WEM_ML_Elementor_Source_Discovery::discover( $object_id );

        if ( is_wp_error( $discovery ) ) {
            return $discovery;
        }

        $headings = array_values(
            array_filter(
                $discovery['fields'],
                static function ( $field ) {
                    return isset( $field['widget_type'], $field['setting_path'] )
                        && 'heading' === $field['widget_type']
                        && 'title' === $field['setting_path'];
                }
            )
        );

        if ( empty( $headings ) ) {
            return new WP_Error( 'wem_ml_validation_no_heading', '当前对象没有 Adapter 可识别的 Heading.title。' );
        }

        $slug        = WEM_ML_Slug_Repository::get_slug( $post->post_type, $object_id, 'es' );
        $state       = WEM_ML_Object_State::get_status( $post->post_type, $object_id, 'es' );
        $source_url  = WEM_ML_Router::get_source_permalink( $object_id );
        $spanish_url = $slug ? home_url( user_trailingslashit( 'es/' . $slug ) ) : '';

        $en_fetch = self::fetch_frontend( $source_url );
        $es_fetch = $spanish_url ? self::fetch_frontend( $spanish_url ) : array(
            'ok' => false,
            'code' => 0,
            'body' => '',
            'error' => 'Missing Spanish slug.',
        );

        $rows = array();

        foreach ( $headings as $field ) {
            $source      = WEM_ML_Source_Unit_Repository::get_by_context( $field['context_key'] );
            $translation = $source ? WEM_ML_Translation_Repository::get_translation( (int) $source->id, 'es' ) : null;

            $runtime_state = 'missing-source';
            $expected_es   = (string) $field['source_text'];

            if ( $source && 'elementor_widget_field' === (string) $source->context_type && 'active' === (string) $source->state ) {
                if ( ! hash_equals( (string) $source->source_hash, (string) $field['source_hash'] ) ) {
                    $runtime_state = 'source-drift';
                } elseif ( ! $translation || 'reviewed' !== (string) $translation->status || '' === trim( (string) $translation->translated_text ) ) {
                    $runtime_state = 'missing-translation';
                } elseif ( WEM_ML_Translation_Repository::is_stale( $source, $translation ) ) {
                    $runtime_state = 'stale';
                } elseif ( 'published' !== $state ) {
                    $runtime_state = 'not-published';
                } else {
                    $runtime_state = 'applied';
                    $expected_es   = (string) $translation->translated_text;
                }
            }

            $actual_en = $en_fetch['ok'] ? self::extract_heading( $en_fetch['body'], $field['element_id'] ) : '';
            $actual_es = $es_fetch['ok'] ? self::extract_heading( $es_fetch['body'], $field['element_id'] ) : '';

            $en_match = '' !== $actual_en && self::same_text( $actual_en, (string) $field['source_text'] );
            $es_match = '' !== $actual_es && self::same_text( $actual_es, $expected_es );

            $cache_suspected = false;
            if (
                ! $es_match
                && $translation
                && 'applied' !== $runtime_state
                && '' !== $actual_es
                && self::same_text( $actual_es, (string) $translation->translated_text )
            ) {
                $cache_suspected = true;
            }

            $rows[] = array(
                'element_id'            => $field['element_id'],
                'context_key'           => $field['context_key'],
                'source_text'           => (string) $field['source_text'],
                'live_source_hash'      => (string) $field['source_hash'],
                'source_unit_id'        => $source ? (int) $source->id : 0,
                'stored_source_hash'    => $source ? (string) $source->source_hash : '',
                'translated_text'       => $translation ? (string) $translation->translated_text : '',
                'translated_from_hash'  => $translation ? (string) $translation->translated_from_hash : '',
                'runtime_state'         => $runtime_state,
                'expected_es'           => $expected_es,
                'actual_en'             => $actual_en,
                'actual_es'             => $actual_es,
                'en_match'              => $en_match,
                'es_match'              => $es_match,
                'cache_suspected'       => $cache_suspected,
            );
        }

        $overall_pass = true;
        foreach ( $rows as $row ) {
            if ( ! $row['en_match'] || ! $row['es_match'] ) {
                $overall_pass = false;
                break;
            }
        }

        return array(
            'object_id'        => $object_id,
            'object_type'      => $post->post_type,
            'source_json_hash' => $discovery['source_json_hash'],
            'state'            => $state,
            'slug'             => $slug,
            'source_url'       => $source_url,
            'spanish_url'      => $spanish_url,
            'en_fetch'         => $en_fetch,
            'es_fetch'         => $es_fetch,
            'rows'             => $rows,
            'overall_pass'     => $overall_pass,
        );
    }

    private static function fetch_frontend( $url ) {
        if ( ! $url ) {
            return array( 'ok' => false, 'code' => 0, 'body' => '', 'error' => 'URL unavailable.' );
        }

        $response = wp_remote_get(
            $url,
            array(
                'timeout'     => 15,
                'redirection' => 3,
                'headers'     => array(
                    'User-Agent' => 'WEM-ML-Runtime-Validation/' . WEM_ML_VERSION,
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return array( 'ok' => false, 'code' => 0, 'body' => '', 'error' => $response->get_error_message() );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = (string) wp_remote_retrieve_body( $response );

        return array(
            'ok'    => $code >= 200 && $code < 300 && '' !== $body,
            'code'  => $code,
            'body'  => $body,
            'error' => '',
        );
    }

    private static function extract_heading( $html, $element_id ) {
        if ( '' === $html || '' === $element_id ) {
            return '';
        }

        if ( class_exists( 'DOMDocument' ) ) {
            $previous = libxml_use_internal_errors( true );
            $dom      = new DOMDocument();
            $loaded   = $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );

            if ( $loaded ) {
                $xpath = new DOMXPath( $dom );
                $query = sprintf(
                    '//*[@data-id="%s"]//*[contains(concat(" ", normalize-space(@class), " "), " elementor-heading-title ")]',
                    $element_id
                );
                $nodes = $xpath->query( $query );

                if ( $nodes && $nodes->length > 0 ) {
                    $text = trim( html_entity_decode( $nodes->item( 0 )->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
                    libxml_clear_errors();
                    libxml_use_internal_errors( $previous );
                    return $text;
                }
            }

            libxml_clear_errors();
            libxml_use_internal_errors( $previous );
        }

        $pattern = '/data-id=["\']' . preg_quote( $element_id, '/' ) . '["\'][\s\S]{0,5000}?class=["\'][^"\']*elementor-heading-title[^"\']*["\'][^>]*>([\s\S]*?)<\/h[1-6]>/i';
        if ( preg_match( $pattern, $html, $matches ) ) {
            return trim( wp_strip_all_tags( html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
        }

        return '';
    }

    private static function same_text( $a, $b ) {
        return WEM_ML_Translation_Repository::normalize_text( (string) $a )
            === WEM_ML_Translation_Repository::normalize_text( (string) $b );
    }

    private static function render_report( $report ) {
        $overall_class = $report['overall_pass'] ? 'notice notice-success inline' : 'notice notice-warning inline';
        $overall_text  = $report['overall_pass'] ? 'PASS' : 'CHECK REQUIRED';
        ?>
        <hr>
        <h2>Validation Summary</h2>
        <div class="<?php echo esc_attr( $overall_class ); ?>"><p><strong>Overall: <?php echo esc_html( $overall_text ); ?></strong></p></div>

        <table class="widefat striped" style="max-width:1400px;margin-top:12px">
            <tbody>
                <tr><th style="width:220px">Object</th><td><code><?php echo esc_html( $report['object_type'] . ' #' . $report['object_id'] ); ?></code></td></tr>
                <tr><th>Object Language State</th><td><strong><?php echo esc_html( $report['state'] ); ?></strong></td></tr>
                <tr><th>Spanish Slug</th><td><?php echo $report['slug'] ? '<code>' . esc_html( $report['slug'] ) . '</code>' : '<strong>missing</strong>'; ?></td></tr>
                <tr><th>Source JSON SHA-256</th><td><code><?php echo esc_html( $report['source_json_hash'] ); ?></code></td></tr>
                <tr><th>English URL</th><td><code><?php echo esc_html( $report['source_url'] ); ?></code> · HTTP <?php echo esc_html( (string) $report['en_fetch']['code'] ); ?></td></tr>
                <tr><th>Spanish URL</th><td><code><?php echo esc_html( $report['spanish_url'] ); ?></code> · HTTP <?php echo esc_html( (string) $report['es_fetch']['code'] ); ?></td></tr>
            </tbody>
        </table>

        <h2 style="margin-top:28px">Heading Runtime Checks</h2>
        <table class="widefat striped" style="max-width:1700px">
            <thead>
                <tr>
                    <th>Element</th>
                    <th>Source / Translation</th>
                    <th>Hash State</th>
                    <th>Runtime Decision</th>
                    <th>Expected ES</th>
                    <th>Actual EN</th>
                    <th>Actual ES</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $report['rows'] as $row ) : ?>
                <tr>
                    <td>
                        <code><?php echo esc_html( $row['element_id'] ); ?></code><br>
                        <small><code><?php echo esc_html( $row['context_key'] ); ?></code></small><br>
                        <small>Source Unit: <?php echo $row['source_unit_id'] ? '#' . esc_html( (string) $row['source_unit_id'] ) : 'missing'; ?></small>
                    </td>
                    <td>
                        <strong>Source:</strong> <?php echo esc_html( $row['source_text'] ); ?><br>
                        <strong>Spanish:</strong> <?php echo '' !== $row['translated_text'] ? esc_html( $row['translated_text'] ) : '<em>missing</em>'; ?>
                    </td>
                    <td>
                        <small>Live: <code><?php echo esc_html( self::short_hash( $row['live_source_hash'] ) ); ?></code></small><br>
                        <small>Stored: <code><?php echo esc_html( self::short_hash( $row['stored_source_hash'] ) ); ?></code></small><br>
                        <small>From: <code><?php echo esc_html( self::short_hash( $row['translated_from_hash'] ) ); ?></code></small>
                    </td>
                    <td><strong><?php echo esc_html( $row['runtime_state'] ); ?></strong></td>
                    <td><?php echo esc_html( $row['expected_es'] ); ?></td>
                    <td><?php echo '' !== $row['actual_en'] ? esc_html( $row['actual_en'] ) : '<em>not detected</em>'; ?></td>
                    <td><?php echo '' !== $row['actual_es'] ? esc_html( $row['actual_es'] ) : '<em>not detected</em>'; ?></td>
                    <td>
                        EN <?php echo $row['en_match'] ? '✅' : '❌'; ?><br>
                        ES <?php echo $row['es_match'] ? '✅' : '❌'; ?>
                        <?php if ( $row['cache_suspected'] ) : ?>
                            <br><strong>⚠ CACHE SUSPECTED</strong>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <p class="description" style="margin-top:12px"><strong>说明：</strong>当 Runtime Decision 为 <code>source-drift</code>、<code>stale</code>、<code>missing-translation</code> 等非 applied 状态时，Expected ES 应回退到当前 English Source。如果 Actual ES 仍是旧 Spanish Translation，则标记 <code>CACHE SUSPECTED</code>。</p>
        <?php
    }

    private static function short_hash( $hash ) {
        return $hash ? substr( (string) $hash, 0, 16 ) . '…' : '—';
    }
}
