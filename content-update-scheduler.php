<?php
/**
 * Content Update Scheduler
 *
 * Plugin Name: Content Update Scheduler
 * Description: Schedule content updates for any page or post type.
 * Author: Infinitnet
 * Author URI: https://infinitnet.io/
 * Version: 3.1.5
 * License: GPLv3
 * Text Domain: content-update-scheduler
 *
 * @package cus
 */

defined('ABSPATH') || exit;

if (!defined('CUS_VERSION')) {
    define('CUS_VERSION', '3.1.5');
}
if (!defined('CUS_PLUGIN_FILE')) {
    define('CUS_PLUGIN_FILE', __FILE__);
}
if (!defined('CUS_PLUGIN_DIR')) {
    define('CUS_PLUGIN_DIR', __DIR__);
}

require_once CUS_PLUGIN_DIR . '/options.php';
require_once CUS_PLUGIN_DIR . '/includes/class-content-update-scheduler.php';

add_action('save_post', array('ContentUpdateScheduler', 'save_meta'), 10, 2);
add_action('cus_publish_post', array('ContentUpdateScheduler', 'cron_publish_post'), 1);

add_action('wp_ajax_load_pubdate', array('ContentUpdateScheduler', 'load_pubdate'));
add_action('init', array('ContentUpdateScheduler', 'init'), PHP_INT_MAX);
add_action('admin_action_workflow_copy_to_publish', array('ContentUpdateScheduler', 'admin_action_workflow_copy_to_publish'));
add_action('admin_action_workflow_publish_now', array('ContentUpdateScheduler', 'admin_action_workflow_publish_now'));
add_action('transition_post_status', array('ContentUpdateScheduler', 'prevent_status_change'), 10, 3);

add_filter('display_post_states', array('ContentUpdateScheduler', 'display_post_states'));
add_filter('page_row_actions', array('ContentUpdateScheduler', 'page_row_actions'), 10, 2);
add_filter('post_row_actions', array('ContentUpdateScheduler', 'page_row_actions'), 10, 2);
add_filter('manage_pages_columns', array('ContentUpdateScheduler', 'manage_pages_columns'));
add_filter('page_attributes_dropdown_pages_args', array('ContentUpdateScheduler', 'parent_dropdown_status'));

/* Homepage scheduling functionality (admin only) */
add_action('admin_init', function () {
    if (is_admin()) {
        add_filter('wp_dropdown_pages', array('ContentUpdateScheduler', 'override_static_front_page_and_post_option'), 1, 2);
        ContentUpdateScheduler::init_homepage_scheduling();

        // Add CSS for homepage dropdowns via proper hook.
        add_action('admin_head', function () {
            echo '<style type="text/css">select#page_on_front, select#page_for_posts {float: right;margin-left: 10px;}</style>';
        });
    }
});

add_filter('template_redirect', array('ContentUpdateScheduler', 'user_restriction_scheduled_content'), 1);

// Add custom cron interval.
add_filter('cron_schedules', function ($schedules) {
    $schedules['five_minutes'] = array(
        'interval' => 300,
        'display'  => __('Every Five Minutes'),
    );
    return $schedules;
});

// Hook for checking overdue posts.
add_action('cus_check_overdue_posts', array('ContentUpdateScheduler', 'check_and_publish_overdue_posts'));

register_activation_hook(__FILE__, 'cus_activation');
register_deactivation_hook(__FILE__, 'cus_deactivation');

/**
 * Remove all cron events for a given hook (any args).
 *
 * @param string $hook Cron hook name.
 * @return void
 */
function cus_cron_remove_all_events($hook)
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

/**
 * Check whether a single event exists at a given timestamp for hook+args.
 *
 * @param string $hook Cron hook name.
 * @param int    $timestamp UTC timestamp.
 * @param array  $args Args array.
 * @return bool
 */
function cus_cron_single_event_exists($hook, $timestamp, $args)
{
    if (!function_exists('_get_cron_array')) {
        return false;
    }

    $timestamp = (int) $timestamp;
    $crons = _get_cron_array();
    if (!is_array($crons) || !isset($crons[$timestamp][$hook]) || !is_array($crons[$timestamp][$hook])) {
        return false;
    }

    foreach ($crons[$timestamp][$hook] as $event) {
        $event_args = isset($event['args']) ? $event['args'] : array();
        if ($event_args == $args) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
            return true;
        }
    }

    return false;
}

/**
 * Activation: ensure recurring cron is scheduled and restore any pending single events.
 *
 * @return void
 */
function cus_activation()
{
    // Ensure overdue checker is scheduled (custom interval is registered by this plugin).
    if (!wp_next_scheduled('cus_check_overdue_posts')) {
        wp_schedule_event(time(), 'five_minutes', 'cus_check_overdue_posts');
    }

    // Restore scheduled update publish events based on stored post meta.
    $scheduled_update_ids = get_posts(
        array(
            'post_type'      => 'any',
            'post_status'    => 'cus_sc_publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        )
    );

    $now = time();
    foreach ($scheduled_update_ids as $post_id) {
        $post_id = (int) $post_id;
        $stamp = (int) get_post_meta($post_id, 'cus_sc_publish_pubdate', true);
        if ($stamp <= $now) {
            continue;
        }

        $args = array($post_id);
        if (cus_cron_single_event_exists('cus_publish_post', $stamp, $args)) {
            continue;
        }
        wp_schedule_single_event($stamp, 'cus_publish_post', $args);
    }

    // Restore scheduled homepage change events from stored option.
    $scheduled_changes = get_option('cus_scheduled_homepage_changes', array());
    if (is_array($scheduled_changes)) {
        foreach ($scheduled_changes as $change) {
            if (!is_array($change)) {
                continue;
            }

            $timestamp = isset($change['timestamp']) ? (int) $change['timestamp'] : 0;
            $page_id = isset($change['page_id']) ? (int) $change['page_id'] : 0;

            if ($timestamp <= $now || $page_id <= 0) {
                continue;
            }

            $args = array($page_id);
            if (cus_cron_single_event_exists('cus_change_homepage', $timestamp, $args)) {
                continue;
            }
            wp_schedule_single_event($timestamp, 'cus_change_homepage', $args);
        }
    }
}

/**
 * Deactivation: stop plugin execution without deleting data.
 *
 * @return void
 */
function cus_deactivation()
{
    wp_clear_scheduled_hook('cus_check_overdue_posts');

    // Remove plugin-owned single events. Data remains, and activation restores future events.
    cus_cron_remove_all_events('cus_publish_post');
    cus_cron_remove_all_events('cus_change_homepage');
}
