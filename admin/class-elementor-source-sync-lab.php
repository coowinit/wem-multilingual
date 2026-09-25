<?php
/**
 * Elementor Structured Translation Lab - Step 3A.
 *
 * Explicitly syncs adapter-approved Elementor source fields into WEM Source Units.
 * Never mutates _elementor_data and never writes translations.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WEM_ML_Elementor_Source_Sync_Lab {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_post_wem_ml_sync_elementor_sources', array( __CLASS__, 'handle_sync' ) );
    }

    public static function register_menu() {
        add_management_page(
            'WEM ML Elementor Source Sync',
            'WEM ML Elementor Source Sync',
            'manage_options',
            'wem-multilingual-elementor-source-sync',
            array( __CLASS__, 'render_page' )
        );
    }

    public static function handle_sync() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to perform this action.', 'wem-multilingual' ) );
        }

        check_admin_referer( 'wem_ml_sync_elementor_sources' );

        $object_id = isset( $_POST['object_id'] ) ? absint( $_POST['object_id'] ) : 0;
        $result    = WEM_ML_Elementor_Source_Discovery::discover( $object_id );
        $synced    = 0;
        $failed    = 0;

        if ( ! is_wp_error( $result ) ) {
            foreach ( $result['fields'] as $field ) {
                $row = WEM_ML_Source_Unit_Repository::sync(
                    array(
                        'source_language' => 'en',
                        'source_text'     => $field['source_text'],
                        'context_type'    => 'elementor_widget_field',
                        'context_key'     => $field['context_key'],
                        'object_type'     => $result['object_type'],
                        'object_id'       => $object_id,
                        'field_key'       => $field['field_key'],
                        'state'           => 'active',
                    )
                );

                if ( is_wp_error( $row ) ) {
                    $failed++;
                } else {
                    $synced++;
                }
            }
        }

        $args = array(
            'page'      => 'wem-multilingual-elementor-source-sync',
            'object_id' => $object_id,
        );

        if ( is_wp_error( $result ) ) {
            $args['sync_status'] = 'error';
            $args['message']     = rawurlencode( $result->get_error_message() );
        } elseif ( $failed > 0 ) {
            $args['sync_status'] = 'partial';
            $args['synced']      = $synced;
            $args['failed']      = $failed;
        } else {
            $args['sync_status'] = 'success';
            $args['synced']      = $synced;
        }

        wp_safe_redirect( add_query_arg( $args, admin_url( 'tools.php' ) ) );
        exit;
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $object_id = isset( $_GET['object_id'] ) ? absint( $_GET['object_id'] ) : 0;
        $result    = $object_id > 0 ? WEM_ML_Elementor_Source_Discovery::discover( $object_id ) : null;
        $units     = $object_id > 0 ? WEM_ML_Source_Unit_Repository::get_elementor_units_for_object( $object_id ) : array();
        ?>
        <div class="wrap">
            <h1>WEM ML Elementor Source Sync</h1>
            <p><strong>v0.1.1 · Step 3A · Source Unit Sync</strong></p>
            <p>本页只允许把 Adapter Registry 已确认的 Elementor Source Field 同步到 <code>wp_wem_ml_strings</code>。不会保存 Spanish Translation，也不会修改 <code>_elementor_data</code>。</p>

            <?php self::render_notice(); ?>

            <h2>1. 选择测试对象</h2>
            <form method="get" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>">
                <input type="hidden" name="page" value="wem-multilingual-elementor-source-sync">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="wem-ml-elementor-sync-object-id">Content Object ID</label></th>
                        <td><input id="wem-ml-elementor-sync-object-id" name="object_id" type="number" min="1" required class="small-text" value="<?php echo $object_id ? esc_attr( (string) $object_id ) : ''; ?>"></td>
                    </tr>
                </table>
                <?php submit_button( '只读检查可同步 Source Fields', 'secondary' ); ?>
            </form>

            <?php if ( $object_id > 0 ) : ?>
                <hr>
                <h2>2. Source Discovery</h2>
                <?php if ( is_wp_error( $result ) ) : ?>
                    <div class="notice notice-error inline"><p><?php echo esc_html( $result->get_error_message() ); ?></p></div>
                <?php else : ?>
                    <table class="widefat striped" style="max-width:1200px">
                        <tbody>
                            <tr><th style="width:260px">Object</th><td><code><?php echo esc_html( $result['object_type'] . ' #' . $object_id ); ?></code></td></tr>
                            <tr><th>Source JSON SHA-256</th><td><code><?php echo esc_html( $result['source_json_hash'] ); ?></code></td></tr>
                            <tr><th>Discovered Source Fields</th><td><code><?php echo esc_html( (string) count( $result['fields'] ) ); ?></code></td></tr>
                            <tr><th>Existing Elementor Source Units</th><td><code><?php echo esc_html( (string) count( $units ) ); ?></code></td></tr>
                        </tbody>
                    </table>

                    <?php self::render_discovery_table( $result['fields'], $units ); ?>

                    <?php if ( ! empty( $result['fields'] ) ) : ?>
                        <h2 style="margin-top:24px">3. Explicit Source Unit Sync</h2>
                        <p>点击后只写入 WEM 自有表 <code>wp_wem_ml_strings</code>。相同 Context 再次同步会更新原 Source Unit，不会重复新增。</p>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="wem_ml_sync_elementor_sources">
                            <input type="hidden" name="object_id" value="<?php echo esc_attr( (string) $object_id ); ?>">
                            <?php wp_nonce_field( 'wem_ml_sync_elementor_sources' ); ?>
                            <?php submit_button( '同步 Elementor Source Units', 'primary' ); ?>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>

                <h2 style="margin-top:28px">4. Repository Snapshot</h2>
                <?php self::render_repository_table( $units ); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_notice() {
        $status = isset( $_GET['sync_status'] ) ? sanitize_key( $_GET['sync_status'] ) : '';

        if ( 'success' === $status ) {
            $synced = isset( $_GET['synced'] ) ? absint( $_GET['synced'] ) : 0;
            echo '<div class="notice notice-success inline"><p>Source Unit Sync 完成：' . esc_html( (string) $synced ) . ' 条。</p></div>';
        } elseif ( 'partial' === $status ) {
            $synced = isset( $_GET['synced'] ) ? absint( $_GET['synced'] ) : 0;
            $failed = isset( $_GET['failed'] ) ? absint( $_GET['failed'] ) : 0;
            echo '<div class="notice notice-warning inline"><p>部分同步完成：成功 ' . esc_html( (string) $synced ) . '，失败 ' . esc_html( (string) $failed ) . '。</p></div>';
        } elseif ( 'error' === $status ) {
            $message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '同步失败。';
            echo '<div class="notice notice-error inline"><p>' . esc_html( $message ) . '</p></div>';
        }
    }

    private static function render_discovery_table( $fields, $units ) {
        $by_context = array();
        foreach ( $units as $unit ) {
            $by_context[ (string) $unit->context_key ] = $unit;
        }
        ?>
        <table class="widefat striped" style="max-width:1500px;margin-top:14px">
            <thead><tr><th>#</th><th>Context Key</th><th>Field Key</th><th>Source Text</th><th>Live Hash</th><th>Repository State</th></tr></thead>
            <tbody>
            <?php foreach ( $fields as $index => $field ) : ?>
                <?php
                $existing = isset( $by_context[ $field['context_key'] ] ) ? $by_context[ $field['context_key'] ] : null;
                $state    = 'new';
                if ( $existing ) {
                    $state = hash_equals( (string) $existing->source_hash, (string) $field['source_hash'] ) ? 'current' : 'source-drift';
                }
                ?>
                <tr>
                    <td><?php echo esc_html( (string) ( $index + 1 ) ); ?></td>
                    <td><code><?php echo esc_html( $field['context_key'] ); ?></code></td>
                    <td><code><?php echo esc_html( $field['field_key'] ); ?></code></td>
                    <td><?php echo esc_html( $field['source_text'] ); ?></td>
                    <td><code><?php echo esc_html( substr( $field['source_hash'], 0, 16 ) . '…' ); ?></code></td>
                    <td><strong><?php echo esc_html( $state ); ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private static function render_repository_table( $units ) {
        if ( empty( $units ) ) {
            echo '<p><em>当前对象还没有 Elementor Source Units。</em></p>';
            return;
        }
        ?>
        <table class="widefat striped" style="max-width:1500px">
            <thead><tr><th>ID</th><th>Context Type</th><th>Context Key</th><th>Object</th><th>Field Key</th><th>Source Text</th><th>Source Hash</th><th>State</th></tr></thead>
            <tbody>
            <?php foreach ( $units as $unit ) : ?>
                <tr>
                    <td><code><?php echo esc_html( (string) $unit->id ); ?></code></td>
                    <td><code><?php echo esc_html( $unit->context_type ); ?></code></td>
                    <td><code><?php echo esc_html( $unit->context_key ); ?></code></td>
                    <td><code><?php echo esc_html( $unit->object_type . ' #' . $unit->object_id ); ?></code></td>
                    <td><code><?php echo esc_html( $unit->field_key ); ?></code></td>
                    <td><?php echo esc_html( $unit->source_text ); ?></td>
                    <td><code><?php echo esc_html( substr( $unit->source_hash, 0, 16 ) . '…' ); ?></code></td>
                    <td><code><?php echo esc_html( $unit->state ); ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}
