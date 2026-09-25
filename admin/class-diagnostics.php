<?php
/**
 * Experimental diagnostics for WEM Multilingual.
 *
 * Validates both published and draft language-state scenarios.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WEM_ML_Diagnostics {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
    }

    public static function register_menu() {
        add_management_page(
            'WEM ML Diagnostics',
            'WEM ML Diagnostics',
            'manage_options',
            'wem-multilingual-diagnostics',
            array( __CLASS__, 'render_page' )
        );
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $object_id = isset( $_GET['object_id'] ) ? absint( $_GET['object_id'] ) : 0;
        $post      = $object_id > 0 ? get_post( $object_id ) : null;
        $mappings  = WEM_ML_Slug_Repository::get_all();
        ?>
        <div class="wrap">
            <h1>WEM ML Diagnostics</h1>
            <p><strong>Experimental Core · One-click Validation Lab</strong></p>
            <p>自动识别当前语言状态，并分别按 <code>published</code> 或 <code>draft</code> 的正确规则验证 Routing、State、Title Overlay 与 SEO Core。</p>

            <h2>1. 已登记对象：一键验证</h2>
            <?php self::render_quick_test_table( $mappings ); ?>

            <h2>2. 按 Object ID 验证</h2>
            <form method="get" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>">
                <input type="hidden" name="page" value="wem-multilingual-diagnostics">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="wem-ml-diagnostic-object-id">Page / Post ID</label></th>
                        <td><input id="wem-ml-diagnostic-object-id" name="object_id" type="number" min="1" required class="small-text" value="<?php echo $object_id ? esc_attr( (string) $object_id ) : ''; ?>"></td>
                    </tr>
                </table>
                <?php submit_button( '自动识别状态并验证', 'primary' ); ?>
            </form>

            <?php if ( $object_id > 0 ) : ?>
                <hr>
                <h2>3. 自动验收结果</h2>
                <?php
                if ( ! $post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
                    echo '<div class="notice notice-error inline"><p>没有找到可测试的 Page / Post。</p></div>';
                } else {
                    self::render_validation_result( $post );
                }
                ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_quick_test_table( $mappings ) {
        if ( empty( $mappings ) ) {
            echo '<p>当前还没有 Spanish Slug Mapping。</p>';
            return;
        }
        ?>
        <table class="widefat striped" style="max-width:1100px">
            <thead><tr><th>Object</th><th>Source Title</th><th>Spanish Slug</th><th>State</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ( $mappings as $row ) : ?>
                <?php
                $post  = get_post( (int) $row->object_id );
                $state = WEM_ML_Object_State::get_status( $row->object_type, (int) $row->object_id, 'es' );
                $url   = add_query_arg(
                    array(
                        'page'      => 'wem-multilingual-diagnostics',
                        'object_id' => (int) $row->object_id,
                    ),
                    admin_url( 'tools.php' )
                );
                ?>
                <tr>
                    <td><code><?php echo esc_html( $row->object_type . ' #' . $row->object_id ); ?></code></td>
                    <td><?php echo $post ? esc_html( $post->post_title ) : '<em>Object missing</em>'; ?></td>
                    <td><code><?php echo esc_html( $row->translated_slug ); ?></code></td>
                    <td><code><?php echo esc_html( $state ); ?></code></td>
                    <td><a class="button button-secondary" href="<?php echo esc_url( $url ); ?>">一键验证</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private static function render_validation_result( $post ) {
        $object_id         = (int) $post->ID;
        $object_type       = $post->post_type;
        $source_permalink  = WEM_ML_Router::get_source_permalink( $object_id );
        $state             = WEM_ML_Object_State::get_status( $object_type, $object_id, 'es' );
        $is_published      = 'published' === $state;
        $slug              = WEM_ML_Slug_Repository::get_slug( $object_type, $object_id, 'es' );
        $candidate_spanish = $slug ? home_url( user_trailingslashit( 'es/' . $slug ) ) : '';
        $localized         = WEM_ML_Router::get_localized_permalink( $object_type, $object_id, 'es', $source_permalink );
        $evaluation        = WEM_ML_Title_Overlay::evaluate( $object_id, 'es' );
        $seo_data          = WEM_ML_SEO::get_object_seo_data( $object_id );
        $source_probe      = self::probe_url( $source_permalink );
        $spanish_probe     = $candidate_spanish ? self::probe_url( $candidate_spanish ) : null;

        $core_checks = array();
        $seo_checks  = array();

        $core_checks[] = self::check(
            'Source URL 可访问',
            ! is_wp_error( $source_probe ) && 200 === $source_probe['code'],
            is_wp_error( $source_probe ) ? $source_probe->get_error_message() : 'HTTP ' . $source_probe['code']
        );
        $core_checks[] = self::check( 'Spanish Slug 已登记', ! empty( $slug ), $slug ? $slug : 'missing' );
        $core_checks[] = self::check(
            'Spanish State 与当前模式一致',
            in_array( $state, array( 'draft', 'published' ), true ),
            $state
        );

        if ( $is_published ) {
            self::build_published_core_checks( $core_checks, $evaluation );
        } else {
            $core_checks[] = self::check(
                'Draft 时 Title Overlay = not-published',
                'not-published' === $evaluation['state'],
                (string) $evaluation['state']
            );
            $core_checks[] = self::check(
                'Draft 时 Localized Permalink 回退 Source URL',
                self::same_url( $localized, $source_permalink ),
                $localized
            );
        }

        if ( $candidate_spanish && $spanish_probe && ! is_wp_error( $spanish_probe ) ) {
            $expected_code = $is_published ? 200 : 404;
            $core_checks[] = self::check(
                'Spanish URL HTTP 状态符合 State',
                $expected_code === $spanish_probe['code'],
                'expected ' . $expected_code . ', actual ' . $spanish_probe['code']
            );

            $route_value  = self::header_value( $spanish_probe['headers'], 'x-wem-ml-route' );
            $state_value  = self::header_value( $spanish_probe['headers'], 'x-wem-ml-state' );
            $object_value = self::header_value( $spanish_probe['headers'], 'x-wem-ml-object-id' );

            $core_checks[] = self::header_check( 'Spanish Route Header', $route_value, $is_published ? 'resolved' : 'gated' );
            $core_checks[] = self::header_check( 'Spanish State Header', $state_value, $state );

            if ( $is_published ) {
                $core_checks[] = self::header_check( 'Spanish Object ID Header', $object_value, (string) $object_id );

                if ( 'applied' === $evaluation['state'] && '' !== $evaluation['translated_title'] ) {
                    $title_found = false !== strpos( (string) $spanish_probe['body'], (string) $evaluation['translated_title'] );
                    $core_checks[] = array(
                        'label'  => 'Spanish HTML 中找到译文标题',
                        'status' => $title_found ? 'pass' : 'warn',
                        'detail' => $title_found ? (string) $evaluation['translated_title'] : '未在响应 HTML 中找到译文标题。',
                    );
                }
            }
        } elseif ( $candidate_spanish && is_wp_error( $spanish_probe ) ) {
            $core_checks[] = self::warning( 'Spanish URL 实时请求', $spanish_probe->get_error_message() );
        }

        self::build_seo_checks(
            $seo_checks,
            $source_probe,
            $spanish_probe,
            $seo_data,
            $source_permalink,
            $candidate_spanish,
            $is_published
        );

        $core_summary = self::summarize_checks( $core_checks );
        $seo_summary  = self::summarize_checks( $seo_checks );

        self::render_summary_notice( 'Step 7 Overall', $core_summary );
        self::render_summary_notice( 'Step 8 SEO Overall', $seo_summary );
        ?>
        <table class="widefat striped" style="max-width:1200px;margin-top:14px">
            <tbody>
                <tr><th style="width:260px">Validation Mode</th><td><strong><?php echo esc_html( strtoupper( $state ) ); ?></strong></td></tr>
                <tr><th>Object</th><td><code><?php echo esc_html( $object_type . ' #' . $object_id ); ?></code></td></tr>
                <tr><th>Source Title</th><td><?php echo esc_html( $post->post_title ); ?></td></tr>
                <tr><th>Expected Spanish Title</th><td><?php echo $evaluation['translated_title'] ? esc_html( $evaluation['translated_title'] ) : '<em>—</em>'; ?></td></tr>
                <tr><th>Source URL</th><td><code><?php echo esc_html( $source_permalink ); ?></code></td></tr>
                <tr><th>Spanish URL</th><td><?php echo $candidate_spanish ? '<code>' . esc_html( $candidate_spanish ) . '</code>' : '<em>—</em>'; ?></td></tr>
                <tr><th>Localized Permalink Result</th><td><code><?php echo esc_html( $localized ); ?></code></td></tr>
                <tr><th>Spanish State</th><td><code><?php echo esc_html( $state ); ?></code></td></tr>
                <tr><th>Overlay Decision</th><td><code><?php echo esc_html( $evaluation['state'] ); ?></code></td></tr>
                <tr><th>SEO Source Canonical</th><td><code><?php echo esc_html( $seo_data['canonical_en'] ); ?></code></td></tr>
                <tr><th>SEO Spanish Canonical</th><td><?php echo $seo_data['canonical_es'] ? '<code>' . esc_html( $seo_data['canonical_es'] ) . '</code>' : '<em>not public</em>'; ?></td></tr>
            </tbody>
        </table>

        <?php self::render_checks_table( 'Step 7 自动检查项', $core_checks ); ?>
        <?php self::render_checks_table( 'Step 8 SEO 自动检查项', $seo_checks ); ?>

        <p class="description" style="margin-top:12px">说明：诊断请求会自动加入 cache-busting 参数，降低 SiteGround / Cloudflare 旧缓存干扰。自定义 Header 在服务器回环请求中缺失仍记为 WARN。</p>
        <?php
    }

    private static function build_published_core_checks( &$checks, $evaluation ) {
        $checks[] = self::check( 'Published 时 Title Overlay = applied', 'applied' === $evaluation['state'], (string) $evaluation['state'] );
        $checks[] = self::check(
            'Live Source Hash = Stored Source Hash',
            '' !== $evaluation['stored_source_hash']
                && hash_equals( (string) $evaluation['live_source_hash'], (string) $evaluation['stored_source_hash'] ),
            self::short_hash_pair( $evaluation['live_source_hash'], $evaluation['stored_source_hash'] )
        );
        $checks[] = self::check(
            'Stored Source Hash = Translation From Hash',
            '' !== $evaluation['translated_from_hash']
                && hash_equals( (string) $evaluation['stored_source_hash'], (string) $evaluation['translated_from_hash'] ),
            self::short_hash_pair( $evaluation['stored_source_hash'], $evaluation['translated_from_hash'] )
        );
    }

    private static function build_seo_checks( &$checks, $source_probe, $spanish_probe, $seo_data, $source_url, $spanish_url, $is_published ) {
        if ( is_wp_error( $source_probe ) ) {
            $checks[] = self::warning( 'SEO Source HTML 实时验证', $source_probe->get_error_message() );
            return;
        }

        $source_html       = (string) $source_probe['body'];
        $source_canonicals = self::extract_link_hrefs( $source_html, 'canonical' );

        $checks[] = self::url_list_check( 'Source self canonical', $source_canonicals, $source_url );

        $source_lang = self::extract_html_lang( $source_html );
        $checks[] = self::check(
            'Source html lang',
            0 === stripos( $source_lang, 'en' ),
            $source_lang ? $source_lang : 'missing'
        );

        if ( $is_published ) {
            $checks[] = self::check( 'SEO Spanish Public = true', ! empty( $seo_data['spanish_public'] ), $seo_data['spanish_public'] ? 'true' : 'false' );

            if ( ! $spanish_probe || is_wp_error( $spanish_probe ) ) {
                $checks[] = self::warning( 'Spanish SEO HTML 实时验证', is_wp_error( $spanish_probe ) ? $spanish_probe->get_error_message() : 'missing probe' );
                return;
            }

            $spanish_html       = (string) $spanish_probe['body'];
            $spanish_canonicals = self::extract_link_hrefs( $spanish_html, 'canonical' );

            $checks[] = self::url_list_check( 'Spanish self canonical', $spanish_canonicals, $spanish_url );
            $checks[] = self::check_hreflang( 'Source hreflang=en', $source_html, 'en', $source_url );
            $checks[] = self::check_hreflang( 'Source hreflang=es', $source_html, 'es', $spanish_url );
            $checks[] = self::check_hreflang( 'Source hreflang=x-default', $source_html, 'x-default', $source_url );
            $checks[] = self::check_hreflang( 'Spanish hreflang=en', $spanish_html, 'en', $source_url );
            $checks[] = self::check_hreflang( 'Spanish hreflang=es', $spanish_html, 'es', $spanish_url );
            $checks[] = self::check_hreflang( 'Spanish hreflang=x-default', $spanish_html, 'x-default', $source_url );

            $spanish_lang = self::extract_html_lang( $spanish_html );
            $checks[] = self::check( 'Spanish html lang', 'es' === strtolower( $spanish_lang ), $spanish_lang ? $spanish_lang : 'missing' );

            if ( count( $source_canonicals ) > 1 ) {
                $checks[] = self::warning( 'Source canonical 唯一性', '检测到 ' . count( $source_canonicals ) . ' 个 canonical。' );
            }
            if ( count( $spanish_canonicals ) > 1 ) {
                $checks[] = self::warning( 'Spanish canonical 唯一性', '检测到 ' . count( $spanish_canonicals ) . ' 个 canonical。' );
            }
            return;
        }

        // Draft SEO safety: Spanish must not be advertised as a public alternate.
        $checks[] = self::check( 'SEO Spanish Public = false', empty( $seo_data['spanish_public'] ), $seo_data['spanish_public'] ? 'true' : 'false' );
        $checks[] = self::check( 'Draft 时 SEO Spanish Canonical 为空', empty( $seo_data['canonical_es'] ), $seo_data['canonical_es'] ? $seo_data['canonical_es'] : 'not public' );
        $checks[] = self::check_hreflang_absent( 'Draft Source 不输出 hreflang=es', $source_html, 'es' );
        $checks[] = self::check_hreflang_absent( 'Draft Source 不输出 hreflang=en', $source_html, 'en' );
        $checks[] = self::check_hreflang_absent( 'Draft Source 不输出 hreflang=x-default', $source_html, 'x-default' );

        if ( $spanish_probe && ! is_wp_error( $spanish_probe ) ) {
            $checks[] = self::check(
                'Draft Spanish URL 不作为正式 SEO 页面',
                404 === (int) $spanish_probe['code'],
                'HTTP ' . (int) $spanish_probe['code']
            );
        }
    }

    private static function render_checks_table( $title, $checks ) {
        echo '<h3 style="margin-top:24px">' . esc_html( $title ) . '</h3>';
        echo '<table class="widefat striped" style="max-width:1200px"><thead><tr><th style="width:100px">Result</th><th>Check</th><th>Detail</th></tr></thead><tbody>';
        foreach ( $checks as $check ) {
            echo '<tr><td>' . self::status_badge( $check['status'] ) . '</td><td>' . esc_html( $check['label'] ) . '</td><td><code>' . esc_html( $check['detail'] ) . '</code></td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function summarize_checks( $checks ) {
        $failed = false;
        $warned = false;

        foreach ( $checks as $check ) {
            if ( 'fail' === $check['status'] ) {
                $failed = true;
            } elseif ( 'warn' === $check['status'] ) {
                $warned = true;
            }
        }

        if ( $failed ) {
            return 'FAIL';
        }

        return $warned ? 'PASS with WARN' : 'PASS';
    }

    private static function render_summary_notice( $label, $summary ) {
        $class = 'PASS' === $summary ? 'notice-success' : ( 'PASS with WARN' === $summary ? 'notice-warning' : 'notice-error' );
        echo '<div class="notice ' . esc_attr( $class ) . ' inline" style="margin-top:12px"><p><strong>' . esc_html( $label . ': ' . $summary ) . '</strong></p></div>';
    }

    private static function probe_url( $url ) {
        $probe_url = add_query_arg(
            array(
                '_wem_ml_diag' => sprintf( '%d-%d', time(), wp_rand( 1000, 999999 ) ),
            ),
            $url
        );

        $response = wp_remote_get(
            $probe_url,
            array(
                'timeout'     => 12,
                'redirection' => 0,
                'headers'     => array(
                    'Cache-Control' => 'no-cache, no-store, max-age=0',
                    'Pragma'        => 'no-cache',
                    'User-Agent'    => 'WEM-Multilingual-Diagnostics/' . WEM_ML_VERSION,
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return array(
            'code'    => (int) wp_remote_retrieve_response_code( $response ),
            'headers' => wp_remote_retrieve_headers( $response ),
            'body'    => (string) wp_remote_retrieve_body( $response ),
        );
    }

    private static function extract_link_hrefs( $html, $rel ) {
        $matches = array();
        preg_match_all( '/<link\b[^>]*>/i', (string) $html, $tags );

        foreach ( $tags[0] as $tag ) {
            if ( ! preg_match( '/\brel=["\']([^"\']+)["\']/i', $tag, $rel_match ) ) {
                continue;
            }
            $rels = preg_split( '/\s+/', strtolower( trim( $rel_match[1] ) ) );
            if ( ! in_array( strtolower( $rel ), $rels, true ) ) {
                continue;
            }
            if ( preg_match( '/\bhref=["\']([^"\']+)["\']/i', $tag, $href_match ) ) {
                $matches[] = html_entity_decode( $href_match[1], ENT_QUOTES, 'UTF-8' );
            }
        }

        return $matches;
    }

    private static function check_hreflang( $label, $html, $language, $expected_url ) {
        $found = self::find_hreflang( $html, $language );
        return self::check( $label, '' !== $expected_url && self::same_url( $found, $expected_url ), $found ? $found : 'missing' );
    }

    private static function check_hreflang_absent( $label, $html, $language ) {
        $found = self::find_hreflang( $html, $language );
        return self::check( $label, '' === $found, $found ? 'unexpected: ' . $found : 'absent' );
    }

    private static function find_hreflang( $html, $language ) {
        preg_match_all( '/<link\b[^>]*>/i', (string) $html, $tags );

        foreach ( $tags[0] as $tag ) {
            if ( ! preg_match( '/\brel=["\']alternate["\']/i', $tag ) ) {
                continue;
            }
            if ( ! preg_match( '/\bhreflang=["\']([^"\']+)["\']/i', $tag, $lang_match ) ) {
                continue;
            }
            if ( strtolower( $lang_match[1] ) !== strtolower( $language ) ) {
                continue;
            }
            if ( preg_match( '/\bhref=["\']([^"\']+)["\']/i', $tag, $href_match ) ) {
                return html_entity_decode( $href_match[1], ENT_QUOTES, 'UTF-8' );
            }
        }

        return '';
    }

    private static function extract_html_lang( $html ) {
        if ( preg_match( '/<html\b[^>]*\blang=["\']([^"\']+)["\']/i', (string) $html, $match ) ) {
            return trim( $match[1] );
        }
        return '';
    }

    private static function url_list_check( $label, $urls, $expected ) {
        $matched = false;
        foreach ( $urls as $url ) {
            if ( self::same_url( $url, $expected ) ) {
                $matched = true;
                break;
            }
        }

        return self::check( $label, '' !== $expected && $matched, empty( $urls ) ? 'missing' : implode( ' | ', $urls ) );
    }

    private static function same_url( $left, $right ) {
        return untrailingslashit( (string) $left ) === untrailingslashit( (string) $right );
    }

    private static function header_value( $headers, $name ) {
        if ( is_object( $headers ) && method_exists( $headers, 'offsetGet' ) ) {
            $value = $headers->offsetGet( $name );
            return is_array( $value ) ? implode( ', ', $value ) : (string) $value;
        }

        if ( is_array( $headers ) ) {
            foreach ( $headers as $key => $value ) {
                if ( strtolower( (string) $key ) === strtolower( $name ) ) {
                    return is_array( $value ) ? implode( ', ', $value ) : (string) $value;
                }
            }
        }

        return '';
    }

    private static function header_check( $label, $actual, $expected ) {
        if ( '' === (string) $actual ) {
            return self::warning( $label, 'missing in loopback response; browser F12 may still expose it' );
        }

        return self::check( $label, (string) $actual === (string) $expected, 'expected ' . $expected . ', actual ' . $actual );
    }

    private static function check( $label, $passed, $detail ) {
        return array(
            'label'  => (string) $label,
            'status' => $passed ? 'pass' : 'fail',
            'detail' => (string) $detail,
        );
    }

    private static function warning( $label, $detail ) {
        return array(
            'label'  => (string) $label,
            'status' => 'warn',
            'detail' => (string) $detail,
        );
    }

    private static function short_hash_pair( $left, $right ) {
        $left  = $left ? substr( (string) $left, 0, 12 ) : '—';
        $right = $right ? substr( (string) $right, 0, 12 ) : '—';
        return $left . ' / ' . $right;
    }

    private static function status_badge( $status ) {
        if ( 'pass' === $status ) {
            return '<strong style="color:#008a20">PASS</strong>';
        }
        if ( 'warn' === $status ) {
            return '<strong style="color:#996800">WARN</strong>';
        }
        return '<strong style="color:#b32d2e">FAIL</strong>';
    }
}
