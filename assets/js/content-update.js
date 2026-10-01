/**
 * GZSEO Content Update JavaScript
 * 
 * @package GZSEO
 * @subpackage Content_Update
 * @since 2.0.0
 */

(function($) {
    'use strict';

    var GZSEOContentUpdate = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initEditor();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Generate content button
            //$('#gzseo-generate-content').on('click', this.generateContent.bind(this));
			$(document).on('click', '#gzseo-generate-content', this.generateContent.bind(this));
            
            // Close preview
            $('#gzseo-close-preview').on('click', this.closePreview.bind(this));
            
            // Save content
            $('#gzseo-save-content').on('click', this.saveContent.bind(this));
            
            // Delete content
            $(document).on('click', '.gzseo-delete-content', this.deleteContent.bind(this));
            
            // Toggle content
            $(document).on('click', '.gzseo-toggle-content', this.toggleContent.bind(this));
            
            // Update content
            $(document).on('click', '.gzseo-update-content', this.updateContent.bind(this));
            
            // Validate min/max words
			$('#gzseo-min-words, #gzseo-max-words').on('change', this.validateWordLimits.bind(this));
        },
        
        /**
         * Initialize content editor
         */
        initEditor: function() {
            // Make content editable areas more user-friendly
            $('.gzseo-preview-content, .gzseo-item-content').on('focus', function() {
                $(this).addClass('editing');
            }).on('blur', function() {
                $(this).removeClass('editing');
            });
            
            // Add formatting toolbar
            this.addFormattingToolbar();
        },
        
        /**
         * Add formatting toolbar
         */
        addFormattingToolbar: function() {
            var toolbar = `
                <div class="gzseo-formatting-toolbar" style="display: none;">
                    <button type="button" class="gzseo-format-btn" data-format="bold" title="Bold">
                        <i class="fas fa-bold"></i>
                    </button>
                    <button type="button" class="gzseo-format-btn" data-format="italic" title="Italic">
                        <i class="fas fa-italic"></i>
                    </button>
                    <button type="button" class="gzseo-format-btn" data-format="strong" title="Strong">
                        <strong>S</strong>
                    </button>
                </div>
            `;
            
            $('.gzseo-content-update-wrapper').prepend(toolbar);
            
            // Handle text selection
            $(document).on('mouseup', '.gzseo-preview-content, .gzseo-item-content', function(e) {
                var selection = window.getSelection();
                if (selection.toString().length > 0) {
                    var toolbar = $('.gzseo-formatting-toolbar');
                    toolbar.css({
                        display: 'block',
                        position: 'absolute',
                        top: e.pageY - 40,
                        left: e.pageX - 50
                    });
                }
            });
            
            // Hide toolbar on click outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.gzseo-formatting-toolbar, .gzseo-preview-content, .gzseo-item-content').length) {
                    $('.gzseo-formatting-toolbar').hide();
                }
            });
            
            // Handle formatting
			$(document).on('click', '.gzseo-format-btn', function() {
				var format = $(this).data('format');
				document.execCommand(format, false, null);
				$('.gzseo-formatting-toolbar').hide();
			});
        },
        
        /**
         * Generate content
         */
        generateContent: function(e) {
            e.preventDefault();
            
            var $btn = $(e.currentTarget);
            var originalText = $btn.html();
            
            // Validate inputs
            var paragraphCount = parseInt($('#gzseo-paragraph-count').val()) || 0;
			var minWords = parseInt($('#gzseo-min-words').val()) || 0;
			var maxWords = parseInt($('#gzseo-max-words').val()) || 0;
            
            if (!this.validateInputs(paragraphCount, minWords, maxWords)) {
                return;
            }
			
			if (isNaN(paragraphCount) || isNaN(minWords) || isNaN(maxWords)) {
				this.showNotification('error', 'لطفا مقادیر معتبر وارد کنید');
				return;
			}
            
            // Show loading
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> در حال تولید محتوا...');
            
            // AJAX request
            $.ajax({
                url: gzseo_ajax.ajax_url,
				timeout: 30000,
                type: 'POST',
                data: {
                    action: 'gzseo_generate_content',
                    nonce: gzseo_ajax.nonce,
                    post_id: gzseo_ajax.post_id,
                    paragraph_count: paragraphCount,
                    min_words: minWords,
                    max_words: maxWords
                },
                success: function(response) {
                    if (response.success) {
                        // Show preview
                        $('#gzseo-preview-content').html(response.data.content);
                        $('#gzseo-content-preview').slideDown(300);
                        
                        // Scroll to preview
                        $('html, body').animate({
                            scrollTop: $('#gzseo-content-preview').offset().top - 100
                        }, 500);

						// به‌روزرسانی موجودی از طریق AJAX
						$.post(gzseo_ajax.ajax_url, {
							action: 'gzseo_update_ai_credits',
							nonce: gzseo_ajax.nonce
						}, function(response) {
							if (response.success) {
								$('.gzseo-credits-amount').text(response.data.credits);
							}
						});
                        
                        GZSEOContentUpdate.showNotification('success', response.data.message);
                    } else {
                        GZSEOContentUpdate.showNotification('error', response.data.message);
                    }
                },
                error: function(xhr, status, error) {
					var errorMessage = 'خطا در ارتباط با سرور';
					if (status === 'timeout') {
						errorMessage = 'زمان انتظار تمام شد';
					} else if (xhr.responseJSON && xhr.responseJSON.message) {
						errorMessage = xhr.responseJSON.message;
					}
					GZSEOContentUpdate.showNotification('error', errorMessage);
				},
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },
        
        /**
         * Validate inputs
         */
        validateInputs: function(paragraphCount, minWords, maxWords) {
			if (paragraphCount < 1 || paragraphCount > 10) {
				this.showNotification('error', 'تعداد پاراگراف باید بین 1 تا 10 باشد');
				return false;
			}

			if (minWords >= maxWords) {
				$('#gzseo-max-words').addClass('error');
				$('#gzseo-min-words').addClass('error');
				this.showNotification('error', 'حداکثر کلمات باید بیشتر از حداقل کلمات باشد');
				return false; // این خط کم بود
			} else {
				$('#gzseo-max-words').removeClass('error');
				$('#gzseo-min-words').removeClass('error');
			}

			return true;
		},

        /**
         * Validate word limits
         */
        validateWordLimits: function() {
			var minWords = parseInt($('#gzseo-min-words').val());
			var maxWords = parseInt($('#gzseo-max-words').val());

			if (minWords < 50 || minWords > 1000) {
				GZSEOContentUpdate.showNotification('error', 'حداقل کلمات باید بین 50 تا 1000 باشد');
				return false;
			}

			if (maxWords < 100 || maxWords > 2000) {
				GZSEOContentUpdate.showNotification('error', 'حداکثر کلمات باید بین 100 تا 2000 باشد');
				return false;
			}

			if (minWords >= maxWords) {
				GZSEOContentUpdate.showNotification('error', 'حداکثر کلمات باید بیشتر از حداقل کلمات باشد');
				return false;
			}

			return true;
		},
        
        /**
         * Close preview
         */
        closePreview: function(e) {
            e.preventDefault();
            $('#gzseo-content-preview').slideUp(300);
        },
        
        /**
         * Save content
         */
        saveContent: function(e) {
            e.preventDefault();
            
            var $btn = $(e.currentTarget);
            var originalText = $btn.html();
            
            // Get content and position
            var content = $('#gzseo-preview-content').html();
			content = this.sanitizeContent(content);
			
            var position = $('#gzseo-content-position').val();
            
            if (!content) {
                this.showNotification('error', 'محتوایی برای ذخیره وجود ندارد');
                return;
            }
            
            // Show loading
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> در حال ذخیره...');
            
            $.ajax({
                url: gzseo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gzseo_save_generated_content',
                    nonce: gzseo_ajax.nonce,
                    post_id: gzseo_ajax.post_id,
                    content: content,
                    position: position
                },
                success: function(response) {
                    if (response.success) {
                        GZSEOContentUpdate.showNotification('success', response.data.message);
                        
                        // Close preview
                        $('#gzseo-content-preview').slideUp(300);
                        
                        // Reload saved contents
                        GZSEOContentUpdate.reloadSavedContents();
                    } else {
                        GZSEOContentUpdate.showNotification('error', response.data.message);
                    }
                },
                error: function() {
                    GZSEOContentUpdate.showNotification('error', 'خطا در ذخیره محتوا');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },
		
		sanitizeContent: function(content) {
			// حذف script tags و سایر تگ‌های خطرناک
			return content.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
						  .replace(/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/gi, '')
						  .replace(/javascript:/gi, '');
		},
        
        /**
         * Delete content
         */
        deleteContent: function(e) {
            e.preventDefault();
            
            if (!confirm('آیا از حذف این محتوا اطمینان دارید؟')) {
                return;
            }
            
            var $btn = $(e.currentTarget);
            var contentId = $btn.data('content-id');
            var $item = $btn.closest('.gzseo-saved-item');
            
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            
            $.ajax({
                url: gzseo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gzseo_delete_saved_content',
                    nonce: gzseo_ajax.nonce,
					post_id: gzseo_ajax.post_id,
                    content_id: contentId
                },
                success: function(response) {
                    if (response.success) {
                        $item.fadeOut(300, function() {
                            $(this).remove();
                            
                            // Check if no content left
                            if ($('.gzseo-saved-item').length === 0) {
                                $('#gzseo-saved-contents').html(`
                                    <h4><i class="fas fa-list"></i> محتواهای ذخیره شده</h4>
                                    <div class="gzseo-no-content">
                                        <i class="fas fa-inbox"></i>
                                        <p>هنوز محتوایی ذخیره نشده است.</p>
                                    </div>
                                `);
                            }
                        });
                        
                        GZSEOContentUpdate.showNotification('success', response.data.message);
                    } else {
                        GZSEOContentUpdate.showNotification('error', response.data.message);
                    }
                },
                error: function() {
                    GZSEOContentUpdate.showNotification('error', 'خطا در حذف محتوا');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="fas fa-trash"></i> حذف');
                }
            });
        },
        
        /**
         * Toggle content active status
         */
        toggleContent: function(e) {
            e.preventDefault();
            
            var $btn = $(e.currentTarget);
            var contentId = $btn.data('content-id');
            var $item = $btn.closest('.gzseo-saved-item');
            var $status = $item.find('.gzseo-item-status');
            
            $btn.prop('disabled', true);
            
            $.ajax({
                url: gzseo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gzseo_update_saved_content',
                    nonce: gzseo_ajax.nonce,
					post_id: gzseo_ajax.post_id,
                    content_id: contentId,
                    update_action: 'toggle'
                },
                success: function(response) {
                    if (response.success) {
                        // Toggle UI
                        if ($status.hasClass('active')) {
                            $status.removeClass('active').addClass('inactive');
                            $status.html('<i class="fas fa-times-circle"></i> غیرفعال');
                            $btn.html('<i class="fas fa-power-off"></i> فعال');
                        } else {
                            $status.removeClass('inactive').addClass('active');
                            $status.html('<i class="fas fa-check-circle"></i> فعال');
                            $btn.html('<i class="fas fa-power-off"></i> غیرفعال');
                        }
                        
                        GZSEOContentUpdate.showNotification('success', response.data.message);
                    }
                },
                error: function() {
                    GZSEOContentUpdate.showNotification('error', 'خطا در تغییر وضعیت');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        },
        
        /**
         * Update content
         */
        updateContent: function(e) {
            e.preventDefault();
            
            var $btn = $(e.currentTarget);
            var contentId = $btn.data('content-id');
            var $item = $btn.closest('.gzseo-saved-item');
            var $contentDiv = $item.find('.gzseo-item-content');
            var content = $contentDiv.html();
            
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            
            $.ajax({
                url: gzseo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gzseo_update_saved_content',
                    nonce: gzseo_ajax.nonce,
                    post_id: gzseo_ajax.post_id,
                    content_id: contentId,
                    update_action: 'update',
                    content: content
                },
                success: function(response) {
                    if (response.success) {
                        // Flash effect to show update
                        $contentDiv.css('background-color', '#e8f5e9');
                        setTimeout(function() {
                            $contentDiv.css('background-color', '');
                        }, 1000);
                        
                        GZSEOContentUpdate.showNotification('success', response.data.message);
                    }
                },
                error: function() {
                    GZSEOContentUpdate.showNotification('error', 'خطا در به‌روزرسانی محتوا');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="fas fa-edit"></i> ویرایش');
                }
            });
        },
        
        /**
         * Reload saved contents
         */
        reloadSavedContents: function() {
            $.ajax({
                url: gzseo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gzseo_get_saved_contents',
                    nonce: gzseo_ajax.nonce,
                    post_id: gzseo_ajax.post_id
                },
                success: function(response) {
                    if (response.success && response.data.contents.length > 0) {
                        var html = '<h4><i class="fas fa-list"></i> محتواهای ذخیره شده</h4><div class="gzseo-contents-list">';
                        
                        response.data.contents.forEach(function(content) {
                            var statusClass = content.is_active ? 'active' : 'inactive';
                            var statusIcon = content.is_active ? 'check-circle' : 'times-circle';
                            var statusText = content.is_active ? 'فعال' : 'غیرفعال';
                            var toggleText = content.is_active ? 'غیرفعال' : 'فعال';
                            
                            html += `
                                <div class="gzseo-saved-item" data-content-id="${content.id}">
                                    <div class="gzseo-item-header">
                                        <span class="gzseo-item-date">
                                            <i class="fas fa-calendar"></i>
                                            ${GZSEOContentUpdate.formatDate(content.created_at)}
                                        </span>
                                        <span class="gzseo-item-position">
                                            <i class="fas fa-map-marker-alt"></i>
                                            ${GZSEOContentUpdate.getPositionLabel(content.position)}
                                        </span>
                                        <span class="gzseo-item-status ${statusClass}">
                                            <i class="fas fa-${statusIcon}"></i>
                                            ${statusText}
                                        </span>
                                    </div>
                                    
                                    <div class="gzseo-item-content" contenteditable="true">
                                        ${content.content}
                                    </div>
                                    
                                    <div class="gzseo-item-actions">
                                        <button class="gzseo-btn gzseo-btn-sm gzseo-btn-primary gzseo-toggle-content" 
                                                data-content-id="${content.id}">
                                            <i class="fas fa-power-off"></i>
                                            ${toggleText}
                                        </button>
                                        
                                        <button class="gzseo-btn gzseo-btn-sm gzseo-btn-warning gzseo-update-content" 
                                                data-content-id="${content.id}">
                                            <i class="fas fa-edit"></i>
                                            ویرایش
                                        </button>
                                        
                                        <button class="gzseo-btn gzseo-btn-sm gzseo-btn-danger gzseo-delete-content" 
                                                data-content-id="${content.id}">
                                            <i class="fas fa-trash"></i>
                                            حذف
                                        </button>
                                    </div>
                                </div>
                            `;
                        });
                        
                        html += '</div>';
                        $('#gzseo-saved-contents').html(html);
                    }
                }
            });
        },
        
        /**
         * Format date
         */
        formatDate: function(dateString) {
            var date = new Date(dateString);
            var options = {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            };
            return date.toLocaleDateString('fa-IR', options);
        },
        
        /**
         * Get position label
         */
        getPositionLabel: function(position) {
            var labels = {
                'before_content': 'ابتدای محتوا',
                'after_content': 'انتهای محتوا',
                'after_first_paragraph': 'بعد از اولین پاراگراف',
                'after_second_paragraph': 'بعد از دومین پاراگراف',
                'custom': 'موقعیت دلخواه'
            };
            
            return labels[position] || position;
        },
        
        /**
         * Show notification
         */
        showNotification: function(type, message) {
            var icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
            var className = type === 'success' ? 'gzseo-notification-success' : 'gzseo-notification-error';
            
            var $notification = $(`
                <div class="gzseo-notification ${className}">
                    <i class="fas fa-${icon}"></i>
                    <span>${message}</span>
                </div>
            `);
            
            $('body').append($notification);
            
            setTimeout(function() {
                $notification.addClass('show');
            }, 100);
            
            setTimeout(function() {
                $notification.removeClass('show');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }, 3000);
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        if ($('.gzseo-content-update-wrapper').length > 0) {
            GZSEOContentUpdate.init();
        }
    });
	
	/***************************** آپدیت محتوای دسته بندی ها ***************************/
	
	// Taxonomy Content Generation
	jQuery(document).on('click', '#gzseo-generate-taxonomy-content', function() {
		const $btn = jQuery(this);
		const termId = jQuery('#gzseo-taxonomy-id').val();
		const taxonomyType = jQuery('#gzseo-taxonomy-type').val();
		const paragraphCount = jQuery('#gzseo-paragraph-count').val();
		const minWords = jQuery('#gzseo-min-words').val();
		const maxWords = jQuery('#gzseo-max-words').val();

		if (!termId) {
			alert('شناسه دسته‌بندی یافت نشد');
			return;
		}

		$btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> در حال تولید...');

		jQuery.ajax({
			url: gzseo_ajax.ajax_url,
			type: 'POST',
			data: {
				action: 'gzseo_generate_taxonomy_content',
				nonce: gzseo_ajax.nonce,
				term_id: termId,
				taxonomy: taxonomyType,
				paragraph_count: paragraphCount,
				min_words: minWords,
				max_words: maxWords
			},
			success: function(response) {
				if (response.success) {
					jQuery('#gzseo-taxonomy-preview-content').html(response.data.content);
					jQuery('#gzseo-taxonomy-content-preview').slideDown();

					// Update credits
					if (response.data.credits !== undefined) {
						jQuery('.gzseo-credits-amount').text(response.data.credits);
					}
				} else {
					alert(response.data.message || 'خطا در تولید محتوا');
				}
			},
			error: function() {
				alert('خطا در ارتباط با سرور');
			},
			complete: function() {
				$btn.prop('disabled', false).html('<i class="fas fa-magic"></i> تولید محتوا با هوش مصنوعی');
			}
		});
	});

	// Save Taxonomy Content
	jQuery(document).on('click', '#gzseo-save-taxonomy-content', function() {
		const $btn = jQuery(this);
		const termId = jQuery('#gzseo-taxonomy-id').val();
		const taxonomyType = jQuery('#gzseo-taxonomy-type').val();
		const content = jQuery('#gzseo-taxonomy-preview-content').html();
		const position = jQuery('#gzseo-taxonomy-content-position').val();

		if (!content) {
			alert('محتوایی برای ذخیره وجود ندارد');
			return;
		}

		$btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> در حال ذخیره...');

		jQuery.ajax({
			url: gzseo_ajax.ajax_url,
			type: 'POST',
			data: {
				action: 'gzseo_save_taxonomy_content',
				nonce: gzseo_ajax.nonce,
				term_id: termId,
				taxonomy: taxonomyType,
				content: content,
				position: position
			},
			success: function(response) {
				if (response.success) {
					alert(response.data.message);
					location.reload();
				} else {
					alert(response.data.message || 'خطا در ذخیره محتوا');
				}
			},
			error: function() {
				alert('خطا در ارتباط با سرور');
			},
			complete: function() {
				$btn.prop('disabled', false).html('<i class="fas fa-save"></i> ذخیره محتوا');
			}
		});
	});

	// Close Taxonomy Preview
	jQuery(document).on('click', '#gzseo-close-taxonomy-preview', function() {
		jQuery('#gzseo-taxonomy-content-preview').slideUp();
	});

	// Toggle Taxonomy Content Status
	jQuery(document).on('click', '.gzseo-toggle-taxonomy-content', function() {
		const $btn = jQuery(this);
		const contentId = $btn.data('content-id');
		const termId = jQuery('#gzseo-taxonomy-id').val();

		jQuery.ajax({
			url: gzseo_ajax.ajax_url,
			type: 'POST',
			data: {
				action: 'gzseo_update_saved_content',
				nonce: gzseo_ajax.nonce,
				post_id: termId,
				content_id: contentId,
				update_action: 'toggle'
			},
			success: function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.data.message);
				}
			}
		});
	});
    
})(jQuery);