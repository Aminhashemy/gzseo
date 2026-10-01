<?php
/**
 * GZSEO Table Data Migration
 * 
 * @package GZSEO
 * @subpackage Migration
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class GZSEO_Table_Migration {
    
    /**
     * Migration version
     */
    const MIGRATION_VERSION = '2.0.0';
    
    /**
     * Option name for tracking migration status
     */
    const MIGRATION_STATUS_OPTION = 'gzseo_table_migration_status';
    
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
        $cache_key = 'gzseo_table_migration_check_' . self::MIGRATION_VERSION;
        $cache_group = 'gzseo_migrations';
        $old_format_count = wp_cache_get($cache_key, $cache_group);
        
        if (false === $old_format_count) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- migration operation; prepared and cached.
            $old_format_count = $wpdb->get_var(
                "SELECT COUNT(DISTINCT post_id) 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = 'gzseo_table'"
            );
            // Cache for 1 hour - old format data is relatively static
            wp_cache_set($cache_key, intval($old_format_count), $cache_group, 3600);
        }
        
        return ($old_format_count > 0);
    }
    
    /**
     * Run the table migration
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
                'gzseo_table', $last_post_id, $batch_size
            ));

            foreach ($post_ids as $post_id) {
                $post_id = absint($post_id);
                $meta_data = array(
                    'gzseo_table' => get_post_meta($post_id, 'gzseo_table', true),
                    'gzseo_table_data' => get_post_meta($post_id, 'gzseo_table_data', true),
                    'gzseo_table_position' => get_post_meta($post_id, 'gzseo_table_position', true),
                );
                $result = $this->migrate_post_table($post_id, $meta_data);
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
     * Migrate table for a single post
     */
    private function migrate_post_table($post_id, $meta_data) {
        // Get table content
        $table_content = isset($meta_data['gzseo_table']) ? $meta_data['gzseo_table'] : '';
        
        if (empty($table_content)) {
            return false;
        }
        
        // Get existing new format tables
        $existing_tables = get_post_meta($post_id, '_gzseo_tables', true);
        if (!is_array($existing_tables)) {
            $existing_tables = array();
        }
        
        // Check migration tracking
        $migration_tracking = get_post_meta($post_id, '_gzseo_table_migration_tracking', true);
        if (!is_array($migration_tracking)) {
            $migration_tracking = array();
        }
        
        // Generate hash for this table
        $table_hash = md5($table_content);
        
        // Skip if already migrated
        if (isset($migration_tracking[$table_hash])) {
            return true;
        }
        
        // Convert to new format
        $new_table = $this->convert_table_format($table_content, $meta_data, $table_hash);
        
        if ($new_table) {
            // Add to existing tables
            $existing_tables[] = $new_table;
            
            // Save
            update_post_meta($post_id, '_gzseo_tables', $existing_tables);
            
            // Track migration
            $migration_tracking[$table_hash] = array(
                'new_id' => $new_table['id'],
                'migrated_at' => current_time('mysql')
            );
            update_post_meta($post_id, '_gzseo_table_migration_tracking', $migration_tracking);
            
            // Mark as migrated
            update_post_meta($post_id, '_gzseo_table_migrated', true);
            update_post_meta($post_id, '_gzseo_table_migration_date', current_time('mysql'));
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Convert old table format to new format
     */
    private function convert_table_format($table_content, $meta_data, $old_hash) {
        // Generate new ID
        $new_id = 'gzseo_table_migrated_' . uniqid();
        
        // Parse table data
        $table_data = isset($meta_data['gzseo_table_data']) ? maybe_unserialize($meta_data['gzseo_table_data']) : array();
        
        // Determine if bulk
        $is_bulk = false;
        if (isset($table_data['by'])) {
            $is_bulk = ($table_data['by'] === 'ai_bulk');
        }
        
        // Get timestamp
        $created_date = current_time('mysql');
        if (isset($table_data['time'])) {
            $created_date = gmdate('Y-m-d H:i:s', $table_data['time']);
        }
        
        // Get position
        $position = isset($meta_data['gzseo_table_position']) ? $meta_data['gzseo_table_position'] : 'end_content';
        
        // Map old positions to new
        $position_map = array(
            'default' => 'end_content',
            'before_content' => 'before_content',
            'end_content' => 'end_content',
            'after_first_paragraph' => 'after_first_paragraph',
            'after_first_heading' => 'after_first_paragraph'
        );
        
        if (isset($position_map[$position])) {
            $position = $position_map[$position];
        }
        
        // Convert table HTML
        $table_html = $this->convert_table_html($table_content);
        
        // Get table style settings
        $table_styles = get_option('gzseo_table_styles', array());
        $table_style = isset($table_styles['selected_style']) ? $table_styles['selected_style'] : '1';
        
        // Apply style to table
        if (!empty($table_html)) {
            $table_html = str_replace(
                '<table class="gzseo_table"',
                '<table id="' . $new_id . '" class="gzseo-generated-table gzseo-table-style-' . $table_style . ' gzseo-migrated" data-table-id="' . $new_id . '"',
                $table_html
            );
        }
        
        // Create new format
        $new_table = array(
            'id' => $new_id,
            'html' => $table_html,
            'position' => $position,
            'created_at' => $created_date,
            'updated_at' => $created_date,
            'is_bulk' => $is_bulk,
            'old_format_hash' => $old_hash,
            'migrated_from_v1' => true,
            'original_data' => $table_content // Keep original for reference
        );
        
        return $new_table;
    }
    
    /**
     * Convert table HTML from old format to new
     */
    private function convert_table_html($input) {
        // Use existing conversion logic
        $input = str_replace('[STARTROW]', '[startrow]', $input);
        $input = str_replace('[ENDROW]', '[endrow]', $input);
        $input = str_replace('[STARTCOL]', '[startcol]', $input);
        $input = str_replace('[ENDCOL]', '[endcol]', $input);
        
        $rows = explode('[endrow]', $input);
        $table = '<table class="gzseo_table">';
        $table .= '<tbody>';
        
        $row_index = 0;
        foreach ($rows as $row) {
            if (trim($row) === '') continue;
            
            $columns = explode('[endcol]', $row);
            $table .= '<tr>';
            
            $col_index = 0;
            foreach ($columns as $column) {
                $content = trim($column);
                if ($content === '[startcol]' || $content === '') continue;
                
                // Remove markers
                $content = str_replace(['[startrow]', '[startcol]', '<br/>'], '', $content);
                $content = trim($content);
                
                // First column of each row as th
                if ($col_index === 0) {
                    $table .= '<th>' . $content . '</th>';
                } else {
                    $table .= '<td>' . $content . '</td>';
                }
                
                $col_index++;
            }
            
            $table .= '</tr>';
            $row_index++;
        }
        
        $table .= '</tbody>';
        $table .= '</table>';
        
        return $table;
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
        $cache_key_old = 'gzseo_table_migration_old_count';
        $cache_key_migrated = 'gzseo_table_migration_migrated_count';
        $cache_group = 'gzseo_migrations';
        
        $old_format_count = wp_cache_get($cache_key_old, $cache_group);
        if (false === $old_format_count) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- migration operation; prepared and cached.
            $old_format_count = $wpdb->get_var(
                "SELECT COUNT(DISTINCT post_id) 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = 'gzseo_table'"
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
                WHERE meta_key = '_gzseo_table_migrated' AND meta_value = '1'"
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
        
        $migration_logs = get_option('gzseo_table_migration_logs', array());
        $migration_logs[] = $log_entry;
        
        // Keep last 10 logs
        if (count($migration_logs) > 10) {
            $migration_logs = array_slice($migration_logs, -10);
        }
        
        update_option('gzseo_table_migration_logs', $migration_logs);
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
