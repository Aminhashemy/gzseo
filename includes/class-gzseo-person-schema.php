<?php
/**
 * کلاس مدیریت اسکیمای Person
 * 
 * @package GZSeo
 * @since 2.0.0
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

class GZSEO_Person_Schema {
    
    /**
     * Constructor
     */
    public function __construct() {
        // اضافه کردن هوک‌ها
        add_action('wp_ajax_gzseo_save_person_schema', array($this, 'save_person_schema'));
        add_action('wp_ajax_gzseo_delete_person_schema', array($this, 'delete_person_schema'));
        add_action('wp_head', array($this, 'output_person_schema'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * بارگذاری اسکریپت‌های مدیریت
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'gzseo-dashboard') === false) {
            return;
        }
        
        // بارگذاری WordPress Media
        wp_enqueue_media();
        
        // بارگذاری Select2 CSS
        wp_enqueue_style(
            'select2',
            GZSEO_PLUGIN_URL . 'assets/css/select2.min.css',
            array(),
            GZSEO_VERSION
        );
        
        // بارگذاری Select2 JS
        wp_enqueue_script(
            'select2',
            GZSEO_PLUGIN_URL . 'assets/js/select2.min.js',
            array('jquery'),
            GZSEO_VERSION,
            true
        );
        
        // بارگذاری اسکریپت اسکیما
        wp_enqueue_script(
            'gzseo-schema-admin',
            GZSEO_PLUGIN_URL . 'assets/js/schema-admin.js',
            array('jquery', 'media-upload', 'select2'),
            GZSEO_VERSION,
            true
        );
    }
    
    /**
     * ذخیره اسکیمای Person
     */
    public function save_person_schema() {
        // بررسی nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'gzseo_ajax_nonce')) {
            wp_send_json_error(array('message' => __('خطای امنیتی', 'gzseo')));
            return;
        }
        
        // بررسی دسترسی
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('شما دسترسی لازم را ندارید', 'gzseo')));
            return;
        }
        
        // بررسی وجود mode و schema_data
        if (!isset($_POST['mode']) || !isset($_POST['schema_data'])) {
            wp_send_json_error(array('message' => __('داده‌های ارسالی ناقص است', 'gzseo')));
            return;
        }
        
        $mode = sanitize_text_field(wp_unslash($_POST['mode']));
        $schema_data_raw = wp_unslash( $_POST['schema_data'] );
        // schema_data is sanitized/validated in sanitize_schema_data().
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $schema_data = $this->sanitize_schema_data( $schema_data_raw );
        
        if ($schema_data === false) {
            wp_send_json_error(array('message' => __('داده‌های ارسالی نامعتبر است', 'gzseo')));
            return;
        }
        
        // بررسی وجود اسکیمای قبلی در حالت ایجاد
        $existing_schema = get_option('gzseo_person_schema', array());
        if ($mode === 'create' && !empty($existing_schema)) {
            wp_send_json_error(array('message' => __('یک اسکیمای Person قبلاً ذخیره شده است', 'gzseo')));
            return;
        }
        
        // اعتبارسنجی داده‌ها
        if (empty($schema_data['name'])) {
            wp_send_json_error(array('message' => __('نام و نام خانوادگی الزامی است', 'gzseo')));
            return;
        }
        
        // تمیزسازی و آماده‌سازی داده‌ها
        $clean_data = array(
            'pages' => isset($schema_data['pages']) && is_array($schema_data['pages']) ? array_map('sanitize_text_field', $schema_data['pages']) : array(),
            'name' => sanitize_text_field($schema_data['name']),
            'job_title' => isset($schema_data['job_title']) ? sanitize_text_field($schema_data['job_title']) : '',
            'telephone' => isset($schema_data['telephone']) ? sanitize_text_field($schema_data['telephone']) : '',
            'email' => isset($schema_data['email']) ? sanitize_email($schema_data['email']) : '',
            'url' => isset($schema_data['url']) ? esc_url_raw($schema_data['url']) : '',
            'address' => isset($schema_data['address']) ? sanitize_text_field($schema_data['address']) : '',
            'birthdate' => isset($schema_data['birthdate']) ? sanitize_text_field($schema_data['birthdate']) : '',
            'image_id' => isset($schema_data['image_id']) ? absint($schema_data['image_id']) : 0,
            'image_url' => isset($schema_data['image_url']) ? esc_url_raw($schema_data['image_url']) : '',
            'description' => isset($schema_data['description']) ? sanitize_textarea_field($schema_data['description']) : '',
            'social_links' => isset($schema_data['social_links']) && is_array($schema_data['social_links']) ? array_map('esc_url_raw', array_filter($schema_data['social_links'])) : array()
        );

        // اگر image_id دارد، URL را از آن بگیر (برای اطمینان)
        if (!empty($clean_data['image_id'])) {
            $attachment_url = wp_get_attachment_url($clean_data['image_id']);
            if ($attachment_url) {
                $clean_data['image_url'] = $attachment_url;
            }
        }

        // ذخیره در دیتابیس
        $result = update_option('gzseo_person_schema', $clean_data);

        if ($result || get_option('gzseo_person_schema') === $clean_data) {
            $message = $mode === 'create' ? __('اسکیمای Person با موفقیت ذخیره شد', 'gzseo') : __('اسکیمای Person با موفقیت بروزرسانی شد', 'gzseo');
            wp_send_json_success(array(
                'message' => $message,
                'data' => $clean_data
            ));
        } else {
            wp_send_json_error(array('message' => __('خطا در ذخیره اطلاعات در دیتابیس', 'gzseo')));
        }
    }
    
    /**
     * Sanitize schema data array
     * 
     * @param mixed $data Raw schema data
     * @return array|false Sanitized array or false on failure
     */
    private function sanitize_schema_data($data) {
        // If data is not an array, return false
        if (!is_array($data)) {
            return false;
        }
        
        $sanitized = array();
        
        // Define allowed keys and their sanitization callbacks
        $allowed_fields = array(
            'pages' => 'array',
            'name' => 'text',
            'job_title' => 'text',
            'telephone' => 'text',
            'email' => 'email',
            'url' => 'url',
            'address' => 'text',
            'birthdate' => 'text',
            'image_id' => 'int',
            'image_url' => 'url',
            'description' => 'textarea',
            'social_links' => 'array_url'
        );
        
        foreach ($allowed_fields as $key => $type) {
            if (!isset($data[$key])) {
                continue;
            }
            
            $value = $data[$key];
            
            switch ($type) {
                case 'text':
                    $sanitized[$key] = is_string($value) ? sanitize_text_field($value) : '';
                    break;
                    
                case 'textarea':
                    $sanitized[$key] = is_string($value) ? sanitize_textarea_field($value) : '';
                    break;
                    
                case 'email':
                    $sanitized[$key] = is_string($value) ? sanitize_email($value) : '';
                    break;
                    
                case 'url':
                    $sanitized[$key] = is_string($value) ? esc_url_raw($value) : '';
                    break;
                    
                case 'int':
                    $sanitized[$key] = absint($value);
                    break;
                    
                case 'array':
                    if (is_array($value)) {
                        $sanitized[$key] = array_map('sanitize_text_field', $value);
                    } else {
                        $sanitized[$key] = array();
                    }
                    break;
                    
                case 'array_url':
                    if (is_array($value)) {
                        $sanitized[$key] = array_map('esc_url_raw', array_filter($value));
                    } else {
                        $sanitized[$key] = array();
                    }
                    break;
                    
                default:
                    $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * حذف اسکیمای Person
     */
    public function delete_person_schema() {
        // بررسی nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'gzseo_ajax_nonce')) {
            wp_send_json_error(array('message' => __('خطای امنیتی', 'gzseo')));
            return;
        }
        
        // بررسی دسترسی
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('شما دسترسی لازم را ندارید', 'gzseo')));
            return;
        }
        
        // حذف از دیتابیس
        $result = delete_option('gzseo_person_schema');
        
        if ($result) {
            wp_send_json_success(array('message' => __('اسکیمای Person با موفقیت حذف شد', 'gzseo')));
        } else {
            wp_send_json_error(array('message' => __('خطا در حذف اسکیما', 'gzseo')));
        }
    }
    
    /**
     * خروجی اسکیما در صفحات انتخاب شده
     */
    public function output_person_schema() {
        $schema_data = get_option( 'gzseo_person_schema', array() );

        if ( empty( $schema_data ) || empty( $schema_data['name'] ) ) {
            return;
        }

        $selected_pages = isset( $schema_data['pages'] ) && is_array( $schema_data['pages'] ) ? $schema_data['pages'] : array();

        // بررسی اینکه آیا باید در این صفحه نمایش داده شود
        if ( ! $this->should_display_schema( $selected_pages ) ) {
            return;
        }

        // Sanitize/validate values for canonical internal use (do not HTML-escape here).
        $name = sanitize_text_field( (string) $schema_data['name'] );
        if ( '' === $name ) {
            return;
        }

        // ساخت داده‌های JSON-LD
        $schema = array(
            '@context' => 'https://schema.org',
            '@type'    => 'Person',
            'name'     => $name,
        );

        // افزودن فیلدهای اختیاری
        if ( ! empty( $schema_data['job_title'] ) ) {
            $schema['jobTitle'] = sanitize_text_field( (string) $schema_data['job_title'] );
        }

        if ( ! empty( $schema_data['telephone'] ) ) {
            $schema['telephone'] = sanitize_text_field( (string) $schema_data['telephone'] );
        }

        if ( ! empty( $schema_data['email'] ) ) {
            $email = sanitize_email( (string) $schema_data['email'] );
            if ( '' !== $email ) {
                $schema['email'] = $email;
            }
        }

        if ( ! empty( $schema_data['url'] ) ) {
            $url = esc_url_raw( (string) $schema_data['url'] );
            if ( '' !== $url ) {
                $schema['url'] = $url;
            }
        }

        if ( ! empty( $schema_data['address'] ) ) {
            $schema['address'] = array(
                '@type'           => 'PostalAddress',
                'addressLocality' => sanitize_text_field( (string) $schema_data['address'] ),
            );
        }

        if ( ! empty( $schema_data['birthdate'] ) ) {
            $birthdate      = sanitize_text_field( (string) $schema_data['birthdate'] );
            $gregorian_date = $this->convert_persian_to_gregorian( $birthdate );
            if ( $gregorian_date ) {
                $schema['birthDate'] = sanitize_text_field( (string) $gregorian_date );
            }
        }

        if ( ! empty( $schema_data['image_url'] ) ) {
            $image_url = esc_url_raw( (string) $schema_data['image_url'] );
            if ( '' !== $image_url ) {
                $schema['image'] = $image_url;
            }
        }

        if ( ! empty( $schema_data['description'] ) ) {
            // Treat description as plain text for JSON-LD.
            $schema['description'] = sanitize_textarea_field( (string) $schema_data['description'] );
        }

        if ( ! empty( $schema_data['social_links'] ) ) {
            $links = $schema_data['social_links'];

            if ( is_string( $links ) ) {
                $links = preg_split( '/[\r\n,]+/', $links );
            }

            if ( is_array( $links ) ) {
                $links = array_values( array_filter( array_map( 'esc_url_raw', $links ) ) );
                if ( ! empty( $links ) ) {
                    $schema['sameAs'] = $links;
                }
            }
        }

        // خروجی JSON-LD (escape/encode at the output sink).
        echo "<script type=\"application/ld+json\">\n";
        echo wp_json_encode(
            $schema,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
        );
        echo "\n</script>\n";
    }

    /**
     * بررسی اینکه آیا اسکیما باید در صفحه فعلی نمایش داده شود
     */
