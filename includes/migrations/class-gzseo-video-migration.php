<?php
/**
 * GZSEO Video Data Migration
 * 
 * @package GZSEO
 * @subpackage Migration
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class GZSEO_Video_Migration {
    
    /**
     * Migration version
     */
    const MIGRATION_VERSION = '2.0.0';
    
    /**
     * Option name for tracking migration status
     */
    const MIGRATION_STATUS_OPTION = 'gzseo_video_migration_status';
    
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
        $cache_key = 'gzseo_video_migration_check_' . self::MIGRATION_VERSION;
        $cache_group = 'gzseo_migrations';
        $old_format_count = wp_cache_get($cache_key, $cache_group);
        
        if (false === $old_format_count) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- migration operation; prepared and cached.
            $old_format_count = $wpdb->get_var(
                "SELECT COUNT(DISTINCT post_id) 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = 'gzseo_ai_videos'"
            );
            // Cache for 1 hour - old format data is relatively static
            wp_cache_set($cache_key, intval($old_format_count), $cache_group, 3600);
        }
        
        return ($old_format_count > 0);
    }
    
    /**
     * Run the video data migration
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
                'gzseo_ai_videos', $last_post_id, $batch_size
            ));

            foreach ($post_ids as $post_id) {
                $post_id = absint($post_id);
                $result = $this->migrate_post_videos($post_id, get_post_meta($post_id, 'gzseo_ai_videos', true));
                $result ? $migrated_count++ : $error_count++;
                $last_post_id = max($last_post_id, $post_id);
            }
        } while (count($post_ids) === $batch_size);
        
        // Log migration results
        $this->log_migration_results($migrated_count, $error_count);
        
        // Mark migration as complete
        $this->mark_migration_complete();
    }
    
    /**
     * Migrate videos for a single post
     */
    private function migrate_post_videos($post_id, $old_meta_value) {
        // Parse old video data
        $old_videos = maybe_unserialize($old_meta_value);
        
        if (!is_array($old_videos) || empty($old_videos)) {
            return false;
        }
        
        // Get existing new format videos (if any)
        $existing_new_videos = get_post_meta($post_id, '_gzseo_video_updates', true);
        if (!is_array($existing_new_videos)) {
            $existing_new_videos = array();
        }
        
        // Check if this post has already been migrated
        $migration_tracking = get_post_meta($post_id, '_gzseo_video_migration_tracking', true);
        if (!is_array($migration_tracking)) {
            $migration_tracking = array();
        }
        
        $videos_to_add = array();
        
        foreach ($old_videos as $old_video) {
            // Generate a unique ID for tracking
            $old_video_hash = md5(serialize($old_video));
            
            // Check if this specific video has already been migrated
            if (isset($migration_tracking[$old_video_hash])) {
                continue; // Skip already migrated videos
            }
            
            // Convert old format to new format
            $new_video = $this->convert_video_format($old_video, $old_video_hash);
            
            if ($new_video) {
                $videos_to_add[] = $new_video;
                
                // Track this migration
                $migration_tracking[$old_video_hash] = array(
                    'new_id' => $new_video['id'],
                    'migrated_at' => current_time('mysql')
                );
            }
        }
        
        // If there are videos to add
        if (!empty($videos_to_add)) {
            // Merge with existing videos
            $all_videos = array_merge($existing_new_videos, $videos_to_add);
            
            // Save updated videos
            update_post_meta($post_id, '_gzseo_video_updates', $all_videos);
            
            // Save migration tracking
            update_post_meta($post_id, '_gzseo_video_migration_tracking', $migration_tracking);
            
            // Add migration meta for tracking
            update_post_meta($post_id, '_gzseo_videos_migrated', true);
            update_post_meta($post_id, '_gzseo_videos_migration_date', current_time('mysql'));
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Convert old video format to new format
     */
    private function convert_video_format($old_video, $old_hash) {
        // Validate required fields
        if (!isset($old_video['url']) || !isset($old_video['platform'])) {
            return false;
        }
        
        // Generate new unique ID
        $new_id = 'gzseo_video_migrated_' . uniqid();
        
        // Determine if it's bulk or single
        $is_bulk = isset($old_video['by']) && $old_video['by'] === 'bulk';
        
        // Convert timestamp to datetime if exists
        $created_date = current_time('mysql');
        if (isset($old_video['time'])) {
            $created_date = gmdate('Y-m-d H:i:s', $old_video['time']);
        }
        
        // Get position (use old position or default)
        $position = isset($old_video['position']) ? $old_video['position'] : 'end_content';
        
        // Create new format video
        $new_video = array(
            'id' => $new_id,
            'url' => $old_video['url'],
            'platform' => $old_video['platform'],
            'embed_code' => $this->generate_embed_code($old_video['url'], $old_video['platform']),
            'video_id' => $this->extract_video_id($old_video['url'], $old_video['platform']),
            'is_active' => false, // Set to inactive by default for safety
            'bulk_generated' => $is_bulk,
            'created_date' => $created_date,
            'position' => $position,
            'old_format_hash' => $old_hash, // Track original video for duplicate prevention
            'migrated_from_v1' => true // Flag to identify migrated videos
        );
        
        // Add any additional old data as metadata
        if (isset($old_video['title'])) {
            $new_video['title'] = $old_video['title'];
        }
        
        return $new_video;
    }
    
    /**
     * Generate embed code based on platform
     */
    private function generate_embed_code($url, $platform) {
        switch (strtolower($platform)) {
            case 'youtube':
                $video_id = $this->extract_youtube_id($url);
                if ($video_id) {
                    return sprintf(
                        '<iframe width="560" height="315" src="https://www.youtube.com/embed/%s" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>',
                        esc_attr($video_id)
                    );
                }
                break;
                
            case 'aparat':
                if (preg_match('/aparat\.com\/v\/([^\/?]+)/', $url, $matches)) {
                    return sprintf(
                        '<iframe src="https://www.aparat.com/video/video/embed/videohash/%s/vt/frame" width="560" height="315" frameborder="0" allowfullscreen="true"></iframe>',
                        esc_attr($matches[1])
                    );
                }
                break;
                
            case 'namasha':
                if (preg_match('/namasha\.com\/v\/([^\/?]+)/', $url, $matches)) {
                    return sprintf(
                        '<iframe src="https://www.namasha.com/embed/%s" width="560" height="315" frameborder="0" allowfullscreen="true"></iframe>',
                        esc_attr($matches[1])
                    );
                }
                break;
                
            case 'tamasha':
                if (preg_match('/tamasha\.com\/v\/([^\/?]+)/', $url, $matches)) {
                    return sprintf(
                        '<iframe src="https://www.tamasha.com/embed/%s" width="560" height="315" frameborder="0" allowfullscreen="true"></iframe>',
                        esc_attr($matches[1])
                    );
                }
                break;
                
            case 'ifilo':
                if (preg_match('/ifilo\.net\/v\/([^\/?]+)/', $url, $matches)) {
                    return sprintf(
                        '<iframe src="https://www.ifilo.net/embed/%s" width="560" height="315" frameborder="0" allowfullscreen="true"></iframe>',
                        esc_attr($matches[1])
                    );
                }
                break;
        }
        
        return '';
    }
    
    /**
     * Extract YouTube video ID
     */
    private function extract_youtube_id($url) {
        $patterns = array(
            '/youtube\.com\/watch\?v=([^\&\?\/]+)/',
            '/youtube\.com\/embed\/([^\&\?\/]+)/',
            '/youtube\.com\/v\/([^\&\?\/]+)/',
            '/youtu\.be\/([^\&\?\/]+)/'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }
        
        return false;
    }
    
    /**
     * Extract video ID based on platform
     */
    private function extract_video_id($url, $platform) {
        switch (strtolower($platform)) {
            case 'youtube':
                return $this->extract_youtube_id($url);
                
            case 'aparat':
                if (preg_match('/aparat\.com\/v\/([^\/?]+)/', $url, $matches)) {
                    return $matches[1];
                }
                break;
                
            case 'namasha':
                if (preg_match('/namasha\.com\/v\/([^\/?]+)/', $url, $matches)) {
                    return $matches[1];
                }
                break;
                
            case 'tamasha':
                if (preg_match('/tamasha\.com\/v\/([^\/?]+)/', $url, $matches)) {
                    return $matches[1];
                }
                break;
                
            case 'ifilo':
                if (preg_match('/ifilo\.net\/v\/([^\/?]+)/', $url, $matches)) {
                    return $matches[1];
                }
                break;
        }
        
        return uniqid(); // Fallback to unique ID
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
        $cache_key_old = 'gzseo_video_migration_old_count';
        $cache_key_migrated = 'gzseo_video_migration_migrated_count';
        $cache_group = 'gzseo_migrations';
        
        $old_format_count = wp_cache_get($cache_key_old, $cache_group);
        if (false === $old_format_count) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- migration operation; prepared and cached.
            $old_format_count = $wpdb->get_var(
                "SELECT COUNT(DISTINCT post_id) 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = 'gzseo_ai_videos'"
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
                WHERE meta_key = '_gzseo_videos_migrated' AND meta_value = '1'"
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
        
        $migration_logs = get_option('gzseo_video_migration_logs', array());
        $migration_logs[] = $log_entry;
        
        // Keep only last 10 logs
        if (count($migration_logs) > 10) {
            $migration_logs = array_slice($migration_logs, -10);
        }
        
        update_option('gzseo_video_migration_logs', $migration_logs);
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
