<?php
/**
 * Minimal experimental admin UI for WEM Multilingual.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WEM_ML_Admin {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_post_wem_ml_save_slug', array( __CLASS__, 'handle_save_slug' ) );
        add_action( 'admin_post_wem_ml_sync_title_source', array( __CLASS__, 'handle_sync_title_source' ) );
        add_action( 'admin_post_wem_ml_save_title_translation', array( __CLASS__, 'handle_save_title_translation' ) );
        add_action( 'admin_post_wem_ml_save_object_state', array( __CLASS__, 'handle_save_object_state' ) );
    }

    public static function register_menu() {
        add_management_page(
            'WEM Multilingual',
            'WEM Multilingual',
            'manage_options',
            'wem-multilingual',
            array( __CLASS__, 'render_page' )
        );
    }

    public static function handle_save_slug() {
        self::guard_admin_action( 'wem_ml_save_slug' );

        $object_type = isset( $_POST['object_type'] )
            ? sanitize_key( wp_unslash( $_POST['object_type'] ) )
            : '';
        $object_id = isset( $_POST['object_id'] ) ? absint( $_POST['object_id'] ) : 0;
        $translated_slug = isset( $_POST['translated_slug'] )
            ? sanitize_title( wp_unslash( $_POST['translated_slug'] ) )
            : '';

        $result = WEM_ML_Slug_Repository::save( $object_type, $object_id, 'es', $translated_slug );

        self::redirect_with_result( $result, 'wem_ml_slug_saved' );
    }

    public static function handle_sync_title_source() {
        self::guard_admin_action( 'wem_ml_sync_title_source' );

        $object_id = isset( $_POST['object_id'] ) ? absint( $_POST['object_id'] ) : 0;
        $result    = WEM_ML_Translation_Repository::sync_post_title_source( $object_id );

        self::redirect_with_result( $result, 'wem_ml_source_synced' );
    }

    public static function handle_save_title_translation() {
        self::guard_admin_action( 'wem_ml_save_title_translation' );

        $string_id = isset( $_POST['string_id'] ) ? absint( $_POST['string_id'] ) : 0;
        $translated_text = isset( $_POST['translated_text'] )
            ? sanitize_text_field( wp_unslash( $_POST['translated_text'] ) )
            : '';

        $result = WEM_ML_Translation_Repository::save_spanish_translation(
            $string_id,
            $translated_text
        );

        self::redirect_with_result( $result, 'wem_ml_translation_saved' );
    }

    public static function handle_save_object_state() {
        self::guard_admin_action( 'wem_ml_save_object_state' );

        $object_type = isset( $_POST['object_type'] )
            ? sanitize_key( wp_unslash( $_POST['object_type'] ) )
            : '';
        $object_id = isset( $_POST['object_id'] ) ? absint( $_POST['object_id'] ) : 0;
        $status = isset( $_POST['status'] )
            ? sanitize_key( wp_unslash( $_POST['status'] ) )
            : 'draft';

        $result = WEM_ML_Object_State::save(
            $object_type,
            $object_id,
            'es',
            $status
        );

        self::redirect_with_result( $result, 'wem_ml_state_saved' );
    }

    private static function guard_admin_action( $nonce_action ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You are not allowed to perform this action.' );
        }

        check_admin_referer( $nonce_action );
    }

    private static function redirect_with_result( $result, $success_key ) {
        $args = array( 'page' => 'wem-multilingual' );

        if ( is_wp_error( $result ) ) {
            $args['wem_ml_error'] = rawurlencode( $result->get_error_message() );
        } else {
            $args[ $success_key ] = '1';
        }

        wp_safe_redirect( add_query_arg( $args, admin_url( 'tools.php' ) ) );
        exit;
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $slug_rows  = WEM_ML_Slug_Repository::get_all();
        $title_rows = WEM_ML_Translation_Repository::get_title_units();
        $state_rows = WEM_ML_Object_State::get_all();
        ?>
        <div class="wrap">
            <h1>WEM Multilingual</h1>
            <p><strong>Experimental Core · Step 6A</strong></p>
            <p>当前页面用于验证 Slug Repository、Translation Repository 与 Object Language State。前台 State Gate / Content Overlay 尚未启用。</p>

            <?php self::render_notices(); ?>

            <h2>1. Spanish Slug Repository</h2>
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

            <?php self::render_slug_table( $slug_rows ); ?>

            <hr>

            <h2>2. Translation Repository Lab：post_title</h2>
            <p>先同步 Source Title，再保存 Spanish Translation。当前不会修改 <code>wp_posts.post_title</code>，也不会改变前台标题。</p>

            <h3>2.1 同步 Source Unit</h3>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="wem_ml_sync_title_source">
                <?php wp_nonce_field( 'wem_ml_sync_title_source' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="wem-ml-source-object-id">Page / Post ID</label></th>
                        <td>
                            <input id="wem-ml-source-object-id" name="object_id" type="number" min="1" required class="small-text">
                            <p class="description">例如当前 About EVODEK 页面为 Object #44。</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( '同步 post_title Source Unit' ); ?>
            </form>

            <h3>2.2 当前 Translation Units</h3>
            <?php self::render_translation_table( $title_rows ); ?>

            <hr>

            <h2>3. Object Language State Lab</h2>
            <p>语言版本状态独立于译文是否存在。未建立状态记录时，Core 按 <code>draft</code> 处理，但不会自动写入数据库。</p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="wem_ml_save_object_state">
                <?php wp_nonce_field( 'wem_ml_save_object_state' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="wem-ml-state-object-type">Object Type</label></th>
                        <td>
                            <select id="wem-ml-state-object-type" name="object_type">
                                <option value="page">page</option>
                                <option value="post">post</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wem-ml-state-object-id">Object ID</label></th>
                        <td><input id="wem-ml-state-object-id" name="object_id" type="number" min="1" required class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">Language</th>
                        <td><code>es</code></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wem-ml-state-status">Status</label></th>
                        <td>
                            <select id="wem-ml-state-status" name="status">
                                <option value="draft">draft</option>
                                <option value="published">published</option>
                            </select>
                            <p class="description">Step 6A 只保存状态；下一步 Step 6B 才让状态影响前台访问。</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( '保存 Object Language State' ); ?>
            </form>

            <h3>当前 Object Language States</h3>
            <?php self::render_state_table( $state_rows ); ?>
        </div>
        <?php
    }

    private static function render_notices() {
        if ( isset( $_GET['wem_ml_slug_saved'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>Slug mapping 已保存。</p></div>';
        }
        if ( isset( $_GET['wem_ml_source_synced'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>Source Unit 已同步。</p></div>';
        }
        if ( isset( $_GET['wem_ml_translation_saved'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>Spanish Translation 已保存。</p></div>';
        }
        if ( isset( $_GET['wem_ml_state_saved'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>Object Language State 已保存。</p></div>';
        }
        if ( isset( $_GET['wem_ml_error'] ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html( wp_unslash( $_GET['wem_ml_error'] ) ) . '</p></div>';
        }
    }

    private static function render_translation_table( $title_rows ) {
        if ( empty( $title_rows ) ) {
            echo '<p>还没有 post_title Source Unit。</p>';
            return;
        }
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Unit</th>
                    <th>Context</th>
                    <th>Source</th>
                    <th>Source Hash</th>
                    <th>Spanish</th>
                    <th>From Hash</th>
                    <th>State</th>
                    <th>Manual Translation</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $title_rows as $row ) : ?>
                <?php
                $has_translation = null !== $row->translated_text;
                $is_stale = $has_translation
                    && ! hash_equals( (string) $row->source_hash, (string) $row->translated_from_hash );
                ?>
                <tr>
                    <td><code>#<?php echo esc_html( (string) $row->id ); ?></code></td>
                    <td>
                        <code><?php echo esc_html( $row->context_key ); ?></code><br>
                        <small><?php echo esc_html( $row->object_type . ' #' . $row->object_id ); ?></small>
                    </td>
                    <td><?php echo esc_html( $row->source_text ); ?></td>
                    <td><code title="<?php echo esc_attr( $row->source_hash ); ?>"><?php echo esc_html( substr( $row->source_hash, 0, 12 ) ); ?>…</code></td>
                    <td><?php echo $has_translation ? esc_html( $row->translated_text ) : '<em>—</em>'; ?></td>
                    <td>
                        <?php if ( $has_translation ) : ?>
                            <code title="<?php echo esc_attr( $row->translated_from_hash ); ?>"><?php echo esc_html( substr( $row->translated_from_hash, 0, 12 ) ); ?>…</code>
                        <?php else : ?>
                            <em>—</em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( ! $has_translation ) : ?>
                            <strong>Untranslated</strong>
                        <?php elseif ( $is_stale ) : ?>
                            <strong>Stale</strong>
                        <?php else : ?>
                            <strong>Current</strong>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="wem_ml_save_title_translation">
                            <input type="hidden" name="string_id" value="<?php echo esc_attr( (string) $row->id ); ?>">
                            <?php wp_nonce_field( 'wem_ml_save_title_translation' ); ?>
                            <input name="translated_text" type="text" required class="regular-text" value="<?php echo $has_translation ? esc_attr( $row->translated_text ) : ''; ?>" placeholder="Acerca de EVODEK">
                            <?php submit_button( '保存 Spanish', 'secondary small', 'submit', false ); ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private static function render_slug_table( $rows ) {
        echo '<h3>当前 Slug Mappings</h3>';

        if ( empty( $rows ) ) {
            echo '<p>当前还没有映射。</p>';
            return;
        }
        ?>
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
        <?php
    }

    private static function render_state_table( $rows ) {
        if ( empty( $rows ) ) {
            echo '<p>当前还没有显式状态记录；未记录对象按 <code>draft</code> 处理。</p>';
            return;
        }
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Object</th>
                    <th>Source Title</th>
                    <th>Language</th>
                    <th>Status</th>
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
                    <td><strong><?php echo esc_html( $row->status ); ?></strong></td>
                    <td><?php echo esc_html( $row->updated_at ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}
