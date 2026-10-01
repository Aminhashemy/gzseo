<?php
/**
 * GZSEO Title & Description Generator Class
 * 
 * @package GZSEO
 * @since 2.0.0
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

class GZSEO_Title_Description_Generator {
    
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
        // AJAX actions
        add_action('wp_ajax_gzseo_generate_title_desc', array($this, 'handle_generate_title_desc'));
        add_action('wp_ajax_gzseo_save_title_desc', array($this, 'handle_save_title_desc'));
        
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        
        add_action('wp_ajax_gzseo_generate_taxonomy_title_desc', array($this, 'ajax_generate_taxonomy_title_desc'));
        add_action('wp_ajax_gzseo_save_taxonomy_title_desc', array($this, 'ajax_save_taxonomy_title_desc'));
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets($hook) {
        // Only load on post/page/product edit screens
        if (!in_array($hook, array('post.php', 'post-new.php','term.php'))) {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'gzseo-title-desc',
            GZSEO_PLUGIN_URL . 'assets/css/title-description.css',
            array(),
            GZSEO_VERSION
        );
        
        // Enqueue JS
        wp_enqueue_script(
            'gzseo-title-desc',
            GZSEO_PLUGIN_URL . 'assets/js/title-description.js',
            array('jquery'),
            GZSEO_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('gzseo-title-desc', 'gzseo_td_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gzseo_title_desc_nonce'),
            'post_id' => absint( get_the_ID() ),
            'messages' => array(
                'generating' => __('در حال تولید عنوان و توضیحات...', 'gzseo'),
                'success' => __('عنوان و توضیحات با موفقیت تولید شد', 'gzseo'),
                'error' => __('خطا در تولید عنوان و توضیحات', 'gzseo'),
                'saving' => __('در حال ذخیره...', 'gzseo'),
                'saved' => __('با موفقیت ذخیره شد', 'gzseo'),
                'confirm_save' => __('آیا از ذخیره این عنوان و توضیحات اطمینان دارید؟', 'gzseo'),
                'no_selection' => __('لطفا یک عنوان و توضیحات را انتخاب کنید', 'gzseo'),
                'api_error' => __('خطا در ارتباط با سرور', 'gzseo'),
                'no_credentials' => __('لطفا ابتدا وارد حساب هوش مصنوعی شوید', 'gzseo')
            )
        ));
    }
    
    /**
     * Detect active SEO plugin
     */
    private function detect_seo_plugin() {
        $active_plugins = array();
        
        // Check for Yoast SEO
        if (defined('WPSEO_VERSION') || is_plugin_active('wordpress-seo/wp-seo.php') || is_plugin_active('wordpress-seo-premium/wp-seo-premium.php')) {
            $active_plugins[] = 'yoast';
        }
        
        // Check for Rank Math
        if (defined('RANK_MATH_VERSION') || is_plugin_active('seo-by-rank-math/rank-math.php')) {
            $active_plugins[] = 'rankmath';
        }
        
        // Check for All in One SEO
        if (defined('AIOSEO_VERSION') || is_plugin_active('all-in-one-seo-pack/all_in_one_seo_pack.php') || is_plugin_active('all-in-one-seo-pack-pro/all_in_one_seo_pack.php')) {
            $active_plugins[] = 'aioseo';
        }
        
        // Check for SEOPress
        if (defined('SEOPRESS_VERSION') || is_plugin_active('wp-seopress/seopress.php') || is_plugin_active('wp-seopress-pro/seopress-pro.php')) {
            $active_plugins[] = 'seopress';
        }
        
        // Check for The SEO Framework
        if (defined('THE_SEO_FRAMEWORK_VERSION') || is_plugin_active('autodescription/autodescription.php')) {
            $active_plugins[] = 'seoframework';
        }
        
        return $active_plugins;
    }
    
    /**
     * Get current meta description from SEO plugins
     */
    private function get_seo_meta_description($post_id) {
        $descriptions = array();
        $seo_plugins = $this->detect_seo_plugin();
        
        foreach ($seo_plugins as $plugin) {
            switch ($plugin) {
                case 'yoast':
                    $desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
                    if (!empty($desc)) {
                        $descriptions['yoast'] = $desc;
                    }
                    break;
                    
                case 'rankmath':
                    $desc = get_post_meta($post_id, 'rank_math_description', true);
                    if (!empty($desc)) {
                        $descriptions['rankmath'] = $desc;
                    }
                    break;
                    
                case 'aioseo':
                    $desc = get_post_meta($post_id, '_aioseo_description', true);
                    if (!empty($desc)) {
                        $descriptions['aioseo'] = $desc;
                    }
                    break;
                    
                case 'seopress':
                    $desc = get_post_meta($post_id, '_seopress_titles_desc', true);
                    if (!empty($desc)) {
                        $descriptions['seopress'] = $desc;
                    }
                    break;
                    
                case 'seoframework':
                    $desc = get_post_meta($post_id, '_genesis_description', true);
                    if (!empty($desc)) {
                        $descriptions['seoframework'] = $desc;
                    }
                    break;
            }
        }
        
        // Get GZSEO custom meta description
        $gzseo_desc = get_post_meta($post_id, '_gzseo_meta_description', true);
        if (!empty($gzseo_desc)) {
            $descriptions['gzseo'] = $gzseo_desc;
        }
        
        // Return first found description or empty string
        return !empty($descriptions) ? reset($descriptions) : '';
    }
    
    /**
     * Update meta description in all SEO plugins
     */
    private function update_seo_meta_description($post_id, $description) {
        $seo_plugins = $this->detect_seo_plugin();
        $updated = array();
        
        foreach ($seo_plugins as $plugin) {
            switch ($plugin) {
                case 'yoast':
                    update_post_meta($post_id, '_yoast_wpseo_metadesc', $description);
                    $updated[] = 'Yoast SEO';
                    break;
                    
                case 'rankmath':
                    update_post_meta($post_id, 'rank_math_description', $description);
                    $updated[] = 'Rank Math';
                    break;
                    
                case 'aioseo':
                    update_post_meta($post_id, '_aioseo_description', $description);
                    // AIOSEO 4.x uses JSON format
                    $aioseo_data = get_post_meta($post_id, '_aioseo_keywords', true);
                    if (empty($aioseo_data)) {
                        $aioseo_data = array();
                    }
                    $aioseo_data['description'] = $description;
                    update_post_meta($post_id, '_aioseo_keywords', $aioseo_data);
                    $updated[] = 'All in One SEO';
                    break;
                    
                case 'seopress':
                    update_post_meta($post_id, '_seopress_titles_desc', $description);
                    $updated[] = 'SEOPress';
                    break;
                    
                case 'seoframework':
                    update_post_meta($post_id, '_genesis_description', $description);
                    $updated[] = 'The SEO Framework';
                    break;
            }
        }
        
        // Always save in our custom meta field
        update_post_meta($post_id, '_gzseo_meta_description', $description);
        
        return $updated;
    }
    
    /**
     * Get current SEO title from SEO plugins
     */
    private function get_seo_title($post_id) {
        $titles = array();
        $seo_plugins = $this->detect_seo_plugin();
        
        foreach ($seo_plugins as $plugin) {
            switch ($plugin) {
                case 'yoast':
                    $title = get_post_meta($post_id, '_yoast_wpseo_title', true);
                    if (!empty($title)) {
                        $titles['yoast'] = $title;
                    }
                    break;
                    
                case 'rankmath':
                    $title = get_post_meta($post_id, 'rank_math_title', true);
                    if (!empty($title)) {
                        $titles['rankmath'] = $title;
                    }
                    break;
                    
                case 'aioseo':
                    $title = get_post_meta($post_id, '_aioseo_title', true);
                    if (!empty($title)) {
                        $titles['aioseo'] = $title;
                    }
                    break;
                    
                case 'seopress':
                    $title = get_post_meta($post_id, '_seopress_titles_title', true);
                    if (!empty($title)) {
                        $titles['seopress'] = $title;
                    }
                    break;
                    
                case 'seoframework':
                    $title = get_post_meta($post_id, '_genesis_title', true);
                    if (!empty($title)) {
                        $titles['seoframework'] = $title;
                    }
                    break;
            }
        }
        
        // Return first found title or post title
        return !empty($titles) ? reset($titles) : get_the_title($post_id);
    }
    
    /**
     * Update SEO title in all SEO plugins
     */
    private function update_seo_title($post_id, $title) {
        $seo_plugins = $this->detect_seo_plugin();
        $updated = array();
        
        foreach ($seo_plugins as $plugin) {
            switch ($plugin) {
                case 'yoast':
                    update_post_meta($post_id, '_yoast_wpseo_title', $title);
                    $updated[] = 'Yoast SEO';
                    break;
                    
                case 'rankmath':
                    update_post_meta($post_id, 'rank_math_title', $title);
                    $updated[] = 'Rank Math';
                    break;
                    
                case 'aioseo':
                    update_post_meta($post_id, '_aioseo_title', $title);
                    // AIOSEO 4.x uses JSON format
                    $aioseo_data = get_post_meta($post_id, '_aioseo_keywords', true);
                    if (empty($aioseo_data)) {
                        $aioseo_data = array();
                    }
                    $aioseo_data['title'] = $title;
                    update_post_meta($post_id, '_aioseo_keywords', $aioseo_data);
                    $updated[] = 'All in One SEO';
                    break;
                    
                case 'seopress':
                    update_post_meta($post_id, '_seopress_titles_title', $title);
                    $updated[] = 'SEOPress';
                    break;
                    
                case 'seoframework':
                    update_post_meta($post_id, '_genesis_title', $title);
                    $updated[] = 'The SEO Framework';
                    break;
            }
        }
        
        return $updated;
    }
    
    /**
     * Render content for metabox
     */
    public function render_content($post) {
        // Get AI credentials
        $ai_email = get_option('gzseo_ai_email', '');
        $ai_password = get_option('gzseo_ai_password', '');
        $is_connected = get_option('gzseo_ai_logged_in', false);
        
        // Get current title and meta description
        $current_title = get_the_title($post);
        $current_description = $this->get_seo_meta_description($post->ID);
        
        // Get active SEO plugins
        $active_seo_plugins = $this->detect_seo_plugin();
        ?>
        <div class="gzseo-title-desc-wrapper">
            <?php if (!$is_connected): ?>
                <div class="gzseo-ai-login-notice">
                    <i class="fas fa-exclamation-circle"></i>
                    <p><?php esc_html_e('برای استفاده از این قابلیت، ابتدا باید وارد حساب کاربری خود شوید.', 'gzseo'); ?></p>
                    <a href="<?php echo esc_url( admin_url('admin.php?page=gzseo-dashboard#ai') ); ?>" class="button button-primary">
                        <?php esc_html_e('ورود به حساب هوش مصنوعی', 'gzseo'); ?>
                    </a>
                </div>
            <?php else: ?>
                <!-- Active SEO Plugins Notice -->
                <?php if (!empty($active_seo_plugins)): ?>
                    <div class="gzseo-seo-plugins-notice">
                        <i class="fas fa-info-circle"></i>
                        <span><?php esc_html_e('افزونه‌های SEO فعال: ', 'gzseo'); ?></span>
                        <?php 
                        $plugin_names = array(
                            'yoast' => 'Yoast SEO',
                            'rankmath' => 'Rank Math',
                            'aioseo' => 'All in One SEO',
                            'seopress' => 'SEOPress',
                            'seoframework' => 'The SEO Framework'
                        );
                        $names = array();
                        foreach ($active_seo_plugins as $plugin) {
                            if (isset($plugin_names[$plugin])) {
                                $names[] = $plugin_names[$plugin];
                            }
                        }
                        echo esc_html( implode('، ', $names) );
                        ?>
                    </div>
                <?php endif; ?>
                
                <!-- Current Title & Description -->
                <div class="gzseo-td-current">
                    <h4><?php esc_html_e('عنوان و توضیحات فعلی:', 'gzseo'); ?></h4>
                    <div class="gzseo-td-current-item">
                        <label><?php esc_html_e('عنوان:', 'gzseo'); ?></label>
                        <div class="gzseo-td-current-value"><?php echo esc_html($current_title); ?></div>
                    </div>
                    <div class="gzseo-td-current-item">
                        <label><?php esc_html_e('توضیحات متا:', 'gzseo'); ?></label>
                        <div class="gzseo-td-current-value">
                            <?php echo !empty($current_description) ? esc_html($current_description) : '<span class="gzseo-td-empty">' . esc_html__('توضیحات متا تعریف نشده', 'gzseo') . '</span>'; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Generate Button -->
                <div class="gzseo-td-actions">
                    <button type="button" class="button button-primary" id="gzseo-generate-title-desc">
                        <i class="fas fa-magic"></i> <?php esc_html_e('تولید عنوان و توضیحات جدید', 'gzseo'); ?>
                    </button>
                    <span class="gzseo-td-credits">
                        <!--<i class="fas fa-coins"></i> هر تولید: 50 کلمه-->
                    </span>
                </div>
                
                <!-- Generated Results Container -->
                <div id="gzseo-td-results" class="gzseo-td-results" style="display: none;">
                    <h4><?php esc_html_e('عناوین و توضیحات تولید شده:', 'gzseo'); ?></h4>
                    <div class="gzseo-td-results-list"></div>
                    
                    <!-- Save Button -->
                    <div class="gzseo-td-save-actions" style="display: none;">
                        <button type="button" class="button button-primary" id="gzseo-save-selected-td">
                            <i class="fas fa-save"></i> <?php esc_html_e('ذخیره عنوان و توضیحات انتخابی', 'gzseo'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Loading Indicator -->
                <div class="gzseo-td-loading" style="display: none;">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span><?php esc_html_e('در حال تولید عنوان و توضیحات...', 'gzseo'); ?></span>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Handle generate title and description AJAX request
     */
    public function handle_generate_title_desc() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) ) {
            wp_send_json_error( array( 'message' => __( 'خطای امنیتی', 'gzseo' ) ) );
            return;
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'gzseo_title_desc_nonce' ) ) {
            wp_send_json_error(array('message' => __('خطای امنیتی', 'gzseo')));
            return;
        }

        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('شما مجوز لازم برای تولید عنوان و توضیحات را ندارید', 'gzseo')));
            return;
        }
        
        // Get post data
        $post_id = isset($_POST['post_id']) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
        if (!$post_id) {
            wp_send_json_error(array('message' => __('شناسه پست معتبر نیست', 'gzseo')));
            return;
        }
        if (!GZSEO_Security_Helpers::current_user_can_edit_post($post_id)) {
            wp_send_json_error(array('message' => __('شما اجازه ویرایش این نوشته را ندارید', 'gzseo')), 403);
        }
        
        // Get post details
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => __('پست یافت نشد', 'gzseo')));
            return;
        }
        
        // Get AI credentials
        $email = get_option('gzseo_ai_email', '');
        $password = get_option('gzseo_ai_password', '');
        
        if (empty($email) || empty($password)) {
            wp_send_json_error(array('message' => __('اطلاعات ورود به هوش مصنوعی یافت نشد', 'gzseo')));
            return;
        }
        
        // Decode password
        $password = GZSEO_Security_Helpers::decrypt_credential($password);
        
        // Prepare API request
        $api_url = 'https://api.gzseo.in/createtitleanddescription';
        $post_type = $post->post_type;
        
        // Map post types to content types
        $content_type_map = array(
            'post' => 'blog',
            'page' => 'page',
            'product' => 'product'
        );
        
        $content_type = isset($content_type_map[$post_type]) ? $content_type_map[$post_type] : 'blog';
        
        // Prepare request body
        $body = array(
            'email' => $email,
            'password' => $password,
            'title' => $post->post_title,
            'description' => wp_trim_words($post->post_content, 100, ''),
            'contenttype' => $content_type
        );
        
        // Make API request
        $response = wp_remote_post($api_url, array(
            'method' => 'POST',
                'timeout' => 30,
                'limit_response_size' => 2 * MB_IN_BYTES,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($body)
        ));
        
        // Check for errors
        if (is_wp_error($response)) {
            /* translators: %s: Error message from API server connection. */
            wp_send_json_error(array('message' => __('خطا در ارتباط با سرویس هوش مصنوعی', 'gzseo')));
            return;
        }
        
        // Parse response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data) || !is_array($data)) {
            wp_send_json_error(array('message' => __('پاسخ نامعتبر از سرور', 'gzseo')));
            return;
        }
        
        // Check status
        if (!isset($data['status']) || $data['status'] != 1) {
            $error_msg = isset($data['msg']) && is_string($data['msg']) ? $data['msg'] : esc_html__('خطا در تولید عنوان و توضیحات', 'gzseo');
            wp_send_json_error(array('message' => $error_msg));
            return;
        }
        
        // Extract title and description
        if (!isset($data['msg']) || !is_array($data['msg'])) {
            wp_send_json_error(array('message' => __('فرمت پاسخ نامعتبر است', 'gzseo')));
            return;
        }
        
        $title = isset( $data['msg']['title'] ) ? sanitize_text_field( (string) $data['msg']['title'] ) : '';
        $description = isset( $data['msg']['description'] ) ? sanitize_textarea_field( (string) $data['msg']['description'] ) : '';
        
        if (empty($title) || empty($description)) {
            wp_send_json_error(array('message' => __('عنوان یا توضیحات خالی است', 'gzseo')));
            return;
        }
        
        // Update credits (deduct 50 words)
        $current_credits = absint( get_option( 'gzseo_ai_credits', 0 ) );
        $new_credits = max(0, $current_credits - 50);
        update_option('gzseo_ai_credits', $new_credits);
        
        // Return success
        wp_send_json_success(array(
            'title' => $title,
            'description' => $description,
            'credits' => $new_credits,
            'timestamp' => current_time('mysql')
        ));
    }
    
    /**
     * Handle save title and description AJAX request
     */
    public function handle_save_title_desc() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) ) {
            wp_send_json_error( array( 'message' => __( 'خطای امنیتی', 'gzseo' ) ) );
            return;
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'gzseo_title_desc_nonce' ) ) {
            wp_send_json_error(array('message' => __('خطای امنیتی', 'gzseo')));
            return;
        }
        
        // Get data
        $post_id = isset($_POST['post_id']) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
        
        if (!$post_id || empty($title) || empty($description)) {
            wp_send_json_error(array('message' => __('داده‌های ورودی نامعتبر', 'gzseo')));
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => __('شما دسترسی ویرایش این پست را ندارید', 'gzseo')));
            return;
        }
        
        // Update post title
        $update_result = wp_update_post(array(
            'ID' => $post_id,
            'post_title' => $title
        ));
        
        if (is_wp_error($update_result)) {
            wp_send_json_error(array('message' => __('خطا در بروزرسانی عنوان', 'gzseo')));
            return;
        }
        
        // Update SEO titles in all active SEO plugins
        $updated_title_plugins = $this->update_seo_title($post_id, $title);
        
        // Update meta description in all active SEO plugins
        $updated_desc_plugins = $this->update_seo_meta_description($post_id, $description);
        
        // Combine updated plugins list
        $all_updated = array_unique(array_merge($updated_title_plugins, $updated_desc_plugins));
        
        
        $all_updated = array_map( 'sanitize_text_field', $all_updated );// Log the change
        $log_data = array(
            'post_id' => $post_id,
            'old_title' => get_the_title($post_id),
            'new_title' => $title,
            'new_description' => $description,
            'updated_plugins' => $all_updated,
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql')
        );
        
        // Save log
        $logs = get_option('gzseo_title_desc_logs', array());
        $logs[] = $log_data;
        
        // Keep only last 100 logs
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        update_option('gzseo_title_desc_logs', $logs);
        
        // Prepare success message
        $message = __('عنوان و توضیحات با موفقیت ذخیره شد', 'gzseo');
        if (!empty($all_updated)) {
            $message .= ' (' . esc_html__('در', 'gzseo') . ' ' . implode('، ', $all_updated) . ')';
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'title' => $title,
            'description' => $description,
            'updated_plugins' => $all_updated
        ));
    }
    
    /********************************** تولید عنوان و توضیحات متا دسته بندی ها *********************************/
    
    /**
     * Render content for taxonomy term
     */
    public function render_taxonomy_content($term) {
        // Get AI credentials
        $is_connected = get_option('gzseo_ai_logged_in', false);

        // Get current title and meta description
        $current_title = $term->name;
        $current_description = $term->description;

        // Get taxonomy type
        $taxonomy_type = $term->taxonomy;
        $taxonomy_obj = get_taxonomy($taxonomy_type);
        $taxonomy_label = $taxonomy_obj ? $taxonomy_obj->label : esc_html__('دسته‌بندی', 'gzseo');
        ?>
        <div class="gzseo-title-desc-wrapper">
            <?php if (!$is_connected): ?>
                <div class="gzseo-ai-login-notice">
                    <i class="fas fa-exclamation-circle"></i>
                    <p><?php esc_html_e('برای استفاده از این قابلیت، ابتدا باید وارد حساب کاربری خود شوید.', 'gzseo'); ?></p>
                    <a href="<?php echo esc_url( admin_url('admin.php?page=gzseo-dashboard#ai') ); ?>" class="button button-primary">
                        <?php esc_html_e('ورود به حساب هوش مصنوعی', 'gzseo'); ?>
                    </a>
                </div>
            <?php else: ?>
                <!-- Current Title & Description -->
                <div class="gzseo-td-current">
                    <h4><?php
                        /* translators: %s: Taxonomy label (e.g., Categories, Tags, Product Categories). */
                        printf(esc_html__('عنوان و توضیحات فعلی %s:', 'gzseo'), esc_html( $taxonomy_label ));
                    ?></h4>
                    <div class="gzseo-td-current-item">
                        <label><?php esc_html_e('عنوان:', 'gzseo'); ?></label>
                        <div class="gzseo-td-current-value"><?php echo esc_html($current_title); ?></div>
                    </div>
                    <div class="gzseo-td-current-item">
                        <label><?php esc_html_e('توضیحات:', 'gzseo'); ?></label>
                        <div class="gzseo-td-current-value">
                            <?php echo !empty($current_description) ? esc_html(wp_trim_words($current_description, 20)) : '<span class="gzseo-td-empty">' . esc_html__('توضیحات تعریف نشده', 'gzseo') . '</span>'; ?>
                        </div>
                    </div>
                </div>

                <!-- Generate Button -->
                <div class="gzseo-td-actions">
                    <input type="hidden" id="gzseo-taxonomy-term-id" value="<?php echo esc_attr( $term->term_id ); ?>">
                    <input type="hidden" id="gzseo-taxonomy-type" value="<?php echo esc_attr( $taxonomy_type ); ?>">
                    <button type="button" class="button button-primary" id="gzseo-generate-taxonomy-title-desc">
                        <i class="fas fa-magic"></i> <?php esc_html_e('تولید عنوان و توضیحات جدید', 'gzseo'); ?>
                    </button>
                </div>

                <!-- Generated Results Container -->
                <div id="gzseo-taxonomy-td-results" class="gzseo-td-results" style="display: none;">
                    <h4><?php esc_html_e('عناوین و توضیحات تولید شده:', 'gzseo'); ?></h4>
                    <div class="gzseo-td-results-list"></div>

                    <!-- Save Button -->
                    <div class="gzseo-td-save-actions" style="display: none;">
                        <button type="button" class="button button-primary" id="gzseo-save-selected-taxonomy-td">
                            <i class="fas fa-save"></i> <?php esc_html_e('ذخیره عنوان و توضیحات انتخابی', 'gzseo'); ?>
                        </button>
                    </div>
                </div>

                <!-- Loading Indicator -->
                <div class="gzseo-td-loading" style="display: none;">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span><?php esc_html_e('در حال تولید عنوان و توضیحات...', 'gzseo'); ?></span>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Handle generate taxonomy title and description AJAX request
     */
    public function ajax_generate_taxonomy_title_desc() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) ) {
            wp_send_json_error( array( 'message' => __( 'خطای امنیتی', 'gzseo' ) ) );
            return;
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'gzseo_title_desc_nonce' ) ) {
            wp_send_json_error(array('message' => __('خطای امنیتی', 'gzseo')));
        }

        // Check user permissions
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('شما مجوز لازم برای تولید عنوان و توضیحات دسته‌بندی را ندارید', 'gzseo')));
        }

        // Get term data
        $term_id = isset($_POST['term_id']) ? absint( wp_unslash( $_POST['term_id'] ) ) : 0;
        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';

        if (!$term_id) {
            wp_send_json_error(array('message' => __('شناسه دسته‌بندی معتبر نیست', 'gzseo')));
        }

        // Get term details
        $term = get_term($term_id, $taxonomy);
        if (!$term || is_wp_error($term)) {
            wp_send_json_error(array('message' => __('دسته‌بندی یافت نشد', 'gzseo')));
        }

        // Get AI credentials
        $email = get_option('gzseo_ai_email', '');
        $password = get_option('gzseo_ai_password', '');

        if (empty($email) || empty($password)) {
            wp_send_json_error(array('message' => __('اطلاعات ورود به هوش مصنوعی یافت نشد', 'gzseo')));
        }

        // Decode password
        $password = GZSEO_Security_Helpers::decrypt_credential($password);

        // Prepare API request
        $api_url = 'https://api.gzseo.in/createtitleanddescription';

        // Prepare request body
        $body = array(
            'email' => $email,
            'password' => $password,
            'title' => $term->name,
            'description' => wp_trim_words($term->description, 100, ''),
            'contenttype' => 'blog' // برای taxonomy از blog استفاده می‌کنیم
        );

        // Make API request
        $response = wp_remote_post($api_url, array(
            'method' => 'POST',
                'timeout' => 30,
                'limit_response_size' => 2 * MB_IN_BYTES,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($body)
        ));

        // Check for errors
        if (is_wp_error($response)) {
            /* translators: %s: Error message from API server connection. */
            wp_send_json_error(array('message' => __('خطا در ارتباط با سرویس هوش مصنوعی', 'gzseo')));
        }

        // Parse response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data) || !is_array($data)) {
            wp_send_json_error(array('message' => __('پاسخ نامعتبر از سرور', 'gzseo')));
        }

        // Check status
        if (!isset($data['status']) || $data['status'] != 1) {
            $error_msg = isset($data['msg']) && is_string($data['msg']) ? $data['msg'] : esc_html__('خطا در تولید عنوان و توضیحات', 'gzseo');
            wp_send_json_error(array('message' => $error_msg));
        }

        // Extract title and description
        if (!isset($data['msg']) || !is_array($data['msg'])) {
            wp_send_json_error(array('message' => __('فرمت پاسخ نامعتبر است', 'gzseo')));
        }

        $title = isset( $data['msg']['title'] ) ? sanitize_text_field( (string) $data['msg']['title'] ) : '';
        $description = isset( $data['msg']['description'] ) ? sanitize_textarea_field( (string) $data['msg']['description'] ) : '';

        if (empty($title) || empty($description)) {
            wp_send_json_error(array('message' => __('عنوان یا توضیحات خالی است', 'gzseo')));
        }

        // Update credits
        $current_credits = absint( get_option( 'gzseo_ai_credits', 0 ) );
        $new_credits = max(0, $current_credits - 50);
        update_option('gzseo_ai_credits', $new_credits);

        // Return success
        wp_send_json_success(array(
            'title' => $title,
            'description' => $description,
            'credits' => $new_credits,
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * Handle save taxonomy title and description AJAX request
     */
    public function ajax_save_taxonomy_title_desc() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) ) {
            wp_send_json_error( array( 'message' => __( 'خطای امنیتی', 'gzseo' ) ) );
            return;
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'gzseo_title_desc_nonce' ) ) {
            wp_send_json_error(array('message' => __('خطای امنیتی', 'gzseo')));
        }

        // Get data
        $term_id = isset($_POST['term_id']) ? absint( wp_unslash( $_POST['term_id'] ) ) : 0;
        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';

        if (!$term_id || empty($title) || empty($description)) {
            wp_send_json_error(array('message' => __('داده‌های ورودی نامعتبر', 'gzseo')));
        }

        // Check permissions
        $tax_obj = get_taxonomy($taxonomy);
        if (!$tax_obj || !current_user_can($tax_obj->cap->edit_terms)) {
            wp_send_json_error(array('message' => __('شما دسترسی ویرایش این دسته‌بندی را ندارید', 'gzseo')));
        }

        // Update term
        $update_result = wp_update_term($term_id, $taxonomy, array(
            'name' => $title,
            'description' => $description
        ));

        if (is_wp_error($update_result)) {
            /* translators: %s: Error message from taxonomy update operation. */
            wp_send_json_error(array('message' => __('خطا در بروزرسانی دسته‌بندی', 'gzseo')));
        }

        // Log the change
        $log_data = array(
            'term_id' => $term_id,
            'taxonomy' => $taxonomy,
            'old_title' => get_term($term_id)->name,
            'new_title' => $title,
            'new_description' => $description,
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql')
        );

        // Save log
        $logs = get_option('gzseo_taxonomy_title_desc_logs', array());
        $logs[] = $log_data;

        // Keep only last 100 logs
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }

        update_option('gzseo_taxonomy_title_desc_logs', $logs);

        wp_send_json_success(array(
            'message' => esc_html__('عنوان و توضیحات با موفقیت ذخیره شد', 'gzseo'),
            'title' => $title,
            'description' => $description
        ));
    }
}

// Initialize the class
new GZSEO_Title_Description_Generator();
