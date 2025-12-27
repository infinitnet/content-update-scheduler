<?php
/**
 * Core plugin implementation.
 *
 * @package cus
 */

defined('ABSPATH') || exit;

class ContentUpdateScheduler
{
    // Text domain constant
    const TEXT_DOMAIN = 'content-update-scheduler';
    
    // WooCommerce meta key constants
    const META_STOCK_STATUS = '_stock_status';
    const META_STOCK_QUANTITY = '_stock';
    const META_REGULAR_PRICE = '_regular_price';
    const META_SALE_PRICE = '_sale_price';
    const META_MANAGE_STOCK = '_manage_stock';
    const META_BACKORDERS = '_backorders';
    const META_PRODUCT_URL = '_product_url';
    const META_BUTTON_TEXT = '_button_text';
    const META_CHILDREN = '_children';
    
    // Elementor meta key constants
    const META_ELEMENTOR_DATA = '_elementor_data';
    const META_ELEMENTOR_EDIT_MODE = '_elementor_edit_mode';
    const META_ELEMENTOR_PAGE_SETTINGS = '_elementor_page_settings';
    const META_ELEMENTOR_VERSION = '_elementor_version';
    const META_ELEMENTOR_TEMPLATE_TYPE = '_elementor_template_type';
    const META_ELEMENTOR_CONTROLS_USAGE = '_elementor_controls_usage';
    
    // WPML meta key constants
    const META_WPML_MEDIA_FEATURED = '_wpml_media_featured';
    const META_WPML_MEDIA_DUPLICATE = '_wpml_media_duplicate';
    const META_WPML_MEDIA_PROCESSED = '_wpml_media_processed';

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
        $grouped_products = get_post_meta($source_product_id, self::META_CHILDREN, true);
        if (!empty($grouped_products)) {
            update_post_meta($destination_product_id, self::META_CHILDREN, $grouped_products);
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
        $product_url = get_post_meta($source_product_id, self::META_PRODUCT_URL, true);
        $button_text = get_post_meta($source_product_id, self::META_BUTTON_TEXT, true);

        if (!empty($product_url)) {
            update_post_meta($destination_product_id, self::META_PRODUCT_URL, $product_url);
        }
        if (!empty($button_text)) {
            update_post_meta($destination_product_id, self::META_BUTTON_TEXT, $button_text);
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
        $regular_price = get_post_meta($source_product_id, self::META_REGULAR_PRICE, true);
        $sale_price = get_post_meta($source_product_id, self::META_SALE_PRICE, true);
        $stock_status = get_post_meta($source_product_id, self::META_STOCK_STATUS, true);
        $stock_quantity = get_post_meta($source_product_id, self::META_STOCK_QUANTITY, true);
        $manage_stock = get_post_meta($source_product_id, self::META_MANAGE_STOCK, true);
        $backorders = get_post_meta($source_product_id, self::META_BACKORDERS, true);

        if (!empty($regular_price)) {
            update_post_meta($destination_product_id, self::META_REGULAR_PRICE, $regular_price);
        }
        if (!empty($sale_price)) {
            update_post_meta($destination_product_id, self::META_SALE_PRICE, $sale_price);
        }
        update_post_meta($destination_product_id, self::META_STOCK_STATUS, $stock_status);
        update_post_meta($destination_product_id, self::META_STOCK_QUANTITY, $stock_quantity);
        update_post_meta($destination_product_id, self::META_MANAGE_STOCK, $manage_stock);
        update_post_meta($destination_product_id, self::META_BACKORDERS, $backorders);
    }

    /**
     * Copies Elementor-specific data between posts
     * 
     * @param int $source_id Source post ID
     * @param int $destination_id Destination post ID
     * @return bool Success status
     */
    private static function copy_elementor_data($source_id, $destination_id) {
        // Early exit if Elementor isn't active
        if (!defined('ELEMENTOR_VERSION')) {
            return false;
        }

        try {
            // Core Elementor meta keys that must be preserved
            $elementor_meta_keys = [
                self::META_ELEMENTOR_DATA,
                self::META_ELEMENTOR_EDIT_MODE,
                self::META_ELEMENTOR_PAGE_SETTINGS,
                self::META_ELEMENTOR_VERSION,
                self::META_ELEMENTOR_TEMPLATE_TYPE,
                self::META_ELEMENTOR_CONTROLS_USAGE,
                '_elementor_css'
            ];

            // Get all meta at once for efficiency
            $source_meta = get_post_meta($source_id);
            
            foreach ($elementor_meta_keys as $key) {
                if (!isset($source_meta[$key][0])) {
                    continue;
                }

                $value = $source_meta[$key][0];
                
                // Special handling for Elementor's JSON data
                if ($key === self::META_ELEMENTOR_DATA) {
                    // Validate JSON structure
                    $decoded = json_decode($value);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        // WordPress handles slashing automatically - do not use wp_slash()
                        update_post_meta($destination_id, $key, $value);
                    }
                    continue;
                }

                // Copy other Elementor meta directly
                update_post_meta($destination_id, $key, maybe_unserialize($value));
            }

            // Copy Elementor CSS file
            $upload_dir = wp_upload_dir();
            $source_css = $upload_dir['basedir'] . '/elementor/css/post-' . $source_id . '.css';
            $dest_css = $upload_dir['basedir'] . '/elementor/css/post-' . $destination_id . '.css';
            
            if (file_exists($source_css)) {
                @copy($source_css, $dest_css);
            }

            return true;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Handles the Oxygen builder CSS copying
     *
     * @param int $source_id Source post ID
     * @param int $destination_id Destination post ID
     * @return bool Success status
     */
    private static function handle_wpml_relationships($source_id, $destination_id, $is_publishing = false) {
        // Early exit if WPML isn't active
        if (!defined('ICL_SITEPRESS_VERSION')) {
            return false;
        }

        try {
            // Basic validation
            $post_type = get_post_type($source_id);
            if (!$post_type || $post_type !== get_post_type($destination_id)) {
                return false;
            }

            $element_type = 'post_' . $post_type;

            // Get source language details
            $source_details = apply_filters('wpml_element_language_details', null, array( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
                'element_id' => $source_id,
                'element_type' => $element_type
            ));

            if (!$source_details) {
                return false;
            }

            /**
             * Filter whether to create new translation group
             * 
             * @param bool $create_new_group Whether to create new translation group
             * @param int $source_id Source post ID
             * @param int $destination_id Destination post ID
             * @param bool $is_publishing Whether this is a publish operation
             */
            $create_new_group = apply_filters(
                'content_update_scheduler_wpml_new_translation_group',
                !$is_publishing,
                $source_id,
                $destination_id,
                $is_publishing
            );

            // Set language details
            do_action('wpml_set_element_language_details', array( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
                'element_id' => $destination_id,
                'element_type' => $element_type,
                'trid' => $create_new_group ? null : apply_filters('wpml_element_trid', null, $source_id, $element_type), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
                'language_code' => $source_details->language_code,
                'source_language_code' => $source_details->source_language_code
            ));

            // Copy essential WPML meta
            $wpml_meta_keys = array(
                self::META_WPML_MEDIA_FEATURED,
                self::META_WPML_MEDIA_DUPLICATE,
                self::META_WPML_MEDIA_PROCESSED
            );

            foreach ($wpml_meta_keys as $meta_key) {
                $value = get_post_meta($source_id, $meta_key, true);
                if ($value !== '') {
                    update_post_meta($destination_id, $meta_key, $value);
                }
            }

            /**
             * Fires after WPML relationships have been handled
             * 
             * @param int $source_id Source post ID
             * @param int $destination_id Destination post ID
             * @param bool $is_publishing Whether this is a publish operation
             */
            do_action('content_update_scheduler_after_wpml_handling', 
                $source_id, 
                $destination_id, 
                $is_publishing
            );

            return true;

        } catch (Exception $e) {
            return false;
        }
    }

    private static function copy_oxygen_data($source_id, $destination_id) {
        // Early exit if Oxygen isn't active
        if (!in_array('oxygen/functions.php', apply_filters('active_plugins', get_option('active_plugins')))) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            return false;
        }

        try {
            $upload_dir = wp_upload_dir();
            $source_css = $upload_dir['basedir'] . '/oxygen/css/' . 
                         get_post_field('post_name', $source_id) . '-' . $source_id . '.css';
            $dest_css = $upload_dir['basedir'] . '/oxygen/css/' . 
                       get_post_field('post_name', $destination_id) . '-' . $destination_id . '.css';

            // Copy CSS if source exists
            if (file_exists($source_css)) {
                @copy($source_css, $dest_css);
            }

            return true;

        } catch (Exception $e) {
            return false;
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
            // Protect Unicode escape sequences in variation content
            $protected_var_content = $variation->post_content;
            $protected_var_excerpt = $variation->post_excerpt;
            
            if (strpos($protected_var_content, '\u') !== false) {
                $protected_var_content = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function($matches) {
                    return '___UNICODE_' . $matches[1] . '___';
                }, $protected_var_content);
            }
            
            if (strpos($protected_var_excerpt, '\u') !== false) {
                $protected_var_excerpt = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function($matches) {
                    return '___UNICODE_' . $matches[1] . '___';
                }, $protected_var_excerpt);
            }

            $new_variation = array(
                'post_title'   => $variation->post_title,
                'post_name'    => $variation->post_name,
                'post_status'  => $variation->post_status,
                'post_parent'  => $destination_product_id,
                'post_type'    => 'product_variation',
                'menu_order'   => $variation->menu_order,
                'post_excerpt' => $protected_var_excerpt,
                'post_content' => $protected_var_content,
            );

            $new_variation_id = wp_insert_post($new_variation);
            
            // Restore Unicode escapes in variation if needed
            if (($protected_var_content !== $variation->post_content || $protected_var_excerpt !== $variation->post_excerpt) 
                && !is_wp_error($new_variation_id)) {
                $update_data = array();
                
                if ($protected_var_content !== $variation->post_content) {
                    $restored_content = preg_replace_callback('/___UNICODE_([0-9a-fA-F]{4})___/', function($matches) {
                        return '\\u' . $matches[1];
                    }, $protected_var_content);
                    $update_data['post_content'] = $restored_content;
                }
                
                if ($protected_var_excerpt !== $variation->post_excerpt) {
                    $restored_excerpt = preg_replace_callback('/___UNICODE_([0-9a-fA-F]{4})___/', function($matches) {
                        return '\\u' . $matches[1];
                    }, $protected_var_excerpt);
                    $update_data['post_excerpt'] = $restored_excerpt;
                }
                
                if (!empty($update_data)) {
                    global $wpdb;
                    $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                        $wpdb->posts,
                        $update_data,
                        array('ID' => $new_variation_id),
                        array_fill(0, count($update_data), '%s'),
                        array('%d')
                    );
                    clean_post_cache($new_variation_id);
                }
            }

