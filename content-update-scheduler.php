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

add_action('admin_enqueue_scripts', function () {
    if (!function_exists('get_current_screen')) {
        return;
    }
    $screen = get_current_screen();
    if (!$screen || $screen->base !== 'post') {
        return;
    }

    wp_enqueue_style(
        'content-update-scheduler-metabox',
        plugins_url('assets/metabox.css', CUS_PLUGIN_FILE),
        array(),
        defined('CUS_VERSION') ? CUS_VERSION : null
    );
});

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

register_deactivation_hook(__FILE__, 'cus_deactivation');

function cus_deactivation()
{
    global $wpdb;
    $wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key = 'cus_sc_publish_pubdate'");
    wp_clear_scheduled_hook('cus_check_overdue_posts');

    // Clear scheduled homepage changes.
    wp_clear_scheduled_hook('cus_change_homepage');
    delete_option('cus_scheduled_homepage_changes');
}
