<?php
/**
 * Experimental diagnostics for WEM Multilingual.
 *
 * Keeps low-level routing, permalink and runtime-overlay validation inside
 * wp-admin so theme templates do not need temporary test code.
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
            <p>自动识别 Source / Spanish URL，读取 Slug、State、Translation 与 Hash，并对前台路由做实时 HTTP 检查。</p>

            <h2>1. 已登记对象：一键验证</h2>
            <?php self::render_quick_test_table( $mappings ); ?>

            <h2>2. 按 Object ID 验证</h2>
            <form method="get" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>">
                <input type="hidden" name="page" value="wem-multilingual-diagnostics">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="wem-ml-diagnostic-object-id">Page / Post ID</label></th>
                        <td>
                            <input id="wem-ml-diagnostic-object-id" name="object_id" type="number" min="1" required class="small-text" value="<?php echo $object_id ? esc_attr( (string) $object_id ) : ''; ?>">
                        </td>
                    </tr>
                </table>
                <?php submit_button( '自动识别 URL 并验证', 'primary' ); ?>
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
            <thead>
                <tr>
                    <th>Object</th>
                    <th>Source Title</th>
                    <th>Spanish Slug</th>
                    <th>State</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $mappings as $row ) : ?>
                <?php
                $post = get_post( (int) $row->object_id );
                $state = WEM_ML_Object_State::get_status( $row->object_type, (int) $row->object_id, 'es' );
                $url = add_query_arg(
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
        $object_id        = (int) $post->ID;
        $object_type      = $post->post_type;
        $source_permalink = get_permalink( $post );
        $state            = WEM_ML_Object_State::get_status( $object_type, $object_id, 'es' );
        $slug             = WEM_ML_Slug_Repository::get_slug( $object_type, $object_id, 'es' );
        $candidate_spanish = $slug
            ? home_url( user_trailingslashit( 'es/' . $slug ) )
            : '';
        $localized = WEM_ML_Router::get_localized_permalink(
            $object_type,
            $object_id,
            'es',
            $source_permalink
        );

        $evaluation = WEM_ML_Title_Overlay::evaluate( $object_id, 'es' );

        $source_probe  = self::probe_url( $source_permalink );
        $spanish_probe = $candidate_spanish ? self::probe_url( $candidate_spanish ) : null;

        $checks = array();
        $checks[] = self::check(
            'Source URL 可访问',
            ! is_wp_error( $source_probe ) && 200 === $source_probe['code'],
            is_wp_error( $source_probe ) ? $source_probe->get_error_message() : 'HTTP ' . $source_probe['code']
        );

        $checks[] = self::check(
            'Spanish Slug 已登记',
            ! empty( $slug ),
            $slug ? $slug : 'missing'
        );

        $checks[] = self::check(
            'Spanish State = published',
            'published' === $state,
            $state
        );

        $checks[] = self::check(
            'Title Overlay 数据状态 = applied',
            'applied' === $evaluation['state'],
            (string) $evaluation['state']
        );

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

        if ( $candidate_spanish && $spanish_probe && ! is_wp_error( $spanish_probe ) ) {
            $expected_code = 'published' === $state ? 200 : 404;
            $checks[] = self::check(
                'Spanish URL HTTP 状态符合 State',
                $expected_code === $spanish_probe['code'],
                'expected ' . $expected_code . ', actual ' . $spanish_probe['code']
            );

            $route_value = self::header_value( $spanish_probe['headers'], 'x-wem-ml-route' );
            $state_value = self::header_value( $spanish_probe['headers'], 'x-wem-ml-state' );
            $object_value = self::header_value( $spanish_probe['headers'], 'x-wem-ml-object-id' );

            $checks[] = self::check(
                'Spanish Route Header',
                ( 'published' === $state && 'resolved' === $route_value )
                    || ( 'published' !== $state && 'gated' === $route_value ),
                $route_value ? $route_value : 'missing'
            );

            $checks[] = self::check(
                'Spanish State Header',
                $state === $state_value,
                $state_value ? $state_value : 'missing'
            );

            if ( 'published' === $state ) {
                $checks[] = self::check(
                    'Spanish Object ID Header',
                    (string) $object_id === (string) $object_value,
                    $object_value ? $object_value : 'missing'
                );
            }

            if ( 'applied' === $evaluation['state'] && '' !== $evaluation['translated_title'] ) {
                $title_found = false !== strpos(
                    (string) $spanish_probe['body'],
                    (string) $evaluation['translated_title']
                );

                $checks[] = array(
                    'label'  => 'Spanish HTML 中找到译文标题',
                    'status' => $title_found ? 'pass' : 'warn',
                    'detail' => $title_found
                        ? (string) $evaluation['translated_title']
                        : '未在响应 HTML 中找到。若主题未通过 the_title() 输出标题，需要人工确认模板。',
                );
            }
        } elseif ( $candidate_spanish && is_wp_error( $spanish_probe ) ) {
            $checks[] = array(
                'label'  => 'Spanish URL 实时请求',
                'status' => 'warn',
                'detail' => $spanish_probe->get_error_message(),
            );
        }

        $failed = false;
        $warned = false;
        foreach ( $checks as $check ) {
            if ( 'fail' === $check['status'] ) {
                $failed = true;
            }
            if ( 'warn' === $check['status'] ) {
                $warned = true;
            }
        }

        $overall = $failed ? 'FAIL' : ( $warned ? 'PASS with WARN' : 'PASS' );
        $notice_class = $failed ? 'notice-error' : ( $warned ? 'notice-warning' : 'notice-success' );
        ?>
        <div class="notice <?php echo esc_attr( $notice_class ); ?> inline">
            <p><strong>Step 7 Overall: <?php echo esc_html( $overall ); ?></strong></p>
        </div>

        <table class="widefat striped" style="max-width:1200px;margin-top:14px">
            <tbody>
                <tr><th style="width:260px">Object</th><td><code><?php echo esc_html( $object_type . ' #' . $object_id ); ?></code></td></tr>
                <tr><th>Source Title</th><td><?php echo esc_html( $post->post_title ); ?></td></tr>
                <tr><th>Expected Spanish Title</th><td><?php echo $evaluation['translated_title'] ? esc_html( $evaluation['translated_title'] ) : '<em>—</em>'; ?></td></tr>
                <tr><th>Source URL</th><td><code><?php echo esc_html( $source_permalink ); ?></code></td></tr>
                <tr><th>Spanish Candidate URL</th><td><?php echo $candidate_spanish ? '<code>' . esc_html( $candidate_spanish ) . '</code>' : '<em>—</em>'; ?></td></tr>
                <tr><th>Localized Permalink Result</th><td><code><?php echo esc_html( $localized ); ?></code></td></tr>
                <tr><th>Spanish State</th><td><code><?php echo esc_html( $state ); ?></code></td></tr>
                <tr><th>Overlay Decision</th><td><code><?php echo esc_html( $evaluation['state'] ); ?></code></td></tr>
            </tbody>
        </table>

        <h3 style="margin-top:24px">自动检查项</h3>
        <table class="widefat striped" style="max-width:1200px">
            <thead><tr><th style="width:100px">Result</th><th>Check</th><th>Detail</th></tr></thead>
            <tbody>
            <?php foreach ( $checks as $check ) : ?>
                <tr>
                    <td><?php echo self::status_badge( $check['status'] ); ?></td>
                    <td><?php echo esc_html( $check['label'] ); ?></td>
                    <td><code><?php echo esc_html( $check['detail'] ); ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private static function probe_url( $url ) {
        $response = wp_remote_get(
            $url,
            array(
                'timeout'     => 12,
                'redirection' => 0,
                'headers'     => array(
                    'Cache-Control' => 'no-cache',
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

    private static function check( $label, $passed, $detail ) {
        return array(
            'label'  => (string) $label,
            'status' => $passed ? 'pass' : 'fail',
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
