<?php
/**
 * Plugin bootstrapper and hook registrar.
 *
 * @package cus
 */

namespace Infinitnet\ContentUpdateScheduler;

use Infinitnet\ContentUpdateScheduler\Support\AdminNotices;
use Infinitnet\ContentUpdateScheduler\Support\Cron;

defined('ABSPATH') || exit;

final class Plugin
{
    /**
     * Register runtime hooks.
     *
     * @return void
     */
    public static function init()
    {
        add_action('admin_notices', array(AdminNotices::class, 'render'));

        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_assets'));

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

        add_filter('template_redirect', array('ContentUpdateScheduler', 'user_restriction_scheduled_content'), 1);

        // Add custom cron interval.
        add_filter('cron_schedules', array(__CLASS__, 'add_cron_schedules'));

        // Hook for checking overdue posts.
        add_action('cus_check_overdue_posts', array('ContentUpdateScheduler', 'check_and_publish_overdue_posts'));

        // Homepage scheduling functionality (admin only).
        add_action('admin_init', array(__CLASS__, 'admin_init'));
    }

    /**
     * @param array $schedules
     * @return array
     */
    public static function add_cron_schedules($schedules)
    {
        $schedules['five_minutes'] = array(
            'interval' => 300,
            'display'  => __('Every Five Minutes', 'content-update-scheduler'),
        );

        return $schedules;
    }

    /**
     * @return void
     */
    public static function admin_init()
    {
        if (!is_admin()) {
            return;
        }

        add_filter('wp_dropdown_pages', array('ContentUpdateScheduler', 'override_static_front_page_and_post_option'), 1, 2);
        \ContentUpdateScheduler::init_homepage_scheduling();
    }

    /**
     * Enqueue small admin-only CSS tweaks on relevant screens.
     *
     * @param string $hook_suffix
     * @return void
     */
    public static function enqueue_admin_assets($hook_suffix)
    {
        // Reading Settings screen (static homepage dropdown).
        if ($hook_suffix === 'options-reading.php') {
            wp_enqueue_style(
                'content-update-scheduler-admin',
                plugins_url('assets/admin.css', CUS_PLUGIN_FILE),
                array(),
                defined('CUS_VERSION') ? CUS_VERSION : null
            );
        }

        // Homepage scheduler page.
        if (strpos((string) $hook_suffix, 'schedule-homepage') !== false) {
            wp_enqueue_style(
                'content-update-scheduler-admin',
                plugins_url('assets/admin.css', CUS_PLUGIN_FILE),
                array(),
                defined('CUS_VERSION') ? CUS_VERSION : null
            );
        }
    }

    /**
     * Activation: schedule cron and restore single events.
     *
     * @return void
     */
    public static function activate()
    {
        if (class_exists('ContentUpdateScheduler')) {
            \ContentUpdateScheduler::register_post_status();
        }

        Cron::ensure_overdue_checker();
        Cron::restore_scheduled_update_events();
        Cron::restore_homepage_change_events();
    }

    /**
     * Deactivation: stop cron without deleting data.
     *
     * @return void
     */
    public static function deactivate()
    {
        wp_clear_scheduled_hook('cus_check_overdue_posts');

        if (class_exists('ContentUpdateScheduler')) {
            \ContentUpdateScheduler::register_post_status();
        }

        Cron::clear_all_scheduled_update_events();
        Cron::clear_all_homepage_change_events();
    }
}
