<?php
/**
 * GZSEO Comment Data Migration
 * 
 * @package GZSEO
 * @subpackage Migration
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class GZSEO_Comment_Migration {
    
    /**
     * Migration version
     */
    const MIGRATION_VERSION = '2.0.0';
    
    /**
     * Option name for tracking migration status
     */
    const MIGRATION_STATUS_OPTION = 'gzseo_comment_migration_status';
    
    /**
     * Constructor
     */
    public function __construct() {
        // No auto init
    }
    
    /**
     * Force run migration
     */
    public function force_migration() {
        // Clear migration status for this version
        $migration_status = get_option(self::MIGRATION_STATUS_OPTION, array());
        unset($migration_status[self::MIGRATION_VERSION]);
        update_option(self::MIGRATION_STATUS_OPTION, $migration_status);
        
        // Run migration
        $this->run_migration();
    }

    public static function check_migration_needed() {
        // اگر قبلاً migrate شده، نیازی نیست
        $migration_status = get_option(self::MIGRATION_STATUS_OPTION, array());
        if (isset($migration_status[self::MIGRATION_VERSION]) && 
            $migration_status[self::MIGRATION_VERSION] === 'completed') {
            return false;
        }
        
        // بررسی وجود داده‌های قدیمی
        global $wpdb;
        
        // Cache migration check - safe to cache as old data doesn't change
        $cache_key = 'gzseo_comment_migration_check_' . self::MIGRATION_VERSION;
        $cache_group = 'gzseo_migrations';
        $old_format_count = wp_cache_get($cache_key, $cache_group);
        
        if (false === $old_format_count) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- migration operation; prepared and cached.
            $old_format_count = $wpdb->get_var(
                "SELECT COUNT(DISTINCT comment_id) 
                FROM {$wpdb->commentmeta} 
                WHERE meta_key = 'gzseo_generated_by'"
            );
            // Cache for 1 hour - old format data is relatively static
            wp_cache_set($cache_key, intval($old_format_count), $cache_group, 3600);
        }
        
        return ($old_format_count > 0);
    }
    
    /**
     * Run the comment migration
     */
    private function run_migration() {
        global $wpdb;
        
        $migrated_count = 0;
        $error_count = 0;

        $last_comment_id = 0;
        $batch_size = 200;
        do {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded one-time migration query.
            $comment_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT comment_id FROM {$wpdb->commentmeta} WHERE meta_key = %s AND comment_id > %d ORDER BY comment_id ASC LIMIT %d",
                'gzseo_generated_by', $last_comment_id, $batch_size
            ));

            foreach ($comment_ids as $comment_id) {
                $comment_id = absint($comment_id);
                $comment = get_comment($comment_id);
                if (!$comment) {
                    $error_count++;
                    $last_comment_id = max($last_comment_id, $comment_id);
                    continue;
                }
                $meta = array();
                foreach (array('gzseo_generated_by', 'gzseo_scheduled_publish', 'gzseo_is_scheduled', 'gzseo_published_in_past', 'gzseo_unapproved') as $meta_key) {
                    $meta[$meta_key] = get_comment_meta($comment_id, $meta_key, true);
                }
                $result = $this->migrate_comment(array(
                    'comment_id' => $comment_id,
                    'post_id' => $comment->comment_post_ID,
                    'comment_date' => $comment->comment_date,
                    'meta' => $meta,
                ));
                $result ? $migrated_count++ : $error_count++;
                $last_comment_id = max($last_comment_id, $comment_id);
            }
        } while (count($comment_ids) === $batch_size);
        
        // Log results
        $this->log_migration_results($migrated_count, $error_count);
        
        // Mark complete
        $this->mark_migration_complete();
    }
    
    /**
     * Migrate a single comment
     */
    private function migrate_comment($comment_data) {
        $comment_id = $comment_data['comment_id'];
        $post_id = $comment_data['post_id'];
        $meta = $comment_data['meta'];
        
        // Check if already migrated
        $is_migrated = get_comment_meta($comment_id, '_gzseo_migrated_v2', true);
        if ($is_migrated) {
            return true; // Already migrated
        }
        
        // Determine if bulk or single
        $generated_by = isset($meta['gzseo_generated_by']) ? $meta['gzseo_generated_by'] : 'ai';
        $is_bulk = ($generated_by === 'bulk');
        
        // Add new format meta
        update_comment_meta($comment_id, '_gzseo_generated', true);
        
        if ($is_bulk) {
            update_comment_meta($comment_id, '_gzseo_bulk_generated', true);
        }
        
        // Get comment author info
        $comment = get_comment($comment_id);
        if (!$comment) {
            return false;
        }
        
        // Determine gender from name (if possible)
        $gender = $this->guess_gender_from_name($comment->comment_author);
        update_comment_meta($comment_id, '_gzseo_gender', $gender);
        
        // Handle scheduled comments
        if (isset($meta['gzseo_scheduled_publish'])) {
            update_comment_meta($comment_id, '_gzseo_scheduled_publish', $meta['gzseo_scheduled_publish']);
            update_comment_meta($comment_id, '_gzseo_is_scheduled', '1');
        }
        
        // Handle past published comments
        if (isset($meta['gzseo_published_in_past']) && $meta['gzseo_published_in_past'] === '1') {
            update_comment_meta($comment_id, '_gzseo_published_in_past', '1');
        }
        
        // Handle unapproved comments
        if (isset($meta['gzseo_unapproved']) && $meta['gzseo_unapproved'] === '1') {
            update_comment_meta($comment_id, '_gzseo_unapproved', '1');
        }
        
        // Get tone and word count from settings if bulk
        if ($is_bulk) {
            $tone = get_option('gzseo_comment_tone', 'تجربه مثبت');
            $word_count = get_option('gzseo_comment_word_count', 50);
            
            update_comment_meta($comment_id, '_gzseo_bulk_settings', array(
                'tone' => $tone,
                'word_count' => $word_count,
                'generated_at' => $comment_data['comment_date']
            ));
        }
        
        // Mark as migrated with version info
        update_comment_meta($comment_id, '_gzseo_migrated_v2', true);
        update_comment_meta($comment_id, '_gzseo_migration_date', current_time('mysql'));
        update_comment_meta($comment_id, '_gzseo_migration_version', self::MIGRATION_VERSION);
        
        // Store original comment ID for tracking
        update_comment_meta($comment_id, '_gzseo_original_comment_id', $comment_id);
        
        // Update post meta to track bulk comments
        if ($is_bulk) {
            $this->update_post_bulk_comment_meta($post_id);
        }
        
        return true;
    }
    
    /**
     * Update post meta for bulk comments tracking
     */
    private function update_post_bulk_comment_meta($post_id) {
        $has_bulk = get_post_meta($post_id, '_gzseo_bulk_comments_generated', true);
        if (!$has_bulk) {
            update_post_meta($post_id, '_gzseo_bulk_comments_generated', true);
        }
        
        // Update count
        global $wpdb;
        
        // No caching - this is a write operation during migration, needs fresh count
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration operation.
        $bulk_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT c.comment_ID)
            FROM {$wpdb->comments} c
            INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id
            WHERE c.comment_post_ID = %d 
            AND cm.meta_key = '_gzseo_bulk_generated' 
            AND cm.meta_value = '1'",
            $post_id
        ));
        
        update_post_meta($post_id, '_gzseo_bulk_comments_count', $bulk_count);
        
        // Invalidate comment cache after migration update
        wp_cache_delete('gzseo_bulk_comments_posts', 'gzseo');
        
        // Update date
        $existing_date = get_post_meta($post_id, '_gzseo_bulk_comments_date', true);
        if (!$existing_date) {
            update_post_meta($post_id, '_gzseo_bulk_comments_date', current_time('mysql'));
        }
    }
    
    /**
     * Guess gender from Persian name
     */
    private function guess_gender_from_name($name) {
        // Common Persian male name endings/patterns
        $male_patterns = array(
            'محمد', 'علی', 'حسین', 'حسن', 'رضا', 'احمد', 'مهدی',
            'امیر', 'سعید', 'مجید', 'حمید', 'جواد', 'محسن', 'مصطفی'
        );
        
        // Common Persian female name endings/patterns
        $female_patterns = array(
            'زهرا', 'فاطمه', 'مریم', 'زینب', 'سارا', 'نرگس', 'لیلا',
            'مینا', 'سمیه', 'راضیه', 'معصومه', 'نسرین', 'پریسا', 'شیرین'
        );
        
        // Check patterns
        foreach ($female_patterns as $pattern) {
            if (strpos($name, $pattern) !== false) {
                return 'female';
            }
        }
        
        foreach ($male_patterns as $pattern) {
            if (strpos($name, $pattern) !== false) {
                return 'male';
            }
        }
        
        // Default to male if uncertain
        return 'male';
    }
    
    /**
     * Get migration statistics
     */
    public function get_migration_stats() {
        global $wpdb;
        
        $stats = array(
            'total_old_format' => 0,
            'total_migrated' => 0,
            'total_pending' => 0
        );
        
        // Count comments with old format meta
        $cache_key_old = 'gzseo_comment_migration_old_count';
        $cache_key_migrated = 'gzseo_comment_migration_migrated_count';
        $cache_group = 'gzseo_migrations';
        
        $old_format_count = wp_cache_get($cache_key_old, $cache_group);
        if (false === $old_format_count) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- migration operation; prepared and cached.
            $old_format_count = $wpdb->get_var(
                "SELECT COUNT(DISTINCT comment_id) 
                FROM {$wpdb->commentmeta} 
                WHERE meta_key = 'gzseo_generated_by'"
            );
            wp_cache_set($cache_key_old, intval($old_format_count), $cache_group, 300);
        }
        $stats['total_old_format'] = intval($old_format_count);
        
        // Count migrated comments
        $migrated_count = wp_cache_get($cache_key_migrated, $cache_group);
        if (false === $migrated_count) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- migration operation; prepared and cached.
            $migrated_count = $wpdb->get_var(
                "SELECT COUNT(DISTINCT comment_id) 
                FROM {$wpdb->commentmeta} 
                WHERE meta_key = '_gzseo_migrated_v2' AND meta_value = '1'"
            );
            wp_cache_set($cache_key_migrated, intval($migrated_count), $cache_group, 300);
        }
        $stats['total_migrated'] = intval($migrated_count);
        
        // Calculate pending
        $stats['total_pending'] = max(0, $stats['total_old_format'] - $stats['total_migrated']);
        
        return $stats;
    }
    
    /**
     * Log migration results
     */
    private function log_migration_results($migrated_count, $error_count) {
        $log_entry = array(
            'version' => self::MIGRATION_VERSION,
            'date' => current_time('mysql'),
            'migrated_comments' => $migrated_count,
            'errors' => $error_count,
            'total_processed' => $migrated_count + $error_count
        );
        
        $migration_logs = get_option('gzseo_comment_migration_logs', array());
        $migration_logs[] = $log_entry;
        
        // Keep last 10 logs
        if (count($migration_logs) > 10) {
            $migration_logs = array_slice($migration_logs, -10);
        }
        
        update_option('gzseo_comment_migration_logs', $migration_logs);
    }
    
    /**
     * Mark migration as complete
     */
    private function mark_migration_complete() {
        $migration_status = get_option(self::MIGRATION_STATUS_OPTION, array());
        $migration_status[self::MIGRATION_VERSION] = 'completed';
        $migration_status['last_run'] = current_time('mysql');
        
        update_option(self::MIGRATION_STATUS_OPTION, $migration_status);
    }
    
    /**
     * Get posts with migrated bulk comments
     */
    public function get_posts_with_migrated_bulk_comments() {
        global $wpdb;
        
        // Try to get from cache first
        $cache_key = 'gzseo_migrated_bulk_comments_posts';
        $cache_group = 'gzseo_migrations';
        $results = wp_cache_get($cache_key, $cache_group);
        
        if (false === $results) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- migration operation; prepared and cached.
            $results = $wpdb->get_results(
                "SELECT p.ID, p.post_title, 
                        COUNT(c.comment_ID) as comment_count,
                        MAX(c.comment_date) as last_comment_date
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->comments} c ON p.ID = c.comment_post_ID
                INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id
                WHERE cm.meta_key = '_gzseo_bulk_generated' 
                AND cm.meta_value = '1'
                AND c.comment_approved = '1'
                GROUP BY p.ID
                ORDER BY last_comment_date DESC"
            );
            
            // Cache for 5 minutes
            wp_cache_set($cache_key, $results, $cache_group, 300);
        }
        
        return $results;
    }
}
