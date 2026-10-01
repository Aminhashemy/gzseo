<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * کلاس مدیریت استایل جدول - نسخه اصلاح شده
 */
class GZSEO_Table_Styles {
    
    public function __construct() {
        // افزودن اکشن برای ذخیره تنظیمات
        add_action('wp_ajax_gzseo_save_table_styles', array($this, 'save_table_styles'));
        
        // افزودن استایل‌ها به صفحات نمایش محتوا با اولویت بالاتر
        add_action('wp_head', array($this, 'output_table_styles'), 999);
        
        // همچنین اضافه کردن به wp_footer برای اطمینان بیشتر
        add_action('wp_footer', array($this, 'output_table_styles_fallback'), 999);
        
        // اعمال استایل‌ها به جداول موجود با اولویت پایین‌تر
        add_filter('the_content', array($this, 'apply_table_styles'), 999);
        
        // پشتیبانی از excerpt
        add_filter('the_excerpt', array($this, 'apply_table_styles'), 999);
        
        // پشتیبانی از محتوای ویجت‌ها
        add_filter('widget_text', array($this, 'apply_table_styles'), 999);
        
        // اضافه کردن به inline styles برای اطمینان از بارگذاری
        add_action('wp_enqueue_scripts', array($this, 'enqueue_inline_styles'), 999);
    }
    
