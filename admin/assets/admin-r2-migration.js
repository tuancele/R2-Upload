jQuery(document).ready(function($) {
    'use strict';

    const tool = $('#r2-migration-tool');
    if (tool.length === 0) {
        return; // Thoát nếu không tìm thấy công cụ
    }

    if (typeof tuanceleR2Data === 'undefined' || !tuanceleR2Data.nonce) {
        console.error('Lỗi: Dữ liệu nonce không được truyền từ PHP.');
        $('#r2-migration-status').text('Lỗi cấu hình script. Vui lòng kiểm tra Console.');
        return;
    }
    
    const nonce = tuanceleR2Data.nonce;
    const ajaxurl = tuanceleR2Data.ajax_url;
    let statusInterval;

    const progressBar = $('#r2-progress-bar');
    const statusBar = $('#r2-migration-status');
    const startBtn = $('#start-r2-migration');
    const cancelBtn = $('#cancel-r2-migration');
    const recheckBtn = $('#recheck-r2-migration'); // [MỚI] Nút kiểm tra lại

    console.log('R2 Migration Script Loaded. Nonce:', nonce);

    function updateStatus(isManualRecheck = false) {
        if (isManualRecheck) {
            recheckBtn.text('Đang kiểm tra...').prop('disabled', true);
            statusBar.text('Đang kết nối máy chủ để kiểm tra file...');
        }

        $.post(ajaxurl, { 
            action: 'tuancele_r2_get_migration_status', 
            _wpnonce: nonce
        })
        .done(function(response) {
            if (!response.success) {
                clearInterval(statusInterval);
                let errorMsg = response.data && response.data.message ? response.data.message : 'Lỗi không xác định.';
                statusBar.html('<span style="color:red;">Lỗi lấy trạng thái: ' + errorMsg + '</span>');
                recheckBtn.text('Lỗi!').prop('disabled', false);
                return;
            }
            
            const status = response.data;
            const localCount = status.local_files_remaining || 0;
            
            // Cập nhật text của nút Start với số lượng file mới nhất
            startBtn.text('Bắt đầu Di chuyển ' + localCount + ' tệp');
            
            if (status.running) {
                // ĐANG CHẠY
                startBtn.prop('disabled', true);
                cancelBtn.prop('disabled', false);
                recheckBtn.text('Đang chạy...').prop('disabled', true);
                
                let percentage = status.total > 0 ? Math.round((status.processed / status.total) * 100) : 0;
                statusBar.text('Đang xử lý... (' + status.processed + ' / ' + status.total + ' tệp)');
                progressBar.css('width', percentage + '%').text(percentage + '%');
                
                // Nếu đang chạy, tiếp tục tự động cập nhật
                if (!statusInterval) {
                     statusInterval = setInterval(updateStatus, 5000);
                }

            } else {
                // ĐÃ DỪNG (Hoàn tất hoặc Bị hủy)
                cancelBtn.prop('disabled', true);
                recheckBtn.text('Kiểm tra lại').prop('disabled', false);
                clearInterval(statusInterval);
                statusInterval = null; // Xóa interval

                if (localCount === 0) {
                     // HOÀN TẤT, KHÔNG CÒN FILE
                     statusBar.text('🎉 Hoàn tất! Không còn tệp nào trên local.');
                     progressBar.css('width', '100%').text('100%');
                     startBtn.prop('disabled', true); // Tắt nút Start vì không còn gì để chạy
                 } else {
                    // SẴN SÀNG CHẠY (hoặc đã bị hủy)
                    if (isManualRecheck) {
                        statusBar.text('Đã kiểm tra xong! Tìm thấy ' + localCount + ' tệp mới cần di chuyển.');
                    } else {
                        statusBar.text('Sẵn sàng di chuyển ' + localCount + ' tệp.');
                    }
                    progressBar.css('width', '0%').text('0%');
                    startBtn.prop('disabled', false); // Bật nút Start
                 }
            }
        })
        .fail(function(jqXHR) {
            clearInterval(statusInterval);
            statusBar.html('<span style="color:red;">Lỗi ' + jqXHR.status + '! Yêu cầu bị máy chủ từ chối.</span>');
            recheckBtn.text('Kiểm tra lại').prop('disabled', false);
        });
    }

    $('#start-r2-migration').on('click', function() {
        $(this).prop('disabled', true).text('Đang khởi tạo...');
        cancelBtn.prop('disabled', false);
        recheckBtn.prop('disabled', true).text('Đang chạy...');
        
        $.post(ajaxurl, { 
            action: 'tuancele_r2_start_migration', 
            _wpnonce: nonce
        })
        .done(function(response) {
            if(response.success) {
                updateStatus(); // Cập nhật trạng thái ngay lập tức
                statusInterval = setInterval(updateStatus, 5000); // Bắt đầu vòng lặp
            } else {
                let errorMsg = response.data && response.data.message ? response.data.message : 'Không rõ nguyên nhân.';
                alert('Lỗi khởi tạo: ' + errorMsg);
                updateStatus(); // Cập nhật lại trạng thái (để reset các nút)
            }
        });
    });

    $('#cancel-r2-migration').on('click', function() {
        if (!confirm('Bạn có chắc muốn hủy bỏ quá trình di chuyển?')) return;
        $(this).prop('disabled', true).text('Đang hủy...');
        
        clearInterval(statusInterval); // Dừng cập nhật tự động ngay
        statusInterval = null;

        $.post(ajaxurl, { 
            action: 'tuancele_r2_cancel_migration', 
            _wpnonce: nonce
        })
        .always(function() { // Dù thành công hay thất bại, cũng cập nhật lại status
            updateStatus(true); // Cập nhật lại (với trạng thái là "đang recheck")
        });
    });
    
    // [MỚI] Xử lý nút kiểm tra lại
    $('#recheck-r2-migration').on('click', function() {
        updateStatus(true); // Chạy updateStatus với cờ 'true' (đang kiểm tra thủ công)
    });
    
    // Tự động kiểm tra trạng thái khi tải trang
    updateStatus(); 
});