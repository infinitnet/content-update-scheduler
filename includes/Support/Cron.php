<?php
/**
 * Cron helpers.
 *
 * @package cus
 */

namespace Infinitnet\ContentUpdateScheduler\Support;

defined('ABSPATH') || exit;

final class Cron
{
    /**
     * Ensure the recurring overdue-check event exists.
     *
     * @return void
     */
    public static function ensure_overdue_checker()
    {
        if (!wp_next_scheduled('cus_check_overdue_posts')) {
            wp_schedule_event(time(), 'five_minutes', 'cus_check_overdue_posts');
        }
    }

    /**
     * Clear all scheduled publish events for scheduled-update posts.
     *
     * @return void
     */
    public static function clear_all_scheduled_update_events()
    {
        $ids = get_posts(array(
            'post_type'      => 'any',
            'post_status'    => 'cus_sc_publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ));

        foreach ($ids as $post_id) {
            wp_clear_scheduled_hook('cus_publish_post', array((int) $post_id));
        }
    }

    /**
     * Restore scheduled publish events from stored post meta.
     *
     * @return void
     */
    public static function restore_scheduled_update_events()
    {
        $ids = get_posts(array(
            'post_type'      => 'any',
            'post_status'    => 'cus_sc_publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ));

        $now = time();
        foreach ($ids as $post_id) {
            $post_id = (int) $post_id;
            $stamp = (int) get_post_meta($post_id, 'cus_sc_publish_pubdate', true);
            if ($stamp <= $now) {
                continue;
            }
            if (wp_next_scheduled('cus_publish_post', array($post_id))) {
                continue;
            }
            wp_schedule_single_event($stamp, 'cus_publish_post', array($post_id));
        }
    }

    /**
     * Clear all scheduled homepage change events based on stored option.
     *
     * @return void
     */
    public static function clear_all_homepage_change_events()
    {
        $scheduled_changes = get_option('cus_scheduled_homepage_changes', array());
        if (!is_array($scheduled_changes)) {
            return;
        }

        foreach ($scheduled_changes as $change) {
            if (!is_array($change)) {
                continue;
            }
            $page_id = isset($change['page_id']) ? (int) $change['page_id'] : 0;
            $timestamp = isset($change['timestamp']) ? (int) $change['timestamp'] : 0;
            if ($page_id > 0) {
                // Back-compat: events may exist with args = array($page_id).
                wp_clear_scheduled_hook('cus_change_homepage', array($page_id));
                // New format: args = array($page_id, $timestamp).
                if ($timestamp > 0) {
                    wp_clear_scheduled_hook('cus_change_homepage', array($page_id, $timestamp));
                }
            }
        }
    }

    /**
     * Restore scheduled homepage change events from stored option.
     *
     * @return void
     */
    public static function restore_homepage_change_events()
    {
        $scheduled_changes = get_option('cus_scheduled_homepage_changes', array());
        if (!is_array($scheduled_changes)) {
            return;
        }

        $now = time();
        foreach ($scheduled_changes as $change) {
            if (!is_array($change)) {
                continue;
            }
            $timestamp = isset($change['timestamp']) ? (int) $change['timestamp'] : 0;
            $page_id = isset($change['page_id']) ? (int) $change['page_id'] : 0;

            if ($timestamp <= 0 || $page_id <= 0) {
                continue;
            }

            $new_args = array($page_id, $timestamp);
            $old_args = array($page_id);

            // If an old-format event exists, unschedule it so we can schedule the new-format args.
            if (wp_next_scheduled('cus_change_homepage', $old_args)) {
                wp_unschedule_event($timestamp, 'cus_change_homepage', $old_args);
            }

            if (wp_next_scheduled('cus_change_homepage', $new_args)) {
                continue;
            }

            // If the scheduled time is already past, fire ASAP (best-effort recovery from missed cron).
            $run_at = ($timestamp <= $now) ? ($now + 60) : $timestamp;

            wp_schedule_single_event($run_at, 'cus_change_homepage', $new_args);
        }
    }
}
