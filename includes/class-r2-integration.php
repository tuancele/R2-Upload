<?php
// File: includes/class-r2-integration.php

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Tuancele_R2_Integration {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Constructor là private để đảm bảo là Singleton
    private function __construct() {
        // Chúng ta không cần load_dependencies() nữa
        // vì tệp plugin chính đã làm việc đó.
        $this->init_hooks();
    }

    private function init_hooks() {
        $client = Tuancele_R2_Client::get_instance();
        $actions = new Tuancele_R2_Actions();

        if ($client->is_enabled()) {
            $rewriter = new Tuancele_R2_Rewriter();

            // Hook đổi tên file (giữ nguyên từ theme)
            add_filter( 'wp_handle_upload_prefilter', [ $this, 'rename_file_on_upload' ], 10, 1 );

            // Hooks cho upload và delete
            add_filter('wp_generate_attachment_metadata', [$actions, 'handle_upload'], 20, 2);
            add_action('delete_attachment', [$actions, 'handle_delete'], 10, 1);
            
            // Hooks cho việc viết lại URL
            add_filter('wp_get_attachment_url', [$rewriter, 'rewrite_attachment_url'], 99, 2);
            add_filter('wp_get_attachment_image_src', [$rewriter, 'rewrite_image_src'], 99, 2);
            add_filter('wp_calculate_image_srcset', [$rewriter, 'rewrite_srcset'], 99, 5);
        }

        // Hook để kiểm tra kết nối khi lưu cài đặt
        add_action('update_option_tuancele_r2_settings', [$this, 'handle_settings_update'], 10, 2);
        
        // Kích hoạt các hook cho Công cụ Migration
        if (is_admin()) {
            $migration = new Tuancele_R2_Migration($actions);
            
            add_action('wp_ajax_tuancele_r2_start_migration', [$migration, 'ajax_start_migration']);
            add_action('wp_ajax_tuancele_r2_cancel_migration', [$migration, 'ajax_cancel_migration']);
            add_action('wp_ajax_tuancele_r2_get_migration_status', [$migration, 'ajax_get_status']);
            add_action('tuancele_r2_run_migration_batch', [$migration, 'process_batch']);
        }
    }
    
    // Hàm này giữ nguyên
    public function handle_settings_update($old_value, $new_value) {
        if (!isset($new_value['enable_r2']) || $new_value['enable_r2'] !== 'on') {
            update_option('tuancele_r2_connection_status', ['success' => true, 'message' => 'Đã tắt.']);
            return;
        }
        $status = Tuancele_R2_Client::test_connection($new_value);
        update_option('tuancele_r2_connection_status', $status);
    }
    
    // Hàm này giữ nguyên
    public function rename_file_on_upload( $file ) {
        $file_info = pathinfo( $file['name'] );
        $extension = isset( $file_info['extension'] ) ? strtolower( $file_info['extension'] ) : '';
        
        $allowed_extensions = [ 
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'avi', 'wmv', 'mp3', 'wav', 'pdf'
        ];

        if ( in_array( $extension, $allowed_extensions ) ) {
            $options = get_option('tuancele_r2_settings', []);
            $prefix = $options['rename_prefix'] ?? '';

            try {
                $datetime = new DateTime( 'now', wp_timezone() );
                $date_str = $datetime->format( 'dmY' );
                $time_str = $datetime->format( 'His' );
            } catch ( Exception $e ) {
                $date_str = date( 'dmY' );
                $time_str = date( 'His' );
            }

            $random_str = strtolower( substr( wp_generate_password( 24, false, false ), 0, 12 ) );
            $new_name_parts = [];
            
            if ( ! empty( $prefix ) ) {
                $new_name_parts[] = rtrim( $prefix, '-_' ); 
            }
            
            $new_name_parts[] = $date_str;
            $new_name_parts[] = $time_str;
            $new_name_parts[] = $random_str;
            
            $new_name = implode( '-', $new_name_parts ) . '.' . $extension;
            $file['name'] = $new_name;
        }
        
        return $file;
    }
}