<?php
/**
 * Experimental diagnostics for WEM Multilingual.
 *
 * Keeps low-level routing and permalink validation inside wp-admin so theme
 * templates do not need temporary test code.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WEM_ML_Diagnostics {

    /**
     * Register diagnostics hooks.
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
    }

    /**
     * Register the diagnostics page under Tools.
     */
    public static function register_menu() {
        add_management_page(
            'WEM ML Diagnostics',
            'WEM ML Diagnostics',
            'manage_options',
            'wem-multilingual-diagnostics',
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * Render the diagnostics page.
     */
    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $object_id = isset( $_GET['object_id'] ) ? absint( $_GET['object_id'] ) : 0;
        $post      = $object_id > 0 ? get_post( $object_id ) : null;

        ?>
        <div class="wrap">
            <h1>WEM ML Diagnostics</h1>
            <p><strong>Experimental Core · Route / Permalink Lab</strong></p>
            <p>用于验证对象状态、Slug Mapping 与最终 Spanish Permalink。此页面不修改任何数据。</p>

            <form method="get" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>">
                <input type="hidden" name="page" value="wem-multilingual-diagnostics">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="wem-ml-diagnostic-object-id">Page / Post ID</label></th>
                        <td>
                            <input id="wem-ml-diagnostic-object-id" name="object_id" type="number" min="1" required class="small-text" value="<?php echo $object_id ? esc_attr( (string) $object_id ) : ''; ?>">
                            <p class="description">例如 About EVODEK 为 Object #44。</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( '运行诊断', 'secondary' ); ?>
            </form>

            <?php if ( $object_id > 0 ) : ?>
                <hr>
                <h2>诊断结果</h2>

                <?php if ( ! $post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) : ?>
                    <div class="notice notice-error inline"><p>没有找到可测试的 Page / Post。</p></div>
                <?php else : ?>
                    <?php
                    $source_permalink = get_permalink( $post );
                    $state            = WEM_ML_Object_State::get_status( $post->post_type, (int) $post->ID, 'es' );
                    $slug             = WEM_ML_Slug_Repository::get_slug( $post->post_type, (int) $post->ID, 'es' );
                    $localized        = WEM_ML_Router::get_localized_permalink(
                        $post->post_type,
                        (int) $post->ID,
                        'es',
                        $source_permalink
                    );
                    $is_public_spanish = 'published' === $state && null !== $slug && '' !== $slug;
                    ?>
                    <table class="widefat striped" style="max-width:1000px">
                        <tbody>
                            <tr>
                                <th style="width:220px">Object</th>
                                <td><code><?php echo esc_html( $post->post_type . ' #' . $post->ID ); ?></code></td>
                            </tr>
                            <tr>
                                <th>Source Title</th>
                                <td><?php echo esc_html( $post->post_title ); ?></td>
                            </tr>
                            <tr>
                                <th>Source Permalink</th>
                                <td><code><?php echo esc_html( $source_permalink ); ?></code></td>
                            </tr>
                            <tr>
                                <th>Spanish State</th>
                                <td><code><?php echo esc_html( $state ); ?></code></td>
                            </tr>
                            <tr>
                                <th>Translated Slug</th>
                                <td><?php echo $slug ? '<code>' . esc_html( $slug ) . '</code>' : '<em>—</em>'; ?></td>
                            </tr>
                            <tr>
                                <th>Spanish Public?</th>
                                <td><strong><?php echo $is_public_spanish ? 'Yes' : 'No'; ?></strong></td>
                            </tr>
                            <tr>
                                <th>Localized Permalink Result</th>
                                <td><code><?php echo esc_html( $localized ); ?></code></td>
                            </tr>
                        </tbody>
                    </table>

                    <p class="description" style="margin-top:12px">
                        规则：只有 <code>state = published</code> 且存在 Spanish slug 时，Localized Permalink 才返回 <code>/es/{slug}/</code>；否则回退 Source Permalink。
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
}
