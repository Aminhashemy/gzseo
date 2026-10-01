<?php
/**
 * GZSEO Security Helper Functions
 * 
 * Provides centralized security validation functions for nonce and capability checks
 * following WordPress.org plugin security standards.
 * 
 * @package GZ_SEO
 * @since 2.0.12
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class GZSEO_Security_Helpers {

    /** Encrypt a reversible API credential using the site's WordPress salts. */
    public static function encrypt_credential($value) {
        $value = (string) $value;
        if ('' === $value || !function_exists('openssl_encrypt')) {
            return '';
        }

        $key = hash('sha256', wp_salt('auth') . wp_salt('secure_auth'), true);
        $iv  = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if (false === $ciphertext) {
            return '';
        }

        return 'gzseo:v1:' . base64_encode(wp_json_encode(array(
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'data' => base64_encode($ciphertext),
        )));
    }

    /** Decrypt current credentials and transparently read the legacy Base64 format. */
    public static function decrypt_credential($stored_value) {
        $stored_value = (string) $stored_value;
        if (0 !== strpos($stored_value, 'gzseo:v1:')) {
            $legacy = base64_decode($stored_value, true);
            return false === $legacy ? '' : $legacy;
        }

        if (!function_exists('openssl_decrypt')) {
            return '';
        }

        $payload = json_decode((string) base64_decode(substr($stored_value, 9), true), true);
        if (!is_array($payload) || empty($payload['iv']) || empty($payload['tag']) || empty($payload['data'])) {
            return '';
        }

        $key = hash('sha256', wp_salt('auth') . wp_salt('secure_auth'), true);
        $plaintext = openssl_decrypt(
            (string) base64_decode($payload['data'], true),
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            (string) base64_decode($payload['iv'], true),
            (string) base64_decode($payload['tag'], true)
        );

        return false === $plaintext ? '' : $plaintext;
    }

    public static function get_ai_password() {
        return self::decrypt_credential(get_option('gzseo_ai_password', ''));
    }
    
    /**
     * Verify AJAX request with nonce and capability check
     * 
     * @param string $nonce_action The nonce action to verify
     * @param string $capability The capability required (default: 'edit_posts')
     * @param bool $send_json Whether to send JSON error response (default: true)
     * @return bool True if valid, false otherwise
     */
    public static function verify_ajax_request($nonce_action, $capability = 'edit_posts', $send_json = true) {
        // Verify nonce FIRST - before reading any input
        $nonce_verified = false;
        
        // Sanitize nonce before verification
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if ($nonce && wp_verify_nonce($nonce, $nonce_action)) {
            $nonce_verified = true;
        } else {
            // Try _wpnonce as fallback
            $wpnonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
            if ($wpnonce && wp_verify_nonce($wpnonce, $nonce_action)) {
                $nonce_verified = true;
            }
        }
        
        if (!$nonce_verified) {
            if ($send_json) {
                wp_send_json_error(array(
                    'message' => esc_html__('خطای امنیتی - nonce نامعتبر است', 'gzseo')
                ));
            }
            return false;
        }
        
        // Check user capability
        if (!current_user_can($capability)) {
            if ($send_json) {
                wp_send_json_error(array(
                    'message' => esc_html__('شما مجوز لازم برای انجام این عملیات را ندارید', 'gzseo')
                ));
            }
            return false;
        }
        
        return true;
    }
    
    /**
     * Verify admin page request with nonce and capability check
     * 
     * @param string $nonce_action The nonce action to verify (if empty, skips nonce check for read-only pages)
     * @param string $capability The capability required (default: 'manage_options')
     * @return bool True if valid, false otherwise (dies on failure)
     */
    public static function verify_admin_request($nonce_action = '', $capability = 'manage_options') {
        // Check user capability first
        if (!current_user_can($capability)) {
            wp_die(esc_html__('شما مجوز لازم برای دسترسی به این صفحه را ندارید', 'gzseo'), 403);
        }
        
        // If nonce action provided, verify it (for state-changing operations)
        if (!empty($nonce_action)) {
            $nonce_verified = false;
            
            // Sanitize nonce before verification (POST)
            $wpnonce_post = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
            if ($wpnonce_post && wp_verify_nonce($wpnonce_post, $nonce_action)) {
                $nonce_verified = true;
            } else {
                // Try GET as fallback
                $wpnonce_get = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
                if ($wpnonce_get && wp_verify_nonce($wpnonce_get, $nonce_action)) {
                    $nonce_verified = true;
                }
            }
            
            if (!$nonce_verified) {
                wp_die(esc_html__('خطای امنیتی - nonce نامعتبر است', 'gzseo'), 403);
            }
        }
        
        return true;
    }
    
    /**
     * Sanitize and validate ORDER BY column with strict whitelisting
     * 
     * @param string $orderby The order by column from user input
     * @param array $allowed_columns Array of allowed column names (keys are user input, values are actual column names)
     * @param string $default The default column to use if invalid
     * @return string The sanitized column name
     */
    public static function sanitize_orderby($orderby, $allowed_columns, $default = 'ID') {
        // Strict whitelist validation
        if (isset($allowed_columns[$orderby])) {
            return $allowed_columns[$orderby];
        }
        
        return $default;
    }
    
    /**
     * Sanitize and validate ORDER direction
     * 
     * @param string $order The order direction from user input
     * @param string $default The default direction (default: 'DESC')
     * @return string Either 'ASC' or 'DESC'
     */
    public static function sanitize_order($order, $default = 'DESC') {
        $order = strtoupper(trim($order));
        
        if ($order === 'ASC' || $order === 'DESC') {
            return $order;
        }
        
        return $default;
    }
    
    /**
     * Build safe WHERE clause with proper placeholders
     * 
     * @param array $conditions Array of conditions, each with 'column', 'operator', 'value', 'type' (optional)
     * @param string $relation The relation between conditions (AND/OR, default: AND)
     * @return array Array with 'sql' and 'values' for use with $wpdb->prepare()
     */
    public static function build_where_clause($conditions, $relation = 'AND') {
        global $wpdb;
        
        if (empty($conditions)) {
            return array('sql' => '', 'values' => array());
        }
        
        $relation = (strtoupper($relation) === 'OR') ? 'OR' : 'AND';
        $where_parts = array();
        $values = array();
        
        foreach ($conditions as $condition) {
            // Validate required fields
            if (!isset($condition['column']) || !isset($condition['value'])) {
                continue;
            }
            
            $column = $condition['column'];
            $operator = isset($condition['operator']) ? $condition['operator'] : '=';
            $value = $condition['value'];
            $type = isset($condition['type']) ? $condition['type'] : '%s';

            // SQL identifiers cannot be parameterized; accept only simple,
            // non-quoted identifiers and optional table qualification.
            if (!is_string($column) || !preg_match('/^[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)?$/', $column)) {
                continue;
            }
            if (!in_array($type, array('%s', '%d', '%f'), true)) {
                $type = '%s';
            }
            
            // Validate operator
            $allowed_operators = array('=', '!=', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN');
            if (!in_array(strtoupper($operator), $allowed_operators)) {
                $operator = '=';
            }
            
            // Build condition based on operator
            if (in_array(strtoupper($operator), array('IN', 'NOT IN'))) {
                if (!is_array($value)) {
                    continue;
                }
                
                $placeholders = array();
                foreach ($value as $val) {
                    $placeholders[] = $type;
                    $values[] = $val;
                }
                
                $where_parts[] = $column . ' ' . $operator . ' (' . implode(', ', $placeholders) . ')';
            } else {
                $where_parts[] = $column . ' ' . $operator . ' ' . $type;
                $values[] = $value;
            }
        }
        
        if (empty($where_parts)) {
            return array('sql' => '', 'values' => array());
        }
        
        $sql = 'WHERE ' . implode(' ' . $relation . ' ', $where_parts);
        
        return array('sql' => $sql, 'values' => $values);
    }
    
    /**
     * Get sanitized POST value
     * 
     * @param string $key The POST key
     * @param mixed $default The default value if not set
     * @param string $sanitize_callback The sanitization callback (default: 'sanitize_text_field')
     * @return mixed The sanitized value
     */
    public static function get_post($key, $default = '', $sanitize_callback = 'sanitize_text_field') {
        // Sanitize key to prevent unsafe array access.
        $safe_key = sanitize_key($key);

        if (!isset($_POST[$safe_key])) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            return $default;
        }

        $raw = wp_unslash($_POST[$safe_key]); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        return self::sanitize_value($raw, $sanitize_callback, $default);
    }

    /**
     * Sanitize a value using a known/allowed callback (or safe fallback).
     *
     * This method exists to keep sanitization explicit so PHPCS/WPCS can
     * properly validate sanitization, and to avoid relying on arbitrary callables.
     *
     * @param mixed  $value            Raw unslashed value.
     * @param mixed  $sanitize_callback Sanitizer identifier (string callback name) or callable.
     * @param mixed  $default          Default value used for type fallback where needed.
     * @return mixed
     */
    private static function sanitize_value($value, $sanitize_callback, $default = '') {
        // Handle arrays safely.
        if (is_array($value)) {
            $out = array();
            foreach ($value as $k => $v) {
                $out[sanitize_key((string) $k)] = self::sanitize_value($v, $sanitize_callback, $default);
            }
            return $out;
        }

        $value = (string) $value;
        $callback = is_string($sanitize_callback) ? $sanitize_callback : '';

        switch ($callback) {
            case 'sanitize_text_field':
                return sanitize_text_field($value);

            case 'sanitize_textarea_field':
                return sanitize_textarea_field($value);

            case 'sanitize_email':
                return sanitize_email($value);

            case 'esc_url_raw':
                return esc_url_raw($value);

            case 'sanitize_key':
                return sanitize_key($value);

            case 'absint':
                return absint($value);

            case 'intval':
                return (int) $value;

            case 'floatval':
                return (float) $value;

            default:
                // If a custom callable is provided, apply it to a safely sanitized baseline string.
                if (is_callable($sanitize_callback)) {
                    return call_user_func($sanitize_callback, sanitize_text_field($value));
                }

                // Safe fallback.
                return sanitize_text_field($value);
        }
    }
    /**
     * Get sanitized GET value
     * 
     * @param string $key The GET key
     * @param mixed $default The default value if not set
     * @param string $sanitize_callback The sanitization callback (default: 'sanitize_text_field')
     * @return mixed The sanitized value
     */
    public static function get_get($key, $default = '', $sanitize_callback = 'sanitize_text_field') {
        // Sanitize key to prevent unsafe array access.
        $safe_key = sanitize_key($key);

        if (!isset($_GET[$safe_key])) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            return $default;
        }

        $raw = wp_unslash($_GET[$safe_key]); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        return self::sanitize_value($raw, $sanitize_callback, $default);
    }
    /**
     * Get sanitized integer from POST
     * 
     * @param string $key The POST key
     * @param int $default The default value if not set
     * @return int The sanitized integer
     */
    public static function get_post_int($key, $default = 0) {
        $safe_key = sanitize_key($key);

        if (!isset($_POST[$safe_key])) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            return (int) $default;
        }

        return absint(wp_unslash($_POST[$safe_key])); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    }

    /**
     * Get sanitized integer from GET
     * 
     * @param string $key The GET key
     * @param int $default The default value if not set
     * @return int The sanitized integer
     */
    public static function get_get_int($key, $default = 0) {
        $safe_key = sanitize_key($key);

        if (!isset($_GET[$safe_key])) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            return (int) $default;
        }

        return absint(wp_unslash($_GET[$safe_key])); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    }

    /**
     * Get sanitized boolean from POST
     * 
     * @param string $key The POST key
     * @param bool $default The default value if not set
     * @return bool The sanitized boolean
     */
    public static function get_post_bool($key, $default = false) {
        $safe_key = sanitize_key($key);

        if (!isset($_POST[$safe_key])) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            return (bool) $default;
        }

        $value = wp_unslash($_POST[$safe_key]); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        // Support common boolean representations.
        if (is_bool($value)) {
            return $value;
        }

        $value = is_scalar($value) ? (string) $value : '';

        return in_array(strtolower($value), array('1', 'true', 'yes', 'on'), true);
    }

    /** Check access to the exact post supplied by a request. */
    public static function current_user_can_edit_post($post_id) {
        $post_id = absint($post_id);
        return $post_id > 0 && get_post($post_id) && current_user_can('edit_post', $post_id);
    }

    /** Check access to the exact term and taxonomy supplied by a request. */
    public static function current_user_can_edit_term($term_id, $taxonomy = '') {
        $term_id  = absint($term_id);
        $taxonomy = sanitize_key($taxonomy);
        $term     = $term_id ? get_term($term_id, $taxonomy ?: '') : null;

        if (!$term || is_wp_error($term)) {
            return false;
        }

        $taxonomy_object = get_taxonomy($term->taxonomy);
        return $taxonomy_object && current_user_can($taxonomy_object->cap->edit_terms);
    }

    /**
     * Create nonce URL for admin actions
     * 
     * @param string $url The base URL
     * @param string $action The nonce action
     * @param string $name The nonce parameter name (default: '_wpnonce')
     * @return string The URL with nonce
     */
    public static function nonce_url($url, $action, $name = '_wpnonce') {
        return wp_nonce_url($url, $action, $name);
    }
}
