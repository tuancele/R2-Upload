<?php
// File: admin/class-r2-settings-page.php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

final class Tuancele_R2_Settings_Page {

    public function __construct() {
        add_action('admin_menu', [ $this, 'create_settings_page' ]);
        add_action('admin_init', [ $this, 'register_r2_settings' ]);
        add_action('admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ]);
    }

    /**
     * Tạo trang menu chính trong admin
     */
    public function create_settings_page() {
        add_menu_page(
            'Cài đặt Cloudflare R2',       // Page Title
            'Cloudflare R2',               // Menu Title
            'manage_options',              // Capability
            'tuancele-r2-settings',        // Menu Slug
            [ $this, 'render_settings_page_html' ], // Callback function
            'dashicons-cloud'              // Icon
        );
    }

    /**
     * Render HTML cho trang cài đặt
     */
    public function render_settings_page_html() {
        ?>
        <div class="wrap">
            <h1>Cài đặt lưu trữ Cloudflare R2</h1>
            <p>Điền thông tin kết nối từ tài khoản Cloudflare R2 của bạn. Các tệp media sẽ được tự động đồng bộ và phục vụ từ R2.</p>
            <form method="post" action="options.php">
                <?php
                settings_fields('tuancele_amp_r2_group');
                do_settings_sections('tuancele-amp-r2');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Đăng ký cài đặt, section, và fields
     *
     * [ĐÃ CẬP NHẬT] Bổ sung trường Cache-Control
     */
    public function register_r2_settings() {
        
        register_setting('tuancele_amp_r2_group', 'tuancele_r2_settings');
        add_settings_section('tuancele_r2_settings_section', 'Thông tin kết nối Cloudflare R2', [ $this, 'r2_section_callback' ], 'tuancele-amp-r2');
        
        $r2_fields = [
            'enable_r2' => [
                'label' => 'Kích hoạt R2', 
                'type' => 'checkbox',
                'desc' => 'Kích hoạt để bắt đầu offload file lên Cloudflare R2.'
            ], 
            'rename_prefix' => [
                'label' => 'Tiền tố Đổi tên File',
                'placeholder' => 'vi du: vpnmisa',
                'desc' => 'Tùy chọn. Ví dụ: "booyoung". Tên file sẽ là: <code>booyoung-09112025-131055-abcd1234efgh.jpg</code><br>Để trống để dùng định dạng mặc định (không có tiền tố).'
            ],
            'access_key_id' => [
                'label' => 'Access Key ID',
                'placeholder' => 'Nhập Access Key ID của bạn',
                'desc' => 'Lấy từ R2 > Tổng quan (Overview) > <strong>Quản lý Token R2 (Manage R2 API Tokens)</strong>.'
            ], 
            'secret_access_key' => [
                'label' => 'Secret Access Key', 
                'type' => 'password',
                'placeholder' => 'Nhập Secret Access Key của bạn',
                'desc' => 'Tạo token mới với quyền <strong>"Edit" (Chỉnh sửa)</strong> để lấy Key và Secret.'
            ], 
            'bucket' => [
                'label' => 'Tên Bucket',
                'placeholder' => 'ví dụ: my-wordpress-bucket',
                'desc' => 'Tên bucket R2 mà bạn đã tạo (ví dụ: <code>my-wordpress-bucket</code>).'
            ], 
            'endpoint' => [
                'label' => 'Endpoint (S3 API)',
                'placeholder' => 'https://xxxxx.r2.cloudflarestorage.com',
                'desc' => 'Đi tới R2 > [Tên Bucket] > Cài đặt (Settings). Sao chép <strong>S3 API Endpoint</strong>.<br>Ví dụ: <code>https://xxxxx.r2.cloudflarestorage.com</code>'
            ], 
            'public_url' => [
                'label' => 'Public URL (Bắt buộc)',
                'placeholder' => 'https://pub-xxxxx.r2.dev',
                'desc' => 'URL công khai của bucket. Đi tới R2 > [Tên Bucket] > Cài đặt (Settings) > <strong>Quyền truy cập tên miền công khai</strong>.<br>Nó phải được bật và có dạng: <code>https://pub-xxxxx.r2.dev</code> (hoặc tên miền tùy chỉnh của bạn).'
            ],
            // --- [THÊM TRƯỜNG MỚI TẠI ĐÂY] ---
            'cache_control' => [
                'label' => 'Cache-Control Header',
                'type' => 'text',
                'placeholder' => 'public, max-age=31536000',
                'desc' => 'Metadata cho cache trình duyệt. Để trống để dùng giá trị mặc định: <code>public, max-age=31536000</code> (1 năm).'
            ],
            // --- [KẾT THÚC TRƯỜNG MỚI] ---
            'delete_local_file' => [
                'label' => 'Xóa file gốc', 
                'type' => 'checkbox',
                'desc' => 'Sau khi tải lên R2 thành công, tự động xóa file gốc trên máy chủ để tiết kiệm dung lượng.'
            ], 
            'enable_webp_conversion' => [
                'label' => 'Chuyển sang WebP', 
                'type' => 'checkbox',
                'desc' => 'Tự động chuyển đổi ảnh JPG/PNG sang định dạng WebP trước khi tải lên R2. (Yêu cầu thư viện GD hoặc Imagick trên máy chủ).'
            ]
        ];
        
        foreach ($r2_fields as $id => $field) {
            add_settings_field('tuancele_r2_' . $id, $field['label'], [ $this, 'r2_field_callback' ], 'tuancele-amp-r2', 'tuancele_r2_settings_section', array_merge($field, ['id' => $id]));
        }
        
        add_settings_section('tuancele_r2_migration_section', 'Công cụ Di chuyển Dữ liệu cũ', [ $this, 'r2_migration_section_callback' ], 'tuancele-amp-r2');
        add_settings_field('tuancele_r2_migration_tool', 'Trạng thái & Hành động', [ $this, 'r2_migration_tool_callback' ], 'tuancele-amp-r2', 'tuancele_r2_migration_section');
    }

    /**
     * Callbacks (sao chép y hệt từ admin-settings-module.php)
     */
    public function r2_section_callback() {
        echo '<p>Điền các thông tin dưới đây để kết nối website của bạn với dịch vụ lưu trữ Cloudflare R2.</p>';
        $status = get_option('tuancele_r2_connection_status');
         if ($status && isset($status['message'])) {
            $color = isset($status['success']) && $status['success'] ? '#28a745' : '#dc3545';
            echo '<strong>Trạng thái kết nối: <span style="color:' . esc_attr($color) . ';">' . esc_html($status['message']) . '</span></strong>';
        } else {
             echo '<strong>Trạng thái kết nối: <span style="color:#ffc107;">Chưa kiểm tra.</span></strong>';
        }
    }

    // [ĐÃ CẬP NHẬT] Bổ sung 'placeholder'
    public function r2_field_callback($args) {
        $options = get_option('tuancele_r2_settings', []);
        $id = $args['id'];
        $value = $options[$id] ?? '';
        $type = $args['type'] ?? 'text';
        $placeholder = $args['placeholder'] ?? ''; // Lấy placeholder

        switch ($type) {
            case 'checkbox':
                echo '<label><input type="checkbox" id="tuancele_r2_' . esc_attr($id) . '" name="tuancele_r2_settings[' . esc_attr($id) . ']" value="on" ' . checked('on', $value, false) . '></label>';
                break;
            case 'password':
                echo '<input type="password" id="tuancele_r2_' . esc_attr($id) . '" name="tuancele_r2_settings[' . esc_attr($id) . ']" value="' . esc_attr($value) . '" class="regular-text" autocomplete="new-password" placeholder="' . esc_attr($placeholder) . '" />';
                break;
            default:
                echo '<input type="text" id="tuancele_r2_' . esc_attr($id) . '" name="tuancele_r2_settings[' . esc_attr($id) . ']" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr($placeholder) . '" />';
                break;
        }
        
        if (!empty($args['desc'])) {
            // Sử dụng wp_kses_post để cho phép các thẻ an toàn như <code>, <br>, <strong>
            echo '<p class="description">' . wp_kses_post($args['desc']) . '</p>';
        }
    }

    // Hàm này giữ nguyên
    public function r2_migration_section_callback() {
        echo '<p>Sử dụng công cụ này để tải lên Cloudflare R2 toàn bộ các tệp media đã được tải lên từ trước.</p>';
    }

    // Hàm này giữ nguyên
    public function r2_migration_tool_callback() {
        $status = get_option('tuancele_r2_migration_status', ['running' => false, 'total' => 0, 'processed' => 0]);
        $is_running = $status['running'];
        
        $local_query = new WP_Query([
            'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => -1, 'fields' => 'ids',
            'meta_query' => [['key' => '_tuancele_r2_offloaded', 'compare' => 'NOT EXISTS']]
        ]);
        $local_count = $local_query->post_count;
        ?>
        <style>#r2-migration-tool{border:1px solid #ccd0d4;padding:20px;background:#fff;border-radius:4px}#r2-migration-status{font-weight:700;margin-bottom:15px}#r2-progress-bar-container{width:100%;background-color:#e0e0e0;border-radius:4px;overflow:hidden;height:25px;margin-top:15px}#r2-progress-bar{width:0;height:100%;background-color:#4caf50;text-align:center;line-height:25px;color:#fff;transition:width .3s ease}#r2-migration-tool button{margin-right:10px}</style>
        <div id="r2-migration-tool">
            <div id="r2-migration-status"></div>
            <div id="r2-progress-bar-container"><div id="r2-progress-bar">0%</div></div>
<p style="margin-top:15px">
            <button type="button" class="button button-primary" id="start-r2-migration" <?php if ($is_running || $local_count === 0) echo 'disabled'; ?>>Bắt đầu Di chuyển <?php echo $local_count; ?> tệp</button>
            <button type="button" class="button" id="cancel-r2-migration" <?php if (!$is_running) echo 'disabled'; ?>>Hủy bỏ</button>
            <button type="button" class="button" id="recheck-r2-migration" style="margin-left: 15px;" <?php if ($is_running) echo 'disabled'; ?>><?php echo $is_running ? 'Đang chạy...' : 'Kiểm tra lại'; ?></button>
        </p>
        </div>
        <?php
    }

    // Hàm này giữ nguyên
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_tuancele-r2-settings') {
            return;
        }

        wp_enqueue_script(
            'tuancele-r2-migration',
            TC_R2_PLUGIN_URL . 'admin/assets/admin-r2-migration.js',
            ['jquery'],
            TC_R2_PLUGIN_VERSION,
            true
        );

        $nonce_data_script = sprintf(
            'const tuanceleR2Data = { ajax_url: "%s", nonce: "%s" };',
            admin_url('admin-ajax.php'),
            wp_create_nonce('r2_migration_nonce')
        );
        wp_add_inline_script('tuancele-r2-migration', $nonce_data_script, 'before');

        $script_toggle = "
        jQuery(document).ready(function($) {
            'use strict';
            var mainCheckbox = $('input[type=\"checkbox\"][name*=\"[enable_r2]\"]');
            if (mainCheckbox.length > 0) {
                const dependentFields = mainCheckbox.closest('tr').nextAll();
                function toggleFields() {
                    if (mainCheckbox.is(':checked')) { dependentFields.show(); } else { dependentFields.hide(); }
                }
                toggleFields(); 
                mainCheckbox.on('change', toggleFields);
            }
        });";
        wp_add_inline_script('jquery-core', $script_toggle);
    }
}