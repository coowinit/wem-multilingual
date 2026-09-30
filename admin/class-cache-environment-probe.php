<?php
/**
 * Cache environment probe and manual targeted purge lab.
 *
 * v0.1.1 Step 5C-1:
 * - Read-only cache capability detection and target URL resolution.
 *
 * v0.1.1 Step 5C-2:
 * - Explicit administrator-only purge button.
 * - Purges only the current object's EN / ES URLs through the shared
 *   WEM_ML_Cache_Invalidator service.
 * - Never performs a full-domain purge.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WEM_ML_Cache_Environment_Probe {

    public static function init() {
        add_action( 'admin_footer', array( __CLASS__, 'render_on_runtime_validation_page' ), 20 );
        add_action( 'admin_post_wem_ml_purge_object_cache', array( __CLASS__, 'handle_purge' ) );
    }

    public static function handle_purge() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Permission denied.' );
        }

        check_admin_referer( 'wem_ml_purge_object_cache' );

        $object_id = isset( $_POST['object_id'] ) ? absint( $_POST['object_id'] ) : 0;

        if ( $object_id <= 0 ) {
            self::redirect_with_notice( $object_id, 'error', '缺少有效的 Content Object ID。' );
        }

        $result = WEM_ML_Cache_Invalidator::purge_object( $object_id );

        if ( is_wp_error( $result ) ) {
            self::redirect_with_notice( $object_id, 'error', $result->get_error_message() );
        }

        $provider = isset( $result['provider'] ) ? (string) $result['provider'] : 'cache-provider';
        $method   = isset( $result['method'] ) ? (string) $result['method'] : 'targeted-purge';
        $count    = isset( $result['purged'] ) && is_array( $result['purged'] ) ? count( $result['purged'] ) : 0;

        self::redirect_with_notice(
            $object_id,
            'success',
            sprintf(
                'Targeted cache purge 已执行：%d 个 URL；Provider=%s；Method=%s。',
                $count,
                $provider,
                $method
            )
        );
    }

    private static function redirect_with_notice( $object_id, $status, $message ) {
        $url = add_query_arg(
            array(
                'page'             => 'wem-multilingual-elementor-runtime-validation',
                'object_id'        => absint( $object_id ),
                'wem_cache_status' => sanitize_key( $status ),
                'wem_cache_notice' => rawurlencode( $message ),
            ),
            admin_url( 'tools.php' )
        );

        wp_safe_redirect( $url );
        exit;
    }

    public static function render_on_runtime_validation_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
        if ( 'wem-multilingual-elementor-runtime-validation' !== $page ) {
            return;
        }

        $object_id = isset( $_GET['object_id'] ) ? absint( $_GET['object_id'] ) : 0;
        if ( $object_id <= 0 ) {
            return;
        }

        self::render( $object_id );
        ?>
        <script>
        (function () {
            var probe = document.getElementById('wem-ml-cache-environment-probe-block');
            var wrap  = document.querySelector('#wpbody-content > .wrap');
            if (probe && wrap) {
                wrap.appendChild(probe);
            }
        }());
        </script>
        <?php
    }

    public static function render( $object_id ) {
        $object_id = absint( $object_id );
        if ( $object_id <= 0 ) {
            return;
        }

        $post = get_post( $object_id );
        if ( ! $post || 'revision' === $post->post_type ) {
            return;
        }

        $resolved = WEM_ML_Cache_Invalidator::resolve_object_urls( $object_id );

        if ( is_wp_error( $resolved ) ) {
            $source_url  = '';
            $spanish_url = '';
        } else {
            $source_url  = isset( $resolved['source_url'] ) ? (string) $resolved['source_url'] : '';
            $spanish_url = isset( $resolved['spanish_url'] ) ? (string) $resolved['spanish_url'] : '';
        }

        $wp_rocket_detected     = defined( 'WP_ROCKET_VERSION' ) || function_exists( 'rocket_clean_post' ) || function_exists( 'rocket_clean_files' );
        $rocket_post_available  = function_exists( 'rocket_clean_post' );
        $rocket_files_available = function_exists( 'rocket_clean_files' );
        $siteground_available   = function_exists( 'sg_cachepress_purge_cache' );
        $targeted_ready         = ( $rocket_files_available || $siteground_available ) && $source_url && $spanish_url;
        ?>
        <div id="wem-ml-cache-environment-probe-block">
            <hr style="margin-top:32px">
            <div id="wem-ml-cache-environment-probe">
                <h2>Cache Environment Probe</h2>
                <p><strong>v0.1.1 · Step 5C-2 · Targeted Object Cache Purge Lab</strong></p>
                <p>本区块检测缓存环境与目标 URL，并提供显式的对象级缓存清理实验。不会修改任何 WEM / Elementor 数据，也不会调用全站缓存清理。</p>

                <?php self::render_notice(); ?>

                <table class="widefat striped" style="max-width:1500px;margin-top:12px;table-layout:auto">
                    <tbody>
                        <tr>
                            <th scope="row" style="width:280px;min-width:280px">Object</th>
                            <td><code><?php echo esc_html( $post->post_type . ' #' . $object_id ); ?></code></td>
                        </tr>
                        <tr>
                            <th scope="row">WP Rocket</th>
                            <td><?php echo $wp_rocket_detected ? '✅ detected' : '— not detected'; ?></td>
                        </tr>
                        <tr>
                            <th scope="row">WP Rocket · rocket_clean_post()</th>
                            <td><?php echo $rocket_post_available ? '✅ available' : '— unavailable'; ?></td>
                        </tr>
                        <tr>
                            <th scope="row">WP Rocket · rocket_clean_files()</th>
                            <td><?php echo $rocket_files_available ? '✅ available' : '— unavailable'; ?></td>
                        </tr>
                        <tr>
                            <th scope="row">SiteGround · sg_cachepress_purge_cache()</th>
                            <td><?php echo $siteground_available ? '✅ available' : '— unavailable'; ?></td>
                        </tr>
                        <tr>
                            <th scope="row">English Target URL</th>
                            <td style="overflow-wrap:anywhere"><?php echo $source_url ? '<code>' . esc_html( $source_url ) . '</code>' : '<em>unavailable</em>'; ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Spanish Target URL</th>
                            <td style="overflow-wrap:anywhere"><?php echo $spanish_url ? '<code>' . esc_html( $spanish_url ) . '</code>' : '<em>missing translated slug</em>'; ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Cloudflare Strategy</th>
                            <td>由 WP Rocket 的现有 Cloudflare 集成间接管理；WEM v0.1.1 不直接保存 Cloudflare API 凭据，也不直接调用 Cloudflare Purge API。Step 5C-2 只验证 WP Rocket 的 targeted purge 是否足以让当前 EN / ES 页面恢复正确输出。</td>
                        </tr>
                        <tr>
                            <th scope="row">Step 5C-2 Readiness</th>
                            <td><?php echo $targeted_ready ? '✅ ready for targeted EN + ES purge experiment' : '⚠ targeted purge prerequisites incomplete'; ?></td>
                        </tr>
                    </tbody>
                </table>

                <h3 style="margin-top:22px">Manual Targeted Purge</h3>
                <p>仅清理上表中的 English / Spanish Target URL。WP Rocket 可用时优先调用 <code>rocket_clean_files()</code>；不会调用 <code>rocket_clean_domain()</code>。</p>

                <?php if ( $targeted_ready ) : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="wem_ml_purge_object_cache">
                        <input type="hidden" name="object_id" value="<?php echo esc_attr( (string) $object_id ); ?>">
                        <?php wp_nonce_field( 'wem_ml_purge_object_cache' ); ?>
                        <?php submit_button( 'Purge This Object Cache', 'secondary', 'submit', false ); ?>
                    </form>
                    <p class="description" style="margin-top:8px">点击后会返回当前 Runtime Validation 页面并自动重新检查 #<?php echo esc_html( (string) $object_id ); ?>。本实验只验证 targeted purge，不会自动绑定 Translation / Source / State / Slug 保存事件。</p>
                <?php else : ?>
                    <p><strong>⚠ 当前环境还不能执行 targeted purge。</strong></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private static function render_notice() {
        if ( empty( $_GET['wem_cache_notice'] ) ) {
            return;
        }

        $status  = isset( $_GET['wem_cache_status'] ) ? sanitize_key( (string) $_GET['wem_cache_status'] ) : 'success';
        $message = sanitize_text_field( rawurldecode( (string) $_GET['wem_cache_notice'] ) );
        $class   = 'error' === $status ? 'notice notice-error inline' : 'notice notice-success inline';

        echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $message ) . '</p></div>';
    }
}
