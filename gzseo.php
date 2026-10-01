<?php
/**
 * Plugin Name: GZSEO
 * Description: Generation Z SEO Plugin | Website SEO Powered by Artificial Intelligence
 * Author: Amin Hashemi
 * Version: 2.0.15
 * Author URI: https://aminhashemy.org/
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// جلوگیری از دسترسی مستقیم
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// تعریف ثابت‌ها
define( 'GZSEO_VERSION', '2.0.15' );
define( 'GZSEO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GZSEO_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

// کلاس اصلی افزونه
class GZSeo
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('init', array($this, 'load_textdomain'));
        
        // Load security helpers first
        require_once GZSEO_PLUGIN_PATH . 'includes/class-gzseo-security-helpers.php';
        
        $this->init_hooks();

        require_once GZSEO_PLUGIN_PATH . 'includes/ajax-handlers.php';
        
        require_once GZSEO_PLUGIN_PATH . 'includes/class-gzseo-featured-image-ai.php';

        // Load Comment Generator AI module
        require_once GZSEO_PLUGIN_PATH . 'includes/class-gzseo-comment-generator-ai.php';

        // Load Search Console module
        require_once GZSEO_PLUGIN_PATH . 'includes/class-gzseo-search-console.php';
        new GZSEO_Search_Console();

        require_once GZSEO_PLUGIN_PATH . 'includes/class-gzseo-ai-api.php';

        require_once GZSEO_PLUGIN_PATH . 'includes/class-gzseo-search-keywords.php';

        require_once GZSEO_PLUGIN_PATH . 'includes/class-gzseo-table-generator.php';
        //$this->table_generator = new GZSEO_Table_Generator();

        require_once GZSEO_PLUGIN_PATH . 'includes/class-gzseo-title-description-generator.php';

        require_once GZSEO_PLUGIN_PATH . 'includes/class-gzseo-video-update.php';

        require_once GZSEO_PLUGIN_PATH . 'includes/class-gzseo-content-update.php';

        require_once GZSEO_PLUGIN_PATH . 'includes/class-gzseo-table-styles.php';

        require_once GZSEO_PLUGIN_PATH . 'includes/class-gzseo-person-schema.php';
        require_once GZSEO_PLUGIN_PATH . 'includes/class-gzseo-product-list-schema.php';

        require_once GZSEO_PLUGIN_PATH . 'includes/class-gzseo-featured-image-settings.php';

        require_once GZSEO_PLUGIN_PATH . 'includes/migrations/class-gzseo-migration-manager.php';

        require_once GZSEO_PLUGIN_PATH . 'includes/migrations/class-gzseo-ai-migration.php';
        require_once GZSEO_PLUGIN_PATH . 'includes/migrations/class-gzseo-search-console-migration.php';
    }

    private function init_hooks()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_gzseo_save_settings', array($this, 'save_settings'));
        add_action('wp_ajax_gzseo_refresh_dashboard', array($this, 'refresh_dashboard'));
        add_action('wp_ajax_gzseo_auth_google', array($this, 'auth_google'));
        add_action('wp_ajax_gzseo_disconnect_google', array($this, 'disconnect_google'));
        add_action('wp_ajax_gzseo_save_bulk_update', array($this, 'save_bulk_update'));
        add_action('wp_ajax_gzseo_save_config', array($this, 'save_config'));
        // اضافه کردن متاباکس به صفحات ادمین
        add_action('add_meta_boxes', array($this, 'add_gzseo_metabox'));

        // اضافه کردن متاباکس به دسته‌بندی‌ها
        add_action('category_edit_form_fields', array($this, 'render_taxonomy_fields_limited'), 10, 2);
        add_action('post_tag_edit_form_fields', array($this, 'render_taxonomy_fields_limited'), 10, 2);
        add_action('product_cat_edit_form_fields', array($this, 'render_taxonomy_fields_limited'), 10, 2);
        add_action('product_tag_edit_form_fields', array($this, 'render_taxonomy_fields_limited'), 10, 2);

        add_action('save_post', array($this, 'save_gzseo_metabox'));

        add_action('wp_ajax_gzseo_get_taxonomy_terms', array($this, 'get_taxonomy_terms'));

        add_action('wp_ajax_gzseo_bulk_preview', array($this, 'bulk_preview'));
        add_action('wp_ajax_gzseo_bulk_execute', array($this, 'bulk_execute'));

        add_action('wp_ajax_gzseo_update_ai_credits', array($this, 'ajax_update_ai_credits'));

        add_action('init', array($this, 'check_and_run_migration'), 5);

    }

    public function save_gzseo_metabox()
    {

    }

    // متد بررسی اتصال
    public function is_ai_connected()
    {
        return get_option('gzseo_ai_logged_in', false);
    }

    // متد دریافت ایمیل
    public function get_ai_email()
    {
        return get_option('gzseo_ai_email', '');
    }

    // متد دریافت اعتبار
    public function get_ai_credits()
    {
        $credits = get_option('gzseo_ai_credits', 0);
        return absint( $credits );
    }

    /*public function load_textdomain()
    {
        load_plugin_textdomain('gzseo', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }*/
    public function load_textdomain()
    {
        $domain = 'gzseo';
        $locale = get_locale(); // همیشه en_US خواهد بود
        
        // ساخت مسیر کامل و دقیق فایل .mo
        $mofile = plugin_dir_path(__FILE__) . 'languages/' . $domain . '-' . $locale . '.mo';
        
        // دیباگ: نمایش اطلاعات
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            // Debug logging disabled for WordPress.org compliance.
        }

        // بارگذاری مستقیم فایل .mo
        load_textdomain($domain, $mofile);
        
        // دیباگ: بررسی نهایی
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            // Debug logging disabled for WordPress.org compliance.
        }
    }

    public function add_admin_menu()
    {
        // منوی اصلی
        add_menu_page(
            __('جی زد سئو', 'gzseo'),
            __('جی زد سئو', 'gzseo'),
            'manage_options',
            'gzseo-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-chart-line',
            30
        );

        // زیرمنوی داشبورد
        add_submenu_page(
            'gzseo-dashboard',
            __('داشبورد', 'gzseo'),
            __('داشبورد', 'gzseo'),
            'manage_options',
            'gzseo-dashboard',
            array($this, 'render_dashboard')
        );

        // زیرمنوی آپدیت دسته جمعی
        add_submenu_page(
            'gzseo-dashboard',
            __('آپدیت دسته جمعی', 'gzseo'),
            __('آپدیت دسته جمعی', 'gzseo'),
            'manage_options',
            'gzseo-bulk-update',
            array($this, 'render_bulk_update_page')
        );

        // زیرمنوی گزارشات
        add_submenu_page(
            'gzseo-dashboard',
            __('گزارشات', 'gzseo'),
            __('گزارشات', 'gzseo'),
            'manage_options',
            'gzseo-reports',
            array($this, 'render_reports_page')
        );
    }

    public function enqueue_admin_assets($hook)
    {
        // صفحات افزونه
        $gzseo_pages = array(
            'toplevel_page_gzseo-dashboard',
            'جی-زد-سئو_page_gzseo-reports',
            'جی-زد-سئو_page_gzseo-bulk-update'
        );

        // صفحات ویرایش پست‌ها
        $edit_pages = array(
            'post.php',
            'post-new.php',
            'term.php',
            'edit-tags.php'
        );
        //echo $hook;die();

        // بررسی صفحات افزونه یا صفحات ویرایش
        if ( ! in_array( urldecode( $hook ), $gzseo_pages, true ) && ! in_array( $hook, $edit_pages, true ) ) {
            return;
        }

        // فقط برای صفحات ویرایش، بررسی نوع پست
        if ( in_array( $hook, $edit_pages, true ) ) {
            global $post_type, $taxnow;

            // نوع‌های پست مجاز
            $allowed_post_types = array('post', 'page', 'product');

            // تکسونومی‌های مجاز
            $allowed_taxonomies = array('category', 'post_tag', 'product_cat', 'product_tag');

            // بررسی نوع پست
            if ( isset( $post_type ) && ! in_array( $post_type, $allowed_post_types, true ) ) {
                return;
            }

            // بررسی تکسونومی برای صفحات ترم
            if ( ( 'term.php' === $hook || 'edit-tags.php' === $hook ) && isset( $taxnow ) && ! in_array( $taxnow, $allowed_taxonomies, true ) ) {
                return;
            }
        }
        
        // Select2 CSS
        wp_enqueue_style(
            'select2',
            GZSEO_PLUGIN_URL . 'assets/css/select2.min.css',
            array(),
            GZSEO_VERSION
        );
        
        // Select2 JS
        wp_enqueue_script(
            'select2',
            GZSEO_PLUGIN_URL . 'assets/js/select2.min.js',
            array( 'jquery' ),
            GZSEO_VERSION,
            true
        );

        // Enqueue styles
        wp_enqueue_style(
            'gzseo-admin',
            GZSEO_PLUGIN_URL . 'assets/admin.css',
            array(),
            GZSEO_VERSION
        );

        // Enqueue Font Awesome
        wp_enqueue_style(
            'gzseo-fontawesome',
            GZSEO_PLUGIN_URL . 'assets/css/all.min.css',
            array(),
            GZSEO_VERSION
        );

        wp_enqueue_script(
            'gzseo-admin',
            GZSEO_PLUGIN_URL . 'assets/admin.js',
            array( 'jquery', 'select2' ),
            GZSEO_VERSION,
            true
        );

        // Localize script
        wp_localize_script(
            'gzseo-admin',
            'gzseo_ajax',
            array(
                'ajax_url'     => admin_url( 'admin-ajax.php' ),
                'nonce'        => wp_create_nonce( 'gzseo_ajax_nonce' ),
                'current_page' => $hook,
                'post_type'    => isset( $post_type ) ? $post_type : '',
                'post_id'      => get_the_ID(),
                'messages'     => array(
                    'confirm_delete' => __( 'آیا مطمئن هستید؟', 'gzseo' ),
                    'loading'        => __( 'در حال بارگذاری...', 'gzseo' ),
                    'error'          => __( 'خطایی رخ داده است', 'gzseo' ),
                    'success'        => __( 'عملیات با موفقیت انجام شد', 'gzseo' ),
                ),
            )
        );

        // Page-specific scripts should not run on every post/term editor.
        if ('جی-زد-سئو_page_gzseo-bulk-update' === urldecode($hook)) {
            wp_enqueue_script(
                'gzseo-bulk-update-page',
                GZSEO_PLUGIN_URL . 'assets/js/bulk-update-page.js',
                array('jquery'),
                GZSEO_VERSION,
                true
            );
        }

        if ('toplevel_page_gzseo-dashboard' === $hook) {
            wp_enqueue_script(
                'gzseo-featured-image-settings',
                GZSEO_PLUGIN_URL . 'assets/js/featured-image-settings.js',
                array('jquery'),
                GZSEO_VERSION,
                true
            );
        }

    }

    public function render_dashboard()
    {
        require_once GZSEO_PLUGIN_PATH . 'includes/admin-dashboard.php';
    }

    public function render_bulk_update_page()
    {
        require_once GZSEO_PLUGIN_PATH . 'includes/admin-bulk-update.php';
    }

    public function render_reports_page()
    {
        require_once GZSEO_PLUGIN_PATH . 'includes/admin-reports.php';
    }

    public function add_gzseo_metabox()
    {
        $post_types = array('post', 'page', 'product');

        foreach ($post_types as $post_type) {
            add_meta_box(
                'gzseo_metabox',
                __( 'امکانات افزونه جی زد سئو', 'gzseo' ),
                array($this, 'render_gzseo_metabox'),
                $post_type,
                'normal',
                'high'
            );
        }

        
    }

    public function render_gzseo_metabox($post)
    {
        wp_nonce_field('gzseo_metabox_nonce', 'gzseo_metabox_nonce');
        $this->render_gzseo_fields();
    }

    public function render_taxonomy_fields($term)
    {
        ?>
        <tr class="form-field">
            <td colspan="2">
                <?php $this->render_gzseo_fields(); ?>
            </td>
        </tr>
        <?php
    }

    public function render_taxonomy_fields_limited($term)
    {
        ?>
        <tr class="form-field">
            <td colspan="2">
                <?php $this->render_gzseo_fields_limited(); ?>
            </td>
        </tr>
        <?php
    }

    private function render_gzseo_fields()
    {
        $ai_credits = get_option('gzseo_ai_credits', 0);
        global $post;
        ?>
        <div class="gzseo-metabox-wrapper">
            <div class="gzseo-metabox-header">
                <div class="gzseo-metabox-title"><?php esc_html_e('امکانات افزونه جی زد سئو', 'gzseo'); ?></div>
                <div class="gzseo-metabox-credits">
                    <i class="fas fa-coins"></i>
                    <?php esc_html_e('اعتبار شما:', 'gzseo'); ?> <span class="gzseo-credits-amount"><?php echo esc_html( number_format_i18n( $ai_credits ) ); ?></span> <?php esc_html_e('توکن', 'gzseo'); ?>
                </div>
            </div>

            <div class="gzseo-metabox-tabs">
                <button type="button" class="gzseo-metabox-tab active" data-tab="content-update"><?php esc_html_e('آپدیت محتوایی', 'gzseo'); ?></button>
                <button type="button" class="gzseo-metabox-tab" data-tab="video-update"><?php esc_html_e('آپدیت ویدیویی', 'gzseo'); ?></button>
                <button type="button" class="gzseo-metabox-tab" data-tab="featured-image"><?php esc_html_e('تصویر شاخص', 'gzseo'); ?></button>
                <button type="button" class="gzseo-metabox-tab" data-tab="title-desc"><?php esc_html_e('عنوان و توضیحات پست', 'gzseo'); ?></button>
                <button type="button" class="gzseo-metabox-tab" data-tab="generate-comment"><?php esc_html_e('تولید کامنت', 'gzseo'); ?></button>
                <button type="button" class="gzseo-metabox-tab" data-tab="generate-table"><?php esc_html_e('تولید جدول', 'gzseo'); ?></button>
            </div>

            <div class="gzseo-metabox-content">
                <!-- تب آپدیت محتوایی -->
                <div class="gzseo-metabox-panel active" id="gzseo-content-update">
                    <?php
                    if (class_exists('GZSEO_Content_Update')) {
                        $content_update = new GZSEO_Content_Update();
                        $content_update->render_content($post);
                    } else {
                        echo '<p>' . esc_html__('ماژول آپدیت محتوایی یافت نشد.', 'gzseo') . '</p>';
                    }
                    ?>
                </div>

                <!-- تب آپدیت ویدیویی -->
                <div class="gzseo-metabox-panel" id="gzseo-video-update">
                    <?php
                    if (class_exists('GZSEO_Video_Update')) {
                        $video_update = new GZSEO_Video_Update();
                        $video_update->render_content($post);
                    } else {
                        echo '<p>' . esc_html__('ماژول آپدیت ویدیویی یافت نشد.', 'gzseo') . '</p>';
                    }
                    ?>
                </div>

                <!-- تب تصویر شاخص -->
                <div class="gzseo-metabox-panel" id="gzseo-featured-image">
                    <?php
                    if (class_exists('GZSEO_Featured_Image_AI')) {
                        $featured_image_ai = new GZSEO_Featured_Image_AI();
                        $featured_image_ai->render_content($post);
                    } else {
                        echo '<p>' . esc_html__('ماژول تولید تصویر با هوش مصنوعی یافت نشد.', 'gzseo') . '</p>';
                    }
                    ?>
                </div>

                <!-- تب عنوان و توضیحات -->
                <div class="gzseo-metabox-panel" id="gzseo-title-desc">
                    <?php
                    if (class_exists('GZSEO_Title_Description_Generator')) {
                        $title_desc_generator = new GZSEO_Title_Description_Generator();
                        $title_desc_generator->render_content($post);
                    } else {
                        echo '<p>' . esc_html__('ماژول تولید عنوان و توضیحات با هوش مصنوعی یافت نشد.', 'gzseo') . '</p>';
                    }
                    ?>
                </div>

                <!-- تب تولید کامنت -->
                <div class="gzseo-metabox-panel" id="gzseo-generate-comment">
                    <?php
                    if (class_exists('GZSEO_Comment_Generator_AI')) {
                        $comment_generator_ai = new GZSEO_Comment_Generator_AI();
                        $comment_generator_ai->render_content($post);
                    } else {
                        echo '<p>' . esc_html__('ماژول تولید کامنت با هوش مصنوعی یافت نشد.', 'gzseo') . '</p>';
                    }
                    ?>
                </div>

                <!-- تب تولید جدول -->
                <div class="gzseo-metabox-panel" id="gzseo-generate-table">
                    <?php
                    if (class_exists('GZSEO_Table_Generator')) {
                        $table_generator_ai = new GZSEO_Table_Generator();
                        $table_generator_ai->render_content($post);
                    } else {
                        echo '<p>' . esc_html__('ماژول تولید جدول با هوش مصنوعی یافت نشد.', 'gzseo') . '</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_gzseo_fields_limited()
    {
        $ai_credits = get_option('gzseo_ai_credits', 0);
        global $tag; // برای taxonomy از $tag استفاده می‌کنیم
        ?>
        <div class="gzseo-metabox-wrapper">
            <div class="gzseo-metabox-header">
                <div class="gzseo-metabox-title"><?php esc_html_e('امکانات افزونه جی زد سئو', 'gzseo'); ?></div>
                <div class="gzseo-metabox-credits">
                    <i class="fas fa-coins"></i>
                    <?php esc_html_e('اعتبار شما:', 'gzseo'); ?> <span class="gzseo-credits-amount"><?php echo esc_html( number_format_i18n( $ai_credits ) ); ?></span> <?php esc_html_e('توکن', 'gzseo'); ?>
                </div>
            </div>

            <div class="gzseo-metabox-tabs">
                <button type="button" class="gzseo-metabox-tab active" data-tab="content-update"><?php esc_html_e('آپدیت محتوایی', 'gzseo'); ?></button>
                <button type="button" class="gzseo-metabox-tab" data-tab="video-update"><?php esc_html_e('آپدیت ویدیویی', 'gzseo'); ?></button>
                <button type="button" class="gzseo-metabox-tab" data-tab="title-desc"><?php esc_html_e('عنوان و توضیحات پست', 'gzseo'); ?></button>
                <button type="button" class="gzseo-metabox-tab" data-tab="generate-table"><?php esc_html_e('تولید جدول', 'gzseo'); ?></button>
            </div>

            <div class="gzseo-metabox-content">
                <!-- تب آپدیت محتوایی -->
                <div class="gzseo-metabox-panel active" id="gzseo-content-update">
                    <?php
                    if (class_exists('GZSEO_Content_Update')) {
                        $content_update = new GZSEO_Content_Update();
                        $content_update->render_taxonomy_content($tag);
                    } else {
                        echo '<p>' . esc_html__('ماژول آپدیت محتوایی یافت نشد.', 'gzseo') . '</p>';
                    }
                    ?>
                </div>

                <!-- تب آپدیت ویدیویی -->
                <div class="gzseo-metabox-panel" id="gzseo-video-update">
                    <?php
                    if (class_exists('GZSEO_Video_Update')) {
                        $video_update = new GZSEO_Video_Update();
                        $video_update->render_taxonomy_content($tag);
                    } else {
                        echo '<p>' . esc_html__('ماژول آپدیت ویدیویی یافت نشد.', 'gzseo') . '</p>';
                    }
                    ?>
                </div>

                <!-- تب عنوان و توضیحات -->
                <div class="gzseo-metabox-panel" id="gzseo-title-desc">
                    <?php
                    if (class_exists('GZSEO_Title_Description_Generator')) {
                        $title_desc_generator = new GZSEO_Title_Description_Generator();
                        $title_desc_generator->render_taxonomy_content($tag);
                    } else {
                        echo '<p>' . esc_html__('ماژول تولید عنوان و توضیحات با هوش مصنوعی یافت نشد.', 'gzseo') . '</p>';
                    }
                    ?>
                </div>

                <!-- تب تولید جدول -->
                <div class="gzseo-metabox-panel" id="gzseo-generate-table">
                    <?php
                    if (class_exists('GZSEO_Table_Generator')) {
                        $table_generator_ai = new GZSEO_Table_Generator();
                        $table_generator_ai->render_taxonomy_content($tag);
                    } else {
                        echo '<p>' . esc_html__('ماژول تولید جدول با هوش مصنوعی یافت نشد.', 'gzseo') . '</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    // Helper methods
    private function is_google_connected()
    {
        return get_option('gzseo_google_connected', false);
    }

    // AJAX handlers
    public function save_settings()
    {
        // Verify nonce and capability BEFORE any other processing.
        if ( ! GZSEO_Security_Helpers::verify_ajax_request( 'gzseo_nonce', 'manage_options' ) ) {
            return;
        }

        // Save settings logic here

        wp_send_json_success(
            array(
                'message' => __( 'تنظیمات با موفقیت ذخیره شد', 'gzseo' ),
            )
        );
    }

    public function refresh_dashboard()
    {
        // Verify nonce and capability BEFORE any other processing.
        if ( ! GZSEO_Security_Helpers::verify_ajax_request( 'gzseo_nonce', 'manage_options' ) ) {
            return;
        }

        // Refresh dashboard logic here

        wp_send_json_success(
            array(
                'message' => __( 'داشبورد بروزرسانی شد', 'gzseo' ),
            )
        );
    }

    public function auth_google()
    {
        // Verify nonce and capability BEFORE any other processing.
        if ( ! GZSEO_Security_Helpers::verify_ajax_request( 'gzseo_nonce', 'manage_options' ) ) {
            return;
        }

        // Google auth logic here

        wp_send_json_success(
            array(
                'redirect_url' => 'https://accounts.google.com/oauth/authorize',
            )
        );
    }

    public function disconnect_google()
    {
        check_ajax_referer( 'gzseo_nonce', 'nonce' );

        // Check user permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'شما دسترسی لازم را ندارید', 'gzseo' ),
                )
            );
            return;
        }

        update_option('gzseo_google_connected', false);

        wp_send_json_success(
            array(
                'message' => __( 'اتصال قطع شد', 'gzseo' ),
            )
        );
    }

    public function save_bulk_update()
    {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        $wpnonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

        $nonce_verified = false;

        if ( $nonce && wp_verify_nonce( $nonce, 'gzseo_ajax_nonce' ) ) {
            $nonce_verified = true;
        } elseif ( $wpnonce && wp_verify_nonce( $wpnonce, 'gzseo_ajax_nonce' ) ) {
            $nonce_verified = true;
        }
        
        if (!$nonce_verified) {
            wp_send_json_error(array(
                'message' => __('خطای امنیتی - nonce نامعتبر است', 'gzseo')
            ));
            return;
        }
        
        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('شما مجوز لازم برای انجام این عملیات را ندارید', 'gzseo')
            ));
            return;
        }

        $action = GZSEO_Security_Helpers::get_post('bulk_action', 'add');

        if ( 'delete' === $action ) {
            // حذف آیتم
            $index = GZSEO_Security_Helpers::get_post_int('index', 0);
            $saved_items = get_option('gzseo_bulk_update_items', array());

            if (isset($saved_items[$index])) {
                unset($saved_items[$index]);
                $saved_items = array_values($saved_items); // Re-index array
                update_option('gzseo_bulk_update_items', $saved_items);
                wp_send_json_success(
                    array(
                        'message' => __( 'آیتم با موفقیت حذف شد', 'gzseo' ),
                    )
                );
            } else {
                wp_send_json_error(
                    array(
                        'message' => __( 'آیتم یافت نشد', 'gzseo' ),
                    )
                );
            }
            return;
        }

        if ( 'update' === $action ) {
            // ویرایش آیتم
            $index = GZSEO_Security_Helpers::get_post_int('index', 0);
            $saved_items = get_option('gzseo_bulk_update_items', array());

            if (isset($saved_items[$index])) {
                $selected_terms = isset( $_POST['selected_terms'] ) && is_array( $_POST['selected_terms'] )
                    ? array_map( 'sanitize_text_field', wp_unslash( $_POST['selected_terms'] ) )
                    : array();

                $saved_items[$index] = array(
                    'post_type' => GZSEO_Security_Helpers::get_post('post_type'),
                    'last_update' => GZSEO_Security_Helpers::get_post_int('last_update', 30),
                    'update_type' => GZSEO_Security_Helpers::get_post('update_type'),
                    'taxonomy_type' => GZSEO_Security_Helpers::get_post('taxonomy_type'),
                    'selected_terms' => $selected_terms
                );

                update_option('gzseo_bulk_update_items', $saved_items);
                wp_send_json_success(
                    array(
                        'message' => __( 'آیتم با موفقیت بروزرسانی شد', 'gzseo' ),
                    )
                );
            } else {
                wp_send_json_error(
                    array(
                        'message' => __( 'آیتم یافت نشد', 'gzseo' ),
                    )
                );
            }
            return;
        }

        // افزودن آیتم جدید
        $selected_terms = isset( $_POST['selected_terms'] ) && is_array( $_POST['selected_terms'] )
            ? array_map( 'sanitize_text_field', wp_unslash( $_POST['selected_terms'] ) )
            : array();

        $new_item = array(
            'post_type' => GZSEO_Security_Helpers::get_post('post_type'),
            'last_update' => GZSEO_Security_Helpers::get_post_int('last_update', 30),
            'update_type' => GZSEO_Security_Helpers::get_post('update_type'),
            'taxonomy_type' => GZSEO_Security_Helpers::get_post('taxonomy_type'),
            'selected_terms' => $selected_terms
        );

        $saved_items = get_option('gzseo_bulk_update_items', array());
        $saved_items[] = $new_item;

        update_option('gzseo_bulk_update_items', $saved_items);

        wp_send_json_success(
            array(
                'message' => __( 'آیتم جدید با موفقیت ذخیره شد', 'gzseo' ),
            )
        );
    }

    public function get_taxonomy_terms()
    {
        // Verify nonce and capability BEFORE reading any input
        if ( ! GZSEO_Security_Helpers::verify_ajax_request( 'gzseo_ajax_nonce', 'manage_options' ) ) {
            return;
        }

        $taxonomy_type = GZSEO_Security_Helpers::get_post('taxonomy_type');
        $post_type = GZSEO_Security_Helpers::get_post('post_type');

        $terms_html = '';

        if ($taxonomy_type === 'all') {
            // دریافت تمام تکسونومی‌های مرتبط با post type
            $taxonomies = get_object_taxonomies($post_type, 'objects');

            foreach ($taxonomies as $taxonomy) {
                if ($taxonomy->public && $taxonomy->show_ui) {
                    $terms = get_terms(array(
                        'taxonomy' => $taxonomy->name,
                        'hide_empty' => false
                    ));

                    if (!is_wp_error($terms) && !empty($terms)) {
                        $terms_html .= '<div class="gzseo-taxonomy-group">';
                        $terms_html .= '<h4>' . esc_html($taxonomy->label) . '</h4>';

                        foreach ($terms as $term) {
                            $terms_html .= '<label>';
                            $terms_html .= '<input type="checkbox" name="selected_terms[]" value="' . esc_attr($term->term_id) . '">';
                            $terms_html .= ' ' . esc_html($term->name);
                            $terms_html .= '</label>';
                        }

                        $terms_html .= '</div>';
                    }
                }
            }
        } else {
            // دریافت ترم‌های یک تکسونومی خاص
            $terms = get_terms(array(
                'taxonomy' => $taxonomy_type,
                'hide_empty' => false
            ));

            if (!is_wp_error($terms) && !empty($terms)) {
                $terms_html .= '<div class="gzseo-taxonomy-group">';

                foreach ($terms as $term) {
                    $terms_html .= '<label>';
                    $terms_html .= '<input type="checkbox" name="selected_terms[]" value="' . esc_attr($term->term_id) . '">';
                    $terms_html .= ' ' . esc_html($term->name);
                    $terms_html .= '</label>';
                }

                $terms_html .= '</div>';
            }
        }

        if ( empty( $terms_html ) ) {
            $terms_html = '<p>' . esc_html__( 'هیچ ترمی یافت نشد.', 'gzseo' ) . '</p>';
        }

        wp_send_json_success(
            array(
                'html' => $terms_html,
            )
        );
    }

    public function bulk_preview()
    {
        // Verify nonce and capability BEFORE reading any input
        if ( ! GZSEO_Security_Helpers::verify_ajax_request( 'gzseo_ajax_nonce', 'manage_options' ) ) {
            return;
        }

        $index = GZSEO_Security_Helpers::get_post_int( 'index', 0 );
        $count = max(1, min(50, GZSEO_Security_Helpers::get_post_int('count', 10)));

        $saved_items = get_option( 'gzseo_bulk_update_items', array() );

        if ( ! isset( $saved_items[ $index ] ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'آیتم یافت نشد', 'gzseo' ),
                )
            );
            return;
        }

        $item = $saved_items[$index];

        // Query arguments
        $args = array(
            'post_type' => $item['post_type'],
            'posts_per_page' => $count,
            'post_status' => 'publish', // فقط پست‌های منتشر شده
            'orderby' => 'modified',
            'order' => 'ASC',
            'date_query' => array(
                array(
                    'column' => 'post_modified',
                    'before' => $item['last_update'] . ' days ago'
                )
            )
        );

        // Add taxonomy query if terms selected
        if (!empty($item['selected_terms'])) {
            $tax_query = array();
            $terms_by_tax = array();

            foreach ($item['selected_terms'] as $term_id) {
                $term = get_term($term_id);
                if ($term && !is_wp_error($term)) {
                    if (!isset($terms_by_tax[$term->taxonomy])) {
                        $terms_by_tax[$term->taxonomy] = array();
                    }
                    $terms_by_tax[$term->taxonomy][] = $term_id;
                }
            }

            foreach ($terms_by_tax as $taxonomy => $term_ids) {
                $tax_query[] = array(
                    'taxonomy' => $taxonomy,
                    'field' => 'term_id',
                    'terms' => $term_ids,
                    'operator' => 'IN'
                );
            }

            if (!empty($tax_query)) {
                $tax_query['relation'] = 'OR';
                $args['tax_query'] = $tax_query;
            }
        }

        $query = new WP_Query($args);
        $posts = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $posts[] = array(
                    'id'    => get_the_ID(),
                    'title' => get_the_title(),
                );
            }
            wp_reset_postdata();
        }

        wp_send_json_success(
            array(
                'posts' => $posts,
            )
        );
    }

    public function bulk_execute()
    {
        // Verify nonce and capability BEFORE reading any input
        if ( ! GZSEO_Security_Helpers::verify_ajax_request( 'gzseo_ajax_nonce', 'manage_options' ) ) {
            return;
        }

        // لود کردن کلاس اجرای آپدیت دسته جمعی
        require_once GZSEO_PLUGIN_PATH . 'includes/class-gzseo-bulk-execute.php';

        $index  = GZSEO_Security_Helpers::get_post_int( 'index', 0 );
        $offset = max(0, GZSEO_Security_Helpers::get_post_int('offset', 0));
        $count  = max(1, min(20, GZSEO_Security_Helpers::get_post_int('count', 10)));

        $saved_items = get_option( 'gzseo_bulk_update_items', array() );

        if ( ! isset( $saved_items[ $index ] ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'آیتم یافت نشد', 'gzseo' ),
                )
            );
            return;
        }

        $item = $saved_items[$index];

        // Query arguments
        $args = array(
            'post_type' => $item['post_type'],
            'posts_per_page' => $count,
            'post_status' => 'publish', // فقط پست‌های منتشر شده
            'offset' => $offset,
            'orderby' => 'modified',
            'order' => 'ASC',
            'date_query' => array(
                array(
                    'column' => 'post_modified',
                    'before' => $item['last_update'] . ' days ago'
                )
            )
        );

        // Add taxonomy query if terms selected
        if (!empty($item['selected_terms'])) {
            $tax_query = array();
            $terms_by_tax = array();

            foreach ($item['selected_terms'] as $term_id) {
                $term = get_term($term_id);
                if ($term && !is_wp_error($term)) {
                    if (!isset($terms_by_tax[$term->taxonomy])) {
                        $terms_by_tax[$term->taxonomy] = array();
                    }
                    $terms_by_tax[$term->taxonomy][] = $term_id;
                }
            }

            foreach ($terms_by_tax as $taxonomy => $term_ids) {
                $tax_query[] = array(
                    'taxonomy' => $taxonomy,
                    'field' => 'term_id',
                    'terms' => $term_ids,
                    'operator' => 'IN'
                );
            }

            if (!empty($tax_query)) {
                $tax_query['relation'] = 'OR';
                $args['tax_query'] = $tax_query;
            }
        }

        $query = new WP_Query($args);
        $processed = 0;
        $logs = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $post_title = get_the_title();

                // Execute update based on type
                $result = array();

                switch ($item['update_type']) {
                    case 'table':
                        $result = GZSEO_Bulk_Execute::execute_table_update($post_id, true);
                        break;

                    case 'comment':
                        $result = GZSEO_Bulk_Execute::execute_comment_update($post_id, true);
                        break;

                    case 'content_update':
                        $result = GZSEO_Bulk_Execute::execute_content_update($post_id, true);
                        break;

                    case 'video_update':
                        $result = GZSEO_Bulk_Execute::execute_video_update($post_id, true);
                        break;

                    case 'feature_img':
                        $result = GZSEO_Bulk_Execute::execute_featured_image_update($post_id, true);
                        break;

                    default:
                        $result = array(
                            'success' => false,
                            'message' => __('نوع آپدیت نامعتبر است', 'gzseo')
                        );
                }

                // Add to logs
                $logs[] = array(
                    'status'  => $result['success'] ? 'success' : 'error',
                    'message' => sprintf(
                        '%s: %s - %s',
                        $result['success'] ? '✓' : '✗',
                        $post_title,
                        $result['message']
                    ),
                );

                if ($result['success']) {
                    $processed++;
                }
            }
            wp_reset_postdata();
        }

        $main_instance = GZSeo::get_instance();
        if ( method_exists( $main_instance, 'update_ai_credits' ) ) {
            $main_instance->update_ai_credits( true );
        }

        wp_send_json_success(
            array(
                'processed' => $processed,
                /* translators: %d: Number of processed posts. */
                'message'   => sprintf( __( '%d مورد با موفقیت آپدیت شد', 'gzseo' ), $processed ),
                'logs'      => $logs,
            )
        );
    }

    public function save_config() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        $wpnonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

        $nonce_verified = false;

        if ( $nonce && wp_verify_nonce( $nonce, 'gzseo_ajax_nonce' ) ) {
            $nonce_verified = true;
        } elseif ( $wpnonce && wp_verify_nonce( $wpnonce, 'gzseo_ajax_nonce' ) ) {
            $nonce_verified = true;
        }
        
        if (!$nonce_verified) {
            wp_send_json_error(array(
                'message' => __('خطای امنیتی - nonce نامعتبر است', 'gzseo')
            ));
            return;
        }
        
        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('شما مجوز لازم برای انجام این عملیات را ندارید', 'gzseo')
            ));
            return;
        }
        
        $config_type = GZSEO_Security_Helpers::get_post('config_type');
        
        // Properly check, unslash, and prepare config array for sanitization
        $config = array();
        if ( isset( $_POST['config'] ) && is_array( $_POST['config'] ) ) {
            $config = array_map( 'sanitize_text_field', wp_unslash( $_POST['config'] ) );
        }
        
        switch ( $config_type ) {
            case 'content-update':
                // ذخیره تنظیمات آپدیت محتوا
                $accordion_display = isset( $config['accordion_display'] ) && 'true' === $config['accordion_display'] ? 1 : 0;
                $accordion_title   = isset( $config['accordion_title'] ) ? sanitize_text_field( $config['accordion_title'] ) : '';
                $text_position     = isset( $config['text_position'] ) ? sanitize_text_field( $config['text_position'] ) : '';
                $video_position    = isset( $config['video_position'] ) ? sanitize_text_field( $config['video_position'] ) : '';

                update_option('gzseo_accordion_display', $accordion_display);
                update_option('gzseo_accordion_title', $accordion_title);
                update_option('gzseo_text_position', $text_position);
                update_option('gzseo_video_position', $video_position);

                wp_send_json_success(
                    array(
                        'message' => __( 'تنظیمات آپدیت محتوا با موفقیت ذخیره شد', 'gzseo' ),
                    )
                );
                break;

            case 'comment-management':
                // ذخیره تنظیمات مدیریت کامنت
                $tone       = isset( $config['tone'] ) ? sanitize_text_field( $config['tone'] ) : '';
                $word_count = isset( $config['word_count'] ) ? intval( $config['word_count'] ) : 0;

                update_option('gzseo_comment_tone', $tone);
                update_option('gzseo_comment_word_count', $word_count);

                wp_send_json_success(
                    array(
                        'message' => __( 'تنظیمات مدیریت کامنت با موفقیت ذخیره شد', 'gzseo' ),
                    )
                );
                break;

            case 'table-style':
                // این بخش قبلا در کلاس GZSEO_Table_Styles مدیریت می‌شود
                wp_send_json_error(
                    array(
                        'message' => __( 'از اکشن مخصوص table styles استفاده کنید', 'gzseo' ),
                    )
                );
                break;

            case 'schema':
                // ذخیره تنظیمات اسکیما (برای استفاده‌های آینده)
                // Sanitize the entire config array recursively before saving
                $sanitized_config = $this->sanitize_config_array( $config );
                update_option('gzseo_schema_settings', $sanitized_config);

                wp_send_json_success(
                    array(
                        'message' => __( 'تنظیمات اسکیما با موفقیت ذخیره شد', 'gzseo' ),
                    )
                );
                break;

            default:
                wp_send_json_error(
                    array(
                        'message' => __( 'نوع پیکربندی نامعتبر است', 'gzseo' ),
                    )
                );
                break;
        }
    }

    /**
     * Recursively sanitize configuration array
     * 
     * @param array $config The configuration array to sanitize
     * @return array Sanitized configuration array
     */
    private function sanitize_config_array( $config ) {
        if ( ! is_array( $config ) ) {
            return array();
        }

        $sanitized = array();
        
        foreach ( $config as $key => $value ) {
            // Sanitize the key
            $sanitized_key = sanitize_key( $key );
            
            // Sanitize the value based on its type
            if ( is_array( $value ) ) {
                // Recursively sanitize nested arrays
                $sanitized[ $sanitized_key ] = $this->sanitize_config_array( $value );
            } elseif ( is_numeric( $value ) ) {
                // Handle numeric values
                $sanitized[ $sanitized_key ] = is_float( $value ) ? floatval( $value ) : intval( $value );
            } elseif ( is_bool( $value ) ) {
                // Handle boolean values
                $sanitized[ $sanitized_key ] = (bool) $value;
            } elseif ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
                // Handle URLs
                $sanitized[ $sanitized_key ] = esc_url_raw( $value );
            } else {
                // Default to sanitize_text_field for strings
                $sanitized[ $sanitized_key ] = sanitize_text_field( $value );
            }
        }
        
        return $sanitized;
    }

    /**
    * به‌روزرسانی موجودی حساب کاربری
    */
    public function update_ai_credits($force_update = false) {
        // بررسی وضعیت لاگین
        if ( ! $this->is_ai_connected() ) {
            return false;
        }
        
        // بررسی کش و جلوگیری از درخواست‌های مکرر
        $cache_key = 'gzseo_ai_credits_last_check';
        $last_check   = get_transient( $cache_key );
        $current_time = time();

        // اگر force_update نباشد و کمتر از 5 دقیقه از آخرین بررسی گذشته باشد
        if ( ! $force_update && $last_check && ( $current_time - $last_check ) < 300 ) {
            return get_option( 'gzseo_ai_credits', 0 );
        }
        
        // دریافت اطلاعات کاربری
        $email    = get_option( 'gzseo_ai_email', '' );
        $password = GZSEO_Security_Helpers::get_ai_password();
        
        if ( empty( $email ) || empty( $password ) ) {
            return false;
        }
        
        // ارسال درخواست به API
        $response = wp_remote_post(
            'https://api.gzseo.in/login',
            array(
                'method'  => 'POST',
                'timeout' => 30,
                'limit_response_size' => 2 * MB_IN_BYTES,
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body'    => wp_json_encode(
                    array(
                        'email'    => $email,
                        'password' => $password,
                    )
                ),
            )
        );
        
        // بررسی خطا
        if ( is_wp_error( $response ) ) {
            // ذخیره زمان خطا برای تلاش بعدی
            set_transient( 'gzseo_ai_api_error', true, 300 ); // 5 دقیقه
            return get_option( 'gzseo_ai_credits', 0 );
        }
        
        $body        = wp_remote_retrieve_body( $response );
        $data        = json_decode( $body, true );
        $status_code = wp_remote_retrieve_response_code( $response );
        
        if ( 200 === $status_code && isset( $data['words'] ) ) {
            // به‌روزرسانی موجودی
            update_option( 'gzseo_ai_credits', $data['words'] );
            update_option( 'gzseo_ai_user_data', $data );
            update_option( 'gzseo_ai_last_update', current_time( 'mysql' ) );

            // ذخیره زمان آخرین بررسی موفق
            set_transient( $cache_key, $current_time, 300 ); // 5 دقیقه

            return $data['words'];
        }
        
        // در صورت خطا، مقدار قبلی را برگردان
        return get_option( 'gzseo_ai_credits', 0 );
    }

    /**
    * ارسال درخواست API با قابلیت retry
    */
    private function make_api_request_with_retry( $url, $data, $max_retries = 2 ) {
        $attempt = 0;
        $timeout = 5; // شروع با 5 ثانیه timeout

        while ( $attempt < $max_retries ) {
            $response = wp_remote_post(
                $url,
                array(
                    'method'      => 'POST',
                    'timeout'     => $timeout,
                    'limit_response_size' => 2 * MB_IN_BYTES,
                    'headers'     => array(
                        'Content-Type' => 'application/json',
                    ),
                    'body'        => wp_json_encode( $data ),
                    'sslverify'   => true,
                    'data_format' => 'body',
                )
            );
            
            // بررسی خطا
            if ( ! is_wp_error( $response ) ) {
                $status_code = wp_remote_retrieve_response_code( $response );

                if ( 200 === $status_code ) {
                    $body   = wp_remote_retrieve_body( $response );
                    $result = json_decode( $body, true );

                    if ( JSON_ERROR_NONE === json_last_error() ) {
                        return $result;
                    }
                }

                // اگر سرور جواب داد ولی خطا بود (مثلا 401)
                if ( $status_code >= 400 && $status_code < 500 ) {
                    // خطای client-side، retry نکن
                    $this->log_api_error( __( 'Client error', 'gzseo' ), $status_code );
                    return false;
                }
            } else {
                // log error for debugging
                $this->log_api_error( $response->get_error_message(), $attempt + 1 );
            }

            $attempt++;

            // افزایش timeout برای تلاش بعدی
            $timeout = min( $timeout * 1.5, 15 ); // حداکثر 15 ثانیه

            // صبر قبل از تلاش مجدد (exponential backoff)
            if ( $attempt < $max_retries ) {
            // Do not block the PHP worker inside an AJAX request. The next
            // retry is immediate; transport timeouts already provide pacing.
            }
        }
        
        // در صورت شکست همه تلاش‌ها
        set_transient( 'gzseo_ai_api_error', true, 300 ); // 5 دقیقه نشانه خطا
        return false;
    }

    /**
    * لاگ خطاهای API (اختیاری)
    */
    private function log_api_error( $message, $context = '' ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            /* translators: %1$s: Error message, %2$s: Context, %3$s: Time. */
            $log_message = sprintf(
                '[GZSEO AI] ' . __( 'API Error:', 'gzseo' ) . ' %1$s | ' . __( 'Context:', 'gzseo' ) . ' %2$s | ' . __( 'Time:', 'gzseo' ) . ' %3$s',
                $message,
                $context,
                current_time( 'mysql' )
            );

            // Intentionally avoid logging in production.
            unset( $log_message );
        }
    }

    /**
    * تابع کمکی برای دریافت موجودی با کش
    */
    public function get_cached_ai_credits() {
        // اگر API دچار خطا شده، از کش استفاده کن
        if ( get_transient( 'gzseo_ai_api_error' ) ) {
            return get_option( 'gzseo_ai_credits', 0 );
        }
        
        // در غیر این صورت سعی کن به‌روزرسانی کن
        return $this->update_ai_credits();
    }

    /**
    * AJAX handler برای به‌روزرسانی موجودی
    */
    public function ajax_update_ai_credits() {
        // Verify nonce and capability BEFORE any other processing.
        if ( ! GZSEO_Security_Helpers::verify_ajax_request( 'gzseo_ajax_nonce', 'manage_options' ) ) {
            return;
        }

        $credits = $this->update_ai_credits( true ); // force update

        if ( $credits !== false ) {
            wp_send_json_success(
                array(
                    'credits'     => number_format_i18n( $credits ),
                    'raw_credits' => $credits,
                )
            );
        } else {
            // حتی در صورت خطا، مقدار کش شده را برگردان
            $cached_credits = get_option( 'gzseo_ai_credits', 0 );
            wp_send_json_success(
                array(
                    'credits'     => number_format_i18n( $cached_credits ),
                    'raw_credits' => $cached_credits,
                    'cached'      => true,
                )
            );
        }
    }

    /**
    * تابع استاتیک برای دسترسی آسان
    */
    public static function get_updated_credits($force = false) {
        $instance = self::get_instance();
        return $instance->update_ai_credits($force);
    }

    public function check_and_run_migration() {
        // اجرا فقط در پنل ادمین
        if (!is_admin()) {
            return;
        }
        
        // بررسی و اجرای migration برای AI
        if (GZSEO_AI_Migration::check_migration_needed()) {
            GZSEO_AI_Migration::migrate();
        }
    
        // بررسی و اجرای migration برای Search Console
        if (GZSEO_Search_Console_Migration::check_migration_needed()) {
            GZSEO_Search_Console_Migration::migrate();
        }
        
        // لود کردن Migration Manager برای بقیه migrations
        require_once GZSEO_PLUGIN_PATH . 'includes/migrations/class-gzseo-migration-manager.php';
        
        // اجرای migrations اتوماتیک
        GZSEO_Migration_Manager::run_auto_migrations();
        update_option('gzseo_version', GZSEO_VERSION);
    }
}

// Initialize plugin
add_action('plugins_loaded', function () {
    GZSeo::get_instance();
});

// Activation hook
register_activation_hook(__FILE__, function () {
    // Create necessary database tables or options
    update_option('gzseo_version', GZSEO_VERSION);

    require_once GZSEO_PLUGIN_PATH . 'includes/migrations/class-gzseo-ai-migration.php';
    GZSEO_AI_Migration::migrate();

    // Migration برای Search Console
    require_once GZSEO_PLUGIN_PATH . 'includes/migrations/class-gzseo-search-console-migration.php';
    GZSEO_Search_Console_Migration::migrate();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function () {
    // Cleanup if necessary

    // Clear transient on deactivation
    delete_transient('gzseo_last_scheduled_check');
    wp_clear_scheduled_hook('gzseo_check_scheduled_comments');
});
