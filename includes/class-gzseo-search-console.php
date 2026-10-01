<?php
/**
 * GZ SEO Search Console Module
 * 
 * @package GZ_SEO
 * @subpackage Modules/Search_Console
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class GZSEO_Search_Console {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    public function init_hooks() {
        // Ajax handlers
        add_action('wp_ajax_gzseo_search_console_auth', array($this, 'handle_auth'));
        add_action('wp_ajax_gzseo_search_console_disconnect', array($this, 'handle_disconnect'));
        add_action('wp_ajax_gzseo_search_console_save_settings', array($this, 'handle_save_settings'));
        
        // REST API endpoint for receiving token
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('gzseo/v1', '/importtoken', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_import_token'),
            'permission_callback' => array($this, 'verify_import_token_request'),
        ));
    }

    /**
     * Authenticate the public OAuth callback with a short-lived, one-time state.
     *
     * The callback cannot require a logged-in WordPress user, so the state is
     * generated before redirecting to GZSEO and must be returned unchanged.
     */
    public function verify_import_token_request($request) {
        $state = sanitize_text_field((string) $request->get_param('state'));
        if (empty($state)) {
            return new WP_Error('gzseo_missing_oauth_state', __('OAuth state is missing.', 'gzseo'), array('status' => 403));
        }

        $state_key = 'gzseo_sc_state_' . hash('sha256', $state);
        $expected  = get_transient($state_key);

        if (!is_string($expected) || !hash_equals($expected, hash('sha256', $state))) {
            return new WP_Error('gzseo_invalid_oauth_state', __('OAuth state is invalid or expired.', 'gzseo'), array('status' => 403));
        }

        return true;
    }
    
    /**
     * Process site URL based on domain type
     */
    private function process_site_url($site_url, $domain_type) {
        // حذف فضاهای خالی
        $site_url = trim($site_url);
        
        // ابتدا تمام پیشوندهای احتمالی را حذف می‌کنیم
        $site_url = preg_replace('#^(https?://|sc-domain:)+#i', '', $site_url);
        
        // حذف / از انتهای آدرس
        $site_url = rtrim($site_url, '/');
        
        if ($domain_type === 'domain') {
            // اضافه کردن sc-domain: به ابتدای آدرس
            $site_url = 'sc-domain:' . $site_url;
        } else {
            // برای حالت url، اضافه کردن https://
            $site_url = 'https://' . $site_url;
        }
        
        return $site_url;
    }
    
    /**
     * Get display URL for user interface
     */
    public static function get_display_url() {
        $site_url = get_option('gzseo_sc_site_url', '');
        $domain_type = get_option('gzseo_sc_domain_type', 'domain');
        
        if ($domain_type === 'domain' && !empty($site_url)) {
            // حذف sc-domain: برای نمایش
            $site_url = preg_replace('#^sc-domain:#', '', $site_url);
        }
        
        return $site_url;
    }
    
    /**
     * Handle authentication request
     */
    public function handle_auth() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'gzseo_ajax_nonce')) {
            wp_send_json_error(array('message' => __('خطای امنیتی', 'gzseo')));
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('شما مجوز لازم برای اتصال به Search Console را ندارید', 'gzseo')));
            return;
        }
        
        // Get settings
        $use_advanced = isset($_POST['use_advanced']) && sanitize_text_field(wp_unslash($_POST['use_advanced'])) === 'true';
        $site_url = '';
        $domain_type = 'domain';
        
        if ($use_advanced) {
            $site_url = isset($_POST['site_url']) ? sanitize_text_field(wp_unslash($_POST['site_url'])) : '';
            $domain_type = isset($_POST['domain_type']) ? sanitize_key( wp_unslash( $_POST['domain_type'] ) ) : 'domain';
            // Allow only expected values.
            if ( ! in_array( $domain_type, array( 'domain', 'url' ), true ) ) {
                $domain_type = 'domain';
            }

            
            // Validate inputs
            if (empty($site_url)) {
                wp_send_json_error(array('message' => __('لطفا آدرس سایت را وارد کنید', 'gzseo')));
                return;
            }
            
            // پردازش URL بر اساس نوع دامنه
            $site_url = $this->process_site_url($site_url, $domain_type);
        } else {
            // Use current site URL
            $site_url = get_site_url();
            $parsed_url = wp_parse_url($site_url);
            $site_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
        }
        
        // Save settings
        update_option('gzseo_sc_use_advanced', $use_advanced);
        update_option('gzseo_sc_site_url', $site_url);
        update_option('gzseo_sc_domain_type', $domain_type);
        
        // Get current domain for callback
        /*$callback_domain = get_site_url();
        $parsed_callback = parse_url($callback_domain);
        $callback_domain = $parsed_callback['scheme'] . '://' . $parsed_callback['host'];*/
        $state = wp_generate_password(48, false, false);
        $state_key = 'gzseo_sc_state_' . hash('sha256', $state);
        set_transient($state_key, hash('sha256', $state), 15 * MINUTE_IN_SECONDS);

        $callback_domain = add_query_arg('state', $state, get_rest_url(null, 'gzseo/v1/importtoken'));
        $callback_domain = esc_url_raw( $callback_domain );
        
        // Build authentication URL
        $auth_url = sprintf(
            'https://api.gzseo.in/callback2?siteuri=%s&domain=%s&state=%s',
            urlencode($callback_domain),
            urlencode($site_url),
            urlencode($state)
        );

        // Normalize URL for JSON response.
        $auth_url = esc_url_raw( $auth_url );

        wp_send_json_success(array(
            'auth_url' => $auth_url,
            'message' => __('در حال انتقال به صفحه احراز هویت گوگل...', 'gzseo')
        ));
    }
    
    /**
     * Handle token import from OAuth callback
     */
    public function handle_import_token($request) {
        $params = $request->get_params();
        
        // Log received data for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
        }
        
        // Validate required fields
        $required_fields = array('access_token', 'refresh_token', 'expires_in');
        foreach ($required_fields as $field) {
            if (!isset($params[$field]) || empty($params[$field])) {
                return new WP_REST_Response(array(
                    'success' => false,
                    /* translators: %s: Field name. */
                    'message' => sprintf(__('Missing required field: %s', 'gzseo'), $field)
                ), 400);
            }
        }
        
        // Calculate expiration time
        $expires_at = time() + intval($params['expires_in']);
        
        // Save tokens and data
        update_option('gzseo_sc_access_token', sanitize_text_field($params['access_token']));
        update_option('gzseo_sc_refresh_token', sanitize_text_field($params['refresh_token']));
        update_option('gzseo_sc_token_expires', $expires_at);
        update_option('gzseo_sc_token_type', isset($params['token_type']) ? sanitize_text_field($params['token_type']) : 'Bearer');
        update_option('gzseo_sc_scope', isset($params['scope']) ? sanitize_text_field($params['scope']) : '');
        
        // Save expires_in as raw value for API calls
        update_option('gzseo_sc_expires_in', intval($params['expires_in']));
        
        // Save created timestamp
        $created = isset($params['created']) ? intval($params['created']) : time();
        update_option('gzseo_sc_created', $created);
        
        // Save domain
        if (isset($params['domain'])) {
            update_option('gzseo_sc_domain', sanitize_text_field($params['domain']));
            update_option('gzseo_sc_verified_domain', sanitize_text_field($params['domain']));
        } else {
            // If domain not provided in params, use the site URL we have
            $domain = $this->get_site_url();
            update_option('gzseo_sc_domain', $domain);
        }
        
        // Save additional data if provided
        if (isset($params['email'])) {
            update_option('gzseo_sc_email', sanitize_email($params['email']));
        }
        
        // Mark as connected
        update_option('gzseo_sc_connected', true);
        update_option('gzseo_sc_connected_at', current_time('mysql'));
        
        // Save all data as a single option for easier retrieval
        $search_console_data = array(
            'access_token' => sanitize_text_field($params['access_token']),
            'refresh_token' => sanitize_text_field($params['refresh_token']),
            'expires_in' => intval($params['expires_in']),
            'expires_at' => $expires_at,
            'token_type' => isset($params['token_type']) ? sanitize_text_field($params['token_type']) : 'Bearer',
            'scope' => isset($params['scope']) ? sanitize_text_field($params['scope']) : '',
            'created' => $created,
            'domain' => isset($params['domain']) ? sanitize_text_field($params['domain']) : $this->get_site_url(),
            'email' => isset($params['email']) ? sanitize_email($params['email']) : '',
            'connected_at' => current_time('mysql')
        );
        
        update_option('gzseo_search_console_data', $search_console_data);

        // The OAuth state is single-use; prevent callback replay.
        $state = isset($params['state']) ? sanitize_text_field($params['state']) : '';
        if ($state) {
            delete_transient('gzseo_sc_state_' . hash('sha256', $state));
        }
        
        // Return success response
        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Token imported successfully', 'gzseo')
        ), 200);
    }
    
    /**
     * Handle disconnect request
     */
    public function handle_disconnect() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'gzseo_ajax_nonce')) {
            wp_send_json_error(array('message' => __('خطای امنیتی', 'gzseo')));
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('شما مجوز لازم برای قطع اتصال Search Console را ندارید', 'gzseo')));
            return;
        }
        
        // Clear all Search Console data
        $options_to_delete = array(
            'gzseo_sc_connected',
            'gzseo_sc_access_token',
            'gzseo_sc_refresh_token',
            'gzseo_sc_token_expires',
            'gzseo_sc_expires_in',
            'gzseo_sc_token_type',
            'gzseo_sc_scope',
            'gzseo_sc_email',
            'gzseo_sc_domain',
            'gzseo_sc_verified_domain',
            'gzseo_sc_created',
            'gzseo_sc_connected_at',
            'gzseo_sc_use_advanced',
            'gzseo_sc_site_url',
            'gzseo_sc_domain_type',
            'gzseo_search_console_data' // حذف option جامع
        );
        
        foreach ($options_to_delete as $option) {
            delete_option($option);
        }
        
        wp_send_json_success(array('message' => __('اتصال با موفقیت قطع شد', 'gzseo')));
    }
    
    /**
     * Handle save settings
     */
    public function handle_save_settings() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'gzseo_ajax_nonce')) {
            wp_send_json_error(array('message' => __('خطای امنیتی', 'gzseo')));
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('شما مجوز لازم برای ذخیره تنظیمات را ندارید', 'gzseo')));
            return;
        }
        
        // Save settings
        $use_advanced = isset($_POST['use_advanced']) && sanitize_text_field(wp_unslash($_POST['use_advanced'])) === 'true';
        update_option('gzseo_sc_use_advanced', $use_advanced);
        
        if ($use_advanced) {
            $site_url = isset($_POST['site_url']) ? sanitize_text_field(wp_unslash($_POST['site_url'])) : '';
            $domain_type = isset($_POST['domain_type']) ? sanitize_key( wp_unslash( $_POST['domain_type'] ) ) : 'domain';
            // Allow only expected values.
            if ( ! in_array( $domain_type, array( 'domain', 'url' ), true ) ) {
                $domain_type = 'domain';
            }

            
            // پردازش URL بر اساس نوع دامنه
            $site_url = $this->process_site_url($site_url, $domain_type);
            
            update_option('gzseo_sc_site_url', $site_url);
            update_option('gzseo_sc_domain_type', $domain_type);
            
            // به‌روزرسانی دامنه در gzseo_search_console_data
            $search_console_data = get_option('gzseo_search_console_data', array());
            if (!empty($search_console_data)) {
                $search_console_data['domain'] = $site_url;
                update_option('gzseo_search_console_data', $search_console_data);
            }
            
            // همچنین به‌روزرسانی gzseo_sc_domain
            update_option('gzseo_sc_domain', $site_url);
        }
        
        wp_send_json_success(array('message' => __('تنظیمات ذخیره شد', 'gzseo')));
    }
    
    /**
     * Check if Search Console is connected
     */
    public static function is_connected() {
        return get_option('gzseo_sc_connected', false);
    }
    
    /**
     * Get connected email
     */
    public static function get_connected_email() {
        return get_option('gzseo_sc_email', '');
    }
    
    /**
     * Get access token
     */
    public static function get_access_token() {
        // Check if token is expired
        $expires_at = get_option('gzseo_sc_token_expires', 0);
        if ($expires_at < time()) {
            // Token is expired, need to refresh
            if (!self::refresh_access_token()) {
                return '';
            }
        }
        
        return get_option('gzseo_sc_access_token', '');
    }
    
    /**
     * Refresh access token
     */
    private static function refresh_access_token() {
        $refresh_token = get_option('gzseo_sc_refresh_token', '');
        
        if (empty($refresh_token)) {
            return false;
        }
        
        // Make request to refresh token
        // This would need to be implemented based on your API
        // For now, we'll just return false
        return false;
    }
    
    /**
     * Get site URL for Search Console
     */
    public static function get_site_url() {
        $use_advanced = get_option('gzseo_sc_use_advanced', false);
        
        if ($use_advanced) {
            return get_option('gzseo_sc_site_url', '');
        } else {
            $site_url = get_site_url();
            $parsed_url = wp_parse_url($site_url);
            return $parsed_url['scheme'] . '://' . $parsed_url['host'];
        }
    }
    
    /**
     * Get domain type
     */
    public static function get_domain_type() {
        return get_option('gzseo_sc_domain_type', 'domain');
    }

    public static function get_search_console_data() {
        // ابتدا چک کن که آیا migration نیاز است
        if (GZSEO_Search_Console_Migration::check_migration_needed()) {
            GZSEO_Search_Console_Migration::migrate();
        }
        
        // بازگرداندن اطلاعات
        $data = get_option('gzseo_search_console_data', array());
        
        // اگر data خالی بود، سعی کن از option های جداگانه بسازی
        if (empty($data)) {
            $data = array(
                'access_token' => get_option('gzseo_sc_access_token', ''),
                'refresh_token' => get_option('gzseo_sc_refresh_token', ''),
                'expires_in' => get_option('gzseo_sc_expires_in', 3600),
                'expires_at' => get_option('gzseo_sc_token_expires', 0),
                'created' => get_option('gzseo_sc_created', time()),
                'domain' => get_option('gzseo_sc_domain', ''),
            );
        }
        
        return $data;
    }
}
