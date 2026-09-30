<?php
/**
 * Elementor Translation Lab - v0.1.1 Step 4A / Step 5C-3A.
 *
 * Manual Spanish translation for existing Elementor Source Units.
 * Step 5C-3A adds targeted object-cache invalidation after a real
 * translation change. It still never mutates Elementor source data.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WEM_ML_Elementor_Translation_Lab {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_post_wem_ml_save_elementor_spanish_translation', array( __CLASS__, 'handle_save' ) );
    }

    public static function register_menu() {
        add_management_page(
            'WEM ML Elementor Translation',
            'WEM ML Elementor Translation',
            'manage_options',
            'wem-multilingual-elementor-translation-lab',
            array( __CLASS__, 'render_page' )
        );
    }

    public static function handle_save() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Permission denied.' );
        }

        check_admin_referer( 'wem_ml_elementor_translation_save' );

        $object_id       = isset( $_POST['object_id'] ) ? absint( $_POST['object_id'] ) : 0;
        $string_id       = isset( $_POST['string_id'] ) ? absint( $_POST['string_id'] ) : 0;
        $translated_text = isset( $_POST['translated_text'] ) ? trim( wp_unslash( (string) $_POST['translated_text'] ) ) : '';

        $source = WEM_ML_Translation_Repository::get_source( $string_id );

        if (
            ! $source ||
            'elementor_widget_field' !== (string) $source->context_type ||
            $object_id !== (int) $source->object_id
        ) {
            self::redirect_with_notice( $object_id, 'error', 'Source Unit 不属于当前 Elementor 对象。' );
        }

        if ( '' === $translated_text ) {
            self::redirect_with_notice( $object_id, 'error', 'Spanish Translation 不能为空。' );
        }

        $existing_translation = WEM_ML_Translation_Repository::get_translation( $string_id, 'es' );
        $translation_changed  = ! $existing_translation
            || (string) $existing_translation->translated_text !== $translated_text
            || (string) $existing_translation->translated_from_hash !== (string) $source->source_hash
            || 'reviewed' !== (string) $existing_translation->status;

        $result = WEM_ML_Translation_Repository::save_spanish_translation( $string_id, $translated_text );

        if ( is_wp_error( $result ) ) {
            self::redirect_with_notice( $object_id, 'error', $result->get_error_message() );
        }

        $message = 'Spanish Translation 已保存。';

        if ( $translation_changed ) {
            $cache_result = WEM_ML_Cache_Invalidator::purge_object( $object_id );

            if ( is_wp_error( $cache_result ) ) {
                $message .= ' Translation 已更新，但目标缓存自动清理未执行：' . $cache_result->get_error_message();
                self::redirect_with_notice( $object_id, 'warning', $message );
            }

            $purged_count = isset( $cache_result['purged'] ) && is_array( $cache_result['purged'] )
                ? count( $cache_result['purged'] )
                : 0;
            $provider = isset( $cache_result['provider'] ) ? (string) $cache_result['provider'] : 'unknown';
            $method   = isset( $cache_result['method'] ) ? (string) $cache_result['method'] : 'unknown';

            $message .= sprintf(
                ' Targeted cache purge 已自动执行：%d 个 URL；Provider=%s；Method=%s。',
                $purged_count,
                $provider,
                $method
            );
        } else {
            $message .= ' 内容与当前 reviewed Translation 一致，本次未触发缓存清理。';
        }

        self::redirect_with_notice( $object_id, 'success', $message );
    }

    private static function redirect_with_notice( $object_id, $status, $message ) {
        $url = add_query_arg(
            array(
                'page'       => 'wem-multilingual-elementor-translation-lab',
                'object_id'  => absint( $object_id ),
                'wem_status' => sanitize_key( $status ),
                'wem_notice' => rawurlencode( $message ),
            ),
            admin_url( 'tools.php' )
        );

        wp_safe_redirect( $url );
        exit;
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $object_id = isset( $_GET['object_id'] ) ? absint( $_GET['object_id'] ) : 0;
        $post      = $object_id > 0 ? get_post( $object_id ) : null;
        $units     = $object_id > 0 ? WEM_ML_Source_Unit_Repository::get_elementor_units_for_object( $object_id ) : array();
        ?>
        <div class="wrap">
            <h1>WEM ML Elementor Translation</h1>
            <p><strong>v0.1.1 · Step 5C-3A · Translation + Automatic Targeted Cache Invalidation</strong></p>
            <p>本页对已经存在的 <code>elementor_widget_field</code> Source Unit 保存 Spanish Translation 到 <code>wp_wem_ml_translations</code>。当 Translation 实际发生变化时，会自动清理当前对象的 EN / ES 目标页面缓存；不会修改 <code>_elementor_data</code>。</p>

            <?php self::render_notice(); ?>

            <h2>1. 选择测试对象</h2>
            <form method="get" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>">
                <input type="hidden" name="page" value="wem-multilingual-elementor-translation-lab">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="wem-ml-elementor-translation-object-id">Content Object ID</label></th>
                        <td>
                            <input id="wem-ml-elementor-translation-object-id" name="object_id" type="number" min="1" required class="small-text" value="<?php echo $object_id ? esc_attr( (string) $object_id ) : ''; ?>">
                            <p class="description">当前 Runtime / Cache 回归实验可继续使用已同步的 Page <code>#1730</code>。</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( '查看 Elementor Source Units', 'secondary' ); ?>
            </form>

            <?php if ( $object_id > 0 ) : ?>
                <hr>
                <h2>2. Repository Translation State</h2>
                <?php if ( ! $post || 'revision' === $post->post_type ) : ?>
                    <div class="notice notice-error inline"><p>没有找到可测试的 WordPress Content Object。</p></div>
                <?php elseif ( empty( $units ) ) : ?>
                    <div class="notice notice-warning inline"><p>当前对象还没有 Elementor Source Unit。请先完成 Source Unit Sync。</p></div>
                <?php else : ?>
                    <table class="widefat striped" style="max-width:1500px">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Context Key</th>
                                <th>Field Key</th>
                                <th>Source Text</th>
                                <th>Source Hash</th>
                                <th>Spanish Translation</th>
                                <th>Translation From Hash</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $units as $unit ) : ?>
                            <?php
                            $translation = WEM_ML_Translation_Repository::get_translation( (int) $unit->id, 'es' );
                            $state       = self::translation_state( $unit, $translation );
                            ?>
                            <tr>
                                <td><code><?php echo esc_html( (string) $unit->id ); ?></code></td>
                                <td><code><?php echo esc_html( $unit->context_key ); ?></code></td>
                                <td><code><?php echo esc_html( $unit->field_key ); ?></code></td>
                                <td><?php echo esc_html( $unit->source_text ); ?></td>
                                <td><code title="<?php echo esc_attr( $unit->source_hash ); ?>"><?php echo esc_html( substr( $unit->source_hash, 0, 16 ) . '…' ); ?></code></td>
                                <td><?php echo $translation ? esc_html( $translation->translated_text ) : '<em>—</em>'; ?></td>
                                <td><?php echo $translation ? '<code title="' . esc_attr( $translation->translated_from_hash ) . '">' . esc_html( substr( $translation->translated_from_hash, 0, 16 ) . '…' ) . '</code>' : '<em>—</em>'; ?></td>
                                <td><strong><?php echo esc_html( $state ); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <h2 style="margin-top:28px">3. Manual Spanish Translation</h2>
                    <p>选择一个 Source Unit 保存 Spanish Translation。只有译文内容、状态或绑定 Source Hash 实际发生变化时，才触发当前对象的 targeted cache purge。</p>

                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:1000px">
                        <input type="hidden" name="action" value="wem_ml_save_elementor_spanish_translation">
                        <input type="hidden" name="object_id" value="<?php echo esc_attr( (string) $object_id ); ?>">
                        <?php wp_nonce_field( 'wem_ml_elementor_translation_save' ); ?>

                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="wem-ml-string-id">Source Unit</label></th>
                                <td>
                                    <select id="wem-ml-string-id" name="string_id" required>
                                        <?php foreach ( $units as $unit ) : ?>
                                            <option value="<?php echo esc_attr( (string) $unit->id ); ?>">
                                                <?php echo esc_html( '#' . $unit->id . ' · ' . $unit->source_text . ' · ' . $unit->field_key ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="wem-ml-spanish-translation">Spanish Translation</label></th>
                                <td>
                                    <input id="wem-ml-spanish-translation" name="translated_text" type="text" class="regular-text" required placeholder="输入 Spanish Translation">
                                    <p class="description">保存时会把当前 Source Hash 写入 <code>translated_from_hash</code>。</p>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button( '保存 Spanish Translation', 'primary' ); ?>
                    </form>

                    <p class="description"><strong>Step 5C-3A 验收重点：</strong>修改 Translation 保存后应自动 targeted purge；无需手动清 WP Rocket，Runtime Validation 的 Normal ES 应直接得到新译文。重复保存完全相同的 current Translation 时，不应再次清缓存。</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function translation_state( $source, $translation ) {
        if ( ! $translation ) {
            return 'missing';
        }

        return WEM_ML_Translation_Repository::is_stale( $source, $translation ) ? 'stale' : 'current';
    }

    private static function render_notice() {
        if ( empty( $_GET['wem_notice'] ) ) {
            return;
        }

        $status  = isset( $_GET['wem_status'] ) ? sanitize_key( (string) $_GET['wem_status'] ) : 'success';
        $message = sanitize_text_field( rawurldecode( (string) $_GET['wem_notice'] ) );

        if ( 'error' === $status ) {
            $class = 'notice notice-error';
        } elseif ( 'warning' === $status ) {
            $class = 'notice notice-warning';
        } else {
            $class = 'notice notice-success';
        }

        echo '<div class="' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
    }
}
