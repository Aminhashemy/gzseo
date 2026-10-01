<?php
/**
 * کلاس مدیریت تنظیمات تصویر شاخص
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

class GZSEO_Featured_Image_Settings {
    
    // ابعاد پیش تعریف شده
    private $predefined_dimensions;
    
    public function __construct() {
        $this->predefined_dimensions = array(
            '1024x1024' => array('width' => 1024, 'height' => 1024, 'label' => __( '1024×1024 (مربع)', 'gzseo' )),
            '1152x896'  => array('width' => 1152, 'height' => 896, 'label' => __( '1152x896 (افقی)', 'gzseo' )),
            '896x1152' => array('width' => 896, 'height' => 1152, 'label' => __( '896x1152 (عمودی)', 'gzseo' )),
            '1216x832' => array('width' => 1216, 'height' => 832, 'label' => __( '1216x832 (افقی عریض)', 'gzseo' )),
            '1344x768' => array('width' => 1344, 'height' => 768, 'label' => __( '1344x768 (افقی فول)', 'gzseo' )),
            '768x1344' => array('width' => 768, 'height' => 1344, 'label' => __( '768x1344 (عمودی فول)', 'gzseo' )),
            '1536x640' => array('width' => 1536, 'height' => 640, 'label' => __( '1536x640 (افقی بنر)', 'gzseo' )),
            '640x1536' => array('width' => 640, 'height' => 1536, 'label' => __( '640x1536 (عمودی بنر)', 'gzseo' ))
        );
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // ثبت AJAX handler
        add_action('wp_ajax_gzseo_save_featured_image_settings', array($this, 'save_settings'));
    }
    
    /**
     * رندر پنل تنظیمات برای استفاده در تب پیکربندی
     */
    public function render_config_panel() {
        ?>
        <!-- Featured Image Settings Panel -->
        <div class="gzseo-config-panel" id="config-featured-image">
            <?php $this->render_settings_form(); ?>
        </div>
        <?php
    }
    
    /**
     * رندر فرم تنظیمات
     */
    private function render_settings_form() {
        // دریافت تنظیمات ذخیره شده
        $selected_dimension = get_option('gzseo_featured_image_selected_dimension', '1024x1024');
        $default_width = get_option('gzseo_featured_image_default_width', 1024);
        $default_height = get_option('gzseo_featured_image_default_height', 1024);
        ?>
        
        <h3><?php esc_html_e( 'تنظیمات تصویر شاخص', 'gzseo' ); ?></h3>
        
        <div class="gzseo-config-row">
            <div class="gzseo-config-col" style="width: 100%;">
                <h4><?php esc_html_e( 'ابعاد پیش‌فرض', 'gzseo' ); ?></h4>
                <p><?php esc_html_e( 'مشخص کنید ابعاد تصویر به صورت پیش‌فرض چه مقداری باشد.', 'gzseo' ); ?></p>
                <select id="gzseo-ai-dimensions" name="dimensions" class="gzseo-select">
                    <option value=""><?php esc_html_e( 'انتخاب ابعاد...', 'gzseo' ); ?></option>
                    <?php foreach ($this->predefined_dimensions as $key => $dimension): ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($selected_dimension, $key); ?>>
                            <?php echo esc_html($dimension['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <!-- نمایش ابعاد انتخاب شده -->
                <div class="gzseo-dimension-display">
                    <span><?php esc_html_e( 'عرض:', 'gzseo' ); ?> <strong id="display-width"><?php echo esc_html($default_width); ?></strong> <?php esc_html_e( 'پیکسل', 'gzseo' ); ?></span>
                    <span><?php esc_html_e( 'ارتفاع:', 'gzseo' ); ?> <strong id="display-height"><?php echo esc_html($default_height); ?></strong> <?php esc_html_e( 'پیکسل', 'gzseo' ); ?></span>
                </div>
            </div>
        </div>
        
        <!-- پیش‌نمایش ابعاد -->
        <div class="gzseo-dimension-preview-wrapper">
            <h4><?php esc_html_e( 'پیش‌نمایش ابعاد', 'gzseo' ); ?></h4>
            <div class="gzseo-preview-box" id="gzseo-dimension-preview-box">
                <span class="gzseo-preview-dimensions">
                    <span id="preview-width"><?php echo esc_html($default_width); ?></span>
                    ×
                    <span id="preview-height"><?php echo esc_html($default_height); ?></span>
                </span>
            </div>
            <p class="gzseo-preview-ratio" id="preview-ratio"></p>
        </div>
        
        <button class="gzseo-btn gzseo-btn-primary" id="gzseo-save-featured-image-settings">
            <i class="fas fa-save"></i>
            <?php esc_html_e( 'ذخیره تنظیمات', 'gzseo' ); ?>
        </button>
        
        <style>
            .gzseo-dimension-display {
                margin-top: 15px;
                padding: 10px;
                background: #f5f5f5;
                border-radius: 4px;
                display: flex;
                gap: 30px;
            }
            
            .gzseo-dimension-display strong {
                color: #2196F3;
                font-size: 16px;
            }
            
            .gzseo-range-wrapper {
                display: flex;
                align-items: center;
                gap: 15px;
            }
            
            .gzseo-range {
                flex: 1;
                height: 6px;
                -webkit-appearance: none;
                appearance: none;
                background: #ddd;
                border-radius: 3px;
                outline: none;
            }
            
            .gzseo-range::-webkit-slider-thumb {
                -webkit-appearance: none;
                appearance: none;
                width: 20px;
                height: 20px;
                background: #2196F3;
                border-radius: 50%;
                cursor: pointer;
            }
            
            .gzseo-range::-moz-range-thumb {
                width: 20px;
                height: 20px;
                background: #2196F3;
                border-radius: 50%;
                cursor: pointer;
                border: none;
            }
            
            .gzseo-range-value {
                min-width: 45px;
                text-align: center;
                font-weight: bold;
                color: #2196F3;
            }
            
            .gzseo-dimension-preview-wrapper {
                margin-top: 30px;
                padding: 20px;
                background: #f9f9f9;
                border-radius: 8px;
                text-align: center;
            }
            
            .gzseo-dimension-preview-wrapper h4 {
                margin-top: 0;
                margin-bottom: 15px;
            }
            
            .gzseo-preview-box {
                display: inline-block;
                background: #fff;
                border: 2px dashed #2196F3;
                border-radius: 4px;
                position: relative;
                max-width: 300px;
                max-height: 200px;
                transition: all 0.3s ease;
            }
            
            .gzseo-preview-dimensions {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                font-size: 16px;
                font-weight: bold;
                color: #2196F3;
            }
            
            .gzseo-preview-ratio {
                margin-top: 10px;
                color: #666;
                font-size: 13px;
            }
        </style>
        <?php
    }
    
    /**
     * ذخیره تنظیمات
     */
    public function save_settings() {
        // دریافت و sanitize کردن nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        
        // بررسی nonce
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'gzseo_ajax_nonce' ) ) {
            wp_send_json_error(array('message' => __( 'خطای امنیتی', 'gzseo' )));
            return;
        }
        
        // بررسی دسترسی
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __( 'شما دسترسی لازم را ندارید', 'gzseo' )));
            return;
        }
        
        // دریافت و sanitize کردن ابعاد انتخاب شده
        $selected_dimension = isset($_POST['dimensions']) ? sanitize_text_field( wp_unslash( $_POST['dimensions'] ) ) : '';
        
        if (empty($selected_dimension) || !isset($this->predefined_dimensions[$selected_dimension])) {
            wp_send_json_error(array('message' => __( 'لطفا یک ابعاد معتبر انتخاب کنید', 'gzseo' )));
            return;
        }
        
        // استخراج عرض و ارتفاع
        $dimension_data = $this->predefined_dimensions[$selected_dimension];
        $width = absint($dimension_data['width']);
        $height = absint($dimension_data['height']);
        
        // ذخیره در دیتابیس
        update_option('gzseo_featured_image_selected_dimension', $selected_dimension);
        update_option('gzseo_featured_image_default_width', $width);
        update_option('gzseo_featured_image_default_height', $height);
        
        wp_send_json_success(array(
            'message' => __( 'تنظیمات با موفقیت ذخیره شد', 'gzseo' ),
            'data' => array(
                'selected_dimension' => $selected_dimension,
                'width' => $width,
                'height' => $height
            )
        ));
    }
}

// Initialize the class
new GZSEO_Featured_Image_Settings();
