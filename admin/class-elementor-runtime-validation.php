<?php
/**
 * Read-only Elementor Runtime Validation Lab.
 *
 * v0.1.1 Structured Runtime Validation v4:
 * - No writes, no cache purge, no translation/state mutation.
 * - Compares repository/runtime decision with actual EN/ES frontend output.
 * - Performs normal + cache-bypass Spanish requests.
 * - Reports WEM language header, common cache headers and validation-only runtime trace.
 * - Step 5E validates adapter-approved Heading.title + Button.text.
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
        $report    = $object_id > 0 ? self::build_report( $object_id ) : null;
        ?>
        <div class="wrap">
            <h1>WEM ML Elementor Runtime Validation</h1>
            <p><strong>v0.1.1 · Structured Runtime Validation v4</strong></p>
            <p>一键只读检查 Routing / State / Source Unit / Translation / Runtime Decision，并对 Spanish 页面执行 Normal / Cache-bypass 双请求；Bypass 请求还会返回 Elementor Runtime Trace。不会清缓存，也不会修改任何数据。</p>

            <form method="get" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>">
                <input type="hidden" name="page" value="wem-multilingual-elementor-runtime-validation">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="wem-ml-runtime-validation-object-id">Content Object ID</label></th>
                        <td>
                            <input id="wem-ml-runtime-validation-object-id" name="object_id" type="number" min="1" required class="small-text" value="<?php echo $object_id ? esc_attr( (string) $object_id ) : ''; ?>">
                            <p class="description">当前回归测试对象可继续使用 Page <code>#1730</code>。</p>
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

        $runtime_fields = array_values( array_filter( $discovery['fields'], static function ( $field ) {
            if ( ! isset( $field['widget_type'], $field['setting_path'] ) ) {
                return false;
            }

            return (
                'heading' === $field['widget_type']
                && 'title' === $field['setting_path']
            ) || (
                'button' === $field['widget_type']
                && 'text' === $field['setting_path']
            );
        } ) );

        if ( empty( $runtime_fields ) ) {
            return new WP_Error( 'wem_ml_validation_no_runtime_fields', '当前对象没有 Adapter 可识别的 Heading.title 或 Button.text。' );
        }

        $slug        = WEM_ML_Slug_Repository::get_slug( $post->post_type, $object_id, 'es' );
        $state       = WEM_ML_Object_State::get_status( $post->post_type, $object_id, 'es' );
        $source_url  = WEM_ML_Router::get_source_permalink( $object_id );
        $spanish_url = $slug ? home_url( user_trailingslashit( 'es/' . $slug ) ) : '';
        $bypass_url  = $spanish_url ? add_query_arg( 'wem_ml_validation', (string) wp_rand( 100000, 999999 ), $spanish_url ) : '';

        $en_fetch        = self::fetch_frontend( $source_url, false );
        $es_fetch        = $spanish_url ? self::fetch_frontend( $spanish_url, false ) : self::empty_fetch( 'Missing Spanish slug.' );
        $es_bypass_fetch = $bypass_url ? self::fetch_frontend( $bypass_url, true ) : self::empty_fetch( 'Missing Spanish slug.' );
        $runtime_trace   = self::extract_runtime_trace( $es_bypass_fetch['body'] );

        $rows = array();
        foreach ( $runtime_fields as $field ) {
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

            $actual_en        = $en_fetch['ok'] ? self::extract_runtime_field( $en_fetch['body'], $field ) : '';
            $actual_es        = $es_fetch['ok'] ? self::extract_runtime_field( $es_fetch['body'], $field ) : '';
            $actual_es_bypass = $es_bypass_fetch['ok'] ? self::extract_runtime_field( $es_bypass_fetch['body'], $field ) : '';

            $en_match        = '' !== $actual_en && self::same_text( $actual_en, (string) $field['source_text'] );
            $es_match        = '' !== $actual_es && self::same_text( $actual_es, $expected_es );
            $es_bypass_match = '' !== $actual_es_bypass && self::same_text( $actual_es_bypass, $expected_es );

            $rows[] = array(
                'element_id'            => $field['element_id'],
                'widget_type'           => isset( $field['widget_type'] ) ? (string) $field['widget_type'] : '',
                'setting_path'          => isset( $field['setting_path'] ) ? (string) $field['setting_path'] : '',
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
                'actual_es_bypass'      => $actual_es_bypass,
                'en_match'              => $en_match,
                'es_match'              => $es_match,
                'es_bypass_match'       => $es_bypass_match,
                'diagnosis'             => self::diagnose( $runtime_state, $expected_es, $actual_es, $actual_es_bypass, $translation, $es_fetch, $es_bypass_fetch ),
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
            'object_id'         => $object_id,
            'object_type'       => $post->post_type,
            'source_json_hash'  => $discovery['source_json_hash'],
            'state'             => $state,
            'slug'              => $slug,
            'source_url'        => $source_url,
            'spanish_url'       => $spanish_url,
            'bypass_url'        => $bypass_url,
            'en_fetch'          => $en_fetch,
            'es_fetch'          => $es_fetch,
            'es_bypass_fetch'   => $es_bypass_fetch,
            'runtime_trace'     => $runtime_trace,
            'rows'              => $rows,
            'overall_pass'      => $overall_pass,
        );
    }

    private static function empty_fetch( $error ) {
        return array( 'ok' => false, 'code' => 0, 'body' => '', 'error' => $error, 'headers' => array() );
    }

    private static function fetch_frontend( $url, $bypass_cache ) {
        if ( ! $url ) {
            return self::empty_fetch( 'URL unavailable.' );
        }

        $headers = array( 'User-Agent' => 'WEM-ML-Runtime-Validation/' . WEM_ML_VERSION );
        if ( $bypass_cache ) {
            $headers['Cache-Control'] = 'no-cache, no-store, max-age=0';
            $headers['Pragma']        = 'no-cache';
        }

        $response = wp_remote_get( $url, array( 'timeout' => 15, 'redirection' => 3, 'headers' => $headers ) );
        if ( is_wp_error( $response ) ) {
            return self::empty_fetch( $response->get_error_message() );
        }

        return array(
            'ok'      => (int) wp_remote_retrieve_response_code( $response ) >= 200 && (int) wp_remote_retrieve_response_code( $response ) < 300 && '' !== (string) wp_remote_retrieve_body( $response ),
            'code'    => (int) wp_remote_retrieve_response_code( $response ),
            'body'    => (string) wp_remote_retrieve_body( $response ),
            'error'   => '',
            'headers' => self::normalize_headers( wp_remote_retrieve_headers( $response ) ),
        );
    }

    private static function normalize_headers( $headers ) {
        $wanted = array( 'x-wem-ml-language', 'cf-cache-status', 'cache-control', 'age', 'x-cache', 'x-cache-enabled', 'x-proxy-cache' );
        $result = array();
        foreach ( $wanted as $name ) {
            $value = '';
            if ( is_object( $headers ) && method_exists( $headers, 'offsetGet' ) ) {
                $candidate = $headers->offsetGet( $name );
                if ( null !== $candidate ) {
                    $value = is_array( $candidate ) ? implode( ', ', $candidate ) : (string) $candidate;
                }
            } elseif ( is_array( $headers ) && isset( $headers[ $name ] ) ) {
                $candidate = $headers[ $name ];
                $value = is_array( $candidate ) ? implode( ', ', $candidate ) : (string) $candidate;
            }
            $result[ $name ] = $value;
        }
        return $result;
    }

    private static function extract_runtime_trace( $html ) {
        if ( '' === (string) $html ) {
            return array();
        }

        if ( ! preg_match( '/<!--\s*WEM_ML_RUNTIME_TRACE\s+(.+?)\s*-->/s', (string) $html, $matches ) ) {
            return array();
        }

        $json = html_entity_decode( trim( $matches[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $data = json_decode( $json, true );
        return is_array( $data ) ? $data : array();
    }

    private static function diagnose( $runtime_state, $expected_es, $actual_es, $actual_es_bypass, $translation, $es_fetch, $es_bypass_fetch ) {
        $normal_lang = isset( $es_fetch['headers']['x-wem-ml-language'] ) ? strtolower( (string) $es_fetch['headers']['x-wem-ml-language'] ) : '';
        $bypass_lang = isset( $es_bypass_fetch['headers']['x-wem-ml-language'] ) ? strtolower( (string) $es_bypass_fetch['headers']['x-wem-ml-language'] ) : '';

        if ( ( $normal_lang && 'es' !== $normal_lang ) || ( $bypass_lang && 'es' !== $bypass_lang ) ) {
            return 'LANGUAGE CONTEXT MISMATCH';
        }

        $normal_match = '' !== $actual_es && self::same_text( $actual_es, $expected_es );
        $bypass_match = '' !== $actual_es_bypass && self::same_text( $actual_es_bypass, $expected_es );

        if ( $normal_match && $bypass_match ) return 'PASS';
        if ( ! $normal_match && $bypass_match ) return 'PAGE CACHE SUSPECTED';
        if ( ! $normal_match && ! $bypass_match ) {
            if ( $translation && 'applied' !== $runtime_state && '' !== $actual_es && self::same_text( $actual_es, (string) $translation->translated_text ) ) {
                return 'ELEMENTOR / RUNTIME CACHE SUSPECTED';
            }
            if ( 'applied' === $runtime_state ) return 'ELEMENTOR RUNTIME / ELEMENT CACHE SUSPECTED';
            return 'RUNTIME OUTPUT MISMATCH';
        }
        return 'CHECK REQUIRED';
    }

    private static function extract_runtime_field( $html, $field ) {
        if ( empty( $field['element_id'] ) || empty( $field['widget_type'] ) || empty( $field['setting_path'] ) ) {
            return '';
        }

        if ( 'heading' === $field['widget_type'] && 'title' === $field['setting_path'] ) {
            return self::extract_element_text_by_class( $html, $field['element_id'], 'elementor-heading-title', 'h[1-6]' );
        }

        if ( 'button' === $field['widget_type'] && 'text' === $field['setting_path'] ) {
            return self::extract_element_text_by_class( $html, $field['element_id'], 'elementor-button-text', 'span' );
        }

        return '';
    }

    private static function extract_element_text_by_class( $html, $element_id, $class_name, $tag_pattern ) {
        if ( '' === $html || '' === $element_id || '' === $class_name ) {
            return '';
        }

        if ( class_exists( 'DOMDocument' ) ) {
            $previous = libxml_use_internal_errors( true );
            $dom = new DOMDocument();
            $loaded = $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );

            if ( $loaded ) {
                $xpath = new DOMXPath( $dom );
                $query = sprintf(
                    '//*[@data-id="%s"]//*[contains(concat(" ", normalize-space(@class), " "), " %s ")]',
                    $element_id,
                    $class_name
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

        $pattern = '/data-id=["\']'
            . preg_quote( $element_id, '/' )
            . '["\'][\s\S]{0,5000}?<'
            . $tag_pattern
            . '\b[^>]*class=["\'][^"\']*'
            . preg_quote( $class_name, '/' )
            . '[^"\']*["\'][^>]*>([\s\S]*?)<\/'
            . $tag_pattern
            . '>/i';

        if ( preg_match( $pattern, $html, $matches ) ) {
            return trim( wp_strip_all_tags( html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
        }

        return '';
    }

    private static function same_text( $a, $b ) {
        return WEM_ML_Translation_Repository::normalize_text( (string) $a ) === WEM_ML_Translation_Repository::normalize_text( (string) $b );
    }

    private static function render_report( $report ) {
        $overall_class = $report['overall_pass'] ? 'notice notice-success inline' : 'notice notice-warning inline';
        $overall_text  = $report['overall_pass'] ? 'PASS' : 'CHECK REQUIRED';
        ?>
        <hr>
        <h2>Validation Summary</h2>
        <div class="<?php echo esc_attr( $overall_class ); ?>"><p><strong>Overall: <?php echo esc_html( $overall_text ); ?></strong></p></div>

        <table class="widefat striped" style="max-width:1500px;margin-top:12px"><tbody>
            <tr><th style="width:220px">Object</th><td><code><?php echo esc_html( $report['object_type'] . ' #' . $report['object_id'] ); ?></code></td></tr>
            <tr><th>Object Language State</th><td><strong><?php echo esc_html( $report['state'] ); ?></strong></td></tr>
            <tr><th>Spanish Slug</th><td><?php echo $report['slug'] ? '<code>' . esc_html( $report['slug'] ) . '</code>' : '<strong>missing</strong>'; ?></td></tr>
            <tr><th>Source JSON SHA-256</th><td><code><?php echo esc_html( $report['source_json_hash'] ); ?></code></td></tr>
            <tr><th>English URL</th><td><code><?php echo esc_html( $report['source_url'] ); ?></code> · HTTP <?php echo esc_html( (string) $report['en_fetch']['code'] ); ?></td></tr>
            <tr><th>Spanish URL</th><td><code><?php echo esc_html( $report['spanish_url'] ); ?></code> · HTTP <?php echo esc_html( (string) $report['es_fetch']['code'] ); ?></td></tr>
            <tr><th>Spanish Bypass URL</th><td><code><?php echo esc_html( $report['bypass_url'] ); ?></code> · HTTP <?php echo esc_html( (string) $report['es_bypass_fetch']['code'] ); ?></td></tr>
        </tbody></table>

        <h2 style="margin-top:28px">Response Headers</h2>
        <?php self::render_headers_table( $report['es_fetch'], $report['es_bypass_fetch'] ); ?>

        <h2 style="margin-top:28px">Elementor Runtime Trace</h2>
        <?php self::render_trace_table( $report['runtime_trace'] ); ?>

        <h2 style="margin-top:28px">Structured Runtime Checks</h2>
        <table class="widefat striped" style="max-width:1900px">
            <thead><tr><th>Element</th><th>Source / Translation</th><th>Hash State</th><th>Runtime Decision</th><th>Expected ES</th><th>Actual EN</th><th>Normal ES</th><th>Bypass ES</th><th>Diagnosis</th></tr></thead>
            <tbody>
            <?php foreach ( $report['rows'] as $row ) : ?>
                <tr>
                    <td><code><?php echo esc_html( $row['element_id'] ); ?></code><br><small><strong><?php echo esc_html( $row['widget_type'] . '.' . $row['setting_path'] ); ?></strong></small><br><small><code><?php echo esc_html( $row['context_key'] ); ?></code></small><br><small>Source Unit: <?php echo $row['source_unit_id'] ? '#' . esc_html( (string) $row['source_unit_id'] ) : 'missing'; ?></small></td>
                    <td><strong>Source:</strong> <?php echo esc_html( $row['source_text'] ); ?><br><strong>Spanish:</strong> <?php echo '' !== $row['translated_text'] ? esc_html( $row['translated_text'] ) : '<em>missing</em>'; ?></td>
                    <td><small>Live: <code><?php echo esc_html( self::short_hash( $row['live_source_hash'] ) ); ?></code></small><br><small>Stored: <code><?php echo esc_html( self::short_hash( $row['stored_source_hash'] ) ); ?></code></small><br><small>From: <code><?php echo esc_html( self::short_hash( $row['translated_from_hash'] ) ); ?></code></small></td>
                    <td><strong><?php echo esc_html( $row['runtime_state'] ); ?></strong></td>
                    <td><?php echo esc_html( $row['expected_es'] ); ?></td>
                    <td><?php echo '' !== $row['actual_en'] ? esc_html( $row['actual_en'] ) : '<em>not detected</em>'; ?><br><?php echo $row['en_match'] ? '✅' : '❌'; ?></td>
                    <td><?php echo '' !== $row['actual_es'] ? esc_html( $row['actual_es'] ) : '<em>not detected</em>'; ?><br><?php echo $row['es_match'] ? '✅' : '❌'; ?></td>
                    <td><?php echo '' !== $row['actual_es_bypass'] ? esc_html( $row['actual_es_bypass'] ) : '<em>not detected</em>'; ?><br><?php echo $row['es_bypass_match'] ? '✅' : '❌'; ?></td>
                    <td><strong><?php echo esc_html( $row['diagnosis'] ); ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private static function render_trace_table( $trace ) {
        if ( empty( $trace ) ) {
            echo '<div class="notice notice-warning inline"><p><strong>No Runtime Trace detected.</strong> 说明 validation bypass 请求中没有收到 WEM Runtime Trace，优先检查 Elementor hook 是否进入或 wp_footer 是否输出。</p></div>';
            return;
        }
        ?>
        <table class="widefat striped" style="max-width:1700px">
            <thead><tr><th>#</th><th>Hook</th><th>Language</th><th>Object</th><th>Element</th><th>Context / State</th><th>Result</th></tr></thead>
            <tbody>
            <?php foreach ( $trace as $index => $entry ) : ?>
                <tr>
                    <td><?php echo esc_html( (string) ( $index + 1 ) ); ?></td>
                    <td><code><?php echo esc_html( isset( $entry['hook'] ) ? $entry['hook'] : '—' ); ?></code></td>
                    <td><code><?php echo esc_html( isset( $entry['language'] ) ? $entry['language'] : '—' ); ?></code></td>
                    <td><code><?php echo esc_html( isset( $entry['object_id'] ) ? (string) $entry['object_id'] : '—' ); ?></code></td>
                    <td><code><?php echo esc_html( isset( $entry['element_id'] ) ? $entry['element_id'] : '—' ); ?></code></td>
                    <td><?php if ( ! empty( $entry['context_key'] ) ) : ?><code><?php echo esc_html( $entry['context_key'] ); ?></code><br><?php endif; ?><?php if ( isset( $entry['state'] ) ) : ?><strong><?php echo esc_html( $entry['state'] ); ?></strong><?php endif; ?></td>
                    <td><?php echo esc_html( isset( $entry['result'] ) ? $entry['result'] : ( ! empty( $entry['can_overlay'] ) ? 'can_overlay=true' : '—' ) ); ?><?php if ( isset( $entry['replace_count'] ) ) : ?> · replace_count=<?php echo esc_html( (string) $entry['replace_count'] ); ?><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private static function render_headers_table( $normal, $bypass ) {
        $names = array( 'x-wem-ml-language' => 'X-WEM-ML-Language', 'cf-cache-status' => 'CF-Cache-Status', 'cache-control' => 'Cache-Control', 'age' => 'Age', 'x-cache' => 'X-Cache', 'x-cache-enabled' => 'X-Cache-Enabled', 'x-proxy-cache' => 'X-Proxy-Cache' );
        ?>
        <table class="widefat striped" style="max-width:1500px"><thead><tr><th>Header</th><th>Normal ES</th><th>Bypass ES</th></tr></thead><tbody>
        <?php foreach ( $names as $key => $label ) : $normal_value = ! empty( $normal['headers'][ $key ] ) ? $normal['headers'][ $key ] : '—'; $bypass_value = ! empty( $bypass['headers'][ $key ] ) ? $bypass['headers'][ $key ] : '—'; ?>
            <tr><th><?php echo esc_html( $label ); ?></th><td><code><?php echo esc_html( $normal_value ); ?></code></td><td><code><?php echo esc_html( $bypass_value ); ?></code></td></tr>
        <?php endforeach; ?>
        </tbody></table>
        <?php
    }

    private static function short_hash( $hash ) {
        return $hash ? substr( (string) $hash, 0, 16 ) . '…' : '—';
    }
}
