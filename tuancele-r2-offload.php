<?php
/**
 * Plugin Name:       Tuancele R2 Offload
 * Plugin URI:        https://vpnmisa.com
 * Description:       Tự động offload, chuyển đổi WebP và rewrite URL media của WordPress sang Cloudflare R2.
 * Version:           1.0.0
 * Author:            Tuancele
 * Author URI:        https://tuancele.com
 * License:           GPLv2 or later
 * Text Domain:       tuancele-r2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// 1. Định nghĩa các hằng số (Constants) của plugin
define( 'TC_R2_PLUGIN_VERSION', '1.0.0' );
define( 'TC_R2_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'TC_R2_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TC_R2_PLUGIN_FILE', __FILE__ );

/**
 * Tải thư viện Composer (AWS SDK)
 * Chúng ta kiểm tra sự tồn tại của nó để đưa ra thông báo lỗi thân thiện.
 */
if ( file_exists( TC_R2_PLUGIN_PATH . 'vendor/autoload.php' ) ) {
    require_once TC_R2_PLUGIN_PATH . 'vendor/autoload.php';
} else {
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Lỗi Plugin (Tuancele R2 Offload):</strong> Không tìm thấy thư viện <code>vendor/autoload.php</code>.';
        echo ' Vui lòng sao chép thư mục <code>vendor</code> từ theme gốc vào thư mục plugin này.';
        echo '</p></div>';
    });
    return; // Dừng plugin nếu không có SDK
}

/**
 * Tải các file logic chính của plugin
 */
require_once TC_R2_PLUGIN_PATH . 'includes/class-r2-client.php';
require_once TC_R2_PLUGIN_PATH . 'includes/class-r2-webp.php';
require_once TC_R2_PLUGIN_PATH . 'includes/class-r2-actions.php';
require_once TC_R2_PLUGIN_PATH . 'includes/class-r2-rewriter.php';
require_once TC_R2_PLUGIN_PATH . 'includes/class-r2-migration.php';
require_once TC_R2_PLUGIN_PATH . 'includes/class-r2-integration.php';

/**
 * Tải trang cài đặt (chỉ khi ở trong admin)
 */
if ( is_admin() ) {
    require_once TC_R2_PLUGIN_PATH . 'admin/class-r2-settings-page.php';
}

/**
 * Khởi chạy plugin
 */
function tuancele_r2_load_plugin() {
    // Khởi chạy bộ điều phối chính
    Tuancele_R2_Integration::get_instance();

    // Khởi chạy trang cài đặt
    if ( is_admin() ) {
        new Tuancele_R2_Settings_Page();
    }
}
add_action( 'plugins_loaded', 'tuancele_r2_load_plugin' );