private function should_display_schema($selected_pages) {
        if (empty($selected_pages)) {
            return false;
        }
        
        // اگر "همه صفحات" انتخاب شده باشد
        if (in_array('all', $selected_pages)) {
            return true;
        }
        
        // صفحه اصلی
        if (is_front_page() && in_array('front_page', $selected_pages)) {
            return true;
        }
        
        // صفحه بلاگ
        if (is_home() && in_array('blog_page', $selected_pages)) {
            return true;
        }
        
        // صفحه جستجو
        if (is_search() && in_array('search_page', $selected_pages)) {
            return true;
        }
        
        // صفحه 404
        if (is_404() && in_array('404_page', $selected_pages)) {
            return true;
        }
        
        // بررسی برای نوشته‌ها
        if (is_single() && !is_attachment()) {
            $post_id = get_the_ID();
            $post_type = get_post_type();
            
            // بررسی نوشته‌های معمولی
            if ($post_type === 'post' && in_array('post_' . $post_id, $selected_pages)) {
                return true;
            }
            
            // بررسی محصولات
            if ($post_type === 'product' && in_array('product_' . $post_id, $selected_pages)) {
                return true;
            }
        }
        
        // بررسی برای صفحات
        if (is_page()) {
            $page_id = get_the_ID();
            if (in_array('page_' . $page_id, $selected_pages)) {
                return true;
            }
        }
        
        // بررسی برای دسته‌بندی‌ها
        if (is_category()) {
            $category_id = get_queried_object_id();
            if (in_array('category_' . $category_id, $selected_pages)) {
                return true;
            }
        }
        
        // بررسی برای برچسب‌ها
        if (is_tag()) {
            $tag_id = get_queried_object_id();
            if (in_array('tag_' . $tag_id, $selected_pages)) {
                return true;
            }
        }
        
        // بررسی برای دسته‌بندی محصولات
        if (is_tax('product_cat')) {
            $term_id = get_queried_object_id();
            if (in_array('product_cat_' . $term_id, $selected_pages)) {
                return true;
            }
        }
        
        return false;
    }
    
    /** Convert a Jalali date (YYYY/MM/DD) to ISO Gregorian format. */
    private function convert_persian_to_gregorian($persian_date) {
        if (empty($persian_date)) {
            return '';
        }

        $normalized = strtr((string) $persian_date, array(
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
            '-'=>'/','.'=>'/'
        ));
        $parts = array_map('intval', explode('/', $normalized));
        if (3 !== count($parts) || $parts[0] < 1200 || $parts[0] > 1700 || $parts[1] < 1 || $parts[1] > 12 || $parts[2] < 1 || $parts[2] > 31) {
            return '';
        }

        list($jy, $jm, $jd) = $parts;
        $jy += 1595;
        $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33) + 3, 4) + $jd;
        $days += ($jm < 7) ? (($jm - 1) * 31) : ((($jm - 7) * 30) + 186);

        $gy = 400 * intdiv($days, 146097);
        $days %= 146097;
        if ($days > 36524) {
            $gy += 100 * intdiv(--$days, 36524);
            $days %= 36524;
            if ($days >= 365) {
                $days++;
            }
        }
        $gy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $gy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        $gd = $days + 1;
        $month_days = array(0, 31, (($gy % 4 === 0 && $gy % 100 !== 0) || $gy % 400 === 0) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
        for ($gm = 1; $gm <= 12 && $gd > $month_days[$gm]; $gm++) {
            $gd -= $month_days[$gm];
        }

        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }
    
    /**
     * دریافت تنظیمات اسکیمای Person
     */
    public static function get_schema_settings() {
        return get_option('gzseo_person_schema', array());
    }
    
    /**
     * بررسی وجود اسکیما
     */
    public static function has_schema() {
        $schema = get_option('gzseo_person_schema', array());
        return !empty($schema) && !empty($schema['name']);
    }
}

// Initialize the class
new GZSEO_Person_Schema();
