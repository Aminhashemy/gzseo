<?php
/**
 * GZ SEO Comment Generator AI Module
 * 
 * @package GZ_SEO
 * @subpackage Modules/Comment_Generator
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class GZSEO_Comment_Generator_AI
{

    /**
     * Constructor
     */
    public function __construct()
    {
        // Add custom cron interval
        add_filter('cron_schedules', array($this, 'add_cron_interval'));

        // Ensure cron is working with minute interval
        if (!wp_next_scheduled('gzseo_check_scheduled_comments')) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, 'gzseo_every_minute', 'gzseo_check_scheduled_comments');
        }

        $this->load_dependencies();
        // مستقیماً init_hooks را فراخوانی می‌کنیم
        $this->init_hooks();
    }

    /**
     * Add custom cron interval for every minute
     */
    public function add_cron_interval($schedules)
    {
        $schedules['gzseo_every_minute'] = array(
            'interval' => 60, // 60 seconds = 1 minute
            'display' => __( 'هر دقیقه', 'gzseo' )
        );
        return $schedules;
    }

    /**
     * Initialize hooks
     */
    public function init_hooks()
    {
        // Ajax handlers
        add_action('wp_ajax_gzseo_generate_comments', array($this, 'handle_generate_comments'));
        add_action('wp_ajax_gzseo_save_all_comments', array($this, 'handle_save_all_comments'));
        add_action('wp_ajax_gzseo_update_comment', array($this, 'handle_update_comment'));
        add_action('wp_ajax_gzseo_delete_generated_comment', array($this, 'handle_delete_comment'));
        // Disabled: handler method `handle_publish_comment` does not exist;
        // calling this endpoint would raise a fatal error. Re-enable after
        // implementing the handler with proper nonce + capability checks.
        // add_action('wp_ajax_gzseo_publish_comment', array($this, 'handle_publish_comment'));
        add_action('wp_ajax_gzseo_get_random_author', array($this, 'handle_get_random_author'));

        // اضافه کردن handler برای کلمات کلیدی
        //add_action('wp_ajax_gzseo_load_keywords_section', array($this, 'handle_load_keywords_section'));
        add_action('wp_ajax_gzseo_load_keywords_section', array($this, 'handle_load_keywords_section'));

        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        // Test handler
        add_action('wp_ajax_gzseo_test_comment_ajax', array($this, 'handle_test_ajax'));

        // Schedule hooks
        add_action('gzseo_publish_scheduled_comment', array($this, 'publish_scheduled_comment'), 10, 1);
        add_action('gzseo_check_scheduled_comments', array($this, 'check_missed_scheduled_comments'));

        // Check for missed scheduled comments on admin init
        //add_action('admin_init', array($this, 'check_missed_scheduled_comments'));

        // Add bulk indicator in admin comment list
        add_filter('comment_text', array($this, 'add_bulk_indicator_to_comment_list'), 10, 2);

        // Add custom column in comments list
        add_filter('manage_edit-comments_columns', array($this, 'add_bulk_column_to_comments'));
        add_action('manage_comments_custom_column', array($this, 'display_bulk_column_in_comments'), 10, 2);

        add_action('wp_ajax_gzseo_delete_bulk_comments', array($this, 'handle_delete_bulk_comments'));
    }

    /**
     * Test Ajax handler
     */
    public function handle_test_ajax()
    {
        // Verify nonce and capability BEFORE any other processing.
        if ( ! GZSEO_Security_Helpers::verify_ajax_request( 'gzseo_ajax_nonce', 'manage_options' ) ) {
            return;
        }

        wp_send_json_success(array('message' => __( 'Comment Generator Ajax is working!', 'gzseo' )));
    }

    /**
     * Load dependencies
     */
    private function load_dependencies()
    {
        require_once GZSEO_PLUGIN_PATH . 'includes/comment-generator/class-gzseo-comment-names.php';
    }

    public function enqueue_assets()
    {
        // Comment Generator AI Module
        wp_enqueue_style(
            'gzseo-comment-generator',
            GZSEO_PLUGIN_URL . 'assets/css/comment-generator-ai.css',
            array(),
            GZSEO_VERSION
        );

        wp_enqueue_script(
            'gzseo-comment-generator',
            GZSEO_PLUGIN_URL . 'assets/js/comment-generator-ai.js',
            array('jquery'),
            GZSEO_VERSION,
            true
        );

        wp_localize_script('gzseo-comment-generator', 'gzseo_cg_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gzseo_comment_generator_nonce'),
            'post_id' => get_the_ID(),
            'messages' => array(
                'generating'      => __( 'در حال تولید کامنت‌ها...', 'gzseo' ),
                'success'         => __( 'کامنت‌ها با موفقیت تولید شد', 'gzseo' ),
                'error'           => __( 'خطا در تولید کامنت‌ها', 'gzseo' ),
                'confirm_delete'  => __( 'آیا از حذف این کامنت مطمئن هستید؟', 'gzseo' ),
                'saving'          => __( 'در حال ذخیره...', 'gzseo' ),
                'saved'           => __( 'ذخیره شد', 'gzseo' ),
                'publishing'      => __( 'در حال انتشار...', 'gzseo' ),
                'published'       => __( 'منتشر شد', 'gzseo' )
            )
        ));
    }

    /**
     * Check if AI is logged in
     */
    private function is_ai_logged_in()
    {
        return get_option('gzseo_ai_logged_in', false);
    }

    /**
     * Get AI credentials
     */
    private function get_ai_credentials()
    {
        return array(
            'email' => get_option('gzseo_ai_email', ''),
            'password' => GZSEO_Security_Helpers::get_ai_password()
        );
    }

    /**
     * Handle generate comments request
     */
    public function handle_generate_comments()
    {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'gzseo_comment_generator_nonce')) {
            wp_send_json_error(array('message' => __( 'خطای امنیتی - nonce نامعتبر', 'gzseo' )));
            return;
        }

        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __( 'شما مجوز لازم برای تولید کامنت را ندارید', 'gzseo' )));
            return;
        }

        // Check if AI is logged in
        if (!$this->is_ai_logged_in()) {
            wp_send_json_error(array('message' => __( 'لطفا ابتدا وارد حساب هوش مصنوعی شوید', 'gzseo' )));
            return;
        }

        // Get credentials
        $credentials = $this->get_ai_credentials();

        // Get form data
        $post_id = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;
        if (!GZSEO_Security_Helpers::current_user_can_edit_post($post_id)) {
            wp_send_json_error(array('message' => __('شما اجازه ویرایش این نوشته را ندارید', 'gzseo')), 403);
        }
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $content = isset($_POST['content']) ? sanitize_textarea_field(wp_unslash($_POST['content'])) : '';
        $keyword = isset($_POST['keyword']) ? sanitize_text_field(wp_unslash($_POST['keyword'])) : '';
        $ai_model = isset($_POST['ai_model']) ? sanitize_text_field(wp_unslash($_POST['ai_model'])) : 'GPT-4O';
        $tone = isset($_POST['tone_of_content']) ? sanitize_text_field(wp_unslash($_POST['tone_of_content'])) : 'سوال جامع';
        $word_count = isset($_POST['word_count']) ? intval(wp_unslash($_POST['word_count'])) : 50;
        $comment_count = isset($_POST['comment_count']) ? intval(wp_unslash($_POST['comment_count'])) : 10;
        $author_gender = isset($_POST['author_gender']) ? sanitize_text_field(wp_unslash($_POST['author_gender'])) : 'mixed';

        // Validate inputs - اگر title و content خالی باشند، keyword باید وجود داشته باشد
        if (empty($title) && empty($content) && empty($keyword)) {
            wp_send_json_error(array('message' => __( 'در صورتی که عنوان و محتوا خالی هستند، باید حداقل یک کلمه کلیدی وارد کنید', 'gzseo' )));
            return;
        }

        if ($word_count > 500) {
            wp_send_json_error(array('message' => __( 'حداکثر تعداد کلمات برای ساخت کامنت 500 عدد می باشد.', 'gzseo' )));
            return;
        }

        // Prepare API request
        $api_data = array(
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'Title' => $title,
            'content' => $content,
            'keyword' => $keyword, // ارسال به صورت رشته
            'ai_model' => $ai_model,
            'Tone_of_Content' => $tone,
            'Word_Count' => $word_count
        );

        // Make API request
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
            wp_send_json_error(array(
                'message' => __( 'خطا در اتصال به سرویس هوش مصنوعی', 'gzseo' )));
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);

        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
        }

        if ($status_code !== 200) {
            wp_send_json_error(array(
                /* translators: %s: Status code. */
                'message' => sprintf(__( 'خطا در دریافت پاسخ از سرور (Status: %s)', 'gzseo' ), $status_code)
            ));
            return;
        }

        // Parse comments from string response
        $comments = $this->parse_comments_from_string($body);

        if (empty($comments)) {
            wp_send_json_error(array('message' => __( 'هیچ کامنتی در پاسخ دریافتی یافت نشد', 'gzseo' )));
            return;
        }

        // Process comments - فقط برای نمایش، بدون ذخیره
        $generated_comments = array();
        $names_generator = new GZSEO_Comment_Names();

        foreach ($comments as $index => $comment_text) {
            if ($author_gender === 'mixed') {
                $author_data = $names_generator->get_random_name();
            } else {
                $author_data = $names_generator->get_random_name($author_gender);
            }

            $use_random_date_raw = isset($_POST['use_random_date']) ? sanitize_text_field(wp_unslash($_POST['use_random_date'])) : '';
            $use_random_date = ($use_random_date_raw === 'true');

            $comment_data = array(
                'id' => 'preview_' . wp_generate_uuid4(),
                'content' => trim($comment_text),
                'author' => $author_data['full_name'],
                'author_email' => $this->generate_fake_email($author_data['first_name'], $author_data['last_name']),
                'gender' => $author_data['gender'],
                'date' => $this->get_random_date(),
                'status' => 'preview', // وضعیت جدید برای پیش‌نمایش
                'post_id' => $post_id,
                'use_random_date' => $use_random_date,
                'publish_immediately' => true, // به صورت پیش‌فرض، انتشار فوری
                'save_as_draft' => false,
                'schedule' => array(
                    'enabled' => false,
                    'interval_type' => 'hours',
                    'interval_value' => 1
                )
            );

            $generated_comments[] = $comment_data;
        }

        wp_send_json_success(array(
            
            'message' => sprintf(
                /* translators: %d: Number of generated comments. */
                _n( '%d کامنت با موفقیت تولید شد', '%d کامنت با موفقیت تولید شد', count($generated_comments), 'gzseo' ),
                count($generated_comments)
            ),
            'comments' => $generated_comments,
            'preview_mode' => true
        ));
    }

    /**
     * Handle save all comments
     */
    public function handle_save_all_comments()
    {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'gzseo_comment_generator_nonce')) {
            wp_send_json_error(array('message' => __( 'خطای امنیتی', 'gzseo' )));
            return;
        }

        // Check user permissions
        if (!current_user_can('moderate_comments')) {
            wp_send_json_error(array('message' => __( 'شما مجوز لازم برای ایجاد کامنت را ندارید', 'gzseo' )));
            return;
        }

        $comments_raw = isset($_POST['comments']) ? wp_unslash($_POST['comments']) : array();
        // Sanitize comments array
        $comments = array();
        if (is_array($comments_raw)) {
            foreach ($comments_raw as $comment) {
                if (is_array($comment)) {
                    $sanitized_comment = array();
                    $sanitized_comment['author'] = isset($comment['author']) ? sanitize_text_field($comment['author']) : '';
                    $sanitized_comment['author_email'] = isset($comment['author_email']) ? sanitize_email($comment['author_email']) : '';
                    $sanitized_comment['content'] = isset($comment['content']) ? sanitize_textarea_field($comment['content']) : '';
                    $sanitized_comment['gender'] = isset($comment['gender']) ? sanitize_text_field($comment['gender']) : '';
                    $sanitized_comment['date'] = isset($comment['date']) ? sanitize_text_field($comment['date']) : '';
                    $sanitized_comment['use_random_date'] = isset($comment['use_random_date']) ? sanitize_text_field($comment['use_random_date']) : '';
                    $sanitized_comment['save_as_draft'] = isset($comment['save_as_draft']) ? sanitize_text_field($comment['save_as_draft']) : '';
                    if (isset($comment['schedule']) && is_array($comment['schedule'])) {
                        $sanitized_comment['schedule'] = array(
                            'enabled' => isset($comment['schedule']['enabled']) ? sanitize_text_field($comment['schedule']['enabled']) : '',
                            'interval_type' => isset($comment['schedule']['interval_type']) ? sanitize_text_field($comment['schedule']['interval_type']) : '',
                            'interval_value' => isset($comment['schedule']['interval_value']) ? intval($comment['schedule']['interval_value']) : 0,
                        );
                    } else {
                        $sanitized_comment['schedule'] = array('enabled' => '', 'interval_type' => '', 'interval_value' => 0);
                    }
                    $comments[] = $sanitized_comment;
                }
            }
        }
        
        $post_id = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;
        if (!GZSEO_Security_Helpers::current_user_can_edit_post($post_id)) {
            wp_send_json_error(array('message' => __('شما اجازه ثبت نظر برای این نوشته را ندارید', 'gzseo')), 403);
        }
        $author_gender = isset($_POST['author_gender']) ? sanitize_text_field(wp_unslash($_POST['author_gender'])) : 'mixed';

        if (empty($comments)) {
            wp_send_json_error(array('message' => __( 'هیچ کامنتی برای ذخیره وجود ندارد', 'gzseo' )));
            return;
        }

        $saved_count = 0;
        $published_count = 0;
        $scheduled_count = 0;
        $draft_count = 0;
        $errors = array();

        foreach ($comments as $comment) {
            // بررسی نوع انتشار
            if (isset($comment['save_as_draft']) && $comment['save_as_draft'] === 'true') {
                // ذخیره به صورت پیش‌نویس (غیرفعال)
                $comment_date = isset($comment['use_random_date']) && $comment['use_random_date'] === 'true' ?
                    $comment['date'] : current_time('mysql');

                $comment_id = wp_insert_comment(array(
                    'comment_post_ID' => $post_id,
                    'comment_author' => $comment['author'],
                    'comment_author_email' => $comment['author_email'],
                    'comment_content' => $comment['content'],
                    'comment_date' => $comment_date,
                    'comment_date_gmt' => get_gmt_from_date($comment_date),
                    'comment_approved' => 0, // غیرفعال
                    'comment_type' => 'comment'
                ));

                if ($comment_id) {
                    $draft_count++;
                    // ذخیره متادیتا
                    update_comment_meta($comment_id, '_gzseo_generated', true);
                    update_comment_meta($comment_id, '_gzseo_gender', $comment['gender']);
                    update_comment_meta($comment_id, '_gzseo_saved_as_draft', true);
                } else {
                    $errors[] = __( 'خطا در ذخیره پیش‌نویس: ', 'gzseo' ) . $comment['author'];
                }

            } elseif (isset($comment['schedule']['enabled']) && $comment['schedule']['enabled'] === 'true') {
                // زمان‌بندی انتشار
                $schedule_data = $comment['schedule'];
                $comment_data_for_schedule = array(
                    'post_id' => $post_id,
                    'author' => $comment['author'],
                    'author_email' => $comment['author_email'],
                    'content' => $comment['content'],
                    'gender' => $comment['gender'],
                    'date' => isset($comment['use_random_date']) && $comment['use_random_date'] === 'true' ?
                        $comment['date'] : current_time('mysql')
                );

                $scheduled = $this->schedule_comment($comment_data_for_schedule, $schedule_data);
                if ($scheduled) {
                    $scheduled_count++;
                } else {
                    $errors[] = __( 'خطا در زمان‌بندی: ', 'gzseo' ) . $comment['author'];
                }

            } else {
                // انتشار فوری
                $comment_date = isset($comment['use_random_date']) && $comment['use_random_date'] === 'true' ?
                    $comment['date'] : current_time('mysql');

                $comment_id = wp_insert_comment(array(
                    'comment_post_ID' => $post_id,
                    'comment_author' => $comment['author'],
                    'comment_author_email' => $comment['author_email'],
                    'comment_content' => $comment['content'],
                    'comment_date' => $comment_date,
                    'comment_date_gmt' => get_gmt_from_date($comment_date),
                    'comment_approved' => 1,
                    'comment_type' => 'comment'
                ));

                if ($comment_id) {
                    $published_count++;
                    // ذخیره متادیتا
                    update_comment_meta($comment_id, '_gzseo_generated', true);
                    update_comment_meta($comment_id, '_gzseo_gender', $comment['gender']);
                } else {
                    $errors[] = __( 'خطا در انتشار کامنت: ', 'gzseo' ) . $comment['author'];
                }
            }
        }

        // ساخت پیام مناسب
        $message_parts = array();
        if ($published_count > 0) {
            
            $message_parts[] = sprintf(
                /* translators: %d: Number of published comments. */
                _n( '%d کامنت منتشر شد', '%d کامنت منتشر شد', $published_count, 'gzseo' ),
                $published_count
            );
        }
        if ($draft_count > 0) {
            
            $message_parts[] = sprintf(
                /* translators: %d: Number of draft comments. */
                _n( '%d کامنت به صورت پیش‌نویس ذخیره شد', '%d کامنت به صورت پیش‌نویس ذخیره شد', $draft_count, 'gzseo' ) ,
                $draft_count
            );
        }
        if ($scheduled_count > 0) {
            
            $message_parts[] = sprintf(
                /* translators: %d: Number of scheduled comments. */
                _n( '%d کامنت زمان‌بندی شد (در حال حاضر به صورت غیرفعال)', '%d کامنت زمان‌بندی شد (در حال حاضر به صورت غیرفعال)', $scheduled_count, 'gzseo' ) ,
                $scheduled_count
            );
        }

        $message = implode('، ', $message_parts);

        if (!empty($errors)) {
            $message .= ' ' . __( '(برخی کامنت‌ها با خطا مواجه شدند)', 'gzseo' );
        }

        wp_send_json_success(array(
            'message' => $message,
            'published_count' => $published_count,
            'draft_count' => $draft_count,
            'scheduled_count' => $scheduled_count,
            'errors' => $errors
        ));
    }

    /**
     * Schedule comment
     */
    private function schedule_comment($comment_data, $schedule_data)
    {
        $interval_type = $schedule_data['interval_type'];
        $interval_value = intval($schedule_data['interval_value']);

        // Calculate timestamp
        $timestamp = $this->calculate_schedule_timestamp($interval_type, $interval_value);

        // First, save the comment as pending (unapproved)
        $comment_id = wp_insert_comment(array(
            'comment_post_ID' => $comment_data['post_id'],
            'comment_author' => $comment_data['author'],
            'comment_author_email' => $comment_data['author_email'],
            'comment_content' => $comment_data['content'],
            'comment_date' => current_time('mysql'),
            'comment_date_gmt' => current_time('mysql', 1),
            'comment_approved' => 0, // Save as unapproved
            'comment_type' => 'comment'
        ));

        if (!$comment_id) {
            return false;
        }

        // Save metadata
        update_comment_meta($comment_id, '_gzseo_generated', true);
        update_comment_meta($comment_id, '_gzseo_gender', $comment_data['gender']);
        update_comment_meta($comment_id, '_gzseo_scheduled', true);
        update_comment_meta($comment_id, '_gzseo_scheduled_time', $timestamp);
        update_comment_meta($comment_id, '_gzseo_schedule_data', $schedule_data);

        // Schedule event - اضافه کردن comment_id به آرگومان
        $scheduled = wp_schedule_single_event($timestamp, 'gzseo_publish_scheduled_comment', array($comment_id));

        if ($scheduled === false) {
            // اگر زمان‌بندی موفق نبود، حداقل کامنت ذخیره شده است
            if (defined('WP_DEBUG') && WP_DEBUG) {
            }
        }

        return true; // برگرداندن true چون کامنت ذخیره شده
    }

    /**
     * Calculate schedule timestamp
     */
    private function calculate_schedule_timestamp($type, $value)
    {
        $current_time = current_time('timestamp');

        switch ($type) {
            case 'minutes':
                return $current_time + ($value * 60);
            case 'hours':
                return $current_time + ($value * 3600);
            case 'days':
                return $current_time + ($value * 86400);
            case 'weeks':
                return $current_time + ($value * 604800);
            case 'months':
                return $current_time + ($value * 2592000);
            default:
                return $current_time + 3600; // Default 1 hour
        }
    }

    /**
     * Publish scheduled comment
     */
    public function publish_scheduled_comment($comment_id)
    {
        // Get the comment
        $comment = get_comment($comment_id);

        if (!$comment) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
            }
            return;
        }

        // Update comment status to approved (این تابع از cron اجرا می‌شود، نیاز به بررسی کاربر ندارد)
        $updated = wp_update_comment(array(
            'comment_ID' => $comment_id,
            'comment_approved' => 1,
            'comment_date' => current_time('mysql'),
            'comment_date_gmt' => current_time('mysql', 1)
        ));

        if ($updated) {
            // Update metadata
            update_comment_meta($comment_id, '_gzseo_published_at', current_time('mysql'));
            delete_comment_meta($comment_id, '_gzseo_scheduled_time');
        }
    }

    /**
     * Check for missed scheduled comments
     */
    public function check_missed_scheduled_comments()
    {
        // Get all scheduled comments
        $args = array(
            'status' => 'hold', // unapproved comments
            'number' => 100,
            'orderby' => 'comment_ID',
            'order' => 'ASC',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- querying comments by _gzseo_scheduled meta is required to find scheduled comments.
            'meta_query' => array(
                array(
                    'key' => '_gzseo_scheduled',
                    'value' => true,
                    'compare' => '='
                ),
                array(
                    'key' => '_gzseo_scheduled_time',
                    'value' => current_time('timestamp'),
                    'compare' => '<=',
                    'type' => 'NUMERIC'
                )
            )
        );

        $comments = get_comments($args);

        if (!empty($comments)) {
            //error_log('GZSEO: Checking ' . count($comments) . ' scheduled comments at ' . current_time('Y-m-d H:i:s'));
        }

        foreach ($comments as $comment) {
            $scheduled_time = get_comment_meta($comment->comment_ID, '_gzseo_scheduled_time', true);

            // If scheduled time has passed, publish the comment
            if ($scheduled_time && $scheduled_time <= current_time('timestamp')) {
                //error_log('GZSEO: Publishing scheduled comment ID: ' . $comment->comment_ID);

                $this->publish_scheduled_comment($comment->comment_ID);

                // Remove the scheduled time meta
                delete_comment_meta($comment->comment_ID, '_gzseo_scheduled_time');
                delete_comment_meta($comment->comment_ID, '_gzseo_scheduled');
            }
        }
    }

    /**
     * Parse comments from string response
     */
    private function parse_comments_from_string($response_string)
    {
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
     */
    private function generate_fake_email($first_name, $last_name)
    {
        $seed = sanitize_key(remove_accents($first_name . '-' . $last_name));
        if ('' === $seed) {
            $seed = 'commenter';
        }
        return substr($seed, 0, 40) . '-' . wp_rand(1000, 9999) . '@example.invalid';
    }

    /**
     * Get random date in last 30 days
     */
    private function get_random_date()
    {
        $days_ago = wp_rand(1, 30);
        $random_hour = wp_rand(0, 23);
        $random_minute = wp_rand(0, 59);
        $random_second = wp_rand(0, 59);

        // تاریخ تصادفی با ساعت 00:00:00
        $random_date = strtotime("-{$days_ago} days");
        $random_date = strtotime(gmdate('Y-m-d', $random_date) . " 00:00:00");

        // اضافه کردن ساعت، دقیقه و ثانیه تصادفی
        $random_date += ($random_hour * 3600) + ($random_minute * 60) + $random_second;

        return gmdate('Y-m-d H:i:s', $random_date);
    }

    /**
     * Handle update comment
     */
    public function handle_update_comment()
    {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'gzseo_comment_generator_nonce')) {
            wp_send_json_error(array('message' => __( 'خطای امنیتی', 'gzseo' )));
            return;
        }

        // Check user permissions
        if (!current_user_can('moderate_comments')) {
            wp_send_json_error(array('message' => __( 'شما مجوز لازم برای ویرایش کامنت را ندارید', 'gzseo' )));
            return;
        }

        // این متد حالا فقط برای آپدیت موقت در حافظه JavaScript استفاده می‌شود
        wp_send_json_success(array('message' => __( 'کامنت با موفقیت بروزرسانی شد', 'gzseo' )));
    }

    /**
     * Handle delete comment
     */
    public function handle_delete_comment()
    {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'gzseo_comment_generator_nonce')) {
            wp_send_json_error(array('message' => __( 'خطای امنیتی', 'gzseo' )));
            return;
        }

        // Check user permissions
        if (!current_user_can('moderate_comments')) {
            wp_send_json_error(array('message' => __( 'شما مجوز لازم برای حذف کامنت را ندارید', 'gzseo' )));
            return;
        }

        // این متد حالا فقط برای حذف موقت در حافظه JavaScript استفاده می‌شود
        wp_send_json_success(array('message' => __( 'کامنت حذف شد', 'gzseo' )));
    }

    /**
     * Handle get random author
     */
    public function handle_get_random_author()
    {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'gzseo_comment_generator_nonce')) {
            wp_send_json_error(array('message' => __( 'خطای امنیتی', 'gzseo' )));
            return;
        }

        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __( 'شما مجوز لازم برای این عملیات را ندارید', 'gzseo' )));
            return;
        }

        $gender = isset($_POST['gender']) ? sanitize_text_field(wp_unslash($_POST['gender'])) : null;

        $names_generator = new GZSEO_Comment_Names();
        $author_data = $names_generator->get_random_name($gender);

        wp_send_json_success($author_data);
    }

    /**
     * Handle load keywords section
     */
    public function handle_load_keywords_section()
    {
        // Verify nonce and capability BEFORE any other processing.
        if ( ! GZSEO_Security_Helpers::verify_ajax_request( 'gzseo_comment_generator_nonce', 'manage_options' ) ) {
            return;
        }

        try {
            $post_id = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;
            $post_url = isset($_POST['post_url']) ? esc_url_raw(wp_unslash($_POST['post_url'])) : '';

            if (!$post_id || !$post_url) {
                wp_send_json_error(array('message' => __( 'پارامترهای نامعتبر', 'gzseo' )));
                return;
            }

            // بررسی وجود کلاس
            if (!class_exists('GZSEO_Search_Keywords')) {
                // اگر کلاس وجود ندارد، سعی در load کردن آن
                $file_path = GZSEO_PLUGIN_PATH . 'includes/class-gzseo-search-keywords.php';
                if (file_exists($file_path)) {
                    require_once $file_path;
                } else {
                    wp_send_json_error(array('message' => __( 'فایل کلمات کلیدی یافت نشد', 'gzseo' )));
                    return;
                }
            }

            // ایجاد instance از کلاس
            $search_keywords = new GZSEO_Search_Keywords();

            // دریافت کلمات کلیدی
            $keywords_data = $search_keywords->get_search_console_keywords($post_url);
            // Generate HTML
            ob_start();
            ?>
            <div class="gzseo-search-keywords-container">
                <div class="gzseo-keywords-header">
                    <h4><i class="fas fa-search"></i> <?php esc_html_e( 'کلمات کلیدی سرچ کنسول', 'gzseo' ); ?></h4>
                    <span class="gzseo-keywords-help"><?php esc_html_e( 'روی هر کلمه کلیدی کلیک کنید تا اضافه شود', 'gzseo' ); ?></span>
                </div>

                <?php if (!empty($keywords_data) && is_array($keywords_data)): ?>
                    <div class="gzseo-keywords-list">
                        <?php foreach ($keywords_data as $keyword): ?>
                            <div class="gzseo-keyword-item" data-keyword="<?php echo esc_attr($keyword['query']); ?>"
                                title="<?php esc_attr_e( 'کلیک کنید تا اضافه شود', 'gzseo' ); ?>">
                                <div class="gzseo-keyword-text">
                                    <?php echo esc_html($keyword['query']); ?>
                                </div>
                                <div class="gzseo-keyword-stats">
                                    <span class="gzseo-stat" title="<?php esc_attr_e( 'کلیک‌ها', 'gzseo' ); ?>">
                                        <i class="fas fa-mouse-pointer"></i>
                                        <?php echo esc_html(number_format($keyword['clicks'])); ?>
                                    </span>
                                    <span class="gzseo-stat" title="<?php esc_attr_e( 'نمایش‌ها', 'gzseo' ); ?>">
                                        <i class="fas fa-eye"></i>
                                        <?php echo esc_html(number_format($keyword['impressions'])); ?>
                                    </span>
                                    <?php if (isset($keyword['position']) && $keyword['position'] > 0): ?>
                                        <span class="gzseo-stat" title="<?php esc_attr_e( 'رتبه میانگین', 'gzseo' ); ?>">
                                            <i class="fas fa-chart-line"></i>
                                            <?php echo esc_html(number_format($keyword['position'], 1)); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (isset($keyword['ctr']) && $keyword['ctr'] > 0): ?>
                                        <span class="gzseo-stat" title="<?php esc_attr_e( 'نرخ کلیک', 'gzseo' ); ?>">
                                            <i class="fas fa-percentage"></i>
                                            <?php echo esc_html(number_format($keyword['ctr'], 1)); ?>%
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="gzseo-no-keywords">
                        <i class="fas fa-info-circle"></i>
                        <?php esc_html_e( 'کلمات کلیدی برای این صفحه یافت نشد', 'gzseo' ); ?>
                        <small style="display: block; margin-top: 5px; color: #999;">
                            <?php esc_html_e( 'ممکن است به سرچ کنسول متصل نباشید یا این صفحه ترافیکی نداشته باشد', 'gzseo' ); ?>
                        </small>
                    </div>
                <?php endif; ?>
            </div>
            <?php
            $html = ob_get_clean();

            wp_send_json_success(array('html' => $html));

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
            }
            wp_send_json_error(array('message' => __( 'خطا در دریافت کلمات کلیدی: ', 'gzseo' ) . $e->getMessage()));
        }
    }

    /**
     * Render content
     */
    public function render_content($post)
    {
        $is_logged_in = $this->is_ai_logged_in();
        $generated_comments = get_post_meta($post->ID, '_gzseo_generated_comments', true);

        if (!is_array($generated_comments)) {
            $generated_comments = array();
        }
        ?>
        <div class="gzseo-comment-generator-wrapper">
            <?php if (!$is_logged_in): ?>
                <div class="gzseo-ai-login-notice">
                    <i class="fas fa-exclamation-circle"></i>
                    <p><?php esc_html_e( 'برای استفاده از تولید کامنت با هوش مصنوعی، ابتدا باید وارد حساب کاربری خود شوید.', 'gzseo' ); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=gzseo-dashboard#ai')); ?>" class="button button-primary">
                        <?php esc_html_e( 'ورود به حساب هوش مصنوعی', 'gzseo' ); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="gzseo-cg-form">
                    <input type="hidden" id="gzseo-cg-post-id" value="<?php echo esc_attr($post->ID); ?>">
                    <input type="hidden" id="gzseo-cg-title" value="<?php echo esc_attr(get_the_title($post->ID)); ?>">
                    <input type="hidden" id="gzseo-cg-content" value="<?php
                    $content = get_post_field('post_content', $post->ID);
                    echo esc_attr(wp_strip_all_tags($content));
                    ?>">
                    <input type="hidden" id="gzseo-cg-ai-model" value="standard">

                    <!-- Form Fields -->
                    <div class="gzseo-cg-info-box">
                        <i class="fas fa-info-circle"></i>
                        <p>
                            <?php
                            /* translators: %s: Post title. */
                            printf( esc_html__( 'کامنت‌ها بر اساس عنوان "%s" و محتوای این پست تولید خواهند شد.', 'gzseo' ), esc_html(get_the_title($post->ID)) );
                            ?>
                        </p>
                    </div>

                    <div class="gzseo-form-group">
                        <label for="gzseo-cg-keywords"><?php esc_html_e( 'کلمات کلیدی:', 'gzseo' ); ?></label>
                        <div class="gzseo-keyword-input-wrapper" style="display: flex; gap: 10px;">
                            <input type="text" id="gzseo-cg-keywords" class="widefat" style="flex: 1;"
                                placeholder="<?php esc_attr_e( 'کلمات کلیدی را وارد کنید', 'gzseo' ); ?>">
                            <button type="button" id="gzseo-cg-add-keyword" class="button">
                                <i class="fas fa-plus"></i> <?php esc_html_e( 'افزودن', 'gzseo' ); ?>
                            </button>
                        </div>
                        <div class="gzseo-cg-keywords-tags"></div>
                        <small class="gzseo-help-text"><?php esc_html_e( 'برای اضافه کردن هر کلمه کلیدی، آن را تایپ کرده و Enter بزنید یا دکمه افزودن را کلیک کنید', 'gzseo' ); ?></small>
                    </div>

                    <div id="gzseo-search-keywords-wrapper" data-post-id="<?php echo esc_attr($post->ID); ?>"
                        data-post-url="<?php echo esc_url(get_permalink($post->ID)); ?>" style="margin-top: 10px;">
                        <div class="gzseo-loading-keywords" style="display: none;">
                            <i class="fas fa-spinner fa-spin"></i> <?php esc_html_e( 'در حال دریافت کلمات کلیدی از سرچ کنسول...', 'gzseo' ); ?>
                        </div>
                    </div>

                    <div class="gzseo-cg-row">
                        <div class="gzseo-cg-col-6">
                            <div class="gzseo-form-group">
                                <label for="gzseo-cg-word-count"><?php esc_html_e( 'تعداد کلمات:', 'gzseo' ); ?></label>
                                <input type="number" id="gzseo-cg-word-count" class="widefat" value="50" min="10" max="500">
                            </div>
                        </div>
                        <div class="gzseo-cg-col-6">
                            <div class="gzseo-form-group">
                                <label for="gzseo-cg-tone"><?php esc_html_e( 'لحن محتوا:', 'gzseo' ); ?></label>
                                <select id="gzseo-cg-tone" class="widefat">
                                    <option value="سوال جامع، سوال کوتاه، تجربه منفی، تجربه مثبت"><?php esc_html_e( 'رندوم', 'gzseo' ); ?></option>
                                    <option value="سوال جامع"><?php esc_html_e( 'سوال جامع', 'gzseo' ); ?></option>
                                    <option value="سوال کوتاه"><?php esc_html_e( 'سوال کوتاه', 'gzseo' ); ?></option>
                                    <option value="تجربه منفی"><?php esc_html_e( 'تجربه منفی', 'gzseo' ); ?></option>
                                    <option value="تجربه مثبت"><?php esc_html_e( 'تجربه مثبت', 'gzseo' ); ?></option>
                                </select>
                            </div>
                        </div>
                        <!--<div class="gzseo-cg-col-4">
                            <div class="gzseo-form-group">
                                <label for="gzseo-cg-count">تعداد کامنت:</label>
                                <input type="number" id="gzseo-cg-count" class="widefat" value="3" min="1" max="10">
                            </div>
                        </div>-->
                    </div>

                    <div class="gzseo-form-group">
                        <label for="gzseo-cg-author-gender"><?php esc_html_e( 'جنسیت نویسندگان:', 'gzseo' ); ?></label>
                        <select id="gzseo-cg-author-gender" class="widefat">
                            <option value="mixed" selected><?php esc_html_e( 'ترکیبی (مذکر و مونث)', 'gzseo' ); ?></option>
                            <option value="male"><?php esc_html_e( 'فقط مذکر', 'gzseo' ); ?></option>
                            <option value="female"><?php esc_html_e( 'فقط مونث', 'gzseo' ); ?></option>
                        </select>
                        <small class="gzseo-help-text"><?php esc_html_e( 'انتخاب کنید که نام نویسندگان کامنت‌ها از چه جنسیتی انتخاب شوند', 'gzseo' ); ?></small>
                    </div>

                    <div class="gzseo-form-group">
                        <label class="gzseo-checkbox-label">
                            <input type="checkbox" id="gzseo-cg-random-date">
                            <?php esc_html_e( 'تاریخ تصادفی (30 روز گذشته)', 'gzseo' ); ?>
                        </label>
                    </div>

                    <div class="gzseo-cg-actions">
                        <button type="button" id="gzseo-cg-generate" class="button button-primary">
                            <i class="fas fa-magic"></i> <?php esc_html_e( 'تولید کامنت‌ها', 'gzseo' ); ?>
                        </button>
                    </div>

                    <!-- Generated Comments Section -->
                    <div id="gzseo-cg-results" class="gzseo-cg-results" style="display: none;">
                        <h4><?php esc_html_e( 'کامنت‌های تولید شده:', 'gzseo' ); ?></h4>
                        <div id="gzseo-cg-comments-list"></div>
                        <div class="gzseo-cg-final-actions" style="display: none;">
                            <button type="button" id="gzseo-cg-save-all" class="button button-primary">
                                <i class="fas fa-save"></i> <?php esc_html_e( 'ذخیره نهایی کامنت‌ها', 'gzseo' ); ?>
                            </button>
                            <button type="button" id="gzseo-cg-cancel-all" class="button">
                                <i class="fas fa-times"></i> <?php esc_html_e( 'انصراف', 'gzseo' ); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div id="gzseo-cg-messages" class="gzseo-cg-messages"></div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get bulk generated comments count for a post
     */
    public function get_bulk_comments_count($post_id)
    {
        $args = array(
            'post_id' => $post_id,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- querying comments by _gzseo_bulk_generated meta is required to identify bulk-generated comments.
            'meta_query' => array(
                array(
                    'key' => '_gzseo_bulk_generated',
                    'value' => '1',
                    'compare' => '='
                )
            ),
            'count' => true
        );

        return get_comments($args);
    }

    /**
     * Get all bulk generated comments
     */
    public function get_bulk_comments($post_id = null)
    {
        $args = array(
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- querying comments by _gzseo_bulk_generated meta is required to identify bulk-generated comments.
            'meta_query' => array(
                array(
                    'key' => '_gzseo_bulk_generated',
                    'value' => '1',
                    'compare' => '='
                )
            ),
            'orderby' => 'comment_date',
            'order' => 'DESC'
        );

        if ($post_id) {
            $args['post_id'] = $post_id;
        }

        return get_comments($args);
    }

    /**
     * Get posts with bulk generated comments
     */
    public function get_posts_with_bulk_comments()
    {
        global $wpdb;

        // Try to get from cache first
        $cache_key = 'gzseo_bulk_comments_posts';
        $cache_group = 'gzseo';
        $results = wp_cache_get($cache_key, $cache_group);

        if (false === $results) {
            // Direct database call required for complex JOIN query with comment metadata.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- 3-table JOIN cannot be expressed via WP_Query; result is cached for 5 minutes.
            $results = $wpdb->get_results(
                "SELECT DISTINCT p.ID, p.post_title, 
                        COUNT(c.comment_ID) as comment_count,
                        MAX(c.comment_date) as last_comment_date
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->comments} c ON p.ID = c.comment_post_ID
                INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id
                WHERE cm.meta_key = '_gzseo_bulk_generated' 
                AND cm.meta_value = '1'
                AND c.comment_approved = '1'
                GROUP BY p.ID
                ORDER BY last_comment_date DESC"
            );

            // Cache for 5 minutes (300 seconds)
            wp_cache_set($cache_key, $results, $cache_group, 300);
        }

        return $results;
    }

    /**
     * Delete all bulk generated comments for a post
     */
    public function delete_bulk_comments($post_id)
    {
        $comments = $this->get_bulk_comments($post_id);
        $deleted_count = 0;

        foreach ($comments as $comment) {
            if (wp_delete_comment($comment->comment_ID, true)) {
                $deleted_count++;
            }
        }

        // حذف متادیتا از پست
        delete_post_meta($post_id, '_gzseo_bulk_comments_generated');
        delete_post_meta($post_id, '_gzseo_bulk_comments_count');
        delete_post_meta($post_id, '_gzseo_bulk_comments_date');

        // Invalidate cache after bulk comment deletion
        wp_cache_delete('gzseo_bulk_comments_posts', 'gzseo');

        return $deleted_count;
    }

    /**
     * Check if comment is bulk generated
     */
    public function is_bulk_generated($comment_id)
    {
        return get_comment_meta($comment_id, '_gzseo_bulk_generated', true) === '1';
    }

    /**
     * Add bulk indicator to comment list in admin
     */
    public function add_bulk_indicator_to_comment_list($comment_text, $comment)
    {
        if (is_user_logged_in() && current_user_can('moderate_comments') && $this->is_bulk_generated($comment->comment_ID)) {
            $indicator = ' <span class="gzseo-bulk-comment-indicator" style="background: #ff9800; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 5px;">' . __( ' آپدیت دسته جمعی', 'gzseo' ) . '</span>';
            return $comment_text . $indicator;
        }
        return $comment_text;
    }

    /**
     * Add bulk column to comments list
     */
    public function add_bulk_column_to_comments($columns)
    {
        $columns['gzseo_source'] = __( 'منبع تولید', 'gzseo' );
        return $columns;
    }

    /**
     * Display bulk column content in comments list
     */
    public function display_bulk_column_in_comments($column, $comment_id)
    {
        if ($column === 'gzseo_source') {
            if ($this->is_bulk_generated($comment_id)) {
                echo '<span style="color: #ff9800;" title="' . esc_attr__( 'تولید شده با آپدیت دسته جمعی', 'gzseo' ) . '">
                    <span class="dashicons dashicons-update"></span> ' . esc_html__( 'دسته جمعی', 'gzseo' ) . '
                    </span>';
            } elseif (get_comment_meta($comment_id, '_gzseo_generated', true)) {
                echo '<span style="color: #4caf50;" title="' . esc_attr__( 'تولید شده با هوش مصنوعی', 'gzseo' ) . '">
                    <span class="dashicons dashicons-admin-generic"></span> ' . esc_html__( 'تکی', 'gzseo' ) . '
                    </span>';
            } else {
                echo '<span style="color: #999;">-</span>';
            }
        }
    }

    /**
     * Handle delete bulk comments request
     */
    public function handle_delete_bulk_comments() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'gzseo_delete_bulk_comments')) {
            wp_send_json_error(array('message' => __( 'خطای امنیتی', 'gzseo' )));
            return;
        }

        // Check user permissions
        if (!current_user_can('moderate_comments')) {
            wp_send_json_error(array('message' => __( 'شما مجوز لازم برای حذف کامنت را ندارید', 'gzseo' )));
            return;
        }
        
        $post_id = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;
        
        if (!$post_id) {
            wp_send_json_error(array('message' => __( 'شناسه پست نامعتبر است', 'gzseo' )));
            return;
        }
        
        // حذف کامنت‌های bulk
        $deleted_count = $this->delete_bulk_comments($post_id);
        
        if ($deleted_count > 0) {
            wp_send_json_success(array(
                
                'message' => sprintf(
                    /* translators: %d: Number of bulk comments deleted. */
                    _n( '%d کامنت bulk با موفقیت حذف شد', '%d کامنت bulk با موفقیت حذف شد', $deleted_count, 'gzseo' ),
                    $deleted_count
                ),
                'deleted_count' => $deleted_count
            ));
        } else {
            wp_send_json_error(array('message' => __( 'هیچ کامنت bulk برای حذف یافت نشد', 'gzseo' )));
        }
    }
}

// Initialize the class
new GZSEO_Comment_Generator_AI();
