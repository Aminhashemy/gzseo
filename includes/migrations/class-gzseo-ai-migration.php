<?php
/**
 * کلاس مدیریت انتقال اطلاعات AI از نسخه قدیم به جدید
 * 
 * @package GZSeo
 * @since 2.0.0
 */

class GZSEO_AI_Migration {
    
    /**
     * اجرای فرآیند migration
     * این متد در زمان نصب/فعالسازی افزونه اجرا می‌شود
     */
    public static function migrate() {
        // بررسی اینکه آیا قبلاً migrate شده یا نه
        if (get_option('gzseo_ai_migrated_v2', false)) {
            return;
        }
        
        // بررسی وجود اطلاعات در نسخه جدید
        if (get_option('gzseo_ai_logged_in', false)) {
            // اگر در نسخه جدید قبلاً لاگین شده، نیازی به migration نیست
            self::mark_as_migrated();
            return;
        }
        
        // بررسی وجود اطلاعات نسخه قدیم
        $old_ai_data = get_option('gzseo_ai_data', null);
        
        if (!$old_ai_data || !is_array($old_ai_data)) {
            // اگر اطلاعات قدیمی وجود ندارد
            self::mark_as_migrated();
            return;
        }
        
        // بررسی وضعیت در نسخه قدیم
        if (isset($old_ai_data['status']) && $old_ai_data['status'] === 'empty') {
            // اگر در نسخه قدیم هم empty بوده
            self::mark_as_migrated();
            return;
        }
        
        // بررسی وجود username و password
        if (!isset($old_ai_data['username']) || !isset($old_ai_data['password'])) {
            self::mark_as_migrated();
            return;
        }
        
        // انتقال اطلاعات به فرمت جدید
        self::migrate_user_data($old_ai_data);
    }
    
    /**
     * انتقال اطلاعات کاربری به فرمت جدید
     */
    private static function migrate_user_data($old_data) {
        $username = $old_data['username'];
        $password = $old_data['password'];
        
        // بررسی اطلاعات کش شده قدیمی
        $old_status = get_transient('gzseo_ai_status');
        
        if ($old_status && is_array($old_status) && isset($old_status['status']) && $old_status['status'] === 'ok') {
            // اگر اطلاعات معتبر در transient وجود دارد، از آن استفاده کن
            self::save_new_format($username, $password, $old_status);
        } else {
            // در غیر این صورت، تلاش برای لاگین مجدد
            self::attempt_fresh_login($username, $password);
        }
    }
    
    /**
     * ذخیره اطلاعات در فرمت جدید
     */
    private static function save_new_format($email, $password, $user_data = array()) {
        // ذخیره در فرمت جدید
        update_option('gzseo_ai_email', $email);
        update_option('gzseo_ai_password', GZSEO_Security_Helpers::encrypt_credential($password));
        update_option('gzseo_ai_logged_in', true);
        update_option('gzseo_ai_last_login', current_time('mysql'));
        
        // ذخیره اطلاعات اضافی اگر موجود است
        if (isset($user_data['credit']) || isset($user_data['words'])) {
            $credits = isset($user_data['words']) ? $user_data['words'] : 
                      (isset($user_data['credit']) ? $user_data['credit'] : 0);
            update_option('gzseo_ai_credits', $credits);
        }
        
        if (!empty($user_data)) {
            // حذف فیلدهای اضافی که در فرمت قدیم بود
            unset($user_data['status']);
            unset($user_data['user_email']);
            update_option('gzseo_ai_user_data', $user_data);
        }
        
        // علامت‌گذاری به عنوان migrate شده
        self::mark_as_migrated();
        
        // لاگ موفقیت (اختیاری)
        self::log_migration('موفقیت‌آمیز - اطلاعات از کش منتقل شد');
    }
    
    /**
     * تلاش برای لاگین مجدد با اطلاعات قدیمی
     */
    private static function attempt_fresh_login($username, $password) {
        // ارسال درخواست به API
        $response = wp_remote_post('https://api.gzseo.in/login', array(
            'method' => 'POST',
            'timeout' => 30,
            'limit_response_size' => 2 * MB_IN_BYTES,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'email' => $username,
                'password' => $password
            ))
        ));
        
        if (is_wp_error($response)) {
            // در صورت خطا، فقط اطلاعات پایه را ذخیره کن
            self::save_basic_info($username, $password);
            self::log_migration('خطا در اتصال - فقط اطلاعات پایه ذخیره شد');
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 200 && isset($data['words'])) {
            // لاگین موفق
            self::save_new_format($username, $password, $data);
            self::log_migration('موفقیت‌آمیز - لاگین مجدد انجام شد');
        } else if (isset($data['message']) && $data['message'] === 'Unauthorized') {
            // اطلاعات نامعتبر - ذخیره نکن
            self::mark_as_migrated();
            self::log_migration('ناموفق - اطلاعات ورود نامعتبر');
        } else {
            // سایر خطاها - اطلاعات پایه را ذخیره کن
            self::save_basic_info($username, $password);
            self::log_migration('خطا در پاسخ API - اطلاعات پایه ذخیره شد');
        }
    }
    
    /**
     * ذخیره اطلاعات پایه (بدون اعتبارسنجی)
     */
    private static function save_basic_info($username, $password) {
        update_option('gzseo_ai_email', $username);
        update_option('gzseo_ai_password', GZSEO_Security_Helpers::encrypt_credential($password));
        update_option('gzseo_ai_logged_in', false); // به عنوان لاگین نشده علامت‌گذاری
        
        self::mark_as_migrated();
    }
    
    /**
     * علامت‌گذاری به عنوان migrate شده
     */
    private static function mark_as_migrated() {
        update_option('gzseo_ai_migrated_v2', true);
        update_option('gzseo_ai_migration_date', current_time('mysql'));
    }
    
    /**
     * لاگ کردن نتیجه migration (اختیاری)
     */
    private static function log_migration($message) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            // Debug logging disabled for WordPress.org compliance.
        }
        
        // ذخیره در دیتابیس برای مرجع
        update_option('gzseo_ai_migration_log', array(
            'message' => $message,
            'date' => current_time('mysql')
        ));
    }
    
    /**
     * بررسی نیاز به migration
     * این متد می‌تواند در هر بار لود افزونه فراخوانی شود
     */
    public static function check_migration_needed() {
        // اگر قبلاً migrate شده، نیازی نیست
        if (get_option('gzseo_ai_migrated_v2', false)) {
            return false;
        }
        
        // اگر در نسخه جدید لاگین شده، نیازی نیست
        if (get_option('gzseo_ai_logged_in', false)) {
            self::mark_as_migrated();
            return false;
        }
        
        // بررسی وجود اطلاعات قدیمی
        $old_data = get_option('gzseo_ai_data', null);
        return ($old_data && is_array($old_data) && 
                isset($old_data['username']) && 
                isset($old_data['password']));
    }
    
    /**
     * پاکسازی اطلاعات قدیمی (اختیاری)
     * این متد را فقط پس از اطمینان از موفقیت migration فراخوانی کنید
     */
    public static function cleanup_old_data() {
        // این متد به صورت دستی و پس از اطمینان کامل فراخوانی شود
        // delete_option('gzseo_ai_data');
        // delete_transient('gzseo_ai_status');
    }
}
