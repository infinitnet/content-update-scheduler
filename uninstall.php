<?php
/**
 * Uninstall handler for Content Update Scheduler.
 *
 * Removes plugin-owned data. This file is executed by WordPress when the plugin is uninstalled.
 *
 * @package cus
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Remove all cron events for a given hook (any args).
 *
 * @param string $hook Cron hook name.
 * @return void
 */
function cus_uninstall_cron_remove_all_events($hook)
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
cus_uninstall_cron_remove_all_events('cus_publish_post');
cus_uninstall_cron_remove_all_events('cus_change_homepage');

// Remove plugin options.
delete_option('tsu_options');
delete_option('cus_scheduled_homepage_changes');

global $wpdb;

// Remove post meta keys used by this plugin.
$meta_keys = array(
    'cus_sc_publish_pubdate',
    'cus_sc_publish_original',
    'cus_sc_publish_keep_dates',
);

foreach ($meta_keys as $meta_key) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->delete($wpdb->postmeta, array('meta_key' => $meta_key));
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
    wp_delete_post((int) $post_id, true);
}

