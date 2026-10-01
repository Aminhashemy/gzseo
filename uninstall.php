<?php
/** Remove GZSEO-owned settings and metadata when the plugin is deleted. */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

wp_clear_scheduled_hook('gzseo_check_scheduled_comments');

// Remove all argument-specific single events created for scheduled comments.
$cron = _get_cron_array();
if (is_array($cron)) {
    foreach ($cron as $timestamp => $hooks) {
        if (empty($hooks['gzseo_publish_scheduled_comment'])) {
            continue;
        }
        foreach ($hooks['gzseo_publish_scheduled_comment'] as $event) {
            wp_unschedule_event($timestamp, 'gzseo_publish_scheduled_comment', isset($event['args']) ? $event['args'] : array());
        }
    }
}

$option_like = $wpdb->esc_like('gzseo_') . '%';
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $option_like)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

$post_meta_like = $wpdb->esc_like('_gzseo_') . '%';
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $post_meta_like)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s", $post_meta_like)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->commentmeta} WHERE meta_key LIKE %s", $post_meta_like)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

$table_name = $wpdb->prefix . 'gzseo_content_updates';
$wpdb->query("DROP TABLE IF EXISTS `{$table_name}`"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is derived only from the trusted WordPress prefix.
