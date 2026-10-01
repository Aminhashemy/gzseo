/**
 * GZSEO Title & Description Generator JavaScript
 * 
 * @package GZSEO
 * @since 2.0.0
 */

(function($) {
    'use strict';

    // Global variables
    let generatedItems = [];
    let selectedIndex = null;

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('#gzseo-title-desc').length) {
            initTitleDescGenerator();
        }
    });

    /**
     * Initialize title and description generator
     */
    function initTitleDescGenerator() {
        // Generate button click
        $('#gzseo-generate-title-desc').on('click', generateTitleDesc);
        
        // Save button click
        $('#gzseo-save-selected-td').on('click', saveSelectedTitleDesc);
        
        // Radio button change (for selecting generated items)
        $(document).on('change', '.gzseo-td-radio', function() {
            selectedIndex = parseInt($(this).val());
            updatePreview();
        });
        
        // Edit button clicks
        $(document).on('click', '.gzseo-td-edit-btn', function() {
            const index = $(this).data('index');
            enableEdit(index);
        });
        
        // Save edit button clicks
        $(document).on('click', '.gzseo-td-save-edit-btn', function() {
            const index = $(this).data('index');
            saveEdit(index);
        });
        
        // Cancel edit button clicks
        $(document).on('click', '.gzseo-td-cancel-edit-btn', function() {
            const index = $(this).data('index');
            cancelEdit(index);
        });
    }

    /**
     * Generate title and description
     */
    function generateTitleDesc() {
        const $button = $(this);
        const $loading = $('.gzseo-td-loading');
        const $results = $('#gzseo-td-results');
        
        // Disable button and show loading
        $button.prop('disabled', true);
        $loading.show();
        
        // AJAX request
        $.ajax({
            url: gzseo_td_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'gzseo_generate_title_desc',
                nonce: gzseo_td_ajax.nonce,
                post_id: gzseo_td_ajax.post_id
            },
            success: function(response) {
                if (response.success) {
                    // Add to generated items
                    const newItem = {
                        title: response.data.title,
                        description: response.data.description,
                        timestamp: response.data.timestamp,
                        edited: false
                    };
                    
                    generatedItems.push(newItem);
                    
                    // Update UI
                    renderGeneratedItems();
                    $results.slideDown();
                    
                    // Update credits display
                    updateCreditsDisplay(response.data.credits);
                    
					// به‌روزرسانی موجودی از طریق AJAX
					$.post(gzseo_ajax.ajax_url, {
						action: 'gzseo_update_ai_credits',
						nonce: gzseo_ajax.nonce
					}, function(response) {
						if (response.success) {
							$('.gzseo-credits-amount').text(response.data.credits);
						}
					});

                    // Show success message
                    showNotification('success', gzseo_td_ajax.messages.success);
                } else {
                    showNotification('error', response.data.message || gzseo_td_ajax.messages.error);
                }
            },
            error: function() {
                showNotification('error', gzseo_td_ajax.messages.api_error);
            },
            complete: function() {
                $button.prop('disabled', false);
                $loading.hide();
            }
        });
    }

    /**
     * Render generated items
     */
    function renderGeneratedItems() {
        const $container = $('.gzseo-td-results-list');
        $container.empty();
        
        generatedItems.forEach(function(item, index) {
            const $item = createItemElement(item, index);
            $container.append($item);
        });
        
        // Show save button if items exist
        if (generatedItems.length > 0) {
            $('.gzseo-td-save-actions').show();
        }
    }

    /**
     * Create item element
     */
    function createItemElement(item, index) {
        const itemHtml = `
            <div class="gzseo-td-item ${item.edited ? 'gzseo-td-edited' : ''}" data-index="${index}">
                <div class="gzseo-td-item-header">
                    <label class="gzseo-td-radio-label">
                        <input type="radio" name="gzseo_td_selection" class="gzseo-td-radio" value="${index}">
                        <span class="gzseo-td-item-number">نتیجه ${index + 1}</span>
                    </label>
                    <div class="gzseo-td-item-actions">
                        <button type="button" class="gzseo-td-edit-btn" data-index="${index}">
                            <i class="fas fa-edit"></i> ویرایش
                        </button>
                        ${item.edited ? '<span class="gzseo-td-edited-badge">ویرایش شده</span>' : ''}
                    </div>
                </div>
                
                <div class="gzseo-td-item-content">
                    <div class="gzseo-td-field">
                        <label>عنوان:</label>
                        <div class="gzseo-td-value gzseo-td-title-value" data-index="${index}">
                            ${escapeHtml(item.title)}
                        </div>
                        <textarea class="gzseo-td-edit-field gzseo-td-title-edit" data-index="${index}" 
                                  style="display: none;">${escapeHtml(item.title)}</textarea>
                    </div>
                    
                    <div class="gzseo-td-field">
                        <label>توضیحات متا:</label>
                        <div class="gzseo-td-value gzseo-td-desc-value" data-index="${index}">
                            ${escapeHtml(item.description)}
                        </div>
                        <textarea class="gzseo-td-edit-field gzseo-td-desc-edit" data-index="${index}" 
                                  style="display: none;" rows="3">${escapeHtml(item.description)}</textarea>
                    </div>
                    
                    <div class="gzseo-td-edit-actions" data-index="${index}" style="display: none;">
                        <button type="button" class="button button-small gzseo-td-save-edit-btn" data-index="${index}">
                            <i class="fas fa-check"></i> ذخیره تغییرات
                        </button>
                        <button type="button" class="button button-small gzseo-td-cancel-edit-btn" data-index="${index}">
                            <i class="fas fa-times"></i> انصراف
                        </button>
                    </div>
                </div>
                
                <div class="gzseo-td-item-meta">
                    <span class="gzseo-td-char-count">
                        <i class="fas fa-text-width"></i> 
                        عنوان: ${item.title.length} کاراکتر | 
                        توضیحات: ${item.description.length} کاراکتر
                    </span>
                </div>
            </div>
        `;
        
        return $(itemHtml);
    }

    /**
     * Enable edit mode for an item
     */
    function enableEdit(index) {
        const $item = $(`.gzseo-td-item[data-index="${index}"]`);
        
        // Hide values and show edit fields
        $item.find('.gzseo-td-value').hide();
        $item.find('.gzseo-td-edit-field').show();
        $item.find('.gzseo-td-edit-actions').show();
        $item.find('.gzseo-td-item-actions').hide();
        
        // Focus on title field
        $item.find('.gzseo-td-title-edit').focus();
    }

    /**
     * Save edited item
     */
    function saveEdit(index) {
        const $item = $(`.gzseo-td-item[data-index="${index}"]`);
        
        // Get new values
        const newTitle = $item.find('.gzseo-td-title-edit').val().trim();
        const newDesc = $item.find('.gzseo-td-desc-edit').val().trim();
        
        if (!newTitle || !newDesc) {
            showNotification('error', 'عنوان و توضیحات نمیتوانند خالی باشند');
            return;
        }
        
        // Update data
        generatedItems[index].title = newTitle;
        generatedItems[index].description = newDesc;
        generatedItems[index].edited = true;
        
        // Re-render
        renderGeneratedItems();
        
        showNotification('success', 'تغییرات ذخیره شد');
    }

    /**
     * Cancel edit mode
     */
    function cancelEdit(index) {
        const $item = $(`.gzseo-td-item[data-index="${index}"]`);
        
        // Reset edit fields to original values
        $item.find('.gzseo-td-title-edit').val(generatedItems[index].title);
        $item.find('.gzseo-td-desc-edit').val(generatedItems[index].description);
        
        // Hide edit fields and show values
        $item.find('.gzseo-td-edit-field').hide();
        $item.find('.gzseo-td-edit-actions').hide();
        $item.find('.gzseo-td-value').show();
        $item.find('.gzseo-td-item-actions').show();
    }

    /**
     * Save selected title and description
     */
    function saveSelectedTitleDesc() {
        if (selectedIndex === null) {
            showNotification('error', gzseo_td_ajax.messages.no_selection);
            return;
        }
        
        if (!confirm(gzseo_td_ajax.messages.confirm_save)) {
            return;
        }
        
        const selectedItem = generatedItems[selectedIndex];
        const $button = $(this);
        
        // Disable button and show loading
        $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> ' + gzseo_td_ajax.messages.saving);
        
        // AJAX request
        $.ajax({
            url: gzseo_td_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'gzseo_save_title_desc',
                nonce: gzseo_td_ajax.nonce,
                post_id: gzseo_td_ajax.post_id,
                title: selectedItem.title,
                description: selectedItem.description
            },
            success: function(response) {
                if (response.success) {
                    showNotification('success', response.data.message);
                    
                    // Update current display
                    updateCurrentDisplay(selectedItem.title, selectedItem.description);
                    
                    // Reset selection
                    selectedIndex = null;
                    generatedItems = [];
                    $('#gzseo-td-results').slideUp();
                    
                    // Reload page after 2 seconds
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showNotification('error', response.data.message || gzseo_td_ajax.messages.error);
                }
            },
            error: function() {
                showNotification('error', gzseo_td_ajax.messages.api_error);
            },
            complete: function() {
                $button.prop('disabled', false).html('<i class="fas fa-save"></i> ذخیره عنوان و توضیحات انتخابی');
            }
        });
    }

    /**
     * Update preview (optional - for future enhancement)
     */
    function updatePreview() {
        // This function can be used to show a live preview of selected item
        // Currently not implemented but can be added later
    }

    /**
     * Update current display
     */
    function updateCurrentDisplay(title, description) {
        $('.gzseo-td-current-item').eq(0).find('.gzseo-td-current-value').text(title);
        $('.gzseo-td-current-item').eq(1).find('.gzseo-td-current-value').html(description);
    }

    /**
     * Update credits display
     */
    function updateCreditsDisplay(credits) {
        $('.gzseo-credits-amount').text(credits.toLocaleString('fa-IR'));
    }

    /**
     * Show notification
     */
    function showNotification(type, message) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            info: 'info-circle'
        };
        
        const $notification = $(`
            <div class="gzseo-td-notification gzseo-td-notification-${type}">
                <i class="fas fa-${icons[type]}"></i>
                <span>${message}</span>
            </div>
        `);
        
        // Remove existing notifications
        $('.gzseo-td-notification').remove();
        
        // Add new notification
        $('.gzseo-title-desc-wrapper').prepend($notification);
        
        // Auto remove after 5 seconds
        setTimeout(function() {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        
        return text.replace(/[&<>"']/g, function(m) {
            return map[m];
        });
    }
	
	/***************************** آپدیت عنوان و توضیحات متای تکسونومی ها *****************************/
	
	// Generate Taxonomy Title & Description
	jQuery(document).on('click', '#gzseo-generate-taxonomy-title-desc', function() {
		const $btn = jQuery(this);
		const termId = jQuery('#gzseo-taxonomy-term-id').val();
		const taxonomy = jQuery('#gzseo-taxonomy-type').val();

		if (!termId) {
			alert('شناسه دسته‌بندی یافت نشد');
			return;
		}

		$btn.prop('disabled', true);
		jQuery('.gzseo-td-loading').show();

		jQuery.ajax({
			url: gzseo_td_ajax.ajax_url,
			type: 'POST',
			data: {
				action: 'gzseo_generate_taxonomy_title_desc',
				nonce: gzseo_td_ajax.nonce,
				term_id: termId,
				taxonomy: taxonomy
			},
			success: function(response) {
				if (response.success) {
					// Display results
					const resultHtml = `
						<div class="gzseo-td-result-item">
							<label>
								<input type="radio" name="gzseo_td_selection" value="generated" checked>
								<div class="gzseo-td-result-content">
									<div class="gzseo-td-result-title">
										<strong>عنوان:</strong> ${response.data.title}
									</div>
									<div class="gzseo-td-result-desc">
										<strong>توضیحات:</strong> ${response.data.description}
									</div>
								</div>
							</label>
						</div>
					`;

					jQuery('#gzseo-taxonomy-td-results .gzseo-td-results-list').html(resultHtml);
					jQuery('#gzseo-taxonomy-td-results').slideDown();
					jQuery('.gzseo-td-save-actions').show();

					// Update credits
					if (response.data.credits !== undefined) {
						jQuery('.gzseo-credits-amount').text(response.data.credits);
					}

					// Store data
					jQuery('#gzseo-taxonomy-td-results').data('generated-title', response.data.title);
					jQuery('#gzseo-taxonomy-td-results').data('generated-description', response.data.description);

				} else {
					alert(response.data.message || 'خطا در تولید عنوان و توضیحات');
				}
			},
			error: function() {
				alert('خطا در ارتباط با سرور');
			},
			complete: function() {
				$btn.prop('disabled', false);
				jQuery('.gzseo-td-loading').hide();
			}
		});
	});

	// Save Taxonomy Title & Description
	jQuery(document).on('click', '#gzseo-save-selected-taxonomy-td', function() {
		const $btn = jQuery(this);
		const termId = jQuery('#gzseo-taxonomy-term-id').val();
		const taxonomy = jQuery('#gzseo-taxonomy-type').val();
		const title = jQuery('#gzseo-taxonomy-td-results').data('generated-title');
		const description = jQuery('#gzseo-taxonomy-td-results').data('generated-description');

		if (!title || !description) {
			alert('لطفا یک عنوان و توضیحات را انتخاب کنید');
			return;
		}

		if (!confirm('آیا از ذخیره این عنوان و توضیحات اطمینان دارید؟')) {
			return;
		}

		$btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> در حال ذخیره...');

		jQuery.ajax({
			url: gzseo_td_ajax.ajax_url,
			type: 'POST',
			data: {
				action: 'gzseo_save_taxonomy_title_desc',
				nonce: gzseo_td_ajax.nonce,
				term_id: termId,
				taxonomy: taxonomy,
				title: title,
				description: description
			},
			success: function(response) {
				if (response.success) {
					alert(response.data.message);
					location.reload();
				} else {
					alert(response.data.message || 'خطا در ذخیره');
				}
			},
			error: function() {
				alert('خطا در ارتباط با سرور');
			},
			complete: function() {
				$btn.prop('disabled', false).html('<i class="fas fa-save"></i> ذخیره عنوان و توضیحات انتخابی');
			}
		});
	});

})(jQuery);