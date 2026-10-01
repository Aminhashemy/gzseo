<?php
/**
 * GZSEO Bulk Execute Handler
 * 
 * @package GZ_SEO
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class GZSEO_Bulk_Execute
{

    /**
     * Execute bulk update for table generation
     */
    public static function execute_table_update($post_id, $is_bulk = false)
    {
        // بررسی وضعیت ورود به هوش مصنوعی
        if (!get_option('gzseo_ai_logged_in', false)) {
            return array(
                'success' => false,
                'message' => esc_html__( 'لطفا ابتدا وارد حساب هوش مصنوعی شوید', 'gzseo' )
            );
        }

        // دریافت اطلاعات احراز هویت
        $credentials = array(
            'email' => get_option('gzseo_ai_email', ''),
            'password' => GZSEO_Security_Helpers::get_ai_password()
        );

        // دریافت محتوای پست
        $post = get_post($post_id);
        if (!$post) {
            return array(
                'success' => false,
                'message' => esc_html__( 'پست یافت نشد', 'gzseo' )
            );
        }

        $content = wp_strip_all_tags($post->post_content);

        // آماده‌سازی داده‌ها برای API
        $api_data = array(
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'content' => $content,
            'ai_model' => 'standard'
        );

        // ارسال درخواست به API
        $response = wp_remote_post('https://api.gzseo.in/createtable', array(
            'method' => 'POST',
            'timeout' => 60,
            'limit_response_size' => 2 * MB_IN_BYTES,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($api_data)
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                    'message' => esc_html__( 'خطا در اتصال به سرویس هوش مصنوعی', 'gzseo' )
            );
        }

        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            return array(
                'success' => false,
                'message' => esc_html__( 'خطا در دریافت پاسخ از سرور', 'gzseo' )
            );
        }

        // پردازش جدول
        $table_html = self::parse_table_from_string($body);

        if (empty($table_html)) {
            return array(
                'success' => false,
                'message' => esc_html__( 'خطا در پردازش جدول', 'gzseo' )
            );
        }

        // دریافت استایل جدول
        $table_style = get_option('gzseo_table_style', '1');
        $use_custom_style = get_option('gzseo_use_custom_table_style', true);

        // ایجاد ID یکتا برای جدول
        $table_id = 'gzseo_table_bulk_' . wp_generate_uuid4();

        // اعمال استایل به جدول
        if ($use_custom_style) {
            $table_html = self::apply_table_style($table_html, $table_style, $table_id);
        }

        // ذخیره جدول
        $existing_tables = get_post_meta($post_id, '_gzseo_tables', true);
        if (!is_array($existing_tables)) {
            $existing_tables = array();
        }

        // دریافت تنظیمات استایل جدول
        $table_styles_settings = get_option('gzseo_table_styles', array());
        $table_position = isset($table_styles_settings['table_position']) ? $table_styles_settings['table_position'] : 'after_content';

        // اضافه کردن جدول جدید
        $new_table = array(
            'id' => $table_id,
            'html' => $table_html,
            'position' => $table_position,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'is_bulk' => true // نشان‌دهنده اینکه با آپدیت دسته جمعی ساخته شده
        );

        $existing_tables[] = $new_table;

        // ذخیره در دیتابیس
        update_post_meta($post_id, '_gzseo_tables', $existing_tables);

        // بروزرسانی تاریخ آخرین ویرایش پست
        wp_update_post(array(
            'ID' => $post_id,
            'post_modified' => current_time('mysql'),
            'post_modified_gmt' => current_time('mysql', 1)
        ));

        // ذخیره متادیتا برای ردیابی
        update_post_meta($post_id, '_gzseo_bulk_table_updated', current_time('mysql'));

        return array(
            'success' => true,
            'message' => esc_html__( 'جدول با موفقیت ایجاد شد', 'gzseo' ),
            'table_id' => $table_id,
            'post_title' => get_the_title($post_id)
        );
    }

    /**
     * Parse table from string response
     */
    /*private static function parse_table_from_string($response_string)
    {
        // حذف تگ های br
        $response_string = str_replace('<br>', '', $response_string);
        $response_string = str_replace('<br/>', '', $response_string);
        $response_string = str_replace('<br />', '', $response_string);

        // تجزیه سطرها
        $rows = array();
        preg_match_all('/\[startrow\](.*?)\[ENDROW\]/i', $response_string, $row_matches);

        if (empty($row_matches[1])) {
            return '';
        }

        $table_html = '<table class="gzseo-generated-table">';
        $table_html .= '<tbody>';

        foreach ($row_matches[1] as $row_index => $row_content) {
            // تجزیه ستون‌ها
            preg_match_all('/\[startcol\](.*?)\[ENDCOL\]/i', $row_content, $col_matches);

            if (!empty($col_matches[1])) {
                $table_html .= '<tr>';

                foreach ($col_matches[1] as $col_index => $col_content) {
                    // اولین ستون از هر سطر را به عنوان th در نظر بگیر
                    if ($col_index === 0) {
                        $table_html .= '<th>' . trim($col_content) . '</th>';
                    } else {
                        $table_html .= '<td>' . trim($col_content) . '</td>';
                    }
                }

                $table_html .= '</tr>';
            }
        }

        $table_html .= '</tbody>';
        $table_html .= '</table>';

        return $table_html;
    }*/
    
    private static function parse_table_from_string($response_string) {
        // حذف تگ های br
        $response_string = str_replace('<br>', '', $response_string);
        $response_string = str_replace('<br/>', '', $response_string);
        $response_string = str_replace('<br />', '', $response_string);
        
        // تجزیه سطرها
        $rows = array();
        preg_match_all('/\[startrow\](.*?)\[ENDROW\]/i', $response_string, $row_matches);
        
        if (empty($row_matches[1])) {
            return '';
        }
        
        $table_html = '<table class="gzseo-generated-table">';
        $table_html .= '<tbody>';
        
        foreach ($row_matches[1] as $row_index => $row_content) {
            // تجزیه ستون‌ها
            preg_match_all('/\[startcol\](.*?)\[ENDCOL\]/i', $row_content, $col_matches);
            
            if (!empty($col_matches[1])) {
                $table_html .= '<tr>';
                
                // دکمه حذف سطر در ابتدا
                $table_html .= '<td class="gzseo-table-actions gzseo-row-actions"><button class="gzseo-remove-row" title="' . esc_attr__( 'حذف سطر', 'gzseo' ) . '"><i class="fas fa-times"></i></button></td>';
                
                foreach ($col_matches[1] as $col_index => $col_content) {
                    // اولین ستون از هر سطر را به عنوان th در نظر بگیر
                    if ($col_index === 0) {
                        $table_html .= '<th contenteditable="true" data-row="' . $row_index . '" data-col="' . $col_index . '">' . trim($col_content) . '</th>';
                    } else {
                        $table_html .= '<td contenteditable="true" data-row="' . $row_index . '" data-col="' . $col_index . '">' . trim($col_content) . '</td>';
                    }
                }
                
                $table_html .= '</tr>';
            }
        }
        
        // سطر دکمه‌های حذف ستون
        $table_html .= '<tr class="gzseo-col-actions-row">';
        $table_html .= '<td class="gzseo-table-actions"></td>'; // سلول خالی برای دکمه حذف سطر
        
        // تعداد ستون‌ها را محاسبه کنید
        if (!empty($col_matches[1])) {
            foreach ($col_matches[1] as $col_index => $col_content) {
                $table_html .= '<td class="gzseo-table-actions gzseo-col-actions"><button class="gzseo-remove-col" data-col="' . $col_index . '" title="' . esc_attr__( 'حذف ستون', 'gzseo' ) . '"><i class="fas fa-times"></i></button></td>';
            }
        }
        $table_html .= '</tr>';
        
        $table_html .= '</tbody>';
        $table_html .= '</table>';
        
        return $table_html;
    }

    /**
     * Apply table style
     */
    private static function apply_table_style($table_html, $style, $table_id)
    {
        // افزودن کلاس استایل و ID به جدول
        $table_html = str_replace(
            'class="gzseo-generated-table"',
            'id="' . $table_id . '" class="gzseo-generated-table gzseo-table-style-' . $style . ' gzseo-bulk-generated" data-table-id="' . $table_id . '"',
            $table_html
        );

        return $table_html;
    }

    /**
     * Execute comment generation
     */
    public static function execute_comment_update($post_id, $is_bulk = false) {
        // بررسی وضعیت ورود به هوش مصنوعی
        if (!get_option('gzseo_ai_logged_in', false)) {
            return array(
                'success' => false,
                'message' => esc_html__( 'لطفا ابتدا وارد حساب هوش مصنوعی شوید', 'gzseo' )
            );
        }
        
        // دریافت اطلاعات احراز هویت
        $credentials = array(
            'email' => get_option('gzseo_ai_email', ''),
            'password' => GZSEO_Security_Helpers::get_ai_password()
        );
        
        if (!$credentials['email'] || !$credentials['password']) {
            return array(
                'success' => false,
                'message' => esc_html__( 'اطلاعات احراز هویت یافت نشد', 'gzseo' )
            );
        }
        
        // دریافت اطلاعات پست
        $post = get_post($post_id);
        if (!$post) {
            return array(
                'success' => false,
                'message' => esc_html__( 'پست یافت نشد', 'gzseo' )
            );
        }
        
        // تنظیمات پیش‌فرض برای آپدیت دسته جمعی
        $title = $post->post_title;
        $content = wp_strip_all_tags($post->post_content);
        $keyword = ''; // در آپدیت دسته جمعی از کلمه کلیدی استفاده نمی‌شود
        $ai_model = 'standard';
        
        // دریافت تنظیمات از دیتابیس
        $tone = get_option('gzseo_comment_tone', 'تجربه مثبت');
        $word_count = get_option('gzseo_comment_word_count', 50);

        // اطمینان از اینکه تعداد کلمات عددی معتبر است
        $word_count = intval($word_count) > 0 ? intval($word_count) : 50;

        $comment_count = 10;
        $use_random_date = false;
        
        // آماده‌سازی داده‌ها برای API
        $api_data = array(
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'Title' => $title,
            'content' => $content,
            'keyword' => $keyword,
            'ai_model' => $ai_model,
            'Tone_of_Content' => $tone,
            'Word_Count' => $word_count
        );
        
        // ارسال درخواست به API
        $response = wp_remote_post('https://api.gzseo.in/createadvancedcomment', array(
            'method' => 'POST',
            'timeout' => 300,
            'limit_response_size' => 2 * MB_IN_BYTES,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($api_data)
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                    'message' => esc_html__( 'خطا در اتصال به سرویس هوش مصنوعی', 'gzseo' )
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            return array(
                'success' => false,
                /* translators: %s: Status code. */
                'message' => sprintf(esc_html__( 'خطا در دریافت پاسخ از سرور (Status: %s)', 'gzseo' ), $status_code)
            );
        }
        
        // پردازش کامنت‌ها از پاسخ
        $comments = self::parse_comments_from_string($body);
        
        if (empty($comments)) {
            return array(
                'success' => false,
                'message' => esc_html__( 'هیچ کامنتی در پاسخ دریافتی یافت نشد', 'gzseo' )
            );
        }
        
        // محدود کردن به 10 کامنت (در صورتی که بیشتر تولید شده باشد)
        $comments = array_slice($comments, 0, $comment_count);
        
        // ذخیره کامنت‌ها
        $saved_count = 0;
        $names_generator = new GZSEO_Comment_Names();
        
        foreach ($comments as $index => $comment_text) {
            // تولید اطلاعات نویسنده
            $author_data = $names_generator->get_random_name();
            
            // تاریخ کامنت
            $comment_date = $use_random_date ? self::get_random_date() : current_time('mysql');
            
            // درج کامنت
            $comment_id = wp_insert_comment(array(
                'comment_post_ID' => $post_id,
                'comment_author' => $author_data['full_name'],
                'comment_author_email' => self::generate_fake_email($author_data['first_name'], $author_data['last_name']),
                'comment_content' => trim($comment_text),
                'comment_date' => $comment_date,
                'comment_date_gmt' => get_gmt_from_date($comment_date),
                'comment_approved' => 1, // انتشار فوری
                'comment_type' => 'comment'
            ));
            
            if ($comment_id) {
                // ذخیره متادیتا
                update_comment_meta($comment_id, '_gzseo_generated', true);
                update_comment_meta($comment_id, '_gzseo_bulk_generated', true); // نشانگر bulk
                update_comment_meta($comment_id, '_gzseo_gender', $author_data['gender']);
                update_comment_meta($comment_id, '_gzseo_bulk_settings', array(
                    'tone' => $tone,
                    'word_count' => $word_count,
                    'generated_at' => current_time('mysql')
                ));
                
                $saved_count++;
            }
        }
        
        // ذخیره متادیتا در پست
        update_post_meta($post_id, '_gzseo_bulk_comments_generated', true);
        update_post_meta($post_id, '_gzseo_bulk_comments_count', $saved_count);
        update_post_meta($post_id, '_gzseo_bulk_comments_date', current_time('mysql'));
        
        return array(
            'success' => true,
            
            'message' => sprintf(
                /* translators: %d: Number of comments. */
                esc_html( _n( '%d کامنت با موفقیت تولید و منتشر شد', '%d کامنت با موفقیت تولید و منتشر شد', $saved_count, 'gzseo' ) ),
                $saved_count
            ),
            'comments_count' => $saved_count,
            'post_title' => $title
        );
    }
    
    /**
     * Parse comments from string response
     * (کپی از کلاس اصلی برای استفاده در bulk)
     */
    private static function parse_comments_from_string($response_string) {
        $comments = array();
        
        // استفاده از regex برای استخراج متن بین [start] و [end]
        preg_match_all('/\[start\](.*?)\[end\]/s', $response_string, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $comment) {
                $comment = trim($comment);
                if (!empty($comment)) {
                    $comments[] = $comment;
                }
            }
        }
        
        return $comments;
    }
    
    /**
     * Generate fake email
     * (کپی از کلاس اصلی برای استفاده در bulk)
     */
    private static function generate_fake_email($first_name, $last_name) {
        $seed = sanitize_key(remove_accents($first_name . '-' . $last_name));
        if ('' === $seed) {
            $seed = 'commenter';
        }
        return substr($seed, 0, 40) . '-' . wp_rand(1000, 9999) . '@example.invalid';
    }
    
    /**
     * Get random date in last 30 days
     * (کپی از کلاس اصلی برای استفاده در bulk)
     */
    private static function get_random_date() {
        $days_ago = wp_rand(1, 30);
        return gmdate('Y-m-d H:i:s', strtotime("-{$days_ago} days"));
    }

    /**
     * Execute content update
     */
    public static function execute_content_update($post_id, $is_bulk = false)
    {
        // بررسی وضعیت ورود به هوش مصنوعی
        if (!get_option('gzseo_ai_logged_in', false)) {
            return array(
                'success' => false,
                'message' => esc_html__( 'لطفا ابتدا وارد حساب هوش مصنوعی شوید', 'gzseo' )
            );
        }

        // دریافت اطلاعات احراز هویت
        $email = get_option('gzseo_ai_email', '');
        $password = GZSEO_Security_Helpers::get_ai_password();

        if (!$email || !$password) {
            return array(
                'success' => false,
                'message' => esc_html__( 'اطلاعات احراز هویت یافت نشد', 'gzseo' )
            );
        }

        // دریافت اطلاعات پست
        $post = get_post($post_id);
        if (!$post) {
            return array(
                'success' => false,
                'message' => esc_html__( 'پست یافت نشد', 'gzseo' )
            );
        }

        // تنظیمات پیش‌فرض برای آپدیت دسته جمعی
        $paragraph_count = 3;
        $min_words = 200;
        $max_words = 350;

        // آماده‌سازی داده‌ها برای API
        $api_data = array(
            'email' => $email,
            'password' => $password,
            'title' => $post->post_title,
            'content' => wp_strip_all_tags($post->post_content),
            'paragraphCount' => $paragraph_count,
            'minLength' => $min_words,
            'maxLength' => $max_words
        );

        // ارسال درخواست به API
        $response = wp_remote_post('https://api.gzseo.in/createadvanceupdatecontent', array(
            'timeout' => 60,
            'limit_response_size' => 2 * MB_IN_BYTES,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($api_data)
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                    'message' => esc_html__( 'خطا در اتصال به سرویس هوش مصنوعی', 'gzseo' )
            );
        }

        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            return array(
                'success' => false,
                /* translators: %s: Status code. */
                'message' => sprintf(esc_html__( 'خطا در دریافت پاسخ از سرور (Status: %s)', 'gzseo' ), $status_code)
            );
        }

        // پاکسازی محتوا - فقط تگ‌های مجاز
        $allowed_tags = array(
            'p' => array(),
            'strong' => array(),
            'em' => array(),
            'b' => array()
        );

        $clean_content = wp_kses($body, $allowed_tags);

        if (empty($clean_content)) {
            return array(
                'success' => false,
                'message' => esc_html__( 'محتوای دریافتی خالی است', 'gzseo' )
            );
        }
        
        if (strlen($clean_content) < 50) {
            return array(
                'success' => false,
                'message' => esc_html__( 'پاسخی یافت نشد، داده محتوا کافی نیست.', 'gzseo' )
            );
        }

        // محتوا در post meta ذخیره می‌شود (دیگر نیازی به جدول جداگانه نیست)

        // دریافت تنظیمات موقعیت و آکاردئون
        $text_position = get_option('gzseo_text_position', 'after_content');
        $accordion_display = get_option('gzseo_accordion_display', 0);
        $accordion_title = get_option('gzseo_accordion_title', 'مشاهده بیشتر');

        // دریافت محتواهای قبلی (اگر وجود داشته باشد)
        $existing_contents = get_post_meta($post_id, '_gzseo_content_updates', true);
        if (!is_array($existing_contents)) {
            $existing_contents = array();
        }

        // ایجاد ID یکتا برای این محتوا
        $content_id = 'gzseo_content_' . wp_generate_uuid4();

        // آماده‌سازی داده‌های جدید
        $new_content = array(
            'id' => $content_id,
            'content' => $clean_content,
            'position' => $text_position,
            'is_active' => 1,
            'is_bulk' => 1,
            'accordion_display' => $accordion_display,
            'accordion_title' => $accordion_title,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        // اضافه کردن محتوای جدید به آرایه
        $existing_contents[] = $new_content;

        // ذخیره در post meta
        $result = update_post_meta($post_id, '_gzseo_content_updates', $existing_contents);

        if ($result === false) {
            return array(
                'success' => false,
                'message' => esc_html__( 'خطا در ذخیره محتوا در دیتابیس', 'gzseo' )
            );
        }

        // بروزرسانی تاریخ آخرین ویرایش پست
        wp_update_post(array(
            'ID' => $post_id,
            'post_modified' => current_time('mysql'),
            'post_modified_gmt' => current_time('mysql', 1)
        ));

        // ذخیره متادیتا برای ردیابی
        update_post_meta($post_id, '_gzseo_bulk_content_updated', current_time('mysql'));

        return array(
            'success' => true,
            'message' => esc_html__( 'محتوا با موفقیت تولید و ذخیره شد', 'gzseo' ),
            'content_id' => $content_id,
            'post_title' => get_the_title($post_id)
        );
    }

    /**
     * Create content updates table
     * 
     * @deprecated This method is deprecated. Content is now stored in post meta.
     *             Kept for backward compatibility only.
     *             Should only be called from plugin activation hook if needed.
     */
    private static function create_content_table()
    {
        // This method is deprecated and should not be called during runtime.
        // Content is now stored using update_post_meta() instead of custom tables.
        // If you need to create the table, call this only from register_activation_hook.
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'gzseo_content_updates';

        // Table name is derived from wpdb prefix + static suffix; escape for safe SQL usage.
        $safe_table_name = esc_sql( $table_name );

        $charset_collate = $wpdb->get_charset_collate();

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dbDelta requires a raw CREATE TABLE statement.
        $sql = "CREATE TABLE IF NOT EXISTS `{$safe_table_name}` (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            content longtext NOT NULL,
            position varchar(50) NOT NULL DEFAULT 'after_content',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            is_bulk tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY is_bulk (is_bulk)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Execute video update
     */
     public static function execute_video_update($post_id, $is_bulk = false) {
        // بررسی وضعیت ورود به هوش مصنوعی
        if (!get_option('gzseo_ai_logged_in', false)) {
            return array(
                'success' => false,
                'message' => esc_html__( 'لطفا ابتدا وارد حساب هوش مصنوعی شوید', 'gzseo' )
            );
        }
        
        // دریافت اطلاعات احراز هویت
        $email = get_option('gzseo_ai_email', '');
        $password = GZSEO_Security_Helpers::get_ai_password();
        
        if (empty($email) || empty($password)) {
            return array(
                'success' => false,
                'message' => esc_html__( 'اطلاعات احراز هویت یافت نشد', 'gzseo' )
            );
        }
        
        // دریافت عنوان پست به عنوان keyword
        $post_title = get_the_title($post_id);
        if (empty($post_title)) {
            return array(
                'success' => false,
                'message' => esc_html__( 'عنوان پست یافت نشد', 'gzseo' )
            );
        }
        
        // فراخوانی API برای جستجوی ویدیو
        $response = wp_remote_post('https://api.gzseo.in/getserpervideo', array(
            'method' => 'POST',
            'timeout' => 30,
            'limit_response_size' => 2 * MB_IN_BYTES,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'email' => $email,
                'password' => $password,
                'keyword' => $post_title
            )
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                    'message' => esc_html__( 'خطا در اتصال به سرویس هوش مصنوعی', 'gzseo' )
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'message' => esc_html__( 'خطا در پردازش پاسخ سرور', 'gzseo' )
            );
        }
        
        // پردازش ویدیوها - استفاده از کلاس GZSEO_Video_Update اگر وجود دارد
        $processed_videos = array();
        
        if (class_exists('GZSEO_Video_Update')) {
            // استفاده از کلاس موجود
            $video_update_instance = new GZSEO_Video_Update();
            $processed_videos = $video_update_instance->process_video_results($data);
        } else {
            // پردازش محلی اگر کلاس موجود نیست
            $processed_videos = self::process_video_results_local($data);
        }
        
        if (empty($processed_videos)) {
            return array(
                'success' => false,
                'message' => esc_html__( 'هیچ ویدیویی یافت نشد', 'gzseo' )
            );
        }
        
        // محدود کردن به 3 ویدیو برای آپدیت دسته جمعی
        $processed_videos = array_slice($processed_videos, 0, 3);
        
        // دریافت ویدیوهای موجود
        $existing_videos = get_post_meta($post_id, '_gzseo_video_updates', true);
        if (!is_array($existing_videos)) {
            $existing_videos = array();
        }
        
        // حفظ ویدیوهای دستی (غیر bulk) قبلی
        $non_bulk_videos = array();
        foreach ($existing_videos as $video) {
            if (!isset($video['bulk_generated']) || $video['bulk_generated'] !== true) {
                $non_bulk_videos[] = $video;
            }
        }
        
        // دریافت موقعیت نمایش
        $saved_position = get_post_meta($post_id, '_gzseo_video_position', true);
        $default_position = !empty($saved_position) ? $saved_position : get_option('gzseo_video_position', 'after_content');
        
        // اضافه کردن ویژگی‌های لازم به ویدیوهای جدید
        foreach ($processed_videos as &$video) {
            $video['is_active'] = false; // غیرفعال به صورت پیش‌فرض
            $video['bulk_generated'] = true; // نشانگر تولید با bulk
            $video['created_date'] = current_time('mysql');
        }
        unset($video);
        
        // ترکیب ویدیوهای دستی قبلی با ویدیوهای bulk جدید
        $all_videos = array_merge($non_bulk_videos, $processed_videos);
        
        // ذخیره همه ویدیوها
        update_post_meta($post_id, '_gzseo_video_updates', $all_videos);
        update_post_meta($post_id, '_gzseo_video_position', $default_position);
        update_post_meta($post_id, '_gzseo_video_update_date', current_time('mysql'));
        update_post_meta($post_id, '_gzseo_bulk_video_generated', true);
        update_post_meta($post_id, '_gzseo_bulk_video_count', count($processed_videos));
        
        // آماده‌سازی پیام
        
        $message = sprintf(
            /* translators: %d: Number of videos added. */
            esc_html( _n( '%d ویدیو جدید از طریق آپدیت دسته جمعی اضافه شد', '%d ویدیو جدید از طریق آپدیت دسته جمعی اضافه شد', count($processed_videos), 'gzseo' ) ),
            count($processed_videos)
        );
        
        if (count($non_bulk_videos) > 0) {
            
            $message .= sprintf(
                /* translators: %d: Number of existing manual videos. */
                esc_html( _n( ' (همراه با %d ویدیو دستی قبلی)', ' (همراه با %d ویدیو دستی قبلی)', count($non_bulk_videos), 'gzseo' ) ),
                count($non_bulk_videos)
            );
        }
        
        return array(
            'success' => true,
            'message' => $message,
            'videos_count' => count($processed_videos),
            'total_videos' => count($all_videos),
            'preserved_videos' => count($non_bulk_videos),
            'post_title' => $post_title
        );
    }

    /**
    * Process video results locally
    */
    private static function process_video_results_local($videos) {
        $processed = array();
        
        if (!is_array($videos)) {
            return $processed;
        }
        
        foreach ($videos as $video) {
            if (empty($video['url']) || empty($video['platform'])) {
                continue;
            }
            
            $video_data = array(
                'url' => $video['url'],
                'platform' => $video['platform'],
                'embed_code' => self::generate_embed_code_local($video['url'], $video['platform']),
                'video_id' => self::extract_video_id_local($video['url'], $video['platform'])
            );
            
            if (!empty($video_data['embed_code'])) {
                $processed[] = $video_data;
            }
        }
        
        return $processed;
    }

    /**
    * Generate embed code based on platform
    */
    private static function generate_embed_code_local($url, $platform) {
        switch (strtolower($platform)) {
            case 'youtube':
                return self::generate_youtube_embed_local($url);
            case 'aparat':
                return self::generate_aparat_embed_local($url);
            case 'namasha':
                return self::generate_namasha_embed_local($url);
            case 'tamasha':
                return self::generate_tamasha_embed_local($url);
            case 'ifilo':
                return self::generate_ifilo_embed_local($url);
            default:
                return '';
        }
    }

    /**
    * Generate YouTube embed code
    */
    private static function generate_youtube_embed_local($url) {
        $video_id = self::extract_youtube_id_local($url);
        if (!$video_id) {
            return '';
        }
        
        return sprintf(
            '<iframe width="560" height="315" src="https://www.youtube.com/embed/%s" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>',
            esc_attr($video_id)
        );
    }

    /**
    * Extract YouTube video ID
    */
    private static function extract_youtube_id_local($url) {
        $patterns = array(
            '/youtube\.com\/watch\?v=([^\&\?\/]+)/',
            '/youtube\.com\/embed\/([^\&\?\/]+)/',
            '/youtube\.com\/v\/([^\&\?\/]+)/',
            '/youtu\.be\/([^\&\?\/]+)/',
            '/youtube\.com\/verify_age\?next_url=\/watch%3Fv%3D([^\&\?\/]+)/',
            '/youtube\.com\/@[^\/]+\/([^\&\?\/]+)/'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }
        
        return false;
    }

    /**
    * Generate other platform embeds
    */
    private static function generate_aparat_embed_local($url) {
        if (preg_match('/aparat\.com\/v\/([^\/?]+)/', $url, $matches)) {
            $video_id = $matches[1];
            return sprintf(
                '<iframe src="https://www.aparat.com/video/video/embed/videohash/%s/vt/frame" width="560" height="315" frameborder="0" allowfullscreen="true"></iframe>',
                esc_attr($video_id)
            );
        }
        return '';
    }

    private static function generate_namasha_embed_local($url) {
        if (preg_match('/namasha\.com\/v\/([^\/?]+)/', $url, $matches)) {
            $video_id = $matches[1];
            return sprintf(
                '<iframe src="https://www.namasha.com/embed/%s" width="560" height="315" frameborder="0" allowfullscreen="true"></iframe>',
                esc_attr($video_id)
            );
        }
        return '';
    }

    private static function generate_tamasha_embed_local($url) {
        if (preg_match('/tamasha\.com\/v\/([^\/?]+)/', $url, $matches)) {
            $video_id = $matches[1];
            return sprintf(
                '<iframe src="https://www.tamasha.com/embed/%s" width="560" height="315" frameborder="0" allowfullscreen="true"></iframe>',
                esc_attr($video_id)
            );
        }
        return '';
    }

    private static function generate_ifilo_embed_local($url) {
        if (preg_match('/ifilo\.net\/v\/([^\/?]+)/', $url, $matches)) {
            $video_id = $matches[1];
            return sprintf(
                '<iframe src="https://www.ifilo.net/embed/%s" width="560" height="315" frameborder="0" allowfullscreen="true"></iframe>',
                esc_attr($video_id)
            );
        }
        return '';
    }

    /**
    * Extract video ID
    */
    private static function extract_video_id_local($url, $platform) {
        switch (strtolower($platform)) {
            case 'youtube':
                return self::extract_youtube_id_local($url);
            case 'aparat':
                if (preg_match('/aparat\.com\/v\/([^\/?]+)/', $url, $matches)) {
                    return $matches[1];
                }
                break;
            case 'namasha':
                if (preg_match('/namasha\.com\/v\/([^\/?]+)/', $url, $matches)) {
                    return $matches[1];
                }
                break;
            case 'tamasha':
                if (preg_match('/tamasha\.com\/v\/([^\/?]+)/', $url, $matches)) {
                    return $matches[1];
                }
                break;
            case 'ifilo':
                if (preg_match('/ifilo\.net\/v\/([^\/?]+)/', $url, $matches)) {
                    return $matches[1];
                }
                break;
        }
        return uniqid();
    }

    /**
     * Execute featured image update
     */
    public static function execute_featured_image_update($post_id, $is_bulk = false) {
        // بررسی وضعیت ورود به هوش مصنوعی
        if (!get_option('gzseo_ai_logged_in', false)) {
            return array(
                'success' => false,
                'message' => esc_html__( 'لطفا ابتدا وارد حساب هوش مصنوعی شوید', 'gzseo' )
            );
        }
        
        // دریافت اطلاعات احراز هویت
        $credentials = array(
            'email' => get_option('gzseo_ai_email', ''),
            'password' => GZSEO_Security_Helpers::get_ai_password()
        );
        
        if (!$credentials['email'] || !$credentials['password']) {
            return array(
                'success' => false,
                'message' => esc_html__( 'اطلاعات احراز هویت یافت نشد', 'gzseo' )
            );
        }
        
        // دریافت عنوان پست به عنوان prompt
        $post_title = get_the_title($post_id);
        if (empty($post_title)) {
            return array(
                'success' => false,
                'message' => esc_html__( 'عنوان پست یافت نشد', 'gzseo' )
            );
        }
        
        // دریافت ابعاد از تنظیمات ذخیره شده
        $width = get_option('gzseo_featured_image_default_width', 1024);
        $height = get_option('gzseo_featured_image_default_height', 1024);

        // اطمینان از اینکه مقادیر عددی هستند
        $width = intval($width) > 0 ? intval($width) : 1024;
        $height = intval($height) > 0 ? intval($height) : 1024;
        
        // آماده‌سازی داده‌ها برای API
        $api_data = array(
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'prompt' => $post_title,
            'width' => $width,
            'height' => $height
        );
        
        // ارسال درخواست به API
        $response = wp_remote_post('https://app.gzseo.in/api/generateimage', array(
            'method' => 'POST',
            'timeout' => 300,
            'limit_response_size' => 64 * KB_IN_BYTES,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($api_data)
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                    'message' => esc_html__( 'خطا در اتصال به سرویس هوش مصنوعی', 'gzseo' )
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $body = utf8_decode(urldecode($body));
        $status_code = wp_remote_retrieve_response_code($response);
        
        // بررسی و استخراج URL تصویر
        if (isset($body) && !empty($body)) {
            $image_url = $body;
        } else {
            return array(
                'success' => false,
                'message' => esc_html__( 'آدرس تصویر دریافت نشد', 'gzseo' )
            );
        }
        
        // دانلود و ذخیره تصویر در کتابخانه رسانه
        $upload_result = self::download_image_to_media($image_url, $post_id, true);
        
        if (is_wp_error($upload_result)) {
            return array(
                'success' => false,
                'message' => esc_html__( 'خطا در ذخیره تصویر تولیدشده', 'gzseo' )
            );
        }
        
        // ذخیره تصویر شاخص فعلی به عنوان backup
        $current_thumbnail_id = get_post_thumbnail_id($post_id);
        if ($current_thumbnail_id) {
            update_post_meta($post_id, '_gzseo_previous_thumbnail_id', $current_thumbnail_id);
        }
        
        // تنظیم تصویر جدید به عنوان تصویر شاخص
        $result = set_post_thumbnail($post_id, $upload_result);
        
        if (!$result) {
            return array(
                'success' => false,
                'message' => esc_html__( 'خطا در تنظیم تصویر شاخص', 'gzseo' )
            );
        }
        
        // ذخیره متادیتا برای نشان دادن اینکه این تصویر با bulk ساخته شده
        update_post_meta($upload_result, '_gzseo_bulk_generated', true);
        update_post_meta($post_id, '_gzseo_bulk_featured_image', true);
        update_post_meta($post_id, '_gzseo_bulk_featured_image_date', current_time('mysql'));
        
        return array(
            'success' => true,
            'message' => esc_html__( 'تصویر شاخص با موفقیت تولید و تنظیم شد', 'gzseo' ),
            'image_id' => $upload_result,
            'image_url' => wp_get_attachment_url($upload_result),
            'post_title' => $post_title
        );
    }
    
    /**
     * Download image from URL and save to media library
     * Modified version for bulk operations
     */
    private static function download_image_to_media($image_url, $post_id, $is_bulk = false) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        if (!wp_http_validate_url($image_url)) {
            return new WP_Error('invalid_image_url', __('آدرس تصویر معتبر نیست', 'gzseo'));
        }

        // download_url uses the safe WordPress HTTP transport and streams to disk.
        $tmp = download_url($image_url, 30);
        
        if (is_wp_error($tmp)) {
            return $tmp;
        }

        if (filesize($tmp) > 10 * MB_IN_BYTES) {
            wp_delete_file($tmp);
            return new WP_Error('image_too_large', __('حجم تصویر بیشتر از حد مجاز است', 'gzseo'));
        }

        $image_info = @getimagesize($tmp); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        if (!$image_info || empty($image_info['mime']) || 0 !== strpos($image_info['mime'], 'image/')) {
            wp_delete_file($tmp);
            return new WP_Error('invalid_image_data', __('فایل دریافت‌شده تصویر معتبر نیست', 'gzseo'));
        }
        
        // Get file info
        $prefix = $is_bulk ? 'gzseo-ai-bulk-featured-' : 'gzseo-ai-featured-';
        $file_array = array(
            'name' => $prefix . $post_id . '-' . gmdate('YmdHis') . '.' . ('image/png' === $image_info['mime'] ? 'png' : ('image/webp' === $image_info['mime'] ? 'webp' : 'jpg')),
            'tmp_name' => $tmp
        );
        
        // Upload to media library
        $description = $is_bulk ? 
            esc_html__( 'تصویر شاخص تولید شده با هوش مصنوعی (آپدیت دسته جمعی)', 'gzseo' ) : 
            esc_html__( 'تصویر شاخص تولید شده توسط هوش مصنوعی', 'gzseo' );
            
        $attachment_id = media_handle_sideload($file_array, $post_id, $description);
        
        // Delete temp file
        wp_delete_file($tmp);
        
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }
        
        return $attachment_id;
    }

    /**
     * Update content table structure
     * 
     * @deprecated This method performs schema changes and should only be called from
     *             plugin activation/upgrade hooks, never during normal runtime.
     *             Content is now stored in post meta, making this table obsolete.
     * 
     * Note: This method is kept for backward compatibility with existing installations
     *       that may still have the custom table. New installations should not use it.
     */
    public static function update_content_table_structure() {
        global $wpdb;
        
        // Validate and construct table name from known safe components
        $table_suffix = 'gzseo_content_updates';
        
        // Validate suffix matches expected pattern (alphanumeric and underscore only)
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table_suffix)) {
            return; // Invalid table suffix, abort safely
        }
        
        // Construct full table name from validated prefix + validated suffix
        $table_name = $wpdb->prefix . $table_suffix;
        
        // Final validation: ensure complete table name is safe
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table_name)) {
            return; // Invalid table name, abort safely
        }
        
        // بررسی وجود جدول
        // Cache key for table existence check - safe to cache indefinitely as table structure rarely changes
        $cache_key_table = 'gzseo_table_exists_' . md5($table_name);
        $cache_group = 'gzseo';
        $table_exists = wp_cache_get($cache_key_table, $cache_group);
        
        if (false === $table_exists) {
            // Use wpdb->prepare() for the LIKE comparison value.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Legacy table existence check; prepared and cached.
            $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) === $table_name;
            // Cache indefinitely - table existence doesn't change often
            wp_cache_set($cache_key_table, $table_exists, $cache_group, 0);
        }
        
        if (!$table_exists) {
            return;
        }
        
        // Schema updates for legacy table (safe, idempotent).
        // This table is deprecated; however, existing installations may still have it.
        // We only attempt to reconcile missing columns when running in admin and by an authorized user.
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $required_columns = array( 'is_bulk', 'accordion_display', 'accordion_title' );

        // Cache group for schema checks.
        $cache_group = 'gzseo';
        $needs_update = false;

        foreach ( $required_columns as $col_name ) {
            $cache_key_col = 'gzseo_col_exists_' . md5( $table_name . '|' . $col_name );
            $col_exists    = wp_cache_get( $cache_key_col, $cache_group );

            if ( false === $col_exists ) {
                // Check column existence via INFORMATION_SCHEMA using prepared values (no identifier interpolation).
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Legacy upgrade check; prepared and cached.
                $col_exists = (bool) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT 1
                         FROM INFORMATION_SCHEMA.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE()
                           AND TABLE_NAME = %s
                           AND COLUMN_NAME = %s
                         LIMIT 1",
                        $table_name,
                        $col_name
                    )
                );

                // Cache indefinitely; invalidated if we run dbDelta below.
                wp_cache_set( $cache_key_col, $col_exists, $cache_group, 0 );
            }

            if ( ! $col_exists ) {
                $needs_update = true;
                break;
            }
        }

        if ( ! $needs_update ) {
            return;
        }

        // Reconcile the table schema using dbDelta (idempotent; adds missing columns/indexes).
        $charset_collate  = $wpdb->get_charset_collate();
        $safe_table_name  = esc_sql( $table_name );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dbDelta requires a raw CREATE TABLE statement.
        $sql = "CREATE TABLE `{$safe_table_name}` (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            content longtext NOT NULL,
            position varchar(50) NOT NULL DEFAULT 'after_content',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            is_bulk tinyint(1) NOT NULL DEFAULT 0,
            accordion_display tinyint(1) NOT NULL DEFAULT 0,
            accordion_title varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY is_bulk (is_bulk)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Invalidate cached column checks after schema change attempt.
        foreach ( $required_columns as $col_name ) {
            wp_cache_delete( 'gzseo_col_exists_' . md5( $table_name . '|' . $col_name ), $cache_group );
        }
    }
}