            // Copy variation meta data
            $meta_data = get_post_meta($variation->ID);
            foreach ($meta_data as $key => $value) {
                update_post_meta($new_variation_id, $key, maybe_unserialize($value[0]));
            }

            // Ensure stock status, stock quantity, manage stock, and backorders are correctly copied
            $original_stock_status = get_post_meta($variation->ID, self::META_STOCK_STATUS, true);
            $original_stock_quantity = get_post_meta($variation->ID, self::META_STOCK_QUANTITY, true);
            $original_manage_stock = get_post_meta($variation->ID, self::META_MANAGE_STOCK, true);
            $original_backorders = get_post_meta($variation->ID, self::META_BACKORDERS, true);

            if ($original_stock_status !== '') {
                update_post_meta($new_variation_id, self::META_STOCK_STATUS, $original_stock_status);
            }

            if ($original_stock_quantity !== '') {
                update_post_meta($new_variation_id, self::META_STOCK_QUANTITY, $original_stock_quantity);
            }
            
            if ($original_manage_stock !== '') {
                update_post_meta($new_variation_id, self::META_MANAGE_STOCK, $original_manage_stock);
            }
            
            if ($original_backorders !== '') {
                update_post_meta($new_variation_id, self::META_BACKORDERS, $original_backorders);
            }
        }
    }

    /**
     * Label to be displayed to the user
     *
     * @access public
     * @var string
     */
    public static $cus_publish_label         = 'Content Update Scheduler';

    /**
     * Title for the Publish Metabox
     *
     * @access protected
     * @var string
     */
    protected static $_cus_publish_metabox    = 'Content Update Scheduler';

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
        
        self::$cus_publish_label    = __('Content Update Scheduler', 'content-update-scheduler');
        self::$_cus_publish_metabox = __('Content Update Scheduler', 'content-update-scheduler');
        self::register_post_status();

        // Get all public post types plus 'product' (maintaining existing behavior)
        $post_types = array_merge(
            get_post_types(array('public' => true), 'names'), 
            array('product')
        );

        /**
         * Filter to exclude specific post types from content update scheduling
         * 
         * @param array $excluded_post_types Array of post type names to exclude
         * @return array Modified array of post type names to exclude
         */
        $excluded_post_types = apply_filters('content_update_scheduler_excluded_post_types', array());

        // Ensure excluded_post_types is an array
        $excluded_post_types = is_array($excluded_post_types) ? $excluded_post_types : array();

        // Remove excluded post types
        $post_types = array_diff($post_types, $excluded_post_types);

        // Remove duplicates and ensure valid post types
        $post_types = array_unique($post_types);
        foreach ($post_types as $post_type) {
            if (post_type_exists($post_type)) {
                add_filter('manage_edit-' . $post_type . '_columns', array( 'ContentUpdateScheduler', 'manage_pages_columns' ));
                add_action('manage_' . $post_type . '_posts_custom_column', array( 'ContentUpdateScheduler', 'manage_pages_custom_column' ), 10, 2);
                add_action('add_meta_boxes', array( 'ContentUpdateScheduler', 'add_meta_boxes_page' ), 10, 2);
            }
        }

        // Specific filter for WooCommerce products (maintaining existing behavior)
        add_filter('manage_edit-product_columns', array( 'ContentUpdateScheduler', 'manage_pages_columns' ));
        add_action('manage_product_posts_custom_column', array( 'ContentUpdateScheduler', 'manage_pages_custom_column' ), 10, 2);

        // Set up custom cron schedule.
        \Infinitnet\ContentUpdateScheduler\Support\Cron::ensure_overdue_checker();

        // Add scheduled republications status page (admin only)
        if (is_admin()) {
            add_action('admin_menu', array(__CLASS__, 'add_republications_status_page'));
        }
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
        if (!current_user_can('edit_posts')) {
            wp_die('', '', array('response' => 403));
        }

        if (!isset($_REQUEST['postid'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            wp_die('', '', array('response' => 400));
        }

        $stamp = get_post_meta(absint(wp_unslash($_REQUEST['postid'])), self::$_cus_publish_status . '_pubdate', true); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!$stamp) {
            wp_die('');
        }

        // Keep legacy behavior: return a small HTML snippet for admin-ajax consumers.
        wp_die('<div style="margin-left:20px">' . esc_html(self::get_pubdate($stamp)) . '</div>');
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
            'label'                     => _x('Content Update Scheduler', 'Status General Name', 'content-update-scheduler'),
            'public'                    => $public,
            'internal'                  => true,
            'publicly_queryable'        => true,
            'protected'                 => true,
            'exclude_from_search'       => $exclude_from_search,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            // translators: number of posts.
            'label_count'               => _n_noop('Content Update Scheduler <span class="count">(%s)</span>', 'Content Update Scheduler <span class="count">(%s)</span>', 'content-update-scheduler'),
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
                $orig_id = (int) get_post_meta($post->ID, self::$_cus_publish_status . '_original', true);
	                $orig = $orig_id ? get_post($orig_id) : null;
	                if ($orig instanceof WP_Post) {
	                    array_push($states, __('Original', 'content-update-scheduler') . ': ' . $orig->post_title);
	                }
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
        $copy_url = wp_nonce_url(
            admin_url('admin.php?action=workflow_copy_to_publish&post=' . $post->ID),
            'workflow_copy_to_publish' . $post->ID,
            'n'
        );
        if ($post->post_status === self::$_cus_publish_status) {
            $publish_now_url = wp_nonce_url(
                admin_url('admin.php?action=workflow_publish_now&post=' . $post->ID),
                'workflow_publish_now' . $post->ID,
                'n'
            );
            
            // Only show "Publish Now" to users who can publish posts
            $post_type_object = get_post_type_object($post->post_type);
            $publish_cap = ($post_type_object && isset($post_type_object->cap->publish_posts)) ? $post_type_object->cap->publish_posts : 'publish_posts';

	            if (current_user_can($publish_cap)) {
	                $actions['publish_now'] = '<a href="' . esc_url($publish_now_url) . '">' . __('Publish Now', 'content-update-scheduler') . '</a>';
	            }
            $actions['copy_to_publish'] = '<a href="' . esc_url($copy_url) . '">' . self::$cus_publish_label . '</a>';
	            if (ContentUpdateScheduler_Options::get('tsu_recursive')) {
	                $actions['copy_to_publish'] = '<a href="' . esc_url($copy_url) . '">' . __('Schedule recursive', 'content-update-scheduler') . '</a>';
	            }
        } elseif ('trash' !== $post->post_status) {
            $actions['copy_to_publish'] = '<a href="' . esc_url($copy_url) . '">' . self::$cus_publish_label . '</a>';
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
	                $new['cus_publish'] = esc_html__('Republication Date', 'content-update-scheduler');
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
            if (!$post instanceof WP_Post) {
                echo '&#8212;';
                return;
            }

            // Scheduled update rows show their own scheduled date.
            if ($post->post_status === self::$_cus_publish_status) {
                $stamp = (int) get_post_meta($post_id, self::$_cus_publish_status . '_pubdate', true);
                if ($stamp > 0) {
                    echo esc_html(self::get_pubdate($stamp));
                } else {
                    echo '&#8212;';
                }
                return;
            }

            // Original rows show the *next* scheduled republication, if any.
            $next = (int) self::get_next_scheduled_pubdate_for_original($post_id);
            if ($next > 0) {
                echo esc_html(self::get_pubdate($next));
            } else {
                echo '&#8212;';
            }
        }
    }

    /**
     * Returns the next scheduled republication timestamp (UTC) for a given original post ID.
     *
     * @param int $original_post_id
     * @return int
     */
    private static function get_next_scheduled_pubdate_for_original($original_post_id)
    {
        static $cache = array();

        $original_post_id = (int) $original_post_id;
        if ($original_post_id <= 0) {
            return 0;
        }

        if (array_key_exists($original_post_id, $cache)) {
            return (int) $cache[$original_post_id];
        }

        // Prime cache for all originals visible on the current admin list page (best-effort).
        $to_prime = array();
        global $wp_query;
        if (isset($wp_query->posts) && is_array($wp_query->posts)) {
            foreach ($wp_query->posts as $p) {
                if (!$p instanceof WP_Post) {
                    continue;
                }
                if ($p->post_status === self::$_cus_publish_status) {
                    continue;
                }
                $pid = (int) $p->ID;
                if ($pid > 0 && !array_key_exists($pid, $cache)) {
                    $to_prime[] = $pid;
                }
            }
        }

        if (empty($to_prime)) {
            $to_prime = array($original_post_id);
        }

        foreach ($to_prime as $pid) {
            $cache[(int) $pid] = 0;
        }

        $now = time();
        $query = new WP_Query(array(
            'post_type'              => 'any',
            'post_status'            => self::$_cus_publish_status,
            'posts_per_page'         => -1,
            'no_found_rows'          => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false,
            'meta_key'               => self::$_cus_publish_status . '_pubdate',
            'orderby'                => 'meta_value_num',
            'order'                  => 'ASC',
            'meta_query'             => array(
                array(
                    'key'     => self::$_cus_publish_status . '_original',
                    'value'   => array_map('intval', $to_prime),
                    'compare' => 'IN',
                ),
                array(
                    'key'     => self::$_cus_publish_status . '_pubdate',
                    'value'   => $now,
                    'compare' => '>',
                    'type'    => 'NUMERIC',
                ),
            ),
        ));

        if (!empty($query->posts) && is_array($query->posts)) {
            foreach ($query->posts as $scheduled_post) {
                if (!$scheduled_post instanceof WP_Post) {
                    continue;
                }
                $orig_id = (int) get_post_meta($scheduled_post->ID, self::$_cus_publish_status . '_original', true);
                if ($orig_id <= 0 || !array_key_exists($orig_id, $cache) || (int) $cache[$orig_id] > 0) {
                    continue;
                }

                $stamp = (int) get_post_meta($scheduled_post->ID, self::$_cus_publish_status . '_pubdate', true);
                if ($stamp > $now) {
                    $cache[$orig_id] = $stamp;
                }
            }
        }

        return (int) ($cache[$original_post_id] ?? 0);
    }

    /**
     * Handles the admin action workflow_copy_to_publish.
     * redirects to post edit screen if successful
     *
     * @return void
     */
    public static function admin_action_workflow_copy_to_publish()
    {
        if (isset($_REQUEST['n'], $_REQUEST['post'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $post_id = absint(wp_unslash($_REQUEST['post'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $nonce = sanitize_text_field(wp_unslash($_REQUEST['n'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

            if (!wp_verify_nonce($nonce, 'workflow_copy_to_publish' . $post_id)) {
                return;
            }

            if (!current_user_can('edit_post', $post_id)) {
                \Infinitnet\ContentUpdateScheduler\Support\AdminNotices::add(
                    'error',
                    __('You do not have permission to edit this content.', 'content-update-scheduler')
                );
                wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url('edit.php'));
                exit;
            }

            $post = get_post($post_id);
            if (!$post) {
                \Infinitnet\ContentUpdateScheduler\Support\AdminNotices::add(
                    'error',
                    __('Post not found.', 'content-update-scheduler')
                );
                wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url('edit.php'));
                exit;
            }

            $publishing_id = self::create_publishing_post($post);
            if (!is_wp_error($publishing_id) && $publishing_id) {
                \Infinitnet\ContentUpdateScheduler\Support\AdminNotices::add(
                    'success',
                    __('Scheduled update created.', 'content-update-scheduler')
                );
                wp_safe_redirect(admin_url('post.php?action=edit&post=' . absint($publishing_id)));
                exit;
            }

            if (is_wp_error($publishing_id)) {
                $error_message = $publishing_id->get_error_message();
            } else {
                $error_message = __('Unable to create scheduled update.', 'content-update-scheduler');
            }

            // translators: %s: error message.
            \Infinitnet\ContentUpdateScheduler\Support\AdminNotices::add(
                'error',
                // translators: %s: error message.
                sprintf(__('Content scheduling failed: %s', 'content-update-scheduler'), $error_message)
            );
            wp_safe_redirect(admin_url('edit.php?post_type=' . $post->post_type));
            exit;
        }
    }

    /**
     * Handles the admin action workflow_publish_now
     *
     * @return void
     */
    public static function admin_action_workflow_publish_now()
    {
        if (isset($_REQUEST['n'], $_REQUEST['post'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $post_id = absint(wp_unslash($_REQUEST['post'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $nonce = sanitize_text_field(wp_unslash($_REQUEST['n'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

            if (!wp_verify_nonce($nonce, 'workflow_publish_now' . $post_id)) {
                return;
            }

            $post = get_post($post_id);
            if (!$post) {
                \Infinitnet\ContentUpdateScheduler\Support\AdminNotices::add(
                    'error',
                    __('Post not found.', 'content-update-scheduler')
                );
                wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url('edit.php'));
                exit;
            }
            
            $post_type_object = get_post_type_object($post->post_type);
            $publish_cap = ($post_type_object && isset($post_type_object->cap->publish_posts)) ? $post_type_object->cap->publish_posts : 'publish_posts';

            // Check if user has permission to publish this post type.
            if (!current_user_can($publish_cap)) {
                \Infinitnet\ContentUpdateScheduler\Support\AdminNotices::add(
                    'error',
                    __('You do not have permission to publish content.', 'content-update-scheduler')
                );
                wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url('edit.php'));
                exit;
            }
            
            $result = self::publish_post($post->ID);
            if (is_wp_error($result)) {
                // translators: %s: error message.
                \Infinitnet\ContentUpdateScheduler\Support\AdminNotices::add(
                    'error',
                    // translators: %s: error message.
                    sprintf(__('Publishing failed: %s', 'content-update-scheduler'), $result->get_error_message())
                );
                wp_safe_redirect(admin_url('edit.php?post_type=' . $post->post_type));
                exit;
            }

            \Infinitnet\ContentUpdateScheduler\Support\AdminNotices::add(
                'success',
                __('Scheduled update published.', 'content-update-scheduler')
            );
            wp_safe_redirect(admin_url('edit.php?post_type=' . $post->post_type));
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

        wp_enqueue_style('wp-admin');
        wp_enqueue_style(
            'content-update-scheduler-metabox',
            plugins_url('assets/metabox.css', CUS_PLUGIN_FILE),
            array(),
            defined('CUS_VERSION') ? CUS_VERSION : null
        );

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
        wp_nonce_field(basename(CUS_PLUGIN_FILE), self::$_cus_publish_status . '_nonce');
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

        $metabox_script_handle = 'content-update-scheduler-metabox';
        wp_enqueue_script(
            $metabox_script_handle,
            plugins_url('assets/metabox.js', CUS_PLUGIN_FILE),
            array('jquery'),
            defined('CUS_VERSION') ? CUS_VERSION : null,
            true
        );
        wp_add_inline_script(
            $metabox_script_handle,
            'window.ContentUpdateSchedulerMetabox = ' . wp_json_encode(
                array(
                    'metaname'             => $metaname,
                    'timezoneOffsetHours'  => wp_timezone()->getOffset(new DateTime('now', new DateTimeZone('UTC'))) / 3600,
                    'timezoneString'       => wp_timezone_string(),
                )
            ) . ';',
            'before'
        );

	        ?>
	        <div class="block-editor-publish-date-time-picker">
	            <p>
	                <strong><?php esc_html_e('Republication Date', 'content-update-scheduler'); ?></strong>
	            </p>
	            <p class="description">
	                <?php esc_html_e('This schedules an UPDATE to existing content. The original post remains published with its current date.', 'content-update-scheduler'); ?>
	            </p>
	            <div class="components-datetime">
	                <div class="components-datetime__date">
	                    <select name="<?php echo esc_attr($metaname); ?>_month" id="<?php echo esc_attr($metaname); ?>_month">
	                        <?php
	                        global $wp_locale;
	                        for ($month_number = 1; $month_number <= 12; $month_number++) {
	                            $month_label = ($wp_locale && method_exists($wp_locale, 'get_month'))
	                                ? $wp_locale->get_month($month_number)
	                                : wp_date('F', mktime(0, 0, 0, $month_number, 1));
	                            echo '<option value="' . esc_attr($month_number) . '"' . selected($month_number, (int) $month, false) . '>' . esc_html($month_label) . '</option>';
	                        }
	                        ?>
	                    </select>
	                    <input type="number" name="<?php echo esc_attr($metaname); ?>_day" id="<?php echo esc_attr($metaname); ?>_day" min="1" max="31" value="<?php echo esc_attr($day); ?>" />
	                    <input type="number" name="<?php echo esc_attr($metaname); ?>_year" id="<?php echo esc_attr($metaname); ?>_year" min="<?php echo esc_attr(wp_date('Y')); ?>" value="<?php echo esc_attr($year); ?>" />
	                </div>
                <div class="components-datetime__time">
                    <input type="time" name="<?php echo esc_attr($metaname); ?>_time" id="<?php echo esc_attr($metaname); ?>_time" value="<?php echo esc_attr($time); ?>" />
                </div>
	            </div>
	            <p>
	                <?php esc_html_e('Please enter time in your site\'s configured timezone', 'content-update-scheduler'); ?>
	            </p>
	            <p class="description" style="margin-bottom: 1em;">
	                <strong><?php esc_html_e('Current WordPress time:', 'content-update-scheduler'); ?></strong>
	                <span id="current-wordpress-time"><?php echo esc_html(wp_date('F j, Y H:i T')); ?></span>
	                <small style="display: block; margin-top: 0.25em; opacity: 0.7;">
	                    <?php esc_html_e('Enter times in your site\'s configured timezone shown above.', 'content-update-scheduler'); ?>
	                </small>
	            </p>
	            <div id="validation-messages">
	                <div id="pastmsg" class="notice notice-warning inline" style="display:none;">
	                    <p>
	                        <?php
	                        echo esc_html__('The release date is in the past.', 'content-update-scheduler');
	                        echo esc_html__(' This post will be published 5 minutes from now.', 'content-update-scheduler');
	                        ?>
	                    </p>
	                </div>
	                <div id="invalidmsg" class="notice notice-error inline" style="display:none;">
	                    <p><?php esc_html_e('Please enter a valid date and time.', 'content-update-scheduler'); ?></p>
	                </div>
	                <div id="successmsg" class="notice notice-success inline" style="display:none;">
	                    <p><?php esc_html_e('Valid scheduling date selected.', 'content-update-scheduler'); ?></p>
	                </div>
	            </div>
	            <div class="misc-pub-section">
	                <label>
                    <input type="checkbox" 
                           name="<?php echo esc_attr(self::$_cus_publish_status); ?>_keep_dates" 
	                           id="<?php echo esc_attr(self::$_cus_publish_status); ?>_keep_dates"
	                           <?php checked(get_post_meta($post->ID, self::$_cus_publish_status . '_keep_dates', true), 'yes'); ?>>
	                    <?php esc_html_e('Keep original publication date', 'content-update-scheduler'); ?>
	                </label>
	                <p class="description">
	                    <?php esc_html_e('If checked, the original publication date will be preserved when this update is published.', 'content-update-scheduler'); ?>
	                </p>
	            </div>
	        </div>
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

        if (empty($tzstring)) { // Create a valid offset timezone if no timezone string exists.
            // Convert WordPress GMT offset to valid timezone offset format
            $hours = intval($current_offset);
            $minutes = abs(($current_offset - $hours) * 60);
            $tzstring = sprintf('%+03d:%02d', $hours, $minutes);
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

            // Protect Unicode escape sequences during status change
            $content_needs_protection = strpos($post->post_content, '\u') !== false;
            $excerpt_needs_protection = strpos($post->post_excerpt, '\u') !== false;
            
            if ($content_needs_protection) {
                $post->post_content = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function($matches) {
                    return '___UNICODE_' . $matches[1] . '___';
                }, $post->post_content);
            }
            
            if ($excerpt_needs_protection) {
                $post->post_excerpt = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function($matches) {
                    return '___UNICODE_' . $matches[1] . '___';
                }, $post->post_excerpt);
            }

            $post->post_status = self::$_cus_publish_status;
            $result = wp_update_post($post, true);
            
            // Restore Unicode escapes if they were protected
            if (!is_wp_error($result) && ($content_needs_protection || $excerpt_needs_protection)) {
                $update_data = array();
                
                if ($content_needs_protection) {
                    $restored_content = preg_replace_callback('/___UNICODE_([0-9a-fA-F]{4})___/', function($matches) {
                        return '\\u' . $matches[1];
                    }, $post->post_content);
                    $update_data['post_content'] = $restored_content;
                }
                
                if ($excerpt_needs_protection) {
                    $restored_excerpt = preg_replace_callback('/___UNICODE_([0-9a-fA-F]{4})___/', function($matches) {
                        return '\\u' . $matches[1];
                    }, $post->post_excerpt);
                    $update_data['post_excerpt'] = $restored_excerpt;
                }
                
                if (!empty($update_data)) {
                    global $wpdb;
                    $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                        $wpdb->posts,
                        $update_data,
                        array('ID' => $result),
                        array_fill(0, count($update_data), '%s'),
                        array('%d')
                    );
                    clean_post_cache($result);
                }
            }

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
        
        $new_author_id = (int) $post->post_author;
        if ($new_author_id > 0 && !get_user_by('id', $new_author_id)) {
            $new_author_id = 0;
        }
        if ($new_author_id <= 0) {
            $new_author_id = (int) get_current_user_id();
        }

        // Protect Unicode escape sequences in content and excerpt before copying
        $protected_content = $post->post_content;
        $protected_excerpt = $post->post_excerpt;
        
        if (strpos($protected_content, '\u') !== false) {
            $protected_content = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function($matches) {
                return '___UNICODE_' . $matches[1] . '___';
            }, $protected_content);
        }
        
        if (strpos($protected_excerpt, '\u') !== false) {
            $protected_excerpt = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function($matches) {
                return '___UNICODE_' . $matches[1] . '___';
            }, $protected_excerpt);
        }

        // create the new post.
        $new_post = array(
            'menu_order'     => $post->menu_order,
            'comment_status' => $post->comment_status,
            'ping_status'    => $post->ping_status,
            'post_author'    => $new_author_id,
            'post_content'   => $protected_content,
            'post_excerpt'   => $protected_excerpt,
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

        // Restore Unicode escape sequences if they were protected
        $need_content_restore = strpos($protected_content, '___UNICODE_') !== false;
        $need_excerpt_restore = strpos($protected_excerpt, '___UNICODE_') !== false;
        
        if ($need_content_restore || $need_excerpt_restore) {
            $update_data = array();
            
            if ($need_content_restore) {
                $restored_content = preg_replace_callback('/___UNICODE_([0-9a-fA-F]{4})___/', function($matches) {
                    return '\\u' . $matches[1];
                }, $protected_content);
                $update_data['post_content'] = $restored_content;
            }
            
            if ($need_excerpt_restore) {
                $restored_excerpt = preg_replace_callback('/___UNICODE_([0-9a-fA-F]{4})___/', function($matches) {
                    return '\\u' . $matches[1];
                }, $protected_excerpt);
                $update_data['post_excerpt'] = $restored_excerpt;
            }
            
            // Update content/excerpt directly to bypass filters that might corrupt it
            global $wpdb;
            $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $wpdb->posts,
                $update_data,
                array('ID' => $new_post_id),
                array_fill(0, count($update_data), '%s'),
                array('%d')
            );
            
            // Clear any caches
            clean_post_cache($new_post_id);
        }

        self::copy_elementor_data($post->ID, $new_post_id);
        self::copy_oxygen_data($post->ID, $new_post_id);
        self::handle_wpml_relationships($post->ID, $new_post_id);

        // copy meta and terms over to the new post.
        self::copy_meta_and_terms($post->ID, $new_post_id);

        // and finally referencing the original post.
        update_post_meta($new_post_id, self::$_cus_publish_status . '_original', $original);
        
        // Ensure the keep_dates setting is not copied from previous scheduled updates
        delete_post_meta($new_post_id, self::$_cus_publish_status . '_keep_dates');
        // Ensure a recursive copy doesn't inherit an existing schedule timestamp.
        delete_post_meta($new_post_id, self::$_cus_publish_status . '_pubdate');
        wp_clear_scheduled_hook('cus_publish_post', array((int) $new_post_id));

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
    /**
     * Helper method to properly copy meta values while preserving their format
     * 
     * @param mixed $value The meta value to process
     * @return mixed The processed meta value
     */
    private static function copy_meta_value($value) {
        // If the value is serialized, handle it carefully
        if (is_serialized($value)) {
            $unserialized = maybe_unserialize($value);
            if ($unserialized === false) {
                return $value; // Return original if unserialization fails
            }
            return $unserialized;
        }

        // Only treat as JSON if it's actually structured JSON data, not just any string with braces
        if (is_string($value) && strlen($value) > 2) {
            // Must start and end with proper JSON delimiters
            if ((substr($value, 0, 1) === '{' && substr($value, -1) === '}') ||
                (substr($value, 0, 1) === '[' && substr($value, -1) === ']')) {
                
                $json_decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Additional validation: must decode to array or object (not string/number)
                    // This prevents treating HTML/CSS with braces as JSON
                    if (is_array($json_decoded) || is_object($json_decoded)) {
                        return $value;
                    }
                }
            }
        }

        return $value;
    }

    /**
     * Copies meta and terms from one post to another
     *
     * @param int  $source_post_id      The post from which to copy.
     * @param int  $destination_post_id The post which will get the meta and terms.
     * @param bool $restore_references  Whether to restore references to the original post ID.
     *
     * @return void
     */
    private static function copy_meta_and_terms($source_post_id, $destination_post_id, $restore_references = false) 
    {
        $source_post = get_post($source_post_id);
        $destination_post = get_post($destination_post_id);

        // Abort if any of the ids is not a post.
        if (!$source_post || !$destination_post) {
            return;
        }

        // Store current kses status and temporarily disable filters.
        $should_filter = ! current_filter('content_save_pre');
        if ($should_filter) {
            remove_filter('content_save_pre', 'wp_filter_post_kses');
            remove_filter('db_insert_value', 'wp_filter_kses');
        }

        try {
            // Copy meta.
            $meta = get_post_meta($source_post->ID);
            foreach ($meta as $key => $values) {
                // Skip Elementor meta keys - they're handled separately in copy_elementor_data()
                if (strpos($key, '_elementor') === 0) {
                    continue;
                }
                
                delete_post_meta($destination_post->ID, $key);
                foreach ($values as $value) {
                    $processed_value = self::copy_meta_value($value);
                    
                    if ($restore_references && is_string($processed_value) &&
                        strpos($processed_value, (string)$source_post->ID) !== false) {
                        $processed_value = str_replace(
                            (string)$source_post->ID,
                            (string)$destination_post->ID,
                            $processed_value
                        );
                    }
                    
                    add_post_meta($destination_post->ID, $key, $processed_value);
                }
            }

            // Copy terms.
            $taxonomies = get_object_taxonomies($source_post->post_type);
            foreach ($taxonomies as $taxonomy) {
                $post_terms = wp_get_object_terms($source_post->ID, $taxonomy, array(
                    'orderby' => 'term_order',
                ));
                $terms = array();
                foreach ($post_terms as $term) {
                    $terms[] = $term->slug;
                }
                wp_set_object_terms($destination_post->ID, null, $taxonomy);
                wp_set_object_terms($destination_post->ID, $terms, $taxonomy);
            }
        } finally {
            // Restore filters if they were active.
            if ($should_filter) {
                add_filter('content_save_pre', 'wp_filter_post_kses');
                add_filter('db_insert_value', 'wp_filter_kses');
            }
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
        
        if ($post->post_status === self::$_cus_publish_status || get_post_meta($post_id, self::$_cus_publish_status . '_original', true)) {
            $nonce = ContentUpdateScheduler::$_cus_publish_status . '_nonce';
            $pub = ContentUpdateScheduler::$_cus_publish_status . '_pubdate';

            
            if (!isset($_POST[$nonce]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonce])), basename(CUS_PLUGIN_FILE))) {
                return $post_id;
            }
            $type_obj = get_post_type_object($post->post_type);
            $edit_cap = ($type_obj && isset($type_obj->cap->edit_post)) ? $type_obj->cap->edit_post : 'edit_post';
            if (!current_user_can($edit_cap, $post_id)) {
                return $post_id;
            }


            if (isset($_POST[$pub . '_month'], $_POST[$pub . '_day'], $_POST[$pub . '_year'], $_POST[$pub . '_time'])) {
                $month = intval($_POST[$pub . '_month']);
                $day = intval($_POST[$pub . '_day']);
                $year = intval($_POST[$pub . '_year']);
                $time = sanitize_text_field(wp_unslash($_POST[$pub . '_time']));

                // Convert form data to timestamp using WordPress timezone
                $date_string = sprintf('%04d-%02d-%02d %s', $year, $month, $day, $time);
                try {
                    $tz = wp_timezone();
                    $date_time = DateTime::createFromFormat('Y-m-d H:i', $date_string, $tz);
                    if ($date_time === false) {
                        return $post_id; // Invalid date format
                    }
                    // Convert to UTC timestamp for storage
                    $date_time->setTimezone(new DateTimeZone('UTC'));
                    $stamp = $date_time->getTimestamp();
                } catch (Exception $e) {
                    return $post_id; // Error creating timestamp
                }

                // If a past timestamp is selected, schedule for 5 minutes from now (UTC).
                $now = time();
                if ($stamp <= $now) {
                    $stamp = $now + 300;
                }

                wp_clear_scheduled_hook('cus_publish_post', array($post_id));
                update_post_meta($post_id, $pub, $stamp);
                $scheduled = wp_schedule_single_event($stamp, 'cus_publish_post', array($post_id));
                
                // Save keep_dates preference
                $keep_dates_key = self::$_cus_publish_status . '_keep_dates';
                if (isset($_POST[$keep_dates_key])) {
                    update_post_meta($post_id, $keep_dates_key, 'yes');
                } else {
                    delete_post_meta($post_id, $keep_dates_key);
                }

                // Verify the event was scheduled
                $next_scheduled = wp_next_scheduled('cus_publish_post', array($post_id));

                // Check all scheduled events
                self::check_scheduled_events();
            } else {
            }

            // Check if the post being saved is a republication draft
            $original_post_id = get_post_meta($post_id, self::$_cus_publish_status . '_original', true);
            if ($original_post_id) {
                // Ensure the original post's stock status and quantity are maintained
                $original_stock_status = get_post_meta($original_post_id, self::META_STOCK_STATUS, true);
                $original_stock_quantity = get_post_meta($original_post_id, self::META_STOCK_QUANTITY, true);

                if ($original_stock_status !== '') {
                    update_post_meta($post_id, self::META_STOCK_STATUS, $original_stock_status);
                }
                if ($original_stock_quantity !== '') {
                    update_post_meta($post_id, self::META_STOCK_QUANTITY, $original_stock_quantity);
                }
            }
        } else {
        }
    }

    /**
     * Publishes a scheduled update
     *
     * Copies the original post's contents and meta into its "scheduled update" and then deletes
     * the scheduled post. This function is either called by wp_cron or if the user hits the
     * 'publish now' action
     *
     * @param int $post_id the post's id.
     *
     * @return int|WP_Error the original post's id or WP_Error on failure
     */
    public static function publish_post($post_id)
    {
        // Implement locking mechanism.
        $lock_key = 'cus_publish_lock_' . $post_id;
        if (get_transient($lock_key)) {
            return new WP_Error('locked', 'Publish process already running for this post');
        }
        set_transient($lock_key, true, 300); // Lock for 5 minutes.

        $rollback = null;

        try {
            $post_id = (int) $post_id;
            $orig_id = (int) get_post_meta($post_id, self::$_cus_publish_status . '_original', true);

            // Break early if given post is not an actual scheduled post created by this plugin.
            if (!$orig_id) {
                return new WP_Error('no_original', 'No original post found');
            }

            $orig = get_post($orig_id);
            if (!$orig) {
                return new WP_Error('original_not_found', 'Original post not found');
            }

            $post = get_post($post_id);
            if (!$post) {
                return new WP_Error('scheduled_not_found', 'Scheduled post not found');
            }

            // Ensure the post is not in the trash before proceeding.
            if ($post->post_status === 'trash') {
                return new WP_Error('post_trashed', 'Post is in trash');
            }

            /**
             * Fires before a scheduled post is being updated.
             *
             * @param WP_Post $post The scheduled update post.
             * @param WP_post $orig The original post.
             */
            do_action('ContentUpdateScheduler\\before_publish_post', $post, $orig);

            // Snapshot original state for rollback.
            $orig_post_before = get_post($orig_id, ARRAY_A);
            $taxonomies = get_object_taxonomies($orig->post_type);
            $orig_terms_before = array();
            foreach ($taxonomies as $taxonomy) {
                $orig_terms_before[$taxonomy] = wp_get_object_terms($orig_id, $taxonomy, array('fields' => 'ids'));
            }

            // Snapshot WPML language details for rollback (best-effort).
            $wpml_language_before = null;
            if (defined('ICL_SITEPRESS_VERSION')) {
                $post_type = get_post_type($orig_id);
                if ($post_type) {
                    $element_type = 'post_' . $post_type;
                    $details = apply_filters('wpml_element_language_details', null, array( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
                        'element_id'   => $orig_id,
                        'element_type' => $element_type,
                    ));

                    if ($details) {
                        $wpml_language_before = array(
                            'element_type'         => $element_type,
                            'trid'                 => apply_filters('wpml_element_trid', null, $orig_id, $element_type), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
                            'language_code'        => isset($details->language_code) ? $details->language_code : null,
                            'source_language_code' => isset($details->source_language_code) ? $details->source_language_code : null,
                        );
                    }
                }
            }

            $source_meta = get_post_meta($post->ID);
            $meta_keys = is_array($source_meta) ? array_keys($source_meta) : array();

            $elementor_meta_keys = array(
                self::META_ELEMENTOR_DATA,
                self::META_ELEMENTOR_EDIT_MODE,
                self::META_ELEMENTOR_PAGE_SETTINGS,
                self::META_ELEMENTOR_VERSION,
                self::META_ELEMENTOR_TEMPLATE_TYPE,
                self::META_ELEMENTOR_CONTROLS_USAGE,
                '_elementor_css',
            );

            $wpml_meta_keys = array(
                self::META_WPML_MEDIA_FEATURED,
                self::META_WPML_MEDIA_DUPLICATE,
                self::META_WPML_MEDIA_PROCESSED,
            );

            $meta_keys = array_values(array_unique(array_merge(
                $meta_keys,
                $elementor_meta_keys,
                $wpml_meta_keys,
                array(self::$_cus_publish_status . '_pubdate')
            )));

            $orig_meta_before = array();
            foreach ($meta_keys as $key) {
                $orig_meta_before[$key] = get_post_meta($orig_id, $key, false);
            }

            // Snapshot builder CSS files (best-effort).
            $upload_dir = wp_upload_dir();
            $css_snapshots = array();
            $elementor_css_path = $upload_dir['basedir'] . '/elementor/css/post-' . $orig_id . '.css';
            $css_snapshots['elementor'] = array(
                'path'    => $elementor_css_path,
                'exists'  => file_exists($elementor_css_path),
                'content' => file_exists($elementor_css_path) ? @file_get_contents($elementor_css_path) : null,
            );

            $oxygen_css_path = $upload_dir['basedir'] . '/oxygen/css/' . get_post_field('post_name', $orig_id) . '-' . $orig_id . '.css';
            $css_snapshots['oxygen'] = array(
                'path'    => $oxygen_css_path,
                'exists'  => file_exists($oxygen_css_path),
                'content' => file_exists($oxygen_css_path) ? @file_get_contents($oxygen_css_path) : null,
            );

            $rollback = function ($error) use ($orig_id, $orig_post_before, $orig_terms_before, $orig_meta_before, $css_snapshots, $wpml_language_before) {
                if (!is_wp_error($error)) {
                    $error = new WP_Error('publish_failed', 'Publish failed');
                }

                if (is_array($orig_post_before)) {
                    wp_update_post($orig_post_before, true);
                }

                foreach ($orig_meta_before as $meta_key => $values) {
                    delete_post_meta($orig_id, $meta_key);
                    if (is_array($values)) {
                        foreach ($values as $value) {
                            add_post_meta($orig_id, $meta_key, $value);
                        }
                    }
                }

                foreach ($orig_terms_before as $taxonomy => $ids) {
                    wp_set_object_terms($orig_id, array_map('intval', (array) $ids), $taxonomy, false);
                }

                // Restore WPML language details if available.
                if (defined('ICL_SITEPRESS_VERSION') && is_array($wpml_language_before) && !empty($wpml_language_before['element_type'])) {
                    do_action('wpml_set_element_language_details', array( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
                        'element_id'           => $orig_id,
                        'element_type'         => $wpml_language_before['element_type'],
                        'trid'                 => $wpml_language_before['trid'],
                        'language_code'        => $wpml_language_before['language_code'],
                        'source_language_code' => $wpml_language_before['source_language_code'],
                    ));
                }

                foreach ($css_snapshots as $snapshot) {
                    if (!is_array($snapshot) || empty($snapshot['path'])) {
                        continue;
                    }

                    $path = $snapshot['path'];
                    if (!empty($snapshot['exists'])) {
                        @file_put_contents($path, (string) $snapshot['content']);
                    } elseif (file_exists($path)) {
                        if (function_exists('wp_delete_file')) {
                            wp_delete_file($path);
                        }
                    }
                }

                clean_post_cache($orig_id);
                return $error;
            };

            // Preserve stock fields on the original product.
            $original_stock_status = get_post_meta($orig->ID, self::META_STOCK_STATUS, true);
            $original_stock_quantity = get_post_meta($orig->ID, self::META_STOCK_QUANTITY, true);

            // Copy meta and terms, restoring references to the original post ID.
            delete_post_meta($orig->ID, self::$_cus_publish_status . '_pubdate');
            self::copy_meta_and_terms($post->ID, $orig->ID, true);
            // Ensure internal scheduling meta never persists on the original post.
            delete_post_meta($orig->ID, self::$_cus_publish_status . '_original');
            delete_post_meta($orig->ID, self::$_cus_publish_status . '_pubdate');
            delete_post_meta($orig->ID, self::$_cus_publish_status . '_keep_dates');

            // Restore stock on the original product after meta copy.
            if ($original_stock_status !== '') {
                update_post_meta($orig->ID, self::META_STOCK_STATUS, $original_stock_status);
            }
            if ($original_stock_quantity !== '') {
                update_post_meta($orig->ID, self::META_STOCK_QUANTITY, $original_stock_quantity);
            }

            // Apply scheduled content back onto the original post.
            $post->ID = $orig->ID;
            $post->post_name = $orig->post_name;
            $post->guid = $orig->guid;
            $post->post_parent = $orig->post_parent;
            $post->post_status = $orig->post_status;

            $keep_dates = get_post_meta($post_id, self::$_cus_publish_status . '_keep_dates', true) === 'yes';

            if ($keep_dates) {
                // Keep original dates but update modified date.
                $post->post_date = $orig->post_date;
                $post->post_date_gmt = $orig->post_date_gmt;
                $post->post_modified = wp_date('Y-m-d H:i:s');
                $post->post_modified_gmt = get_gmt_from_date($post->post_modified);
            } else {
                // Use new dates.
                $post_date = wp_date('Y-m-d H:i:s');

                /**
                 * Filter the new posts' post date.
                 *
                 * @param string  $post_date The date to be used, must be in the form of `Y-m-d H:i:s`.
                 * @param WP_Post $post      The scheduled update post.
                 * @param WP_Post $orig      The original post.
                 */
                $post_date = apply_filters('ContentUpdateScheduler\\publish_post_date', $post_date, $post, $orig);

                $post->post_date = $post_date;
                $post->post_date_gmt = get_gmt_from_date($post_date);
                $post->post_modified = $post_date;
                $post->post_modified_gmt = $post->post_date_gmt;
            }

            // Prevent Unicode escape sequence corruption in content and excerpt.
            $content_has_unicode = strpos($post->post_content, '\u') !== false;
            $excerpt_has_unicode = strpos($post->post_excerpt, '\u') !== false;

            if ($content_has_unicode) {
                $post->post_content = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($matches) {
                    return '___UNICODE_' . $matches[1] . '___';
                }, $post->post_content);
            }

            if ($excerpt_has_unicode) {
                $post->post_excerpt = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($matches) {
                    return '___UNICODE_' . $matches[1] . '___';
                }, $post->post_excerpt);
            }

            $result = wp_update_post($post, true);
            if (is_wp_error($result)) {
                return $rollback ? $rollback($result) : $result;
            }

            // Restore Unicode escapes if they were protected.
            if ($content_has_unicode || $excerpt_has_unicode) {
                $updated_post = get_post($result);
                if ($updated_post) {
                    $update_data = array();

                    if ($content_has_unicode && strpos($updated_post->post_content, '___UNICODE_') !== false) {
                        $update_data['post_content'] = preg_replace_callback('/___UNICODE_([0-9a-fA-F]{4})___/', function ($matches) {
                            return '\\u' . $matches[1];
                        }, $updated_post->post_content);
                    }

                    if ($excerpt_has_unicode && strpos($updated_post->post_excerpt, '___UNICODE_') !== false) {
                        $update_data['post_excerpt'] = preg_replace_callback('/___UNICODE_([0-9a-fA-F]{4})___/', function ($matches) {
                            return '\\u' . $matches[1];
                        }, $updated_post->post_excerpt);
                    }

                    if (!empty($update_data)) {
                        global $wpdb;
                        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                            $wpdb->posts,
                            $update_data,
                            array('ID' => $result),
                            array_fill(0, count($update_data), '%s'),
                            array('%d')
                        );
                        clean_post_cache($result);
                    }
                }
            }

            // Copy builder and WPML data back onto the original (meta copy excludes Elementor keys).
            self::copy_elementor_data($post_id, $orig_id);
            self::copy_oxygen_data($post_id, $orig_id);
            self::handle_wpml_relationships($post_id, $orig_id, true);

            $deleted = wp_delete_post($post_id, true);
            if (!$deleted || is_wp_error($deleted)) {
                $delete_error = is_wp_error($deleted) ? $deleted : new WP_Error('delete_failed', 'Failed to delete scheduled update');
                return $rollback ? $rollback($delete_error) : $delete_error;
            }

            // Clear the cron event after successful publishing.
            wp_clear_scheduled_hook('cus_publish_post', array($post_id));

            /**
             * Fires after a scheduled post has been successfully published.
             *
             * @param WP_Post $post The scheduled update post.
             * @param WP_post $orig The original post.
             */
            do_action('ContentUpdateScheduler\\after_publish_post', $post, $orig);

            return $orig->ID;
        } catch (Exception $e) {
            $error = new WP_Error('publish_exception', $e->getMessage());
            return $rollback ? $rollback($error) : $error;
        } finally {
            delete_transient($lock_key);
        }
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
        $post = get_post($ID);
        if (!$post) {
            return;
        }

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
	        // Validate timestamp
	        if (empty($stamp) || !is_numeric($stamp) || $stamp <= 0) {
	            return __('Invalid date', 'content-update-scheduler');
	        }
        
        try {
            $date = new DateTime('@' . $stamp);
            $date->setTimezone(wp_timezone());
            return $date->format(get_option('date_format') . ' ' . get_option('time_format'));
	        } catch (Exception $e) {
	            return __('Invalid date', 'content-update-scheduler');
	        }
	    }

    /* bhullar custom code */
    public static function override_static_front_page_and_post_option($html, $arg)
    {
        // Only modify dropdowns for homepage/posts page settings
        if (!isset($arg['name']) || !in_array($arg['name'], array('page_on_front', 'page_for_posts'), true)) {
            return $html;
        }
        
        $arg['post_status'] = array('publish', 'draft','future','cus_sc_publish');
        return self::co_wp_dropdown_pages($arg);
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
                    $output .= "\t<option value=\"-1\">" . esc_html($parsed_args['show_option_no_change']) . "</option>\n";
                }
                if ($parsed_args['show_option_none']) {
                    $output .= "\t<option value=\"" . esc_attr($parsed_args['option_none_value']) . '">' . esc_html($parsed_args['show_option_none']) . "</option>\n";
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
        $html = apply_filters('content_update_scheduler_wp_dropdown_pages', $output, $parsed_args, $pages);
        // Back-compat: legacy filter name.
        $html = apply_filters('co_wp_dropdown_pages', $html, $parsed_args, $pages); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

        if ($parsed_args['echo']) {
            echo wp_kses($html, array(
                'select' => array(
                    'name'  => true,
                    'id'    => true,
                    'class' => true,
                ),
                'option' => array(
                    'value'    => true,
                    'selected' => true,
                    'class'    => true,
                ),
            ));
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
            if (!empty($cus_sc_publish_pubdate) && (int) $cus_sc_publish_pubdate > time() && empty($original_post_id)) {
                global $wp_query;
                $wp_query->set_404();
                status_header(404);
                get_template_part(404);
                exit();
            }
        }
    }

    public static function check_scheduled_events() {
        if (!function_exists('_get_cron_array')) {
            return;
        }

        $cron = _get_cron_array();
        if (!is_array($cron)) {
            return;
        }

        // Intentionally no-op: this method exists for legacy debugging hooks.
    }

    public static function check_and_publish_overdue_posts() {
        // Stored timestamps are UTC seconds.
        $current_timestamp = time();

        $query = new WP_Query(array(
            'post_type'              => 'any',
            'post_status'            => self::$_cus_publish_status,
            'posts_per_page'         => 50,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'meta_query'             => array(
                array(
                    'key'     => self::$_cus_publish_status . '_pubdate',
                    'value'   => (int) $current_timestamp,
                    'compare' => '<=',
                    'type'    => 'NUMERIC',
                ),
            ),
        ));

        if (empty($query->posts) || !is_array($query->posts)) {
            return;
        }

        foreach ($query->posts as $post_id) {
            self::cron_publish_post((int) $post_id);
        }
    }

    /**
     * Initialize homepage scheduling functionality
     */
    public static function init_homepage_scheduling() {
        if (is_admin()) {
            add_action('admin_menu', array(__CLASS__, 'add_homepage_scheduling_page'));
            add_action('wp_ajax_schedule_homepage_change', array(__CLASS__, 'handle_homepage_scheduling'));
            add_action('wp_ajax_cancel_homepage_change', array(__CLASS__, 'handle_cancel_homepage_change'));
        }
        add_action('cus_change_homepage', array(__CLASS__, 'cron_change_homepage'));
    }

    /**
     * Add homepage scheduling admin page
     */
    public static function add_homepage_scheduling_page() {
        add_submenu_page(
            'options-general.php',
            __('Schedule Homepage Changes', 'content-update-scheduler'),
            __('Schedule Homepage', 'content-update-scheduler'),
            'manage_options',
            'schedule-homepage',
            array(__CLASS__, 'homepage_scheduling_page')
        );
    }

    /**
     * Render homepage scheduling page
     */
    public static function homepage_scheduling_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $homepage_script_handle = 'content-update-scheduler-homepage-scheduler';
        wp_enqueue_script(
            $homepage_script_handle,
            plugins_url('assets/homepage-scheduler.js', CUS_PLUGIN_FILE),
            array('jquery'),
            defined('CUS_VERSION') ? CUS_VERSION : null,
            true
        );
        wp_add_inline_script(
            $homepage_script_handle,
            'window.ContentUpdateSchedulerHomepageScheduler = ' . wp_json_encode(
                array(
                    'confirmCancel'        => __('Are you sure you want to cancel this scheduled homepage change?', 'content-update-scheduler'),
                    'errorMissingAjaxUrl'  => __('AJAX endpoint missing.', 'content-update-scheduler'),
                    'errorMissingNonce'    => __('Security token missing. Please reload the page.', 'content-update-scheduler'),
                    'errorRequestFailed'   => __('Request failed. Please check your connection and try again.', 'content-update-scheduler'),
                    'errorUnknown'         => __('Unknown error', 'content-update-scheduler'),
                    'scheduledSuccess'     => __('Homepage change scheduled.', 'content-update-scheduler'),
                    'canceledSuccess'      => __('Homepage change canceled.', 'content-update-scheduler'),
                )
            ) . ';',
            'before'
        );

        // Get available pages for homepage
        $pages = get_pages(array(
            'post_status' => array('publish', 'cus_sc_publish')
        ));

        // Get scheduled homepage changes
        $scheduled_changes = self::get_scheduled_homepage_changes();

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div id="cus-homepage-notices"></div>
            
            <div class="card">
                <h2><?php esc_html_e('Schedule New Homepage Change', 'content-update-scheduler'); ?></h2>
                <form id="schedule-homepage-form">
                    <?php wp_nonce_field('schedule_homepage_change', 'homepage_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="new_homepage"><?php esc_html_e('New Homepage', 'content-update-scheduler'); ?></label>
                            </th>
                            <td>
                                <select name="new_homepage" id="new_homepage" required>
                                    <option value=""><?php esc_html_e('Select a page...', 'content-update-scheduler'); ?></option>
                                    <?php foreach ($pages as $page): ?>
                                        <option value="<?php echo esc_attr($page->ID); ?>">
                                            <?php echo esc_html($page->post_title); ?>
                                            <?php if ($page->post_status === 'cus_sc_publish'): ?>
                                                (<?php esc_html_e('Scheduled Update', 'content-update-scheduler'); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="schedule_date"><?php esc_html_e('Schedule Date', 'content-update-scheduler'); ?></label>
                            </th>
                            <td>
                                <input type="date" name="schedule_date" id="schedule_date" required>
                                <input type="time" name="schedule_time" id="schedule_time" required>
                                <p class="description"><?php esc_html_e('Date and time when the homepage should change', 'content-update-scheduler'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Schedule Homepage Change', 'content-update-scheduler'); ?></button>
                        <span class="spinner" style="float:none;"></span>
                    </p>
                </form>
            </div>

            <?php if (!empty($scheduled_changes)): ?>
            <div class="card">
                <h2><?php esc_html_e('Scheduled Homepage Changes', 'content-update-scheduler'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('New Homepage', 'content-update-scheduler'); ?></th>
                            <th><?php esc_html_e('Scheduled Date', 'content-update-scheduler'); ?></th>
                            <th><?php esc_html_e('Actions', 'content-update-scheduler'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scheduled_changes as $change): ?>
                        <tr>
                            <td><?php echo esc_html(get_the_title($change['page_id'])); ?></td>
                            <td><?php echo esc_html(self::get_pubdate($change['timestamp'])); ?></td>
                            <td>
	                                <a href="#" class="button button-small cancel-homepage-change" 
	                                   data-timestamp="<?php echo esc_attr($change['timestamp']); ?>"
	                                   data-page-id="<?php echo esc_attr($change['page_id']); ?>">
	                                    <?php esc_html_e('Cancel', 'content-update-scheduler'); ?>
	                                </a>
	                            </td>
	                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Handle AJAX request for scheduling homepage changes
     */
    public static function handle_homepage_scheduling() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        if (!check_ajax_referer('schedule_homepage_change', 'homepage_nonce', false)) {
            wp_send_json_error('Invalid nonce');
        }

        $page_id = isset($_POST['page_id']) ? absint(wp_unslash($_POST['page_id'])) : 0;
        $schedule_date = isset($_POST['schedule_date']) ? sanitize_text_field(wp_unslash($_POST['schedule_date'])) : '';
        $schedule_time = isset($_POST['schedule_time']) ? sanitize_text_field(wp_unslash($_POST['schedule_time'])) : '';

        if (empty($page_id) || empty($schedule_date) || empty($schedule_time)) {
            wp_send_json_error('Missing required fields');
        }

        $page = get_post($page_id);
        if (!$page || $page->post_type !== 'page') {
            wp_send_json_error('Invalid page');
        }

        // Convert to timestamp using WordPress timezone
        $tz = wp_timezone();
        $date_string = $schedule_date . ' ' . $schedule_time;
        $date_time = DateTime::createFromFormat('Y-m-d H:i', $date_string, $tz);

        if ($date_time === false) {
            wp_send_json_error('Invalid date format');
        }

        // Convert to UTC for scheduling
        $date_time->setTimezone(new DateTimeZone('UTC'));
        $timestamp = $date_time->getTimestamp();

        // Normalize past timestamps to a few minutes from now (UTC), mirroring scheduled-update behavior.
        $now = time();
        if ($timestamp <= $now) {
            $timestamp = $now + 300;
        }

        // Schedule the homepage change
        $scheduled = wp_schedule_single_event($timestamp, 'cus_change_homepage', array($page_id, $timestamp));
        
        if ($scheduled === false) {
            wp_send_json_error('Failed to schedule homepage change');
        }

        // Store the scheduled change in options for display
        $scheduled_changes = get_option('cus_scheduled_homepage_changes', array());
        $scheduled_changes[] = array(
            'page_id' => $page_id,
            'timestamp' => $timestamp,
            'scheduled_at' => time()
        );
        update_option('cus_scheduled_homepage_changes', $scheduled_changes);

        wp_send_json_success('Homepage change scheduled successfully');
    }

    /**
     * Handle AJAX request for canceling homepage changes
     */
    public static function handle_cancel_homepage_change() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        if (!check_ajax_referer('schedule_homepage_change', 'homepage_nonce', false)) {
            wp_send_json_error('Invalid nonce');
        }

        $timestamp = isset($_POST['timestamp']) ? absint(wp_unslash($_POST['timestamp'])) : 0;
        $page_id = isset($_POST['page_id']) ? absint(wp_unslash($_POST['page_id'])) : 0;

        if ($timestamp <= 0 || $page_id <= 0) {
            wp_send_json_error('Missing required fields');
        }

        // Remove only the matching cron instance (do not cancel other pending homepage changes).
        // Use `wp_clear_scheduled_hook()` so it still works if the event was "restored" and rescheduled
        // to a different runtime (e.g. overdue recovery).
        wp_clear_scheduled_hook('cus_change_homepage', array($page_id, $timestamp));

        // Back-compat: older installs may have scheduled events with args = array($page_id) at the same timestamp.
        wp_unschedule_event($timestamp, 'cus_change_homepage', array($page_id));

        // Remove from our stored changes
        $scheduled_changes = get_option('cus_scheduled_homepage_changes', array());
        $scheduled_changes = array_filter($scheduled_changes, function($change) use ($timestamp, $page_id) {
            return !($change['timestamp'] == $timestamp && $change['page_id'] == $page_id);
        });
        update_option('cus_scheduled_homepage_changes', $scheduled_changes);

        wp_send_json_success('Homepage change canceled successfully');
    }

    /**
     * Get scheduled homepage changes
     */
    public static function get_scheduled_homepage_changes() {
        $scheduled_changes = get_option('cus_scheduled_homepage_changes', array());
        if (!is_array($scheduled_changes)) {
            return array();
        }

        // Display-only: do not delete overdue items here (WP-Cron might have missed them).
        usort($scheduled_changes, function ($a, $b) {
            $at = isset($a['timestamp']) ? (int) $a['timestamp'] : 0;
            $bt = isset($b['timestamp']) ? (int) $b['timestamp'] : 0;
            return $at <=> $bt;
        });

        return $scheduled_changes;
    }

    /**
     * Cron job to change homepage
     */
    public static function cron_change_homepage($page_id, $timestamp = null) {
        
        // Verify the page exists and is published
        $page = get_post($page_id);
        if (!$page || $page->post_type !== 'page') {
            return;
        }

        // Allow scheduled-status pages too (consistent with dropdown behavior).
        if (!in_array($page->post_status, array('publish', self::$_cus_publish_status), true)) {
            return;
        }

        // Update the homepage setting
        update_option('show_on_front', 'page');
        update_option('page_on_front', $page_id);
        
        // Remove from scheduled changes
        $scheduled_changes = get_option('cus_scheduled_homepage_changes', array());
        $scheduled_changes = is_array($scheduled_changes) ? $scheduled_changes : array();
        $scheduled_changes = array_filter($scheduled_changes, function($change) use ($page_id, $timestamp) {
            if (!is_array($change)) {
                return false;
            }
            if (!isset($change['page_id'], $change['timestamp'])) {
                return false;
            }
            if ((int) $change['page_id'] !== (int) $page_id) {
                return true;
            }
            if ($timestamp === null) {
                // Back-compat: cron may have been scheduled without the timestamp arg; remove by page_id.
                return false;
            }
            return (int) $change['timestamp'] !== (int) $timestamp;
        });
        update_option('cus_scheduled_homepage_changes', $scheduled_changes);
        
    }

    /**
     * Add scheduled republications status page
     */
    public static function add_republications_status_page() {
        add_submenu_page(
            'tools.php',
            __('Scheduled Republications', 'content-update-scheduler'),
            __('Scheduled Republications', 'content-update-scheduler'),
            'manage_options',
            'scheduled-republications',
            array(__CLASS__, 'republications_status_page')
        );
    }

    /**
     * Render scheduled republications status page
     */
    public static function republications_status_page() {
        \Infinitnet\ContentUpdateScheduler\Admin\ScheduledRepublicationsPage::render();
    }
}
