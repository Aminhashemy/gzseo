<?php
/**
 * GZSEO Table Generator Module - Enhanced Version
 * 
 * @package GZ_SEO
 * @subpackage Modules/Table_Generator
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class GZSEO_Table_Generator {
    
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
        add_action('wp_ajax_gzseo_generate_table', array($this, 'handle_generate_table'));
        add_action('wp_ajax_gzseo_save_table', array($this, 'handle_save_table'));
        add_action('wp_ajax_gzseo_delete_table', array($this, 'handle_delete_table'));
        add_action('wp_ajax_gzseo_update_table', array($this, 'handle_update_table'));
        add_action('wp_ajax_gzseo_get_tables', array($this, 'handle_get_tables'));
        
        // Content filters
        add_filter('the_content', array($this, 'insert_tables_into_content'), 20);
        add_filter('term_description', array($this, 'insert_tables_into_content'), 20);
        
        // Add table styles
        add_action('wp_head', array($this, 'add_frontend_styles'));

        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Add frontend styles
     */
    public function add_frontend_styles() {
        ?>
        <style>
            .gzseo-table-wrapper table .gzseo-table-actions,
            .gzseo-table-wrapper table .gzseo-remove-row,
            .gzseo-table-wrapper table .gzseo-remove-col {
                display: none !important;
            }
            .gzseo-table-wrapper table td[contenteditable="true"],
            .gzseo-table-wrapper table th[contenteditable="true"] {
                contenteditable: false;
            }
        </style>
        <?php
    }

    public function enqueue_assets()
    {
        // Comment Generator AI Module
        wp_enqueue_style(
            'gzseo-table-generator',
            GZSEO_PLUGIN_URL . 'assets/css/table-generator.css',
            array(),
            GZSEO_VERSION
        );

        // Register scripts
        wp_enqueue_script(
            'gzseo-table-generator', 
            GZSEO_PLUGIN_URL . 'assets/js/table-generator.js',
            array('jquery'),
            GZSEO_VERSION,
            true
        );

        // Localize script
        wp_localize_script('gzseo-table-generator', 'gzseo_tg_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gzseo_table_generator_nonce')
        ));
    }
    
    /**
     * Check if AI is logged in
     */
    private function is_ai_logged_in() {
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

    private function current_user_can_edit_object($object_id, $object_type) {
        if ('term' === $object_type || 'taxonomy' === $object_type) {
            return GZSEO_Security_Helpers::current_user_can_edit_term($object_id);
        }

        return GZSEO_Security_Helpers::current_user_can_edit_post($object_id);
    }
    
    

    /**
     * Sanitize table cell content coming from untrusted sources (e.g. AI response).
     *
     * We keep the internal value as HTML (tables rely on it), but restrict it to a safe subset
     * using WordPress KSES. This is NOT "output escaping"; it is input sanitization.
     *
     * @param string $content Raw cell content.
     * @return string Sanitized cell content.
     */
    private function sanitize_table_cell_content( $content ) {
        $content = is_string( $content ) ? trim( $content ) : '';
        if ( $content === '' ) {
            return '';
        }
        // Allow the same HTML subset WordPress allows in posts.
        return wp_kses_post( $content );
    }

    /**
     * Allowed HTML for frontend-rendered tables.
     *
     * @return array
     */
    private function get_allowed_frontend_table_html() {
        $allowed = wp_kses_allowed_html( 'post' );

        // Ensure our wrapper and table attributes are allowed.
        $allowed['div']['class'] = true;

        if ( isset( $allowed['table'] ) && is_array( $allowed['table'] ) ) {
            $allowed['table']['class']        = true;
            $allowed['table']['id']           = true;
            $allowed['table']['data-table-id'] = true;
        }

        return $allowed;
    }
/**
     * Handle generate table request
     */
    public function handle_generate_table() {
        // Sanitize and verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        
        if (!$nonce || !wp_verify_nonce($nonce, 'gzseo_table_generator_nonce')) {
            wp_send_json_error(array('message' => __('خطای امنیتی', 'gzseo')));
            return;
        }

        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('شما مجوز لازم برای ایجاد جدول را ندارید', 'gzseo')));
            return;
        }
        
        // Check if AI is logged in
        if (!$this->is_ai_logged_in()) {
            wp_send_json_error(array('message' => __('لطفا ابتدا وارد حساب هوش مصنوعی شوید', 'gzseo')));
            return;
        }
        
        // Get credentials
        $credentials = $this->get_ai_credentials();
        
        // Get form data
        $post_id = isset($_POST['post_id']) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
        $content = isset($_POST['content']) ? sanitize_textarea_field(wp_unslash($_POST['content'])) : '';
        $object_type = isset($_POST['object_type']) ? sanitize_key( wp_unslash( $_POST['object_type'] ) ) : 'post';

        if ($post_id && !$this->current_user_can_edit_object($post_id, $object_type)) {
            wp_send_json_error(array('message' => __('شما اجازه ویرایش این محتوا را ندارید', 'gzseo')), 403);
        }
        
        if (empty($content)) {
            wp_send_json_error(array('message' => __('محتوا نمی‌تواند خالی باشد', 'gzseo')));
            return;
        }
        
        // Prepare API request
        $api_data = array(
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'content' => $content,
            'ai_model' => 'standard'
        );
        
        // Make API request
        $response = wp_remote_post('https://api.gzseo.in/createtable', array(
            'method' => 'POST',
            'timeout' => 60,
            'limit_response_size' => 2 * MB_IN_BYTES,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($api_data)
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                /* translators: %s: Error message from the server connection. */
                'message' => __('خطا در اتصال به سرویس هوش مصنوعی', 'gzseo')
            ));
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            /* translators: %s: HTTP status code from server response. */
            wp_send_json_error(array('message' => sprintf(__('خطا در دریافت پاسخ از سرور (Status: %s)', 'gzseo'), $status_code)));
            return;
        }
        
        // Parse table from string response
        $table_html = $this->parse_table_from_string($body);
        
        if (empty($table_html)) {
            wp_send_json_error(array('message' => __('خطا در پردازش جدول', 'gzseo')));
            return;
        }
        
        // Get table style
        $table_style = get_option('gzseo_table_style', '1');
        $use_custom_style = get_option('gzseo_use_custom_table_style', true);
        
        // Generate unique table ID
        $table_id = 'gzseo_table_' . uniqid();
        
        // Apply style to table
        if ($use_custom_style) {
            $table_html = $this->apply_table_style($table_html, $table_style, $table_id);
        }
        
        wp_send_json_success(array(
            'message' => __('جدول با موفقیت تولید شد', 'gzseo'),
            'table_html' => $table_html,
            'table_style' => $table_style,
            'table_id' => $table_id
        ));
    }
    
    /**
     * Parse table from string response
     */
    private function parse_table_from_string($response_string) {
        // حذف تگ های br
        $response_string = str_replace('<br>', '', $response_string);
        $response_string = str_replace('<br/>', '', $response_string);
        $response_string = str_replace('<br />', '', $response_string);
        
        // تجزیه سطرها
        $rows = array();
        preg_match_all('/\[startrow\](.*?)\[ENDROW\]/i', $response_string, $row_matches);
        
        if (empty($row_matches[1])) {
            return '';
        }
        
        $table_html = '<table class="gzseo-generated-table">';
        $table_html .= '<tbody>';
        
        foreach ($row_matches[1] as $row_index => $row_content) {
            // تجزیه ستون‌ها
            preg_match_all('/\[startcol\](.*?)\[ENDCOL\]/i', $row_content, $col_matches);
            
            if (!empty($col_matches[1])) {
                $table_html .= '<tr>';
                
                // دکمه حذف سطر در ابتدا
                $table_html .= '<td class="gzseo-table-actions gzseo-row-actions"><button class="gzseo-remove-row" title="' . esc_attr__('حذف سطر', 'gzseo') . '"><i class="fas fa-times"></i></button></td>';
                
                foreach ($col_matches[1] as $col_index => $col_content) {
                    // اولین ستون از هر سطر را به عنوان th در نظر بگیر
                    if ($col_index === 0) {
                        $table_html .= '<th contenteditable="true" data-row="' . esc_attr( absint( $row_index ) ) . '" data-col="' . esc_attr( absint( $col_index ) ) . '">' . $this->sanitize_table_cell_content( $col_content ) . '</th>';
                    } else {
                        $table_html .= '<td contenteditable="true" data-row="' . esc_attr( absint( $row_index ) ) . '" data-col="' . esc_attr( absint( $col_index ) ) . '">' . $this->sanitize_table_cell_content( $col_content ) . '</td>';
                    }
                }
                
                $table_html .= '</tr>';
            }
        }
        
        // سطر دکمه‌های حذف ستون
        $table_html .= '<tr class="gzseo-col-actions-row">';
        $table_html .= '<td class="gzseo-table-actions"></td>'; // سلول خالی برای دکمه حذف سطر
        
        // تعداد ستون‌ها را محاسبه کنید
        if (!empty($col_matches[1])) {
            foreach ($col_matches[1] as $col_index => $col_content) {
                $table_html .= '<td class="gzseo-table-actions gzseo-col-actions"><button class="gzseo-remove-col" data-col="' . esc_attr( absint( $col_index ) ) . '" title="' . esc_attr__('حذف ستون', 'gzseo') . '"><i class="fas fa-times"></i></button></td>';
            }
        }
        $table_html .= '</tr>';
        
        $table_html .= '</tbody>';
        $table_html .= '</table>';
        
        return $table_html;
    }
    
    /**
     * Apply table style
     */
    private function apply_table_style( $table_html, $style, $table_id ) {
        // Add style class and ID to the table (values are not user input but still normalize).
        $style    = absint( $style );
        $table_id = sanitize_key( (string) $table_id );

        $replacement = sprintf(
            'id="%1$s" class="gzseo-generated-table gzseo-table-style-%2$d" data-table-id="%1$s"',
            esc_attr( $table_id ),
            $style
        );

        $table_html = str_replace(
            'class="gzseo-generated-table"',
            $replacement,
            $table_html
        );

        return $table_html;
    }

    
    /**
     * Handle save table
     */
    public function handle_save_table() {
        // Sanitize and verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        
        if (!$nonce || !wp_verify_nonce($nonce, 'gzseo_table_generator_nonce')) {
            wp_send_json_error(array('message' => __('خطای امنیتی', 'gzseo')));
            return;
        }

        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('شما مجوز لازم برای ذخیره جدول را ندارید', 'gzseo')));
            return;
        }
        
        $object_id = isset($_POST['object_id']) ? absint( wp_unslash( $_POST['object_id'] ) ) : 0;
        $object_type = isset($_POST['object_type']) ? sanitize_key( wp_unslash( $_POST['object_type'] ) ) : 'post';
        if (!$this->current_user_can_edit_object($object_id, $object_type)) {
            wp_send_json_error(array('message' => __('شما اجازه ویرایش این محتوا را ندارید', 'gzseo')), 403);
        }
        $table_html = isset($_POST['table_html']) ? wp_kses_post(wp_unslash($_POST['table_html'])) : '';
        $position = isset($_POST['position']) ? sanitize_key( wp_unslash( $_POST['position'] ) ) : 'after_content';
        $table_id = isset($_POST['table_id']) ? sanitize_key( wp_unslash( $_POST['table_id'] ) ) : '';
        
        if (!$object_id || empty($table_html) || empty($table_id)) {
            wp_send_json_error(array('message' => __('اطلاعات ناقص است', 'gzseo')));
            return;
        }
        
        // Get existing tables
        $tables = $this->get_tables($object_id, $object_type);
        
        // Check if table already exists
        $table_exists = false;
        foreach ($tables as $key => $table) {
            if ($table['id'] === $table_id) {
                $tables[$key] = array(
                    'id' => $table_id,
                    'html' => $table_html,
                    'position' => $position,
                    'created_at' => $table['created_at'],
                    'updated_at' => current_time('mysql')
                );
                $table_exists = true;
                break;
            }
        }
        
        // Add new table if not exists
        if (!$table_exists) {
            $tables[] = array(
                'id' => $table_id,
                'html' => $table_html,
                'position' => $position,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            );
        }
        
        // Save tables
        $this->save_tables($object_id, $object_type, $tables);
        
        wp_send_json_success(array(
            'message' => __('جدول با موفقیت ذخیره شد', 'gzseo'),
            'table_id' => $table_id
        ));
    }
    
    /**
     * Handle update table
     */
    public function handle_update_table() {
        // Sanitize and verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        
        if (!$nonce || !wp_verify_nonce($nonce, 'gzseo_table_generator_nonce')) {
            wp_send_json_error(array('message' => __('خطای امنیتی', 'gzseo')));
            return;
        }

        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('شما مجوز لازم برای ویرایش جدول را ندارید', 'gzseo')));
            return;
        }
        
        $object_id = isset($_POST['object_id']) ? absint( wp_unslash( $_POST['object_id'] ) ) : 0;
        $object_type = isset($_POST['object_type']) ? sanitize_key( wp_unslash( $_POST['object_type'] ) ) : 'post';
        if (!$this->current_user_can_edit_object($object_id, $object_type)) {
            wp_send_json_error(array('message' => __('شما اجازه ویرایش این محتوا را ندارید', 'gzseo')), 403);
        }
        $table_id = isset($_POST['table_id']) ? sanitize_key( wp_unslash( $_POST['table_id'] ) ) : '';
        $table_html = isset($_POST['table_html']) ? wp_kses_post(wp_unslash($_POST['table_html'])) : '';
        $position = isset($_POST['position']) ? sanitize_key( wp_unslash( $_POST['position'] ) ) : '';
        
        if (!$object_id || empty($table_id)) {
            wp_send_json_error(array('message' => __('اطلاعات ناقص است', 'gzseo')));
            return;
        }
        
        // Get existing tables
        $tables = $this->get_tables($object_id, $object_type);
        
        // Update table
        $updated = false;
        foreach ($tables as $key => $table) {
            if ($table['id'] === $table_id) {
                if (!empty($table_html)) {
                    $tables[$key]['html'] = $table_html;
                }
                if (!empty($position)) {
                    $tables[$key]['position'] = $position;
                }
                $tables[$key]['updated_at'] = current_time('mysql');
                $updated = true;
                break;
            }
        }
        
        if (!$updated) {
            wp_send_json_error(array('message' => __('جدول یافت نشد', 'gzseo')));
            return;
        }
        
        // Save tables
        $this->save_tables($object_id, $object_type, $tables);
        
        wp_send_json_success(array('message' => __('جدول با موفقیت بروزرسانی شد', 'gzseo')));
    }
    
    /**
     * Handle delete table
     */
    public function handle_delete_table() {
        // Sanitize and verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        
        if (!$nonce || !wp_verify_nonce($nonce, 'gzseo_table_generator_nonce')) {
            wp_send_json_error(array('message' => __('خطای امنیتی', 'gzseo')));
            return;
        }

        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('شما مجوز لازم برای حذف جدول را ندارید', 'gzseo')));
            return;
        }
        
        $object_id = isset($_POST['object_id']) ? absint( wp_unslash( $_POST['object_id'] ) ) : 0;
        $object_type = isset($_POST['object_type']) ? sanitize_key( wp_unslash( $_POST['object_type'] ) ) : 'post';
        if (!$this->current_user_can_edit_object($object_id, $object_type)) {
            wp_send_json_error(array('message' => __('شما اجازه ویرایش این محتوا را ندارید', 'gzseo')), 403);
        }
        $table_id = isset($_POST['table_id']) ? sanitize_key( wp_unslash( $_POST['table_id'] ) ) : '';
        
        if (!$object_id || empty($table_id)) {
            wp_send_json_error(array('message' => __('اطلاعات ناقص است', 'gzseo')));
            return;
        }
        
        // Get existing tables
        $tables = $this->get_tables($object_id, $object_type);
        
        // Remove table
        $new_tables = array();
        foreach ($tables as $table) {
            if ($table['id'] !== $table_id) {
                $new_tables[] = $table;
            }
        }
        
        // Save tables
        $this->save_tables($object_id, $object_type, $new_tables);
        
        wp_send_json_success(array('message' => __('جدول با موفقیت حذف شد', 'gzseo')));
    }
    
    /**
     * Handle get tables
     */
    public function handle_get_tables() {
        // Verify nonce and capability BEFORE any other processing.
        if ( ! GZSEO_Security_Helpers::verify_ajax_request( 'gzseo_table_generator_nonce', 'edit_posts' ) ) {
            return;
        }

        $object_id = isset($_POST['object_id']) ? absint( wp_unslash( $_POST['object_id'] ) ) : 0;
        $object_type = isset($_POST['object_type']) ? sanitize_key( wp_unslash( $_POST['object_type'] ) ) : 'post';
        if (!$this->current_user_can_edit_object($object_id, $object_type)) {
            wp_send_json_error(array('message' => __('شما اجازه مشاهده این محتوا را ندارید', 'gzseo')), 403);
        }
        
        if (!$object_id) {
            wp_send_json_error(array('message' => __('شناسه نامعتبر است', 'gzseo')));
            return;
        }
        
        $tables = $this->get_tables($object_id, $object_type);
        
        wp_send_json_success(array('tables' => $tables));
    }
    
    /**
     * Get tables for object
     */
    private function get_tables($object_id, $object_type = 'post') {
        if ($object_type === 'term') {
            $tables = get_term_meta($object_id, '_gzseo_tables', true);
        } else {
            $tables = get_post_meta($object_id, '_gzseo_tables', true);
        }
        
        return is_array($tables) ? $tables : array();
    }
    
    /**
     * Save tables for object
     */
    private function save_tables($object_id, $object_type, $tables) {
        if ($object_type === 'term') {
            update_term_meta($object_id, '_gzseo_tables', $tables);
        } else {
            update_post_meta($object_id, '_gzseo_tables', $tables);
        }
    }
    
    /**
     * Clean table HTML for frontend display
     */
    private function clean_table_for_frontend( $table_html ) {
        // Remove action buttons/rows.
        $table_html = preg_replace( '/<td[^>]*class="[^"]*gzseo-table-actions[^"]*"[^>]*>.*?<\/td>/is', '', (string) $table_html );
        $table_html = preg_replace( '/<tr[^>]*class="[^"]*gzseo-col-actions-row[^"]*"[^>]*>.*?<\/tr>/is', '', $table_html );

        // Remove contenteditable attributes.
        $table_html = str_replace( 'contenteditable="true"', '', $table_html );

        // Final safety pass for frontend: allow only safe HTML.
        return wp_kses( $table_html, $this->get_allowed_frontend_table_html() );
    }
    
    /**
     * Insert tables into content
     */
    public function insert_tables_into_content($content) {
        global $post;
        
        // Determine object type and ID
        $object_id = 0;
        $object_type = 'post';
        
        if (is_singular() && isset($post->ID)) {
            $object_id = $post->ID;
            $object_type = 'post';
        } elseif (is_category() || is_tag() || is_tax()) {
            $object_id = get_queried_object_id();
            $object_type = 'term';
        }
        
        if (!$object_id) {
            return $content;
        }
        
        // Get tables
        $tables = $this->get_tables($object_id, $object_type);
        
        if (empty($tables)) {
            return $content;
        }
        
        // Process each table
        foreach ($tables as $table) {
            // Clean table HTML for frontend
            $clean_table_html = $this->clean_table_for_frontend($table['html']);
            $table_html = '<div class="gzseo-table-wrapper">' . $clean_table_html . '</div>';
            
            switch ($table['position']) {
                case 'before_content':
                    $content = $table_html . $content;
                    break;
                    
                case 'after_content':
                    $content = $content . $table_html;
                    break;
                    
                case 'after_first_paragraph':
                    $paragraphs = explode('</p>', $content);
                    if (count($paragraphs) > 1) {
                        $paragraphs[0] .= '</p>' . $table_html;
                        $content = implode('</p>', $paragraphs);
                    } else {
                        $content = $content . $table_html;
                    }
                    break;
                    
                case 'after_first_heading':
                    $pattern = '/(<h[1-6][^>]*>.*?<\/h[1-6]>)/i';
                    if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                        $pos = $matches[0][1] + strlen($matches[0][0]);
                        $content = substr($content, 0, $pos) . $table_html . substr($content, $pos);
                    } else {
                        $content = $content . $table_html;
                    }
                    break;
            }
        }
        
        return $content;
    }
    
    /**
     * Render content for metabox
     */
    public function render_content($post) {
        $is_logged_in = $this->is_ai_logged_in();
        $tables = $this->get_tables($post->ID, 'post');
        $object_type = $post->post_type === 'page' ? 'page' : 'post';
        ?>
        <div class="gzseo-table-generator-wrapper">
            <?php if (!$is_logged_in): ?>
                <div class="gzseo-ai-login-notice">
                    <i class="fas fa-exclamation-circle"></i>
                    <p><?php esc_html_e('برای استفاده از تولید جدول با هوش مصنوعی، ابتدا باید وارد حساب کاربری خود شوید.', 'gzseo'); ?></p>
                    <a href="<?php echo esc_url( admin_url('admin.php?page=gzseo-dashboard#ai') ); ?>" class="button button-primary">
                        <?php esc_html_e('ورود به حساب هوش مصنوعی', 'gzseo'); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="gzseo-tg-form">
                    <input type="hidden" id="gzseo-tg-object-id" value="<?php echo esc_attr( $post->ID ); ?>">
                    <input type="hidden" id="gzseo-tg-object-type" value="<?php echo esc_attr( $object_type ); ?>">
                    <input type="hidden" id="gzseo-tg-content" value="<?php 
                        $content = get_post_field('post_content', $post->ID);
                        echo esc_attr(wp_strip_all_tags($content));
                    ?>">
                    
                    <!-- Tables List -->
                    <div id="gzseo-tables-list" class="gzseo-tables-list">
                        <?php if (!empty($tables)): ?>
                            <?php foreach ($tables as $table): ?>
                                <div class="gzseo-table-item" data-table-id="<?php echo esc_attr($table['id']); ?>">
                                    <div class="gzseo-table-header">
                                        <div class="gzseo-table-info">
                                            <i class="fas fa-table"></i>
                                            <span><?php echo esc_html__('جدول - ', 'gzseo') . esc_html( date_i18n('Y/m/d H:i', strtotime($table['created_at'])) ); ?></span>
                                        </div>
                                        <div class="gzseo-table-actions">
                                            <button class="gzseo-btn-icon gzseo-edit-table" title="<?php esc_html_e('ویرایش', 'gzseo'); ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="gzseo-btn-icon gzseo-delete-table" title="<?php esc_html_e('حذف', 'gzseo'); ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="gzseo-table-content" style="display: none;">
                                        <div class="gzseo-table-editor">
                                            <div class="gzseo-table-toolbar">
                                                <button class="gzseo-btn-sm gzseo-add-row"><i class="fas fa-plus"></i> <?php esc_html_e('افزودن سطر', 'gzseo'); ?></button>
                                                <button class="gzseo-btn-sm gzseo-add-column"><i class="fas fa-plus"></i> <?php esc_html_e('افزودن ستون', 'gzseo'); ?></button>
                                            </div>
                                            <div class="gzseo-table-preview">
                                                <?php 
                                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already sanitized with wp_kses_post() on save
                                                echo wp_kses_post( $table['html'] ); 
                                                ?>
                                            </div>
                                        </div>
                                        
                                        <div class="gzseo-form-group">
                                            <label><?php esc_html_e('موقعیت نمایش:', 'gzseo'); ?></label>
                                            <select class="gzseo-table-position widefat" data-table-id="<?php echo esc_attr($table['id']); ?>">
                                                <option value="before_content" <?php selected($table['position'], 'before_content'); ?>><?php esc_html_e('ابتدای محتوا', 'gzseo'); ?></option>
                                                <option value="after_content" <?php selected($table['position'], 'after_content'); ?>><?php esc_html_e('انتهای محتوا', 'gzseo'); ?></option>
                                                <option value="after_first_paragraph" <?php selected($table['position'], 'after_first_paragraph'); ?>><?php esc_html_e('بعد از اولین پاراگراف', 'gzseo'); ?></option>
                                                <option value="after_first_heading" <?php selected($table['position'], 'after_first_heading'); ?>><?php esc_html_e('بعد از اولین تیتر', 'gzseo'); ?></option>
                                            </select>
                                        </div>
                                        
                                        <div class="gzseo-table-save-actions">
                                            <button class="button button-primary gzseo-save-table-changes"><?php esc_html_e('ذخیره تغییرات', 'gzseo'); ?></button>
                                            <button class="button gzseo-cancel-table-edit"><?php esc_html_e('انصراف', 'gzseo'); ?></button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Add New Table -->
                    <div class="gzseo-add-table-section">
                        <button type="button" id="gzseo-tg-generate" class="button button-primary">
                            <i class="fas fa-plus"></i> <?php esc_html_e('افزودن جدول جدید', 'gzseo'); ?>
                        </button>
                    </div>
                    
                    <!-- Preview Section -->
                    <div id="gzseo-tg-preview" class="gzseo-tg-preview" style="display: none;">
                        <h4><?php esc_html_e('پیش‌نمایش جدول جدید:', 'gzseo'); ?></h4>
                        <div class="gzseo-table-editor">
                            <div class="gzseo-table-toolbar">
                                <button class="gzseo-btn-sm gzseo-add-row-preview"><i class="fas fa-plus"></i> <?php esc_html_e('افزودن سطر', 'gzseo'); ?></button>
                                <button class="gzseo-btn-sm gzseo-add-column-preview"><i class="fas fa-plus"></i> <?php esc_html_e('افزودن ستون', 'gzseo'); ?></button>
                            </div>
                            <div id="gzseo-tg-preview-content"></div>
                        </div>
                        
                        <div class="gzseo-form-group">
                            <label for="gzseo-tg-position"><?php esc_html_e('موقعیت نمایش:', 'gzseo'); ?></label>
                            <select id="gzseo-tg-position" class="widefat">
                                <option value="before_content"><?php esc_html_e('ابتدای محتوا', 'gzseo'); ?></option>
                                <option value="after_content" selected><?php esc_html_e('انتهای محتوا', 'gzseo'); ?></option>
                                <option value="after_first_paragraph"><?php esc_html_e('بعد از اولین پاراگراف', 'gzseo'); ?></option>
                                <option value="after_first_heading"><?php esc_html_e('بعد از اولین تیتر', 'gzseo'); ?></option>
                            </select>
                        </div>
                        
                        <div class="gzseo-tg-preview-actions">
                            <button type="button" id="gzseo-tg-save-preview" class="button button-primary">
                                <i class="fas fa-save"></i> <?php esc_html_e('ذخیره جدول', 'gzseo'); ?>
                            </button>
                            <button type="button" id="gzseo-tg-cancel-preview" class="button">
                                <i class="fas fa-times"></i> <?php esc_html_e('انصراف', 'gzseo'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Messages -->
                    <div id="gzseo-tg-messages" class="gzseo-tg-messages"></div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render content for term metabox
     */
    public function render_term_content($term) {
        $is_logged_in = $this->is_ai_logged_in();
        $tables = $this->get_tables($term->term_id, 'term');
        ?>
        <div class="gzseo-table-generator-wrapper">
            <!-- Similar content as render_content but for terms -->
            <!-- کد مشابه render_content اما برای دسته‌بندی‌ها -->
        </div>
        <?php
    }
    
    /**************************** تولید جدول برای تکسونومی ها ***************************/
    /**
     * Render content for taxonomy term metabox
     */
    public function render_taxonomy_content($term) {
        $is_logged_in = $this->is_ai_logged_in();
        $tables = $this->get_tables($term->term_id, 'term');
        ?>
        <div class="gzseo-table-generator-wrapper">
            <?php if (!$is_logged_in): ?>
                <div class="gzseo-ai-login-notice">
                    <i class="fas fa-exclamation-circle"></i>
                    <p><?php esc_html_e('برای استفاده از تولید جدول با هوش مصنوعی، ابتدا باید وارد حساب کاربری خود شوید.', 'gzseo'); ?></p>
                    <a href="<?php echo esc_url( admin_url('admin.php?page=gzseo-dashboard#ai') ); ?>" class="button button-primary">
                        <?php esc_html_e('ورود به حساب هوش مصنوعی', 'gzseo'); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="gzseo-tg-form">
                    <input type="hidden" id="gzseo-tg-term-id" value="<?php echo esc_attr( $term->term_id ); ?>">
                    <input type="hidden" id="gzseo-tg-taxonomy" value="<?php echo esc_attr( $term->taxonomy ); ?>">
                    <input type="hidden" id="gzseo-tg-object-type" value="term">
                    <input type="hidden" id="gzseo-tg-content" value="<?php 
                        $content = $term->description;
                        echo esc_attr(wp_strip_all_tags($content));
                    ?>">

                    <!-- Tables List -->
                    <div id="gzseo-taxonomy-tables-list" class="gzseo-tables-list">
                        <?php if (!empty($tables)): ?>
                            <?php foreach ($tables as $table): ?>
                                <div class="gzseo-table-item" data-table-id="<?php echo esc_attr($table['id']); ?>">
                                    <div class="gzseo-table-header">
                                        <div class="gzseo-table-info">
                                            <i class="fas fa-table"></i>
                                            <span><?php echo esc_html__('جدول - ', 'gzseo') . esc_html( date_i18n('Y/m/d H:i', strtotime($table['created_at'])) ); ?></span>
                                        </div>
                                        <div class="gzseo-table-actions">
                                            <button class="gzseo-btn-icon gzseo-edit-taxonomy-table" title="<?php esc_html_e('ویرایش', 'gzseo'); ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="gzseo-btn-icon gzseo-delete-taxonomy-table" title="<?php esc_html_e('حذف', 'gzseo'); ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="gzseo-table-content" style="display: none;">
                                        <div class="gzseo-table-editor">
                                            <div class="gzseo-table-toolbar">
                                                <button class="gzseo-btn-sm gzseo-add-row"><i class="fas fa-plus"></i> <?php esc_html_e('افزودن سطر', 'gzseo'); ?></button>
                                                <button class="gzseo-btn-sm gzseo-add-column"><i class="fas fa-plus"></i> <?php esc_html_e('افزودن ستون', 'gzseo'); ?></button>
                                            </div>
                                            <div class="gzseo-table-preview">
                                                <?php 
                                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already sanitized with wp_kses_post() on save
                                                echo wp_kses_post( $table['html'] ); 
                                                ?>
                                            </div>
                                        </div>

                                        <div class="gzseo-form-group">
                                            <label><?php esc_html_e('موقعیت نمایش:', 'gzseo'); ?></label>
                                            <select class="gzseo-table-position widefat" data-table-id="<?php echo esc_attr($table['id']); ?>">
                                                <option value="before_content" <?php selected($table['position'], 'before_content'); ?>><?php esc_html_e('ابتدای محتوا', 'gzseo'); ?></option>
                                                <option value="after_content" <?php selected($table['position'], 'after_content'); ?>><?php esc_html_e('انتهای محتوا', 'gzseo'); ?></option>
                                                <option value="after_first_paragraph" <?php selected($table['position'], 'after_first_paragraph'); ?>><?php esc_html_e('بعد از اولین پاراگراف', 'gzseo'); ?></option>
                                                <option value="after_first_heading" <?php selected($table['position'], 'after_first_heading'); ?>><?php esc_html_e('بعد از اولین تیتر', 'gzseo'); ?></option>
                                            </select>
                                        </div>

                                        <div class="gzseo-table-save-actions">
                                            <button class="button button-primary gzseo-save-taxonomy-table-changes"><?php esc_html_e('ذخیره تغییرات', 'gzseo'); ?></button>
                                            <button class="button gzseo-cancel-taxonomy-table-edit"><?php esc_html_e('انصراف', 'gzseo'); ?></button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Add New Table -->
                    <div class="gzseo-add-table-section">
                        <button type="button" id="gzseo-tg-generate-taxonomy" class="button button-primary">
                            <i class="fas fa-plus"></i> <?php esc_html_e('افزودن جدول جدید', 'gzseo'); ?>
                        </button>
                    </div>

                    <!-- Preview Section -->
                    <div id="gzseo-tg-taxonomy-preview" class="gzseo-tg-preview" style="display: none;">
                        <h4><?php esc_html_e('پیش‌نمایش جدول جدید:', 'gzseo'); ?></h4>
                        <div class="gzseo-table-editor">
                            <div class="gzseo-table-toolbar">
                                <button class="gzseo-btn-sm gzseo-add-row-preview"><i class="fas fa-plus"></i> <?php esc_html_e('افزودن سطر', 'gzseo'); ?></button>
                                <button class="gzseo-btn-sm gzseo-add-column-preview"><i class="fas fa-plus"></i> <?php esc_html_e('افزودن ستون', 'gzseo'); ?></button>
                            </div>
                            <div id="gzseo-tg-taxonomy-preview-content"></div>
                        </div>

                        <div class="gzseo-form-group">
                            <label for="gzseo-tg-taxonomy-position"><?php esc_html_e('موقعیت نمایش:', 'gzseo'); ?></label>
                            <select id="gzseo-tg-taxonomy-position" class="widefat">
                                <option value="before_content"><?php esc_html_e('ابتدای محتوا', 'gzseo'); ?></option>
                                <option value="after_content" selected><?php esc_html_e('انتهای محتوا', 'gzseo'); ?></option>
                                <option value="after_first_paragraph"><?php esc_html_e('بعد از اولین پاراگراف', 'gzseo'); ?></option>
                                <option value="after_first_heading"><?php esc_html_e('بعد از اولین تیتر', 'gzseo'); ?></option>
                            </select>
                        </div>

                        <div class="gzseo-tg-preview-actions">
                            <button type="button" id="gzseo-tg-save-taxonomy-preview" class="button button-primary">
                                <i class="fas fa-save"></i> <?php esc_html_e('ذخیره جدول', 'gzseo'); ?>
                            </button>
                            <button type="button" id="gzseo-tg-cancel-taxonomy-preview" class="button">
                                <i class="fas fa-times"></i> <?php esc_html_e('انصراف', 'gzseo'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div id="gzseo-tg-taxonomy-messages" class="gzseo-tg-messages"></div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

// Initialize the class
new GZSEO_Table_Generator();
