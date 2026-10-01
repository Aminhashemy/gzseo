jQuery(document).ready(function($) {
    'use strict';
    
    // اگر در تب اسکیما نیستیم، کد اجرا نشود
    if ($('#config-schema').length === 0) {
        return;
    }
    
    // Initialize Select2 for pages selection
    $('.gzseo-pages-select').select2({
        placeholder: 'صفحاتی را که می‌خواهید اسکیما در آن‌ها نمایش داده شود انتخاب کنید',
        allowClear: true,
        dir: 'rtl',
        language: {
            noResults: function() {
                return 'نتیجه‌ای یافت نشد';
            },
            searching: function() {
                return 'در حال جستجو...';
            },
            removeAllItems: function() {
                return 'حذف همه موارد';
            }
        },
        width: '100%',
        templateResult: function(data) {
            if (!data.id) {
                return data.text;
            }
            
            // اضافه کردن رنگ به انواع مختلف
            var $result = $('<span>' + data.text + '</span>');
            
            if (data.id === 'all') {
                $result.css('font-weight', 'bold');
            } else if (data.id.startsWith('post_')) {
                $result.prepend('<i class="fas fa-file-alt" style="margin-left: 5px; color: #2196F3;"></i> ');
            } else if (data.id.startsWith('page_')) {
                $result.prepend('<i class="fas fa-file" style="margin-left: 5px; color: #4CAF50;"></i> ');
            } else if (data.id.startsWith('product_')) {
                $result.prepend('<i class="fas fa-shopping-cart" style="margin-left: 5px; color: #FF9800;"></i> ');
            } else if (data.id.startsWith('category_')) {
                $result.prepend('<i class="fas fa-folder" style="margin-left: 5px; color: #9C27B0;"></i> ');
            } else if (data.id.startsWith('tag_')) {
                $result.prepend('<i class="fas fa-tag" style="margin-left: 5px; color: #00BCD4;"></i> ');
            }
            
            return $result;
        },
        templateSelection: function(data) {
            return data.text;
        }
    });
    
    // وقتی "همه صفحات" انتخاب شد، بقیه گزینه‌ها غیرفعال شود
    $('.gzseo-pages-select').on('change', function() {
        var values = $(this).val() || [];
        if (values.includes('all')) {
            // اگر "همه صفحات" انتخاب شده، فقط همان را نگه دار
            $(this).val(['all']).trigger('change');
            // غیرفعال کردن انتخاب سایر گزینه‌ها
            $(this).find('option:not([value="all"])').prop('disabled', true);
        } else {
            // فعال کردن همه گزینه‌ها
            $(this).find('option').prop('disabled', false);
        }
    });
    
    // Media uploader for image selection
    let mediaUploader;
    
    $('#gzseo-select-image').on('click', function(e) {
        e.preventDefault();
        
        // اگر media uploader قبلاً باز شده، دوباره بازش کن
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        // ایجاد media uploader
        mediaUploader = wp.media({
            title: 'انتخاب تصویر برای اسکیمای Person',
            button: {
                text: 'انتخاب تصویر'
            },
            multiple: false
        });
        
        // وقتی تصویر انتخاب شد
        mediaUploader.on('select', function() {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#gzseo-person-image-id').val(attachment.id);
            $('#gzseo-person-image-url').val(attachment.url);
            $('#gzseo-remove-image').show();
            
            // نمایش پیش‌نمایش تصویر
            var preview = $('.gzseo-schema-image-preview');
            if (preview.length === 0) {
                preview = $('<div class="gzseo-schema-image-preview"></div>');
                $('.gzseo-schema-image-selector').after(preview);
            }
            preview.html('<img src="' + attachment.url + '" alt="پیش‌نمایش تصویر">');
        });
        
        // باز کردن media uploader
        mediaUploader.open();
    });
    
    // حذف تصویر
    $('#gzseo-remove-image').on('click', function(e) {
        e.preventDefault();
        $('#gzseo-person-image-id').val('');
        $('#gzseo-person-image-url').val('');
        $(this).hide();
        $('.gzseo-schema-image-preview').remove();
    });
    
    // افزودن شبکه اجتماعی
    $('#gzseo-schema-add-social').on('click', function(e) {
        e.preventDefault();
        
        const newSocialLink = `
            <div class="gzseo-schema-social-link-item">
                <input type="url" class="gzseo-schema-input" name="social_links[]" value="" placeholder="https://twitter.com/username">
                <button type="button" class="gzseo-btn gzseo-btn-danger gzseo-schema-btn gzseo-schema-btn-danger gzseo-remove-social">حذف</button>
            </div>
        `;
        
        $('#gzseo-schema-social-links').append(newSocialLink);
    });
    
    // حذف شبکه اجتماعی
    $(document).on('click', '.gzseo-remove-social', function(e) {
        e.preventDefault();
        
        // اگر فقط یک آیتم باقی مانده، اجازه حذف نده
        if ($('.gzseo-schema-social-link-item').length > 1) {
            $(this).closest('.gzseo-schema-social-link-item').fadeOut(300, function() {
                $(this).remove();
            });
        } else {
            // فقط مقدار را خالی کن
            $(this).siblings('input').val('');
        }
    });
    
    // ذخیره/بروزرسانی اسکیما
    $('#gzseo-person-schema-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        const mode = $form.data('mode');
        const originalBtnText = $submitBtn.html();
        
        // اعتبارسنجی
        const name = $form.find('input[name="name"]').val().trim();
        if (!name) {
            showSchemaMessage('error', 'لطفا نام و نام خانوادگی را وارد کنید');
            $form.find('input[name="name"]').focus();
            return;
        }
        
        // جمع‌آوری داده‌ها
        const formData = {
            action: 'gzseo_save_person_schema',
            nonce: gzseo_ajax.nonce,
            mode: mode,
            schema_data: {
                pages: $form.find('select[name="pages[]"]').val() || [],
                name: name,
                job_title: $form.find('input[name="job_title"]').val(),
                telephone: $form.find('input[name="telephone"]').val(),
                email: $form.find('input[name="email"]').val(),
                url: $form.find('input[name="url"]').val(),
                address: $form.find('input[name="address"]').val(),
                birthdate: $form.find('input[name="birthdate"]').val(),
                image_id: $form.find('input[name="image_id"]').val(),
                image_url: $form.find('input[name="image_url"]').val(),
                description: $form.find('textarea[name="description"]').val(),
                social_links: []
            }
        };
        
        // جمع‌آوری لینک‌های شبکه‌های اجتماعی
        $form.find('input[name="social_links[]"]').each(function() {
            const link = $(this).val().trim();
            if (link) {
                formData.schema_data.social_links.push(link);
            }
        });
        
        // غیرفعال کردن دکمه
        $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> در حال ذخیره...');
        
        // ارسال درخواست
        $.ajax({
            url: gzseo_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showSchemaMessage('success', response.data.message);
                    
                    // بروزرسانی حالت فرم
                    if (mode === 'create') {
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    }
                } else {
                    showSchemaMessage('error', response.data.message || 'خطا در ذخیره اسکیما');
                }
            },
            error: function(xhr, status, error) {
                console.error('Schema save error:', error);
                showSchemaMessage('error', 'خطا در ارتباط با سرور');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).html(originalBtnText);
            }
        });
    });
    
    // حذف اسکیما
    $('#gzseo-delete-schema').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('آیا از حذف اسکیمای Person اطمینان دارید؟ این عمل قابل بازگشت نیست.')) {
            return;
        }
        
        const $btn = $(this);
        const originalBtnText = $btn.html();
        
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> در حال حذف...');
        
        $.ajax({
            url: gzseo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'gzseo_delete_person_schema',
                nonce: gzseo_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showSchemaMessage('success', response.data.message);
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showSchemaMessage('error', response.data.message || 'خطا در حذف اسکیما');
                }
            },
            error: function(xhr, status, error) {
                console.error('Schema delete error:', error);
                showSchemaMessage('error', 'خطا در ارتباط با سرور');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalBtnText);
            }
        });
    });
    
    // اعتبارسنجی تاریخ شمسی
    $('input[name="birthdate"]').on('blur', function() {
        const value = $(this).val().trim();
        if (value && !isValidPersianDate(value)) {
            showSchemaMessage('error', 'فرمت تاریخ صحیح نیست. مثال: 1375/05/15');
            $(this).focus();
        }
    });
    
    // نمایش پیام
    function showSchemaMessage(type, message) {
        const $container = $('#config-schema');
        const alertClass = type === 'success' ? 'gzseo-schema-alert-success' : 'gzseo-schema-alert-error';
        const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
        
        // حذف پیام‌های قبلی
        $container.find('.gzseo-schema-alert:not(.gzseo-schema-alert-info)').remove();
        
        const $message = $(`
            <div class="gzseo-schema-alert ${alertClass}" style="margin-bottom: 20px;">
                <i class="fas fa-${icon}"></i>
                ${message}
            </div>
        `);
        
        // اضافه کردن بعد از عنوان
        $container.find('h3').after($message);
        
        // اسکرول به بالای فرم
        $('html, body').animate({
            scrollTop: $container.offset().top - 100
        }, 300);
        
        // حذف خودکار پیام بعد از 5 ثانیه
        setTimeout(function() {
            $message.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // اعتبارسنجی تاریخ شمسی
    function isValidPersianDate(date) {
        const pattern = /^\d{4}\/\d{2}\/\d{2}$/;
        if (!pattern.test(date)) return false;
        
        const parts = date.split('/');
        const year = parseInt(parts[0]);
        const month = parseInt(parts[1]);
        const day = parseInt(parts[2]);
        
        // بررسی محدوده سال
        if (year < 1300 || year > 1450) return false;
        
        // بررسی محدوده ماه
        if (month < 1 || month > 12) return false;
        
        // بررسی محدوده روز
        if (day < 1 || day > 31) return false;
        
        // ماه‌های 31 روزه (1-6)
        if (month <= 6 && day > 31) return false;
        
        // ماه‌های 30 روزه (7-11)
        if (month >= 7 && month <= 11 && day > 30) return false;
        
        // اسفند (ماه 12)
        if (month === 12 && day > 30) return false; // ساده‌سازی برای سال‌های کبیسه
        
        return true;
    }
    
    // هایلایت فیلد اجباری
    $('input[required]').on('invalid', function() {
        $(this).addClass('error');
    }).on('input', function() {
        $(this).removeClass('error');
    });
});