<?php

/**
 * Content Update Scheduler
 *
 * Plugin Name: Content Update Scheduler
 * Description: Schedule content updates for any page or post type.
 * Author: Infinitnet
 * Author URI: https://infinitnet.io/
 * Version: 2.3
 * License: GPLv3
 * Text Domain: content-update-scheduler
 *
 * @package cus
 */


/**
 * Content Update Scheduler main class
 */
class ContentUpdateScheduler
{
    /**
     * Copies grouped products from one product to another.
     *
     * @param int $source_product_id      The product from which to copy grouped products.
     * @param int $destination_product_id The product which will get the grouped products.
     *
     * @return void
     */
    private static function copy_grouped_products($source_product_id, $destination_product_id)
    {
        $grouped_products = get_post_meta($source_product_id, '_children', true);
        if (!empty($grouped_products)) {
            update_post_meta($destination_product_id, '_children', $grouped_products);
        }
    }

    /**
     * Copies external product data from one product to another.
     *
     * @param int $source_product_id      The product from which to copy external product data.
     * @param int $destination_product_id The product which will get the external product data.
     *
     * @return void
     */
    private static function copy_external_product($source_product_id, $destination_product_id)
    {
        $product_url = get_post_meta($source_product_id, '_product_url', true);
        $button_text = get_post_meta($source_product_id, '_button_text', true);

        if (!empty($product_url)) {
            update_post_meta($destination_product_id, '_product_url', $product_url);
        }
        if (!empty($button_text)) {
            update_post_meta($destination_product_id, '_button_text', $button_text);
        }
    }

    /**
     * Copies simple product data from one product to another.
     *
     * @param int $source_product_id      The product from which to copy simple product data.
     * @param int $destination_product_id The product which will get the simple product data.
     *
     * @return void
     */
    private static function copy_simple_product($source_product_id, $destination_product_id)
    {
        $regular_price = get_post_meta($source_product_id, '_regular_price', true);
        $sale_price = get_post_meta($source_product_id, '_sale_price', true);
        $stock_status = get_post_meta($source_product_id, '_stock_status', true);
        $stock_quantity = get_post_meta($source_product_id, '_stock', true);
        $manage_stock = get_post_meta($source_product_id, '_manage_stock', true);
        $backorders = get_post_meta($source_product_id, '_backorders', true);

        if (!empty($regular_price)) {
            update_post_meta($destination_product_id, '_regular_price', $regular_price);
        }
        if (!empty($sale_price)) {
            update_post_meta($destination_product_id, '_sale_price', $sale_price);
        }
        update_post_meta($destination_product_id, '_stock_status', $stock_status);
        update_post_meta($destination_product_id, '_stock', $stock_quantity);
        update_post_meta($destination_product_id, '_manage_stock', $manage_stock);
        update_post_meta($destination_product_id, '_backorders', $backorders);
    }

