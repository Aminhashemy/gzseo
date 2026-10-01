jQuery(document).ready(function($) {
    'use strict';
    
    let currentImageId = null;
    
    // Generate image button click
    $('#gzseo-generate-image').on('click', function() {
        const $btn = $(this);
        const postId = $('#gzseo-post-id').val();
        const dimensions = $('#gzseo-ai-dimensions').val();
        
        // Validate inputs
        if (!dimensions) {
            showMessage('error', 'لطفا ابعاد تصویر را انتخاب کنید');
            return;
        }
        
        // Parse dimensions
        const [width, height] = dimensions.split('x').map(Number);
        
        // Show loading
        $btn.prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin"></i> ' + gzseo_fi_ajax.messages.generating);
        
        // Hide previous preview
        $('#gzseo-ai-preview').hide();
        
        // Make Ajax request
        $.ajax({
            url: gzseo_fi_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'gzseo_generate_featured_image',
                nonce: gzseo_fi_ajax.nonce,
                post_id: postId,
                width: width,
                height: height
            },
            success: function(response) {
                if (response.success) {
                    currentImageId = response.data.image_id;
                    showPreview(response.data.thumbnail_url);
                    showMessage('success', response.data.message);
                } else {
                    showMessage('error', response.data.message);
                }

				// به‌روزرسانی موجودی از طریق AJAX
				$.post(gzseo_ajax.ajax_url, {
					action: 'gzseo_update_ai_credits',
					nonce: gzseo_ajax.nonce
				}, function(response) {
					if (response.success) {
						$('.gzseo-credits-amount').text(response.data.credits);
					}
				});
            },
            error: function(xhr, status, error) {
                console.error('Ajax error:', error);
                showMessage('error', gzseo_fi_ajax.messages.error);
            },
            complete: function() {
                $btn.prop('disabled', false)
                    .html('<i class="fas fa-magic"></i> تولید تصویر');
            }
        });
    });
    
    // Confirm image button click
    $('#gzseo-confirm-image').on('click', function() {
        if (!confirm(gzseo_fi_ajax.messages.confirm_set)) {
            return;
        }
        
        const $btn = $(this);
        const postId = $('#gzseo-post-id').val();
        
        $btn.prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin"></i> در حال تنظیم...');
        
        $.ajax({
            url: gzseo_fi_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'gzseo_set_featured_image',
                nonce: gzseo_fi_ajax.nonce,
                post_id: postId,
                image_id: currentImageId
            },
            success: function(response) {
                if (response.success) {
                    showMessage('success', response.data.message);
                    
                    // Update WordPress featured image box
                    if (typeof wp !== 'undefined' && wp.media && wp.media.featuredImage) {
                        wp.media.featuredImage.set(currentImageId);
                    }
                    
                    // Reload featured image metabox
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showMessage('error', response.data.message);
                }
            },
            complete: function() {
                $btn.prop('disabled', false)
                    .html('<i class="fas fa-check"></i> تنظیم به عنوان تصویر شاخص');
            }
        });
    });
    
    // Cancel image button click
    $('#gzseo-cancel-image').on('click', function() {
        $('#gzseo-ai-preview').fadeOut();
        currentImageId = null;
    });
    
    // Restore image button click
    $('#gzseo-restore-image').on('click', function() {
        if (!confirm(gzseo_fi_ajax.messages.confirm_restore)) {
            return;
        }
        
        const $btn = $(this);
        const postId = $('#gzseo-post-id').val();
        
        $btn.prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin"></i> در حال بازگردانی...');
        
        $.ajax({
            url: gzseo_fi_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'gzseo_restore_featured_image',
                nonce: gzseo_fi_ajax.nonce,
                post_id: postId
            },
            success: function(response) {
                if (response.success) {
                    showMessage('success', response.data.message);
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showMessage('error', response.data.message);
                }
            },
            complete: function() {
                $btn.prop('disabled', false)
                    .html('<i class="fas fa-undo"></i> بازگردانی تصویر قبلی');
            }
        });
    });
    
    // Show preview function
    function showPreview(imageUrl) {
        const $preview = $('#gzseo-ai-preview');
        $preview.find('img').attr('src', imageUrl);
        $preview.fadeIn();
        
        // Scroll to preview
        $('html, body').animate({
            scrollTop: $preview.offset().top - 100
        }, 500);
    }
    
    // Show message function
    function showMessage(type, message) {
        const $messages = $('#gzseo-ai-messages');
        const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        const typeClass = 'gzseo-message-' + type;
        
        const $message = $(`
            <div class="gzseo-message ${typeClass}">
                <i class="fas ${iconClass}"></i>
                <span>${message}</span>
            </div>
        `);
        
        $messages.html($message);
        $message.fadeIn();
        
        // Auto hide after 5 seconds
        setTimeout(function() {
            $message.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Clear messages on input change
    $('#gzseo-ai-dimensions').on('change', function() {
        $('#gzseo-ai-messages').empty();
    });
});