<?php
/**
 * کلاس مدیریت انتقال اطلاعات Search Console از نسخه قدیم به جدید
 * 
 * @package GZSeo
 * @since 2.0.0
 */

class GZSEO_Search_Console_Migration {
    
    /**
     * اجرای فرآیند migration
     * این متد در زمان نصب/فعالسازی افزونه اجرا می‌شود
     */
    public static function migrate() {
        // بررسی اینکه آیا قبلاً migrate شده یا نه
        if (get_option('gzseo_sc_migrated_v2', false)) {
            return;
        }
        
        // بررسی وجود اطلاعات در نسخه جدید
        if (get_option('gzseo_sc_connected', false)) {
            // اگر در نسخه جدید قبلاً متصل شده، نیازی به migration نیست
            self::mark_as_migrated();
            return;
        }
        
        // بررسی وجود اطلاعات نسخه قدیم
        $old_ga_data = get_option('gzseo_ga_data', null);
        
        if (!$old_ga_data || !is_array($old_ga_data)) {
            // اگر اطلاعات قدیمی وجود ندارد
            self::mark_as_migrated();
            return;
        }
        
        // بررسی فیلدهای ضروری
        if (!isset($old_ga_data['access_token']) || 
            !isset($old_ga_data['refresh_token']) || 
            empty($old_ga_data['access_token'])) {
            self::mark_as_migrated();
            return;
        }
        
        // انتقال اطلاعات به فرمت جدید
        self::migrate_search_console_data($old_ga_data);
    }
    
    /**
     * انتقال اطلاعات Search Console به فرمت جدید
     */
    private static function migrate_search_console_data($old_data) {
        // استخراج و اعتبارسنجی داده‌های قدیمی
        $access_token = isset($old_data['access_token']) ? $old_data['access_token'] : '';
        $refresh_token = isset($old_data['refresh_token']) ? $old_data['refresh_token'] : '';
        $expires_in = isset($old_data['expires_in']) ? intval($old_data['expires_in']) : 3600;
        $created = isset($old_data['created']) ? intval($old_data['created']) : time();
        $domain = isset($old_data['domain']) ? $old_data['domain'] : '';
        
        // محاسبه زمان انقضا
        $expires_at = $created + $expires_in;
        
        // اگر زمان انقضا در گذشته است، از زمان فعلی + expires_in استفاده کن
        if ($expires_at < time()) {
            $expires_at = time() + $expires_in;
        }
        
        // پردازش دامنه
        $processed_domain = self::process_domain($domain);
        
        // ذخیره در فرمت جدید - هر option جداگانه
        update_option('gzseo_sc_access_token', sanitize_text_field($access_token));
        update_option('gzseo_sc_refresh_token', sanitize_text_field($refresh_token));
        update_option('gzseo_sc_token_expires', $expires_at);
        update_option('gzseo_sc_expires_in', $expires_in);
        update_option('gzseo_sc_token_type', 'Bearer'); // مقدار پیش‌فرض
        update_option('gzseo_sc_scope', 'https://www.googleapis.com/auth/webmasters.readonly'); // مقدار احتمالی
        update_option('gzseo_sc_created', $created);
        update_option('gzseo_sc_domain', $processed_domain['domain']);
        update_option('gzseo_sc_verified_domain', $processed_domain['domain']);
        
        // تنظیم نوع دامنه و تنظیمات پیشرفته
        update_option('gzseo_sc_domain_type', $processed_domain['type']);
        update_option('gzseo_sc_site_url', $processed_domain['domain']);
        update_option('gzseo_sc_use_advanced', $processed_domain['use_advanced']);
        
        // علامت‌گذاری به عنوان متصل
        update_option('gzseo_sc_connected', true);
        update_option('gzseo_sc_connected_at', current_time('mysql'));
        
        // ذخیره همه داده‌ها به صورت یکجا (برای سازگاری)
        $search_console_data = array(
            'access_token' => sanitize_text_field($access_token),
            'refresh_token' => sanitize_text_field($refresh_token),
            'expires_in' => $expires_in,
            'expires_at' => $expires_at,
            'token_type' => 'Bearer',
            'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
            'created' => $created,
            'domain' => $processed_domain['domain'],
            'email' => '', // در نسخه قدیم email ذخیره نمی‌شد
            'connected_at' => current_time('mysql')
        );
        
        update_option('gzseo_search_console_data', $search_console_data);
        
        // علامت‌گذاری به عنوان migrate شده
        self::mark_as_migrated();
        
        // لاگ موفقیت
        self::log_migration('موفقیت‌آمیز - اطلاعات Search Console منتقل شد', $processed_domain);
    }
    
