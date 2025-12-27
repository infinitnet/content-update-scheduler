<?php
/**
 * WP_List_Table implementation for scheduled republications.
 *
 * @package cus
 */

namespace Infinitnet\ContentUpdateScheduler\Admin;

defined('ABSPATH') || exit;

if (!class_exists('\WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class ScheduledRepublicationsTable extends \WP_List_Table
{
    /**
     * @var array<int,\WP_Post>
     */
    private $items_posts = array();

    public function __construct()
    {
        parent::__construct(array(
            'singular' => 'scheduled_republication',
            'plural'   => 'scheduled_republications',
            'ajax'     => false,
        ));
    }

    public function get_columns()
    {
        return array(
            'original'  => esc_html__('Original Post', 'content-update-scheduler'),
            'scheduled' => esc_html__('Scheduled Date', 'content-update-scheduler'),
            'status'    => esc_html__('Status', 'content-update-scheduler'),
            'actions'   => esc_html__('Actions', 'content-update-scheduler'),
        );
    }

    protected function get_sortable_columns()
    {
        return array(
            'scheduled' => array('scheduled', true),
        );
    }

    public function no_items()
    {
        esc_html_e('No scheduled republications found.', 'content-update-scheduler');
    }

    public function prepare_items()
    {
        $per_page = 20;
        $paged = isset($_REQUEST['paged']) ? max(1, absint($_REQUEST['paged'])) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $orderby = isset($_REQUEST['orderby']) ? sanitize_key(wp_unslash($_REQUEST['orderby'])) : 'scheduled'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $order = isset($_REQUEST['order']) ? strtoupper(sanitize_key(wp_unslash($_REQUEST['order']))) : 'ASC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $order = in_array($order, array('ASC', 'DESC'), true) ? $order : 'ASC';

        $args = array(
            'post_type'      => 'any',
            'post_status'    => 'cus_sc_publish',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'meta_key'       => 'cus_sc_publish_pubdate',
            'orderby'        => ($orderby === 'scheduled') ? 'meta_value_num' : 'meta_value_num',
            'order'          => $order,
            'no_found_rows'  => false,
        );

        $query = new \WP_Query($args);

        // If the user is on an out-of-range page (e.g. `paged=2` when only 1 page exists),
        // WP_Query will return no posts but `found_posts` will remain non-zero.
        if (empty($query->posts) && $query->found_posts > 0 && $paged > 1 && $query->max_num_pages > 0) {
            $args['paged'] = 1;
            $query = new \WP_Query($args);
        }

        $this->items_posts = is_array($query->posts) ? $query->posts : array();
        $this->items = $this->items_posts;

        $columns = $this->get_columns();
        $hidden = get_hidden_columns($this->screen);
        $sortable = $this->get_sortable_columns();
        $primary = $this->get_primary_column_name();
        $this->_column_headers = array($columns, $hidden, $sortable, $primary);

        $this->set_pagination_args(array(
            'total_items' => (int) $query->found_posts,
            'per_page'    => $per_page,
            'total_pages' => (int) $query->max_num_pages,
        ));
    }

    /**
     * @param \WP_Post $item
     * @return string
     */
    public function column_original($item)
    {
        return $this->render_original_column($item);
    }

    /**
     * @param \WP_Post $item
     * @return string
     */
    public function column_scheduled($item)
    {
        return $this->render_scheduled_column($item);
    }

    /**
     * @param \WP_Post $item
     * @return string
     */
    public function column_status($item)
    {
        return $this->render_status_column($item);
    }

    /**
     * @param \WP_Post $item
     * @return string
     */
    public function column_actions($item)
    {
        return $this->render_actions_column($item);
    }

    /**
     * @param \WP_Post $item
     * @param string  $column_name
     * @return string
     */
    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'original':
                return $this->render_original_column($item);
            case 'scheduled':
                return $this->render_scheduled_column($item);
            case 'status':
                return $this->render_status_column($item);
            case 'actions':
                return $this->render_actions_column($item);
            default:
                return '';
        }
    }

    /**
     * @return string
     */
    protected function get_primary_column_name()
    {
        return 'original';
    }

    /**
     * @param \WP_Post $item
     * @return string
     */
    private function render_original_column($item)
    {
        $scheduled_title = get_the_title($item);
        $original_id = (int) get_post_meta($item->ID, 'cus_sc_publish_original', true);

        if ($original_id) {
            $original_title = get_the_title($original_id);
            $html  = '<strong>' . esc_html($original_title) . '</strong>';
            $html .= '<br><small>' . esc_html($scheduled_title) . ' (' . esc_html__('Update', 'content-update-scheduler') . ')</small>';
            return $html;
        }

        return '<strong>' . esc_html($scheduled_title) . '</strong>';
    }

    /**
     * @param \WP_Post $item
     * @return string
     */
    private function render_scheduled_column($item)
    {
        $stamp = (int) get_post_meta($item->ID, 'cus_sc_publish_pubdate', true);
        if ($stamp <= 0) {
            return esc_html__('Invalid date', 'content-update-scheduler');
        }

        return esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $stamp, wp_timezone()));
    }

    /**
     * @param \WP_Post $item
     * @return string
     */
    private function render_status_column($item)
    {
        $stamp = (int) get_post_meta($item->ID, 'cus_sc_publish_pubdate', true);
        $now = time();

        if ($stamp > 0 && $stamp <= $now) {
            return '<span style="color:#ffb900;font-weight:600;">' . esc_html__('Overdue', 'content-update-scheduler') . '</span>';
        }

        return '<span style="color:#00a32a;">' . esc_html__('Scheduled', 'content-update-scheduler') . '</span>';
    }

    /**
     * @param \WP_Post $item
     * @return string
     */
    private function render_actions_column($item)
    {
        $edit_link = get_edit_post_link($item->ID);
        if (!$edit_link) {
            $edit_link = admin_url('post.php?action=edit&post=' . (int) $item->ID);
        }

        $publish_now_url = wp_nonce_url(
            admin_url('admin.php?action=workflow_publish_now&post=' . (int) $item->ID),
            'workflow_publish_now' . (int) $item->ID,
            'n'
        );

        $html  = '<a class="button button-small" href="' . esc_url($edit_link) . '">' . esc_html__('Edit Update', 'content-update-scheduler') . '</a> ';
        $html .= '<a class="button button-primary button-small" href="' . esc_url($publish_now_url) . '">' . esc_html__('Publish Now', 'content-update-scheduler') . '</a>';
        return $html;
    }
}
