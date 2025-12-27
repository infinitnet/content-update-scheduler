<?php
/**
 * Content Update Scheduler
 *
 * Plugin Name: Content Update Scheduler
 * Description: Schedule content updates for any page or post type.
 * Author: Infinitnet
 * Author URI: https://infinitnet.io/
 * Version: 4.0.2
 * License: GPLv3
 * Text Domain: content-update-scheduler
 *
 * @package cus
 */

defined('ABSPATH') || exit;

if (!defined('CUS_VERSION')) {
    define('CUS_VERSION', '4.0.2');
}
if (!defined('CUS_PLUGIN_FILE')) {
    define('CUS_PLUGIN_FILE', __FILE__);
}
if (!defined('CUS_PLUGIN_DIR')) {
    define('CUS_PLUGIN_DIR', __DIR__);
}

spl_autoload_register(function ($class) {
    $prefix = 'Infinitnet\\ContentUpdateScheduler\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $relative = str_replace('\\', '/', $relative);
    $path = CUS_PLUGIN_DIR . '/includes/' . $relative . '.php';
    if (file_exists($path)) {
        require_once $path;
    }
});

require_once CUS_PLUGIN_DIR . '/options.php';
require_once CUS_PLUGIN_DIR . '/includes/class-content-update-scheduler.php';

/**
 * Register hooks (runtime + lifecycle).
 */
\Infinitnet\ContentUpdateScheduler\Plugin::init();
register_activation_hook(__FILE__, array('\Infinitnet\ContentUpdateScheduler\Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('\Infinitnet\ContentUpdateScheduler\Plugin', 'deactivate'));
