/**
 * GZ SEO Bulk Update Page JavaScript
 * 
 * @package GZ_SEO
 * @since 2.0.0
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // پیش‌نمایش
        $('.gzseo-preview-btn').on('click', function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const $form = $btn.closest('.gzseo-bulk-execute-form');
            const $box = $btn.closest('.gzseo-bulk-box');
            const index = $form.data('index');
            const count = $form.find('input[name="update_count"]').val();
            
            if (!count || count < 1) {
                alert('لطفا تعداد معتبری وارد کنید');
                return;
            }
            
            // نمایش loading
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> در حال بارگذاری...');
            
            // مخفی کردن نتایج قبلی
            $box.find('.gzseo-preview-results').hide().empty();
            
            // درخواست AJAX
            $.ajax({
                url: gzseo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gzseo_bulk_preview',
                    nonce: gzseo_ajax.nonce,
                    index: index,
                    count: count
                },
                success: function(response) {
                    if (response.success) {
                        showPreviewResults($box, response.data.posts);
                    } else {
                        alert(response.data.message || 'خطا در دریافت پیش‌نمایش');
                    }
                },
                error: function() {
                    alert('خطا در ارتباط با سرور');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="fas fa-eye"></i> پیش نمایش');
                }
            });
        });
        
        // اجرا
        $('.gzseo-bulk-execute-form').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $box = $form.closest('.gzseo-bulk-box');
            const $btn = $form.find('.gzseo-execute-btn');
            const index = $form.data('index');
            const count = $form.find('input[name="update_count"]').val();
            
            if (!count || count < 1) {
                alert('لطفا تعداد معتبری وارد کنید');
                return;
            }
            
            if (!confirm(`آیا از آپدیت ${count} مورد مطمئن هستید؟`)) {
                return;
            }
            
            // غیرفعال کردن دکمه‌ها
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> در حال پردازش...');
            $form.find('.gzseo-preview-btn').prop('disabled', true);
            
            // ایجاد بخش پیشرفت
            let progressHtml = `
                <div class="gzseo-progress-section">
                    <div class="gzseo-progress-bar">
                        <div class="gzseo-progress-fill" style="width: 0%;"></div>
                    </div>
                    <div class="gzseo-progress-info">
                        <span class="gzseo-progress-current">0</span> از <span class="gzseo-progress-total">${count}</span> مورد پردازش شد
                    </div>
                    <div class="gzseo-progress-log"></div>
                </div>
            `;
            
            $box.find('.gzseo-preview-results').html(progressHtml).show();
            
            // شروع پردازش تک‌به‌تک
            processItemByItem($box, index, 0, count);
        });
        
        // تابع نمایش نتایج پیش‌نمایش
        function showPreviewResults($box, posts) {
            let html = '<h4>پیش‌نمایش موارد انتخاب شده:</h4>';
            
            if (posts.length > 0) {
                html += '<ul>';
                posts.forEach(function(post, index) {
                    html += `<li>${index + 1}. ${post.title} (ID: ${post.id})</li>`;
                });
                html += '</ul>';
            } else {
                html += '<p>هیچ موردی یافت نشد.</p>';
            }
            
            $box.find('.gzseo-preview-results').html(html).slideDown();
        }
        
        // تابع پردازش تک‌به‌تک (هر پست جداگانه)
        function processItemByItem($box, index, offset, total) {
            if (offset >= total) {
                // اتمام پردازش
                completeProcessing($box);
                return;
            }
            
            const currentItem = offset + 1;
            
            $.ajax({
                url: gzseo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gzseo_bulk_execute',
                    nonce: gzseo_ajax.nonce,
                    index: index,
                    offset: offset,
                    count: 1 // فقط یک مورد در هر درخواست
                },
                success: function(response) {
                    if (response.success) {
                        // بروزرسانی فوری پیشرفت
                        updateProgress($box, currentItem, total, response.data);
                        
                        // ادامه به مورد بعدی با تاخیر کوتاه
                        setTimeout(function() {
                            processItemByItem($box, index, offset + 1, total);
                        }, 500); // 500ms تاخیر بین هر درخواست
                    } else {
                        // ثبت خطا و ادامه به مورد بعدی
                        const errorData = {
                            logs: [{
                                status: 'error',
                                message: response.data.message || 'خطا در پردازش مورد ' + currentItem
                            }]
                        };
                        updateProgress($box, currentItem, total, errorData);
                        
                        setTimeout(function() {
                            processItemByItem($box, index, offset + 1, total);
                        }, 500);
                    }
                },
                error: function() {
                    // ثبت خطا و ادامه
                    const errorData = {
                        logs: [{
                            status: 'error',
                            message: 'خطا در ارتباط با سرور برای مورد ' + currentItem
                        }]
                    };
                    updateProgress($box, currentItem, total, errorData);
                    
                    setTimeout(function() {
                        processItemByItem($box, index, offset + 1, total);
                    }, 500);
                }
            });
        }
        
        // تابع بروزرسانی پیشرفت
        function updateProgress($box, processed, total, data) {
            const percentage = Math.round((processed / total) * 100);
            
            $box.find('.gzseo-progress-fill').css('width', percentage + '%');
            $box.find('.gzseo-progress-current').text(processed);
            
            // اضافه کردن لاگ‌ها
            if (data.logs && data.logs.length > 0) {
                const $log = $box.find('.gzseo-progress-log');
                data.logs.forEach(function(log) {
                    $log.append(`<div class="gzseo-log-item ${log.status}">${log.message}</div>`);
                });
                
                // اسکرول به پایین
                $log.scrollTop($log[0].scrollHeight);
            }
        }
        
        // تابع اتمام پردازش
        function completeProcessing($box) {
            const $form = $box.find('.gzseo-bulk-execute-form');
            
            $form.find('.gzseo-execute-btn')
                .prop('disabled', false)
                .html('<i class="fas fa-play"></i> اجرا');
            $form.find('.gzseo-preview-btn').prop('disabled', false);
            
            // نمایش پیام موفقیت
            $box.find('.gzseo-progress-log').append(
                '<div class="gzseo-log-item success"><strong>✓ پردازش با موفقیت کامل شد</strong></div>'
            );
            
            // اسکرول به انتهای لاگ
            const $log = $box.find('.gzseo-progress-log');
            $log.scrollTop($log[0].scrollHeight);
        }
        
    });
    
})(jQuery);