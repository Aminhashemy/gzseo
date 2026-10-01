// Featured Image Settings JavaScript
(function($) {
    'use strict';

    // ابعاد پیش تعریف شده
    const predefinedDimensions = {
        '1024x1024': { width: 1024, height: 1024 },
        '1152x896': { width: 1152, height: 896 },
        '896x1152': { width: 896, height: 1152 },
        '1216x832': { width: 1216, height: 832 },
        '1344x768': { width: 1344, height: 768 },
        '768x1344': { width: 768, height: 1344 },
        '1536x640': { width: 1536, height: 640 },
        '640x1536': { width: 640, height: 1536 }
    };

    $(document).ready(function() {
        // بررسی وجود پنل تنظیمات تصویر شاخص
        if ($('#config-featured-image').length) {
            initFeaturedImageSettings();
        }
    });

    function initFeaturedImageSettings() {
        // به‌روزرسانی پیش‌نمایش هنگام تغییر ابعاد
        $('#gzseo-ai-dimensions').on('change', function() {
            const selectedValue = $(this).val();
            if (selectedValue && predefinedDimensions[selectedValue]) {
                updateDimensionDisplay(predefinedDimensions[selectedValue]);
            }
        });

        // ذخیره تنظیمات
        $('#gzseo-save-featured-image-settings').on('click', function(e) {
            e.preventDefault();
            saveFeaturedImageSettings();
        });

        // محاسبه اولیه نسبت تصویر
        const initialDimension = $('#gzseo-ai-dimensions').val();
        if (initialDimension && predefinedDimensions[initialDimension]) {
            updateDimensionDisplay(predefinedDimensions[initialDimension]);
        }
    }

    // تابع به‌روزرسانی نمایش ابعاد
    function updateDimensionDisplay(dimensions) {
        const { width, height } = dimensions;
        
        // به‌روزرسانی اعداد در بخش اطلاعات
        $('#display-width').text(width);
        $('#display-height').text(height);
        
        // به‌روزرسانی پیش‌نمایش
        $('#preview-width').text(width);
        $('#preview-height').text(height);
        
        // محاسبه نسبت
        const gcd = findGCD(width, height);
        const ratioWidth = width / gcd;
        const ratioHeight = height / gcd;
        $('#preview-ratio').html(`<i class="fas fa-expand-arrows-alt"></i> نسبت تصویر: ${ratioWidth}:${ratioHeight}`);
        
        // به‌روزرسانی اندازه باکس پیش‌نمایش
        updatePreviewBox(width, height);
    }

    // تابع به‌روزرسانی باکس پیش‌نمایش
    function updatePreviewBox(width, height) {
        const maxPreviewWidth = 300;
        const maxPreviewHeight = 200;
        
        let previewWidth = width;
        let previewHeight = height;
        
        // محاسبه اندازه متناسب برای پیش‌نمایش
        if (width > maxPreviewWidth || height > maxPreviewHeight) {
            const widthRatio = maxPreviewWidth / width;
            const heightRatio = maxPreviewHeight / height;
            const ratio = Math.min(widthRatio, heightRatio);
            
            previewWidth = Math.round(width * ratio);
            previewHeight = Math.round(height * ratio);
        }
        
        $('#gzseo-dimension-preview-box').css({
            width: previewWidth + 'px',
            height: previewHeight + 'px'
        });
    }

    // تابع محاسبه بزرگترین مقسوم علیه مشترک
    function findGCD(a, b) {
        return b === 0 ? a : findGCD(b, a % b);
    }

    // تابع ذخیره تنظیمات
    function saveFeaturedImageSettings() {
        const $btn = $('#gzseo-save-featured-image-settings');
        const originalText = $btn.html();
        
        // جمع‌آوری داده‌ها
        const formData = {
            action: 'gzseo_save_featured_image_settings',
            nonce: gzseo_ajax.nonce,
            dimensions: $('#gzseo-ai-dimensions').val()
        };
        
        // اعتبارسنجی
        if (!formData.dimensions) {
            showMessage('error', 'لطفا یک ابعاد را انتخاب کنید');
            $('#gzseo-ai-dimensions').focus();
            return;
        }
        
        // نمایش حالت لودینگ
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> در حال ذخیره...');
        
        // ارسال درخواست AJAX
        $.ajax({
            url: gzseo_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showMessage('success', response.data.message);
                    
                    // انیمیشن موفقیت
                    $btn.addClass('gzseo-success-animation');
                    setTimeout(function() {
                        $btn.removeClass('gzseo-success-animation');
                    }, 1000);
                } else {
                    showMessage('error', response.data.message || 'خطا در ذخیره تنظیمات');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                showMessage('error', 'خطا در برقراری ارتباط با سرور');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    }

	// تابع نمایش پیام
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
		
		// Add to active config panel
		$('#config-featured-image').prepend($message);
		
		// Show with animation
		$message.hide().slideDown(300);
		
		// Auto hide after 5 seconds
		setTimeout(function() {
			$message.slideUp(300, function() {
				$(this).remove();
			});
		}, 5000);
	}

})(jQuery);