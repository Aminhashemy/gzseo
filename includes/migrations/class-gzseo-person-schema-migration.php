<?php
/**
 * GZSEO Person Schema Data Migration
 * 
 * @package GZSEO
 * @subpackage Migration
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class GZSEO_Person_Schema_Migration {
    
    /**
     * Migration version
     */
    const MIGRATION_VERSION = '2.0.0';
    
    /**
     * Option name for tracking migration status
     */
    const MIGRATION_STATUS_OPTION = 'gzseo_person_schema_migration_status';
    
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
        $old_schema = get_option('gzseo_schema_person', false);
        
        return (!empty($old_schema) && is_array($old_schema) && !empty($old_schema['name']));
    }
    
    /**
     * Run the person schema migration
     */
    private function run_migration() {
        // Check if already migrated
        $migration_status = get_option(self::MIGRATION_STATUS_OPTION, array());
        if (isset($migration_status[self::MIGRATION_VERSION]) && $migration_status[self::MIGRATION_VERSION] === 'completed') {
            return;
        }
        
        // Get old person schema data
        $old_schema = get_option('gzseo_schema_person', false);
        
        if (empty($old_schema) || !is_array($old_schema)) {
            $this->mark_migration_complete();
            return;
        }
        
        // Check if new format already exists
        $new_schema = get_option('gzseo_person_schema', false);
        if (!empty($new_schema) && !empty($new_schema['name'])) {
            // New schema already exists, check if it's from migration
            if (!isset($new_schema['migrated_from_v1']) || $new_schema['migrated_from_v1'] !== true) {
                // It's a manually created new schema, don't overwrite
                $this->log_migration_skip('New schema already exists');
                $this->mark_migration_complete();
                return;
            }
        }
        
        // Migrate the data
        $result = $this->migrate_person_schema($old_schema);
        
        if ($result) {
            $this->log_migration_success();
        } else {
            $this->log_migration_error();
        }
        
        // Mark migration as complete
        $this->mark_migration_complete();
    }
    
    /**
     * Migrate person schema data
     */
    private function migrate_person_schema($old_schema) {
        // Create hash of old data for tracking
        $old_data_hash = md5(serialize($old_schema));
        
        // Map old format to new format
        $new_schema = array(
            // Pages where schema should be displayed
            'pages' => $this->migrate_page_settings($old_schema),
            
            // Basic information
            'name' => isset($old_schema['name']) ? sanitize_text_field($old_schema['name']) : '',
            'job_title' => isset($old_schema['job_title']) ? sanitize_text_field($old_schema['job_title']) : '',
            'telephone' => isset($old_schema['telephone']) ? sanitize_text_field($old_schema['telephone']) : '',
            'email' => isset($old_schema['email']) ? sanitize_email($old_schema['email']) : '',
            'url' => isset($old_schema['url']) ? esc_url_raw($old_schema['url']) : '',
            'address' => isset($old_schema['address']) ? sanitize_text_field($old_schema['address']) : '',
            'birthdate' => isset($old_schema['birth_date']) ? $this->migrate_birthdate($old_schema['birth_date']) : '',
            
            // Image
            'image_id' => isset($old_schema['image_id']) ? absint($old_schema['image_id']) : 0,
            'image_url' => '',
            
            // Description
            'description' => isset($old_schema['description']) ? sanitize_textarea_field($old_schema['description']) : '',
            
            // Social links
            'social_links' => $this->migrate_social_links($old_schema),
            
            // Migration metadata
            'migrated_from_v1' => true,
            'migration_date' => current_time('mysql'),
            'migration_version' => self::MIGRATION_VERSION,
            'old_data_hash' => $old_data_hash
        );
        
        // Get image URL if image ID exists
        if ($new_schema['image_id'] > 0) {
            $image_url = wp_get_attachment_url($new_schema['image_id']);
            if ($image_url) {
                $new_schema['image_url'] = $image_url;
            }
        }
        
        // Remove empty values
        $new_schema = array_filter($new_schema, function($value) {
            return $value !== '' && $value !== null && $value !== array();
        });
        
        // Validate required fields
        if (empty($new_schema['name'])) {
            return false;
        }
        
        // Save migrated data
        $saved = update_option('gzseo_person_schema', $new_schema);
        
        // Save migration tracking
        if ($saved) {
            $this->save_migration_tracking($old_data_hash, $new_schema);
        }
        
        return $saved;
    }
    
    /**
     * Migrate page settings
     */
    private function migrate_page_settings($old_schema) {
        $pages = array();
        
        if (!isset($old_schema['showing_page_ids']) || empty($old_schema['showing_page_ids'])) {
            return $pages;
        }
        
        $old_pages = $old_schema['showing_page_ids'];
        if (!is_array($old_pages)) {
            $old_pages = array($old_pages);
        }
        
        foreach ($old_pages as $page_id) {
            // Check if page still exists
            $post = get_post($page_id);
            if ($post) {
                $pages[] = 'page_' . $page_id;
            }
        }
        
        return $pages;
    }
    
    /**
     * Migrate birthdate format
     */
    private function migrate_birthdate($old_birthdate) {
        if (empty($old_birthdate)) {
            return '';
        }
        
        // Check if it's Persian date format (e.g., 1375/05/15)
        if (preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $old_birthdate)) {
            // Keep as is for now, conversion will be handled by display function
            return $old_birthdate;
        }
        
        // Try to parse as standard date
        $timestamp = strtotime($old_birthdate);
        if ($timestamp !== false) {
            return gmdate('Y-m-d', $timestamp);
        }
        
        return $old_birthdate;
    }
    
    /**
     * Migrate social links
     */
    private function migrate_social_links($old_schema) {
        $social_links = array();
        
        if (!isset($old_schema['same_as']) || empty($old_schema['same_as'])) {
            return $social_links;
        }
        
        $old_links = $old_schema['same_as'];
        if (!is_array($old_links)) {
            $old_links = array($old_links);
        }
        
        foreach ($old_links as $link) {
            $clean_link = esc_url_raw($link);
            if (!empty($clean_link)) {
                $social_links[] = $clean_link;
            }
        }
        
        return array_unique($social_links);
    }
    
    /**
     * Save migration tracking data
     */
    private function save_migration_tracking($old_hash, $new_data) {
        $tracking = get_option('gzseo_person_schema_migration_tracking', array());
        
        $tracking[$old_hash] = array(
            'migrated_at' => current_time('mysql'),
            'new_data_summary' => array(
                'name' => $new_data['name'],
                'has_image' => !empty($new_data['image_id']),
                'pages_count' => count($new_data['pages']),
                'social_links_count' => isset($new_data['social_links']) ? count($new_data['social_links']) : 0
            )
        );
        
        update_option('gzseo_person_schema_migration_tracking', $tracking);
    }
    
    /**
     * Get migration statistics
     */
    public function get_migration_stats() {
        $stats = array(
            'total_old_format' => 0,
            'total_migrated' => 0,
            'total_pending' => 0
        );
        
        // Check for old format
        $old_schema = get_option('gzseo_schema_person', false);
        if (!empty($old_schema) && is_array($old_schema) && !empty($old_schema['name'])) {
            $stats['total_old_format'] = 1;
        }
        
        // Check for migrated
        $new_schema = get_option('gzseo_person_schema', false);
        if (!empty($new_schema) && isset($new_schema['migrated_from_v1']) && $new_schema['migrated_from_v1'] === true) {
            $stats['total_migrated'] = 1;
        }
        
        // Calculate pending
        $stats['total_pending'] = $stats['total_old_format'] - $stats['total_migrated'];
        
        return $stats;
    }
    
    /**
     * Log migration success
     */
    private function log_migration_success() {
        $logs = get_option('gzseo_person_schema_migration_logs', array());
        
        $logs[] = array(
            'type' => 'success',
            'message' => 'Person schema migrated successfully',
            'date' => current_time('mysql'),
            'version' => self::MIGRATION_VERSION
        );
        
        // Keep only last 10 logs
        if (count($logs) > 10) {
            $logs = array_slice($logs, -10);
        }
        
        update_option('gzseo_person_schema_migration_logs', $logs);
    }
    
    /**
     * Log migration error
     */
    private function log_migration_error() {
        $logs = get_option('gzseo_person_schema_migration_logs', array());
        
        $logs[] = array(
            'type' => 'error',
            'message' => 'Failed to migrate person schema',
            'date' => current_time('mysql'),
            'version' => self::MIGRATION_VERSION
        );
        
        // Keep only last 10 logs
        if (count($logs) > 10) {
            $logs = array_slice($logs, -10);
        }
        
        update_option('gzseo_person_schema_migration_logs', $logs);
    }
    
    /**
     * Log migration skip
     */
    private function log_migration_skip($reason) {
        $logs = get_option('gzseo_person_schema_migration_logs', array());
        
        $logs[] = array(
            'type' => 'skip',
            'message' => 'Migration skipped: ' . $reason,
            'date' => current_time('mysql'),
            'version' => self::MIGRATION_VERSION
        );
        
        // Keep only last 10 logs
        if (count($logs) > 10) {
            $logs = array_slice($logs, -10);
        }
        
        update_option('gzseo_person_schema_migration_logs', $logs);
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
     * Get migration logs
     */
    public function get_migration_logs() {
        return get_option('gzseo_person_schema_migration_logs', array());
    }
    
    /**
     * Clear migration data (for testing/debugging)
     */
    public function clear_migration_data() {
        delete_option('gzseo_person_schema_migration_logs');
        delete_option('gzseo_person_schema_migration_tracking');
        
        // Remove migration flags from new schema
        $new_schema = get_option('gzseo_person_schema', false);
        if ($new_schema && isset($new_schema['migrated_from_v1'])) {
            unset($new_schema['migrated_from_v1']);
            unset($new_schema['migration_date']);
            unset($new_schema['migration_version']);
            unset($new_schema['old_data_hash']);
            update_option('gzseo_person_schema', $new_schema);
        }
    }
}   