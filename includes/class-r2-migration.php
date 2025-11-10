<?php
// File: inc/r2/class-r2-migration.php
// PHIÊN BẢN TỐI ƯU (STATELESS BATCHING) - V2 (FIX BUG "STUCK AT 0")
// - Giữ nguyên logic Stateless (Không query nặng, không transient).
// - Khôi phục lại logic "FIX LỖI LOCAL": Chạy process_batch()
//   đồng bộ ngay trong ajax_start_migration() để đảm bảo
//   Batch 1 luôn chạy và Batch 2 luôn được lên lịch.

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Tuancele_R2_Migration {

    const STATUS_OPTION = 'tuancele_r2_migration_status';
    const BATCH_SIZE = 5;
    const NONCE_ACTION = 'r2_migration_nonce';

    private $r2_actions;

    public function __construct(Tuancele_R2_Actions $r2_actions) {
        $this->r2_actions = $r2_actions;
    }

    private function verify_nonce() {
        $nonce_value = $_POST['_wpnonce'] ?? '';
        if ( ! wp_verify_nonce( $nonce_value, self::NONCE_ACTION ) ) {
            error_log('R2 Migration - Nonce Verification Failed. Received Nonce: ' . $nonce_value);
            wp_send_json_error( [
                'message' => 'Lỗi bảo mật: Xác thực không thành công. Vui lòng tải lại trang (Hard Refresh: Ctrl+Shift+R) và thử lại.',
            ], 403 );
        }
    }

    private function trigger_cron() {
        wp_remote_post(site_url('wp-cron.php?doing_wp_cron'), [
            'timeout'   => 0.01,
            'blocking'  => false,
            'sslverify' => false,
        ]);
    }

    /**
     * [ĐÃ TỐI ƯU V2] Bắt đầu quá trình di chuyển.
     * Đếm, đặt cờ, và chạy batch đầu tiên ngay lập tức.
     */
    public function ajax_start_migration() {
        $this->verify_nonce();

        // 1. Chỉ query để ĐẾM
        $query = new WP_Query([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [['key' => '_tuancele_r2_offloaded', 'compare' => 'NOT EXISTS']],
        ]);
        $local_count = $query->found_posts;

        if ( empty($local_count) ) {
            wp_send_json_error(['message' => 'Không tìm thấy tệp nào cần di chuyển.']);
        }

        // 2. Cập nhật trạng thái
        update_option(self::STATUS_OPTION, [
            'running' => true, 
            'total' => $local_count, 
            'processed' => 0
        ]);

        // 3. [KHÔI PHỤC LOGIC GỐC]
        // Lên lịch Batch 1 (để cron có thể tự chạy nếu AJAX bị hủy)
        wp_schedule_single_event(time(), 'tuancele_r2_run_migration_batch');
        
        // Chạy Batch 1 ngay lập tức, đồng bộ.
        // Hàm này sẽ tự lên lịch cho Batch 2.
        $this->process_batch();

        // 4. Kích hoạt cron (để chạy Batch 2 nếu Batch 1 thành công)
        $this->trigger_cron();

        // 5. Trả về thành công
        // JS sẽ tự động gọi ajax_get_status và thấy 5 tệp đã được xử lý.
        wp_send_json_success();
    }

    /**
     * [ĐÃ TỐI ƯU] Xử lý một lô tệp (chạy bằng WP-Cron).
     */
    public function process_batch() {
        $status = get_option(self::STATUS_OPTION, []);
        if (empty($status['running'])) { 
            return;
        }

        // 1. [MỚI] Tự query lô (batch) của chính nó
        $batch_query = new WP_Query([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => self::BATCH_SIZE,
            'fields'         => 'ids',
            'meta_query'     => [['key' => '_tuancele_r2_offloaded', 'compare' => 'NOT EXISTS']],
            'orderby'        => 'ID',
            'order'          => 'ASC'
        ]);
        
        $batch_ids = $batch_query->posts;

        // 2. Kiểm tra xem đã hoàn thành chưa
        if ( ! $batch_query->have_posts() || empty($batch_ids) ) {
            // Đã hết tệp, dừng lại
            update_option(self::STATUS_OPTION, array_merge($status, ['running' => false]));
            wp_clear_scheduled_hook('tuancele_r2_run_migration_batch');
            return;
        }

        // 3. Xử lý lô đã tìm thấy
        foreach ($batch_ids as $attachment_id) {
            if (method_exists($this->r2_actions, 'offload_attachment')) {
                 $this->r2_actions->offload_attachment($attachment_id);
            }
            // Thêm một khoảng dừng nhỏ để tránh làm quá tải API
            sleep(0.1); 
        }

        // 4. Cập nhật tiến trình
        $new_processed = ($status['processed'] ?? 0) + count($batch_ids);
        $status['processed'] = min($new_processed, $status['total']); 
        update_option(self::STATUS_OPTION, $status);

        // 5. Lên lịch cho lô tiếp theo
        // (Kiểm tra lại xem sau khi xử lý lô này, còn tệp nào không)
        $remaining_query = new WP_Query([
            'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1, 'fields' => 'ids',
            'meta_query' => [['key' => '_tuancele_r2_offloaded', 'compare' => 'NOT EXISTS']],
        ]);

        if ( $remaining_query->have_posts() ) {
            // Vẫn còn tệp, lên lịch tiếp
            wp_schedule_single_event(time() + 2, 'tuancele_r2_run_migration_batch');
            $this->trigger_cron(); // Kích hoạt cron cho lô tiếp theo
        } else {
            // Đã hết tệp, dừng lại
            update_option(self::STATUS_OPTION, array_merge($status, ['running' => false]));
            wp_clear_scheduled_hook('tuancele_r2_run_migration_batch');
        }
    }

    /**
     * [ĐÃ TỐI ƯU] Hủy bỏ quá trình.
     */
    public function ajax_cancel_migration() {
        $this->verify_nonce();
        
        wp_clear_scheduled_hook('tuancele_r2_run_migration_batch');
        
        // [ĐÃ XÓA] Không cần transient
        
        $status = get_option(self::STATUS_OPTION, []);
        update_option(self::STATUS_OPTION, array_merge($status, ['running' => false]));
        wp_send_json_success();
    }

    /**
     * [KHÔNG THAY ĐỔI] Lấy trạng thái.
     */
    public function ajax_get_status() {
        $this->verify_nonce();
        
        $status = get_option(self::STATUS_OPTION, ['running' => false, 'total' => 0, 'processed' => 0]);
        
        $local_query = new WP_Query([
            'post_type'      => 'attachment', 'post_status'    => 'inherit', 
            'posts_per_page' => 1, 'fields'         => 'ids',
            'meta_query'     => [['key' => '_tuancele_r2_offloaded', 'compare' => 'NOT EXISTS']],
        ]);
        $local_count = $local_query->found_posts;
        
        $status['local_files_remaining'] = $local_count;

        if ($status['running'] === false) {
             $status['total'] = $local_count;
             $status['processed'] = 0;
             update_option(self::STATUS_OPTION, $status);
        }
        
        wp_send_json_success($status);
    }
}