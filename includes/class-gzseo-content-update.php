<?php
/**
 * GZSEO Content Update Module
 * 
 * @package GZSEO
 * @subpackage Content_Update
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class GZSEO_Content_Update {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX hooks for content generation
        add_action('wp_ajax_gzseo_generate_content', array($this, 'ajax_generate_content'));
        add_action('wp_ajax_gzseo_save_generated_content', array($this, 'ajax_save_generated_content'));
        add_action('wp_ajax_gzseo_get_saved_contents', array($this, 'ajax_get_saved_contents'));
        add_action('wp_ajax_gzseo_delete_saved_content', array($this, 'ajax_delete_saved_content'));
        add_action('wp_ajax_gzseo_update_saved_content', array($this, 'ajax_update_saved_content'));
        
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Hook for displaying content updates
        add_filter('the_content', array($this, 'display_content_updates'), 20);
        
        add_action('wp_ajax_gzseo_generate_taxonomy_content', array($this, 'ajax_generate_taxonomy_content'));
        add_action('wp_ajax_gzseo_save_taxonomy_content', array($this, 'ajax_save_taxonomy_content'));
        add_filter('term_description', array($this, 'display_taxonomy_content_updates'), 20, 2);
    }
    
    public function enqueue_assets()
    {
        // Content Update Module
        wp_enqueue_style(
            'gzseo-content-update',
            GZSEO_PLUGIN_URL . 'assets/css/content-update.css',
            array(),
            GZSEO_VERSION
        );
        
        wp_enqueue_script(
            'gzseo-content-update',
            GZSEO_PLUGIN_URL . 'assets/js/content-update.js',
            array('jquery'),
            GZSEO_VERSION,
            true
        );
        
        wp_localize_script('gzseo-content-update', 'gzseo_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gzseo_ajax_nonce'),
            'post_id' => absint( get_the_ID() ),
            'messages' => array(
                'generating'      => __( 'در حال تولید محتوا...', 'gzseo' ),
                'success'         => __( 'محتوا با موفقیت تولید شد', 'gzseo' ),
                'error'           => __( 'خطا در تولید محتوا', 'gzseo' ),
                'confirm_delete'  => __( 'آیا از حذف این محتوا مطمئن هستید؟', 'gzseo' ),
                'saving'          => __( 'در حال ذخیره...', 'gzseo' ),
                'saved'           => __( 'ذخیره شد', 'gzseo' ),
                'publishing'      => __( 'در حال انتشار...', 'gzseo' ),
                'published'       => __( 'منتشر شد', 'gzseo' )
            )
        ));
    }
    
    /**
     * Render content in metabox
     */
    public function render_content($post) {
        // Get saved content updates for this post
        $saved_contents = $this->get_saved_contents($post->ID);
        $ai_credits = get_option('gzseo_ai_credits', 0);

        // Get AI credentials
        $is_connected = get_option('gzseo_ai_logged_in', false);
        ?>
        <div class="gzseo-content-update-wrapper">
            <?php if (!$is_connected): ?>
                <div class="gzseo-ai-login-notice">
                    <i class="fas fa-exclamation-circle"></i>
                    <p><?php esc_html_e( 'برای استفاده از قابلیت آپدیت محتوایی با هوش مصنوعی، ابتدا باید وارد حساب کاربری خود شوید.', 'gzseo' ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=gzseo-dashboard#ai' ) ); ?>" class="button button-primary">
                        <?php esc_html_e( 'ورود به حساب هوش مصنوعی', 'gzseo' ); ?>
                    </a>
                </div>
            <?php else: ?>
                <!-- Content Generation Form -->
                <div class="gzseo-content-form">
                    <div class="gzseo-form-row">
                        <div class="gzseo-form-group">
                            <label for="gzseo-paragraph-count">
                                <i class="fas fa-paragraph"></i>
                                <?php esc_html_e( 'تعداد پاراگراف', 'gzseo' ); ?>
                            </label>
                            <input type="number" id="gzseo-paragraph-count" class="gzseo-input" 
                                value="3" min="1" max="10" required>
                        </div>
                        
                        <div class="gzseo-form-group">
                            <label for="gzseo-min-words">
                                <i class="fas fa-minus"></i>
                                <?php esc_html_e( 'حداقل کلمات', 'gzseo' ); ?>
                            </label>
                            <input type="number" id="gzseo-min-words" class="gzseo-input" 
                                value="200" min="50" max="1000" required>
                        </div>
                        
                        <div class="gzseo-form-group">
                            <label for="gzseo-max-words">
                                <i class="fas fa-plus"></i>
                                <?php esc_html_e( 'حداکثر کلمات', 'gzseo' ); ?>
                            </label>
                            <input type="number" id="gzseo-max-words" class="gzseo-input" 
                                value="350" min="100" max="2000" required>
                        </div>
                    </div>
                    
                    <div class="gzseo-form-actions">
                        <button type="button" class="gzseo-btn gzseo-btn-primary" id="gzseo-generate-content">
                            <i class="fas fa-magic"></i>
                            <?php esc_html_e( 'تولید محتوا با هوش مصنوعی', 'gzseo' ); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Generated Content Preview -->
                <div class="gzseo-content-preview" id="gzseo-content-preview" style="display: none;">
                    <div class="gzseo-preview-header">
                        <h4>
                            <i class="fas fa-eye"></i>
                            <?php esc_html_e( 'پیش‌نمایش محتوای تولید شده', 'gzseo' ); ?>
                        </h4>
                        <button type="button" class="gzseo-preview-close" id="gzseo-close-preview">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="gzseo-preview-content" id="gzseo-preview-content" contenteditable="true">
                        <!-- Generated content will be displayed here -->
                    </div>
                    
                    <div class="gzseo-preview-actions">
                        <div class="gzseo-position-selector">
                            <label for="gzseo-content-position">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php esc_html_e( 'موقعیت نمایش:', 'gzseo' ); ?>
                            </label>
                            <select id="gzseo-content-position" class="gzseo-select">
                                <option value="before_content"><?php esc_html_e( 'ابتدای محتوا', 'gzseo' ); ?></option>
                                <option value="after_content" selected><?php esc_html_e( 'انتهای محتوا', 'gzseo' ); ?></option>
                                <option value="after_first_paragraph"><?php esc_html_e( 'بعد از اولین پاراگراف', 'gzseo' ); ?></option>
                                <option value="after_second_paragraph"><?php esc_html_e( 'بعد از دومین پاراگراف', 'gzseo' ); ?></option>
                                <option value="custom"><?php esc_html_e( 'موقعیت دلخواه (با شورت‌کد)', 'gzseo' ); ?></option>
                            </select>
                        </div>
                        
                        <button type="button" class="gzseo-btn gzseo-btn-success" id="gzseo-save-content">
                            <i class="fas fa-save"></i>
                            <?php esc_html_e( 'ذخیره محتوا', 'gzseo' ); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Saved Contents List -->
                <div class="gzseo-saved-contents" id="gzseo-saved-contents">
                    <h4>
                        <i class="fas fa-list"></i>
                        <?php esc_html_e( 'محتواهای ذخیره شده', 'gzseo' ); ?>
                    </h4>
                    
                    <?php if ( empty( $saved_contents ) ) : ?>
                        <div class="gzseo-no-content">
                            <i class="fas fa-inbox"></i>
                            <p><?php esc_html_e( 'هنوز محتوایی ذخیره نشده است.', 'gzseo' ); ?></p>
                        </div>
                    <?php else : ?>
                        <div class="gzseo-contents-list">
                            <?php foreach ($saved_contents as $content) : ?>
                                <div class="gzseo-saved-item" data-content-id="<?php echo esc_attr( $content->id ); ?>">
                                    <div class="gzseo-item-header">
                                        <span class="gzseo-item-date">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo esc_html( date_i18n( 'Y/m/d H:i', strtotime( $content->created_at ) ) ); ?>
                                        </span>
                                        <span class="gzseo-item-position">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo esc_html( $this->get_position_label( $content->position ) ); ?>
                                        </span>
                                        <span class="gzseo-item-status <?php echo esc_attr( $content->is_active ? 'active' : 'inactive' ); ?>">
                                            <i class="fas fa-<?php echo esc_attr( $content->is_active ? 'check-circle' : 'times-circle' ); ?>"></i>
                                            <?php echo esc_html( $content->is_active ? __( 'فعال', 'gzseo' ) : __( 'غیرفعال', 'gzseo' ) ); ?>
                                        </span>
                                        <?php if ( $content->is_bulk ) : ?>
                                            <span class="gzseo-item-bulk">
                                                <i class="fas fa-robot"></i>
                                                <?php esc_html_e( 'دسته جمعی', 'gzseo' ); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="gzseo-item-content" contenteditable="true">
                                        <?php echo wp_kses($content->content, array(
                                            'p' => array(),
                                            'strong' => array(),
                                            'em' => array(),
                                            'b' => array()
                                        )); ?>
                                    </div>
                                    
                                    <div class="gzseo-item-actions">
                                        <button class="gzseo-btn gzseo-btn-sm gzseo-btn-primary gzseo-toggle-content" 
                                                data-content-id="<?php echo esc_attr( $content->id ); ?>">
                                            <i class="fas fa-power-off"></i>
                                            <?php echo esc_html( $content->is_active ? __( 'غیرفعال', 'gzseo' ) : __( 'فعال', 'gzseo' ) ); ?>
                                        </button>
                                        
                                        <button class="gzseo-btn gzseo-btn-sm gzseo-btn-warning gzseo-update-content" 
                                                data-content-id="<?php echo esc_attr( $content->id ); ?>">
                                            <i class="fas fa-edit"></i>
                                            <?php esc_html_e( 'ویرایش', 'gzseo' ); ?>
                                        </button>
                                        
                                        <button class="gzseo-btn gzseo-btn-sm gzseo-btn-danger gzseo-delete-content" 
                                                data-content-id="<?php echo esc_attr( $content->id ); ?>">
                                            <i class="fas fa-trash"></i>
                                            <?php esc_html_e( 'حذف', 'gzseo' ); ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for generating content
     */
    public function ajax_generate_content() {
        // Verify nonce
        if (!check_ajax_referer('gzseo_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __( 'خطای امنیتی', 'gzseo' )));
        }

        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __( 'شما مجوز لازم برای ویرایش محتوا را ندارید', 'gzseo' )));
        }
        
        // Get parameters
        $post_id         = isset( $_POST['post_id'] ) ? intval( wp_unslash( $_POST['post_id'] ) ) : 0;
        $paragraph_count = isset( $_POST['paragraph_count'] ) ? intval( wp_unslash( $_POST['paragraph_count'] ) ) : 3;
        $min_words       = isset( $_POST['min_words'] ) ? intval( wp_unslash( $_POST['min_words'] ) ) : 50;
        $max_words       = isset( $_POST['max_words'] ) ? intval( wp_unslash( $_POST['max_words'] ) ) : 100;

        if (!GZSEO_Security_Helpers::current_user_can_edit_post($post_id)) {
            wp_send_json_error(array('message' => __('شما اجازه ویرایش این نوشته را ندارید', 'gzseo')), 403);
        }

        $paragraph_count = max(1, min(20, $paragraph_count));
        $min_words = max(10, min(2000, $min_words));
        $max_words = max($min_words, min(5000, $max_words));
        
        // Validate post
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => __( 'پست یافت نشد', 'gzseo' )));
        }
        
        // Get AI credentials
        $email = get_option('gzseo_ai_email');
        $password = GZSEO_Security_Helpers::get_ai_password();
        
        if (!$email || !$password) {
            wp_send_json_error(array('message' => __( 'لطفا ابتدا به حساب هوش مصنوعی خود متصل شوید', 'gzseo' )));
        }
        
        // Prepare API request
        $api_data = array(
            'email' => $email,
            'password' => $password,
            'title' => $post->post_title,
            'content' => wp_strip_all_tags($post->post_content),
            'paragraphCount' => $paragraph_count,
            'minLength' => $min_words,
            'maxLength' => $max_words
        );
        
        // Make API request
        $response = wp_remote_post('https://api.gzseo.in/createadvanceupdatecontent', array(
            'timeout' => 60,
            'limit_response_size' => 2 * MB_IN_BYTES,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($api_data)
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => __( 'خطا در ارتباط با سرویس هوش مصنوعی', 'gzseo' )));
        }
        
        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            wp_send_json_error(array('message' => __( 'خطا در دریافت پاسخ از سرور', 'gzseo' )));
        }
        
        // Clean content - only allow specific tags
        $allowed_tags = array(
            'p' => array(),
            'strong' => array(),
            'em' => array(),
            'b' => array()
        );
        
        $clean_content = wp_kses($body, $allowed_tags);
        
        // Update credits (if API returns used credits)
        // This is a placeholder - adjust based on actual API response
        
        wp_send_json_success(array(
            'content' => $clean_content,
            'message' => __( 'محتوا با موفقیت تولید شد', 'gzseo' )
        ));
    }
    
    /**
     * AJAX handler for saving generated content
     */
    public function ajax_save_generated_content() {
        // Verify nonce
        if (!check_ajax_referer('gzseo_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __( 'خطای امنیتی', 'gzseo' )));
        }

        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __( 'شما مجوز لازم برای ذخیره محتوا را ندارید', 'gzseo' )));
        }
        
        $post_id = isset( $_POST['post_id'] ) ? intval( wp_unslash( $_POST['post_id'] ) ) : 0;
        if (!GZSEO_Security_Helpers::current_user_can_edit_post($post_id)) {
            wp_send_json_error(array('message' => __('شما اجازه ویرایش این نوشته را ندارید', 'gzseo')), 403);
        }
        $content = isset( $_POST['content'] ) ? wp_kses(
            wp_unslash( $_POST['content'] ),
            array(
                'p'      => array(),
                'strong' => array(),
                'em'     => array(),
                'b'      => array(),
            )
        ) : '';
        $position = isset( $_POST['position'] ) ? sanitize_text_field( wp_unslash( $_POST['position'] ) ) : 'after';
        $is_bulk  = isset( $_POST['is_bulk'] ) ? intval( wp_unslash( $_POST['is_bulk'] ) ) : 0;
        
        // دریافت محتواهای قبلی
        $saved_contents = get_post_meta($post_id, '_gzseo_content_updates', true);
        if (!is_array($saved_contents)) {
            $saved_contents = array();
        }
        
        // ایجاد ID یکتا
        $content_id = 'gzseo_content_' . uniqid();
        
        // آماده‌سازی داده جدید
        $new_content = array(
            'id' => $content_id,
            'content' => $content,
            'position' => $position,
            'is_active' => 1,
            'is_bulk' => $is_bulk,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        // اضافه کردن به آرایه
        $saved_contents[] = $new_content;
        
        // ذخیره در post meta
        $result = update_post_meta($post_id, '_gzseo_content_updates', $saved_contents);
        
        // Invalidate cache after update
        wp_cache_delete('gzseo_bulk_content_posts', 'gzseo');
        
        if ($result === false) {
            wp_send_json_error(array('message' => __( 'خطا در ذخیره محتوا', 'gzseo' )));
        }
        
        wp_send_json_success(array(
            'message' => __( 'محتوا با موفقیت ذخیره شد', 'gzseo' ),
            'content_id' => $content_id
        ));
    }
    
    /**
     * Get saved contents for a post
     */
    private function get_saved_contents($post_id) {
        $contents = get_post_meta($post_id, '_gzseo_content_updates', true);
        
        if (!is_array($contents)) {
            return array();
        }
        
        // تبدیل آرایه به آبجکت برای سازگاری با کد قبلی
        $results = array();
        foreach ($contents as $content) {
            $results[] = (object) $content;
        }
        
        // مرتب‌سازی بر اساس تاریخ ایجاد
        usort($results, function($a, $b) {
            return strtotime($b->created_at) - strtotime($a->created_at);
        });
        
        return $results;
    }
    
    /**
     * Create database table
     */
    private function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gzseo_content_updates';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
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
     * Get position label
     */
    private function get_position_label($position) {
        $labels = array(
            'before_content'         => __( 'ابتدای محتوا', 'gzseo' ),
            'after_content'          => __( 'انتهای محتوا', 'gzseo' ),
            'after_first_paragraph'  => __( 'بعد از اولین پاراگراف', 'gzseo' ),
            'after_second_paragraph' => __( 'بعد از دومین پاراگراف', 'gzseo' ),
            'custom'                 => __( 'موقعیت دلخواه', 'gzseo' )
        );
        
        return isset($labels[$position]) ? $labels[$position] : $position;
    }
    
    /**
     * Display content updates on frontend
     */
    public function display_content_updates($content) {
        if (!is_singular()) {
            return $content;
        }
        
        global $post;
        $saved_contents = $this->get_saved_contents($post->ID);
        
        if (empty($saved_contents)) {
            return $content;
        }
        
        foreach ($saved_contents as $saved_content) {
            if (!$saved_content->is_active) {
                continue;
            }
            
                        $update_content = '<div class="gzseo-content-update">' . wp_kses(
                $saved_content->content,
                array(
                'p'      => array(),
                'strong' => array(),
                'em'     => array(),
                'b'      => array(),
            )
            ) . '</div>';
            
            switch ($saved_content->position) {
                case 'before_content':
                    $content = $update_content . $content;
                    break;
                    
                case 'after_content':
                    $content = $content . $update_content;
                    break;
                    
                case 'after_first_paragraph':
                    $content = $this->insert_after_paragraph($content, $update_content, 1);
                    break;
                    
                case 'after_second_paragraph':
                    $content = $this->insert_after_paragraph($content, $update_content, 2);
                    break;
            }
        }
        
        return $content;
    }
    
    /**
     * Insert content after specific paragraph
     */
    private function insert_after_paragraph($content, $insertion, $paragraph_id) {
        $closing_p = '</p>';
        $paragraphs = explode($closing_p, $content);
        
        if (count($paragraphs) > $paragraph_id) {
            $paragraphs[$paragraph_id - 1] .= $closing_p . $insertion;
        } else {
            $paragraphs[count($paragraphs) - 1] .= $closing_p . $insertion;
        }
        
        return implode($closing_p, $paragraphs);
    }
    
    /**
     * AJAX handler for getting saved contents
     */
    public function ajax_get_saved_contents() {
        // Verify nonce and capability BEFORE any other processing.
        if ( ! GZSEO_Security_Helpers::verify_ajax_request( 'gzseo_ajax_nonce', 'edit_posts' ) ) {
            return;
        }

        $post_id  = isset( $_POST['post_id'] ) ? intval( wp_unslash( $_POST['post_id'] ) ) : 0;
        if (!GZSEO_Security_Helpers::current_user_can_edit_post($post_id)) {
            wp_send_json_error(array('message' => __('شما اجازه مشاهده این نوشته را ندارید', 'gzseo')), 403);
        }
        $contents = $this->get_saved_contents( $post_id );
        
        wp_send_json_success(array('contents' => $contents));
    }
    
    /**
     * AJAX handler for deleting saved content
     */
    public function ajax_delete_saved_content() {
        if (!check_ajax_referer('gzseo_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __( 'خطای امنیتی', 'gzseo' )));
        }

        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __( 'شما مجوز لازم برای حذف محتوا را ندارید', 'gzseo' )));
        }
        
        $post_id    = isset( $_POST['post_id'] ) ? intval( wp_unslash( $_POST['post_id'] ) ) : 0;
        if (!GZSEO_Security_Helpers::current_user_can_edit_post($post_id)) {
            wp_send_json_error(array('message' => __('شما اجازه ویرایش این نوشته را ندارید', 'gzseo')), 403);
        }
        $content_id = isset( $_POST['content_id'] ) ? sanitize_text_field( wp_unslash( $_POST['content_id'] ) ) : '';
        
        // دریافت محتواهای موجود
        $saved_contents = get_post_meta( $post_id, '_gzseo_content_updates', true );
        if (!is_array($saved_contents)) {
            wp_send_json_error(array('message' => __( 'محتوایی یافت نشد', 'gzseo' )));
        }
        
        // حذف محتوا با ID مشخص شده
        $filtered_contents = array();
        foreach ($saved_contents as $content) {
            if ($content['id'] !== $content_id) {
                $filtered_contents[] = $content;
            }
        }
        
        // بروزرسانی post meta
        update_post_meta($post_id, '_gzseo_content_updates', $filtered_contents);
        
        // Invalidate cache after delete
        wp_cache_delete('gzseo_bulk_content_posts', 'gzseo');
        
        wp_send_json_success(array('message' => __( 'محتوا با موفقیت حذف شد', 'gzseo' )));
    }
    
    /**
     * AJAX handler for updating saved content
     */
    public function ajax_update_saved_content() {
        if (!check_ajax_referer('gzseo_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __( 'خطای امنیتی', 'gzseo' )));
        }

        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __( 'شما مجوز لازم برای ویرایش محتوا را ندارید', 'gzseo' )));
        }
        
        $post_id    = isset( $_POST['post_id'] ) ? intval( wp_unslash( $_POST['post_id'] ) ) : 0;
        if (!GZSEO_Security_Helpers::current_user_can_edit_post($post_id)) {
            wp_send_json_error(array('message' => __('شما اجازه ویرایش این نوشته را ندارید', 'gzseo')), 403);
        }
        $content_id = isset( $_POST['content_id'] ) ? sanitize_text_field( wp_unslash( $_POST['content_id'] ) ) : '';
        $action     = isset( $_POST['update_action'] ) ? sanitize_text_field( wp_unslash( $_POST['update_action'] ) ) : '';
        
        // دریافت محتواهای موجود
        $saved_contents = get_post_meta( $post_id, '_gzseo_content_updates', true );
        if (!is_array($saved_contents)) {
            wp_send_json_error(array('message' => __( 'محتوایی یافت نشد', 'gzseo' )));
        }
        
        // پیدا کردن و بروزرسانی محتوا
        foreach ($saved_contents as &$content) {
            if ($content['id'] === $content_id) {
                if ($action === 'toggle') {
                    $content['is_active'] = !$content['is_active'];
                } elseif ($action === 'update') {
                    $content['content'] = isset( $_POST['content'] ) ? wp_kses(
                        wp_unslash( $_POST['content'] ),
                        array(
                            'p'      => array(),
                            'strong' => array(),
                            'em'     => array(),
                            'b'      => array(),
                        )
                    ) : '';
                    $content['updated_at'] = current_time('mysql');
                }
                break;
            }
        }
        
        // ذخیره تغییرات
        update_post_meta($post_id, '_gzseo_content_updates', $saved_contents);
        
        // Invalidate cache after update
        wp_cache_delete('gzseo_bulk_content_posts', 'gzseo');
        
        wp_send_json_success(array('message' => __( 'محتوا با موفقیت به‌روزرسانی شد', 'gzseo' )));
    }

    /**
     * Get bulk generated contents count for a post
     */
    public function get_bulk_contents_count($post_id) {
        $contents = get_post_meta($post_id, '_gzseo_content_updates', true);
        
        if (!is_array($contents)) {
            return 0;
        }
        
        $count = 0;
        foreach ($contents as $content) {
            if (isset($content['is_bulk']) && $content['is_bulk'] == 1) {
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Delete all bulk generated contents for a post
     */
    public function delete_bulk_contents($post_id) {
        $contents = get_post_meta($post_id, '_gzseo_content_updates', true);
        
        if (!is_array($contents)) {
            return false;
        }
        
        // فیلتر کردن محتواهای غیر bulk
        $filtered_contents = array();
        foreach ($contents as $content) {
            if (!isset($content['is_bulk']) || $content['is_bulk'] != 1) {
                $filtered_contents[] = $content;
            }
        }
        
        $result = update_post_meta($post_id, '_gzseo_content_updates', $filtered_contents);
        
        // Invalidate cache after bulk delete
        wp_cache_delete('gzseo_bulk_content_posts', 'gzseo');
        
        return $result;
    }

    /**
     * Get all posts with bulk generated content
     */
    public function get_posts_with_bulk_content() {
        global $wpdb;
        
        // Try to get from cache first
        $cache_key = 'gzseo_bulk_content_posts';
        $cache_group = 'gzseo';
        $results = wp_cache_get($cache_key, $cache_group);
        
        if (false === $results) {
            // دریافت همه پست‌هایی که متا _gzseo_content_updates دارند
            // Direct database call is required for complex meta value parsing.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- avoids N+1 queries; result is cached.
            $posts = $wpdb->get_results(
                "SELECT post_id, meta_value 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_gzseo_content_updates'"
            );
            
            $results = array();
            foreach ($posts as $post) {
                $contents = maybe_unserialize($post->meta_value);
                if (is_array($contents)) {
                    $bulk_count = 0;
                    foreach ($contents as $content) {
                        if (isset($content['is_bulk']) && $content['is_bulk'] == 1) {
                            $bulk_count++;
                        }
                    }
                    if ($bulk_count > 0) {
                        $results[] = (object) array(
                            'post_id' => $post->post_id,
                            'content_count' => $bulk_count
                        );
                    }
                }
            }
            
            // Cache for 5 minutes (300 seconds)
            wp_cache_set($cache_key, $results, $cache_group, 300);
        }
        
        return $results;
    }
    
    /****************************** تولید محتوا برای تکسونومی ها *****************************/
    /**
     * Render content for taxonomy term (categories/tags)
     */
    public function render_taxonomy_content($term) {
        // Get saved content updates for this term
        $saved_contents = $this->get_saved_taxonomy_contents($term->term_id);
        $ai_credits = get_option('gzseo_ai_credits', 0);

        // Get AI credentials
        $is_connected = get_option('gzseo_ai_logged_in', false);
        ?>
        <div class="gzseo-content-update-wrapper">
            <?php if (!$is_connected): ?>
                <div class="gzseo-ai-login-notice">
                    <i class="fas fa-exclamation-circle"></i>
                    <p><?php esc_html_e( 'برای استفاده از قابلیت آپدیت محتوایی با هوش مصنوعی، ابتدا باید وارد حساب کاربری خود شوید.', 'gzseo' ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=gzseo-dashboard#ai' ) ); ?>" class="button button-primary">
                        <?php esc_html_e( 'ورود به حساب هوش مصنوعی', 'gzseo' ); ?>
                    </a>
                </div>
            <?php else: ?>
                <!-- Content Generation Form -->
                <div class="gzseo-content-form">
                    <input type="hidden" id="gzseo-taxonomy-id" value="<?php echo esc_attr( $term->term_id ); ?>">
                    <input type="hidden" id="gzseo-taxonomy-type" value="<?php echo esc_attr( $term->taxonomy ); ?>">

                    <div class="gzseo-form-row">
                        <div class="gzseo-form-group">
                            <label for="gzseo-paragraph-count">
                                <i class="fas fa-paragraph"></i>
                                <?php esc_html_e( 'تعداد پاراگراف', 'gzseo' ); ?>
                            </label>
                            <input type="number" id="gzseo-paragraph-count" class="gzseo-input" 
                                value="3" min="1" max="10" required>
                        </div>

                        <div class="gzseo-form-group">
                            <label for="gzseo-min-words">
                                <i class="fas fa-minus"></i>
                                <?php esc_html_e( 'حداقل کلمات', 'gzseo' ); ?>
                            </label>
                            <input type="number" id="gzseo-min-words" class="gzseo-input" 
                                value="200" min="50" max="1000" required>
                        </div>

                        <div class="gzseo-form-group">
                            <label for="gzseo-max-words">
                                <i class="fas fa-plus"></i>
                                <?php esc_html_e( 'حداکثر کلمات', 'gzseo' ); ?>
                            </label>
                            <input type="number" id="gzseo-max-words" class="gzseo-input" 
                                value="350" min="100" max="2000" required>
                        </div>
                    </div>

                    <div class="gzseo-form-actions">
                        <button type="button" class="gzseo-btn gzseo-btn-primary" id="gzseo-generate-taxonomy-content">
                            <i class="fas fa-magic"></i>
                            <?php esc_html_e( 'تولید محتوا با هوش مصنوعی', 'gzseo' ); ?>
                        </button>
                    </div>
                </div>

                <!-- Generated Content Preview -->
                <div class="gzseo-content-preview" id="gzseo-taxonomy-content-preview" style="display: none;">
                    <div class="gzseo-preview-header">
                        <h4>
                            <i class="fas fa-eye"></i>
                            <?php esc_html_e( 'پیش‌نمایش محتوای تولید شده', 'gzseo' ); ?>
                        </h4>
                        <button type="button" class="gzseo-preview-close" id="gzseo-close-taxonomy-preview">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="gzseo-preview-content" id="gzseo-taxonomy-preview-content" contenteditable="true">
                        <!-- Generated content will be displayed here -->
                    </div>

                    <div class="gzseo-preview-actions">
                        <div class="gzseo-position-selector">
                            <label for="gzseo-taxonomy-content-position">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php esc_html_e( 'موقعیت نمایش:', 'gzseo' ); ?>
                            </label>
                            <select id="gzseo-taxonomy-content-position" class="gzseo-select">
                                <option value="before_content"><?php esc_html_e( 'ابتدای محتوا', 'gzseo' ); ?></option>
                                <option value="after_content" selected><?php esc_html_e( 'انتهای محتوا', 'gzseo' ); ?></option>
                                <option value="after_first_paragraph"><?php esc_html_e( 'بعد از اولین پاراگراف', 'gzseo' ); ?></option>
                                <option value="after_second_paragraph"><?php esc_html_e( 'بعد از دومین پاراگراف', 'gzseo' ); ?></option>
                            </select>
                        </div>

                        <button type="button" class="gzseo-btn gzseo-btn-success" id="gzseo-save-taxonomy-content">
                            <i class="fas fa-save"></i>
                            <?php esc_html_e( 'ذخیره محتوا', 'gzseo' ); ?>
                        </button>
                    </div>
                </div>

                <!-- Saved Contents List -->
                <div class="gzseo-saved-contents" id="gzseo-taxonomy-saved-contents">
                    <h4>
                        <i class="fas fa-list"></i>
                        <?php esc_html_e( 'محتواهای ذخیره شده', 'gzseo' ); ?>
                    </h4>

                    <?php if ( empty( $saved_contents ) ) : ?>
                        <div class="gzseo-no-content">
                            <i class="fas fa-inbox"></i>
                            <p><?php esc_html_e( 'هنوز محتوایی ذخیره نشده است.', 'gzseo' ); ?></p>
                        </div>
                    <?php else : ?>
                        <div class="gzseo-contents-list">
                            <?php foreach ($saved_contents as $content) : ?>
                                <div class="gzseo-saved-item" data-content-id="<?php echo esc_attr( $content->id ); ?>">
                                    <div class="gzseo-item-header">
                                        <span class="gzseo-item-date">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo esc_html( date_i18n( 'Y/m/d H:i', strtotime( $content->created_at ) ) ); ?>
                                        </span>
                                        <span class="gzseo-item-position">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo esc_html( $this->get_position_label( $content->position ) ); ?>
                                        </span>
                                        <span class="gzseo-item-status <?php echo esc_attr( $content->is_active ? 'active' : 'inactive' ); ?>">
                                            <i class="fas fa-<?php echo esc_attr( $content->is_active ? 'check-circle' : 'times-circle' ); ?>"></i>
                                            <?php echo esc_html( $content->is_active ? __( 'فعال', 'gzseo' ) : __( 'غیرفعال', 'gzseo' ) ); ?>
                                        </span>
                                    </div>

                                    <div class="gzseo-item-content" contenteditable="true">
                                        <?php echo wp_kses(
                                            $content->content,
                                            array(
                                                'p'      => array(),
                                                'strong' => array(),
                                                'em'     => array(),
                                                'b'      => array(),
                                            )
                                        ); ?>
                                    </div>

                                    <div class="gzseo-item-actions">
                                        <button class="gzseo-btn gzseo-btn-sm gzseo-btn-primary gzseo-toggle-taxonomy-content" 
                                                data-content-id="<?php echo esc_attr( $content->id ); ?>">
                                            <i class="fas fa-power-off"></i>
                                            <?php echo esc_html( $content->is_active ? __( 'غیرفعال', 'gzseo' ) : __( 'فعال', 'gzseo' ) ); ?>
                                        </button>

                                        <button class="gzseo-btn gzseo-btn-sm gzseo-btn-warning gzseo-update-taxonomy-content" 
                                                data-content-id="<?php echo esc_attr( $content->id ); ?>">
                                            <i class="fas fa-edit"></i>
                                            <?php esc_html_e( 'ویرایش', 'gzseo' ); ?>
                                        </button>

                                        <button class="gzseo-btn gzseo-btn-sm gzseo-btn-danger gzseo-delete-taxonomy-content" 
                                                data-content-id="<?php echo esc_attr( $content->id ); ?>">
                                            <i class="fas fa-trash"></i>
                                            <?php esc_html_e( 'حذف', 'gzseo' ); ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get saved contents for a taxonomy term
     */
    private function get_saved_taxonomy_contents($term_id) {
        $contents = get_term_meta($term_id, '_gzseo_content_updates', true);

        if (!is_array($contents)) {
            return array();
        }

        // تبدیل آرایه به آبجکت برای سازگاری با کد قبلی
        $results = array();
        foreach ($contents as $content) {
            $results[] = (object) $content;
        }

        // مرتب‌سازی بر اساس تاریخ ایجاد
        usort($results, function($a, $b) {
            return strtotime($b->created_at) - strtotime($a->created_at);
        });

        return $results;
    }

    /**
     * AJAX handler for generating taxonomy content
     */
    public function ajax_generate_taxonomy_content() {
        // Verify nonce
        if (!check_ajax_referer('gzseo_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __( 'خطای امنیتی', 'gzseo' )));
        }

        // Check user permissions
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __( 'شما مجوز لازم برای ویرایش دسته‌بندی را ندارید', 'gzseo' )));
        }

        // Get parameters
        $term_id         = isset( $_POST['term_id'] ) ? intval( wp_unslash( $_POST['term_id'] ) ) : 0;
        $taxonomy        = isset( $_POST['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_POST['taxonomy'] ) ) : '';
        $paragraph_count = isset( $_POST['paragraph_count'] ) ? intval( wp_unslash( $_POST['paragraph_count'] ) ) : 3;
        $min_words       = isset( $_POST['min_words'] ) ? intval( wp_unslash( $_POST['min_words'] ) ) : 50;
        $max_words       = isset( $_POST['max_words'] ) ? intval( wp_unslash( $_POST['max_words'] ) ) : 100;

        if (!GZSEO_Security_Helpers::current_user_can_edit_term($term_id, $taxonomy)) {
            wp_send_json_error(array('message' => __('شما اجازه ویرایش این دسته‌بندی را ندارید', 'gzseo')), 403);
        }

        $paragraph_count = max(1, min(20, $paragraph_count));
        $min_words = max(10, min(2000, $min_words));
        $max_words = max($min_words, min(5000, $max_words));

        // Validate term
        $term = get_term($term_id, $taxonomy);
        if (!$term || is_wp_error($term)) {
            wp_send_json_error(array('message' => __( 'دسته‌بندی یافت نشد', 'gzseo' )));
        }

        // Get AI credentials
        $email = get_option('gzseo_ai_email');
        $password = GZSEO_Security_Helpers::get_ai_password();

        if (!$email || !$password) {
            wp_send_json_error(array('message' => __( 'لطفا ابتدا به حساب هوش مصنوعی خود متصل شوید', 'gzseo' )));
        }

        // Prepare API request
        $api_data = array(
            'email' => $email,
            'password' => $password,
            'title' => $term->name,
            'content' => wp_strip_all_tags($term->description),
            'paragraphCount' => $paragraph_count,
            'minLength' => $min_words,
            'maxLength' => $max_words
        );

        // Make API request
        $response = wp_remote_post('https://api.gzseo.in/createadvanceupdatecontent', array(
            'timeout' => 60,
            'limit_response_size' => 2 * MB_IN_BYTES,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($api_data)
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => __( 'خطا در ارتباط با سرویس هوش مصنوعی', 'gzseo' )));
        }

        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            wp_send_json_error(array('message' => __( 'خطا در دریافت پاسخ از سرور', 'gzseo' )));
        }

        // Clean content
        $allowed_tags = array(
            'p' => array(),
            'strong' => array(),
            'em' => array(),
            'b' => array()
        );

        $clean_content = wp_kses($body, $allowed_tags);

        wp_send_json_success(array(
            'content' => $clean_content,
            'message' => __( 'محتوا با موفقیت تولید شد', 'gzseo' )
        ));
    }

    /**
     * AJAX handler for saving taxonomy content
     */
    public function ajax_save_taxonomy_content() {
        // Verify nonce
        if (!check_ajax_referer('gzseo_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __( 'خطای امنیتی', 'gzseo' )));
        }

        // Check user permissions
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __( 'شما مجوز لازم برای ذخیره دسته‌بندی را ندارید', 'gzseo' )));
        }

        $term_id = isset( $_POST['term_id'] ) ? intval( wp_unslash( $_POST['term_id'] ) ) : 0;
        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key(wp_unslash($_POST['taxonomy'])) : '';
        if (!GZSEO_Security_Helpers::current_user_can_edit_term($term_id, $taxonomy)) {
            wp_send_json_error(array('message' => __('شما اجازه ویرایش این دسته‌بندی را ندارید', 'gzseo')), 403);
        }
        $content = isset( $_POST['content'] ) ? wp_kses(
            wp_unslash( $_POST['content'] ),
            array(
                'p'      => array(),
                'strong' => array(),
                'em'     => array(),
                'b'      => array(),
            )
        ) : '';
        $position = isset( $_POST['position'] ) ? sanitize_text_field( wp_unslash( $_POST['position'] ) ) : 'after';
        $is_bulk  = isset( $_POST['is_bulk'] ) ? intval( wp_unslash( $_POST['is_bulk'] ) ) : 0;

        // دریافت محتواهای قبلی
        $saved_contents = get_term_meta($term_id, '_gzseo_content_updates', true);
        if (!is_array($saved_contents)) {
            $saved_contents = array();
        }

        // ایجاد ID یکتا
        $content_id = 'gzseo_content_' . uniqid();

        // آماده‌سازی داده جدید
        $new_content = array(
            'id' => $content_id,
            'content' => $content,
            'position' => $position,
            'is_active' => 1,
            'is_bulk' => $is_bulk,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        // اضافه کردن به آرایه
        $saved_contents[] = $new_content;

        // ذخیره در term meta
        $result = update_term_meta($term_id, '_gzseo_content_updates', $saved_contents);

        if ($result === false) {
            wp_send_json_error(array('message' => __( 'خطا در ذخیره محتوا', 'gzseo' )));
        }

        wp_send_json_success(array(
            'message' => __( 'محتوا با موفقیت ذخیره شد', 'gzseo' ),
            'content_id' => $content_id
        ));
    }

    /**
     * Display taxonomy content updates on frontend
     */
    public function display_taxonomy_content_updates($description, $term_id) {
        if (empty($term_id)) {
            return $description;
        }

        $saved_contents = $this->get_saved_taxonomy_contents($term_id);

        if (empty($saved_contents)) {
            return $description;
        }

        foreach ($saved_contents as $saved_content) {
            if (!$saved_content->is_active) {
                continue;
            }

                        $update_content = '<div class="gzseo-content-update">' . wp_kses(
                $saved_content->content,
                array(
                'p'      => array(),
                'strong' => array(),
                'em'     => array(),
                'b'      => array(),
            )
            ) . '</div>';

            switch ($saved_content->position) {
                case 'before_content':
                    $description = $update_content . $description;
                    break;

                case 'after_content':
                    $description = $description . $update_content;
                    break;

                case 'after_first_paragraph':
                    $description = $this->insert_after_paragraph($description, $update_content, 1);
                    break;

                case 'after_second_paragraph':
                    $description = $this->insert_after_paragraph($description, $update_content, 2);
                    break;
            }
        }

        return $description;
    }
}

// Initialize the class
new GZSEO_Content_Update();
