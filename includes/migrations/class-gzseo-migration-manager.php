<?php
/**
 * GZSEO Migration Manager
 * @package GZSEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class GZSEO_Migration_Manager {
    
    /**
     * Migration modules
     */

    private $migrations = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'init_migrations'));
        add_action('admin_menu', array($this, 'add_migration_page'));
        add_action('wp_ajax_gzseo_run_migration', array($this, 'ajax_run_migration'));
        // متد حذف موارد منتقل شده از نسخه قبلی به نسخه جدید
        add_action('wp_ajax_gzseo_rollback_migration', array($this, 'ajax_rollback_migration'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Initialize all migration modules
     */
    public function init_migrations() {
        // فقط migrations فعال را بارگذاری کنید
        $this->load_migration_modules();
    }

    /**
     * اجرای اتوماتیک migrations
     */
    public static function run_auto_migrations() {
        // تنظیمات برای فعال/غیرفعال کردن auto migration
        $auto_migration_enabled = get_option('gzseo_auto_migration_enabled', false);
        
        if (!$auto_migration_enabled) {
            return;
        }
        
        // لود migrations
        $migrations = array(
            'video' => 'GZSEO_Video_Migration',
            'content' => 'GZSEO_Content_Migration',
            'table' => 'GZSEO_Table_Migration',
            'comment' => 'GZSEO_Comment_Migration',
            'featured_image' => 'GZSEO_Featured_Image_Migration',
            'person_schema' => 'GZSEO_Person_Schema_Migration',
        );
        
        foreach ($migrations as $key => $class_name) {
            $file_path = GZSEO_PLUGIN_PATH . 'includes/migrations/class-gzseo-' . str_replace('_', '-', $key) . '-migration.php';
            
            if (file_exists($file_path)) {
                require_once $file_path;
                
                if (class_exists($class_name)) {
                    // بررسی نیاز به migration
                    if (call_user_func(array($class_name, 'check_migration_needed'))) {
                        // اجرای migration
                        $migration = new $class_name();
                        $migration->force_migration();
                        
                        // لاگ اجرای اتوماتیک
                        self::log_auto_migration($key, $class_name);
                    }
                }
            }
        }
    }

    /**
     * لاگ کردن migrations اتوماتیک
     */
    private static function log_auto_migration($key, $class_name) {
        $auto_migration_logs = get_option('gzseo_auto_migration_logs', array());
        
        $auto_migration_logs[] = array(
            'type' => $key,
            'class' => $class_name,
            'date' => current_time('mysql'),
            'mode' => 'automatic'
        );
        
        // نگهداری 20 لاگ آخر
        if (count($auto_migration_logs) > 20) {
            $auto_migration_logs = array_slice($auto_migration_logs, -20);
        }
        
        update_option('gzseo_auto_migration_logs', $auto_migration_logs);
    }
    
    /**
     * Load migration modules
     */
    private function load_migration_modules() {
        $migration_files = array(
            'video' => 'class-gzseo-video-migration.php',
            'content' => 'class-gzseo-content-migration.php',
            'table' => 'class-gzseo-table-migration.php',
            'comment' => 'class-gzseo-comment-migration.php',
            'featured_image' => 'class-gzseo-featured-image-migration.php',
            'person_schema' => 'class-gzseo-person-schema-migration.php',
        );
        
        foreach ($migration_files as $key => $file) {
            $file_path = GZSEO_PLUGIN_PATH . 'includes/migrations/' . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
                $class_name = 'GZSEO_' . ucfirst($key) . '_Migration';
                if (class_exists($class_name)) {
                    $this->migrations[$key] = new $class_name();
                }
            }
        }
    }
    
    /**
     * Add migration page to admin menu
     */
    public function add_migration_page() {
        add_submenu_page(
            'gzseo-dashboard',
            __('انتقال داده‌ها', 'gzseo'),
            __('انتقال داده‌ها', 'gzseo'),
            'manage_options',
            'gzseo-migration',
            array($this, 'render_migration_page')
        );
    }
    
    /**
     * Render migration page
     */
    public function render_migration_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('انتقال داده‌ها از نسخه قبلی', 'gzseo'); ?></h1>
            
            <div class="gzseo-migration-status">
                <h2><?php esc_html_e('وضعیت انتقال', 'gzseo'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('نوع داده', 'gzseo'); ?></th>
                            <th><?php esc_html_e('وضعیت', 'gzseo'); ?></th>
                            <th><?php esc_html_e('تعداد کل', 'gzseo'); ?></th>
                            <th><?php esc_html_e('تعداد منتقل شده', 'gzseo'); ?></th>
                            <th><?php esc_html_e('عملیات', 'gzseo'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($this->migrations as $key => $migration): 
                            $stats = $migration->get_migration_stats();
                        ?>
                        <tr>
                            <td><?php echo esc_html( $this->get_migration_label($key) ); ?></td>
                            <td>
                                <?php if ($stats['total_pending'] == 0 && $stats['total_old_format'] > 0): ?>
                                    <span style="color: green;"><?php esc_html_e('✓ تکمیل شده', 'gzseo'); ?></span>
                                <?php elseif ($stats['total_migrated'] > 0): ?>
                                    <span style="color: orange;"><?php esc_html_e('در حال انجام', 'gzseo'); ?></span>
                                <?php else: ?>
                                    <span style="color: gray;"><?php esc_html_e('آماده انتقال', 'gzseo'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $stats['total_old_format'] ); ?></td>
                            <td><?php echo esc_html( $stats['total_migrated'] ); ?></td>
                            <td>
                                <button class="button gzseo-migrate-btn" 
                                        data-migration="<?php echo esc_attr($key); ?>"
                                        <?php echo ($stats['total_pending'] == 0) ? 'disabled' : ''; ?>>
                                    <?php esc_html_e('انتقال', 'gzseo'); ?>
                                </button>
                                <?php if ($stats['total_migrated'] > 0): ?>
                                    <button class="button button-link-delete gzseo-rollback-btn" 
                                            data-migration="<?php echo esc_attr($key); ?>"
                                            title="<?php esc_attr_e('حذف داده‌های منتقل شده', 'gzseo'); ?>">
                                    <?php esc_html_e('حذف داده‌های جدید', 'gzseo'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="gzseo-migration-actions" style="margin-top: 20px;">
                    <button id="gzseo-migrate-all" class="button button-primary">
                        <?php esc_html_e('انتقال همه داده‌ها', 'gzseo'); ?>
                    </button>
                </div>
            </div>

            <div class="gzseo-migration-settings" style="margin-top: 30px; padding: 20px; background: #ffffff; border-radius: 5px;">
                <h2><?php esc_html_e('تنظیمات Migration', 'gzseo'); ?></h2>
                
                <form method="post" action="options.php">
                    <?php settings_fields('gzseo_migration_settings'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Migration اتوماتیک', 'gzseo'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="gzseo_auto_migration_enabled" value="1" 
                                        <?php checked(get_option('gzseo_auto_migration_enabled', true), true); ?> />
                                    <?php esc_html_e('فعال کردن انتقال اتوماتیک داده‌ها در زمان بارگذاری افزونه', 'gzseo'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('با فعال بودن این گزینه، داده‌های نسخه قدیم به صورت خودکار به نسخه جدید منتقل می‌شوند.', 'gzseo'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php esc_html_e('حالت Migration', 'gzseo'); ?></th>
                            <td>
                                <select name="gzseo_migration_mode">
                                    <option value="safe" <?php selected(get_option('gzseo_migration_mode', 'safe'), 'safe'); ?>>
                                        <?php esc_html_e('ایمن (بدون حذف داده‌های قدیمی)', 'gzseo'); ?>
                                    </option>
                                    <option value="clean" <?php selected(get_option('gzseo_migration_mode'), 'clean'); ?>>
                                        <?php esc_html_e('پاکسازی (حذف داده‌های قدیمی پس از انتقال موفق)', 'gzseo'); ?>
                                    </option>
                                </select>
                                <p class="description">
                                    <?php esc_html_e('در حالت ایمن، داده‌های قدیمی حفظ می‌شوند. در حالت پاکسازی، پس از انتقال موفق حذف می‌شوند.', 'gzseo'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('ذخیره تنظیمات', 'gzseo')); ?>
                </form>
                
                <!-- نمایش لاگ‌های اتوماتیک -->
                <?php 
                $auto_logs = get_option('gzseo_auto_migration_logs', array());
                if (!empty($auto_logs)): 
                ?>
                <h3><?php esc_html_e('تاریخچه Migration های اتوماتیک', 'gzseo'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('نوع', 'gzseo'); ?></th>
                            <th><?php esc_html_e('تاریخ', 'gzseo'); ?></th>
                            <th><?php esc_html_e('حالت', 'gzseo'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_reverse($auto_logs) as $log): ?>
                        <tr>
                            <td><?php echo esc_html($log['type']); ?></td>
                            <td><?php echo esc_html($log['date']); ?></td>
                            <td><span style="color: green;"><?php esc_html_e('✓ اتوماتیک', 'gzseo'); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.gzseo-migrate-btn').on('click', function() {
                var $btn = $(this);
                var migration = $btn.data('migration');
                runMigration(migration, $btn);
            });
            
            $('#gzseo-migrate-all').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text(<?php echo wp_json_encode(__('در حال انتقال...', 'gzseo')); ?>);
                
                // اجرای همه migrations به صورت متوالی
                var migrations = <?php echo wp_json_encode(array_keys($this->migrations)); ?>;
                runAllMigrations(migrations, 0);
            });
            
            function runMigration(migration, $btn) {
                $btn.prop('disabled', true).text(<?php echo wp_json_encode(__('در حال انتقال...', 'gzseo')); ?>);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'gzseo_run_migration',
                        migration: migration,
                        nonce: '<?php echo esc_attr( wp_create_nonce('gzseo_migration') ); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(<?php echo wp_json_encode(__('انتقال با موفقیت انجام شد', 'gzseo')); ?>);
                            location.reload();
                        } else {
                            alert(<?php echo wp_json_encode(__('خطا: ', 'gzseo')); ?> + response.data.message);
                            $btn.prop('disabled', false).text(<?php echo wp_json_encode(__('انتقال', 'gzseo')); ?>);
                        }
                    }
                });
            }
            
            function runAllMigrations(migrations, index) {
                if (index >= migrations.length) {
                    alert(<?php echo wp_json_encode(__('همه انتقال‌ها با موفقیت انجام شد', 'gzseo')); ?>);
                    location.reload();
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'gzseo_run_migration',
                        migration: migrations[index],
                        nonce: '<?php echo esc_attr( wp_create_nonce('gzseo_migration') ); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            runAllMigrations(migrations, index + 1);
                        } else {
                            alert(<?php echo wp_json_encode(__('خطا در انتقال ', 'gzseo')); ?> + migrations[index]);
                            $('#gzseo-migrate-all').prop('disabled', false).text(<?php echo wp_json_encode(__('انتقال همه داده‌ها', 'gzseo')); ?>);
                        }
                    }
                });
            }

            $('.gzseo-rollback-btn').on('click', function() {
                var $btn = $(this);
                var migration = $btn.data('migration');
                
                if (!confirm(<?php echo wp_json_encode(__('آیا از حذف داده‌های منتقل شده مطمئن هستید؟ این عمل قابل بازگشت نیست.', 'gzseo')); ?>)) {
                    return;
                }
                
                $btn.prop('disabled', true).text(<?php echo wp_json_encode(__('در حال حذف...', 'gzseo')); ?>);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'gzseo_rollback_migration',
                        migration: migration,
                        nonce: '<?php echo esc_attr( wp_create_nonce('gzseo_migration') ); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert(<?php echo wp_json_encode(__('خطا: ', 'gzseo')); ?> + response.data.message);
                            $btn.prop('disabled', false).text(<?php echo wp_json_encode(__('حذف داده‌های جدید', 'gzseo')); ?>);
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Get migration label
     */
    private function get_migration_label($key) {
        $labels = array(
            'video' => __('ویدیوها', 'gzseo'),
            'content' => __('محتواهای متنی', 'gzseo'),
            'table' => __('جداول', 'gzseo'),
            'comment' => __('کامنت‌ها', 'gzseo'),
            'featured_image' => __('تصاویر شاخص', 'gzseo'),
            'person_schema' => __('اسکیمای Person', 'gzseo')
        );
        
        return isset($labels[$key]) ? $labels[$key] : $key;
    }
    
    /**
     * AJAX handler for running migration
     */
    public function ajax_run_migration() {
        // Verify nonce and capability BEFORE any other processing.
        if ( ! GZSEO_Security_Helpers::verify_ajax_request( 'gzseo_migration', 'manage_options' ) ) {
            return;
        }

        // Get and sanitize migration key
        $migration_key = isset($_POST['migration']) ? sanitize_key(wp_unslash($_POST['migration'])) : '';
        
        if (empty($migration_key) || !isset($this->migrations[$migration_key])) {
            wp_send_json_error(array('message' => __('Migration نامعتبر', 'gzseo')));
        }
        
        $this->migrations[$migration_key]->force_migration();
        
        wp_send_json_success(array('message' => __('انتقال انجام شد', 'gzseo')));
    }

    /**
     * AJAX handler for rolling back migration
     */
    public function ajax_rollback_migration() {
        // Verify nonce and capability BEFORE any other processing.
        if ( ! GZSEO_Security_Helpers::verify_ajax_request( 'gzseo_migration', 'manage_options' ) ) {
            return;
        }

        // Get and sanitize migration key
        $migration_key = isset($_POST['migration']) ? sanitize_key(wp_unslash($_POST['migration'])) : '';
        
        if (empty($migration_key) || !isset($this->migrations[$migration_key])) {
            wp_send_json_error(array('message' => __('Migration نامعتبر', 'gzseo')));
        }
        
        // Get migration instance
        $migration = $this->migrations[$migration_key];
        
        // Call rollback method
        $method_name = 'rollback_' . $migration_key . '_migration';
        if (method_exists($this, $method_name)) {
            $result = $this->$method_name();
        } else {
            wp_send_json_error(array('message' => __('متد rollback یافت نشد', 'gzseo')));
        }
        
        wp_send_json_success(array('message' => __('داده‌های منتقل شده با موفقیت حذف شدند', 'gzseo')));
    }

    /**
     * Rollback video migration
     */
    private function rollback_video_migration() {
        global $wpdb;
        
        // Find all migrated posts
        // No caching - rollback operation that modifies data
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration operation.
        $migrated_posts = $wpdb->get_col(
            "SELECT DISTINCT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_gzseo_videos_migrated' AND meta_value = '1'"
        );
        
        foreach ($migrated_posts as $post_id) {
            // Get current videos
            $videos = get_post_meta($post_id, '_gzseo_video_updates', true);
            if (is_array($videos)) {
                // Remove only migrated videos
                $filtered_videos = array_filter($videos, function($video) {
                    return !isset($video['migrated_from_v1']) || $video['migrated_from_v1'] !== true;
                });
                
                if (empty($filtered_videos)) {
                    delete_post_meta($post_id, '_gzseo_video_updates');
                } else {
                    update_post_meta($post_id, '_gzseo_video_updates', $filtered_videos);
                }
            }
            
            // Remove migration tracking
            delete_post_meta($post_id, '_gzseo_video_migration_tracking');
            delete_post_meta($post_id, '_gzseo_videos_migrated');
            delete_post_meta($post_id, '_gzseo_videos_migration_date');
        }
        
        // Clear migration status
        delete_option('gzseo_video_migration_status');
        delete_option('gzseo_video_migration_logs');
    }

    /**
     * Rollback content migration
     */
    private function rollback_content_migration() {
        global $wpdb;
        
        // No caching - rollback operation that modifies data
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration operation.
        $migrated_posts = $wpdb->get_col(
            "SELECT DISTINCT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_gzseo_content_migrated' AND meta_value = '1'"
        );
        
        foreach ($migrated_posts as $post_id) {
            $contents = get_post_meta($post_id, '_gzseo_content_updates', true);
            if (is_array($contents)) {
                $filtered_contents = array_filter($contents, function($content) {
                    return !isset($content['migrated_from_v1']) || $content['migrated_from_v1'] !== true;
                });
                
                if (empty($filtered_contents)) {
                    delete_post_meta($post_id, '_gzseo_content_updates');
                } else {
                    update_post_meta($post_id, '_gzseo_content_updates', $filtered_contents);
                }
            }
            
            delete_post_meta($post_id, '_gzseo_content_migration_tracking');
            delete_post_meta($post_id, '_gzseo_content_migrated');
            delete_post_meta($post_id, '_gzseo_content_migration_date');
        }
        
        delete_option('gzseo_content_migration_status');
        delete_option('gzseo_content_migration_logs');
    }

    /**
     * Rollback table migration
     */
    private function rollback_table_migration() {
        global $wpdb;
        
        // No caching - rollback operation that modifies data
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration operation.
        $migrated_posts = $wpdb->get_col(
            "SELECT DISTINCT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_gzseo_table_migrated' AND meta_value = '1'"
        );
        
        foreach ($migrated_posts as $post_id) {
            $tables = get_post_meta($post_id, '_gzseo_tables', true);
            if (is_array($tables)) {
                $filtered_tables = array_filter($tables, function($table) {
                    return !isset($table['migrated_from_v1']) || $table['migrated_from_v1'] !== true;
                });
                
                if (empty($filtered_tables)) {
                    delete_post_meta($post_id, '_gzseo_tables');
                } else {
                    update_post_meta($post_id, '_gzseo_tables', $filtered_tables);
                }
            }
            
            delete_post_meta($post_id, '_gzseo_table_migration_tracking');
            delete_post_meta($post_id, '_gzseo_table_migrated');
            delete_post_meta($post_id, '_gzseo_table_migration_date');
        }
        
        delete_option('gzseo_table_migration_status');
        delete_option('gzseo_table_migration_logs');
    }

    /**
     * Rollback comment migration
     */
    private function rollback_comment_migration() {
        global $wpdb;
        
        // Remove migration meta from comments
        // Write operation - no caching, invalidate related caches
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration operation.
        $wpdb->query(
            "DELETE FROM {$wpdb->commentmeta} 
            WHERE meta_key IN ('_gzseo_migrated_v2', '_gzseo_migration_date', '_gzseo_migration_version', '_gzseo_original_comment_id')"
        );
        
        // Invalidate comment caches after deletion
        wp_cache_delete('gzseo_bulk_comments_posts', 'gzseo');
        wp_cache_delete('gzseo_migrated_bulk_comments_posts', 'gzseo_migrations');
        
        // Remove new format meta from migrated comments
        // No caching - rollback operation
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time rollback operation; no caching needed.
        $migrated_comments = $wpdb->get_col(
            "SELECT DISTINCT comment_id 
            FROM {$wpdb->commentmeta} 
            WHERE meta_key = '_gzseo_migrated_v2'"
        );
        
        foreach ($migrated_comments as $comment_id) {
            delete_comment_meta($comment_id, '_gzseo_generated');
            delete_comment_meta($comment_id, '_gzseo_bulk_generated');
            delete_comment_meta($comment_id, '_gzseo_gender');
            delete_comment_meta($comment_id, '_gzseo_bulk_settings');
        }
        
        delete_option('gzseo_comment_migration_status');
        delete_option('gzseo_comment_migration_logs');
    }

    /**
     * Rollback person schema migration
     */
    private function rollback_person_schema_migration() {
        $schema = get_option('gzseo_person_schema', array());
        
        if (!empty($schema) && isset($schema['migrated_from_v1']) && $schema['migrated_from_v1'] === true) {
            delete_option('gzseo_person_schema');
        }
        
        delete_option('gzseo_person_schema_migration_status');
        delete_option('gzseo_person_schema_migration_logs');
        delete_option('gzseo_person_schema_migration_tracking');
    }

    public function register_settings() {
        register_setting('gzseo_migration_settings', 'gzseo_auto_migration_enabled', array(
            'sanitize_callback' => array($this, 'sanitize_auto_migration_enabled')
        ));
        register_setting('gzseo_migration_settings', 'gzseo_migration_mode', array(
            'sanitize_callback' => array($this, 'sanitize_migration_mode')
        ));
    }

    /**
     * Sanitize auto migration enabled setting
     * 
     * @param mixed $value Input value
     * @return bool Sanitized boolean value
     */
    public function sanitize_auto_migration_enabled($value) {
        return (bool) $value;
    }

    /**
     * Sanitize migration mode setting
     * 
     * @param mixed $value Input value
     * @return string Sanitized migration mode
     */
    public function sanitize_migration_mode($value) {
        $allowed_modes = array('safe', 'clean');
        $sanitized = sanitize_text_field($value);
        
        if (in_array($sanitized, $allowed_modes, true)) {
            return $sanitized;
        }
        
        // Return default if invalid
        return 'safe';
    }
}

// Initialize
new GZSEO_Migration_Manager();