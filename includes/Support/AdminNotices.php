<?php
/**
 * Admin notice helper.
 *
 * @package cus
 */

namespace Infinitnet\ContentUpdateScheduler\Support;

defined('ABSPATH') || exit;

final class AdminNotices
{
    private const TRANSIENT_PREFIX = 'cus_admin_notice_';

    /**
     * Store a notice for the current user.
     *
     * @param string $type One of: success, error, warning, info.
     * @param string $message Notice message (plain text).
     * @param int    $ttl Seconds to keep the notice.
     * @return void
     */
    public static function add($type, $message, $ttl = 60)
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        $type = in_array($type, array('success', 'error', 'warning', 'info'), true) ? $type : 'info';
        $message = is_string($message) ? $message : '';

        set_transient(self::TRANSIENT_PREFIX . $user_id, array(
            'type'    => $type,
            'message' => $message,
        ), (int) $ttl);
    }

    /**
     * Render and clear the current user's notice.
     *
     * @return void
     */
    public static function render()
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        $notice = get_transient(self::TRANSIENT_PREFIX . $user_id);
        if (!is_array($notice) || empty($notice['message'])) {
            return;
        }

        delete_transient(self::TRANSIENT_PREFIX . $user_id);

        $type = isset($notice['type']) ? $notice['type'] : 'info';
        $type = in_array($type, array('success', 'error', 'warning', 'info'), true) ? $type : 'info';

        printf(
            '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
            esc_attr($type),
            esc_html($notice['message'])
        );
    }
}

