<?php
/**
 * GZSEO Video Update Module
 * 
 * @package GZSEO
 * @subpackage Video Update
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class GZSEO_Video_Update {
    
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
        // AJAX handlers
        add_action('wp_ajax_gzseo_search_videos', array($this, 'handle_video_search'));
        add_action('wp_ajax_gzseo_save_video_update', array($this, 'handle_save_video_update'));
        add_action('wp_ajax_gzseo_get_video_updates', array($this, 'handle_get_video_updates'));
        
        // Taxonomy AJAX handlers
        add_action('wp_ajax_gzseo_save_taxonomy_video_update', array($this, 'ajax_save_taxonomy_video_update'));
        add_action('wp_ajax_gzseo_toggle_taxonomy_video_status', array($this, 'ajax_toggle_taxonomy_video_status'));
    
        // Display video updates in content
        add_filter('the_content', array($this, 'display_video_updates'), 20);
        add_filter('term_description', array($this, 'display_taxonomy_video_updates'), 20, 2);

        add_action('wp_ajax_gzseo_toggle_video_status', array($this, 'handle_toggle_video_status'));
        add_action('wp_ajax_gzseo_delete_bulk_videos', array($this, 'handle_delete_bulk_videos'));
        add_action('wp_ajax_gzseo_get_bulk_video_stats', array($this, 'handle_get_bulk_video_stats'));
        
        // Enqueue scripts for video update
        add_action('admin_enqueue_scripts', array($this, 'enqueue_video_scripts'));
        
        add_action('wp_ajax_gzseo_get_taxonomy_video_updates', array($this, 'ajax_get_taxonomy_video_updates'));
        
        add_action('wp_ajax_gzseo_delete_taxonomy_video', array($this, 'ajax_delete_taxonomy_video'));
    }
    
    /** HTML allowed only for GZSEO-generated video embeds. */
    private function get_allowed_video_embed_html() {
        return array(
            'iframe' => array(
            'src'             => true,
            'height'          => true,
            'width'           => true,
            'frameborder'     => true,
            'allowfullscreen' => true,
            'loading'         => true,
            'allow'           => true,
            'title'           => true,
            'referrerpolicy'  => true,
            ),
        );
    }
    
    /**
     * Enqueue video update scripts and styles
     */
    public function enqueue_video_scripts($hook) {
        // Check if we're on a post edit page
        if (!in_array($hook, array('post.php', 'post-new.php','term.php'))) {
            return;
        }
        
        // Enqueue video update specific CSS
        wp_enqueue_style(
            'gzseo-video-update',
            GZSEO_PLUGIN_URL . 'assets/css/video-update.css',
            array(),
            GZSEO_VERSION
        );
        
        // Enqueue video update specific script
        wp_enqueue_script(
            'gzseo-video-update',
            GZSEO_PLUGIN_URL . 'assets/js/video-update.js',
            array('jquery'),
            GZSEO_VERSION,
            true
        );
        
        // Localize script with necessary data
        wp_localize_script('gzseo-video-update', 'gzseo_video', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gzseo_video_nonce'),
            'post_id' => get_the_ID(),
            'messages' => array(
                'searching' => __('در حال جستجو...', 'gzseo'),
                'no_results' => __('نتیجه‌ای یافت نشد', 'gzseo'),
                'error' => __('خطا در دریافت ویدیوها', 'gzseo'),
                'select_videos' => __('لطفا حداقل یک ویدیو انتخاب کنید', 'gzseo'),
                'saved' => __('ویدیوها با موفقیت ذخیره شدند', 'gzseo'),
                'save_error' => __('خطا در ذخیره ویدیوها', 'gzseo')
            )
        ));
    }
    
    /**
     * Sanitize videos array from POST data
     * 
     * @param mixed $videos Raw videos data from POST
     * @return array Sanitized videos array
     */
    private function sanitize_videos_array($videos) {
        if (!is_array($videos)) {
            return array();
        }
        
        $sanitized_videos = array();
        
        foreach ($videos as $video) {
            if (!is_array($video)) {
                continue;
            }
            
            $sanitized_video = array();
            
            if (isset($video['platform'])) {
                $sanitized_video['platform'] = sanitize_key($video['platform']);
            }
            
            if (isset($video['url'])) {
                $sanitized_video['url'] = esc_url_raw($video['url']);
            }
            
            if (isset($video['video_id'])) {
                $sanitized_video['video_id'] = sanitize_text_field($video['video_id']);
            }
            
            if (isset($video['bulk_generated'])) {
                $sanitized_video['bulk_generated'] = (bool) $video['bulk_generated'];
            }
            
            if (isset($video['is_active'])) {
                $sanitized_video['is_active'] = (bool) $video['is_active'];
            }
            
            if (isset($video['created_date'])) {
                $sanitized_video['created_date'] = sanitize_text_field($video['created_date']);
            }

            // Never trust embed HTML from the browser. Rebuild it from the
            // validated provider URL so stored data cannot contain scripts.
            if (!empty($sanitized_video['url']) && !empty($sanitized_video['platform'])) {
                $embed_code = $this->generate_embed_code($sanitized_video['url'], $sanitized_video['platform']);
                if ($embed_code) {
                    $sanitized_video['embed_code'] = wp_kses($embed_code, $this->get_allowed_video_embed_html());
                }
            }
            
            $sanitized_videos[] = $sanitized_video;
        }
        
        return $sanitized_videos;
    }
    
    /**
     * Handle video search AJAX request
     */
    public function handle_video_search() {
        // Verify nonce and capability BEFORE any other processing.
        if ( ! GZSEO_Security_Helpers::verify_ajax_request( 'gzseo_video_nonce', 'edit_posts' ) ) {
            return;
        }

        // Get keyword
        $keyword = isset($_POST['keyword']) ? sanitize_text_field(wp_unslash($_POST['keyword'])) : '';
        if (empty($keyword)) {
            wp_send_json_error(array('message' => __('لطفا کلمه کلیدی را وارد کنید', 'gzseo')));
        }
        
        // Get AI credentials
        $email = get_option('gzseo_ai_email', '');
        $password = GZSEO_Security_Helpers::get_ai_password();
        
        if (empty($email) || empty($password)) {
            wp_send_json_error(array('message' => __('ابتدا به حساب هوش مصنوعی وارد شوید', 'gzseo')));
        }
        
        // Call API
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
                'keyword' => $keyword
            )
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => __('خطا در اتصال به سرور', 'gzseo')));
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => __('خطا در پردازش پاسخ سرور', 'gzseo')));
        }
        
        // Process videos
        $processed_videos = $this->process_video_results($data);
        
        wp_send_json_success(array(
            'videos' => $processed_videos,
            'count' => count($processed_videos)
        ));
    }
    
    /**
     * Process video results and prepare embed codes
     */
    public function process_video_results($videos) {
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
                'embed_code' => $this->generate_embed_code($video['url'], $video['platform']),
                'video_id' => $this->extract_video_id($video['url'], $video['platform'])
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
    private function generate_embed_code($url, $platform) {
        switch (strtolower($platform)) {
            case 'youtube':
                return $this->generate_youtube_embed($url);
            case 'aparat':
                return $this->generate_aparat_embed($url);
            case 'namasha':
                return $this->generate_namasha_embed($url);
            case 'tamasha':
                return $this->generate_tamasha_embed($url);
            case 'ifilo':
                return $this->generate_ifilo_embed($url);
            default:
                return '';
        }
    }
    
    /**
     * Generate YouTube embed code
     */
    private function generate_youtube_embed($url) {
        $video_id = $this->extract_youtube_id($url);
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
    private function extract_youtube_id($url) {
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
     * Generate Aparat embed code
     */
    private function generate_aparat_embed($url) {
        // Extract video ID from Aparat URL
        if (preg_match('/aparat\.com\/v\/([^\/?]+)/', $url, $matches)) {
            $video_id = $matches[1];
            return sprintf(
                '<iframe src="https://www.aparat.com/video/video/embed/videohash/%s/vt/frame" width="560" height="315" frameborder="0" allowfullscreen="true"></iframe>',
                esc_attr($video_id)
            );
        }
        return '';
    }
    
    /**
     * Generate Namasha embed code
     */
    private function generate_namasha_embed($url) {
        // Extract video ID from Namasha URL
        if (preg_match('/namasha\.com\/v\/([^\/?]+)/', $url, $matches)) {
            $video_id = $matches[1];
            return sprintf(
                '<iframe src="https://www.namasha.com/embed/%s" width="560" height="315" frameborder="0" allowfullscreen="true"></iframe>',
                esc_attr($video_id)
            );
        }
        return '';
    }
    
    /**
     * Generate Tamasha embed code
     */
    private function generate_tamasha_embed($url) {
        // Extract video ID from Tamasha URL
        if (preg_match('/tamasha\.com\/v\/([^\/?]+)/', $url, $matches)) {
            $video_id = $matches[1];
            return sprintf(
                '<iframe src="https://www.tamasha.com/embed/%s" width="560" height="315" frameborder="0" allowfullscreen="true"></iframe>',
                esc_attr($video_id)
            );
        }
        return '';
    }
    
    /**
     * Generate iFilo embed code
     */
    private function generate_ifilo_embed($url) {
        // Extract video ID from iFilo URL
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
     * Extract video ID based on platform
     */
    private function extract_video_id($url, $platform) {
        switch (strtolower($platform)) {
            case 'youtube':
                return $this->extract_youtube_id($url);
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
        return wp_generate_uuid4();
    }
    
    /**
    * Handle save video update AJAX request (modified to preserve video types)
    */
    public function handle_save_video_update() {
        // Sanitize and verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        
        if (!$nonce || !wp_verify_nonce($nonce, 'gzseo_video_nonce')) {
            wp_send_json_error(array('message' => __('دسترسی غیرمجاز', 'gzseo')));
        }

        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('شما مجوز لازم برای ذخیره ویدیو را ندارید', 'gzseo')));
        }
        
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        if (!GZSEO_Security_Helpers::current_user_can_edit_post($post_id)) {
            wp_send_json_error(array('message' => __('شما اجازه ویرایش این نوشته را ندارید', 'gzseo')), 403);
        }
        $videos = isset($_POST['videos']) ? $this->sanitize_videos_array(wp_unslash($_POST['videos'])) : array();
        $position = isset($_POST['position']) ? sanitize_text_field(wp_unslash($_POST['position'])) : '';
        $is_bulk_update = isset($_POST['is_bulk_update']) ? sanitize_text_field(wp_unslash($_POST['is_bulk_update'])) === 'true' : false;
        $preserve_properties = isset($_POST['preserve_properties']) ? sanitize_text_field(wp_unslash($_POST['preserve_properties'])) === 'true' : false;
        $is_remove_action = isset($_POST['is_remove_action']) ? sanitize_text_field(wp_unslash($_POST['is_remove_action'])) === 'true' : false;
        
        if (!is_array($videos)) {
            $videos = array();
        }
        
        // ذخیره موقعیت
        update_post_meta($post_id, '_gzseo_video_position', $position);
        update_post_meta($post_id, '_gzseo_video_update_date', current_time('mysql'));
        
        if ($is_bulk_update) {
            // آپدیت دسته جمعی
            
            // دریافت ویدیوهای موجود
            $existing_videos = get_post_meta($post_id, '_gzseo_video_updates', true);
            if (!is_array($existing_videos)) {
                $existing_videos = array();
            }
            
            // فیلتر کردن - فقط ویدیوهای دستی را نگه دار
            $manual_videos = array();
            foreach ($existing_videos as $video) {
                if (!isset($video['bulk_generated']) || $video['bulk_generated'] !== true) {
                    $manual_videos[] = $video;
                }
            }
            
            // اضافه کردن ویدیوهای bulk جدید
            foreach ($videos as &$video) {
                $video['bulk_generated'] = true;
                $video['is_active'] = false;
                if (!isset($video['created_date'])) {
                    $video['created_date'] = current_time('mysql');
                }
            }
            
            // ترکیب ویدیوهای دستی با bulk جدید
            $final_videos = array_merge($manual_videos, $videos);
            
            // ذخیره
            update_post_meta($post_id, '_gzseo_video_updates', $final_videos);
            update_post_meta($post_id, '_gzseo_bulk_video_generated', true);
            
            // Invalidate cache after bulk video update
            wp_cache_delete('gzseo_bulk_video_count', 'gzseo');
            wp_cache_delete('gzseo_bulk_video_posts', 'gzseo');
            
            // شمارش
            $bulk_count = count($videos);
            update_post_meta($post_id, '_gzseo_bulk_video_count', $bulk_count);
            
            /* translators: %d: Number of bulk videos. */
            $message = sprintf(__('%d ویدیو bulk جدید اضافه شد', 'gzseo'), $bulk_count);
            
        } else {
            // آپدیت دستی
            
            // اگر این یک عملیات حذف است یا preserve_properties فعال است
            if ($preserve_properties || $is_remove_action) {
                // فقط ویدیوهای ارسال شده را ذخیره کن بدون تغییر ویژگی‌ها
                $corrected_videos = array();
                
                foreach ($videos as $video) {
                    // تبدیل string به boolean برای اطمینان
                    if (isset($video['bulk_generated'])) {
                        if (is_string($video['bulk_generated'])) {
                            $video['bulk_generated'] = ($video['bulk_generated'] === 'true' || $video['bulk_generated'] === '1');
                        }
                    }
                    
                    if (isset($video['is_active'])) {
                        if (is_string($video['is_active'])) {
                            $video['is_active'] = ($video['is_active'] === 'true' || $video['is_active'] === '1');
                        }
                    }
                    
                    $corrected_videos[] = $video;
                }
                
                $final_videos = $corrected_videos;
                
            } else {
                // عملیات عادی اضافه کردن ویدیو دستی
                
                // بررسی دقیق هر ویدیو
                $corrected_videos = array();
                
                foreach ($videos as $video) {
                    // کپی ویدیو
                    $corrected_video = $video;
                    
                    // بررسی bulk_generated
                    if (isset($video['bulk_generated'])) {
                        // تبدیل به boolean اگر string است
                        if (is_string($video['bulk_generated'])) {
                            $corrected_video['bulk_generated'] = ($video['bulk_generated'] === 'true' || $video['bulk_generated'] === '1');
                        }
                    } else {
                        // اگر تعریف نشده، پیش‌فرض false برای ویدیوهای دستی
                        $corrected_video['bulk_generated'] = false;
                    }
                    
                    // بررسی is_active
                    if (isset($video['is_active'])) {
                        if (is_string($video['is_active'])) {
                            $corrected_video['is_active'] = ($video['is_active'] === 'true' || $video['is_active'] === '1');
                        }
                    } else {
                        // اگر تعریف نشده
                        $corrected_video['is_active'] = $corrected_video['bulk_generated'] ? false : true;
                    }
                    
                    // بررسی created_date
                    if (!isset($corrected_video['created_date'])) {
                        $corrected_video['created_date'] = current_time('mysql');
                    }
                    
                    $corrected_videos[] = $corrected_video;
                }
                
                $final_videos = $corrected_videos;
            }
            
            // ذخیره ویدیوها
            update_post_meta($post_id, '_gzseo_video_updates', $final_videos);
            
            // بررسی و به‌روزرسانی متادیتای bulk
            $bulk_count = 0;
            $manual_count = 0;
            $has_bulk = false;
            
            foreach ($final_videos as $video) {
                if (isset($video['bulk_generated']) && $video['bulk_generated'] === true) {
                    $bulk_count++;
                    $has_bulk = true;
                } else {
                    $manual_count++;
                }
            }
            
            if ($has_bulk) {
                update_post_meta($post_id, '_gzseo_bulk_video_generated', true);
                update_post_meta($post_id, '_gzseo_bulk_video_count', $bulk_count);
            } else {
                delete_post_meta($post_id, '_gzseo_bulk_video_generated');
                delete_post_meta($post_id, '_gzseo_bulk_video_count');
            }
            
            // Invalidate cache after video deletion
            wp_cache_delete('gzseo_bulk_video_count', 'gzseo');
            wp_cache_delete('gzseo_bulk_video_posts', 'gzseo');
            
            if (empty($final_videos)) {
                $message = __('همه ویدیوها حذف شدند', 'gzseo');
            } elseif ($is_remove_action) {
                $message = __('ویدیو حذف شد', 'gzseo');
            } else {
                /* translators: %d: Number of videos saved. */
                $message = sprintf(__('تعداد %d ویدیو ذخیره شد', 'gzseo'), count($final_videos));
            }
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'count' => count($final_videos ?? array())
        ));
    }
    
    /**
     * Handle get video updates AJAX request
     */
    public function handle_get_video_updates() {
        // Verify nonce and capability BEFORE any other processing.
        if ( ! GZSEO_Security_Helpers::verify_ajax_request( 'gzseo_video_nonce', 'edit_posts' ) ) {
            return;
        }

        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        if (!GZSEO_Security_Helpers::current_user_can_edit_post($post_id)) {
            wp_send_json_error(array('message' => __('شما اجازه مشاهده این نوشته را ندارید', 'gzseo')), 403);
        }
        
        $videos = get_post_meta($post_id, '_gzseo_video_updates', true);
        $position = get_post_meta($post_id, '_gzseo_video_position', true);

        if (is_array($videos)) {
            foreach ($videos as &$video) {
                if (isset($video['embed_code'])) {
                    $video['embed_code'] = wp_kses($video['embed_code'], $this->get_allowed_video_embed_html());
                }
            }
            unset($video);
        }
        
        wp_send_json_success(array(
            'videos' => is_array($videos) ? $videos : array(),
            'position' => $position ?: 'after_content'
        ));
    }
    
    /**
     * Generate HTML for videos (without any sanitization on embed_code)
     */
    private function generate_video_html($videos) {
        $html = '<div class="gzseo-video-updates">';
        $html .= '<h3>' . esc_html__('ویدیوهای مرتبط', 'gzseo') . '</h3>';
        $html .= '<div class="gzseo-video-grid">';
        
        foreach ($videos as $video) {
            if (isset($video['embed_code'])) {
                $html .= '<div class="gzseo-video-item">';
                $html .= wp_kses( $video['embed_code'], $this->get_allowed_video_embed_html() );
                $html .= '</div>';
            }
        }
        
        $html .= '</div></div>';
        
        // Add basic styles
        $html .= '<style>
            .gzseo-video-updates { margin: 30px 0; }
            .gzseo-video-updates h3 { margin-bottom: 20px; }
            .gzseo-video-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
            .gzseo-video-item iframe { width: 100%; height: auto; aspect-ratio: 16/9; }
        </style>';
        
        return $html;
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
            $paragraphs[count($paragraphs) - 1] .= $insertion;
        }
        
        return implode($closing_p, $paragraphs);
    }
    
    /**
     * Insert content after first heading
     */
    private function insert_after_heading($content, $insertion) {
        $pattern = '/(<h[1-6][^>]*>.*?<\/h[1-6]>)/i';
        
        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1] + strlen($matches[0][0]);
            return substr($content, 0, $pos) . $insertion . substr($content, $pos);
        }
        
        return $content . $insertion;
    }
    
    /**
     * Render content for metabox
     */
    public function render_content($post) {
        // Get AI credentials
        $is_connected = get_option('gzseo_ai_logged_in', false);
        ?>

        <?php if (!$is_connected): ?>
            <div class="gzseo-video-update-wrapper">
                <div class="gzseo-ai-login-notice">
                    <i class="fas fa-exclamation-circle"></i>
                    <p><?php esc_html_e('برای استفاده از قابلیت آپدیت ویدیویی با هوش مصنوعی، ابتدا باید وارد حساب کاربری خود شوید.', 'gzseo'); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=gzseo-dashboard#ai')); ?>" class="button button-primary">
                        <?php esc_html_e('ورود به حساب هوش مصنوعی', 'gzseo'); ?>
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="gzseo-video-update-wrapper">
                <div class="gzseo-video-search-section">
                    <div class="gzseo-video-search-form">
                        <div class="gzseo-form-group">
                            <label for="gzseo-video-keyword"><?php esc_html_e('کلمه کلیدی مورد نظر', 'gzseo'); ?></label>
                            <input type="text" id="gzseo-video-keyword" class="gzseo-input" placeholder="<?php esc_attr_e('مثال: آموزش سئو', 'gzseo'); ?>">
                        </div>
                        <button type="button" class="button button-primary" id="gzseo-search-videos">
                            <i class="fas fa-search"></i> <?php esc_html_e('جستجوی ویدیو', 'gzseo'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="gzseo-video-results-section" style="display: none;">
                    <h4><?php esc_html_e('نتایج جستجو', 'gzseo'); ?></h4>
                    <div class="gzseo-video-results"></div>
                    
                    <div class="gzseo-video-position">
                        <label><?php esc_html_e('محل نمایش ویدیوها:', 'gzseo'); ?></label>
                        <select id="gzseo-video-display-position" class="gzseo-select">
                            <option value="before_content"><?php esc_html_e('ابتدای محتوا', 'gzseo'); ?></option>
                            <option value="after_content" selected><?php esc_html_e('انتهای محتوا', 'gzseo'); ?></option>
                            <option value="after_first_paragraph"><?php esc_html_e('بعد از اولین پاراگراف', 'gzseo'); ?></option>
                            <option value="after_first_heading"><?php esc_html_e('بعد از اولین تیتر', 'gzseo'); ?></option>
                        </select>
                    </div>
                    
                    <div class="gzseo-video-actions" style="display: none;">
                        <button type="button" class="button button-primary" id="gzseo-save-videos">
                            <i class="fas fa-save"></i> <?php esc_html_e('ذخیره ویدیوهای انتخاب شده', 'gzseo'); ?>
                        </button>
                        <span class="gzseo-selected-count">0 <?php esc_html_e('ویدیو انتخاب شده', 'gzseo'); ?></span>
                    </div>
                </div>
                
                <div class="gzseo-saved-videos-section" style="display: none;">
                    <h4><?php esc_html_e('ویدیوهای ذخیره شده', 'gzseo'); ?></h4>
                    <div class="gzseo-saved-videos"></div>
                    
                    <?php
                    // دریافت ویدیوها و بررسی وجود ویدیوهای bulk
                    $all_videos = get_post_meta($post->ID, '_gzseo_video_updates', true);
                    if (is_array($all_videos)) {
                        $has_bulk = false;
                        $has_manual = false;
                        
                        foreach ($all_videos as $video) {
                            if (isset($video['bulk_generated']) && $video['bulk_generated']) {
                                $has_bulk = true;
                            } else {
                                $has_manual = true;
                            }
                        }
                        
                        if ($has_bulk || $has_manual) {
                            ?>
                            <div class="gzseo-video-legend">
                                <h5><?php esc_html_e('راهنمای نمادها:', 'gzseo'); ?></h5>
                                <div class="gzseo-legend-items">
                                    <?php if ($has_manual): ?>
                                    <div class="gzseo-legend-item">
                                        <span class="gzseo-legend-icon manual">
                                            <i class="fas fa-hand-pointer"></i>
                                        </span>
                                        <span><?php esc_html_e('ویدیوهای اضافه شده دستی', 'gzseo'); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($has_bulk): ?>
                                    <div class="gzseo-legend-item">
                                        <span class="gzseo-legend-icon bulk">
                                            <i class="fas fa-robot"></i>
                                        </span>
                                        <span><?php esc_html_e('ویدیوهای تولید شده با آپدیت دسته جمعی', 'gzseo'); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php
                        }
                    }
                    ?>
                </div>
            </div>
            
            <!-- Video Preview Modal -->
            <div class="gzseo-video-modal" id="gzseo-video-modal">
                <div class="gzseo-modal-content">
                    <button class="gzseo-modal-close" id="gzseo-modal-close">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="gzseo-modal-video" id="gzseo-modal-video"></div>
                </div>
            </div>

            <?php
            // بررسی ویدیوهای bulk
            $is_bulk_generated = get_post_meta($post->ID, '_gzseo_bulk_video_generated', true);
            if ($is_bulk_generated):
                $bulk_videos = $this->get_bulk_videos($post->ID);
                if (!empty($bulk_videos)):
            ?>
            <div class="gzseo-bulk-videos-notice">
                <div class="gzseo-notice-header">
                    <i class="fas fa-info-circle"></i>
                    <h4><?php esc_html_e('ویدیوهای تولید شده با آپدیت دسته جمعی', 'gzseo'); ?></h4>
                </div>
                <p><?php esc_html_e('این ویدیوها به صورت خودکار با آپدیت دسته جمعی تولید شده‌اند و به صورت پیش‌فرض غیرفعال هستند.', 'gzseo'); ?></p>
                <p><?php esc_html_e('برای نمایش هر ویدیو در محتوا، آن را فعال کنید:', 'gzseo'); ?></p>
                
                <div class="gzseo-bulk-videos-list">
                    <?php foreach ($bulk_videos as $video): 
                        $is_active = isset($video['is_active']) ? $video['is_active'] : false;
                        $index = $video['original_index']; // استفاده از ایندکس اصلی
                    ?>
                    <div class="gzseo-bulk-video-item <?php echo esc_attr( $is_active ? 'active' : 'inactive' ); ?>" data-index="<?php echo esc_attr( $index ); ?>">
                        <div class="gzseo-video-status">
                            <label class="gzseo-switch">
                                <input type="checkbox" 
                                    class="gzseo-video-toggle" 
                                    data-index="<?php echo esc_attr( $index ); ?>"
                                    <?php checked($is_active); ?>>
                                <span class="gzseo-slider"></span>
                            </label>
                            <span class="gzseo-status-text">
                                <?php echo esc_html( $is_active ? __('فعال', 'gzseo') : __('غیرفعال', 'gzseo') ); ?>
                            </span>
                        </div>
                        
                        <div class="gzseo-video-info">
                            <span class="gzseo-video-platform <?php echo esc_attr( strtolower($video['platform']) ); ?>">
                                <?php echo esc_html( $video['platform'] ); ?>
                            </span>
                            <button type="button" 
                                    class="gzseo-preview-bulk-video" 
                                    data-video='<?php echo esc_attr( wp_json_encode($video) ); ?>'>
                                <i class="fas fa-eye"></i> <?php esc_html_e('پیش‌نمایش', 'gzseo'); ?>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="gzseo-bulk-video-stats">
                    <?php
                    $bulk_count = count($bulk_videos);
                    $active_bulk_count = $this->count_active_bulk_videos($post->ID);
                    ?>
                    <span><?php esc_html_e('تعداد ویدیوهای bulk:', 'gzseo'); ?> <?php echo esc_html( $bulk_count ); ?></span>
                    <span><?php esc_html_e('فعال:', 'gzseo'); ?> <span class="gzseo-active-count"><?php echo esc_html( $active_bulk_count ); ?></span></span>
                </div>
            </div>

            <style>
            .gzseo-bulk-videos-notice {
                background: #f8f9fa;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
            }

            .gzseo-notice-header {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 10px;
            }

            .gzseo-notice-header i {
                color: #ff9800;
                font-size: 20px;
            }

            .gzseo-notice-header h4 {
                margin: 0;
                color: #333;
            }

            .gzseo-bulk-videos-list {
                margin: 20px 0;
                border: 1px solid #ddd;
                border-radius: 4px;
                overflow: hidden;
            }

            .gzseo-bulk-video-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 15px;
                background: #fff;
                border-bottom: 1px solid #eee;
                transition: background-color 0.3s;
            }

            .gzseo-bulk-video-item:last-child {
                border-bottom: none;
            }

            .gzseo-bulk-video-item.active {
                background: #f0f8ff;
            }

            .gzseo-video-status {
                display: flex;
                align-items: center;
                gap: 10px;
            }

            /* Toggle Switch Style */
            .gzseo-switch {
                position: relative;
                display: inline-block;
                width: 50px;
                height: 24px;
            }

            .gzseo-switch input {
                opacity: 0;
                width: 0;
                height: 0;
            }

            .gzseo-slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: #ccc;
                transition: .4s;
                border-radius: 24px;
            }

            .gzseo-slider:before {
                position: absolute;
                content: "";
                height: 16px;
                width: 16px;
                left: 4px;
                bottom: 4px;
                background-color: white;
                transition: .4s;
                border-radius: 50%;
            }

            input:checked + .gzseo-slider {
                background-color: #2196F3;
            }

            input:checked + .gzseo-slider:before {
                transform: translateX(26px);
            }

            .gzseo-status-text {
                font-size: 13px;
                color: #666;
            }

            .gzseo-video-info {
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .gzseo-preview-bulk-video {
                background: none;
                border: 1px solid #ddd;
                padding: 5px 10px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 12px;
                transition: all 0.3s;
            }

            .gzseo-preview-bulk-video:hover {
                background: #f0f0f0;
                border-color: #bbb;
            }

            .gzseo-bulk-video-stats {
                display: flex;
                gap: 20px;
                margin-top: 15px;
                font-size: 14px;
                color: #666;
            }

            .gzseo-bulk-video-stats span {
                display: flex;
                align-items: center;
                gap: 5px;
            }

            .gzseo-video-legend {
                margin-top: 20px;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 8px;
                border: 1px solid #e0e0e0;
            }

            .gzseo-video-legend h5 {
                margin: 0 0 10px 0;
                font-size: 14px;
                color: #333;
            }

            .gzseo-legend-items {
                display: flex;
                gap: 20px;
                flex-wrap: wrap;
            }

            .gzseo-legend-item {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 13px;
                color: #666;
            }

            .gzseo-legend-icon {
                width: 24px;
                height: 24px;
                border-radius: 4px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 12px;
            }

            .gzseo-legend-icon.manual {
                background: #e3f2fd;
                color: #2196f3;
            }

            .gzseo-legend-icon.bulk {
                background: #fff3e0;
                color: #ff9800;
            }
            </style>

            <script>
            jQuery(document).ready(function($) {
                // Toggle video status
                $('.gzseo-video-toggle').on('change', function() {
                    const $toggle = $(this);
                    const index = $toggle.data('index');
                    const status = $toggle.is(':checked');
                    const $item = $toggle.closest('.gzseo-bulk-video-item');
                    
                    $.ajax({
                        url: gzseo_video.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'gzseo_toggle_video_status',
                            nonce: gzseo_video.nonce,
                            post_id: gzseo_video.post_id,
                            video_index: index,
                            status: status
                        },
                        success: function(response) {
                            if (response.success) {
                                if (status) {
                                    $item.addClass('active').removeClass('inactive');
                                    $item.find('.gzseo-status-text').text(<?php echo wp_json_encode(__('فعال', 'gzseo')); ?>);
                                } else {
                                    $item.removeClass('active').addClass('inactive');
                                    $item.find('.gzseo-status-text').text(<?php echo wp_json_encode(__('غیرفعال', 'gzseo')); ?>);
                                }
                                $('.gzseo-active-count').text(response.data.active_count);
                            } else {
                                // Revert on error
                                $toggle.prop('checked', !status);
                                alert(response.data.message);
                            }
                        },
                        error: function() {
                            // Revert on error
                            $toggle.prop('checked', !status);
                            alert(<?php echo wp_json_encode(__('خطا در تغییر وضعیت ویدیو', 'gzseo')); ?>);
                        }
                    });
                });
                
                // Preview bulk video
                $('.gzseo-preview-bulk-video').on('click', function() {
                    const videoData = $(this).data('video');
                    
                    if (!videoData || !videoData.embed_code) return;
                    
                    // Use the existing modal from video update
                    $('#gzseo-modal-video').html(videoData.embed_code);
                    $('#gzseo-video-modal').addClass('active');
                    $('body').css('overflow', 'hidden');
                });
            });
            </script>
        <?php endif; endif; endif; ?>

        <?php
    }


    /**
     * Get bulk generated videos count
     */
    public function get_bulk_videos_count($post_id = null) {
        if ($post_id) {
            $is_bulk = get_post_meta($post_id, '_gzseo_bulk_video_generated', true);
            if ($is_bulk) {
                return get_post_meta($post_id, '_gzseo_bulk_video_count', true);
            }
            return 0;
        }
        
        // Get total count
        global $wpdb;
        
        // Try to get from cache first
        $cache_key = 'gzseo_bulk_video_count';
        $cache_group = 'gzseo';
        $count = wp_cache_get($cache_key, $cache_group);
        
        if (false === $count) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- COUNT(DISTINCT post_id) cannot be expressed via WP_Query; result is cached for 5 minutes.
            $count = $wpdb->get_var(
                "SELECT COUNT(DISTINCT post_id) 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_gzseo_bulk_video_generated' 
                AND meta_value = '1'"
            );
            
            // Cache for 5 minutes (300 seconds)
            wp_cache_set($cache_key, intval($count), $cache_group, 300);
        }
        
        return intval($count);
    }

    /**
     * Get posts with bulk generated videos
     */
    public function get_posts_with_bulk_videos() {
        global $wpdb;
        
        // Try to get from cache first
        $cache_key = 'gzseo_bulk_video_posts';
        $cache_group = 'gzseo';
        $results = wp_cache_get($cache_key, $cache_group);
        
        if (false === $results) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- multi-JOIN cannot be expressed via WP_Query; result is cached for 5 minutes.
            $results = $wpdb->get_results(
                "SELECT p.ID, p.post_title, 
                        pm1.meta_value as video_count,
                        pm2.meta_value as update_date
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_gzseo_bulk_video_count'
                LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_gzseo_video_update_date'
                WHERE pm.meta_key = '_gzseo_bulk_video_generated' 
                AND pm.meta_value = '1'
                ORDER BY pm2.meta_value DESC"
            );
            
            // Cache for 5 minutes (300 seconds)
            wp_cache_set($cache_key, $results, $cache_group, 300);
        }
        
        return $results;
    }

    /**
     * Toggle video activation status
     */
    public function toggle_video_status($post_id, $video_index, $status) {
        $videos = get_post_meta($post_id, '_gzseo_video_updates', true);
        
        if (!is_array($videos) || !isset($videos[$video_index])) {
            return false;
        }
        
        $videos[$video_index]['is_active'] = $status;
        
        return update_post_meta($post_id, '_gzseo_video_updates', $videos);
    }

    /**
     * Get active videos count
     */
    public function get_active_videos_count($post_id) {
        $videos = get_post_meta($post_id, '_gzseo_video_updates', true);
        
        if (!is_array($videos)) {
            return 0;
        }
        
        $active_count = 0;
        foreach ($videos as $video) {
            if (!isset($video['is_active']) || $video['is_active'] === true) {
                $active_count++;
            }
        }
        
        return $active_count;
    }

    /**
    * Delete only bulk videos for a post
    */
    public function delete_bulk_videos($post_id) {
        $all_videos = get_post_meta($post_id, '_gzseo_video_updates', true);
        
        if (!is_array($all_videos)) {
            return false;
        }
        
        // فیلتر کردن و حذف فقط ویدیوهای bulk
        $non_bulk_videos = array();
        foreach ($all_videos as $video) {
            if (!isset($video['bulk_generated']) || !$video['bulk_generated']) {
                $non_bulk_videos[] = $video;
            }
        }
        
        // اگر ویدیوهای غیر bulk وجود دارند، آنها را نگه دار
        if (!empty($non_bulk_videos)) {
            update_post_meta($post_id, '_gzseo_video_updates', $non_bulk_videos);
        } else {
            // اگر هیچ ویدیو غیر bulk وجود ندارد، کل متادیتا را حذف کن
            delete_post_meta($post_id, '_gzseo_video_updates');
        }
        
        // حذف متادیتاهای مربوط به bulk
        delete_post_meta($post_id, '_gzseo_bulk_video_generated');
        delete_post_meta($post_id, '_gzseo_bulk_video_count');
        
        // Invalidate cache after bulk video deletion
        wp_cache_delete('gzseo_bulk_video_count', 'gzseo');
        wp_cache_delete('gzseo_bulk_video_posts', 'gzseo');
        
        // اگر position فقط برای bulk بود، آن را هم حذف کن
        $has_non_bulk = !empty($non_bulk_videos);
        if (!$has_non_bulk) {
            delete_post_meta($post_id, '_gzseo_video_position');
            delete_post_meta($post_id, '_gzseo_video_update_date');
        }
        
        return true;
    }

    /**
     * Handle toggle video status AJAX request
     */
    public function handle_toggle_video_status() {
        // Sanitize and verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        
        if (!$nonce || !wp_verify_nonce($nonce, 'gzseo_video_nonce')) {
            wp_send_json_error(array('message' => __('دسترسی غیرمجاز', 'gzseo')));
        }

        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('شما مجوز لازم برای تغییر وضعیت ویدیو را ندارید', 'gzseo')));
        }
        
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        $video_index = isset($_POST['video_index']) ? absint(wp_unslash($_POST['video_index'])) : 0;
        $status = isset($_POST['status']) && sanitize_text_field(wp_unslash($_POST['status'])) === 'true';
        if (!GZSEO_Security_Helpers::current_user_can_edit_post($post_id)) {
            wp_send_json_error(array('message' => __('شما اجازه ویرایش این نوشته را ندارید', 'gzseo')), 403);
        }
        
        if ($this->toggle_video_status($post_id, $video_index, $status)) {
            wp_send_json_success(array(
                'message' => $status ? __('ویدیو فعال شد', 'gzseo') : __('ویدیو غیرفعال شد', 'gzseo'),
                'active_count' => $this->get_active_videos_count($post_id)
            ));
        } else {
            wp_send_json_error(array('message' => __('خطا در تغییر وضعیت ویدیو', 'gzseo')));
        }
    }

    /**
     * Handle delete bulk videos AJAX request
     */
    public function handle_delete_bulk_videos() {
        // Sanitize and verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        
        if (!$nonce || !wp_verify_nonce($nonce, 'gzseo_delete_bulk_videos')) {
            wp_send_json_error(array('message' => __('دسترسی غیرمجاز', 'gzseo')));
        }

        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('شما مجوز لازم برای حذف ویدیوهای bulk را ندارید', 'gzseo')));
        }
        
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        if (!GZSEO_Security_Helpers::current_user_can_edit_post($post_id)) {
            wp_send_json_error(array('message' => __('شما اجازه ویرایش این نوشته را ندارید', 'gzseo')), 403);
        }
        
        if ($this->delete_bulk_videos($post_id)) {
            wp_send_json_success(array('message' => __('ویدیوهای bulk با موفقیت حذف شدند', 'gzseo')));
        } else {
            wp_send_json_error(array('message' => __('خطا در حذف ویدیوها', 'gzseo')));
        }
    }

    /**
     * Handle get bulk video stats AJAX request
     */
    public function handle_get_bulk_video_stats() {
        // Sanitize and verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        
        if (!$nonce || !wp_verify_nonce($nonce, 'gzseo_video_nonce')) {
            wp_send_json_error(array('message' => __('خطای امنیتی', 'gzseo')));
            return;
        }

        // Check user permissions  
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('شما مجوز لازم را ندارید', 'gzseo')));
            return;
        }
        
        $posts = $this->get_posts_with_bulk_videos();
        $total_posts = count($posts);
        $total_videos = 0;
        
        foreach ($posts as $post) {
            $total_videos += intval($post->video_count);
        }
        
        wp_send_json_success(array(
            'total_posts' => $total_posts,
            'total_videos' => $total_videos,
            'posts' => $posts
        ));
    }

    /**
    * Get only bulk generated videos
    */
    public function get_bulk_videos($post_id) {
        $all_videos = get_post_meta($post_id, '_gzseo_video_updates', true);
        
        if (!is_array($all_videos)) {
            return array();
        }
        
        $bulk_videos = array();
        foreach ($all_videos as $index => $video) {
            if (isset($video['bulk_generated']) && $video['bulk_generated']) {
                $video['original_index'] = $index; // حفظ ایندکس اصلی
                $bulk_videos[] = $video;
            }
        }
        
        return $bulk_videos;
    }

    /**
    * Get only non-bulk videos
    */
    public function get_non_bulk_videos($post_id) {
        $all_videos = get_post_meta($post_id, '_gzseo_video_updates', true);
        
        if (!is_array($all_videos)) {
            return array();
        }
        
        $non_bulk_videos = array();
        foreach ($all_videos as $index => $video) {
            if (!isset($video['bulk_generated']) || !$video['bulk_generated']) {
                $video['original_index'] = $index;
                $non_bulk_videos[] = $video;
            }
        }
        
        return $non_bulk_videos;
    }

    /**
    * Count bulk videos
    */
    public function count_bulk_videos($post_id) {
        $bulk_videos = $this->get_bulk_videos($post_id);
        return count($bulk_videos);
    }

    /**
    * Count active bulk videos
    */
    public function count_active_bulk_videos($post_id) {
        $bulk_videos = $this->get_bulk_videos($post_id);
        
        $active_count = 0;
        foreach ($bulk_videos as $video) {
            if (isset($video['is_active']) && $video['is_active']) {
                $active_count++;
            }
        }
        
        return $active_count;
    }

    /**
    * Update display_video_updates to handle mixed videos correctly
    */
    public function display_video_updates($content) {
        if (!is_singular() || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        
        $post_id = get_the_ID();
        $videos = get_post_meta($post_id, '_gzseo_video_updates', true);
        $position = get_post_meta($post_id, '_gzseo_video_position', true);
        
        if (empty($videos) || !is_array($videos)) {
            return $content;
        }
        
        // فیلتر کردن ویدیوهای فعال (هم bulk و هم غیر bulk)
        $active_videos = array();
        foreach ($videos as $video) {
            // اگر bulk_generated تعریف نشده یا false است، ویدیو غیر bulk است و باید نمایش داده شود
            // اگر bulk_generated true است، فقط در صورت is_active نمایش داده شود
            if (!isset($video['bulk_generated']) || !$video['bulk_generated']) {
                // ویدیو غیر bulk - همیشه نمایش بده
                $active_videos[] = $video;
            } elseif (isset($video['is_active']) && $video['is_active']) {
                // ویدیو bulk فعال
                $active_videos[] = $video;
            }
        }
        
        if (empty($active_videos)) {
            return $content;
        }
        
        $video_html = $this->generate_video_html($active_videos);
        
        switch ($position) {
            case 'before_content':
                return $video_html . $content;
            case 'after_first_paragraph':
                return $this->insert_after_paragraph($content, $video_html, 1);
            case 'after_first_heading':
                return $this->insert_after_heading($content, $video_html);
            case 'after_content':
            default:
                return $content . $video_html;
        }
    }

    public function has_bulk_videos($post_id) {
        $videos = get_post_meta($post_id, '_gzseo_video_updates', true);
        
        if (!is_array($videos)) {
            return false;
        }
        
        foreach ($videos as $video) {
            if (isset($video['bulk_generated']) && $video['bulk_generated'] === true) {
                return true;
            }
        }
        
        return false;
    }

    /**
    * دریافت تعداد دقیق ویدیوهای bulk فعال و غیرفعال
    */
    public function get_bulk_videos_stats($post_id) {
        $videos = get_post_meta($post_id, '_gzseo_video_updates', true);
        
        $stats = array(
            'total_bulk' => 0,
            'active_bulk' => 0,
            'inactive_bulk' => 0,
            'total_manual' => 0
        );
        
        if (!is_array($videos)) {
            return $stats;
        }
        
        foreach ($videos as $video) {
            if (isset($video['bulk_generated']) && $video['bulk_generated'] === true) {
                $stats['total_bulk']++;
                
                if (isset($video['is_active']) && $video['is_active'] === true) {
                    $stats['active_bulk']++;
                } else {
                    $stats['inactive_bulk']++;
                }
            } else {
                $stats['total_manual']++;
            }
        }
        
        return $stats;
    }
    
    /******************************* تولید محتوای ویدیویی تکسونومی ها *****************************/
    /**
     * Render content for taxonomy term
     */
    public function render_taxonomy_content($term) {
        // Get AI credentials
        $is_connected = get_option('gzseo_ai_logged_in', false);
        ?>

        <?php if (!$is_connected): ?>
            <div class="gzseo-video-update-wrapper">
                <div class="gzseo-ai-login-notice">
                    <i class="fas fa-exclamation-circle"></i>
                    <p><?php esc_html_e('برای استفاده از قابلیت آپدیت ویدیویی با هوش مصنوعی، ابتدا باید وارد حساب کاربری خود شوید.', 'gzseo'); ?></p>
                    <a href="<?php echo esc_url( admin_url('admin.php?page=gzseo-dashboard#ai') ); ?>" class="button button-primary">
                        <?php esc_html_e('ورود به حساب هوش مصنوعی', 'gzseo'); ?>
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="gzseo-video-update-wrapper">
                <input type="hidden" id="gzseo-video-term-id" value="<?php echo esc_attr( $term->term_id ); ?>">
                <input type="hidden" id="gzseo-video-taxonomy" value="<?php echo esc_attr( $term->taxonomy ); ?>">

                <div class="gzseo-video-search-section">
                    <div class="gzseo-video-search-form">
                        <div class="gzseo-form-group">
                            <label for="gzseo-video-taxonomy-keyword"><?php esc_html_e('کلمه کلیدی مورد نظر', 'gzseo'); ?></label>
                            <input type="text" id="gzseo-video-taxonomy-keyword" class="gzseo-input" placeholder="<?php esc_attr_e('مثال: آموزش سئو', 'gzseo'); ?>">
                        </div>
                        <button type="button" class="button button-primary" id="gzseo-search-taxonomy-videos">
                            <i class="fas fa-search"></i> <?php esc_html_e('جستجوی ویدیو', 'gzseo'); ?>
                        </button>
                    </div>
                </div>

                <div class="gzseo-video-results-section" style="display: none;">
                    <h4><?php esc_html_e('نتایج جستجو', 'gzseo'); ?></h4>
                    <div class="gzseo-video-results"></div>

                    <div class="gzseo-video-position">
                        <label><?php esc_html_e('محل نمایش ویدیوها:', 'gzseo'); ?></label>
                        <select id="gzseo-video-taxonomy-display-position" class="gzseo-select">
                            <option value="before_content"><?php esc_html_e('ابتدای محتوا', 'gzseo'); ?></option>
                            <option value="after_content" selected><?php esc_html_e('انتهای محتوا', 'gzseo'); ?></option>
                            <option value="after_first_paragraph"><?php esc_html_e('بعد از اولین پاراگراف', 'gzseo'); ?></option>
                            <option value="after_first_heading"><?php esc_html_e('بعد از اولین تیتر', 'gzseo'); ?></option>
                        </select>
                    </div>

                    <div class="gzseo-video-actions" style="display: none;">
                        <button type="button" class="button button-primary" id="gzseo-save-taxonomy-videos">
                            <i class="fas fa-save"></i> <?php esc_html_e('ذخیره ویدیوهای انتخاب شده', 'gzseo'); ?>
                        </button>
                        <span class="gzseo-selected-count">0 <?php esc_html_e('ویدیو انتخاب شده', 'gzseo'); ?></span>
                    </div>
                </div>

                <div class="gzseo-saved-videos-section" style="display: none;">
                    <h4><?php esc_html_e('ویدیوهای ذخیره شده', 'gzseo'); ?></h4>
                    <div class="gzseo-saved-videos-taxonomy gzseo-saved-videos"></div>

                    <?php
                    // دریافت ویدیوها و بررسی وجود ویدیوهای bulk
                    $all_videos = get_term_meta($term->term_id, '_gzseo_video_updates', true);
                    if (is_array($all_videos)) {
                        $has_bulk = false;
                        $has_manual = false;

                        foreach ($all_videos as $video) {
                            if (isset($video['bulk_generated']) && $video['bulk_generated']) {
                                $has_bulk = true;
                            } else {
                                $has_manual = true;
                            }
                        }

                        if ($has_bulk || $has_manual) {
                            ?>
                            <div class="gzseo-video-legend">
                                <h5><?php esc_html_e('راهنمای نمادها:', 'gzseo'); ?></h5>
                                <div class="gzseo-legend-items">
                                    <?php if ($has_manual): ?>
                                    <div class="gzseo-legend-item">
                                        <span class="gzseo-legend-icon manual">
                                            <i class="fas fa-hand-pointer"></i>
                                        </span>
                                        <span><?php esc_html_e('ویدیوهای اضافه شده دستی', 'gzseo'); ?></span>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($has_bulk): ?>
                                    <div class="gzseo-legend-item">
                                        <span class="gzseo-legend-icon bulk">
                                            <i class="fas fa-robot"></i>
                                        </span>
                                        <span><?php esc_html_e('ویدیوهای تولید شده با آپدیت دسته جمعی', 'gzseo'); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php
                        }
                    }
                    ?>
                </div>
            </div>

            <!-- Video Preview Modal -->
            <div class="gzseo-video-modal" id="gzseo-video-taxonomy-modal">
                <div class="gzseo-modal-content">
                    <button class="gzseo-modal-close" id="gzseo-taxonomy-modal-close">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="gzseo-modal-video" id="gzseo-taxonomy-modal-video"></div>
                </div>
            </div>

            <?php
            // بررسی ویدیوهای bulk
            $is_bulk_generated = get_term_meta($term->term_id, '_gzseo_bulk_video_generated', true);
            if ($is_bulk_generated):
                $bulk_videos = $this->get_bulk_taxonomy_videos($term->term_id);
                if (!empty($bulk_videos)):
            ?>
            <div class="gzseo-bulk-videos-notice">
                <div class="gzseo-notice-header">
                    <i class="fas fa-info-circle"></i>
                    <h4><?php esc_html_e('ویدیوهای تولید شده با آپدیت دسته جمعی', 'gzseo'); ?></h4>
                </div>
                <p><?php esc_html_e('این ویدیوها به صورت خودکار با آپدیت دسته جمعی تولید شده‌اند و به صورت پیش‌فرض غیرفعال هستند.', 'gzseo'); ?></p>
                <p><?php esc_html_e('برای نمایش هر ویدیو در محتوا، آن را فعال کنید:', 'gzseo'); ?></p>

                <div class="gzseo-bulk-videos-list">
                    <?php foreach ($bulk_videos as $video): 
                        $is_active = isset($video['is_active']) ? $video['is_active'] : false;
                        $index = $video['original_index'];
                    ?>
                    <div class="gzseo-bulk-video-item <?php echo esc_attr( $is_active ? 'active' : 'inactive' ); ?>" data-index="<?php echo esc_attr( $index ); ?>">
                        <div class="gzseo-video-status">
                            <label class="gzseo-switch">
                                <input type="checkbox" 
                                    class="gzseo-taxonomy-video-toggle" 
                                    data-index="<?php echo esc_attr( $index ); ?>"
                                    <?php checked($is_active); ?>>
                                <span class="gzseo-slider"></span>
                            </label>
                            <span class="gzseo-status-text">
                                <?php echo esc_html( $is_active ? __('فعال', 'gzseo') : __('غیرفعال', 'gzseo') ); ?>
                            </span>
                        </div>

                        <div class="gzseo-video-info">
                            <span class="gzseo-video-platform <?php echo esc_attr( strtolower($video['platform']) ); ?>">
                                <?php echo esc_html( $video['platform'] ); ?>
                            </span>
                            <button type="button" 
                                    class="gzseo-preview-taxonomy-bulk-video" 
                                    data-video='<?php echo esc_attr( wp_json_encode($video) ); ?>'>
                                <i class="fas fa-eye"></i> <?php esc_html_e('پیش‌نمایش', 'gzseo'); ?>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="gzseo-bulk-video-stats">
                    <?php
                    $bulk_count = count($bulk_videos);
                    $active_bulk_count = $this->count_active_taxonomy_bulk_videos($term->term_id);
                    ?>
                    <span><?php esc_html_e('تعداد ویدیوهای bulk:', 'gzseo'); ?> <?php echo esc_html( $bulk_count ); ?></span>
                    <span><?php esc_html_e('فعال:', 'gzseo'); ?> <span class="gzseo-taxonomy-active-count"><?php echo esc_html( $active_bulk_count ); ?></span></span>
                </div>
            </div>
            <?php endif; endif; endif; ?>
        <?php
    }

    /**
     * Get bulk generated videos for taxonomy
     */
    public function get_bulk_taxonomy_videos($term_id) {
        $all_videos = get_term_meta($term_id, '_gzseo_video_updates', true);

        if (!is_array($all_videos)) {
            return array();
        }

        $bulk_videos = array();
        foreach ($all_videos as $index => $video) {
            if (isset($video['bulk_generated']) && $video['bulk_generated']) {
                $video['original_index'] = $index;
                $bulk_videos[] = $video;
            }
        }

        return $bulk_videos;
    }

    /**
     * Count active bulk videos for taxonomy
     */
    public function count_active_taxonomy_bulk_videos($term_id) {
        $bulk_videos = $this->get_bulk_taxonomy_videos($term_id);

        $active_count = 0;
        foreach ($bulk_videos as $video) {
            if (isset($video['is_active']) && $video['is_active']) {
                $active_count++;
            }
        }

        return $active_count;
    }

    /**
     * Handle save taxonomy video update AJAX request - Final Fixed Version
     */
    public function ajax_save_taxonomy_video_update() {
        // Sanitize and verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        
        if (!$nonce || !wp_verify_nonce($nonce, 'gzseo_video_nonce')) {
            wp_send_json_error(array('message' => __('دسترسی غیرمجاز', 'gzseo')));
        }

        // Check user permissions
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('شما مجوز لازم برای ذخیره ویدیو در دسته‌بندی را ندارید', 'gzseo')));
        }

        $term_id = isset($_POST['term_id']) ? absint(wp_unslash($_POST['term_id'])) : 0;
        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key(wp_unslash($_POST['taxonomy'])) : '';
        if (!GZSEO_Security_Helpers::current_user_can_edit_term($term_id, $taxonomy)) {
            wp_send_json_error(array('message' => __('شما اجازه ویرایش این دسته‌بندی را ندارید', 'gzseo')), 403);
        }
        $videos = isset($_POST['videos']) ? $this->sanitize_videos_array(wp_unslash($_POST['videos'])) : array();
        $position = isset($_POST['position']) ? sanitize_text_field(wp_unslash($_POST['position'])) : '';
        $is_bulk_update = isset($_POST['is_bulk_update']) ? sanitize_text_field(wp_unslash($_POST['is_bulk_update'])) === 'true' : false;

        if (!is_array($videos) || empty($videos)) {
            wp_send_json_error(array('message' => __('هیچ ویدیویی دریافت نشد', 'gzseo')));
        }

        // دریافت ویدیوهای موجود
        $existing_videos = get_term_meta($term_id, '_gzseo_video_updates', true);
        if (!is_array($existing_videos)) {
            $existing_videos = array();
        }

        // ذخیره موقعیت
        update_term_meta($term_id, '_gzseo_video_position', $position);
        update_term_meta($term_id, '_gzseo_video_update_date', current_time('mysql'));

        if ($is_bulk_update) {
            // آپدیت دسته جمعی
            $manual_videos = array();
            foreach ($existing_videos as $video) {
                if (!isset($video['bulk_generated']) || $video['bulk_generated'] !== true) {
                    $manual_videos[] = $video;
                }
            }

            $processed_videos = array();
            foreach ($videos as $video) {
                if (!isset($video['embed_code']) || empty($video['embed_code'])) {
                    continue;
                }

                $video['bulk_generated'] = true;
                $video['is_active'] = false;
                if (!isset($video['created_date'])) {
                    $video['created_date'] = current_time('mysql');
                }

                $processed_videos[] = $video;
            }

            $final_videos = array_merge($manual_videos, $processed_videos);

            update_term_meta($term_id, '_gzseo_video_updates', $final_videos);
            update_term_meta($term_id, '_gzseo_bulk_video_generated', true);
            update_term_meta($term_id, '_gzseo_bulk_video_count', count($processed_videos));

            /* translators: %d: Number of bulk videos. */
            $message = sprintf(__('%d ویدیو bulk جدید اضافه شد', 'gzseo'), count($processed_videos));

        } else {
            // آپدیت دستی - بدون تغییر در داده‌ها

            $new_videos = array();
            foreach ($videos as $video) {
                // فقط چک می‌کنیم که embed_code وجود داشته باشه
                if (!isset($video['embed_code']) || empty($video['embed_code'])) {
                    continue;
                }

                // ذخیره دقیقاً همون چیزی که از JavaScript اومده
                // فقط فیلدهای امنیتی رو sanitize می‌کنیم
                $safe_video = array(
                    'embed_code' => wp_kses($video['embed_code'], $this->get_allowed_video_embed_html()),
                    'platform' => isset($video['platform']) ? sanitize_text_field($video['platform']) : 'unknown',
                    'url' => isset($video['url']) ? esc_url_raw($video['url']) : '',
                    'video_id' => isset($video['video_id']) ? sanitize_text_field($video['video_id']) : '',
                    'bulk_generated' => false,
                    'is_active' => true,
                    'created_date' => current_time('mysql')
                );

                $new_videos[] = $safe_video;
            }

            if (empty($new_videos)) {
                wp_send_json_error(array('message' => __('هیچ ویدیوی معتبری برای ذخیره وجود ندارد', 'gzseo')));
            }

            // اضافه کردن به ویدیوهای موجود
            $final_videos = array_merge($existing_videos, $new_videos);

            // ذخیره در دیتابیس
            $result = update_term_meta($term_id, '_gzseo_video_updates', $final_videos);

            // بررسی bulk metadata
            $bulk_count = 0;
            $has_bulk = false;

            foreach ($final_videos as $video) {
                if (isset($video['bulk_generated']) && $video['bulk_generated'] === true) {
                    $bulk_count++;
                    $has_bulk = true;
                }
            }

            if ($has_bulk) {
                update_term_meta($term_id, '_gzseo_bulk_video_generated', true);
                update_term_meta($term_id, '_gzseo_bulk_video_count', $bulk_count);
            }

            /* translators: 1: Number of new videos, 2: Total video count. */
            $message = sprintf(__('%1$d ویدیو جدید اضافه شد (مجموع: %2$d)', 'gzseo'), count($new_videos), count($final_videos));
        }

        // بررسی نهایی قبل از ارسال پاسخ
        wp_send_json_success(array(
            'message' => $message,
            'count' => count($final_videos),
            'videos' => $final_videos // ارسال دقیق همون چیزی که ذخیره شده
        ));
    }

    /**
     * Handle toggle taxonomy video status AJAX request
     */
    public function ajax_toggle_taxonomy_video_status() {
        // Sanitize and verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        
        if (!$nonce || !wp_verify_nonce($nonce, 'gzseo_video_nonce')) {
            wp_send_json_error(array('message' => __('دسترسی غیرمجاز', 'gzseo')));
        }

        // Check user permissions
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('شما مجوز لازم برای تغییر وضعیت ویدیو در دسته‌بندی را ندارید', 'gzseo')));
        }

        $term_id = isset($_POST['term_id']) ? absint(wp_unslash($_POST['term_id'])) : 0;
        $video_index = isset($_POST['video_index']) ? absint(wp_unslash($_POST['video_index'])) : 0;
        $status = isset($_POST['status']) && sanitize_text_field(wp_unslash($_POST['status'])) === 'true';

        // Get current videos
        $videos = get_term_meta($term_id, '_gzseo_video_updates', true);

        if (!is_array($videos) || !isset($videos[$video_index])) {
            wp_send_json_error(array('message' => __('ویدیو یافت نشد', 'gzseo')));
        }

        // Update video status
        $videos[$video_index]['is_active'] = $status;

        // Save updated videos
        $updated = update_term_meta($term_id, '_gzseo_video_updates', $videos);

        if ($updated) {
            // Count active videos
            $active_count = 0;
            foreach ($videos as $video) {
                if (isset($video['is_active']) && $video['is_active']) {
                    $active_count++;
                }
            }

            wp_send_json_success(array(
                'message' => $status ? __('ویدیو فعال شد', 'gzseo') : __('ویدیو غیرفعال شد', 'gzseo'),
                'active_count' => $active_count
            ));
        } else {
            wp_send_json_error(array('message' => __('خطا در تغییر وضعیت ویدیو', 'gzseo')));
        }
    }

    public function display_taxonomy_video_updates($description, $term_id = null) {
        // چک کردن که در صفحه taxonomy هستیم
        if (!is_category() && !is_tag() && !is_tax()) {
            return $description;
        }

        // اگر term_id نداریم، سعی کن از queried object دریافت کنیم
        if (empty($term_id)) {
            $queried_object = get_queried_object();
            if (isset($queried_object->term_id)) {
                $term_id = $queried_object->term_id;
            } else {
                return $description;
            }
        }

        $videos = get_term_meta($term_id, '_gzseo_video_updates', true);
        $position = get_term_meta($term_id, '_gzseo_video_position', true);

        if (empty($videos) || !is_array($videos)) {
            return $description;
        }

        // فیلتر کردن ویدیوهای فعال
        $active_videos = array();
        foreach ($videos as $video) {
            // ویدیوهای دستی همیشه نمایش داده میشن
            if (!isset($video['bulk_generated']) || $video['bulk_generated'] !== true) {
                $active_videos[] = $video;
            } 
            // ویدیوهای bulk فقط اگر فعال باشن
            elseif (isset($video['is_active']) && $video['is_active'] === true) {
                $active_videos[] = $video;
            }
        }

        if (empty($active_videos)) {
            return $description;
        }

        // تولید HTML ویدیوها بدون فیلتر کردن
        $video_html = $this->generate_video_html($active_videos);

        // اگر موقعیت تعیین نشده، پیش‌فرض انتهای محتوا
        if (empty($position)) {
            $position = 'after_content';
        }

        switch ($position) {
            case 'before_content':
                return $video_html . $description;

            case 'after_first_paragraph':
                if (!empty($description)) {
                    return $this->insert_after_paragraph($description, $video_html, 1);
                }
                return $description . $video_html;

            case 'after_first_heading':
                if (!empty($description)) {
                    return $this->insert_after_heading($description, $video_html);
                }
                return $description . $video_html;

            case 'after_content':
                return $description . $video_html;
            default:
                return $description . $video_html;
        }
    }
    
    /**
     * Handle get taxonomy video updates AJAX request
     */
    public function ajax_get_taxonomy_video_updates() {
        // Verify nonce and capability BEFORE any other processing.
        if ( ! GZSEO_Security_Helpers::verify_ajax_request( 'gzseo_video_nonce', 'manage_categories' ) ) {
            return;
        }

        $term_id = isset($_POST['term_id']) ? absint(wp_unslash($_POST['term_id'])) : 0;
        if (!GZSEO_Security_Helpers::current_user_can_edit_term($term_id)) {
            wp_send_json_error(array('message' => __('شما اجازه مشاهده این دسته‌بندی را ندارید', 'gzseo')), 403);
        }

        $videos = get_term_meta($term_id, '_gzseo_video_updates', true);
        $position = get_term_meta($term_id, '_gzseo_video_position', true);
        if (is_array($videos)) {
            foreach ($videos as &$video) {
                if (isset($video['embed_code'])) {
                    $video['embed_code'] = wp_kses($video['embed_code'], $this->get_allowed_video_embed_html());
                }
            }
            unset($video);
        }

        wp_send_json_success(array(
            'videos' => is_array($videos) ? $videos : array(),
            'position' => $position ?: 'after_content'
        ));
    }
    
    /**
     * Handle delete taxonomy video AJAX request
     */
    public function ajax_delete_taxonomy_video() {
        // Sanitize and verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        
        if (!$nonce || !wp_verify_nonce($nonce, 'gzseo_video_nonce')) {
            wp_send_json_error(array('message' => __('دسترسی غیرمجاز', 'gzseo')));
        }

        // Check user permissions
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('شما مجوز لازم برای حذف ویدیو از دسته‌بندی را ندارید', 'gzseo')));
        }

        $term_id = isset($_POST['term_id']) ? absint(wp_unslash($_POST['term_id'])) : 0;
        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key(wp_unslash($_POST['taxonomy'])) : '';
        $video_index = isset($_POST['video_index']) ? absint(wp_unslash($_POST['video_index'])) : 0;
        if (!GZSEO_Security_Helpers::current_user_can_edit_term($term_id, $taxonomy)) {
            wp_send_json_error(array('message' => __('شما اجازه ویرایش این دسته‌بندی را ندارید', 'gzseo')), 403);
        }

        // دریافت ویدیوهای موجود
        $videos = get_term_meta($term_id, '_gzseo_video_updates', true);

        if (!is_array($videos)) {
            wp_send_json_error(array('message' => __('ویدیویی یافت نشد', 'gzseo')));
        }

        // بررسی وجود ایندکس
        if (!isset($videos[$video_index])) {
            wp_send_json_error(array('message' => __('ایندکس ویدیو نامعتبر است', 'gzseo')));
        }

        // حذف ویدیو
        array_splice($videos, $video_index, 1);

        // ذخیره لیست جدید
        update_term_meta($term_id, '_gzseo_video_updates', $videos);

        // بررسی و به‌روزرسانی متادیتای bulk
        $bulk_count = 0;
        $has_bulk = false;

        foreach ($videos as $video) {
            if (isset($video['bulk_generated']) && $video['bulk_generated'] === true) {
                $bulk_count++;
                $has_bulk = true;
            }
        }

        if ($has_bulk) {
            update_term_meta($term_id, '_gzseo_bulk_video_count', $bulk_count);
        } else {
            delete_term_meta($term_id, '_gzseo_bulk_video_generated');
            delete_term_meta($term_id, '_gzseo_bulk_video_count');
        }

        // اگر همه ویدیوها حذف شدند
        if (empty($videos)) {
            delete_term_meta($term_id, '_gzseo_video_position');
            delete_term_meta($term_id, '_gzseo_video_update_date');
        }

        wp_send_json_success(array(
            'message' => esc_html__('ویدیو با موفقیت حذف شد', 'gzseo'),
            'count' => count($videos),
            'videos' => $videos
        ));
    }
}

// Initialize the class
new GZSEO_Video_Update();
