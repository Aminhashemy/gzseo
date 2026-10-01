// GZ SEO Admin JavaScript
(function($) {
    'use strict';

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }
    
    function initSchemaPageSelect() {
        var $select = $('#gzseo-schema-pages-select');
        
        if (!$select.length) {
            return;
        }
        
        // Destroy اگر وجود داشت
        if ($select.data('select2')) {
            $select.select2('destroy');
        }
        
        // تنظیمات Select2
        $select.select2({
            ajax: {
                url: gzseo_ajax.ajax_url,
                type: 'POST',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: 'gzseo_search_pages',
                        nonce: gzseo_ajax.nonce,
                        search: params.term || '',
                        page: params.page || 1
                    };
                },
                processResults: function(data, params) {
                    params.page = params.page || 1;
                    
                    if (data.success && data.data && data.data.results) {
                        return {
                            results: data.data.results,
                            pagination: {
                                more: data.data.pagination ? data.data.pagination.more : false
                            }
                        };
                    }
                    
                    return { results: [] };
                },
                cache: false
            },
            placeholder: 'جستجو کنید یا انتخاب کنید...',
            allowClear: false,
            minimumInputLength: 0,
            dir: 'rtl',
            width: '100%',
            closeOnSelect: false,
            dropdownParent: $('#config-schema'),
            language: {
                searching: function() { return 'در حال جستجو...'; },
                noResults: function() { return 'نتیجه‌ای یافت نشد'; },
                loadingMore: function() { return 'بارگذاری موارد بیشتر...'; }
            }
        });
        
        // ذخیره موقعیت اسکرول
        var savedScrollPosition = 0;
        var isSelecting = false;
        
        // ذخیره موقعیت قبل از select
        $select.on('select2:selecting', function(e) {
            isSelecting = true;
            var $resultsContainer = $('.select2-results__options');
            if ($resultsContainer.length) {
                savedScrollPosition = $resultsContainer.scrollTop();
            }
        });
        
        // هندلر برای select
        $select.on('select2:select', function(e) {
            var selectedId = e.params.data.id;
            var selectedText = e.params.data.text;
                        
            if (selectedId === 'all') {
                // پاک کردن همه انتخاب‌ها
                $select.find('option').not('[value="all"]').remove();
                
                // اضافه کردن option "all" اگر وجود ندارد
                if ($select.find('option[value="all"]').length === 0) {
                    var option = new Option(selectedText, 'all', true, true);
                    $select.append(option);
                }
                
                $select.val(['all']);
            } else {
                // حذف "all" اگر وجود داشت
                var values = $select.val() || [];
                if (values.includes('all')) {
                    $select.find('option[value="all"]').remove();
                    values = values.filter(v => v !== 'all');
                }
                
                // اضافه کردن آیتم جدید اگر وجود ندارد
                if ($select.find('option[value="' + selectedId + '"]').length === 0) {
                    var option = new Option(selectedText, selectedId, true, true);
                    $select.append(option);
                }
                
                if (!values.includes(selectedId)) {
                    values.push(selectedId);
                }
                $select.val(values);
            }
            
            // بازگرداندن موقعیت اسکرول فوری
            var $resultsContainer = $('.select2-results__options');
            if ($resultsContainer.length && savedScrollPosition > 0) {
                $resultsContainer.scrollTop(savedScrollPosition);
            }
            
            isSelecting = false;
        });
        
        // جلوگیری از تغییر خودکار اسکرول توسط Select2
        $select.on('select2:open', function() {
            // اگر در حال selecting هستیم، اسکرول را بازگردان
            if (isSelecting) {
                setTimeout(function() {
                    var $resultsContainer = $('.select2-results__options');
                    if ($resultsContainer.length && savedScrollPosition > 0) {
                        $resultsContainer.scrollTop(savedScrollPosition);
                    }
                }, 0);
            }
        });
        
        // Override کردن رفتار پیش‌فرض Select2 برای ensureHighlightVisible
        $select.on('select2:open', function() {
            // Override تابع ensureHighlightVisible
            var select2Instance = $select.data('select2');
            if (select2Instance && select2Instance.results) {
                var originalEnsureHighlightVisible = select2Instance.results.ensureHighlightVisible;
                
                select2Instance.results.ensureHighlightVisible = function() {
                    // اگر در حال selecting هستیم، اسکرول نکن
                    if (!isSelecting) {
                        originalEnsureHighlightVisible.call(this);
                    } else {
                        // فقط اسکرول ذخیره شده را بازگردان
                        var $resultsContainer = $('.select2-results__options');
                        if ($resultsContainer.length && savedScrollPosition > 0) {
                            $resultsContainer.scrollTop(savedScrollPosition);
                        }
                    }
                };
            }
        });
        
        // ذخیره مداوم موقعیت اسکرول
        $(document).on('scroll', '.select2-results__options', function() {
            if (!isSelecting) {
                savedScrollPosition = $(this).scrollTop();
            }
        });
        
        $select.on('select2:unselect', function(e) {
            var unselectedId = e.params.data.id;
            
            // ذخیره موقعیت اسکرول
            var $resultsContainer = $('.select2-results__options');
            if ($resultsContainer.length) {
                savedScrollPosition = $resultsContainer.scrollTop();
            }
            
            $select.find('option[value="' + unselectedId + '"]').remove();
            
            // بازگرداندن اسکرول
            setTimeout(function() {
                if ($resultsContainer.length && savedScrollPosition > 0) {
                    $resultsContainer.scrollTop(savedScrollPosition);
                }
            }, 0);
        });
        
    }
    
    // Main initialization
    $(document).ready(function() {
        
        initSidebarMenu();
        initDashboard();
        initSearchConsole();
        initAI();
        initBulkUpdate();
        initConfig();
        initHelpGuides();
        
        // Initialize Select2 فقط یک بار و با تاخیر
        var schemaSelectInitialized = false;
        
        function tryInitSchemaSelect() {
            if (!schemaSelectInitialized && $('#gzseo-schema-pages-select').length) {
                initSchemaPageSelect();
                schemaSelectInitialized = true;
            }
        }
        
        // تلاش اولیه
        setTimeout(tryInitSchemaSelect, 500);
        
        // زمانی که تب Schema باز می‌شود
        $(document).on('click', '.gzseo-config-tab[data-config-tab="schema"]', function() {
            setTimeout(tryInitSchemaSelect, 300);
        });
    });

    function checkAndLoadAI() {
    	// بررسی اینکه آیا در صفحه مورد نظر هستیم
		if (window.location.href.includes('page=gzseo-dashboard')) {
			// بررسی hash برای تب AI
			if (window.location.hash === '#ai' || $('.gzseo-menu-item[data-tab="ai"]').hasClass('active')) {
				if ($('.gzseo-ai-status').length > 0) {
					loadAIPackages();
				}
			}
		}
	}
	
	$(document).ready(function() {
		checkAndLoadAI();
	});

	$('#gzseo-save-product-list-schema').on('click', function() {
        var $button = $(this);
        var enabled = $('#gzseo-product-list-schema-toggle').is(':checked');
        
        $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> در حال ذخیره...');
        
        $.ajax({
            url: gzseo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'gzseo_save_product_list_schema_settings',
                nonce: gzseo_ajax.nonce,
                enabled: enabled
            },
            success: function(response) {
                if (response.success) {
                    // نمایش پیام موفقیت
                    var message = $('<div class="gzseo-alert gzseo-alert-success" style="margin-top: 10px;">' +
                        '<i class="fas fa-check"></i> ' + escapeHtml(response.data.message) + '</div>');
                    
                    $button.after(message);
                    
                    setTimeout(function() {
                        message.fadeOut(function() {
                            $(this).remove();
                        });
                    }, 3000);
                } else {
                    alert('خطا: ' + response.data.message);
                }
            },
            error: function() {
                alert('خطا در ارتباط با سرور');
            },
            complete: function() {
                $button.prop('disabled', false).html('<i class="fas fa-save"></i> ذخیره تنظیمات');
            }
        });
    });

	// Metabox functionality
	function initMetabox() {
		// Tab switching
		$('.gzseo-metabox-tab').on('click', function() {
			const tab = $(this).data('tab');
			
			$('.gzseo-metabox-tab').removeClass('active');
			$(this).addClass('active');
			
			$('.gzseo-metabox-panel').removeClass('active');
			$('#gzseo-' + tab).addClass('active');
		});
		
		// Generate preview
		$('.gzseo-generate-preview').on('click', function() {
			const $btn = $(this);
			const type = $btn.data('type');
			const $panel = $btn.closest('.gzseo-metabox-panel');
			
			if (type === 'content') {
				const minWords = $('#gzseo-min-words').val();
				const maxWords = $('#gzseo-max-words').val();
				const paragraphs = $('#gzseo-paragraphs').val();
				
				// Show loading
				$btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> در حال تولید...');
				
				// AJAX call
				$.ajax({
					url: gzseo_ajax.ajax_url,
					type: 'POST',
					data: {
						action: 'gzseo_generate_content_preview',
						nonce: gzseo_ajax.nonce,
						min_words: minWords,
						max_words: maxWords,
						paragraphs: paragraphs,
						post_id: $('#post_ID').val() || 0
					},
					success: function(response) {
						if (response.success) {
							$('.gzseo-preview-content').html(response.data.content);
							$('.gzseo-preview-box').slideDown(300);
						} else {
							alert(response.data.message || 'خطا در تولید محتوا');
						}
					},
					complete: function() {
						$btn.prop('disabled', false).html('<i class="fas fa-eye"></i> تولید پیش نمایش');
					}
				});
			}
		});
		
		// Close preview
		$('.gzseo-preview-close').on('click', function() {
			$('.gzseo-preview-box').slideUp(300);
		});
		
		// Save content
		$('.gzseo-save-content').on('click', function() {
			const $btn = $(this);
			const content = $('.gzseo-preview-content').html();
			
			if (!content) {
				alert('ابتدا پیش نمایش را تولید کنید');
				return;
			}
			
			// Save logic here
			alert('محتوا ذخیره شد');
		});
	}

	// Initialize on document ready
	$(document).ready(function() {
		if ($('.gzseo-metabox-wrapper').length) {
			initMetabox();
		}
	});

    // Sidebar menu functionality
    function initSidebarMenu() {
        $('.gzseo-menu-item').on('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all items
            $('.gzseo-menu-item').removeClass('active');
            $(this).addClass('active');
            
            // Hide all tab contents
            $('.gzseo-tab-content').removeClass('active');
            
            // Show selected tab content
            const tabId = $(this).data('tab');
            $('#gzseo-' + tabId).addClass('active gzseo-fade-in');
            
            // Update URL hash
            window.location.hash = tabId;
        });

        // Load tab from URL hash
        if (window.location.hash) {
            const hash = window.location.hash.substring(1);
            $('.gzseo-menu-item[data-tab="' + hash + '"]').click();
        }
    }

    // Dashboard functionality
    function initDashboard() {
        $('.gzseo-refresh-dashboard').on('click', function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.html();
            
            // Show loading
            $btn.html('<span class="gzseo-loading"></span> در حال بروزرسانی...');
            $btn.prop('disabled', true);
            
            $.ajax({
                url: gzseo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gzseo_refresh_dashboard',
                    nonce: gzseo_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showMessage('success', response.data.message);
                        // Reload dashboard content if needed
                        location.reload();
                    }
                },
                error: function() {
                    showMessage('error', 'خطا در بروزرسانی داشبورد');
                },
                complete: function() {
                    $btn.html(originalText);
                    $btn.prop('disabled', false);
                }
            });
        });
    }

    // Search Console functionality
    function initSearchConsole() {
        // Search Console functionality
        $('#gzseo-advanced-settings').on('change', function() {
            if ($(this).is(':checked')) {
                $('#gzseo-advanced-panel').slideDown();
            } else {
                $('#gzseo-advanced-panel').slideUp();
            }
        });

        $('#gzseo-auth-google').on('click', function() {
            var $btn = $(this);
            var useAdvanced = $('#gzseo-advanced-settings').is(':checked');
            
            var data = {
                action: 'gzseo_search_console_auth',
                nonce: gzseo_ajax.nonce,
                use_advanced: useAdvanced
            };
            
            if (useAdvanced) {
                data.site_url = $('#gzseo-site-url').val();
                data.domain_type = $('#gzseo-domain-type').val();
                
                if (!data.site_url) {
                    alert('لطفا آدرس سایت را وارد کنید');
                    return;
                }
            }
            
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> در حال اتصال...');
            
            $.post(gzseo_ajax.ajax_url, data, function(response) {
                if (response.success) {
                    // Open auth window
                    var authWindow = window.open(
                        response.data.auth_url,
                        'GoogleAuth',
                        'width=600,height=700,scrollbars=yes,resizable=yes'
                    );
                    
                    // Check for completion
                    var checkInterval = setInterval(function() {
                        if (authWindow && authWindow.closed) {
                            clearInterval(checkInterval);
                            window.location.reload();
                        }
                    }, 1000);
                } else {
                    alert(response.data.message);
                }
            }).always(function() {
                $btn.prop('disabled', false).html('<i class="fas fa-plug"></i> احراز هویت با گوگل');
            });
        });

        $('#gzseo-disconnect-google').on('click', function() {
            if (!confirm('آیا از قطع اتصال با سرچ کنسول گوگل مطمئن هستید؟')) {
                return;
            }
            
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> در حال قطع اتصال...');
            
            $.post(gzseo_ajax.ajax_url, {
                action: 'gzseo_search_console_disconnect',
                nonce: gzseo_ajax.nonce
            }, function(response) {
                if (response.success) {
                    alert(response.data.message);
                    window.location.reload();
                } else {
                    alert(response.data.message);
                }
            }).always(function() {
                $btn.prop('disabled', false).html('<i class="fas fa-unlink"></i> قطع اتصال');
            });
        });

        $('#gzseo-save-sc-settings').on('click', function() {
            var $btn = $(this);
            var useAdvanced = $('#gzseo-advanced-settings').is(':checked');
            
            var data = {
                action: 'gzseo_search_console_save_settings',
                nonce: gzseo_ajax.nonce,
                use_advanced: useAdvanced
            };
            
            if (useAdvanced) {
                data.site_url = $('#gzseo-site-url').val();
                data.domain_type = $('#gzseo-domain-type').val();
            }
            
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> در حال ذخیره...');
            
            $.post(gzseo_ajax.ajax_url, data, function(response) {
                if (response.success) {
                    showMessage('success', response.data.message);
                } else {
                    showMessage('error', response.data.message);
                }
            }).always(function() {
                $btn.prop('disabled', false).html('<i class="fas fa-save"></i> ذخیره تنظیمات');
            });
        });

        // Helper function to show messages
        function showMessage(type, message) {
            var $container = $('#gzseo-sc-messages');
            var alertClass = type === 'success' ? 'gzseo-alert-success' : 'gzseo-alert-error';
            var icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
            
            var $message = $('<div class="gzseo-alert ' + alertClass + '">' +
                '<i class="fas fa-' + icon + '"></i> ' + message +
                '</div>');
            
            $container.html($message);
            
            setTimeout(function() {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    }

    // AI functionality
    function initAI() {
        // Tab switching
        $('.gzseo-tab-btn').on('click', function() {
            const tab = $(this).data('tab');
            
            // Update active tab
            $('.gzseo-tab-btn').removeClass('active');
            $(this).addClass('active');
            
            // Show corresponding panel
            $('.gzseo-tab-panel').removeClass('active');
            $('#gzseo-' + tab + '-panel').addClass('active');
        });

        // AI Login
        $('#gzseo-ai-login-form').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $btn = $form.find('button[type="submit"]');
            const originalText = $btn.text();
            
            // Get form data
            const formData = {
                action: 'gzseo_ai_login',
                nonce: gzseo_ajax.nonce,
                email: $form.find('input[name="email"]').val(),
                password: $form.find('input[name="password"]').val()
            };
            
            // Show loading
            $btn.html('<span class="gzseo-loading"></span> در حال ورود...');
            $btn.prop('disabled', true);
            
            $.ajax({
                url: gzseo_ajax.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        showMessage('success', response.data.message);
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showMessage('error', response.data.message || 'خطا در ورود');
                    }
                },
                error: function() {
                    showMessage('error', 'خطا در برقراری ارتباط');
                },
                complete: function() {
                    $btn.text(originalText);
                    $btn.prop('disabled', false);
                }
            });
        });

        // AI Registration Form
        $('#gzseo-ai-register-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"]');
            var originalBtnText = $submitBtn.html();
            
            // اعتبارسنجی رمز عبور
            var password = $form.find('input[name="password"]').val();
            var confirmPassword = $form.find('input[name="confirm_password"]').val();
            
            if (password !== confirmPassword) {
                alert('رمز عبور و تکرار آن یکسان نیستند');
                return;
            }
            
            // غیرفعال کردن دکمه
            $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> در حال ثبت نام...');
            
            // ارسال درخواست
            $.post(gzseo_ajax.ajax_url, {
                action: 'gzseo_ai_register',
                nonce: gzseo_ajax.nonce,
                name: $form.find('input[name="name"]').val(),
                email: $form.find('input[name="email"]').val(),
                password: password
            }, function(response) {
                if (response.success) {
                    alert(response.data.message);
                    // بازگردانی به تب ورود
                    $('.gzseo-tab-btn[data-tab="login"]').click();
                    $form[0].reset();
                } else {
                    alert(response.data.message);
                }
            }).always(function() {
                $submitBtn.prop('disabled', false).html(originalBtnText);
            });
        });

        // Load packages when AI tab is opened
        $(document).on('click', '.gzseo-menu-item[data-tab="ai"]', function() {
            if ($('.gzseo-ai-status').length > 0) {
                loadAIPackages();
            }
        });

        // Handle package purchase
        $(document).on('click', '.gzseo-buy-package', function() {
            var $btn = $(this);
            var packageId = $btn.data('package-id');
            var packageType = $btn.data('package-type');
            var userEmail = $('.gzseo-ai-status-right span').text(); // دریافت ایمیل کاربر
            
            if (!userEmail) {
                alert('خطا در دریافت اطلاعات کاربر');
                return;
            }
            
            var originalBtnText = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> در حال ایجاد لینک پرداخت...');
            
            $.post(gzseo_ajax.ajax_url, {
                action: 'gzseo_ai_get_payment_link',
                nonce: gzseo_ajax.nonce,
                email: userEmail,
                packageid: packageId,
                package_type: packageType
            }, function(response) {
                if (response.success) {
                    // ریدایرکت به لینک پرداخت
                    window.location.href = response.data.payment_link;
                } else {
                    alert(response.data.message);
                }
            }).always(function() {
                $btn.prop('disabled', false).html(originalBtnText);
            });
        });

        // AI Disconnect
        $('#gzseo-ai-disconnect').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('آیا از قطع اتصال اطمینان دارید؟')) {
                return;
            }
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            // Show loading
            $btn.html('<span class="gzseo-loading"></span>');
            $btn.prop('disabled', true);
            
            $.ajax({
                url: gzseo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gzseo_ai_logout',
                    nonce: gzseo_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showMessage('success', response.data.message);
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showMessage('error', response.data.message || 'خطا در قطع اتصال');
                    }
                },
                error: function() {
                    showMessage('error', 'خطا در برقراری ارتباط');
                },
                complete: function() {
                    $btn.text(originalText);
                    $btn.prop('disabled', false);
                }
            });
        });
    }

    // Function to load AI packages
    function loadAIPackages() {
        var $container = $('.gzseo-ai-packages');
        
        // نمایش loading
        $container.html('<div class="gzseo-loading"><i class="fas fa-spinner fa-spin"></i></div>');
        
        $.post(gzseo_ajax.ajax_url, {
            action: 'gzseo_ai_get_packages',
            nonce: gzseo_ajax.nonce
        }, function(response) {
            if (response.success) {
                var packages = response.data;
                var html = '';
                
                packages.forEach(function(packageData) {
                    html += '<div class="gzseo-package" data-package-id="' + escapeHtml(packageData.id) + '" data-package-type="' + escapeHtml(packageData.package_type) + '">' +
                        '<div class="gzseo-package-title">' + escapeHtml(packageData.name) + '</div>' +
                        '<div class="gzseo-package-words">جمع کلمات : ' + packageData.tokens.toLocaleString('fa-IR') + '</div>' +
                        '<div class="gzseo-package-price">مبلغ : ' + escapeHtml(packageData.price) + '</div>' +
                        '<button class="gzseo-btn gzseo-btn-primary gzseo-buy-package" data-package-id="' + escapeHtml(packageData.id) + '" data-package-type="' + escapeHtml(packageData.package_type) + '">افزایش اعتبار</button>' +
                        '</div>';
                });
                
                $container.html(html);
            } else {
                $container.html('<div class="gzseo-alert gzseo-alert-error">خطا در بارگذاری پکیج‌ها: ' + escapeHtml(response.data.message) + '</div>');
            }
        }).fail(function() {
            $container.html('<div class="gzseo-alert gzseo-alert-error">خطا در ارتباط با سرور</div>');
        });
    }

    // Bulk Update functionality
    function initBulkUpdate() {
        // Load taxonomy terms when type changes
        $(document).on('change', '.gzseo-taxonomy-type', function() {
            const $container = $(this).closest('.gzseo-bulk-update-item').find('.gzseo-bulk-update-taxonomies');
            const taxonomyType = $(this).val();
            const postType = $(this).closest('.gzseo-bulk-update-item').find('select[name="post_type"]').val();
            
            if (!$(this).prop('disabled')) {
                loadTaxonomyTerms($container, taxonomyType, postType);
            }
        });
        
        // Load terms when post type changes
        $(document).on('change', 'select[name="post_type"]', function() {
            const $item = $(this).closest('.gzseo-bulk-update-item');
            const $taxonomySelect = $item.find('.gzseo-taxonomy-type');
            const taxonomyType = $taxonomySelect.val();
            const postType = $(this).val();
            const $container = $item.find('.gzseo-bulk-update-taxonomies');
            
            if (!$(this).prop('disabled') && taxonomyType) {
                loadTaxonomyTerms($container, taxonomyType, postType);
            }
        });
        
        // Function to load taxonomy terms
        function loadTaxonomyTerms($container, taxonomyType, postType) {
            $container.html('<div class="gzseo-loading"><i class="fas fa-spinner fa-spin"></i> در حال بارگذاری...</div>');
            
            $.ajax({
                url: gzseo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gzseo_get_taxonomy_terms',
                    nonce: gzseo_ajax.nonce,
                    taxonomy_type: taxonomyType,
                    post_type: postType
                },
                success: function(response) {
                    if (response.success) {
                        $container.html(response.data.html);
                    } else {
                        $container.html('<p class="error">خطا در بارگذاری</p>');
                    }
                },
                error: function() {
                    $container.html('<p class="error">خطا در ارتباط با سرور</p>');
                }
            });
        }
        
        // Save new bulk update item
        $('#gzseo-save-bulk-update').on('click', function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.text();
            const $newItem = $('.gzseo-new-item');
            
            // Collect form data
            const formData = {
                action: 'gzseo_save_bulk_update',
                nonce: gzseo_ajax.nonce,
                bulk_action: 'add',
                post_type: $newItem.find('select[name="post_type"]').val(),
                last_update: $newItem.find('input[name="last_update"]').val(),
                update_type: $newItem.find('select[name="update_type"]').val(),
                taxonomy_type: $newItem.find('select[name="taxonomy_type"]').val(),
                selected_terms: []
            };
            
            // Collect selected terms
            $newItem.find('input[name="selected_terms[]"]:checked').each(function() {
                formData.selected_terms.push($(this).val());
            });
            
            // Validation
            if (!formData.last_update) {
                showMessage('error', 'لطفا تعداد روز را وارد کنید');
                return;
            }
            
            // Show loading
            $btn.html('<span class="gzseo-loading"></span> در حال ذخیره...');
            $btn.prop('disabled', true);
            
            $.ajax({
                url: gzseo_ajax.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        showMessage('success', response.data.message);
                        // Reload page to show saved item
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showMessage('error', response.data.message || 'خطا در ذخیره');
                    }
                },
                error: function() {
                    showMessage('error', 'خطا در برقراری ارتباط');
                },
                complete: function() {
                    $btn.text(originalText);
                    $btn.prop('disabled', false);
                }
            });
        });
        
        // Delete saved item
        $(document).on('click', '.gzseo-remove-saved-item', function(e) {
            e.preventDefault();
            
            if (!confirm('آیا از حذف این آیتم مطمئن هستید؟')) {
                return;
            }
            
            const $btn = $(this);
            const index = $btn.data('index');
            
            $.ajax({
                url: gzseo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gzseo_save_bulk_update',
                    nonce: gzseo_ajax.nonce,
                    bulk_action: 'delete',
                    index: index
                },
                success: function(response) {
                    if (response.success) {
                        $btn.closest('.gzseo-saved-item').slideUp(300, function() {
                            $(this).remove();
                        });
                        showMessage('success', response.data.message);
                    }
                }
            });
        });
        
        // Edit saved item
        $(document).on('click', '.gzseo-edit-saved-item', function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const $item = $btn.closest('.gzseo-saved-item');
            const isEditing = $btn.hasClass('editing');
            
            if (isEditing) {
                // Save changes
                const index = $item.data('index');
                const formData = {
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
                
                $item.find('input[name="selected_terms[]"]:checked').each(function() {
                    formData.selected_terms.push($(this).val());
                });
                
                $.ajax({
                    url: gzseo_ajax.ajax_url,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            showMessage('success', response.data.message);
                            $item.find('input, select').prop('disabled', true);
                            $btn.removeClass('editing').text('ویرایش');
                        }
                    }
                });
            } else {
                // Enable editing
                $item.find('input, select').prop('disabled', false);
                $btn.addClass('editing').text('ذخیره تغییرات');
                
                // Load terms if needed
                const taxonomyType = $item.find('.gzseo-taxonomy-type').val();
                const postType = $item.find('select[name="post_type"]').val();
                const $container = $item.find('.gzseo-bulk-update-taxonomies');
                
                if (taxonomyType && $container.find('input[type="checkbox"]').length === 0) {
                    loadTaxonomyTerms($container, taxonomyType, postType);
                }
            }
        });
        
        // Initialize terms on page load for new item
        const $newItem = $('.gzseo-new-item');
        if ($newItem.length) {
            const taxonomyType = $newItem.find('.gzseo-taxonomy-type').val();
            const postType = $newItem.find('select[name="post_type"]').val();
            
            if (taxonomyType) {
                loadTaxonomyTerms($newItem.find('.gzseo-bulk-update-taxonomies'), taxonomyType, postType);
            }
        }
    }

    // Config functionality
    function initConfig() {
        // Config tab switching
        $('.gzseo-config-tab').on('click', function() {
            const tab = $(this).data('config-tab');
            
            // Update active tab
            $('.gzseo-config-tab').removeClass('active');
            $(this).addClass('active');
            
            // Show corresponding panel
            $('.gzseo-config-panel').removeClass('active');
            $('#config-' + tab).addClass('active');
        });
    
        // Table style selection
        $('.gzseo-table-style').on('click', function() {
            $('.gzseo-table-style').removeClass('active');
            $(this).addClass('active');
            
            // Update preview based on selected style
            const style = $(this).data('style');
            updateTablePreview(style);
        });
    
        // Color picker changes
        $('.gzseo-color-settings input[type="color"]').on('change', function() {
            updateTablePreview('custom');
            $('.gzseo-custom-style').click();
        });
    
        // Save table styles button - specific handler
        $(document).on('click', '#gzseo-save-table-styles', function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.html();
            const $panel = $('#config-table-style');
            
            // Collect table style data
            const tableStyleData = {
                use_predefined_styles: $panel.find('input[type="checkbox"]').is(':checked') ? 'true' : 'false',
                table_position: $panel.find('select').val(),
                selected_style: $('.gzseo-table-style.active').data('style') || '1',
                header_bg: $panel.find('.gzseo-color-group').eq(0).find('input[type="color"]').eq(0).val(),
                header_text: $panel.find('.gzseo-color-group').eq(0).find('input[type="color"]').eq(1).val(),
                odd_bg: $panel.find('.gzseo-color-group').eq(1).find('input[type="color"]').eq(0).val(),
                odd_text: $panel.find('.gzseo-color-group').eq(1).find('input[type="color"]').eq(1).val(),
                even_bg: $panel.find('.gzseo-color-group').eq(2).find('input[type="color"]').eq(0).val(),
                even_text: $panel.find('.gzseo-color-group').eq(2).find('input[type="color"]').eq(1).val()
            };
            
            // Show loading
            $btn.html('<i class="fas fa-spinner fa-spin"></i> در حال ذخیره...');
            $btn.prop('disabled', true);
            
            // Send AJAX request
            $.ajax({
                url: gzseo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gzseo_save_table_styles',
                    nonce: gzseo_ajax.nonce,
                    ...tableStyleData
                },
                success: function(response) {
                    if (response.success) {
                        showMessage('success', response.data.message);
                        
                        // اضافه کردن انیمیشن موفقیت به دکمه
                        $btn.addClass('gzseo-success-animation');
                        setTimeout(function() {
                            $btn.removeClass('gzseo-success-animation');
                        }, 1000);
                        
                        // نمایش پیشنهاد بازخوانی صفحه
                        setTimeout(function() {
                            //if (confirm('برای مشاهده تغییرات در صفحات سایت، آیا مایل به بازخوانی صفحه هستید؟')) {
                                location.reload();
                            //}
                        }, 1500);
                    } else {
                        showMessage('error', response.data.message || 'خطا در ذخیره تنظیمات');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    showMessage('error', 'خطا در برقراری ارتباط با سرور');
                },
                complete: function() {
                    $btn.html(originalText);
                    $btn.prop('disabled', false);
                }
            });
        });

        // Save config buttons - for other panels
        $('.gzseo-config-panel .gzseo-btn-primary').not('#gzseo-save-table-styles').not('#gzseo-person-schema-form button[type="submit"]').not('#gzseo-save-featured-image-settings').on('click', function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.text();
            const $panel = $btn.closest('.gzseo-config-panel');
            const panelId = $panel.attr('id');
            
            // Collect config data based on panel
            let configData = {};
            
            switch(panelId) {
                case 'config-comment-management':
                    configData = {
                        tone: $panel.find('select').val(),
                        word_count: $panel.find('input[type="number"]').val()
                    };
                    break;
                    
                case 'config-content-update':
                    configData = {
                        // تنظیمات آپدیت محتوای متنی
                        accordion_display: $panel.find('input[type="checkbox"]').is(':checked'),
                        accordion_title: $panel.find('input[type="text"]').val(),
                        text_position: $panel.find('select').eq(0).val(),
                        // تنظیمات آپدیت محتوای ویدیویی
                        video_position: $panel.find('select').eq(1).val()
                    };
                    break;
            }
            
            // Show loading
            $btn.html('<span class="gzseo-loading"></span> در حال ذخیره...');
            $btn.prop('disabled', true);
            
            $.ajax({
                url: gzseo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gzseo_save_config',
                    nonce: gzseo_ajax.nonce,
                    config_type: panelId.replace('config-', ''),
                    config: configData
                },
                success: function(response) {
                    if (response.success) {
                        showMessage('success', response.data.message);
                    } else {
                        showMessage('error', response.data.message || 'خطا در ذخیره');
                    }
                },
                error: function() {
                    showMessage('error', 'خطا در برقراری ارتباط');
                },
                complete: function() {
                    $btn.text(originalText);
                    $btn.prop('disabled', false);
                }
            });
        });
		
		// بارگذاری تنظیمات ذخیره شده هنگام لود صفحه
		$(document).ready(function() {
			// اگر در تب پیکربندی هستیم
			if ($('#gzseo-config').length && $('#config-table-style').length) {
				// بررسی استایل فعال
				const activeStyle = $('.gzseo-table-style.active').data('style');
				if (activeStyle) {
					// به‌روزرسانی پیش‌نمایش با استایل فعال
					updateTablePreview(activeStyle);
				}

				// اطمینان از اینکه رنگ‌های color picker درست نمایش داده می‌شوند
				setTimeout(function() {
					// Trigger change event to update preview if custom style is selected
					if (activeStyle === 'custom') {
						$('.gzseo-color-settings input[type="color"]').first().trigger('change');
					}
				}, 100);
			}
		});
		
		$(document).on('click', '.gzseo-menu-item[data-tab="config"]', function() {
			setTimeout(function() {
				const activeStyle = $('.gzseo-table-style.active').data('style');
				if (activeStyle) {
					updateTablePreview(activeStyle);
				}
			}, 100);
		});
    
        // Initialize table style on load
        initializeTableStyles();
    }
    
    // تابع برای مقداردهی اولیه استایل‌های جدول
    function initializeTableStyles() {
        // اگر استایل انتخاب شده وجود نداشت، اولین استایل را انتخاب کن
        if ($('.gzseo-table-style.active').length === 0) {
            $('.gzseo-table-style[data-style="1"]').addClass('active');
        }
        
        // به‌روزرسانی پیش‌نمایش با استایل فعلی
        const currentStyle = $('.gzseo-table-style.active').data('style');
        if (currentStyle) {
            updateTablePreview(currentStyle);
        }
    }
    
    // به‌روزرسانی تابع updateTablePreview (اگر قبلاً وجود ندارد)
    function updateTablePreview(style) {
        const $preview = $('.gzseo-table-preview table');
        
        // تعریف رنگ‌های استایل‌های از پیش تعریف شده
        const predefinedStyles = {
            '1': {
                headerBg: '#4e4b83',
                headerText: '#FFFFFF',
                oddBg: '#f4f4ff',
                oddText: '#333333',
                evenBg: '#FFFFFF',
                evenText: '#333333'
            },
            '2': {
                headerBg: '#42a5f5',
                headerText: '#000000',
                oddBg: '#dbefff',
                oddText: '#333333',
                evenBg: '#bbdefb',
                evenText: '#333333'
            },
            '3': {
                headerBg: '#343a40',
                headerText: '#FFFFFF',
                oddBg: '#dee2e6',
                oddText: '#333333',
                evenBg: '#adb5bd',
                evenText: '#333333'
            },
            '4': {
                headerBg: '#204c01',
                headerText: '#FFFFFF',
                oddBg: '#daffb8',
                oddText: '#333333',
                evenBg: '#aad576',
                evenText: '#333333'
            },
            '5': {
                headerBg: '#583101',
                headerText: '#FFFFFF',
                oddBg: '#f3d5b5',
                oddText: '#792924',
                evenBg: '#ffedd8',
                evenText: '#333333'
            }
        };
        
        let colors;
        
        if (style === 'custom') {
            // استفاده از رنگ‌های انتخابی کاربر
            colors = {
                headerBg: $('.gzseo-color-group').eq(0).find('input[type="color"]').eq(0).val(),
                headerText: $('.gzseo-color-group').eq(0).find('input[type="color"]').eq(1).val(),
                oddBg: $('.gzseo-color-group').eq(1).find('input[type="color"]').eq(0).val(),
                oddText: $('.gzseo-color-group').eq(1).find('input[type="color"]').eq(1).val(),
                evenBg: $('.gzseo-color-group').eq(2).find('input[type="color"]').eq(0).val(),
                evenText: $('.gzseo-color-group').eq(2).find('input[type="color"]').eq(1).val()
            };
        } else if (predefinedStyles[style]) {
            colors = predefinedStyles[style];
            
            // اعمال رنگ‌ها به color picker ها
            $('.gzseo-color-group').eq(0).find('input[type="color"]').eq(0).val(colors.headerBg);
            $('.gzseo-color-group').eq(0).find('input[type="color"]').eq(1).val(colors.headerText);
            $('.gzseo-color-group').eq(1).find('input[type="color"]').eq(0).val(colors.oddBg);
            $('.gzseo-color-group').eq(1).find('input[type="color"]').eq(1).val(colors.oddText);
            $('.gzseo-color-group').eq(2).find('input[type="color"]').eq(0).val(colors.evenBg);
            $('.gzseo-color-group').eq(2).find('input[type="color"]').eq(1).val(colors.evenText);
        }
        
        // اعمال استایل‌ها به جدول نمونه
        if (colors) {
            $preview.find('th').css({
                'background-color': colors.headerBg,
                'color': colors.headerText
            });
            
            $preview.find('tbody tr:nth-child(odd) td').css({
                'background-color': colors.oddBg,
                'color': colors.oddText
            });
            
            $preview.find('tbody tr:nth-child(even) td').css({
                'background-color': colors.evenBg,
                'color': colors.evenText
            });
        }
    }
    
    // تابع نمایش پیام (اگر قبلاً تعریف نشده باشد)
    function showMessage(type, message) {
        // Remove existing messages
        $('.gzseo-message').remove();
        
        // Create message element
        const $message = $(`
            <div class="gzseo-message ${type}">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                ${message}
            </div>
        `);
        
        // Add to active tab content
        const $contentBody = $('.gzseo-tab-content.active .gzseo-content-body');
        if ($contentBody.length) {
            $contentBody.prepend($message);
        } else {
            // اگر content body پیدا نشد، در بالای صفحه نمایش بده
            $('.gzseo-content').prepend($message);
        }
        
        // Show with animation
        $message.hide().slideDown(300);
        
        // Auto hide after 5 seconds
        setTimeout(function() {
            $message.slideUp(300, function() {
                $(this).remove();
            });
        }, 5000);
    }

    // Help guides functionality
    function initHelpGuides() {
        // Create popup structure if not exists
        if (!$('.gzseo-help-popup').length) {
            $('body').append(`
                <div class="gzseo-help-popup-overlay"></div>
                <div class="gzseo-help-popup">
                    <div class="gzseo-help-popup-header">
                        <h3>راهنما</h3>
                        <button class="gzseo-help-popup-close">&times;</button>
                    </div>
                    <div class="gzseo-help-popup-content"></div>
                </div>
            `);
        }

        // Open help guide
        $('.gzseo-help-guide').on('click', function(e) {
            e.preventDefault();
            
            const section = $(this).closest('.gzseo-tab-content').attr('id');
            const helpContent = getHelpContent(section);
            
            $('.gzseo-help-popup-content').html(helpContent);
            $('.gzseo-help-popup, .gzseo-help-popup-overlay').addClass('active');
        });

        // Close help guide
        $('.gzseo-help-popup-close, .gzseo-help-popup-overlay').on('click', function() {
            $('.gzseo-help-popup, .gzseo-help-popup-overlay').removeClass('active');
        });
    }

    // Helper function to initialize remove buttons
    function initRemoveButtons() {
        $('.gzseo-remove-item').off('click').on('click', function(e) {
            e.preventDefault();
            
            const $container = $('#gzseo-bulk-update-container');
            
            // Don't remove if it's the last item
            if ($container.find('.gzseo-bulk-update-item').length <= 1) {
                showMessage('error', 'حداقل یک آیتم باید وجود داشته باشد');
                return;
            }
            
            // Remove with animation
            $(this).closest('.gzseo-bulk-update-item').slideUp(300, function() {
                $(this).remove();
            });
        });
    }

    // Helper function to update table preview
    function updateTablePreview(style) {
		const $preview = $('.gzseo-table-preview table');
		
		// تعریف رنگ‌های استایل‌های از پیش تعریف شده
		const predefinedStyles = {
			'1': {
				headerBg: '#4e4b83',
				headerText: '#FFFFFF',
				oddBg: '#f4f4ff',
				oddText: '#333333',
				evenBg: '#FFFFFF',
				evenText: '#333333'
			},
			'2': {
				headerBg: '#42a5f5',
				headerText: '#000000',
				oddBg: '#dbefff',
				oddText: '#333333',
				evenBg: '#bbdefb',
				evenText: '#333333'
			},
			'3': {
				headerBg: '#343a40',
				headerText: '#FFFFFF',
				oddBg: '#dee2e6',
				oddText: '#333333',
				evenBg: '#adb5bd',
				evenText: '#333333'
			},
			'4': {
				headerBg: '#204c01',
				headerText: '#FFFFFF',
				oddBg: '#daffb8',
				oddText: '#333333',
				evenBg: '#aad576',
				evenText: '#333333'
			},
			'5': {
				headerBg: '#583101',
				headerText: '#FFFFFF',
				oddBg: '#f3d5b5',
				oddText: '#792924',
				evenBg: '#ffedd8',
				evenText: '#333333'
			}
		};
		
		let colors;
		
		if (style === 'custom') {
			colors = {
				headerBg: $('.gzseo-color-group').eq(0).find('input[type="color"]').eq(0).val(),
				headerText: $('.gzseo-color-group').eq(0).find('input[type="color"]').eq(1).val(),
				oddBg: $('.gzseo-color-group').eq(1).find('input[type="color"]').eq(0).val(),
				oddText: $('.gzseo-color-group').eq(1).find('input[type="color"]').eq(1).val(),
				evenBg: $('.gzseo-color-group').eq(2).find('input[type="color"]').eq(0).val(),
				evenText: $('.gzseo-color-group').eq(2).find('input[type="color"]').eq(1).val()
			};
		} else if (predefinedStyles[style]) {
			colors = predefinedStyles[style];
			
			// اعمال رنگ‌ها به color picker ها
			$('.gzseo-color-group').eq(0).find('input[type="color"]').eq(0).val(colors.headerBg);
			$('.gzseo-color-group').eq(0).find('input[type="color"]').eq(1).val(colors.headerText);
			$('.gzseo-color-group').eq(1).find('input[type="color"]').eq(0).val(colors.oddBg);
			$('.gzseo-color-group').eq(1).find('input[type="color"]').eq(1).val(colors.oddText);
			$('.gzseo-color-group').eq(2).find('input[type="color"]').eq(0).val(colors.evenBg);
			$('.gzseo-color-group').eq(2).find('input[type="color"]').eq(1).val(colors.evenText);
		}
		
		// اعمال استایل‌ها به جدول نمونه
		if (colors) {
			$preview.find('th').css({
				'background-color': colors.headerBg,
				'color': colors.headerText
			});
			
			$preview.find('tr:nth-child(odd) td').css({
				'background-color': colors.oddBg,
				'color': colors.oddText
			});
			
			$preview.find('tr:nth-child(even) td').css({
				'background-color': colors.evenBg,
				'color': colors.evenText
			});
		}
	}

    // Helper function to get help content
    function getHelpContent(section) {
        const helpTexts = {
            'gzseo-search-console': `
                <h4>راهنمای اتصال به سرچ کنسول</h4>
                <ol>
                    <li>روی دکمه احراز هویت کلیک کنید</li>
                    <li>با حساب گوگل خود وارد شوید</li>
                    <li>دسترسی‌های مورد نیاز را تایید کنید</li>
                    <li>پس از اتصال موفق، به این صفحه بازگردانده می‌شوید</li>
                </ol>
                <p><strong>نکته:</strong> در صورت استفاده از تنظیمات حرفه‌ای، آدرس سایت و نوع دامنه را به درستی انتخاب کنید.</p>
            `,
            'gzseo-ai': `
                <h4>راهنمای استفاده از هوش مصنوعی</h4>
                <p>با استفاده از قابلیت هوش مصنوعی می‌توانید:</p>
                <ul>
                    <li>محتوای سئو شده تولید کنید</li>
                    <li>متا تگ‌های بهینه بسازید</li>
                    <li>محتوای موجود را بهبود دهید</li>
                </ul>
                <p><strong>نحوه شارژ اعتبار:</strong></p>
                <ol>
                    <li>پکیج مورد نظر را انتخاب کنید</li>
                    <li>روی دکمه افزایش اعتبار کلیک کنید</li>
                    <li>پرداخت را تکمیل کنید</li>
                </ol>
            `,
            'gzseo-bulk-update': `
                <h4>راهنمای آپدیت دسته جمعی</h4>
                <p>برای آپدیت دسته جمعی محتواها:</p>
                <ol>
                    <li>نوع نوشته مورد نظر را انتخاب کنید</li>
                    <li>بازه زمانی آخرین آپدیت را مشخص کنید</li>
                    <li>نوع آپدیت را انتخاب کنید</li>
                    <li>در صورت نیاز، تکسونومی را مشخص کنید</li>
                    <li>دکمه ذخیره را بزنید</li>
                </ol>
                <p><strong>نکته:</strong> می‌توانید چندین قانون مختلف برای آپدیت ایجاد کنید.</p>
            `,
            'gzseo-config': `
                <h4>راهنمای پیکربندی افزونه</h4>
                <p>در این بخش می‌توانید تنظیمات مختلف افزونه را پیکربندی کنید:</p>
                <ul>
                    <li><strong>استایل جدول:</strong> ظاهر جداول محتوا را تنظیم کنید</li>
                    <li><strong>مدیریت کامنت:</strong> تنظیمات مربوط به پاسخ‌های هوش مصنوعی به کامنت‌ها</li>
                    <li><strong>آپدیت محتوا:</strong> نحوه نمایش آپدیت‌های محتوا را مشخص کنید</li>
                    <li><strong>اسکیما:</strong> ساختار داده‌های schema.org را مدیریت کنید</li>
                </ul>
            `
        };
        
        return helpTexts[section] || '<p>راهنمایی برای این بخش در دسترس نیست.</p>';
    }

    // Helper function to show messages
    function showMessage(type, message) {
        // Remove existing messages
        $('.gzseo-message').remove();
        
        // Create message element
        const $message = $(`
            <div class="gzseo-message ${type}">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                ${message}
            </div>
        `);
        
        // Add to active tab content
        $('.gzseo-tab-content.active .gzseo-content-body').prepend($message);
        
        // Show with animation
        $message.hide().slideDown(300);
        
        // Auto hide after 5 seconds
        setTimeout(function() {
            $message.slideUp(300, function() {
                $(this).remove();
            });
        }, 5000);
    }

    // Handle form validation
    function validateForm($form) {
        let isValid = true;
        
        $form.find('[required]').each(function() {
            const $field = $(this);
            const value = $field.val().trim();
            
            if (!value) {
                $field.addClass('error');
                isValid = false;
            } else {
                $field.removeClass('error');
            }
        });
        
        // Email validation
        $form.find('input[type="email"]').each(function() {
            const $field = $(this);
            const email = $field.val().trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                $field.addClass('error');
                isValid = false;
            }
        });
        
        return isValid;
    }

    // Handle keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl/Cmd + S to save
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            $('.gzseo-tab-content.active .gzseo-btn-primary').first().click();
        }
        
        // ESC to close popups
        if (e.key === 'Escape') {
            $('.gzseo-help-popup-close').click();
        }
    });

    // Handle window resize for responsive behavior
    let resizeTimer;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            // Adjust layout if needed
            if ($(window).width() < 768) {
                $('.gzseo-container').addClass('mobile');
            } else {
                $('.gzseo-container').removeClass('mobile');
            }
        }, 250);
    });

    // Initialize tooltips
    function initTooltips() {
        $('[data-tooltip]').each(function() {
            $(this).addClass('gzseo-tooltip');
        });
    }

    // Call tooltip init
    initTooltips();

    // Smooth scroll for anchor links
    /*$('a[href^="#"]').on('click', function(e) {
        const target = $(this.getAttribute('href'));
        if (target.length) {
            e.preventDefault();
            $('html, body').animate({
                scrollTop: target.offset().top - 100
            }, 500);
        }
    });*/

    // Auto-save functionality
    let autoSaveTimer;
    function initAutoSave() {
        $('.gzseo-input, .gzseo-select, input[type="checkbox"]').on('change input', function() {
            clearTimeout(autoSaveTimer);
            
            // Show auto-save indicator
            const $indicator = $('.gzseo-autosave-indicator');
            if (!$indicator.length) {
                $('.gzseo-content-header').append('<span class="gzseo-autosave-indicator">تغییرات ذخیره نشده</span>');
            }
            
            // Auto-save after 2 seconds of inactivity
            autoSaveTimer = setTimeout(function() {
                // You can implement auto-save logic here if needed
                $('.gzseo-autosave-indicator').text('ذخیره خودکار...').fadeOut(2000, function() {
                    $(this).remove();
                });
            }, 2000);
        });
    }

    // Initialize auto-save
    initAutoSave();
	
	// Tab switching for AI section
	$(document).on('click', '.gzseo-tab-btn', function() {
		const tab = $(this).data('tab');

		// Update active tab
		$('.gzseo-tab-btn').removeClass('active');
		$(this).addClass('active');

		// Update panels
		$('.gzseo-tab-panel').removeClass('active');
		$('#gzseo-' + tab + '-panel').addClass('active');
	});

	// AI Login Form
	$(document).on('submit', '#gzseo-ai-login-form', function(e) {
		e.preventDefault();

		const $form = $(this);
		const $btn = $form.find('button[type="submit"]');
		const email = $form.find('input[name="email"]').val();
		const password = $form.find('input[name="password"]').val();

		// Disable button and show loading
		$btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> در حال ورود...');

		$.ajax({
			url: gzseo_ajax.ajax_url,
			type: 'POST',
			data: {
				action: 'gzseo_ai_login',
				nonce: gzseo_ajax.nonce,
				email: email,
				password: password
			},
			success: function(response) {
				if (response.success) {
					// Show success message
					showAINotification('success', response.data.message);
					// Reload page after 1.5 seconds
					setTimeout(() => location.reload(), 1500);
				} else {
					showAINotification('error', response.data.message || 'خطا در ورود');
				}
			},
			error: function() {
				showAINotification('error', 'خطا در اتصال به سرور');
			},
			complete: function() {
				$btn.prop('disabled', false).html('ورود');
			}
		});
	});

	// AI Register Form
	$(document).on('submit', '#gzseo-ai-register-form', function(e) {
		e.preventDefault();

		const $form = $(this);
		const password = $form.find('input[name="password"]').val();
		const confirmPassword = $form.find('input[name="confirm_password"]').val();

		if (password !== confirmPassword) {
			showAINotification('error', 'رمز عبور و تکرار آن یکسان نیستند');
			return;
		}

		// Here you can implement registration logic
		showAINotification('info', 'برای ثبت نام به سایت اصلی هدایت شوید');
	});

	// AI Disconnect
	$(document).on('click', '#gzseo-ai-disconnect', function() {
		if (!confirm('آیا از قطع اتصال اطمینان دارید؟')) {
			return;
		}

		const $btn = $(this);
		$btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> در حال قطع...');

		$.ajax({
			url: gzseo_ajax.ajax_url,
			type: 'POST',
			data: {
				action: 'gzseo_ai_logout',
				nonce: gzseo_ajax.nonce
			},
			success: function(response) {
				if (response.success) {
					showAINotification('success', 'اتصال قطع شد');
					setTimeout(() => location.reload(), 1500);
				}
			},
			complete: function() {
				$btn.prop('disabled', false).html('قطع اتصال');
			}
		});
	});

	// Show notification for AI section
	function showAINotification(type, message) {
		const icons = {
			success: 'check-circle',
			error: 'exclamation-circle',
			info: 'info-circle'
		};

		const colors = {
			success: '#28a745',
			error: '#dc3545',
			info: '#17a2b8'
		};

		const notification = $(`
			<div class="gzseo-ai-notification" style="
				position: fixed;
				top: 20px;
				right: 20px;
				background: white;
				padding: 15px 20px;
				border-radius: 5px;
				box-shadow: 0 2px 10px rgba(0,0,0,0.1);
				display: flex;
				align-items: center;
				gap: 10px;
				z-index: 9999;
				min-width: 250px;
			">
				<i class="fas fa-${icons[type]}" style="color: ${colors[type]}"></i>
				<span>${message}</span>
			</div>
		`);

		$('body').append(notification);

		setTimeout(() => {
			notification.fadeOut(() => notification.remove());
		}, 3000);
	}

	// Package purchase buttons
	$(document).on('click', '.gzseo-package button', function() {
		const $package = $(this).closest('.gzseo-package');
		const packageTitle = $package.find('.gzseo-package-title').text();
		const packageWords = $package.find('.gzseo-package-words').text();
		const packagePrice = $package.find('.gzseo-package-price').text();

		// Here you can implement purchase logic
		alert(`خرید ${packageTitle}\n${packageWords}\n${packagePrice}`);
	});

    // Bulk Update Page functionality
    function initBulkUpdatePage() {
        
        
        // Close preview on click outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.gzseo-bulk-actions').length) {
                $('.gzseo-preview-results').slideUp();
            }
        });
    }

    // Execute bulk update with progress
    function executeBulkUpdate(index, totalCount, processedCount, progressCallback, completeCallback) {
        const batchSize = 5; // Process 5 items at a time
        const currentBatch = Math.min(batchSize, totalCount - processedCount);
        
        if (processedCount >= totalCount) {
            completeCallback(true, 'تمام ' + totalCount + ' مورد با موفقیت آپدیت شدند');
            return;
        }
        
        $.ajax({
            url: gzseo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'gzseo_bulk_execute',
                nonce: gzseo_ajax.nonce,
                index: index,
                offset: processedCount,
                count: currentBatch
            },
            success: function(response) {
                if (response.success) {
                    processedCount += response.data.processed || currentBatch;
                    const progress = Math.round((processedCount / totalCount) * 100);
                    progressCallback(progress);
                    
                    // Continue with next batch
                    setTimeout(function() {
                        executeBulkUpdate(index, totalCount, processedCount, progressCallback, completeCallback);
                    }, 500);
                } else {
                    completeCallback(false, response.data.message || 'خطا در اجرای آپدیت');
                }
            },
            error: function(xhr, status, error) {
                console.error('Bulk update error:', error);
                completeCallback(false, 'خطا در ارتباط با سرور');
            }
        });
    }

    // Helper function to show messages in bulk update page
    function showBulkMessage(type, message) {
        // Remove existing messages
        $('.gzseo-bulk-message').remove();
        
        // Create message element
        const $message = $(`
            <div class="gzseo-bulk-message ${type}">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                ${message}
                <button class="gzseo-message-close">&times;</button>
            </div>
        `);
        
        // Add to page
        $('.gzseo-bulk-update-page').prepend($message);
        
        // Show with animation
        $message.hide().slideDown(300);
        
        // Close button
        $message.find('.gzseo-message-close').on('click', function() {
            $message.slideUp(300, function() {
                $(this).remove();
            });
        });
        
        // Auto hide after 5 seconds
        setTimeout(function() {
            $message.slideUp(300, function() {
                $(this).remove();
            });
        }, 5000);
    }

    // Initialize bulk update page if we're on that page
    $(document).ready(function() {
        if ($('.gzseo-bulk-update-page').length) {
            initBulkUpdatePage();
            
            // Override showMessage for bulk update page
            window.showMessage = showBulkMessage;
        }
    });
	
	// نمایش پیش‌نمایش تصویر در هنگام بارگذاری صفحه
	jQuery(document).ready(function($) {
		// اگر تصویر از قبل انتخاب شده باشد
		var imageUrl = $('#gzseo-person-image-url').val();
		var imageId = $('#gzseo-person-image-id').val();

		if (imageUrl && imageId) {
			// اگر preview وجود ندارد، ایجاد کن
			if ($('.gzseo-schema-image-preview').length === 0) {
				var preview = $('<div class="gzseo-schema-image-preview"><img src="' + imageUrl + '" alt="پیش‌نمایش تصویر"></div>');
				$('.gzseo-schema-image-selector').after(preview);
			}

			// نمایش دکمه حذف
			$('#gzseo-remove-image').show();
		}
	});
	
	jQuery(document).ready(function($) {
        // اضافه کردن لینک فراموشی رمز عبور به فرم لاگین
        if ($('#gzseo-login-panel').length) {
            var forgetLink = '<div class="gzseo-forget-password-wrapper" style="text-align: center; margin-top: 15px;">' +
                            '<a href="#" id="gzseo-forget-password-link" style="color: #4e4b83; text-decoration: none; font-size: 14px;">' +
                            '<i class="fas fa-key"></i> رمز عبور خود را فراموش کرده‌اید؟' +
                            '</a></div>';
            
            $('#gzseo-ai-login-form').after(forgetLink);
        }
        
        // مدیریت کلیک روی لینک فراموشی رمز عبور
        $(document).on('click', '#gzseo-forget-password-link', function(e) {
            e.preventDefault();
            
            // نمایش فرم بازیابی رمز عبور
            var resetForm = '<div id="gzseo-reset-password-wrapper" style="padding:30px;">' +
                           '<h4 style="text-align: center; margin-bottom: 20px;">بازیابی رمز عبور</h4>' +
                           '<form id="gzseo-ai-forget-form">' +
                           '<div class="gzseo-form-group">' +
                           '<input type="email" name="reset_email" placeholder="ایمیل حساب کاربری" class="gzseo-input" required>' +
                           '</div>' +
                           '<button type="submit" class="gzseo-btn gzseo-btn-primary">ارسال کد بازیابی</button>' +
                           '<button type="button" class="gzseo-btn gzseo-btn-secondary" id="gzseo-cancel-reset" style="margin-right: 10px;">انصراف</button>' +
                           '</form>' +
                           '<div id="gzseo-reset-messages"></div>' +
                           '</div>';
            
            // مخفی کردن فرم لاگین و نمایش فرم بازیابی
            $('#gzseo-login-panel').fadeOut(300, function() {
                $(this).after(resetForm);
                $('#gzseo-reset-password-wrapper').fadeIn(300);
            });
            
            // مخفی کردن لینک فراموشی رمز
            $('.gzseo-forget-password-wrapper').hide();
        });
        
        // مدیریت فرم ارسال کد بازیابی
        $(document).on('submit', '#gzseo-ai-forget-form', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var email = $form.find('input[name="reset_email"]').val();
            
            // غیرفعال کردن دکمه
            $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> در حال ارسال...');
            
            // پاک کردن پیام‌های قبلی
            $('#gzseo-reset-messages').empty();
            
            // ارسال درخواست
            $.ajax({
                url: gzseo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gzseo_ai_forget_password',
                    nonce: gzseo_ajax.nonce,
                    email: email
                },
                success: function(response) {
                    if (response.success) {
                        // نمایش پیام موفقیت
                        $('#gzseo-reset-messages').html(
                            '<div class="gzseo-alert gzseo-alert-success" style="margin-top: 10px;">' +
                            '<i class="fas fa-check-circle"></i> ' + escapeHtml(response.data.message) +
                            '</div>'
                        );
                        
                        // نمایش فرم تغییر رمز عبور
                        if (response.data.next_step) {
                            setTimeout(function() {
                                showResetPasswordForm();
                            }, 2000);
                        }
                    } else {
                        // نمایش پیام خطا
                        $('#gzseo-reset-messages').html(
                            '<div class="gzseo-alert gzseo-alert-danger" style="margin-top: 10px;">' +
                            '<i class="fas fa-exclamation-circle"></i> ' + escapeHtml(response.data.message) +
                            '</div>'
                        );
                        
                        // فعال کردن دکمه
                        $button.prop('disabled', false).html('ارسال کد بازیابی');
                    }
                },
                error: function() {
                    $('#gzseo-reset-messages').html(
                        '<div class="gzseo-alert gzseo-alert-danger" style="margin-top: 10px;">' +
                        '<i class="fas fa-exclamation-circle"></i> خطا در ارتباط با سرور' +
                        '</div>'
                    );
                    
                    // فعال کردن دکمه
                    $button.prop('disabled', false).html('ارسال کد بازیابی');
                }
            });
        });
        
        // نمایش فرم تغییر رمز عبور
        function showResetPasswordForm() {
            var resetForm = '<form id="gzseo-ai-reset-form">' +
                           '<div class="gzseo-form-group">' +
                           '<input type="text" name="verify_code" placeholder="کد تایید ارسال شده به ایمیل" class="gzseo-input" required>' +
                           '</div>' +
                           '<div class="gzseo-form-group">' +
                           '<input type="password" name="new_password" placeholder="رمز عبور جدید" class="gzseo-input" required>' +
                           '<small style="display: block; color: #666; margin-top: 5px;">رمز عبور باید حداقل 6 کاراکتر باشد</small>' +
                           '</div>' +
                           '<button type="submit" class="gzseo-btn gzseo-btn-primary">تغییر رمز عبور</button>' +
                           '<button type="button" class="gzseo-btn gzseo-btn-secondary" id="gzseo-cancel-reset" style="margin-right: 10px;">انصراف</button>' +
                           '</form>';
            
            $('#gzseo-ai-forget-form').fadeOut(300, function() {
                $(this).replaceWith(resetForm);
                $('#gzseo-ai-reset-form').fadeIn(300);
            });
        }
        
        // مدیریت فرم تغییر رمز عبور
        $(document).on('submit', '#gzseo-ai-reset-form', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var verify_code = $form.find('input[name="verify_code"]').val();
            var new_password = $form.find('input[name="new_password"]').val();
            
            // بررسی طول رمز عبور
            if (new_password.length < 6) {
                $('#gzseo-reset-messages').html(
                    '<div class="gzseo-alert gzseo-alert-danger" style="margin-top: 10px;">' +
                    '<i class="fas fa-exclamation-circle"></i> رمز عبور باید حداقل 6 کاراکتر باشد' +
                    '</div>'
                );
                return;
            }
            
            // غیرفعال کردن دکمه
            $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> در حال تغییر...');
            
            // پاک کردن پیام‌های قبلی
            $('#gzseo-reset-messages').empty();
            
            // ارسال درخواست
            $.ajax({
                url: gzseo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gzseo_ai_reset_password',
                    nonce: gzseo_ajax.nonce,
                    verify_code: verify_code,
                    new_password: new_password
                },
                success: function(response) {
                    if (response.success) {
                        // نمایش پیام موفقیت
                        $('#gzseo-reset-messages').html(
                            '<div class="gzseo-alert gzseo-alert-success" style="margin-top: 10px;">' +
                            '<i class="fas fa-check-circle"></i> ' + escapeHtml(response.data.message) +
                            '</div>'
                        );
                        
                        // بازگشت به فرم لاگین بعد از 3 ثانیه
                        if (response.data.password_changed) {
                            setTimeout(function() {
                                $('#gzseo-reset-password-wrapper').fadeOut(300, function() {
                                    $(this).remove();
                                    $('#gzseo-login-panel').fadeIn(300);
                                    $('.gzseo-forget-password-wrapper').show();
                                    
                                    // نمایش پیام موفقیت در فرم لاگین
                                    $('#gzseo-login-panel').prepend(
                                        '<div class="gzseo-alert gzseo-alert-success" style="margin-bottom: 15px;">' +
                                        '<i class="fas fa-check-circle"></i> رمز عبور با موفقیت تغییر یافت. اکنون می‌توانید وارد شوید.' +
                                        '</div>'
                                    );
                                });
                            }, 3000);
                        }
                    } else {
                        // نمایش پیام خطا
                        $('#gzseo-reset-messages').html(
                            '<div class="gzseo-alert gzseo-alert-danger" style="margin-top: 10px;">' +
                            '<i class="fas fa-exclamation-circle"></i> ' + escapeHtml(response.data.message) +
                            '</div>'
                        );
                        
                        // فعال کردن دکمه
                        $button.prop('disabled', false).html('تغییر رمز عبور');
                    }
                },
                error: function() {
                    $('#gzseo-reset-messages').html(
                        '<div class="gzseo-alert gzseo-alert-danger" style="margin-top: 10px;">' +
                        '<i class="fas fa-exclamation-circle"></i> خطا در ارتباط با سرور' +
                        '</div>'
                    );
                    
                    // فعال کردن دکمه
                    $button.prop('disabled', false).html('تغییر رمز عبور');
                }
            });
        });
        
        // مدیریت دکمه انصراف
        $(document).on('click', '#gzseo-cancel-reset', function() {
            $('#gzseo-reset-password-wrapper').fadeOut(300, function() {
                $(this).remove();
                $('#gzseo-login-panel').fadeIn(300);
                $('.gzseo-forget-password-wrapper').show();
            });
        });
        
        
    });

    // Add CSS for progress bar and messages
    if (!$('#gzseo-bulk-update-styles').length) {
        $('<style id="gzseo-bulk-update-styles">')
            .text(`
                .gzseo-progress-bar {
                    width: 100%;
                    height: 30px;
                    background: #f0f0f0;
                    border-radius: 15px;
                    margin-top: 20px;
                    position: relative;
                    overflow: hidden;
                    box-shadow: inset 0 1px 3px rgba(0,0,0,0.2);
                }
                
                .gzseo-progress-fill {
                    height: 100%;
                    background: linear-gradient(90deg, #2196F3 0%, #1976D2 100%);
                    border-radius: 15px;
                    transition: width 0.3s ease;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
                }
                
                .gzseo-progress-text {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    font-weight: bold;
                    color: #333;
                    text-shadow: 0 1px 2px rgba(255,255,255,0.8);
                }
                
                .gzseo-bulk-message {
                    padding: 15px 40px 15px 15px;
                    margin: 0 0 20px 0;
                    border-radius: 5px;
                    position: relative;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    font-size: 14px;
                }
                
                .gzseo-bulk-message.success {
                    background: #d4edda;
                    color: #155724;
                    border: 1px solid #c3e6cb;
                }
                
                .gzseo-bulk-message.error {
                    background: #f8d7da;
                    color: #721c24;
                    border: 1px solid #f5c6cb;
                }
                
                .gzseo-message-close {
                    position: absolute;
                    top: 50%;
                    right: 15px;
                    transform: translateY(-50%);
                    background: none;
                    border: none;
                    font-size: 24px;
                    cursor: pointer;
                    opacity: 0.5;
                    transition: opacity 0.2s;
                    padding: 0;
                    width: 24px;
                    height: 24px;
                    line-height: 1;
                }
                
                .gzseo-message-close:hover {
                    opacity: 1;
                }
                
                .gzseo-preview-results {
                    animation: slideDown 0.3s ease-out;
                }
                
                @keyframes slideDown {
                    from {
                        opacity: 0;
                        transform: translateY(-10px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                
                .gzseo-bulk-execute-form input[disabled],
                .gzseo-bulk-execute-form button[disabled] {
                    opacity: 0.6;
                    cursor: not-allowed;
                }
            `)
            .appendTo('head');
    }

})(jQuery);
