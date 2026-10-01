/**
 * GZ SEO Table Generator JavaScript - Enhanced Version
 * 
 * @package GZ_SEO
 * @since 2.0.0
 */

(function($) {
    'use strict';
    
    const GZSEOTableGenerator = {
        
        // Properties
        currentTableHtml: '',
        currentTableId: '',
        
        // Initialize
        init: function() {
            if (typeof gzseo_tg_ajax === 'undefined') {
                console.error('GZSEO: gzseo_tg_ajax is not defined');
                return;
            }
            
            this.bindEvents();
            this.loadExistingTables();
        },
        
        // Bind events
        bindEvents: function() {
            // Generate table button
            $('#gzseo-tg-generate').on('click', this.generateTable.bind(this));
            
            // Save preview button
            $('#gzseo-tg-save-preview').on('click', this.savePreview.bind(this));
            
            // Cancel preview button
            $('#gzseo-tg-cancel-preview').on('click', this.cancelPreview.bind(this));
            
            // Edit table button
            $(document).on('click', '.gzseo-edit-table', this.editTable.bind(this));
            
            // Delete table button
            $(document).on('click', '.gzseo-delete-table', this.deleteTable.bind(this));
            
            // Save table changes
            $(document).on('click', '.gzseo-save-table-changes', this.saveTableChanges.bind(this));
            
            // Cancel table edit
            $(document).on('click', '.gzseo-cancel-table-edit', this.cancelTableEdit.bind(this));
            
            // Position change
            $(document).on('change', '.gzseo-table-position', this.handlePositionChange.bind(this));
            
            // Table editing events
            $(document).on('click', '.gzseo-add-row', this.addRow.bind(this));
            $(document).on('click', '.gzseo-add-column', this.addColumn.bind(this));
            $(document).on('click', '.gzseo-add-row-preview', this.addRowPreview.bind(this));
            $(document).on('click', '.gzseo-add-column-preview', this.addColumnPreview.bind(this));
            $(document).on('click', '.gzseo-remove-row', this.removeRow.bind(this));
            $(document).on('click', '.gzseo-remove-col', this.removeColumn.bind(this));
            
            // Content editable blur event for auto-save
            $(document).on('blur', '[contenteditable="true"]', this.handleCellEdit.bind(this));
            
            // Enable contenteditable in admin area
            this.enableEditableContent();
        },
        
        // Enable editable content in admin
        enableEditableContent: function() {
            // فعال کردن ویرایش محتوا در ادمین
            if ($('.gzseo-table-generator-wrapper').length > 0) {
                $('.gzseo-table-preview table td[contenteditable], .gzseo-table-preview table th[contenteditable]').attr('contenteditable', 'true');
                $('#gzseo-tg-preview-content table td[contenteditable], #gzseo-tg-preview-content table th[contenteditable]').attr('contenteditable', 'true');
            }
        },
        
        // Load existing tables
        loadExistingTables: function() {
            // Tables are already loaded from PHP
            this.enableEditableContent();
        },
        
        // Generate table
        generateTable: function() {
            const $btn = $('#gzseo-tg-generate');
            const content = $('#gzseo-tg-content').val();
            
            if (!content) {
                this.showMessage('error', 'محتوای صفحه خالی است');
                return;
            }
            
            // Show loading
            $btn.prop('disabled', true)
                .html('<i class="fas fa-spinner fa-spin"></i> در حال تولید جدول...');
            
            // Prepare data
            const data = {
                action: 'gzseo_generate_table',
                nonce: gzseo_tg_ajax.nonce,
                post_id: $('#gzseo-tg-object-id').val(),
                object_type: $('#gzseo-tg-object-type').val(),
                content: content
            };
            
            // Make request
            $.ajax({
                url: gzseo_tg_ajax.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.currentTableHtml = response.data.table_html;
                        this.currentTableId = response.data.table_id;
                        this.showPreview(response.data.table_html);
                        this.showMessage('success', response.data.message);
                        this.enableEditableContent();
                    } else {
                        this.showMessage('error', response.data.message);
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
                error: (xhr, status, error) => {
                    console.error('Ajax error:', xhr);
                    this.showMessage('error', 'خطا در ارتباط با سرور');
                },
                complete: () => {
                    $btn.prop('disabled', false)
                        .html('<i class="fas fa-plus"></i> افزودن جدول جدید');
                }
            });
        },
        
        // Show preview
        showPreview: function(tableHtml) {
            $('#gzseo-tg-preview-content').html(tableHtml);
            $('#gzseo-tg-preview').slideDown();
            $('#gzseo-tg-generate').hide();
            this.enableEditableContent();
        },
        
        // Save preview
        savePreview: function() {
            const position = $('#gzseo-tg-position').val();
            const tableHtml = $('#gzseo-tg-preview-content').html();
            
            this.saveTableData(this.currentTableId, tableHtml, position, true);
        },
        
        // Cancel preview
        cancelPreview: function() {
            $('#gzseo-tg-preview').slideUp();
            $('#gzseo-tg-generate').show();
            this.currentTableHtml = '';
            this.currentTableId = '';
        },
        
        // Edit table
        editTable: function(e) {
            e.preventDefault();
            const $tableItem = $(e.currentTarget).closest('.gzseo-table-item');
            const $content = $tableItem.find('.gzseo-table-content');
            
            // Toggle content visibility
            $content.slideToggle(() => {
                if ($content.is(':visible')) {
					$content.find('table td, table th').not('.gzseo-table-actions, .gzseo-row-actions').attr('contenteditable', 'true');
                    this.enableEditableContent();
                }
            });
        },
        
        // Save table changes
        saveTableChanges: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const $tableItem = $btn.closest('.gzseo-table-item');
            const tableId = $tableItem.data('table-id');
            const tableHtml = $tableItem.find('.gzseo-table-preview').html();
            const position = $tableItem.find('.gzseo-table-position').val();
            
            // Update table
            this.updateTableData(tableId, tableHtml, position, $btn);
        },
        
        // Cancel table edit
        cancelTableEdit: function(e) {
            e.preventDefault();
            const $tableItem = $(e.currentTarget).closest('.gzseo-table-item');
            $tableItem.find('.gzseo-table-content').slideUp();
        },
        
        // Delete table
        deleteTable: function(e) {
            e.preventDefault();
            
            if (!confirm('آیا از حذف این جدول مطمئن هستید؟')) {
                return;
            }
            
            const $btn = $(e.currentTarget);
            const $tableItem = $btn.closest('.gzseo-table-item');
            const tableId = $tableItem.data('table-id');
            
            // Show loading
            $btn.prop('disabled', true)
                .html('<i class="fas fa-spinner fa-spin"></i>');
            
            // Prepare data
            const data = {
                action: 'gzseo_delete_table',
                nonce: gzseo_tg_ajax.nonce,
                object_id: $('#gzseo-tg-object-id').val(),
                object_type: $('#gzseo-tg-object-type').val(),
                table_id: tableId
            };
            
            // Make request
            $.ajax({
                url: gzseo_tg_ajax.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.showMessage('success', response.data.message);
                        $tableItem.fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        this.showMessage('error', response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Delete error:', xhr);
                    this.showMessage('error', 'خطا در حذف جدول');
                },
                complete: () => {
                    $btn.prop('disabled', false)
                        .html('<i class="fas fa-trash"></i>');
                }
            });
        },
        
        // Save table data
        saveTableData: function(tableId, tableHtml, position, isNew = false) {
            const $btn = isNew ? $('#gzseo-tg-save-preview') : $('.gzseo-save-table-changes');
            
            // Show loading
            $btn.prop('disabled', true)
                .html('<i class="fas fa-spinner fa-spin"></i> در حال ذخیره...');
            
            // Prepare data
            const data = {
                action: 'gzseo_save_table',
                nonce: gzseo_tg_ajax.nonce,
                object_id: $('#gzseo-tg-object-id').val(),
                object_type: $('#gzseo-tg-object-type').val(),
                table_id: tableId,
                table_html: tableHtml,
                position: position
            };
            
            // Make request
            $.ajax({
                url: gzseo_tg_ajax.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.showMessage('success', response.data.message);
                        
                        if (isNew) {
                            // Reload page to show new table
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        }
                    } else {
                        this.showMessage('error', response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Save error:', xhr);
                    this.showMessage('error', 'خطا در ذخیره جدول');
                },
                complete: () => {
                    $btn.prop('disabled', false)
                        .html('<i class="fas fa-save"></i> ذخیره جدول');
                }
            });
        },
        
        // Update table data
        updateTableData: function(tableId, tableHtml, position, $btn) {
            // Show loading
            $btn.prop('disabled', true)
                .html('<i class="fas fa-spinner fa-spin"></i> در حال ذخیره...');
            
            // Prepare data
            const data = {
                action: 'gzseo_update_table',
                nonce: gzseo_tg_ajax.nonce,
                object_id: $('#gzseo-tg-object-id').val(),
                object_type: $('#gzseo-tg-object-type').val(),
                table_id: tableId,
                table_html: tableHtml,
                position: position
            };
            
            // Make request
            $.ajax({
                url: gzseo_tg_ajax.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.showMessage('success', response.data.message);
                        $btn.closest('.gzseo-table-content').slideUp();
                    } else {
                        this.showMessage('error', response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Update error:', xhr);
                    this.showMessage('error', 'خطا در بروزرسانی جدول');
                },
                complete: () => {
                    $btn.prop('disabled', false)
                        .html('ذخیره تغییرات');
                }
            });
        },
        
        // Add row
        addRow: function(e) {
            e.preventDefault();
            const $table = $(e.currentTarget).closest('.gzseo-table-editor').find('table tbody');
            const columnCount = $table.find('tr:first td, tr:first th').length - 1; // Minus action column
            
            // حذف سطر دکمه‌های حذف ستون
            const $colActionsRow = $table.find('.gzseo-col-actions-row');
            
            let newRow = '<tr>';
            newRow += '<td class="gzseo-table-actions gzseo-row-actions"><button class="gzseo-remove-row" title="حذف سطر"><i class="fas fa-times"></i></button></td>';
            newRow += '<th contenteditable="true">عنوان جدید</th>';
            for (let i = 1; i < columnCount; i++) {
                newRow += '<td contenteditable="true">محتوا</td>';
            }
            newRow += '</tr>';
            
            // اضافه کردن سطر جدید قبل از سطر دکمه‌های حذف ستون
            if ($colActionsRow.length > 0) {
                $colActionsRow.before(newRow);
            } else {
                $table.append(newRow);
            }
            
            this.enableEditableContent();
        },
        
        // Add column
        addColumn: function(e) {
            e.preventDefault();
            const $table = $(e.currentTarget).closest('.gzseo-table-editor').find('table');
            
            $table.find('tr').each(function() {
                const $row = $(this);
                
                if ($row.hasClass('gzseo-col-actions-row')) {
                    // اضافه کردن دکمه حذف ستون
                    const colIndex = $row.find('td').length - 1;
                    $row.append('<td class="gzseo-table-actions gzseo-col-actions"><button class="gzseo-remove-col" data-col="' + colIndex + '" title="حذف ستون"><i class="fas fa-times"></i></button></td>');
                } else {
                    // اضافه کردن سلول جدید به سطرهای داده
                    $row.append('<td contenteditable="true">محتوا</td>');
                }
            });
            
            this.enableEditableContent();
        },
        
        // Add row in preview
        addRowPreview: function(e) {
            e.preventDefault();
            const $table = $('#gzseo-tg-preview-content').find('table tbody');
            const columnCount = $table.find('tr:first td, tr:first th').length - 1;
            
            // حذف سطر دکمه‌های حذف ستون
            const $colActionsRow = $table.find('.gzseo-col-actions-row');
            
            let newRow = '<tr>';
            newRow += '<td class="gzseo-table-actions gzseo-row-actions"><button class="gzseo-remove-row" title="حذف سطر"><i class="fas fa-times"></i></button></td>';
            newRow += '<th contenteditable="true">عنوان جدید</th>';
            for (let i = 1; i < columnCount; i++) {
                newRow += '<td contenteditable="true">محتوا</td>';
            }
            newRow += '</tr>';
            
            // اضافه کردن سطر جدید قبل از سطر دکمه‌های حذف ستون
            if ($colActionsRow.length > 0) {
                $colActionsRow.before(newRow);
            } else {
                $table.append(newRow);
            }
            
            this.enableEditableContent();
        },
        
        // Add column in preview
        addColumnPreview: function(e) {
            e.preventDefault();
            const $table = $('#gzseo-tg-preview-content').find('table');
            
            $table.find('tr').each(function() {
                const $row = $(this);
                
                if ($row.hasClass('gzseo-col-actions-row')) {
                    // اضافه کردن دکمه حذف ستون
                    const colIndex = $row.find('td').length - 1;
                    $row.append('<td class="gzseo-table-actions gzseo-col-actions"><button class="gzseo-remove-col" data-col="' + colIndex + '" title="حذف ستون"><i class="fas fa-times"></i></button></td>');
                } else {
                    // اضافه کردن سلول جدید به سطرهای داده
                    $row.append('<td contenteditable="true">محتوا</td>');
                }
            });
            
            this.enableEditableContent();
        },
        
        // Remove row
        removeRow: function(e) {
            e.preventDefault();
            if (confirm('آیا از حذف این سطر مطمئن هستید؟')) {
                $(e.currentTarget).closest('tr').remove();
            }
        },
        
        // Remove column
        removeColumn: function(e) {
            e.preventDefault();
            
            if (confirm('آیا از حذف این ستون مطمئن هستید؟')) {
                const $btn = $(e.currentTarget);
                const colIndex = parseInt($btn.data('col'));
                const $table = $btn.closest('table');
                
                // حذف ستون از تمام سطرها
                $table.find('tr').each(function() {
                    const $row = $(this);
                    if ($row.hasClass('gzseo-col-actions-row')) {
                        // حذف دکمه حذف ستون
                        $row.find('td').eq(colIndex + 1).remove(); // +1 برای دکمه حذف سطر
                    } else {
                        // حذف سلول داده
                        $row.find('td, th').eq(colIndex + 1).remove(); // +1 برای دکمه حذف سطر
                    }
                });
                
                // بروزرسانی data-col برای دکمه‌های باقی‌مانده
                $table.find('.gzseo-remove-col').each(function(index) {
                    $(this).attr('data-col', index);
                });
            }
        },
        
        // Handle cell edit
        handleCellEdit: function(e) {
            // Auto-save functionality can be implemented here
            const $cell = $(e.currentTarget);
            const newContent = $cell.text();
            
            // Mark table as modified
            $cell.closest('.gzseo-table-item').addClass('modified');
        },
        
        // Handle position change
        handlePositionChange: function(e) {
            const $select = $(e.currentTarget);
            const tableId = $select.data('table-id');
            const position = $select.val();
            
            // Update position immediately
            this.updateTablePosition(tableId, position);
        },
        
        // Update table position
        updateTablePosition: function(tableId, position) {
            const data = {
                action: 'gzseo_update_table',
                nonce: gzseo_tg_ajax.nonce,
                object_id: $('#gzseo-tg-object-id').val(),
                object_type: $('#gzseo-tg-object-type').val(),
                table_id: tableId,
                position: position
            };
            
            $.ajax({
                url: gzseo_tg_ajax.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.showMessage('success', 'موقعیت جدول بروزرسانی شد');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Position update error:', xhr);
                }
            });
        },
        
        // Show message
        showMessage: function(type, message) {
            const $container = $('#gzseo-tg-messages');
            const $message = $(`
                <div class="gzseo-message gzseo-message-${type}">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                    ${message}
                </div>
            `);
            
            $container.html($message);
            
            setTimeout(() => {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        if ($('.gzseo-table-generator-wrapper').length > 0) {
            GZSEOTableGenerator.init();
        }
    });
	
	/****************************** آپدیت جداول دسته بندی ها *****************************/
	// Generate Taxonomy Table
	jQuery(document).on('click', '#gzseo-tg-generate-taxonomy', function() {
		const $btn = jQuery(this);
		const termId = jQuery('#gzseo-tg-term-id').val();
		const taxonomy = jQuery('#gzseo-tg-taxonomy').val();
		const content = jQuery('#gzseo-tg-content').val();

		if (!content) {
			alert('محتوایی برای تولید جدول یافت نشد');
			return;
		}

		$btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> در حال تولید...');

		jQuery.ajax({
			url: gzseo_tg_ajax.ajax_url,
			type: 'POST',
			data: {
				action: 'gzseo_generate_table',
				nonce: gzseo_tg_ajax.nonce,
				object_id: termId,
				object_type: 'term',
				content: content
			},
			success: function(response) {
				if (response.success) {
					jQuery('#gzseo-tg-taxonomy-preview-content').html(response.data.table_html);
					jQuery('#gzseo-tg-taxonomy-preview').slideDown();

					// Store table ID
					jQuery('#gzseo-tg-taxonomy-preview').data('table-id', response.data.table_id);
				} else {
					alert(response.data.message || 'خطا در تولید جدول');
				}
			},
			error: function() {
				alert('خطا در ارتباط با سرور');
			},
			complete: function() {
				$btn.prop('disabled', false).html('<i class="fas fa-plus"></i> افزودن جدول جدید');
			}
		});
	});

	// Save Taxonomy Table
	jQuery(document).on('click', '#gzseo-tg-save-taxonomy-preview', function() {
		const $btn = jQuery(this);
		const termId = jQuery('#gzseo-tg-term-id').val();
		const taxonomy = jQuery('#gzseo-tg-taxonomy').val();
		const tableHtml = jQuery('#gzseo-tg-taxonomy-preview-content').html();
		const position = jQuery('#gzseo-tg-taxonomy-position').val();
		const tableId = jQuery('#gzseo-tg-taxonomy-preview').data('table-id');

		if (!tableHtml) {
			alert('جدولی برای ذخیره وجود ندارد');
			return;
		}

		$btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> در حال ذخیره...');

		jQuery.ajax({
			url: gzseo_tg_ajax.ajax_url,
			type: 'POST',
			data: {
				action: 'gzseo_save_table',
				nonce: gzseo_tg_ajax.nonce,
				object_id: termId,
				object_type: 'term',
				table_html: tableHtml,
				position: position,
				table_id: tableId
			},
			success: function(response) {
				if (response.success) {
					alert(response.data.message);
					location.reload();
				} else {
					alert(response.data.message || 'خطا در ذخیره جدول');
				}
			},
			error: function() {
				alert('خطا در ارتباط با سرور');
			},
			complete: function() {
				$btn.prop('disabled', false).html('<i class="fas fa-save"></i> ذخیره جدول');
			}
		});
	});

	// Cancel Taxonomy Table Preview
	jQuery(document).on('click', '#gzseo-tg-cancel-taxonomy-preview', function() {
		jQuery('#gzseo-tg-taxonomy-preview').slideUp();
	});

	// Edit Taxonomy Table
	jQuery(document).on('click', '.gzseo-edit-taxonomy-table', function(e) {
        e.preventDefault();
		const $item = jQuery(this).closest('.gzseo-table-item');
		$item.find('.gzseo-table-content').slideToggle();
	});

	// Delete Taxonomy Table
	jQuery(document).on('click', '.gzseo-delete-taxonomy-table', function() {
		if (!confirm('آیا از حذف این جدول مطمئن هستید؟')) {
			return;
		}

		const $item = jQuery(this).closest('.gzseo-table-item');
		const tableId = $item.data('table-id');
		const termId = jQuery('#gzseo-tg-term-id').val();

		jQuery.ajax({
			url: gzseo_tg_ajax.ajax_url,
			type: 'POST',
			data: {
				action: 'gzseo_delete_table',
				nonce: gzseo_tg_ajax.nonce,
				object_id: termId,
				object_type: 'term',
				table_id: tableId
			},
			success: function(response) {
				if (response.success) {
					$item.fadeOut(function() {
						jQuery(this).remove();
					});
				} else {
					alert(response.data.message);
				}
			}
		});
	});

	// Save Taxonomy Table Changes
	jQuery(document).on('click', '.gzseo-save-taxonomy-table-changes', function() {
		const $btn = jQuery(this);
		const $item = $btn.closest('.gzseo-table-item');
		const tableId = $item.data('table-id');
		const termId = jQuery('#gzseo-tg-term-id').val();
		const tableHtml = $item.find('.gzseo-table-preview').html();
		const position = $item.find('.gzseo-table-position').val();

		$btn.prop('disabled', true);

		jQuery.ajax({
			url: gzseo_tg_ajax.ajax_url,
			type: 'POST',
			data: {
				action: 'gzseo_update_table',
				nonce: gzseo_tg_ajax.nonce,
				object_id: termId,
				object_type: 'term',
				table_id: tableId,
				table_html: tableHtml,
				position: position
			},
			success: function(response) {
				if (response.success) {
					alert(response.data.message);
					$item.find('.gzseo-table-content').slideUp();
				} else {
					alert(response.data.message);
				}
			},
			complete: function() {
				$btn.prop('disabled', false);
			}
		});
	});

	// Cancel Taxonomy Table Edit
	jQuery(document).on('click', '.gzseo-cancel-taxonomy-table-edit', function() {
		jQuery(this).closest('.gzseo-table-content').slideUp();
	});
    
})(jQuery);