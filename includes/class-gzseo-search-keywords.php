<?php
/**
 * GZSEO Search Console Keywords Handler
 * 
 * @package GZSEO
 * @since 2.0.0
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

class GZSEO_Search_Keywords {
    
    /**
     * API URL for search keywords
     */
    const API_URL = 'https://api.gzseo.in/searchword';
    
    /**
     * Constructor
     */
    public function __construct() {
        // حذف action handlers که باعث conflict می‌شوند
        // فقط متدهای utility را نگه می‌داریم
    }

    /**
     * Normalize a Search Console property without treating sc-domain as a URL.
     *
     * @param mixed $property Domain property or URL-prefix property.
     * @return string
     */
    private function normalize_search_console_property($property) {
        $property = is_scalar($property) ? trim(sanitize_text_field((string) $property)) : '';
        if ('' === $property) {
            return '';
        }

        if (0 === stripos($property, 'sc-domain:')) {
            $host = strtolower(trim(substr($property, strlen('sc-domain:'))));
            $host = rtrim($host, '.');

            // A Domain property is a host, never a path, query or fragment.
            if ('' === $host || preg_match('/[\s\/?#]/', $host)) {
                return '';
            }

            return 'sc-domain:' . $host;
        }

        return esc_url_raw($property, array('http', 'https'));
    }
    
    /**
     * Get search keywords from API
     */
    public function get_search_console_keywords( $page_url ) {
        // Normalize and validate input (do not escape for output here).
        $page_url = is_string( $page_url ) ? trim( $page_url ) : '';

        if ( $page_url === '' ) {
            return array();
        }

        // For request payloads, use esc_url_raw() (storage/request context, not HTML output).
        $page_url = esc_url_raw( $page_url );

        if ( $page_url === '' ) {
            return array();
        }

        // دریافت اطلاعات سرچ کنسول از دیتابیس
        $sc_data = get_option( 'gzseo_search_console_data' );

        if ( ! is_array( $sc_data ) || empty( $sc_data['access_token'] ) ) {
            return array(); // Always return an empty array (no fatal/errors in production).
        }

        $stored_domain = isset($sc_data['domain']) ? $sc_data['domain'] : '';
        $domain = $this->normalize_search_console_property($stored_domain);

        // Older/incomplete callbacks may not include domain in the combined
        // option. Fall back to the explicitly saved professional setting.
        if ('' === $domain) {
            $domain = $this->normalize_search_console_property(get_option('gzseo_sc_site_url', ''));
        }
        if ('' === $domain) {
            $domain = $this->normalize_search_console_property(get_site_url());
        }

        // آماده‌سازی داده‌ها برای ارسال
        $post_data = array(
            'access_token'  => isset( $sc_data['access_token'] ) ? (string) $sc_data['access_token'] : '',
            'expires_in'    => isset( $sc_data['expires_in'] ) ? (string) $sc_data['expires_in'] : '',
            'refresh_token' => isset( $sc_data['refresh_token'] ) ? (string) $sc_data['refresh_token'] : '',
            'created'       => isset( $sc_data['created'] ) ? (string) $sc_data['created'] : '',
            'domain'        => $domain,
            'uri'           => $page_url, // آدرس صفحه فعلی
        );

        // ارسال درخواست به API
        $response = wp_remote_post(
            self::API_URL,
            array(
                'method'  => 'POST',
                'timeout' => 30,
                'limit_response_size' => 2 * MB_IN_BYTES,
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ),
                'body'    => $post_data,
            )
        );

        if ( is_wp_error( $response ) ) {
            return array();
        }

        $body        = wp_remote_retrieve_body( $response );
        $status_code = (int) wp_remote_retrieve_response_code( $response );

        if ( 200 !== $status_code || ! is_string( $body ) || $body === '' ) {
            return array();
        }

        $data = json_decode( $body, true );

        if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
            return array();
        }

        // تبدیل فرمت داده‌ها به فرمت مورد انتظار
        $formatted_data = array();

        foreach ( $data as $item ) {
            if ( ! is_array( $item ) || empty( $item['keys'] ) || ! is_array( $item['keys'] ) || empty( $item['keys'][0] ) ) {
                continue;
            }

            $query = sanitize_text_field( (string) $item['keys'][0] );

            if ( $query === '' ) {
                continue;
            }

            $formatted_data[] = array(
                'query'       => $query,
                'clicks'      => isset( $item['clicks'] ) ? (int) $item['clicks'] : 0,
                'impressions' => isset( $item['impressions'] ) ? (int) $item['impressions'] : 0,
                'ctr'         => isset( $item['ctr'] ) ? round( (float) $item['ctr'] * 100, 1 ) : 0,
                'position'    => isset( $item['position'] ) ? round( (float) $item['position'], 1 ) : 0,
            );
        }

        return $formatted_data;
    }
}
