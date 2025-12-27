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

/**
 * Remove all cron events for a given hook (any args).
 *
 * WordPress does not provide a public API to remove all events for a hook regardless of args,
 * so uninstall uses the internal cron array.
 *
 * @param string $hook Cron hook name.
 * @return void
 */
function content_update_scheduler_uninstall_remove_all_cron_events($hook)
{
    if (!function_exists('_get_cron_array') || !function_exists('_set_cron_array')) {
        return;
    }

    $crons = _get_cron_array();
    if (!is_array($crons)) {
        return;
    }

    foreach ($crons as $timestamp => $cronhooks) {
        if (!isset($cronhooks[$hook])) {
            continue;
        }

        unset($crons[$timestamp][$hook]);
        if (empty($crons[$timestamp])) {
            unset($crons[$timestamp]);
        }
    }

    _set_cron_array($crons);
}

// Unschedule cron hooks owned by this plugin.
wp_clear_scheduled_hook('cus_check_overdue_posts');
content_update_scheduler_uninstall_remove_all_cron_events('cus_publish_post');
content_update_scheduler_uninstall_remove_all_cron_events('cus_change_homepage');

// Clear homepage change events based on stored option.
$content_update_scheduler_scheduled_changes = get_option('cus_scheduled_homepage_changes', array());
if (is_array($content_update_scheduler_scheduled_changes)) {
    foreach ($content_update_scheduler_scheduled_changes as $content_update_scheduler_change) {
        if (!is_array($content_update_scheduler_change)) {
            continue;
        }
        $content_update_scheduler_page_id = isset($content_update_scheduler_change['page_id']) ? (int) $content_update_scheduler_change['page_id'] : 0;
        $content_update_scheduler_timestamp = isset($content_update_scheduler_change['timestamp']) ? (int) $content_update_scheduler_change['timestamp'] : 0;
        if ($content_update_scheduler_page_id > 0) {
            wp_clear_scheduled_hook('cus_change_homepage', array($content_update_scheduler_page_id));
            if ($content_update_scheduler_timestamp > 0) {
                wp_clear_scheduled_hook('cus_change_homepage', array($content_update_scheduler_page_id, $content_update_scheduler_timestamp));
            }
        }
    }
}

// Remove plugin options.
delete_option('tsu_options');
delete_option('cus_scheduled_homepage_changes');

// Remove post meta keys used by this plugin.
$content_update_scheduler_meta_keys = array(
    'cus_sc_publish_pubdate',
    'cus_sc_publish_original',
    'cus_sc_publish_keep_dates',
);

foreach ($content_update_scheduler_meta_keys as $content_update_scheduler_meta_key) {
    delete_post_meta_by_key($content_update_scheduler_meta_key);
}

// Delete scheduled update posts created by this plugin.
$content_update_scheduler_scheduled_update_ids = get_posts(
    array(
        'post_type'      => 'any',
        'post_status'    => 'cus_sc_publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    )
);

foreach ($content_update_scheduler_scheduled_update_ids as $content_update_scheduler_post_id) {
    wp_clear_scheduled_hook('cus_publish_post', array((int) $content_update_scheduler_post_id));
    wp_delete_post((int) $content_update_scheduler_post_id, true);
}
