<?php
/**
 * Minimal experimental admin UI for WEM Multilingual.
 *
 * Step 4A only: create/update and inspect Spanish slug mappings.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WEM_ML_Admin {

    /**
     * Register admin hooks.
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_post_wem_ml_save_slug', array( __CLASS__, 'handle_save_slug' ) );
    }

    /**
     * Register Tools submenu.
     */
    public static function register_menu() {
        add_management_page(
            'WEM Multilingual',
            'WEM Multilingual',
            'manage_options',
            'wem-multilingual',
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * Handle one slug mapping save.
     */
    public static function handle_save_slug() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You are not allowed to perform this action.' );
        }

        check_admin_referer( 'wem_ml_save_slug' );

        $object_type = isset( $_POST['object_type'] )
            ? sanitize_key( wp_unslash( $_POST['object_type'] ) )
            : '';

        $object_id = isset( $_POST['object_id'] )
            ? absint( $_POST['object_id'] )
            : 0;

        $translated_slug = isset( $_POST['translated_slug'] )
            ? sanitize_title( wp_unslash( $_POST['translated_slug'] ) )
            : '';

        $result = WEM_ML_Slug_Repository::save(
            $object_type,
            $object_id,
            'es',
            $translated_slug
        );

        $redirect_args = array(
            'page' => 'wem-multilingual',
        );

        if ( is_wp_error( $result ) ) {
            $redirect_args['wem_ml_error'] = rawurlencode( $result->get_error_message() );
        } else {
            $redirect_args['wem_ml_saved'] = '1';
        }

        wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'tools.php' ) ) );
        exit;
    }

    /**
     * Render experimental admin page.
     */
    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $rows = WEM_ML_Slug_Repository::get_all();
        ?>
        <div class="wrap">
            <h1>WEM Multilingual</h1>
            <p><strong>Experimental Core · Step 4A</strong></p>
            <p>当前仅用于验证 Spanish slug repository。保存映射不会改变现有前台路由，Step 4B 才会接入 Router。</p>

            <?php if ( isset( $_GET['wem_ml_saved'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>Slug mapping 已保存。</p></div>
            <?php endif; ?>

            <?php if ( isset( $_GET['wem_ml_error'] ) ) : ?>
                <div class="notice notice-error"><p><?php echo esc_html( wp_unslash( $_GET['wem_ml_error'] ) ); ?></p></div>
            <?php endif; ?>

            <h2>添加 / 更新 Spanish Slug</h2>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="wem_ml_save_slug">
                <?php wp_nonce_field( 'wem_ml_save_slug' ); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="wem-ml-object-type">Object Type</label></th>
                        <td>
                            <select id="wem-ml-object-type" name="object_type">
                                <option value="page">page</option>
                                <option value="post">post</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wem-ml-object-id">Object ID</label></th>
                        <td><input id="wem-ml-object-id" name="object_id" type="number" min="1" required class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">Language</th>
                        <td><code>es</code></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wem-ml-translated-slug">Translated Slug</label></th>
                        <td>
                            <input id="wem-ml-translated-slug" name="translated_slug" type="text" required class="regular-text" placeholder="sobre-evodek">
                            <p class="description">只填写 slug，不填写 <code>/es/</code> 和斜杠。</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( '保存 Slug Mapping' ); ?>
            </form>

            <hr>

            <h2>当前 Slug Mappings</h2>

            <?php if ( empty( $rows ) ) : ?>
                <p>当前还没有映射。</p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Object</th>
                            <th>Source Title</th>
                            <th>Language</th>
                            <th>Translated Slug</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $rows as $row ) : ?>
                            <?php $source_post = get_post( (int) $row->object_id ); ?>
                            <tr>
                                <td><?php echo esc_html( (string) $row->id ); ?></td>
                                <td><code><?php echo esc_html( $row->object_type . ' #' . $row->object_id ); ?></code></td>
                                <td><?php echo $source_post ? esc_html( $source_post->post_title ) : '<em>Object missing</em>'; ?></td>
                                <td><code><?php echo esc_html( $row->language ); ?></code></td>
                                <td><code><?php echo esc_html( $row->translated_slug ); ?></code></td>
                                <td><?php echo esc_html( $row->updated_at ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
