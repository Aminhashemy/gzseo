/**
 * GZ SEO Bulk Update JavaScript
 * 
 * @package GZ_SEO
 * @since 2.0.0
 */

(function($) {
    'use strict';
    
    const GZSEOBulkUpdate = {
        
        // Properties
        currentIndex: null,
        totalItems: 0,
        processedItems: 0,
        isProcessing: false,
        
        // Initialize
        init: function() {
            this.bindEvents();
        },
        
        // Bind events
        bindEvents: function() {
            // Preview button
            $(document).on('click', '.gzseo-preview-bulk', this.handlePreview.bind(this));
            
            // Execute button
            $(document).on('click', '.gzseo-execute-bulk', this.handleExecute.bind(this));
            
            // Edit saved item
            $(document).on('click', '.gzseo-edit-saved-item', this.handleEdit.bind(this));
            
            // Update saved item
            $(document).on('click', '.gzseo-update-saved-item', this.handleUpdate.bind(this));
            
            // Cancel edit
            $(document).on('click', '.gzseo-cancel-edit', this.handleCancelEdit.bind(this));
            
            // Input validation
            $(document).on('input', '.gzseo-update-count', this.validateCount.bind(this));
        },
        
        // Validate count input
        validateCount: function(e) {
            const $input = $(e.currentTarget);
            const max = parseInt($input.attr('max'));
            const value = parseInt($input.val());
            
            if (value > max) {
                $input.val(max);
            } else if (value < 1) {
                $input.val(1);
            }
        },
        
        // Handle preview
        handlePreview: function(e) {
            e.preventDefault();
            
            const $btn = $(e.currentTarget);
            const $item = $btn.closest('.gzseo-bulk-update-item');
            const $count = $item.find('.gzseo-update-count');
            const index = $btn.data('index');
            const count = parseInt($count.val());
            
            if (!count || count < 1) {
                this.showMessage('error', 'لطفا تعداد معتبری وارد کنید');
                return;
            }
            
            // Show loading
            $btn.prop('disabled', true)
                .html('<i class="fas fa-spinner fa-spin"></i> در حال بارگذاری...');
            
            // Hide previous preview if exists
            $item.find('.gzseo-preview-results').hide();
            
            // Make request
            $.ajax({
                url: gzseo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gzseo_bulk_preview',
                    nonce: gzseo_ajax.nonce,
                    index: index,
                    count: count
                },
                success: (response) => {
                    if (response.success) {
                        this.showPreviewResults($item, response.data.posts);
                    } else {
                        this.showMessage('error', response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Preview error:', xhr);
                    this.showMessage('error', 'خطا در دریافت پیش‌نمایش');
                },
                complete: () => {
                    $btn.prop('disabled', false)
                        .html('<i class="fas fa-eye"></i> پیش‌نمایش');
                }
            });
        },
        
        // Show preview results
        showPreviewResults: function($item, posts) {
            const $preview = $item.find('.gzseo-preview-results');
            const $list = $preview.find('.gzseo-preview-list');
            
            // Clear previous results
            $list.empty();
            
            // Add posts to list
            if (posts.length > 0) {
                posts.forEach((post, index) => {
                    $list.append(`
                        <div class="gzseo-preview-item">
                            ${index + 1}. ${post.title} (ID: ${post.id})
                        </div>
                    `);
                });
                
                $preview.slideDown();
            } else {
                this.showMessage('info', 'هیچ موردی یافت نشد');
            }
        },
        
        // Handle execute
        handleExecute: function(e) {
            e.preventDefault();
            
            if (this.isProcessing) {
                this.showMessage('error', 'در حال حاضر در حال پردازش هستیم، لطفا صبر کنید');
                return;
            }
            
            const $btn = $(e.currentTarget);
            const $item = $btn.closest('.gzseo-bulk-update-item');
            const $count = $item.find('.gzseo-update-count');
            const index = $btn.data('index');
            const count = parseInt($count.val());
            
            if (!count || count < 1) {
                this.showMessage('error', 'لطفا تعداد معتبری وارد کنید');
                return;
            }
            
            // Confirm action
            if (!confirm(`آیا از آپدیت ${count} مورد مطمئن هستید؟`)) {
                return;
            }
            
            // Initialize processing
            this.currentIndex = index;
            this.totalItems = count;
            this.processedItems = 0;
            this.isProcessing = true;
            
            // Show progress section
            const $progress = $item.find('.gzseo-progress-section');
            $progress.show();
            
            // Update progress info
            $progress.find('.gzseo-progress-total').text(count);
            $progress.find('.gzseo-progress-current').text(0);
            $progress.find('.gzseo-progress-fill').css('width', '0%');
            $progress.find('.gzseo-progress-log').empty();
            
            // Disable buttons
            $btn.prop('disabled', true);
            $item.find('.gzseo-preview-bulk').prop('disabled', true);
            
            // Start processing
            this.processBatch($item, 0, count);
        },
        
        // Process batch
        processBatch: function($item, offset, total) {
            const batchSize = 5; // تعداد آیتم در هر دسته
            const remaining = total - offset;
            const currentBatch = Math.min(batchSize, remaining);
            
            // Make request
            $.ajax({
                url: gzseo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gzseo_bulk_execute',
                    nonce: gzseo_ajax.nonce,
                    index: this.currentIndex,
                    offset: offset,
                    count: currentBatch
                },
                success: (response) => {
                    if (response.success) {
                        this.updateProgress($item, offset + currentBatch, response.data);
                        
                        // Check if need to continue
                        if (offset + currentBatch < total) {
                            // Continue with next batch
                            setTimeout(() => {
                                this.processBatch($item, offset + currentBatch, total);
                            }, 1000); // تاخیر 1 ثانیه بین دسته‌ها
                        } else {
                            // Complete
                            this.completeProcessing($item);
                        }
                    } else {
                        this.handleProcessError($item, response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Execute error:', xhr);
                    this.handleProcessError($item, 'خطا در ارتباط با سرور');
                }
            });
        },
        
        // Update progress
        updateProgress: function($item, processed, data) {
            const $progress = $item.find('.gzseo-progress-section');
            const percentage = Math.round((processed / this.totalItems) * 100);
            
            // Update progress bar
            $progress.find('.gzseo-progress-fill').css('width', percentage + '%');
            $progress.find('.gzseo-progress-current').text(processed);
            
            // Add log entries
            if (data.logs && data.logs.length > 0) {
                const $log = $progress.find('.gzseo-progress-log');
                data.logs.forEach(log => {
                    $log.append(`
                        <div class="gzseo-progress-log-item ${log.status}">
                            ${log.message}
                        </div>
                    `);
                });
                
                // Scroll to bottom
                $log.scrollTop($log[0].scrollHeight);
            }
        },
        
        // Complete processing
        completeProcessing: function($item) {
            this.isProcessing = false;
            
            // Enable buttons
            $item.find('.gzseo-execute-bulk').prop('disabled', false);
            $item.find('.gzseo-preview-bulk').prop('disabled', false);
            
            // Show success message
            this.showMessage('success', `آپدیت ${this.totalItems} مورد با موفقیت انجام شد`);
            
            // Update stats
            setTimeout(() => {
                location.reload();
            }, 3000);
        },
        
        // Handle process error
        handleProcessError: function($item, message) {
            this.isProcessing = false;
            
            // Enable buttons
            $item.find('.gzseo-execute-bulk').prop('disabled', false);
            $item.find('.gzseo-preview-bulk').prop('disabled', false);
            
            // Show error message
            this.showMessage('error', message);
        },
        
        // Handle edit
        handleEdit: function(e) {
            e.preventDefault();
            
            const $btn = $(e.currentTarget);
            const $item = $btn.closest('.gzseo-bulk-update-item');
            const $fields = $item.find('.gzseo-bulk-update-fields');
            const $content = $item.find('.gzseo-bulk-item-content');
            
            // Toggle visibility
            $fields.slideToggle();
            $content.slideToggle();
        },
        
        // Handle update
        handleUpdate: function(e) {
            e.preventDefault();
            
            const $btn = $(e.currentTarget);
            const $item = $btn.closest('.gzseo-bulk-update-item');
            const index = $btn.data('index');
            
            // Collect data
            const data = {
                action: 'gzseo_save_bulk_update',
                nonce: gzseo_ajax.nonce,
                bulk_action: 'update',
                index: index,
                post_type: $item.find('select[name="post_type"]').val(),
                last_update: $item.find('input[name="last_update"]').val(),
                update_type: $item.find('select[name="update_type"]').val(),
                taxonomy_type: $item.find('select[name="taxonomy_type"]').val(),
                selected_terms: []
            };
            
            // Get selected terms
            $item.find('input[name="selected_terms[]"]:checked').each(function() {
                data.selected_terms.push($(this).val());
            });
            
            // Show loading
            $btn.prop('disabled', true)
                .html('<i class="fas fa-spinner fa-spin"></i> در حال بروزرسانی...');
            
            // Make request
            $.ajax({
                url: gzseo_ajax.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.showMessage('success', response.data.message);
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        this.showMessage('error', response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Update error:', xhr);
                    this.showMessage('error', 'خطا در بروزرسانی');
                },
                complete: () => {
                    $btn.prop('disabled', false)
                        .html('بروزرسانی');
                }
            });
        },
        
        // Handle cancel edit
        handleCancelEdit: function(e) {
            e.preventDefault();
            
            const $item = $(e.currentTarget).closest('.gzseo-bulk-update-item');
            const $fields = $item.find('.gzseo-bulk-update-fields');
            const $content = $item.find('.gzseo-bulk-item-content');
            
            $fields.slideUp();
            $content.slideDown();
        },
        
        // Show message
        showMessage: function(type, message) {
            const $container = $('#gzseo-bulk-messages');
            const $message = $(`
                <div class="gzseo-message gzseo-message-${type}">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                    ${message}
                </div>
            `);
            
            $container.html($message);
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        if ($('#gzseo-saved-bulk-items').length > 0) {
            GZSEOBulkUpdate.init();
        }
    });
    
})(jQuery);