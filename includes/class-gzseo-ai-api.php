<?php
/**
 * GZSEO AI API Handler
 * 
 * @package GZSEO
 * @since 2.0.0
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

class GZSEO_AI_API {
    
    /**
     * Base API URL
     */
    const API_BASE_URL = 'https://api.gzseo.in/';
    
    /**
     * API Endpoints
     */
    const ENDPOINT_REGISTER = 'register';
    const ENDPOINT_GET_PACKAGES = 'getpackages';
    const ENDPOINT_GET_PAYMENT_LINK = 'getpaymentlink';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_gzseo_ai_register', array($this, 'handle_registration'));
        add_action('wp_ajax_gzseo_ai_get_packages', array($this, 'handle_get_packages'));
        add_action('wp_ajax_gzseo_ai_get_payment_link', array($this, 'handle_get_payment_link'));
        
        add_action('wp_ajax_gzseo_ai_login', array($this, 'handle_ai_login'));
        add_action('wp_ajax_gzseo_ai_logout', array($this, 'handle_ai_logout'));

        // اضافه کردن اکشن برای بررسی پیغام بازگشت از درگاه
        add_action('admin_init', array($this, 'handle_payment_callback'));
        add_action('admin_notices', array($this, 'show_payment_notice'));

        add_action('wp_ajax_gzseo_ai_forget_password', array($this, 'handle_forget_password'));
        add_action('wp_ajax_gzseo_ai_reset_password', array($this, 'handle_reset_password'));
    }

    /**
     * Handle payment callback from payment gateway
     */
    public function handle_payment_callback() {
        // بررسی اینکه آیا در صفحه dashboard هستیم و پارامتر status وجود دارد
        if ( isset( $_GET['page'] ) && isset( $_GET['status'] ) ) {
            $page   = sanitize_text_field( wp_unslash( $_GET['page'] ) );
            $status = sanitize_text_field( wp_unslash( $_GET['status'] ) );

            if ( 'gzseo-dashboard' === $page && 'OK' === $status ) {
                // به‌روزرسانی موجودی کاربر پس از پرداخت موفق
                // فراخوانی متد update_ai_credits از کلاس اصلی افزونه
                        if ( method_exists( $this, 'update_ai_credits' ) ) {
                    $this->update_ai_credits( true ); // force update
                } else {
                    $main_instance = GZSeo::get_instance();
                    if ( method_exists( $main_instance, 'update_ai_credits' ) ) {
                        $main_instance->update_ai_credits( true );
                    }
                }

                // ذخیره پیغام موفقیت در transient برای نمایش یکبار
                set_transient( 'gzseo_payment_success', true, 60 );

                // حذف پارامتر از URL برای جلوگیری از نمایش مجدد پیغام
                $request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
                $redirect_url = remove_query_arg( 'status', $request_uri );
                wp_safe_redirect( admin_url( 'admin.php?page=gzseo-dashboard' ) );
                exit;
                
            } elseif ( 'NOK' === $status ) {
                // ذخیره پیغام خطا در transient برای نمایش یکبار
                set_transient( 'gzseo_payment_error', true, 60 );

                // حذف پارامتر از URL
                $request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
                $redirect_url = remove_query_arg( 'status', $request_uri );
                wp_safe_redirect( admin_url( 'admin.php?page=gzseo-dashboard' ) );
                exit;
            }
        }
    }
    
    /**
     * Show payment notice in admin
     */
    public function show_payment_notice() {
        // بررسی اینکه در صفحه dashboard هستیم
        if ( ! isset( $_GET['page'] ) ) {
            return;
        }

        $page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
        if ( 'gzseo-dashboard' !== $page ) {
            return;
        }
        
        // نمایش پیغام موفقیت
        if ( get_transient( 'gzseo_payment_success' ) ) {
            printf(
                '<div class="notice notice-success is-dismissible gzseo-payment-notice"><p><strong>%1$s</strong> %2$s</p></div>',
                esc_html__( 'پرداخت با موفقیت انجام شد!', 'gzseo' ),
                esc_html__( 'اعتبار شما به حساب کاربری اضافه شده است.', 'gzseo' )
            );
            delete_transient('gzseo_payment_success');
        }
        
        // نمایش پیغام خطا
        if ( get_transient( 'gzseo_payment_error' ) ) {
            printf(
                '<div class="notice notice-error is-dismissible gzseo-payment-notice"><p><strong>%1$s</strong> %2$s</p></div>',
                esc_html__( 'پرداخت ناموفق!', 'gzseo' ),
                esc_html__( 'تراکنش انجام نشد. در صورت کسر وجه، مبلغ ظرف ۷۲ ساعت به حساب شما بازگردانده خواهد شد.', 'gzseo' )
            );
            delete_transient('gzseo_payment_error');
        }
    }
    
    public function handle_ai_login()
    {
        // Sanitize and verify nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'gzseo_ajax_nonce' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'خطای امنیتی - لطفا صفحه را رفرش کنید', 'gzseo' ),
                    'debug'   => array(
                        'nonce_sent' => $nonce,
                        'expected'   => 'gzseo_ajax_nonce',
                    ),
                )
            );
            return;
        }

        // Check user permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'شما مجوز لازم برای ورود به حساب AI را ندارید', 'gzseo' ),
                )
            );
            return;
        }

        if (GZSEO_AI_Migration::check_migration_needed()) {
            GZSEO_AI_Migration::migrate();
        }

        $email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        // Password: unslashed and cast to string (not overly sanitized to preserve valid password characters)
        $password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

        if ( empty( $email ) || empty( $password ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'لطفا ایمیل و رمز عبور را وارد کنید', 'gzseo' ),
                )
            );
            return;
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

        if ( is_wp_error( $response ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'خطا در اتصال به سرور', 'gzseo' ),
                )
            );
            return;
        }

        $body        = wp_remote_retrieve_body( $response );
        $data        = json_decode( $body, true );
        $status_code = wp_remote_retrieve_response_code( $response );

        if ( 200 === $status_code && isset( $data['words'] ) ) {
            // ذخیره اطلاعات کاربری
            update_option('gzseo_ai_email', $email);
            update_option('gzseo_ai_password', GZSEO_Security_Helpers::encrypt_credential($password));
            update_option('gzseo_ai_credits', $data['words']);
            update_option('gzseo_ai_user_data', $data);
            update_option('gzseo_ai_logged_in', true);
            update_option('gzseo_ai_last_login', current_time('mysql'));

            wp_send_json_success(
                array(
                    'message'   => __( 'ورود موفقیت آمیز بود', 'gzseo' ),
                    'credits'   => $data['words'],
                    'user_data' => $data,
                )
            );
        } else {
            $error_message = isset( $data['message'] ) ? $this->sanitize_remote_message( $data['message'] ) : __( 'خطا در ورود', 'gzseo' );
            wp_send_json_error(
                array(
                    'message' => $error_message,
                )
            );
        }
    }

    public function handle_ai_logout()
    {
        // Sanitize and verify nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'gzseo_ajax_nonce' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'دسترسی غیرمجاز', 'gzseo' ),
                )
            );
            return;
        }

        // Check user permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'شما مجوز لازم برای خروج از حساب AI را ندارید', 'gzseo' ),
                )
            );
            return;
        }

        // حذف اطلاعات ذخیره شده
        delete_option('gzseo_ai_email');
        delete_option('gzseo_ai_password');
        delete_option('gzseo_ai_credits');
        delete_option('gzseo_ai_user_data');
        delete_option('gzseo_ai_logged_in');

        wp_send_json_success(
            array(
                'message' => __( 'خروج موفقیت آمیز بود', 'gzseo' ),
            )
        );
    }
    
    /**
     * Handle user registration
     */
    public function handle_registration() {
        // Sanitize and verify nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'gzseo_ajax_nonce' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'دسترسی غیرمجاز', 'gzseo' ),
                )
            );
            return;
        }
        
        // بررسی فیلدهای مورد نیاز
        // Check user permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'شما مجوز لازم برای انجام این عملیات را ندارید', 'gzseo' ),
                )
            );
            return;
        }


        $required_fields = array('name', 'email', 'password');
        foreach ($required_fields as $field) {
            // Sanitize field name for safe array access
            $field = sanitize_key( $field );
            // For password field, cast to string; for others, sanitize appropriately
            $field_value = '';
            if ( isset( $_POST[ $field ] ) ) {
                if ( 'email' === $field ) {
                    $field_value = sanitize_email( wp_unslash( $_POST[ $field ] ) );
                } elseif ( 'name' === $field ) {
                    $field_value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
                } else {
                    $field_value = (string) wp_unslash( $_POST[ $field ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                }
            }
            if ( empty( $field_value ) ) {
                wp_send_json_error(
                    array(
                        'message' => __( 'لطفا همه فیلدها را پر کنید', 'gzseo' ),
                    )
                );
                return;
            }
        }
        
        // آماده‌سازی داده‌ها
        $data = array(
            'name'     => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
            'email'    => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
            // Password: unslashed and cast to string (not overly sanitized to preserve valid password characters)
            'password' => isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        );
        
        // ارسال درخواست به API
        $response = $this->send_request(self::ENDPOINT_REGISTER, $data);
        
        if ( is_wp_error( $response ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'خطا در ارتباط با سرور', 'gzseo' ),
                )
            );
            return;
        }

        $body   = wp_remote_retrieve_body( $response );
        $result = json_decode( $body, true );

        if ( JSON_ERROR_NONE !== json_last_error() ) {
            wp_send_json_error(
                array(
                    'message' => __( 'خطا در پردازش پاسخ سرور', 'gzseo' ),
                )
            );
            return;
        }
        
        // بررسی وضعیت پاسخ
        if (isset($result['error'])) {
            if(gettype($result['error']) == 'array')
            {
                $result['error'] = array_values($result['error'])[0][0];
                if ( 'The email must be a valid email address.' === $result['error'] ) {
                    $result['error'] = __( 'لطفا یک ایمیل معتبر وارد کنید.', 'gzseo' );
                } elseif ( 'The email has already been taken.' === $result['error'] ) {
                    $result['error'] = __( 'این ایمیل قبلا استفاده شده است.لطفا ایمیل دیگری وارد نمایید.', 'gzseo' );
                }
            }
            wp_send_json_error(
                array(
                    'message' => $this->sanitize_remote_message( $result['error'] ),
                )
            );
        } elseif ( isset( $result['message'] ) ) {
            wp_send_json_success(
                array(
                    'message' => $this->sanitize_remote_message( $result['message'] ),
                )
            );
        } else {
            wp_send_json_error(
                array(
                    'message' => __( 'پاسخ نامعتبر از سرور', 'gzseo' ),
                )
            );
        }
    }
    
    /**
     * Handle getting packages list
     */
    public function handle_get_packages() {
        // Sanitize and verify nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'gzseo_ajax_nonce' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'دسترسی غیرمجاز', 'gzseo' ),
                )
            );
            return;
        }
        
        // آماده‌سازی داده‌ها
        // Check user permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'شما مجوز لازم برای انجام این عملیات را ندارید', 'gzseo' ),
                )
            );
            return;
        }


        $data = array(
            'version'      => GZSEO_VERSION,
            'fullpackages' => false,
        );
        
        // ارسال درخواست به API
        $response = $this->send_request(self::ENDPOINT_GET_PACKAGES, $data);

        if ( is_wp_error( $response ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'خطا در ارتباط با سرور', 'gzseo' ),
                )
            );
            return;
        }

        $body     = wp_remote_retrieve_body( $response );
        $packages = json_decode( $body, true );

        if ( JSON_ERROR_NONE !== json_last_error() ) {
            wp_send_json_error(
                array(
                    'message' => __( 'خطا در پردازش پاسخ سرور', 'gzseo' ),
                )
            );
            return;
        }
        
        // تبدیل پکیج‌ها به فرمت مناسب برای نمایش
        $formatted_packages = array();
        foreach ( $packages as $package ) {
            $formatted_packages[] = array(
                'id'           => isset( $package['id'] ) ? absint( $package['id'] ) : 0,
                'name'         => isset( $package['plan_name'] ) ? sanitize_text_field( (string) $package['plan_name'] ) : '',
                'price'        => isset( $package['price'] ) ? $this->format_price( $package['price'] ) : '',
                'tokens'       => $this->calculate_total_tokens( $package ),
                'raw_price'    => isset( $package['price'] ) ? (float) $package['price'] : 0,
                'package_type' => isset( $package['pricing_plan'] ) ? sanitize_key( (string) $package['pricing_plan'] ) : '',
            );
        }
        
        wp_send_json_success( $formatted_packages );
    }
    
    /**
     * Handle getting payment link
     */
    public function handle_get_payment_link() {
        // Sanitize and verify nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'gzseo_ajax_nonce' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'دسترسی غیرمجاز', 'gzseo' ),
                )
            );
            return;
        }
        
        // بررسی فیلدهای مورد نیاز
        // Check user permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'شما مجوز لازم برای انجام این عملیات را ندارید', 'gzseo' ),
                )
            );
            return;
        }


        $required_fields = array('email', 'packageid');
        foreach ($required_fields as $field) {
            // Sanitize field name for safe array access
            $field = sanitize_key( $field );
            // Get and sanitize field value appropriately
            $field_value = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
            if ( empty( $field_value ) ) {
                wp_send_json_error(
                    array(
                        'message' => __( 'اطلاعات ناقص است', 'gzseo' ),
                    )
                );
                return;
            }
        }
        
        // آماده‌سازی داده‌ها
        $data = array(
            'email'        => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
            'packageid'    => isset( $_POST['packageid'] ) ? intval( wp_unslash( $_POST['packageid'] ) ) : 0,
            'package_type' => isset( $_POST['package_type'] ) ? sanitize_text_field( wp_unslash( $_POST['package_type'] ) ) : '',
            'url'          => admin_url( 'admin.php?page=gzseo-dashboard' ),
        );
        
        // ارسال درخواست به API
        $response = $this->send_request(self::ENDPOINT_GET_PAYMENT_LINK, $data);

        if ( is_wp_error( $response ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'خطا در ارتباط با سرور', 'gzseo' ),
                )
            );
            return;
        }

        $payment_link = wp_remote_retrieve_body( $response );
        //var_dump($payment_link);
        // بررسی پاسخ
        if ( 'false' === $payment_link || empty( $payment_link ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'خطا در ایجاد لینک پرداخت', 'gzseo' ),
                )
            );
            return;
        }
        
        // بازگشت لینک پرداخت
        wp_send_json_success(
            array(
                'payment_link' => esc_url_raw( (string) $payment_link ),
            )
        );
    }
    
    /**
     * Send HTTP request to API
     * 
     * @param string $endpoint
     * @param array $data
     * @return array|WP_Error
     */
    private function send_request($endpoint, $data) {
        $url = self::API_BASE_URL . $endpoint;
        
        $args = array(
            'method'  => 'POST',
            'timeout' => 30,
            'limit_response_size' => 2 * MB_IN_BYTES,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body'    => $data,
        );

        return wp_remote_post( $url, $args );
    }
    
    /**
     * Format price for display
     * 
     * @param string $price
     * @return string
     */
    private function format_price($price) {
        $price = floatval($price);
        return number_format( $price / 10, 0, '', ',' ) . ' ' . __( 'تومان', 'gzseo' );
    }
    
    /**
     * Calculate total tokens from package data
     * 
     * @param array $package
     * @return int
     */
    private function calculate_total_tokens($package) {
        $token_fields = array(
            'gpt_3_turbo_credits_prepaid',
            'gpt_4_turbo_credits_prepaid',
            'gpt_4_credits_prepaid',
            'gpt_4o_credits_prepaid',
            'claude_3_opus_credits_prepaid',
            'claude_3_sonnet_credits_prepaid',
            'claude_3_haiku_credits_prepaid',
            'fine_tune_credits_prepaid',
            'gemini_pro_credits_prepaid',
            'gpt_4o_mini_credits_prepaid',
            'o1_mini_credits_prepaid',
            'o1_preview_credits_prepaid',
			
			'gpt_3_turbo_credits',
			'gpt_4_turbo_credits',
			'gpt_4_credits',
			'gpt_4o_credits',
			'claude_3_opus_credits',
			'claude_3_sonnet_credits',
			'claude_3_haiku_credits',
			'fine_tune_credits',
			'gemini_pro_credits',
			'gpt_4o_mini_credits',
			'o1_mini_credits',
			'o1_preview_credits'
		);
        
        $total = 0;
        foreach ( $token_fields as $field ) {
            if ( isset( $package[ $field ] ) ) {
                $total += intval( $package[ $field ] );
            }
        }
        
        return $total;
    }

    /**
     * Sanitize message received from remote API before returning it to the browser.
     * Keeps messages as plain text (no HTML).
     *
     * @param mixed $message Remote message.
     * @return string
     */
    private function sanitize_remote_message( $message ) {
        return sanitize_text_field( (string) $message );
    }


    /**
     * Handle forget password request
     */
    public function handle_forget_password() {
        // Sanitize and verify nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'gzseo_ajax_nonce' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'خطای امنیتی - لطفا صفحه را رفرش کنید', 'gzseo' ),
                )
            );
            return;
        }

        // بررسی ایمیل
        // Check user permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'شما مجوز لازم برای انجام این عملیات را ندارید', 'gzseo' ),
                )
            );
            return;
        }


        $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        
        if ( empty( $email ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'لطفا ایمیل خود را وارد کنید', 'gzseo' ),
                )
            );
            return;
        }

        if ( ! is_email( $email ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'لطفا یک ایمیل معتبر وارد کنید', 'gzseo' ),
                )
            );
            return;
        }

        // ارسال درخواست به API
        $response = wp_remote_post(
            'https://api.gzseo.in/forgetpassword',
            array(
                'method'  => 'POST',
                'timeout' => 30,
                'limit_response_size' => 2 * MB_IN_BYTES,
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body'    => wp_json_encode(
                    array(
                        'email' => $email,
                    )
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'خطا در ارتباط با سرور', 'gzseo' ),
                )
            );
            return;
        }

        $body        = wp_remote_retrieve_body( $response );
        $data        = json_decode( $body, true );
        $status_code = wp_remote_retrieve_response_code( $response );

        if ( 200 === $status_code && isset( $data['message'] ) ) {
            // ذخیره ایمیل موقت برای استفاده در مرحله بعد
            set_transient( 'gzseo_reset_email_' . get_current_user_id(), $email, 600 ); // 10 دقیقه

            wp_send_json_success(
                array(
                    'message' => $this->sanitize_remote_message( $data['message'] ),
                    'next_step' => true,
                )
            );
        } else {
            $error_message = isset( $data['message'] ) ? $this->sanitize_remote_message( $data['message'] ) : __( 'خطا در ارسال ایمیل', 'gzseo' );
            wp_send_json_error(
                array(
                    'message' => $error_message,
                )
            );
        }
    }

    /**
     * Handle reset password request
     */
    public function handle_reset_password() {
        // Sanitize and verify nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'gzseo_ajax_nonce' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'خطای امنیتی - لطفا صفحه را رفرش کنید', 'gzseo' ),
                )
            );
            return;
        }

        // Check user permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'شما مجوز لازم برای تغییر رمز عبور را ندارید', 'gzseo' ),
                )
            );
            return;
        }

        // دریافت ایمیل ذخیره شده
        $email = get_transient('gzseo_reset_email_' . get_current_user_id());
        
        if ( ! $email ) {
            wp_send_json_error(
                array(
                    'message' => __( 'جلسه منقضی شده، لطفا دوباره درخواست بازیابی رمز دهید', 'gzseo' ),
                )
            );
            return;
        }

        // بررسی فیلدها
        $verify_code = isset( $_POST['verify_code'] ) ? sanitize_text_field( wp_unslash( $_POST['verify_code'] ) ) : '';
        // Password: unslashed and cast to string (not overly sanitized to preserve valid password characters)
        $new_password = isset( $_POST['new_password'] ) ? (string) wp_unslash( $_POST['new_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        
        if ( empty( $verify_code ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'لطفا کد تایید را وارد کنید', 'gzseo' ),
                )
            );
            return;
        }

        if ( empty( $new_password ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'لطفا رمز عبور جدید را وارد کنید', 'gzseo' ),
                )
            );
            return;
        }

        if ( strlen( $new_password ) < 6 ) {
            wp_send_json_error(
                array(
                    'message' => __( 'رمز عبور باید حداقل 6 کاراکتر باشد', 'gzseo' ),
                )
            );
            return;
        }

        // ارسال درخواست به API
        $response = wp_remote_post(
            'https://api.gzseo.in/confirmnewpassword',
            array(
                'method'  => 'POST',
                'timeout' => 30,
                'limit_response_size' => 2 * MB_IN_BYTES,
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body'    => wp_json_encode(
                    array(
                        'email'       => $email,
                        'verify_code' => $verify_code,
                        'new_password'=> $new_password,
                    )
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'خطا در ارتباط با سرور', 'gzseo' ),
                )
            );
            return;
        }

        $body        = wp_remote_retrieve_body( $response );
        $data        = json_decode( $body, true );
        $status_code = wp_remote_retrieve_response_code( $response );

        if ( 200 === $status_code && isset( $data['message'] ) ) {
            // حذف ایمیل موقت
            delete_transient( 'gzseo_reset_email_' . get_current_user_id() );

            // به‌روزرسانی رمز عبور محلی اگر کاربر قبلا لاگین بوده
            $stored_email = get_option( 'gzseo_ai_email', '' );
            if ( $stored_email === $email ) {
            update_option( 'gzseo_ai_password', GZSEO_Security_Helpers::encrypt_credential( $new_password ) );
            }

            wp_send_json_success(
                array(
                    'message' => $this->sanitize_remote_message( $data['message'] ),
                    'password_changed' => true,
                )
            );
        } else {
            $error_message = isset( $data['message'] ) ? $this->sanitize_remote_message( $data['message'] ) : __( 'خطا در تغییر رمز عبور', 'gzseo' );
            wp_send_json_error(
                array(
                    'message' => $error_message,
                )
            );
        }
    }
}

// ایجاد نمونه از کلاس
new GZSEO_AI_API();
