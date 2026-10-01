<?php
/**
 * GZSEO Content Data Migration
 * 
 * @package GZSEO
 * @subpackage Migration
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class GZSEO_Content_Migration {
    
    /**
     * Migration version
     */
    const MIGRATION_VERSION = '2.0.0';
    
    /**
     * Option name for tracking migration status
     */
    const MIGRATION_STATUS_OPTION = 'gzseo_content_migration_status';
    
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
        $cache_key = 'gzseo_content_migration_check_' . self::MIGRATION_VERSION;
        $cache_group = 'gzseo_migrations';
        $old_format_count = wp_cache_get($cache_key, $cache_group);
        
        if (false === $old_format_count) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- migration operation; prepared and cached.
            $old_format_count = $wpdb->get_var(
                "SELECT COUNT(DISTINCT post_id) 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = 'gzseo_ai_cu_lists'"
            );
            // Cache for 1 hour - old format data is relatively static
            wp_cache_set($cache_key, intval($old_format_count), $cache_group, 3600);
        }
        
        return ($old_format_count > 0);
    }
    
    /**
     * Run the content migration
     */
    private function run_migration() {
        global $wpdb;
        $migrated_count = 0;
        $error_count = 0;

        $last_post_id = 0;
        $batch_size = 200;
        do {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded one-time migration query.
            $post_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id > %d ORDER BY post_id ASC LIMIT %d",
                'gzseo_ai_cu_lists', $last_post_id, $batch_size
            ));

            foreach ($post_ids as $post_id) {
                $post_id = absint($post_id);
                $result = $this->migrate_post_content($post_id, get_post_meta($post_id, 'gzseo_ai_cu_lists', true));
                $result ? $migrated_count++ : $error_count++;
                $last_post_id = max($last_post_id, $post_id);
            }
        } while (count($post_ids) === $batch_size);
        
        // Log results
        $this->log_migration_results($migrated_count, $error_count);
        
        // Mark complete
        $this->mark_migration_complete();
    }
    
    /**
     * Migrate content for a single post
     */
    private function migrate_post_content($post_id, $old_meta_value) {
        // Parse old content data
        $old_contents = maybe_unserialize($old_meta_value);
        
        if (!is_array($old_contents) || empty($old_contents)) {
            return false;
        }
        
        // Get existing new format contents
        $existing_contents = get_post_meta($post_id, '_gzseo_content_updates', true);
        if (!is_array($existing_contents)) {
            $existing_contents = array();
        }
        
        // Check migration tracking
        $migration_tracking = get_post_meta($post_id, '_gzseo_content_migration_tracking', true);
        if (!is_array($migration_tracking)) {
            $migration_tracking = array();
        }
        
        $contents_to_add = array();
        
        foreach ($old_contents as $old_content) {
            // Generate unique hash for tracking
            $old_content_hash = md5(serialize($old_content));
            
            // Skip if already migrated
            if (isset($migration_tracking[$old_content_hash])) {
                continue;
            }
            
            // Convert to new format
            $new_content = $this->convert_content_format($old_content, $old_content_hash);
            
            if ($new_content) {
                $contents_to_add[] = $new_content;
                
                // Track migration
                $migration_tracking[$old_content_hash] = array(
                    'new_id' => $new_content['id'],
                    'migrated_at' => current_time('mysql')
                );
            }
        }
        
        // Add new contents if any
        if (!empty($contents_to_add)) {
            // Merge with existing
            $all_contents = array_merge($existing_contents, $contents_to_add);
            
            // Save
            update_post_meta($post_id, '_gzseo_content_updates', $all_contents);
            
            // Save tracking
            update_post_meta($post_id, '_gzseo_content_migration_tracking', $migration_tracking);
            
            // Mark as migrated
            update_post_meta($post_id, '_gzseo_content_migrated', true);
            update_post_meta($post_id, '_gzseo_content_migration_date', current_time('mysql'));
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Convert old content format to new format
     */
    private function convert_content_format($old_content, $old_hash) {
        // Validate required fields
        if (!isset($old_content['content'])) {
            return false;
        }
        
        // Generate new ID
        $new_id = 'gzseo_content_migrated_' . uniqid();
        
        // Determine if bulk or single
        $is_bulk = isset($old_content['by']) && $old_content['by'] === 'bulk';
        
        // Convert timestamp to datetime
        $created_date = current_time('mysql');
        if (isset($old_content['time'])) {
            $created_date = gmdate('Y-m-d H:i:s', $old_content['time']);
        }
        
        // Get position
        $position = 'after_content'; // default
        if (isset($old_content['position'])) {
            // Map old positions to new positions
            $position_map = array(
                'default' => 'after_content',
                'before' => 'before_content',
                'after' => 'after_content',
                'first_paragraph' => 'after_first_paragraph',
                'second_paragraph' => 'after_second_paragraph'
            );
            
            $position = isset($position_map[$old_content['position']]) 
                ? $position_map[$old_content['position']] 
                : $old_content['position'];
        }
        
        // Get accordion settings
        $accordion_display = 0;
        $accordion_title = 'مشاهده بیشتر';
        
        if (isset($old_content['view_type']) && $old_content['view_type'] === 'true') {
            $accordion_display = 1;
        }
        
        // Create new format
        $new_content = array(
            'id' => $new_id,
            'content' => $old_content['content'],
            'position' => $position,
            'is_active' => 1, // Default active
            'is_bulk' => $is_bulk ? 1 : 0,
            'accordion_display' => $accordion_display,
            'accordion_title' => $accordion_title,
            'created_at' => $created_date,
            'updated_at' => $created_date,
            'old_format_hash' => $old_hash,
            'migrated_from_v1' => true
        );
        
        return $new_content;
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
        
        // Count posts with old format
        $cache_key_old = 'gzseo_content_migration_old_count';
        $cache_key_migrated = 'gzseo_content_migration_migrated_count';
        $cache_group = 'gzseo_migrations';
        
        $old_format_count = wp_cache_get($cache_key_old, $cache_group);
        if (false === $old_format_count) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- migration operation; prepared and cached.
            $old_format_count = $wpdb->get_var(
                "SELECT COUNT(DISTINCT post_id) 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = 'gzseo_ai_cu_lists'"
            );
            wp_cache_set($cache_key_old, intval($old_format_count), $cache_group, 300);
        }
        $stats['total_old_format'] = intval($old_format_count);
        
        // Count migrated posts
        $migrated_count = wp_cache_get($cache_key_migrated, $cache_group);
        if (false === $migrated_count) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- migration operation; prepared and cached.
            $migrated_count = $wpdb->get_var(
                "SELECT COUNT(DISTINCT post_id) 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_gzseo_content_migrated' AND meta_value = '1'"
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
            'migrated_posts' => $migrated_count,
            'errors' => $error_count,
            'total_processed' => $migrated_count + $error_count
        );
        
        $migration_logs = get_option('gzseo_content_migration_logs', array());
        $migration_logs[] = $log_entry;
        
        // Keep last 10 logs
        if (count($migration_logs) > 10) {
            $migration_logs = array_slice($migration_logs, -10);
        }
        
        update_option('gzseo_content_migration_logs', $migration_logs);
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
}
