<?php
/**
 * Uninstall handler for Content Update Scheduler.
 *
 * Removes plugin-owned data. This file is executed by WordPress when the plugin is uninstalled.
 *
 * @package cus
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

// Register the custom post status so WP_Query can find scheduled update posts.
if (function_exists('register_post_status')) {
    register_post_status('cus_sc_publish', array(
        'public'              => false,
        'internal'            => true,
        'protected'           => true,
        'exclude_from_search' => true,
    ));
}

// Unschedule cron hooks owned by this plugin.
wp_clear_scheduled_hook('cus_check_overdue_posts');

// Clear homepage change events based on stored option.
$scheduled_changes = get_option('cus_scheduled_homepage_changes', array());
if (is_array($scheduled_changes)) {
    foreach ($scheduled_changes as $change) {
        if (!is_array($change)) {
            continue;
        }
        $page_id = isset($change['page_id']) ? (int) $change['page_id'] : 0;
        if ($page_id > 0) {
            wp_clear_scheduled_hook('cus_change_homepage', array($page_id));
        }
    }
}

// Remove plugin options.
delete_option('tsu_options');
delete_option('cus_scheduled_homepage_changes');

// Remove post meta keys used by this plugin.
$meta_keys = array(
    'cus_sc_publish_pubdate',
    'cus_sc_publish_original',
    'cus_sc_publish_keep_dates',
);

foreach ($meta_keys as $meta_key) {
    delete_post_meta_by_key($meta_key);
}

// Delete scheduled update posts created by this plugin.
$scheduled_update_ids = get_posts(
    array(
        'post_type'      => 'any',
        'post_status'    => 'cus_sc_publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    )
);

foreach ($scheduled_update_ids as $post_id) {
    wp_clear_scheduled_hook('cus_publish_post', array((int) $post_id));
    wp_delete_post((int) $post_id, true);
}