    /**
     * ذخیره تنظیمات استایل جدول
     */
    public function save_table_styles() {
        // دریافت و sanitize کردن nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        
        // بررسی nonce
        if (!$nonce || !wp_verify_nonce($nonce, 'gzseo_ajax_nonce')) {
            wp_send_json_error(array('message' => __('خطای امنیتی', 'gzseo')));
            return;
        }
        
        // بررسی دسترسی
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('دسترسی غیرمجاز', 'gzseo')));
            return;
        }
        
        // دریافت و ذخیره تنظیمات
        $settings = array(
            'use_predefined_styles' => isset($_POST['use_predefined_styles']) ? sanitize_text_field(wp_unslash($_POST['use_predefined_styles'])) : 'true',
            'table_position' => isset($_POST['table_position']) ? sanitize_text_field(wp_unslash($_POST['table_position'])) : 'after_content',
            'selected_style' => isset($_POST['selected_style']) ? sanitize_key(wp_unslash($_POST['selected_style'])) : '1',
            'header_bg' => isset($_POST['header_bg']) ? sanitize_hex_color(wp_unslash($_POST['header_bg'])) : '#4e4b83',
            'header_text' => isset($_POST['header_text']) ? sanitize_hex_color(wp_unslash($_POST['header_text'])) : '#FFFFFF',
            'odd_bg' => isset($_POST['odd_bg']) ? sanitize_hex_color(wp_unslash($_POST['odd_bg'])) : '#f4f4ff',
            'odd_text' => isset($_POST['odd_text']) ? sanitize_hex_color(wp_unslash($_POST['odd_text'])) : '#333333',
            'even_bg' => isset($_POST['even_bg']) ? sanitize_hex_color(wp_unslash($_POST['even_bg'])) : '#FFFFFF',
            'even_text' => isset($_POST['even_text']) ? sanitize_hex_color(wp_unslash($_POST['even_text'])) : '#333333'
        );
        
        // ذخیره در دیتابیس
        update_option('gzseo_table_styles', $settings);
        
        // پاک کردن کش (اگر پلاگین کش وجود دارد)
        $this->clear_cache();
        
        wp_send_json_success(array(
            'message' => __('تنظیمات با موفقیت ذخیره شد', 'gzseo'),
            'settings' => $settings
        ));
    }
    
    /**
     * دریافت تنظیمات استایل جدول
     */
    public function get_table_styles() {
        $defaults = array(
            'use_predefined_styles' => 'true',
            'table_position' => 'after_content',
            'selected_style' => '1',
            'header_bg' => '#4e4b83',
            'header_text' => '#FFFFFF',
            'odd_bg' => '#f4f4ff',
            'odd_text' => '#333333',
            'even_bg' => '#FFFFFF',
            'even_text' => '#333333'
        );
        
        return wp_parse_args(get_option('gzseo_table_styles', array()), $defaults);
    }
    
    /**
     * بررسی اینکه آیا باید استایل‌ها نمایش داده شوند
     */
    private function should_display_styles() {
        // نمایش در همه صفحات به جز صفحات ادمین
        if (is_admin()) {
            return false;
        }
        
        // نمایش در صفحات مختلف
        /*$display = is_singular() || // تمام صفحات تکی (پست، برگه، CPT)
                   is_home() || // صفحه اصلی بلاگ
                   is_front_page() || // صفحه اصلی سایت
                   is_archive() || // همه صفحات آرشیو
                   is_category() || // دسته‌بندی‌ها
                   is_tag() || // برچسب‌ها
                   is_tax() || // تکسونومی‌های سفارشی
                   is_author() || // صفحات نویسنده
                   is_date() || // آرشیو تاریخ
                   is_search(); // نتایج جستجو*/
        $display = is_single() 
                || (function_exists('is_product') && is_product());
        
        // اجازه به سایر پلاگین‌ها برای تغییر
        return apply_filters('gzseo_should_display_table_styles', $display);
    }
    
    /**
     * خروجی استایل‌ها در head
     */
    public function output_table_styles() {
        // بررسی اینکه آیا قبلاً استایل‌ها اضافه شده‌اند
        static $styles_added = false;
        if ($styles_added) {
            return;
        }
        
        // بررسی شرایط نمایش
        if (!$this->should_display_styles()) {
            return;
        }
        
        $settings = $this->get_table_styles();
        
        // اگر استفاده از استایل‌های آماده غیرفعال باشد
        if ($settings['use_predefined_styles'] !== 'true') {
            return;
        }
        
        // استایل‌های پیش‌فرض برای هر قالب
        $predefined_styles = $this->get_predefined_styles();
        
        // انتخاب استایل
        if ($settings['selected_style'] === 'custom') {
            $colors = array(
                'header_bg' => $settings['header_bg'],
                'header_text' => $settings['header_text'],
                'odd_bg' => $settings['odd_bg'],
                'odd_text' => $settings['odd_text'],
                'even_bg' => $settings['even_bg'],
                'even_text' => $settings['even_text']
            );
        } else {
            $colors = isset($predefined_styles[$settings['selected_style']]) 
                ? $predefined_styles[$settings['selected_style']] 
                : $predefined_styles['1'];
        }
        
        $css = $this->generate_css($colors);
        
        echo "\n<!-- GZSEO Table Styles Start -->\n";
        echo '<style id="gzseo-table-styles" type="text/css">' . "\n";
        // CSS is generated from trusted template with sanitized color values.
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $css;
        echo "</style>\n";
        echo "<!-- GZSEO Table Styles End -->\n\n";
        
        $styles_added = true;
    }
    
    /**
     * Sanitize and normalize a color set used for CSS generation.
     *
     * @param array $colors Raw color set.
     * @return array Sanitized color set with safe fallbacks.
     */
    private function sanitize_color_set( $colors ) {
        $defaults = array(
            'header_bg'   => '#4e4b83',
            'header_text' => '#FFFFFF',
            'odd_bg'      => '#f4f4ff',
            'odd_text'    => '#333333',
            'even_bg'     => '#FFFFFF',
            'even_text'   => '#333333',
        );

        $colors = is_array( $colors ) ? $colors : array();
        $colors = wp_parse_args( $colors, $defaults );

        foreach ( $defaults as $key => $fallback ) {
            $san = sanitize_hex_color( $colors[ $key ] );
            $colors[ $key ] = $san ? $san : $fallback;
        }

        return $colors;
    }

        /**
     * تولید CSS
     */
    private function generate_css( $colors ) {
        $colors = $this->sanitize_color_set( $colors );

        $css = '
            /* استایل‌های جدول جی زد سئو */
            .gzseo-table,
            .gzseo-generated-table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin: 20px 0 !important;
                background: #fff !important;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important;
            }

            /* استایل هدر جدول */
            .gzseo-table th,
            .gzseo-table thead td,
            .gzseo-generated-table th,
            .gzseo-generated-table thead td {
                background-color: ' . $colors['header_bg'] . ' !important;
                color: ' . $colors['header_text'] . ' !important;
                padding: 12px 15px !important;
                text-align: right !important;
                font-weight: bold !important;
                border: 1px solid #ddd !important;
                direction: rtl !important;
            }

            /* ردیف‌های فرد */
            .gzseo-table tbody tr:nth-child(odd) td,
            .gzseo-generated-table tbody tr:nth-child(odd) td{
                background-color: ' . $colors['odd_bg'] . ' !important;
                color: ' . $colors['odd_text'] . ' !important;
            }

            /* ردیف‌های زوج */
            .gzseo-table tbody tr:nth-child(even) td,
            .gzseo-generated-table tbody tr:nth-child(even) td{
                background-color: ' . $colors['even_bg'] . ' !important;
                color: ' . $colors['even_text'] . ' !important;
            }

            /* سلول‌های جدول */
            .gzseo-table td,
            .gzseo-generated-table td {
                padding: 10px 15px !important;
                border: 1px solid #ddd !important;
                text-align: right !important;
                direction: rtl !important;
            }

            /* hover effect */
            .gzseo-table tbody tr:hover td,
            .gzseo-generated-table tbody tr:hover td {
                opacity: 0.9;
                transition: opacity 0.3s ease;
            }

            /* حالت ریسپانسیو */
            @media screen and (max-width: 768px) {
                .gzseo-table,
                .gzseo-generated-table {
                    display: block !important;
                    overflow-x: auto !important;
                    -webkit-overflow-scrolling: touch !important;
                    margin-bottom: 20px !important;
                }

                .gzseo-table th,
                .gzseo-table td,
                .gzseo-generated-table th,
                .gzseo-generated-table td{
                    padding: 8px 10px !important;
                    font-size: 14px !important;
                }
            }
        ';

        return $css;
    }

    
    /**
     * fallback برای اضافه کردن استایل در footer
     */
    public function output_table_styles_fallback() {
        // بررسی با جاوااسکریپت که آیا استایل اضافه شده یا خیر
        ?>
        <script type="text/javascript">
        if (!document.getElementById('gzseo-table-styles')) {
        }
        </script>
        <?php
        
        // اگر استایل قبلاً اضافه نشده بود، اضافه کن
        static $footer_styles_added = false;
        if (!$footer_styles_added) {
            $this->output_table_styles();
            $footer_styles_added = true;
        }
    }
    
    /**
     * اضافه کردن استایل به صورت inline
     */
    public function enqueue_inline_styles() {
        if (!$this->should_display_styles()) {
            return;
        }
        
        $settings = $this->get_table_styles();
        
        if ($settings['use_predefined_styles'] !== 'true') {
            return;
        }
        
        // استایل‌های پیش‌فرض
        $predefined_styles = $this->get_predefined_styles();
        
        // انتخاب رنگ‌ها
        if ($settings['selected_style'] === 'custom') {
            $colors = array(
                'header_bg' => $settings['header_bg'],
                'header_text' => $settings['header_text'],
                'odd_bg' => $settings['odd_bg'],
                'odd_text' => $settings['odd_text'],
                'even_bg' => $settings['even_bg'],
                'even_text' => $settings['even_text']
            );
        } else {
            $colors = isset($predefined_styles[$settings['selected_style']]) 
                ? $predefined_styles[$settings['selected_style']] 
                : $predefined_styles['1'];
        }
        
        $css = $this->generate_css($colors);
        
        // اضافه کردن استایل inline
        wp_add_inline_style('wp-block-library', $css);
        
        // اگر بلاک ادیتور فعال نباشد
        if (!wp_style_is('wp-block-library', 'enqueued')) {
            wp_enqueue_style('gzseo-table-inline', false, array(), GZSEO_VERSION);
            wp_add_inline_style('gzseo-table-inline', $css);
        }
    }
    
    /**
     * دریافت استایل‌های پیش‌فرض
     */
    private function get_predefined_styles() {
        return array(
            '1' => array(
                'header_bg' => '#4e4b83',
                'header_text' => '#FFFFFF',
                'odd_bg' => '#f4f4ff',
                'odd_text' => '#333333',
                'even_bg' => '#FFFFFF',
                'even_text' => '#333333'
            ),
            '2' => array(
                'header_bg' => '#42a5f5',
                'header_text' => '#000000',
                'odd_bg' => '#dbefff',
                'odd_text' => '#333333',
                'even_bg' => '#bbdefb',
                'even_text' => '#333333'
            ),
            '3' => array(
                'header_bg' => '#343a40',
                'header_text' => '#FFFFFF',
                'odd_bg' => '#dee2e6',
                'odd_text' => '#333333',
                'even_bg' => '#adb5bd',
                'even_text' => '#333333'
            ),
            '4' => array(
                'header_bg' => '#204c01',
                'header_text' => '#FFFFFF',
                'odd_bg' => '#daffb8',
                'odd_text' => '#333333',
                'even_bg' => '#aad576',
                'even_text' => '#333333'
            ),
            '5' => array(
                'header_bg' => '#583101',
                'header_text' => '#FFFFFF',
                'odd_bg' => '#f3d5b5',
                'odd_text' => '#792924',
                'even_bg' => '#ffedd8',
                'even_text' => '#333333'
            )
        );
    }
    
    /**
     * اعمال کلاس به جداول موجود در محتوا
     */
    public function apply_table_styles($content) {
        // بررسی شرایط نمایش
        if (!$this->should_display_styles()) {
            return $content;
        }
        
        $settings = $this->get_table_styles();
        
        // اگر استفاده از استایل‌های آماده غیرفعال باشد
        if ($settings['use_predefined_styles'] !== 'true') {
            return $content;
        }
        
        // بررسی وجود جدول در محتوا
        if (strpos($content, '<table') === false) {
            return $content;
        }
        
        // اضافه کردن کلاس به جداول بدون کلاس
        $content = preg_replace(
            '/<table(?![^>]*\sclass=)([^>]*)>/i',
            '<table class="gzseo-table" $1>',
            $content
        );
        
        // اضافه کردن کلاس به جداول با کلاس موجود
        $content = preg_replace(
            '/<table\s+([^>]*\s)?class="([^"]*)"/i',
            '<table $1class="$2 gzseo-table"',
            $content
        );
        
        // اضافه کردن کلاس به جداول با single quote
        $content = preg_replace(
            '/<table\s+([^>]*\s)?class=\'([^\']*)\'/i',
            '<table $1class=\'$2 gzseo-table\'',
            $content
        );
        
        return $content;
    }
    
    /**
     * پاک کردن کش پلاگین‌های مختلف
     */
    private function clear_cache() {
        // WP Rocket
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
        
        // W3 Total Cache
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        
        // WP Super Cache
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
        
        // WP Fastest Cache
        if (function_exists('wpfc_clear_all_cache')) {
            wpfc_clear_all_cache();
        }
        
        // LiteSpeed Cache
        if (class_exists('LiteSpeed_Cache_API')) {
            LiteSpeed_Cache_API::purge_all();
        }
        
        // Autoptimize
        if (class_exists('autoptimizeCache')) {
            autoptimizeCache::clearall();
        }
    }
    
    /**
     * دریافت تنظیمات به صورت JSON برای استفاده در JavaScript
     */
    public function get_settings_json() {
        $settings = $this->get_table_styles();
        return wp_json_encode($settings);
    }
    
    /**
     * اضافه کردن تنظیمات به localize script
     */
    public function localize_settings($data) {
        $data['table_styles'] = $this->get_table_styles();
        return $data;
    }
}

// Initialize the class
new GZSEO_Table_Styles();
