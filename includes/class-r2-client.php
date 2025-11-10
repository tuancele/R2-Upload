<?php
// File: includes/class-r2-client.php

if ( ! defined( 'ABSPATH' ) ) { exit; }

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

final class Tuancele_R2_Client {

    private static $instance = null;
    private $s3_client = null;
    private $options = [];

    private function __construct() {
        $this->options = get_option('tuancele_r2_settings', []);
    }

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_options() {
        return $this->options;
    }

    public function is_enabled() {
        return isset($this->options['enable_r2']) && $this->options['enable_r2'] === 'on';
    }
    
    public function is_webp_enabled() {
        return isset($this->options['enable_webp_conversion']) && $this->options['enable_webp_conversion'] === 'on';
    }

    public function should_delete_local_files() {
        return isset($this->options['delete_local_file']) && $this->options['delete_local_file'] === 'on';
    }

    public function get_s3_client() {
        if ($this->s3_client === null && $this->is_enabled()) {
            if (empty($this->options['access_key_id']) || empty($this->options['secret_access_key']) || empty($this->options['endpoint'])) {
                return null;
            }
            
            // [ĐÃ SỬA] Đảm bảo AWS SDK được tải
            if ( ! class_exists('Aws\S3\S3Client') ) {
                // Sử dụng hằng số của plugin
                $autoload_path = TC_R2_PLUGIN_PATH . 'vendor/autoload.php';
                if ( file_exists( $autoload_path ) ) {
                    require_once $autoload_path;
                } else {
                    error_log('R2 Client Error: AWS SDK (vendor/autoload.php) not found.');
                    return null;
                }
            }

            $this->s3_client = new S3Client([
                'region' => 'auto',
                'version' => 'latest',
                'endpoint' => $this->options['endpoint'],
                'credentials' => [
                    'key'    => $this->options['access_key_id'],
                    'secret' => $this->options['secret_access_key'],
                ],
            ]);
        }
        return $this->s3_client;
    }

    public static function test_connection($settings) {
        if (empty($settings['access_key_id']) || empty($settings['secret_access_key']) || empty($settings['bucket']) || empty($settings['endpoint'])) {
            return ['success' => false, 'message' => 'Thất bại - Vui lòng điền đầy đủ các trường bắt buộc.'];
        }
        
        // [ĐÃ SỬA] Đảm bảo SDK được tải
        if ( ! class_exists('Aws\S3\S3Client') ) {
            $autoload_path = TC_R2_PLUGIN_PATH . 'vendor/autoload.php';
            if ( file_exists( $autoload_path ) ) {
                require_once $autoload_path;
            } else {
                return ['success' => false, 'message' => 'Lỗi nghiêm trọng: Không tìm thấy file vendor/autoload.php.'];
            }
        }

        try {
            $s3 = new S3Client([
                'region' => 'auto', 'version' => 'latest', 'endpoint' => $settings['endpoint'],
                'credentials' => ['key' => $settings['access_key_id'], 'secret' => $settings['secret_access_key']],
            ]);
            $s3->headBucket(['Bucket' => $settings['bucket']]);
            return ['success' => true, 'message' => 'Kết nối thành công!'];
        } catch (Exception $e) {
            $error_message = 'Kết nối thất bại: ' . ($e instanceof S3Exception ? $e->getAwsErrorMessage() : $e->getMessage());
            return ['success' => false, 'message' => $error_message];
        }
    }
}