    /**
     * پردازش دامنه و تشخیص نوع آن
     */
    private static function process_domain($domain) {
        // حذف فضاهای خالی
        $domain = trim($domain);
        
        // اگر دامنه خالی است، از آدرس سایت فعلی استفاده کن
        if (empty($domain)) {
            $site_url = get_site_url();
            $parsed_url = wp_parse_url($site_url);
            $domain = $parsed_url['scheme'] . '://' . $parsed_url['host'];
            
            return array(
                'domain' => $domain,
                'type' => 'url',
                'use_advanced' => false
            );
        }
        
        // بررسی اینکه آیا دامنه sc-domain: دارد
        if (strpos($domain, 'sc-domain:') === 0) {
            return array(
                'domain' => $domain,
                'type' => 'domain',
                'use_advanced' => true
            );
        }
        
        // بررسی اینکه آیا URL کامل است
        if (strpos($domain, 'http://') === 0 || strpos($domain, 'https://') === 0) {
            return array(
                'domain' => $domain,
                'type' => 'url',
                'use_advanced' => true
            );
        }
        
        // اگر هیچ پروتکل یا پیشوندی ندارد، احتمالاً domain property است
        // آن را به فرمت sc-domain: تبدیل می‌کنیم
        return array(
            'domain' => 'sc-domain:' . $domain,
            'type' => 'domain',
            'use_advanced' => true
        );
    }
    
    /**
     * علامت‌گذاری به عنوان migrate شده
     */
    private static function mark_as_migrated() {
        update_option('gzseo_sc_migrated_v2', true);
        update_option('gzseo_sc_migration_date', current_time('mysql'));
    }
    
    /**
     * لاگ کردن نتیجه migration
     */
    private static function log_migration($message, $additional_data = array()) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            // Debug logging disabled for WordPress.org compliance.
        }
        
        // ذخیره در دیتابیس برای مرجع
        update_option('gzseo_sc_migration_log', array(
            'message' => $message,
            'date' => current_time('mysql'),
            'additional_data' => $additional_data
        ));
    }
    
    /**
     * بررسی نیاز به migration
     */
    public static function check_migration_needed() {
        // اگر قبلاً migrate شده، نیازی نیست
        if (get_option('gzseo_sc_migrated_v2', false)) {
            return false;
        }
        
        // اگر در نسخه جدید متصل شده، نیازی نیست
        if (get_option('gzseo_sc_connected', false)) {
            self::mark_as_migrated();
            return false;
        }
        
        // بررسی وجود اطلاعات قدیمی
        $old_data = get_option('gzseo_ga_data', null);
        return ($old_data && is_array($old_data) && 
                isset($old_data['access_token']) && 
                !empty($old_data['access_token']));
    }
    
    /**
     * دریافت اطلاعات migrate شده برای نمایش
     */
    public static function get_migration_info() {
        if (!get_option('gzseo_sc_migrated_v2', false)) {
            return null;
        }
        
        $log = get_option('gzseo_sc_migration_log', null);
        $migration_date = get_option('gzseo_sc_migration_date', '');
        
        return array(
            'migrated' => true,
            'date' => $migration_date,
            'log' => $log
        );
    }
    
    /**
     * بررسی اعتبار توکن migrate شده
     */
    public static function validate_migrated_token() {
        // این متد می‌تواند برای بررسی اینکه آیا توکن migrate شده همچنان معتبر است استفاده شود
        $token_expires = get_option('gzseo_sc_token_expires', 0);
        
        if ($token_expires < time()) {
            // توکن منقضی شده
            return array(
                'valid' => false,
                'reason' => 'expired',
                'expires_at' => $token_expires
            );
        }
        
        return array(
            'valid' => true,
            'expires_at' => $token_expires
        );
    }
    
    /**
     * پاکسازی اطلاعات قدیمی (اختیاری)
     * این متد را فقط پس از اطمینان از موفقیت migration فراخوانی کنید
     */
    public static function cleanup_old_data() {
        // این متد به صورت دستی و پس از اطمینان کامل فراخوانی شود
        // delete_option('gzseo_ga_data');
        
        // برای امنیت بیشتر، ابتدا چک کنیم که migration انجام شده باشد
        if (!get_option('gzseo_sc_migrated_v2', false)) {
            return false;
        }
        
        // و اینکه اطلاعات جدید موجود باشد
        if (!get_option('gzseo_sc_connected', false)) {
            return false;
        }
        
        // حالا می‌توانیم با خیال راحت پاک کنیم
        // delete_option('gzseo_ga_data');
        
        return true;
    }
}