    /**
     * Handles the CSS copying for Elementor and Oxygen plugins.
     *
     * @param int $source_post_id The source post ID.
     * @param int $destination_post_id The destination post ID.
     *
     * @return void
     */
    private static function handle_plugin_css_copy($source_post_id, $destination_post_id)
    {
        // Elementor plugin active.
        if (in_array('elementor/elementor.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            $upload_dir = wp_upload_dir();
            $source_css = $upload_dir['basedir'] . '/elementor/css/post-' . $source_post_id . '.css';
            $destination_css = $upload_dir['basedir'] . '/elementor/css/post-' . $destination_post_id . '.css';

            if (file_exists($source_css)) {
                copy($source_css, $destination_css);
            }

        }

        // Oxygen plugin active.
        if (in_array('oxygen/functions.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            $upload_dir = wp_upload_dir();
            $dir = $upload_dir['basedir'] . '/oxygen/css/' . get_post_field('post_name', $source_post_id) . '-' . $source_post_id . '.css';
            $chdir = $upload_dir['basedir'] . '/oxygen/css/' . get_post_field('post_name', $destination_post_id) . '-' . $destination_post_id . '.css';

            if (!file_exists($chdir)) {
                fopen($chdir, "w");
            }
            if (!file_exists($dir)) {
                fopen($dir, "w");
            }
            copy($dir, $chdir);
        }
    }

    /**
     * Copies variations from one product to another.
     *
     * @param int $source_product_id      The product from which to copy variations.
     * @param int $destination_product_id The product which will get the variations.
     *
     * @return void
     */
    private static function copy_product_variations($source_product_id, $destination_product_id)
    {
        $variations = get_children(array(
            'post_parent' => $source_product_id,
            'post_type'   => 'product_variation',
            'numberposts' => -1,
            'post_status' => 'any',
        ));

        foreach ($variations as $variation) {
            $new_variation = array(
                'post_title'   => $variation->post_title,
                'post_name'    => $variation->post_name,
                'post_status'  => $variation->post_status,
                'post_parent'  => $destination_product_id,
                'post_type'    => 'product_variation',
                'menu_order'   => $variation->menu_order,
                'post_excerpt' => $variation->post_excerpt,
                'post_content' => $variation->post_content,
            );

            $new_variation_id = wp_insert_post($new_variation);

            // Copy variation meta data
            $meta_data = get_post_meta($variation->ID);
            foreach ($meta_data as $key => $value) {
                update_post_meta($new_variation_id, $key, maybe_unserialize($value[0]));
            }

            // Ensure stock status, stock quantity, manage stock, and backorders are correctly copied
            $original_stock_status = get_post_meta($variation->ID, '_stock_status', true);
            $original_stock_quantity = get_post_meta($variation->ID, '_stock', true);
            $original_manage_stock = get_post_meta($variation->ID, '_manage_stock', true);
            $original_backorders = get_post_meta($variation->ID, '_backorders', true);

            if ($original_stock_status !== '') {
                update_post_meta($new_variation_id, '_stock_status', $original_stock_status);
            }

            if ($original_stock_quantity !== '') {
                update_post_meta($new_variation_id, '_stock', $original_stock_quantity);
            }
            
            if ($original_manage_stock !== '') {
                update_post_meta($new_variation_id, '_manage_stock', $original_manage_stock);
            }
            
            if ($original_backorders !== '') {
                update_post_meta($new_variation_id, '_backorders', $original_backorders);
            }
        }
    }

    /**
     * Label to be displayed to the user
     *
     * @access public
     * @var string
     */
    public static $cus_publish_label         = 'Scheduled Content Update';

    /**
     * Title for the Publish Metabox
     *
     * @access protected
     * @var string
     */
    protected static $_cus_publish_metabox    = 'Scheduled Content Update';

    /**
     * Status for wordpress posts
     *
     * @access protected
     * @var string
     */
    protected static $_cus_publish_status     = 'cus_sc_publish';

    /**
     * Initializes cus_publish_label and _cus_publish_metabox with their localized strings.
     *
     * This method initializes cus_publish_label and _cus_publish_metabox with their localized
     * strings and registers the cus_sc_publish post status.
     *
     * @return void
     */
    public static function init()
    {
        require_once dirname(__FILE__) . '/options.php';

        self::load_plugin_textdomain();
        self::$cus_publish_label   = __('Scheduled Content Update', 'cus-scheduleupdate-td');
        self::$_cus_publish_metabox = __('Scheduled Content Update', 'cus-scheduleupdate-td');
        self::register_post_status();

        $post_types = array_merge(
            get_post_types(array('public' => true), 'names'), 
            array('product')
        );
        
        foreach ($post_types as $post_type) {
            add_filter('manage_edit-' . $post_type . '_columns', array( 'ContentUpdateScheduler', 'manage_pages_columns' ));
            add_action('manage_' . $post_type . '_posts_custom_column', array( 'ContentUpdateScheduler', 'manage_pages_custom_column' ), 10, 2);
            add_action('add_meta_boxes', array( 'ContentUpdateScheduler', 'add_meta_boxes_page' ), 10, 2);
        }

        // Specific filter for WooCommerce products
        add_filter('manage_edit-product_columns', array( 'ContentUpdateScheduler', 'manage_pages_columns' ));
        add_action('manage_product_posts_custom_column', array( 'ContentUpdateScheduler', 'manage_pages_custom_column' ), 10, 2);
    }

    /**
     * Wrapper for wp's own load_plugin_textdomain.
     *
     * @access private
     *
     * @return void
     */
    private static function load_plugin_textdomain()
    {
        load_plugin_textdomain('cus-scheduleupdate-td', false, dirname(plugin_basename(__FILE__)) . '/language/');
    }

    /**
     * Retreives all currently registered posttypes.
     *
     * @access private
     *
     * @return array Array of all registered post type as objects
     */
    private static function get_post_types()
    {
        return get_post_types(array(
            'public' => true,
        ), 'objects');
    }

    /**
     * Displays a post's publishing date.
     *
     * @see get_post_meta
     *
     * @return void
     */
    public static function load_pubdate()
    {
        if (isset($_REQUEST['postid'])) { // WPCS: CSRF okay.
            $stamp = get_post_meta(absint(wp_unslash($_REQUEST['postid'])), self::$_cus_publish_status . '_pubdate', true); // WPCS: CSRF okay.
            if ($stamp) {
                $str  = '<div style="margin-left:20px">';
                $str .= self::get_pubdate($stamp);
                $str .= '</div>';
                die($str); // WPCS: XSS okay.
            }
        }
    }

    /**
     * Registers the post status cus_sc_publish.
     *
     * @see register_post_status
     *
     * @return void
     */
    public static function register_post_status()
    {
        $public = false;
        if (ContentUpdateScheduler_Options::get('tsu_visible')) {
            // we only want to register as public if we're not on the search page.
            $public = ! is_search();
        }

        // compatibility with CMS Tree Page View.
        $exclude_from_search = ! is_admin();

        $args = array(
            'label'                     => _x('Scheduled Content Update', 'Status General Name', 'default'),
            'public'                    => $public,
            'internal'                  => true,
            'publicly_queryable'        => true,
            'protected'                 => true,
            'exclude_from_search'       => $exclude_from_search,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            // translators: number of posts.
            'label_count'               => _n_noop('Scheduled Content Update <span class="count">(%s)</span>', 'Scheduled Content Update <span class="count">(%s)</span>', 'cus-scheduleupdate-td'),
        );

        register_post_status(self::$_cus_publish_status, $args);
    }

    /**
     * Adds the cus-schedule-update post status to the list of displayable stati in the parent dropdown
     *
     * @param array $args arguments passed by the filter.
     *
     * @return array Array of parameters
     */
    public static function parent_dropdown_status($args)
    {
        if (! isset($args['post_status']) || ! is_array($args['post_status'])) {
            $args['post_status'] = array( 'publish' );
        }

        $args['post_status'][] = 'cus_sc_publish';

        return $args;
    }

    /**
     * Adds post's state to 'scheduled updates'-posts.
     *
     * @param array $states Array of post states.
     *
     * @global $post
     */
    public static function display_post_states($states)
    {
        global $post;

        if (!$post instanceof WP_Post) {
            return $states;
        }

        $arg = get_query_var('post_status');
        $the_post_types = self::get_post_types();
        // default states for non-public posts.
        if (! isset($the_post_types[ $post->post_type ])) {
            return $states;
        }
        $type = $the_post_types[ $post->post_type ];

        if ($arg !== self::$cus_publish_label && $post->post_status === self::$_cus_publish_status) {
            $states = array( self::$cus_publish_label );
            if (! $type->hierarchical) {
                $orig = get_post(get_post_meta($post->ID, self::$_cus_publish_status . '_original', true));
                array_push($states, __('Original', 'cus-scheduleupdate-td') . ': ' . $orig->post_title);
            }
        }

        return $states;
    }

    /**
     * Adds links for scheduled updates.
     *
     * Adds a link for immediate publishing to all sheduled posts. Adds a link to schedule a change
     * to all non-scheduled posts.
     *
     * @param array $actions Array of available actions added by previous hooks.
     * @param post  $post    the post for which to add actions.
     *
     * @return array Array of available actions for the given post
     */
    public static function page_row_actions($actions, $post)
    {
        $copy = '?action=workflow_copy_to_publish&post=' . $post->ID . '&n=' . wp_create_nonce('workflow_copy_to_publish' . $post->ID);
        if ($post->post_status === self::$_cus_publish_status) {
            $action = '?action=workflow_publish_now&post=' . $post->ID . '&n=' . wp_create_nonce('workflow_publish_now' . $post->ID);
            $actions['publish_now'] = '<a href="' . admin_url('admin.php' . $action) . '">' . __('Publish Now', 'cus-scheduleupdate-td') . '</a>';
            $actions['copy_to_publish'] = '<a href="' . admin_url('admin.php' . $copy) . '">' . self::$cus_publish_label . '</a>';
            if (ContentUpdateScheduler_Options::get('tsu_recursive')) {
                $actions['copy_to_publish'] = '<a href="' . admin_url('admin.php' . $copy) . '">' . __('Schedule recursive', 'cus-scheduleupdate-td') . '</a>';
            }
        } elseif ('trash' !== $post->post_status) {
            $actions['copy_to_publish'] = '<a href="' . admin_url('admin.php' . $copy) . '">' . self::$cus_publish_label . '</a>';
        }

        return $actions;
    }

    /**
     * Adds a column to the pages overview.
     *
     * @param array $columns Array of available columns added by previous hooks.
     *
     * @return array Array of available columns
     */
    public static function manage_pages_columns($columns)
    {
        $new = array();
        foreach ($columns as $key => $val) {
            $new[ $key ] = $val;
            if ('title' === $key) {
                $new['cus_publish'] = esc_html__('Republication Date', 'cus-scheduleupdate-td');
            }
        }
        return $new;
    }

    /**
     * Manages the content of previously added custom columns.
     *
     * @see ContentUpdateScheduler::manage_pages_columns()
     *
     * @param string $column  Name of the column.
     * @param int    $post_id id of the current post.
     *
     * @return void
     */
    public static function manage_pages_custom_column($column, $post_id)
    {
        if ('cus_publish' === $column) {
            $post = get_post($post_id);
            if ($post->post_status === self::$_cus_publish_status || get_post_meta($post_id, self::$_cus_publish_status . '_original', true)) {
                $stamp = get_post_meta($post_id, self::$_cus_publish_status . '_pubdate', true);
                if ($stamp) {
                    echo esc_html(self::get_pubdate($stamp));
                }
            }
        }
    }

    /**
     * Handles the admin action workflow_copy_to_publish.
     * redirects to post edit screen if successful
     *
     * @return void
     */
    public static function admin_action_workflow_copy_to_publish()
    {
        if (isset($_REQUEST['n'], $_REQUEST['post']) && wp_verify_nonce(sanitize_key($_REQUEST['n']), 'workflow_copy_to_publish' . absint($_REQUEST['post']))) {
            $post = get_post(absint(wp_unslash($_REQUEST['post'])));
            $publishing_id = self::create_publishing_post($post);
            if ($publishing_id) {
                wp_redirect(admin_url('post.php?action=edit&post=' . $publishing_id));
                exit;
            } else {
                // translators: %1$s: post type, %2$s: post title.
                $html  = sprintf(__('Could not schedule %1$s %2$s', 'cus-scheduleupdate-td'), $post->post_type, '<i>' . htmlspecialchars($post->post_title) . '</i>');
                $html .= '<br><br>';
                $html .= '<a href="' . esc_attr(admin_url('edit.php?post_type=' . $post->post_type)) . '">' . __('Back') . '</a>';
                wp_die($html); // WPCS: XSS okay.
            }
        }
    }

    /**
     * Handles the admin action workflow_publish_now
     *
     * @return void
     */
    public static function admin_action_workflow_publish_now()
    {
        if (isset($_REQUEST['n'], $_REQUEST['post']) && wp_verify_nonce(sanitize_key($_REQUEST['n']), 'workflow_publish_now' . absint($_REQUEST['post']))) {
            $post = get_post(absint(wp_unslash($_REQUEST['post'])));
            self::publish_post($post->ID);
            wp_redirect(admin_url('edit.php?post_type=' . $post->post_type));
            exit;
            exit;
        }
    }

    /**
     * Adds the 'scheduled update'-metabox to the edit-page screen.
     *
     * @see add_meta_box
     *
     * @param string $post_type The post type of the post being edited.
     * @param post   $post      The post being currently edited.
     *
     * @return void
     */
    public static function add_meta_boxes_page($post_type, $post)
    {
        if ($post->post_status !== self::$_cus_publish_status) {
            return;
        }

        // hides everything except the 'publish' button in the 'publish'-metabox
        add_action('admin_head', function () {
            echo '<style> #duplicate-action, #delete-action, #minor-publishing-actions, #misc-publishing-actions, #preview-action {display:none;} </style>'; // WPCS: XSS okay.
        });

        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('wp-jquery-ui-dialog');
        wp_enqueue_style('wp-admin');

        add_meta_box('meta_' . self::$_cus_publish_status, self::$_cus_publish_metabox, array( 'ContentUpdateScheduler', 'create_meta_box' ), $post_type, 'side');
    }

    /**
     * Creates the HTML-Code for the 'scheduled update'-metabox
     *
     * @param post $post The post being currently edited.
     *
     * @return void
     */
    public static function create_meta_box($post)
    {
        wp_nonce_field(basename(__FILE__), self::$_cus_publish_status . '_nonce');
        $metaname = self::$_cus_publish_status . '_pubdate';
        $stamp = get_post_meta($post->ID, $metaname, true);
        $date = '';
        $time = '';
        
        if ($stamp) {
            $date = wp_date('Y-m-d', $stamp);
            $time = wp_date('H:i', $stamp);
        } else {
            // Set default date to tomorrow
            $date = wp_date('Y-m-d', strtotime('+1 day'));
            $time = '00:00';
        }

        $date_parts = explode('-', $date);
        $year = $date_parts[0];
        $month = $date_parts[1];
        $day = $date_parts[2];

        ?>
        <div class="block-editor-publish-date-time-picker">
            <p>
                <strong><?php esc_html_e('Republication Date', 'cus-scheduleupdate-td'); ?></strong>
            </p>
            <div class="components-datetime">
                <div class="components-datetime__date">
                    <select name="<?php echo esc_attr($metaname); ?>_month" id="<?php echo esc_attr($metaname); ?>_month">
                        <?php
                        $months = array(
                            'January', 'February', 'March', 'April', 'May', 'June',
                            'July', 'August', 'September', 'October', 'November', 'December'
                        );
                        foreach ($months as $index => $month_name) {
                            $month_number = $index + 1;
                            $selected = ($month_number == intval($month)) ? 'selected' : '';
                            echo '<option value="' . esc_attr($month_number) . '" ' . $selected . '>' . esc_html__($month_name, 'cus-scheduleupdate-td') . '</option>';
                        }
                        ?>
                    </select>
                    <input type="number" name="<?php echo esc_attr($metaname); ?>_day" id="<?php echo esc_attr($metaname); ?>_day" min="1" max="31" value="<?php echo esc_attr($day); ?>" />
                    <input type="number" name="<?php echo esc_attr($metaname); ?>_year" id="<?php echo esc_attr($metaname); ?>_year" min="<?php echo esc_attr(date('Y')); ?>" value="<?php echo esc_attr($year); ?>" />
                </div>
                <div class="components-datetime__time">
                    <input type="time" name="<?php echo esc_attr($metaname); ?>_time" id="<?php echo esc_attr($metaname); ?>_time" value="<?php echo esc_attr($time); ?>" />
                </div>
            </div>
            <p>
                <?php esc_html_e('Please enter Time in the site\'s local timezone', 'cus-scheduleupdate-td'); ?>
            </p>
            <p>
                <div id="pastmsg" style="color:red; display:none;">
                    <?php
                    echo esc_html__('The release date is in the past.', 'cus-scheduleupdate-td');
                    if (ContentUpdateScheduler_Options::get('tsu_nodate') === 'nothing') {
                        echo esc_html__('This post will not be published.', 'cus-scheduleupdate-td');
                    } else {
                        echo esc_html__('This post will be published 5 minutes from now.', 'cus-scheduleupdate-td');
                    }
                    ?>
                </div>
            </p>
        </div>
        <script type="text/javascript">
        function initContentUpdateScheduler() {
            jQuery(document).ready(function($) {
                function checkDate() {
                    var month = $('#<?php echo esc_js($metaname); ?>_month').val();
                    var day = $('#<?php echo esc_js($metaname); ?>_day').val();
                    var year = $('#<?php echo esc_js($metaname); ?>_year').val();
                    var time = $('#<?php echo esc_js($metaname); ?>_time').val();
                    
                    var selectedDate = new Date(year, month - 1, day, ...time.split(':'));
                    var now = new Date();
                    
                    if (selectedDate <= now) {
                        $('#pastmsg').show();
                    } else {
                        $('#pastmsg').hide();
                    }
                }

                $('#<?php echo esc_js($metaname); ?>_month, #<?php echo esc_js($metaname); ?>_day, #<?php echo esc_js($metaname); ?>_year, #<?php echo esc_js($metaname); ?>_time').on('change', checkDate);
                
                checkDate(); // Initial check
            });
        }

        if (document.readyState === 'complete' || (document.readyState !== 'loading' && !document.documentElement.doScroll)) {
            initContentUpdateScheduler();
        } else {
            document.addEventListener('DOMContentLoaded', initContentUpdateScheduler);
        }
        </script>
        <style>
        .block-editor-publish-date-time-picker .components-datetime__date,
        .block-editor-publish-date-time-picker .components-datetime__time {
            display: flex;
            align-items: center;
            margin-bottom: 1em;
        }
        .block-editor-publish-date-time-picker select,
        .block-editor-publish-date-time-picker input[type="number"],
        .block-editor-publish-date-time-picker input[type="time"] {
            margin-right: 0.5em;
        }
        .block-editor-publish-date-time-picker input[type="number"] {
            width: 5em;
        }
        </style>
        <?php
    }

    /**
     * Gets the currently set timezone..
     *
     * Retreives either the timezone_string or the gmt_offset.
     *
     * @see get_option
     *
     * @access private
     *
     * @return string The set timezone
     */
    private static function get_timezone_string()
    {
        $current_offset = get_option('gmt_offset');
        $tzstring = get_option('timezone_string');

        // Remove old Etc mappings. Fallback to gmt_offset.
        if (false !== strpos($tzstring, 'Etc/GMT')) {
            $tzstring = '';
        }

        if (empty($tzstring)) { // Create a UTC+- zone if no timezone string exists.
            $offset_string = sprintf('%+d', $current_offset);
            $offset_string = str_replace(array('.25', '.5', '.75'), array(':15', ':30', ':45'), $offset_string);
            $tzstring = 'UTC' . $offset_string;
        }

        // Attempt to create a DateTimeZone object to validate the timezone string
        try {
            new DateTimeZone($tzstring);
        } catch (Exception $e) {
            // If the timezone string is invalid, fall back to UTC
            $tzstring = 'UTC';
        }

        return $tzstring;
    }

    /**
     * Creates a timezone object based on the option gmt_offset
     *
     * @see DateTimeZone
     *
     * @return DateTimeZone timezone specified by the gmt_offset option
     */
    private static function get_timezone_object()
    {
        $offset = intval(get_option('gmt_offset') * 3600);
        $ids = DateTimeZone::listIdentifiers();
        foreach ($ids as $timezone) {
            $tzo = new DateTimeZone($timezone);
            $dt = new DateTime('now', $tzo);
            if ($tzo->getOffset($dt) === $offset) {
                return $tzo;
            }
        }
    }

    /**
     * Prevents scheduled updates to switch to other post states.
     *
     * Prevents post with the state 'scheduled update' to switch to published after being saved
     * clears cron hook if post is trashed
     * restores cron hook if post us un-trashed
     *
     * @param string $new_status the post's new status.
     * @param string $old_status the post's old status.
     * @param post   $post       the post changing status.
     *
     * @return void
     */
    public static function prevent_status_change($new_status, $old_status, $post)
    {
        if ($new_status === $old_status && $new_status === self::$_cus_publish_status) {
            return;
        }

        if ($old_status === self::$_cus_publish_status && 'trash' !== $new_status) {
            remove_action('save_post', array( 'ContentUpdateScheduler', 'save_meta' ), 10);

            $post->post_status = self::$_cus_publish_status;
            wp_update_post($post, true);

            add_action('save_post', array( 'ContentUpdateScheduler', 'save_meta' ), 10, 2);
        } elseif ('trash' === $new_status) {
            wp_clear_scheduled_hook('cus_publish_post', array(
                $post->ID,
            ));
        } elseif ('trash' === $old_status && $new_status === self::$_cus_publish_status) {
            wp_schedule_single_event(get_post_meta($post->ID, self::$_cus_publish_status . '_pubdate', true), 'cus_publish_post', array(
                $post->ID,
            ));
        }
    }

    /**
     * Copies an entire post and sets it's status to 'scheduled update'
     *
     * @param post $post the post to be copied.
     *
     * @return int ID of the newly created post
     */
    public static function create_publishing_post($post)
    {
        $original = $post->ID;
        if ($post->post_status === self::$_cus_publish_status) {
            $original = get_post_meta($post->ID, self::$_cus_publish_status . '_original', true);
        }
        
        $new_author = get_user_by('id', $post->post_author);

        // create the new post.
        $new_post = array(
            'menu_order'     => $post->menu_order,
            'comment_status' => $post->comment_status,
            'ping_status'    => $post->ping_status,
            'post_author'    => $new_author->ID,
            'post_content'   => $post->post_content,
            'post_excerpt'   => $post->post_excerpt,
            'post_mime_type' => $post->mime_type,
            'post_parent'    => $post->ID,
            'post_password'  => $post->post_password,
            'post_status'    => self::$_cus_publish_status,
            'post_title'     => $post->post_title,
            'post_type'      => $post->post_type,
        );

        // insert the new post.
        $new_post_id = wp_insert_post($new_post, true);
        if (is_wp_error($new_post_id)) {
            return $new_post_id;
        }

        self::handle_plugin_css_copy($post->ID, $new_post_id);

        // copy meta and terms over to the new post.
        self::copy_meta_and_terms($post->ID, $new_post_id);

        // and finally referencing the original post.
        update_post_meta($new_post_id, self::$_cus_publish_status . '_original', $original);

        // Handle WooCommerce products
        if (class_exists('WooCommerce') && $post->post_type === 'product') {
            $product = wc_get_product($post->ID);
            if ($product) {
                if ($product->is_type('variable')) {
                    self::copy_product_variations($post->ID, $new_post_id);
                } elseif ($product->is_type('grouped')) {
                    self::copy_grouped_products($post->ID, $new_post_id);
                } elseif ($product->is_type('external')) {
                    self::copy_external_product($post->ID, $new_post_id);
                } else {
                    self::copy_simple_product($post->ID, $new_post_id);
                }
                // Update stock status and quantity if they exist
                if ($original_stock_status !== '') {
                    update_post_meta($post_id, '_stock_status', $original_stock_status);
                }
                if ($original_stock_quantity !== '') {
                    update_post_meta($post_id, '_stock', $original_stock_quantity);
                }
            }
        }

        /**
         * Fires when a post has been duplicated.
         *
         * @param int     $new_post_id ID of the newly created post.
         * @param int     $original    ID of the original post.
         */
        do_action('ContentUpdateScheduler\\create_publishing_post', $new_post_id, $original);

        return $new_post_id;
    }

    /**
     * Copies meta and terms from one post to another
     *
     * @param int $source_post_id      the post from which to copy.
     * @param int $destination_post_id the post which will get the meta and terms.
     * @param bool $restore_references Whether to restore references to the original post ID.
     *
     * @return void
     */
    public static function copy_meta_and_terms($source_post_id, $destination_post_id, $restore_references = false)
    {

        $source_post = get_post($source_post_id);
        $destination_post = get_post($destination_post_id);

        // abort if any of the ids is not a post.
        if (! $source_post || ! $destination_post) {
            return;
        }

        /*
         * remove all meta from the destination,
         * initialize to emptyarray if not set to prevent error in foreach loop
         */

        // now for copying the metadata to the new post.
        $meta = get_post_meta($source_post->ID);
        foreach ($meta as $key => $values) {
            delete_post_meta($destination_post->ID, $key); // Delete existing meta to avoid duplicates
            foreach ($values as $value) {
                if (is_serialized($value)) {
                    $value = preg_replace_callback('/O:\d+:"([^"]+)"/', function ($matches) {
                        return class_exists($matches[1]) ? $matches[0] : 'O:8:"stdClass"';
                    }, $value);
                    
                    $unserialized_value = maybe_unserialize($value);
                    
                    if (is_string($unserialized_value) && strpos($unserialized_value, 'O:8:"stdClass"') !== false) {
                        // Skip this meta entry if it contains undefined objects
                        error_log('Skipping meta entry for key: ' . $key . '. Unserialized value contains undefined objects.');
                        continue 2;
                    }
                } else {
                    $unserialized_value = $value;
                }
                
                if ($restore_references && is_string($unserialized_value) && strpos($unserialized_value, (string)$source_post->ID) !== false) {
                    $unserialized_value = str_replace((string)$source_post->ID, (string)$destination_post->ID, $unserialized_value);
                }
                
                add_post_meta($destination_post->ID, $key, $unserialized_value);
            }
        }


        // and now for copying the terms.
        $taxonomies = get_object_taxonomies($source_post->post_type);
        foreach ($taxonomies as $taxonomy) {
            $post_terms = wp_get_object_terms($source_post->ID, $taxonomy, array(
                'orderby' => 'term_order',
            ));
            $terms = array();
            foreach ($post_terms as $term) {
                $terms[] = $term->slug;
            }
            // reset taxonomy to empty.
            wp_set_object_terms($destination_post->ID, null, $taxonomy);
            // then add new terms.
            wp_set_object_terms($destination_post->ID, $terms, $taxonomy);
        }
    }

    /**
     * Saves a post's publishing date.
     *
     * @param int  $post_id the post's id.
     * @param post $post    the post being saved.
     *
     * @return mixed
     */
    public static function save_meta($post_id, $post)
    {
        error_log("save_meta called for post ID: " . $post_id);
        
        if ($post->post_status === self::$_cus_publish_status || get_post_meta($post_id, self::$_cus_publish_status . '_original', true)) {
            $nonce = ContentUpdateScheduler::$_cus_publish_status . '_nonce';
            $pub = ContentUpdateScheduler::$_cus_publish_status . '_pubdate';

            error_log("Nonce: " . (isset($_POST[$nonce]) ? 'set' : 'not set'));
            
            if (!isset($_POST[$nonce]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonce])), basename(__FILE__))) {
                error_log("Nonce verification failed");
                return $post_id;
            }
            if (!current_user_can(get_post_type_object($post->post_type)->cap->edit_post, $post_id)) {
                error_log("User doesn't have permission to edit this post");
                return $post_id;
            }

            error_log("POST data: " . print_r($_POST, true));

            if (isset($_POST[$pub . '_month'], $_POST[$pub . '_day'], $_POST[$pub . '_year'], $_POST[$pub . '_time'])) {
                $month = intval($_POST[$pub . '_month']);
                $day = intval($_POST[$pub . '_day']);
                $year = intval($_POST[$pub . '_year']);
                $time = sanitize_text_field($_POST[$pub . '_time']);

                error_log("Date components: Year: $year, Month: $month, Day: $day, Time: $time");

                $tz = wp_timezone();
                $date_string = sprintf('%04d-%02d-%02d %s', $year, $month, $day, $time);
                $date_time = DateTime::createFromFormat('Y-m-d H:i', $date_string, $tz);

                if ($date_time === false) {
                    error_log("Invalid date format: $date_string");
                    return $post_id;
                }

                $stamp = $date_time->getTimestamp();

                error_log("Calculated timestamp: $stamp");

                if ($stamp <= time()) {
                    $stamp = time() + 300; // 5 minutes from now
                    error_log("Timestamp was in the past, set to 5 minutes from now: $stamp");
                }

                wp_clear_scheduled_hook('cus_publish_post', array($post_id));
                update_post_meta($post_id, $pub, $stamp);
                wp_schedule_single_event($stamp, 'cus_publish_post', array($post_id));
                error_log("Scheduled event for timestamp: $stamp");
            } else {
                error_log("Date/time POST variables not set");
            }

            // Check if the post being saved is a republication draft
            $original_post_id = get_post_meta($post_id, self::$_cus_publish_status . '_original', true);
            if ($original_post_id) {
                error_log("Handling republication draft. Original post ID: $original_post_id");
                // Ensure the original post's stock status and quantity are maintained
                $original_stock_status = get_post_meta($original_post_id, '_stock_status', true);
                $original_stock_quantity = get_post_meta($original_post_id, '_stock', true);

                if ($original_stock_status !== '') {
                    update_post_meta($post_id, '_stock_status', $original_stock_status);
                    error_log("Updated stock status: $original_stock_status");
                }
                if ($original_stock_quantity !== '') {
                    update_post_meta($post_id, '_stock', $original_stock_quantity);
                    error_log("Updated stock quantity: $original_stock_quantity");
                }
            }
        } else {
            error_log("Post status is not cus_sc_publish and no original post found");
        }
    }

    /**
     * Publishes a scheduled update
     *
     * Copies the original post's contents and meta into it's "scheduled update" and then deletes
     * the original post. This function is either called by wp_cron or if the user hits the
     * 'publish now' action
     *
     * @param int $post_id the post's id.
     *
     * @return int the original post's id
     */
    public static function publish_post($post_id)
    {
        $orig_id = get_post_meta($post_id, self::$_cus_publish_status . '_original', true);

        // break early if given post is not an actual scheduled post created by this plugin.
        if (! $orig_id) {
            return $post_id;
        }

        $orig = get_post($orig_id);

        // Ensure the post is not in the trash before proceeding
        if ($post->post_status === 'trash') {
            return $post_id;
        }

        $post = get_post($post_id);
        $original_stock_status = get_post_meta($orig->ID, '_stock_status', true);
        $original_stock_quantity = get_post_meta($orig->ID, '_stock', true);

        self::handle_plugin_css_copy($post->ID, $orig_id);

        /**
         * Fires before a scheduled post is being updated
         *
         * @param WP_Post $post the scheduled update post.
         * @param WP_post $orig the original post.
         */
        do_action('ContentUpdateScheduler\\before_publish_post', $post, $orig);
        delete_post_meta($orig->ID, self::$_cus_publish_status . '_pubdate');
        // Copy meta and terms, restoring references to the original post ID
        self::copy_meta_and_terms($post->ID, $orig->ID, true);

        $post->ID = $orig->ID;
        $post->post_name = $orig->post_name;
        $post->guid = $orig->guid;
        $post->post_parent = $orig->post_parent;
        $post->post_status = $orig->post_status;
        $post_date = wp_date('Y-m-d H:i:s');

        /**
         * Filter the new posts' post date
         *
         * @param string  $post_date the date to be used, must be in the form of `Y-m-d H:i:s`.
         * @param WP_Post $post      the scheduled update post.
         * @param WP_Post $orig      the original post.
         */
        $post_date = apply_filters('ContentUpdateScheduler\\publish_post_date', $post_date, $post, $orig);

        $post->post_date = $post_date; // we need this to get wp to recognize this as a newly updated post.
        $post->post_date_gmt = get_gmt_from_date($post_date);

        delete_post_meta($orig->ID, self::$_cus_publish_status . '_pubdate');

        $result = wp_update_post($post, true);
        if (!is_wp_error($result)) {
            if ($original_stock_status !== '') {
                update_post_meta($post->ID, '_stock_status', $original_stock_status);
            }
            if ($original_stock_quantity !== '') {
                update_post_meta($post->ID, '_stock', $original_stock_quantity);
            }
        }
        if (is_wp_error($result)) {
            return $result;
        }
        $result = wp_delete_post($post_id, true);
        if (is_wp_error($result)) {
            return $result;
        }

        return $orig->ID;
    }

    /**
     * Wrapper function for cron automated publishing
     * disables the kses filters before and reenables them after the post has been published
     *
     * @param int $ID the post's id.
     *
     * @return void
     */
    public static function cron_publish_post($ID)
    {
        kses_remove_filters();
        self::publish_post($ID);
        kses_init_filters();
    }

    /**
     * Reformats a timestamp into human readable publishing date and time
     *
     * @see date_i18n, DateTime, ContentUpdateScheduler::get_timezone_object
     *
     * @param int $stamp unix timestamp to be formatted.
     *
     * @return string the formatted timestamp
     */
    public static function get_pubdate($stamp)
    {
        $date = new DateTime('@' . $stamp);
        $date->setTimezone(self::get_timezone_object());
        return $date->format(get_option('date_format') . ' ' . get_option('time_format'));
    }

    /* bhullar custom code */
    public static function override_static_front_page_and_post_option($html, $arg)
    {
        $arg['post_status'] = array('publish', 'draft','future','cus_sc_publish');
        echo self::co_wp_dropdown_pages($arg);
        ?>
        <style type="text/css">
            select#page_on_front, select#page_for_posts {float: right;margin-left: 10px;}
        </style>
        <?php
    }

    public static function co_wp_dropdown_pages($args = '')
    {
        $defaults = array(
            'depth'                 => 0,
            'child_of'              => 0,
            'selected'              => 0,
            'echo'                  => 1,
            'name'                  => 'page_id',
            'id'                    => '',
            'class'                 => '',
            'show_option_none'      => '',
            'show_option_no_change' => '',
            'option_none_value'     => '',
            'value_field'           => 'ID',
        );

        $parsed_args = wp_parse_args($args, $defaults);

        $pages  = get_pages($parsed_args);
        $output = '';
        // Back-compat with old system where both id and name were based on $name argument
        if (empty($parsed_args['id'])) {
            $parsed_args['id'] = $parsed_args['name'];
        }

        if (! empty($pages)) {
            $class = '';
            if (! empty($parsed_args['class'])) {
                $class = " class='" . esc_attr($parsed_args['class']) . "'";
            }

            $output = "<select name='" . esc_attr($parsed_args['name']) . "'" . $class . " id='" . esc_attr($parsed_args['id']) . "'>\n";
            if ($parsed_args['show_option_no_change']) {
                $output .= "\t<option value=\"-1\">" . $parsed_args['show_option_no_change'] . "</option>\n";
            }
            if ($parsed_args['show_option_none']) {
                $output .= "\t<option value=\"" . esc_attr($parsed_args['option_none_value']) . '">' . $parsed_args['show_option_none'] . "</option>\n";
            }
            $output .= walk_page_dropdown_tree($pages, $parsed_args['depth'], $parsed_args);
            $output .= "</select>\n";
        }

        /**
         * Filters the HTML output of a list of pages as a drop down.
         *
         * @since 2.1.0
         * @since 4.4.0 `$parsed_args` and `$pages` added as arguments.
         *
         * @param string $output      HTML output for drop down list of pages.
         * @param array  $parsed_args The parsed arguments array.
         * @param array  $pages       List of WP_Post objects returned by `get_pages()`
         */
        $html = apply_filters('co_wp_dropdown_pages', $output, $parsed_args, $pages);

        if ($parsed_args['echo']) {
            echo $html;
        }
        return $html;
    }

    public static function user_restriction_scheduled_content()
    {
        global $post;

        if (!$post instanceof WP_Post) {
            return;
        }

        if (!current_user_can('administrator')) {
            $cus_sc_publish_pubdate = get_post_meta($post->ID, 'cus_sc_publish_pubdate', true);
            $original_post_id = get_post_meta($post->ID, self::$_cus_publish_status . '_original', true);
            
            // Check if the post is a scheduled update and its publication time has passed
            if (!empty($cus_sc_publish_pubdate) && $cus_sc_publish_pubdate > current_time('timestamp') && empty($original_post_id)) {
                global $wp_query;
                $wp_query->set_404();
                status_header(404);
                get_template_part(404);
                exit();
            }
        }
    }
}

