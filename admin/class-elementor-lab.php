<?php
/**
 * Elementor Structured Translation Lab.
 *
 * v0.1.1 Step 1: read-only environment/source discovery.
 * v0.1.1 Step 2: adapter registry + exact translatable field discovery.
 *
 * No translation writes, no runtime overlay, no Elementor source mutation.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WEM_ML_Elementor_Lab {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
    }

    public static function register_menu() {
        add_management_page(
            'WEM ML Elementor Lab',
            'WEM ML Elementor Lab',
            'manage_options',
            'wem-multilingual-elementor-lab',
            array( __CLASS__, 'render_page' )
        );
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $object_id = isset( $_GET['object_id'] ) ? absint( $_GET['object_id'] ) : 0;
        $post      = $object_id > 0 ? get_post( $object_id ) : null;
        ?>
        <div class="wrap">
            <h1>WEM ML Elementor Lab</h1>
            <p><strong>v0.1.1 · Step 2 · Adapter Registry + Exact Source Discovery</strong></p>
            <p>本页继续只读 <code>_elementor_data</code>。Step 2 在 Step 1 Widget Discovery 基础上，只通过 Adapter Registry 精确读取 <code>heading.title</code> 与 <code>button.text</code>，不会登记 Translation Unit、不会保存译文、不会修改 Elementor Source JSON。</p>

            <h2>1. 选择测试对象</h2>
            <form method="get" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>">
                <input type="hidden" name="page" value="wem-multilingual-elementor-lab">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="wem-ml-elementor-object-id">Content Object ID</label></th>
                        <td>
                            <input id="wem-ml-elementor-object-id" name="object_id" type="number" min="1" required class="small-text" value="<?php echo $object_id ? esc_attr( (string) $object_id ) : ''; ?>">
                            <p class="description">可测试 Page、Post 或由 Elementor 编辑的 Custom Post Type，例如产品详情页。</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( '只读扫描 Elementor Source', 'primary' ); ?>
            </form>

            <?php if ( $object_id > 0 ) : ?>
                <hr>
                <h2>2. Environment Probe</h2>
                <?php
                if ( ! $post || 'revision' === $post->post_type ) {
                    echo '<div class="notice notice-error inline"><p>没有找到可测试的 WordPress Content Object。</p></div>';
                } else {
                    self::render_probe( $post );
                }
                ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_probe( $post ) {
        $object_id         = (int) $post->ID;
        $elementor_active  = did_action( 'elementor/loaded' ) || class_exists( '\\Elementor\\Plugin' );
        $edit_mode         = (string) get_post_meta( $object_id, '_elementor_edit_mode', true );
        $raw_data          = get_post_meta( $object_id, '_elementor_data', true );
        $has_data          = is_string( $raw_data ) && '' !== trim( $raw_data );
        $decoded           = $has_data ? json_decode( $raw_data, true ) : null;
        $json_error        = $has_data ? json_last_error_msg() : 'No _elementor_data';
        $json_valid        = $has_data && JSON_ERROR_NONE === json_last_error() && is_array( $decoded );
        $source_json_hash  = $has_data ? hash( 'sha256', $raw_data ) : '';
        $is_elementor_page = 'builder' === $edit_mode || $has_data;
        $widgets           = array();
        $source_fields     = array();

        if ( $json_valid ) {
            self::walk_elements( $decoded, $widgets, $source_fields, array(), $object_id );
        }

        $heading_count = 0;
        $button_count  = 0;

        foreach ( $widgets as $widget ) {
            if ( 'heading' === $widget['widget_type'] ) {
                $heading_count++;
            }
            if ( 'button' === $widget['widget_type'] ) {
                $button_count++;
            }
        }

        $checks = array(
            array(
                'label'  => 'Elementor Runtime Loaded',
                'status' => $elementor_active ? 'pass' : 'warn',
                'detail' => $elementor_active ? 'Elementor class / hook detected' : '未检测到 Elementor runtime；仍可只读检查已有 _elementor_data。',
            ),
            array(
                'label'  => 'Elementor Source Present',
                'status' => $has_data ? 'pass' : 'fail',
                'detail' => $has_data ? '_elementor_data found' : '_elementor_data missing',
            ),
            array(
                'label'  => 'Elementor JSON Valid',
                'status' => $json_valid ? 'pass' : 'fail',
                'detail' => $json_valid ? 'JSON decoded successfully' : $json_error,
            ),
            array(
                'label'  => 'Adapter Registry Loaded',
                'status' => class_exists( 'WEM_ML_Elementor_Adapter_Registry' ) ? 'pass' : 'fail',
                'detail' => class_exists( 'WEM_ML_Elementor_Adapter_Registry' ) ? 'heading.title + button.text registered' : 'registry missing',
            ),
            array(
                'label'  => 'Read-only Mode',
                'status' => 'pass',
                'detail' => '本 Lab 不执行 update_post_meta() / Elementor document save / translation write。',
            ),
        );
        ?>
        <table class="widefat striped" style="max-width:1200px;margin-top:12px">
            <tbody>
                <tr><th style="width:260px">Object</th><td><code><?php echo esc_html( $post->post_type . ' #' . $object_id ); ?></code></td></tr>
                <tr><th>Source Title</th><td><?php echo esc_html( $post->post_title ); ?></td></tr>
                <tr><th>Post Type</th><td><code><?php echo esc_html( $post->post_type ); ?></code></td></tr>
                <tr><th>Elementor Runtime</th><td><code><?php echo $elementor_active ? 'loaded' : 'not-detected'; ?></code></td></tr>
                <tr><th>_elementor_edit_mode</th><td><code><?php echo $edit_mode ? esc_html( $edit_mode ) : '—'; ?></code></td></tr>
                <tr><th>Elementor Page Candidate</th><td><code><?php echo $is_elementor_page ? 'yes' : 'no'; ?></code></td></tr>
                <tr><th>_elementor_data Present</th><td><code><?php echo $has_data ? 'yes' : 'no'; ?></code></td></tr>
                <tr><th>JSON Valid</th><td><code><?php echo $json_valid ? 'yes' : 'no'; ?></code></td></tr>
                <tr><th>Source JSON Bytes</th><td><code><?php echo esc_html( (string) strlen( (string) $raw_data ) ); ?></code></td></tr>
                <tr><th>Source JSON SHA-256</th><td><?php echo $source_json_hash ? '<code>' . esc_html( $source_json_hash ) . '</code>' : '<em>—</em>'; ?></td></tr>
                <tr><th>Discovered Widgets</th><td><code><?php echo esc_html( (string) count( $widgets ) ); ?></code></td></tr>
                <tr><th>Heading Candidates</th><td><code><?php echo esc_html( (string) $heading_count ); ?></code></td></tr>
                <tr><th>Button Candidates</th><td><code><?php echo esc_html( (string) $button_count ); ?></code></td></tr>
                <tr><th>Exact Translatable Fields</th><td><code><?php echo esc_html( (string) count( $source_fields ) ); ?></code></td></tr>
            </tbody>
        </table>

        <?php self::render_checks( $checks ); ?>

        <h2 style="margin-top:28px">3. Read-only Widget Discovery</h2>
        <p>保留 Step 1 的 Widget Locator 观察表。Tree Path 仍只作为诊断信息，不作为永久 Translation Identity。</p>

        <?php if ( ! $json_valid ) : ?>
            <div class="notice notice-error inline"><p>Elementor JSON 无法解析，因此停止 Widget Discovery。没有执行任何回退猜测或字符串扫描。</p></div>
        <?php elseif ( empty( $widgets ) ) : ?>
            <div class="notice notice-warning inline"><p>JSON 可解析，但没有发现 Widget。</p></div>
        <?php else : ?>
            <table class="widefat striped" style="max-width:1400px">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Element ID</th>
                        <th>Widget Type</th>
                        <th>Tree Path</th>
                        <th>Candidate</th>
                        <th>Settings Keys</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $widgets as $index => $widget ) : ?>
                    <?php
                    $candidate = WEM_ML_Elementor_Adapter_Registry::get_fields_for_widget( $widget['widget_type'] ) ? 'v0.1.1 target' : 'observe only';
                    ?>
                    <tr>
                        <td><?php echo esc_html( (string) ( $index + 1 ) ); ?></td>
                        <td><code><?php echo esc_html( $widget['element_id'] ); ?></code></td>
                        <td><code><?php echo esc_html( $widget['widget_type'] ); ?></code></td>
                        <td><code><?php echo esc_html( $widget['tree_path'] ); ?></code></td>
                        <td><?php echo esc_html( $candidate ); ?></td>
                        <td><code><?php echo esc_html( implode( ', ', $widget['setting_keys'] ) ); ?></code></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h2 style="margin-top:28px">4. Adapter-scoped Source Discovery</h2>
        <p>这里只读取 Adapter Registry 明确登记的字段：<code>heading.title</code> 与 <code>button.text</code>。未知字段不会自动扫描或猜测。</p>

        <?php if ( empty( $source_fields ) ) : ?>
            <div class="notice notice-warning inline"><p>当前对象没有发现可由 v0.1.1 Adapter 处理的非空 Source Field。</p></div>
        <?php else : ?>
            <table class="widefat striped" style="max-width:1500px">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Widget Locator</th>
                        <th>Widget Type</th>
                        <th>Setting Path</th>
                        <th>Mode</th>
                        <th>Source Text</th>
                        <th>Source Hash</th>
                        <th>Experimental Context Key</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $source_fields as $index => $field ) : ?>
                    <tr>
                        <td><?php echo esc_html( (string) ( $index + 1 ) ); ?></td>
                        <td><code><?php echo esc_html( $field['element_id'] ); ?></code><br><small><?php echo esc_html( $field['tree_path'] ); ?></small></td>
                        <td><code><?php echo esc_html( $field['widget_type'] ); ?></code></td>
                        <td><code><?php echo esc_html( $field['setting_path'] ); ?></code></td>
                        <td><code><?php echo esc_html( $field['mode'] ); ?></code></td>
                        <td><?php echo esc_html( $field['source_text'] ); ?></td>
                        <td><code title="<?php echo esc_attr( $field['source_hash'] ); ?>"><?php echo esc_html( substr( $field['source_hash'], 0, 16 ) . '…' ); ?></code></td>
                        <td><code><?php echo esc_html( $field['context_key'] ); ?></code></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <p class="description" style="margin-top:14px"><strong>Step 2 验收重点：</strong>5 个 Heading 应精确发现 <code>title</code>，1 个 Button 应精确发现 <code>text</code>；Source Text 应与 Elementor 编辑器一致；重复扫描时 Source Hash 与 Source JSON SHA-256 保持不变。本步仍不写入 Translation Repository。</p>
        <?php
    }

    private static function walk_elements( $elements, &$widgets, &$source_fields, $path, $object_id ) {
        if ( ! is_array( $elements ) ) {
            return;
        }

        foreach ( $elements as $index => $element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }

            $element_id   = isset( $element['id'] ) ? (string) $element['id'] : '';
            $element_type = isset( $element['elType'] ) ? (string) $element['elType'] : '';
            $widget_type  = isset( $element['widgetType'] ) ? (string) $element['widgetType'] : '';
            $settings     = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();

            $current_path   = $path;
            $current_path[] = (string) $index;
            $tree_path      = implode( '.', $current_path );

            if ( 'widget' === $element_type || '' !== $widget_type ) {
                $setting_keys = array_keys( $settings );
                sort( $setting_keys, SORT_STRING );

                $widgets[] = array(
                    'element_id'   => $element_id,
                    'widget_type'  => $widget_type ? $widget_type : 'unknown',
                    'tree_path'    => $tree_path,
                    'setting_keys' => $setting_keys,
                );

                $discovered = WEM_ML_Elementor_Adapter_Registry::discover_fields( $widget_type, $settings );

                foreach ( $discovered as $field ) {
                    $source_text = (string) $field['source_text'];

                    $source_fields[] = array(
                        'element_id'   => $element_id,
                        'widget_type'  => $widget_type,
                        'tree_path'    => $tree_path,
                        'setting_path' => (string) $field['setting_path'],
                        'mode'         => (string) $field['mode'],
                        'source_text'  => $source_text,
                        'source_hash'  => WEM_ML_Translation_Repository::hash_text( $source_text ),
                        // Experimental only: element_id is still a Locator, not a locked permanent identity model.
                        'context_key'  => sprintf(
                            'elementor:%d:%s:%s:%s',
                            absint( $object_id ),
                            $element_id,
                            $widget_type,
                            (string) $field['setting_path']
                        ),
                    );
                }
            }

            if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
                self::walk_elements( $element['elements'], $widgets, $source_fields, $current_path, $object_id );
            }
        }
    }

    private static function render_checks( $checks ) {
        ?>
        <h2 style="margin-top:24px">Step 2 自动检查项</h2>
        <table class="widefat striped" style="max-width:1200px">
            <thead><tr><th style="width:260px">Check</th><th style="width:100px">Status</th><th>Detail</th></tr></thead>
            <tbody>
            <?php foreach ( $checks as $check ) : ?>
                <?php
                $status = strtoupper( $check['status'] );
                $mark   = 'PASS' === $status ? '✅' : ( 'WARN' === $status ? '⚠️' : '❌' );
                ?>
                <tr>
                    <td><?php echo esc_html( $check['label'] ); ?></td>
                    <td><strong><?php echo esc_html( $mark . ' ' . $status ); ?></strong></td>
                    <td><?php echo esc_html( $check['detail'] ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}
