<?php
/**
 * GZ SEO Featured Image AI Generator
 *
 * @package GZ_SEO
 * @subpackage Featured_Image_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

class GZSEO_Featured_Image_AI {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_gzseo_generate_featured_image', array($this, 'handle_generate_image'));
        add_action('wp_ajax_gzseo_set_featured_image', array($this, 'handle_set_featured_image'));
        add_action('wp_ajax_gzseo_restore_featured_image', array($this, 'handle_restore_featured_image'));

        // Add bulk indicator to featured images
        add_filter('post_thumbnail_html', array($this, 'add_bulk_indicator'), 10, 5);

        // Add bulk label in media library
        add_filter('attachment_fields_to_edit', array($this, 'add_bulk_label_to_attachment'), 10, 2);

        // Add custom column in media library
        add_filter('manage_media_columns', array($this, 'add_bulk_column'));
        add_action('manage_media_custom_column', array($this, 'display_bulk_column'), 10, 2);
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook) {
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }
        
        wp_enqueue_script(
            'gzseo-featured-image-ai',
            GZSEO_PLUGIN_URL . 'assets/js/featured-image-ai.js',
            array('jquery'),
            GZSEO_VERSION,
            true
        );
        
        wp_localize_script('gzseo-featured-image-ai', 'gzseo_fi_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gzseo_featured_image_nonce'),
            'messages' => array(
                'generating'      => __( 'در حال تولید تصویر...', 'gzseo' ),
                'error'           => __( 'خطا در تولید تصویر', 'gzseo' ),
                'confirm_set'     => __( 'آیا از انتخاب این تصویر به عنوان تصویر شاخص اطمینان دارید؟', 'gzseo' ),
                'confirm_restore' => __( 'آیا از بازگردانی تصویر قبلی اطمینان دارید؟', 'gzseo' ),
                'success'         => __( 'تصویر با موفقیت تنظیم شد', 'gzseo' ),
                'not_logged_in'    => __( 'لطفا ابتدا وارد حساب هوش مصنوعی شوید', 'gzseo' )
            )
        ));
        
        wp_enqueue_style(
            'gzseo-featured-image-ai',
            GZSEO_PLUGIN_URL . 'assets/css/featured-image-ai.css',
            array(),
            GZSEO_VERSION
        );
    }
    
    /**
     * Check if user is logged in to AI
     */
    public function is_ai_logged_in() {
        return get_option('gzseo_ai_logged_in', false);
    }
    
    /**
     * Get AI credentials
     */
    private function get_ai_credentials() {
        return array(
            'email' => get_option('gzseo_ai_email', ''),
            'password' => GZSEO_Security_Helpers::get_ai_password()
        );
    }
    
    /**
     * Handle generate image request
     */
    public function handle_generate_image() {
        // Verify nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'gzseo_featured_image_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'خطای امنیتی', 'gzseo' ) ) );
        }

        // Check user permissions
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __( 'شما مجوز لازم برای تولید تصویر را ندارید', 'gzseo' )));
        }
        
        // Check if AI is logged in
        if (!$this->is_ai_logged_in()) {
            wp_send_json_error(array('message' => __( 'لطفا ابتدا وارد حساب هوش مصنوعی شوید', 'gzseo' )));
        }
        
        // Get credentials
        $credentials = $this->get_ai_credentials();
        
        // Get form data
        $post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
        if (!GZSEO_Security_Helpers::current_user_can_edit_post($post_id)) {
            wp_send_json_error(array('message' => __('شما اجازه ویرایش این نوشته را ندارید', 'gzseo')), 403);
        }
        $width = isset( $_POST['width'] ) ? absint( wp_unslash( $_POST['width'] ) ) : 0;
        $height = isset( $_POST['height'] ) ? absint( wp_unslash( $_POST['height'] ) ) : 0;
        
        // Get post title as prompt
        $post_title = get_the_title($post_id);
        if (empty($post_title)) {
            wp_send_json_error(array('message' => __( 'عنوان پست یافت نشد', 'gzseo' )));
        }
        
        // Validate dimensions
        $valid_dimensions = array(
            '1024x1024', '1152x896', '896x1152', '1216x832',
            '1344x768', '768x1344', '1536x640', '640x1536'
        );
        
        $dimension_string = $width . 'x' . $height;
        if (!in_array($dimension_string, $valid_dimensions)) {
            wp_send_json_error(array('message' => __( 'ابعاد انتخابی معتبر نیست', 'gzseo' )));
        }
        
        // Prepare API request
        $api_data = array(
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'prompt' => $post_title,
            'width' => $width,
            'height' => $height
        );
        
        // Make API request
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
            wp_send_json_error(array(
                /* translators: %s: Error message returned from remote server */
                'message' => __( 'خطا در اتصال به سرویس تصویر', 'gzseo' )
            ));
        }
        
        $body = rawurldecode(wp_remote_retrieve_body($response));
        //$data = json_decode($body, true);

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code < 200 || $status_code >= 300) {
            wp_send_json_error(array('message' => __('پاسخ نامعتبر از سرویس تصویر دریافت شد', 'gzseo')));
        }
        
        //wp_send_json_error(array('message' => $data));
        // Handle the response format where success is false but message contains the image URL
        if ( isset( $body ) ) {
            $image_url = trim( (string) $body );
            $image_url = esc_url_raw( $image_url );
        } else {
            wp_send_json_error( array( 'message' => __( 'آدرس تصویر دریافت نشد', 'gzseo' ) ) );
        }

        if ( empty( $image_url ) ) {
            wp_send_json_error( array( 'message' => __( 'آدرس تصویر دریافت نشد', 'gzseo' ) ) );
        }
        //var_dump($image_url);die();
        // Download and save image to media library
        $upload_result = $this->download_image_to_media($image_url, $post_id);
        
        if (is_wp_error($upload_result)) {
            wp_send_json_error(array(
                /* translators: %s: Image upload error message */
                'message' => __( 'خطا در ذخیره تصویر تولیدشده', 'gzseo' )
            ));
        }
        
        // Save current featured image as backup
        $current_thumbnail_id = get_post_thumbnail_id($post_id);
        if ($current_thumbnail_id) {
            update_post_meta($post_id, '_gzseo_previous_thumbnail_id', $current_thumbnail_id);
        }
        
        wp_send_json_success(array(
            'message' => __( 'تصویر با موفقیت تولید شد', 'gzseo' ),
            'image_url' => esc_url_raw( wp_get_attachment_url( $upload_result ) ),
            'image_id' => $upload_result,
            'thumbnail_url' => esc_url_raw( wp_get_attachment_image_url( $upload_result, 'medium' ) )
        ));
    }
    
    /**
     * Download image from URL and save to media library
     */
    private function download_image_to_media($image_url, $post_id) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // دانلود با WordPress HTTP API
        $tmp_file = wp_tempnam( $image_url );

        if (!wp_http_validate_url($image_url)) {
            return new WP_Error('invalid_image_url', __('آدرس تصویر معتبر نیست', 'gzseo'));
        }

        $response = wp_safe_remote_get($image_url, array(
            'timeout' => 30,
            'redirection' => 5,
            'limit_response_size' => 10 * MB_IN_BYTES,
            'headers' => array(
                'Accept' => 'image/webp,image/apng,image/*,*/*;q=0.8',
                'Accept-Language' => 'fa,en-US;q=0.9,en;q=0.8',
            ),
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'referrer' => home_url(),
        ));

        if (is_wp_error($response)) {
            return new WP_Error('download_failed', $response->get_error_message());
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $image_data = wp_remote_retrieve_body($response);
        $content_type = strtolower((string) wp_remote_retrieve_header($response, 'content-type'));

        if ($http_code !== 200 || empty($image_data) || 0 !== strpos($content_type, 'image/')) {
            if (!empty($tmp_file)) {
                wp_delete_file($tmp_file);
            }
            return new WP_Error( 'download_failed', __( 'دانلود تصویر ناموفق بود', 'gzseo' ) );
        }

        // ذخیره در فایل موقت
        if (!empty($tmp_file)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents( $tmp_file, $image_data );
        }

        $image_info = @getimagesize($tmp_file); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        if (!$image_info || empty($image_info['mime']) || 0 !== strpos($image_info['mime'], 'image/')) {
            wp_delete_file($tmp_file);
            return new WP_Error('invalid_image_data', __('فایل دریافت‌شده تصویر معتبر نیست', 'gzseo'));
        }

        // Get file info
        $file_array = array(
            'name' => 'gzseo-ai-featured-' . $post_id . '-' . time() . '.' . ('image/png' === $image_info['mime'] ? 'png' : ('image/webp' === $image_info['mime'] ? 'webp' : 'jpg')),
            'tmp_name' => $tmp_file
        );

        // Upload to media library
        $attachment_id = media_handle_sideload( $file_array, $post_id, __( 'تصویر شاخص تولید شده توسط هوش مصنوعی', 'gzseo' ) );

        // Delete temp file
        if (!empty($tmp_file)) {
            wp_delete_file($tmp_file);
        }

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        return $attachment_id;
    }
    
    /**
     * Handle set featured image request
     */
    public function handle_set_featured_image() {
        // Verify nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'gzseo_featured_image_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'خطای امنیتی', 'gzseo' ) ) );
        }

        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __( 'شما مجوز لازم برای تنظیم تصویر شاخص را ندارید', 'gzseo' )));
        }
        
        $post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
        $image_id = isset( $_POST['image_id'] ) ? absint( wp_unslash( $_POST['image_id'] ) ) : 0;
        if (!GZSEO_Security_Helpers::current_user_can_edit_post($post_id)) {
            wp_send_json_error(array('message' => __('شما اجازه ویرایش این نوشته را ندارید', 'gzseo')), 403);
        }
        if (!$image_id || !wp_attachment_is_image($image_id)) {
            wp_send_json_error(array('message' => __('تصویر انتخاب‌شده معتبر نیست', 'gzseo')));
        }
        
        // Set featured image
        $result = set_post_thumbnail($post_id, $image_id);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __( 'تصویر شاخص با موفقیت تنظیم شد', 'gzseo' )
            ));
        } else {
            wp_send_json_error(array(
                'message' => __( 'خطا در تنظیم تصویر شاخص', 'gzseo' )
            ));
        }
    }
    
    /**
     * Handle restore featured image request
     */
    public function handle_restore_featured_image() {
        // Verify nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'gzseo_featured_image_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'خطای امنیتی', 'gzseo' ) ) );
        }

        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __( 'شما مجوز لازم برای بازگردانی تصویر را ندارید', 'gzseo' )));
        }
        
        $post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
        if (!GZSEO_Security_Helpers::current_user_can_edit_post($post_id)) {
            wp_send_json_error(array('message' => __('شما اجازه ویرایش این نوشته را ندارید', 'gzseo')), 403);
        }
        $previous_thumbnail_id = get_post_meta($post_id, '_gzseo_previous_thumbnail_id', true);
        
        if ($previous_thumbnail_id) {
            set_post_thumbnail($post_id, $previous_thumbnail_id);
            delete_post_meta($post_id, '_gzseo_previous_thumbnail_id');
            
            wp_send_json_success(array(
                'message' => __( 'تصویر قبلی بازگردانی شد', 'gzseo' ),
                'thumbnail_url' => esc_url_raw( get_the_post_thumbnail_url( $post_id, 'medium' ) )
            ));
        } else {
            wp_send_json_error(array(
                'message' => __( 'تصویر قبلی یافت نشد', 'gzseo' )
            ));
        }
    }
    
    /**
     * Render featured image AI content
     */
    public function render_content($post) {
        $is_logged_in = $this->is_ai_logged_in();
        $has_previous = get_post_meta($post->ID, '_gzseo_previous_thumbnail_id', true);
        ?>
        <div class="gzseo-featured-image-ai-wrapper">
            <?php if (!$is_logged_in): ?>
                <div class="gzseo-ai-login-notice">
                    <i class="fas fa-exclamation-circle"></i>
                    <p><?php esc_html_e( 'برای استفاده از تولید تصویر با هوش مصنوعی، ابتدا باید وارد حساب کاربری خود شوید.', 'gzseo' ); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=gzseo-dashboard#ai')); ?>" class="button button-primary">
                        <?php esc_html_e( 'ورود به حساب هوش مصنوعی', 'gzseo' ); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="gzseo-ai-form">
                    <input type="hidden" id="gzseo-post-id" value="<?php echo esc_attr($post->ID); ?>">
                    
                    <div class="gzseo-form-group">
                        <label for="gzseo-ai-dimensions"><?php esc_html_e( 'ابعاد تصویر:', 'gzseo' ); ?></label>
                        <select id="gzseo-ai-dimensions" class="widefat">
                            <option value=""><?php esc_html_e( 'انتخاب ابعاد...', 'gzseo' ); ?></option>
                            <option value="1024x1024"><?php esc_html_e( '1024x1024 (مربع)', 'gzseo' ); ?></option>
                            <option value="1152x896"><?php esc_html_e( '1152x896 (افقی)', 'gzseo' ); ?></option>
                            <option value="896x1152"><?php esc_html_e( '896x1152 (عمودی)', 'gzseo' ); ?></option>
                            <option value="1216x832"><?php esc_html_e( '1216x832 (افقی عریض)', 'gzseo' ); ?></option>
                            <option value="1344x768"><?php esc_html_e( '1344x768 (افقی فول)', 'gzseo' ); ?></option>
                            <option value="768x1344"><?php esc_html_e( '768x1344 (عمودی فول)', 'gzseo' ); ?></option>
                            <option value="1536x640"><?php esc_html_e( '1536x640 (افقی بنر)', 'gzseo' ); ?></option>
                            <option value="640x1536"><?php esc_html_e( '640x1536 (عمودی بنر)', 'gzseo' ); ?></option>
                        </select>
                    </div>
                    
                    <div class="gzseo-info-box">
                        <i class="fas fa-info-circle"></i>
                        <span>
                            <?php
                            /* translators: %s: Post title */
                            printf( esc_html__( 'تصویر بر اساس عنوان پست "%s" تولید خواهد شد.', 'gzseo' ), esc_html(get_the_title($post->ID)) );
                            ?>
                        </span>
                    </div>
                    
                    <div class="gzseo-button-group">
                        <button type="button" id="gzseo-generate-image" class="button button-primary">
                            <i class="fas fa-magic"></i> <?php esc_html_e( 'تولید تصویر', 'gzseo' ); ?>
                        </button>
                        
                        <?php if ($has_previous): ?>
                        <button type="button" id="gzseo-restore-image" class="button">
                            <i class="fas fa-undo"></i> <?php esc_html_e( 'بازگردانی تصویر قبلی', 'gzseo' ); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                    
                    <div id="gzseo-ai-preview" class="gzseo-ai-preview" style="display: none;">
                        <h4><?php esc_html_e( 'پیش‌نمایش تصویر تولید شده:', 'gzseo' ); ?></h4>
                        <div class="gzseo-preview-image">
                            <img src="" alt="<?php esc_attr_e( 'پیش‌نمایش', 'gzseo' ); ?>">
                        </div>
                        <div class="gzseo-preview-actions">
                            <button type="button" id="gzseo-confirm-image" class="button button-primary">
                                <i class="fas fa-check"></i> <?php esc_html_e( 'تنظیم به عنوان تصویر شاخص', 'gzseo' ); ?>
                            </button>
                            <button type="button" id="gzseo-cancel-image" class="button">
                                <i class="fas fa-times"></i> <?php esc_html_e( 'انصراف', 'gzseo' ); ?>
                            </button>
                        </div>
                    </div>
                    
                    <div id="gzseo-ai-messages" class="gzseo-ai-messages"></div>
                </div>
            <?php endif; ?>
        </div>
    <?php
    }

    /**
     * Check if a featured image was generated via bulk update
     */
    public function is_bulk_generated($attachment_id) {
        return get_post_meta($attachment_id, '_gzseo_bulk_generated', true);
    }

    /**
     * Get all posts with bulk generated featured images
     */
    public function get_posts_with_bulk_featured_images() {
        global $wpdb;
        
        // Try to get from cache first
        $cache_key = 'gzseo_bulk_featured_images_posts';
        $cache_group = 'gzseo';
        $results = wp_cache_get($cache_key, $cache_group);
        
        if (false === $results) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $results = $wpdb->get_results(
                "SELECT DISTINCT p.ID, p.post_title, pm.meta_value as thumbnail_id
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE pm.meta_key = '_thumbnail_id'
                AND EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} pm2 
                    WHERE pm2.post_id = pm.meta_value 
                    AND pm2.meta_key = '_gzseo_bulk_generated' 
                    AND pm2.meta_value = '1'
                )
                ORDER BY p.ID DESC"
            );
            
            // Cache for 5 minutes (300 seconds)
            wp_cache_set($cache_key, $results, $cache_group, 300);
        }
        
        return $results;
    }

    /**
     * Get bulk featured images count
     */
    public function get_bulk_featured_images_count() {
        global $wpdb;
        
        // Try to get from cache first
        $cache_key = 'gzseo_bulk_featured_images_count';
        $cache_group = 'gzseo';
        $count = wp_cache_get($cache_key, $cache_group);
        
        if (false === $count) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $count = $wpdb->get_var(
                "SELECT COUNT(DISTINCT post_id) 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_gzseo_bulk_featured_image' 
                AND meta_value = '1'"
            );
            
            // Cache for 5 minutes (300 seconds)
            wp_cache_set($cache_key, intval($count), $cache_group, 300);
        }
        
        return intval($count);
    }

    /**
     * Mark featured image in UI if it's bulk generated
     */
    public function add_bulk_indicator($html, $post_id, $post_thumbnail_id, $size, $attr = array()) {
        // بررسی اینکه آیا post_thumbnail_id معتبر است
        if (!$post_thumbnail_id) {
            return $html;
        }

        if ($this->is_bulk_generated($post_thumbnail_id)) {
            // Add a data attribute to identify bulk generated images
            if (is_array($attr) && !empty($attr)) {
                $attr['data-gzseo-bulk'] = 'true';
            } else {
                $html = str_replace('<img', '<img data-gzseo-bulk="true"', $html);
            }
        }
        return $html;
    }

    /**
     * Add bulk generated label to media library
     */
    public function add_bulk_label_to_attachment($form_fields, $post) {
        if ($this->is_bulk_generated($post->ID)) {
            $form_fields['gzseo_bulk_label'] = array(
                'label' => __( 'وضعیت تولید', 'gzseo' ),
                'input' => 'html',
                'html'  => '<span style="color: #f39c12; font-weight: bold;">
                            <span class="dashicons dashicons-update" style="vertical-align: middle;"></span> 
                            ' . esc_html__( 'تولید شده با آپدیت دسته جمعی', 'gzseo' ) . '
                            </span>'
            );
        }
        return $form_fields;
    }

    /**
     * Add bulk column to media library
     */
    public function add_bulk_column($columns) {
        $columns['gzseo_bulk'] = __( 'نوع تولید', 'gzseo' );
        return $columns;
    }

    /**
     * Display bulk column content
     */
    public function display_bulk_column($column_name, $attachment_id) {
        if ($column_name === 'gzseo_bulk' && $this->is_bulk_generated($attachment_id)) {
            echo '<span style="color: #f39c12;" title="' . esc_attr__( 'تولید شده با آپدیت دسته جمعی', 'gzseo' ) . '">
                <span class="dashicons dashicons-update"></span> ' . esc_html__( 'دسته جمعی', 'gzseo' ) . '
                </span>';
        }
    }
}

// Initialize the class
new GZSEO_Featured_Image_AI();