add_action('save_post', array( 'ContentUpdateScheduler', 'save_meta' ), 10, 2);
add_action('cus_publish_post', array( 'ContentUpdateScheduler', 'cron_publish_post' ), PHP_INT_MIN);

add_action('wp_ajax_load_pubdate', array( 'ContentUpdateScheduler', 'load_pubdate' ));
add_action('init', array( 'ContentUpdateScheduler', 'init' ), PHP_INT_MAX);
add_action('admin_action_workflow_copy_to_publish', array( 'ContentUpdateScheduler', 'admin_action_workflow_copy_to_publish' ));
add_action('admin_action_workflow_publish_now', array( 'ContentUpdateScheduler', 'admin_action_workflow_publish_now' ));
add_action('transition_post_status', array( 'ContentUpdateScheduler', 'prevent_status_change' ), 10, 3);

add_filter('display_post_states', array( 'ContentUpdateScheduler', 'display_post_states' ));
add_filter('page_row_actions', array( 'ContentUpdateScheduler', 'page_row_actions' ), 10, 2);
add_filter('post_row_actions', array( 'ContentUpdateScheduler', 'page_row_actions' ), 10, 2);
add_filter('manage_pages_columns', array( 'ContentUpdateScheduler', 'manage_pages_columns' ));
add_filter('page_attributes_dropdown_pages_args', array( 'ContentUpdateScheduler', 'parent_dropdown_status' ));

/* bhullar custom code */
add_action('admin_init', function () {
    //add_filter( 'wp_dropdown_pages', array( 'ContentUpdateScheduler', 'override_static_front_page_and_post_option' ), 1 , 2);
});

add_action('admin_footer', function (){ ?>
    <style>div#ui-datepicker-div {
    z-index: 99 !important;
}</style>
    <?php
});

add_filter('template_redirect', array( 'ContentUpdateScheduler', 'user_restriction_scheduled_content' ), 1);

register_deactivation_hook(__FILE__, 'cus_deactivation');

function cus_deactivation() {
    global $wpdb;
    $wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key = 'cus_sc_publish_pubdate'");
}
