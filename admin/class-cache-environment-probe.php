<?php
/**
 * Read-only cache environment probe for Step 5C-1.
 *
 * This module does not purge cache and does not modify WEM data.
 * It only reports cache integration capabilities and target URLs for the
 * current Runtime Validation object.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WEM_ML_Cache_Environment_Probe {

    public static function init() {
        add_action( 'admin_footer', array( __CLASS__, 'render_on_runtime_validation_page' ), 20 );
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

        $post = get_post( $object_id );
        if ( ! $post || 'revision' === $post->post_type ) {
            return;
        }

        $source_url = WEM_ML_Router::get_source_permalink( $object_id );
        $slug       = WEM_ML_Slug_Repository::get_slug( $post->post_type, $object_id, 'es' );
        $spanish_url = $slug ? home_url( user_trailingslashit( 'es/' . $slug ) ) : '';

        $wp_rocket_detected     = defined( 'WP_ROCKET_VERSION' ) || function_exists( 'rocket_clean_post' ) || function_exists( 'rocket_clean_files' );
        $rocket_post_available  = function_exists( 'rocket_clean_post' );
        $rocket_files_available = function_exists( 'rocket_clean_files' );
        $siteground_available   = function_exists( 'sg_cachepress_purge_cache' );
        ?>
        <div class="wrap" id="wem-ml-cache-environment-probe" style="margin-top:28px">
            <hr>
            <h2>Cache Environment Probe</h2>
            <p><strong>v0.1.1 · Step 5C-1 · Read-only Cache Probe</strong></p>
            <p>本区块只检测缓存环境与目标 URL，不会清缓存，也不会修改任何 WEM / Elementor 数据。</p>

            <table class="widefat striped" style="max-width:1500px;margin-top:12px">
                <tbody>
                    <tr>
                        <th style="width:260px">Object</th>
                        <td><code><?php echo esc_html( $post->post_type . ' #' . $object_id ); ?></code></td>
                    </tr>
                    <tr>
                        <th>WP Rocket</th>
                        <td><?php echo $wp_rocket_detected ? '✅ detected' : '— not detected'; ?></td>
                    </tr>
                    <tr>
                        <th>WP Rocket · rocket_clean_post()</th>
                        <td><?php echo $rocket_post_available ? '✅ available' : '— unavailable'; ?></td>
                    </tr>
                    <tr>
                        <th>WP Rocket · rocket_clean_files()</th>
                        <td><?php echo $rocket_files_available ? '✅ available' : '— unavailable'; ?></td>
                    </tr>
                    <tr>
                        <th>SiteGround · sg_cachepress_purge_cache()</th>
                        <td><?php echo $siteground_available ? '✅ available' : '— unavailable'; ?></td>
                    </tr>
                    <tr>
                        <th>English Target URL</th>
                        <td><?php echo $source_url ? '<code>' . esc_html( $source_url ) . '</code>' : '<em>unavailable</em>'; ?></td>
                    </tr>
                    <tr>
                        <th>Spanish Target URL</th>
                        <td><?php echo $spanish_url ? '<code>' . esc_html( $spanish_url ) . '</code>' : '<em>missing translated slug</em>'; ?></td>
                    </tr>
                    <tr>
                        <th>Cloudflare Strategy</th>
                        <td>由 WP Rocket 的现有 Cloudflare 集成间接管理；WEM v0.1.1 不直接保存 Cloudflare API 凭据，也不直接调用 Cloudflare Purge API。</td>
                    </tr>
                    <tr>
                        <th>Step 5C-2 Readiness</th>
                        <td>
                            <?php if ( $rocket_files_available && $source_url && $spanish_url ) : ?>
                                ✅ ready for targeted EN + ES purge experiment
                            <?php elseif ( $rocket_post_available && $source_url && $spanish_url ) : ?>
                                ⚠ WP Rocket detected, but URL-level purge API still needs compatibility testing
                            <?php else : ?>
                                ⚠ targeted purge prerequisites incomplete
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p class="description" style="margin-top:10px">
                Step 5C-1 只回答“当前站点可用哪些缓存接口，以及 #<?php echo esc_html( (string) $object_id ); ?> 对应哪些 EN / ES URL”。下一步 Step 5C-2 才会增加显式的手动 Purge 按钮。
            </p>
        </div>
        <?php
    }
}
