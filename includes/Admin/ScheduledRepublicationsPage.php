<?php
/**
 * Admin page for scheduled republications.
 *
 * @package cus
 */

namespace Infinitnet\ContentUpdateScheduler\Admin;

defined('ABSPATH') || exit;

final class ScheduledRepublicationsPage
{
    /**
     * @return void
     */
    public static function render()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $table = new ScheduledRepublicationsTable();
        $table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
        echo '<p>' . esc_html__('This page shows all currently scheduled republications.', 'content-update-scheduler') . '</p>';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="' . esc_attr(isset($_REQUEST['page']) ? sanitize_key(wp_unslash($_REQUEST['page'])) : '') . '">'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $table->display();
        echo '</form>';

        echo '</div>';
    }
}

