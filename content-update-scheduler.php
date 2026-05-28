<?php
/**
 * Content Update Scheduler
 *
 * Plugin Name: Content Update Scheduler
 * Description: Schedule content updates for any page or post type.
 * Author: Infinitnet
 * Author URI: https://infinitnet.io/
 * Version: 4.0.3
 * License: GPLv3
 * Text Domain: content-update-scheduler
 *
 * @package cus
 */

defined('ABSPATH') || exit;

if (!defined('CUS_VERSION')) {
    define('CUS_VERSION', '4.0.3');
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

if (!function_exists('cus_bootstrap_report_missing_files')) {
    /**
     * Report an incomplete plugin package without throwing an activation fatal.
     *
     * @param array $missing_files Relative file paths missing from the plugin package.
     * @return void
     */
    function cus_bootstrap_report_missing_files($missing_files)
    {
        $message = sprintf(
            'Content Update Scheduler could not start because its installation is incomplete. Missing required file(s): %s. Please reinstall the plugin from a complete package.',
            implode(', ', $missing_files)
        );

        if (function_exists('add_action')) {
            add_action('admin_notices', function () use ($message) {
                if (function_exists('current_user_can') && !current_user_can('activate_plugins')) {
                    return;
                }

                $escaped_message = function_exists('esc_html')
                    ? esc_html($message)
                    : htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

                echo '<div class="notice notice-error"><p>' . $escaped_message . '</p></div>';
            });

            add_action('admin_init', function () {
                if (!function_exists('deactivate_plugins') || !function_exists('plugin_basename')) {
                    return;
                }

                deactivate_plugins(plugin_basename(CUS_PLUGIN_FILE), true);
            });
        }

        if (function_exists('register_activation_hook')) {
            register_activation_hook(CUS_PLUGIN_FILE, function () use ($message) {
                if (function_exists('deactivate_plugins') && function_exists('plugin_basename')) {
                    deactivate_plugins(plugin_basename(CUS_PLUGIN_FILE), true);
                }

                if (function_exists('wp_die')) {
                    wp_die(
                        function_exists('esc_html') ? esc_html($message) : htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
                        'Plugin activation failed',
                        array('response' => 200)
                    );
                }
            });
        }

        $log_key = 'cus_bootstrap_missing_files_' . md5($message);
        if (!function_exists('get_transient') || get_transient($log_key) === false) {
            error_log($message); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

            if (function_exists('set_transient')) {
                set_transient($log_key, 1, defined('HOUR_IN_SECONDS') ? HOUR_IN_SECONDS : 3600);
            }
        }
    }
}

if (!function_exists('cus_bootstrap_can_read_file')) {
    /**
     * Check file availability using an actual read probe.
     *
     * Some mounted filesystems can report optimistic stat metadata, so `file_exists()`
     * or `is_readable()` alone is not enough to protect the subsequent require.
     *
     * @param string $path Absolute file path.
     * @return bool
     */
    function cus_bootstrap_can_read_file($path)
    {
        if (!file_exists($path) || !is_readable($path)) {
            return false;
        }

        $handle = @fopen($path, 'rb'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        if ($handle === false) {
            return false;
        }

        fclose($handle); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        return true;
    }
}

if (!function_exists('cus_bootstrap_require_file')) {
    /**
     * Require a bootstrap file without allowing missing package files to fatal.
     *
     * @param string $relative_file Relative file path.
     * @return bool
     */
    function cus_bootstrap_require_file($relative_file)
    {
        try {
            @require_once CUS_PLUGIN_DIR . '/' . $relative_file;
            return true;
        } catch (Throwable $e) {
            if (strpos($e->getMessage(), 'Failed opening required') === false) {
                throw $e;
            }

            return false;
        }
    }
}

$cus_required_files = array(
    'options.php',
    'includes/class-content-update-scheduler.php',
    'includes/Plugin.php',
    'includes/Admin/ScheduledRepublicationsPage.php',
    'includes/Admin/ScheduledRepublicationsTable.php',
    'includes/Support/AdminNotices.php',
    'includes/Support/Cron.php',
);
$cus_missing_files = array();

foreach ($cus_required_files as $cus_required_file) {
    $cus_required_path = CUS_PLUGIN_DIR . '/' . $cus_required_file;
    if (!cus_bootstrap_can_read_file($cus_required_path)) {
        $cus_missing_files[] = $cus_required_file;
    }
}

if (!empty($cus_missing_files)) {
    cus_bootstrap_report_missing_files($cus_missing_files);
    return;
}

$cus_bootstrap_files = array(
    'options.php',
    'includes/class-content-update-scheduler.php',
    'includes/Plugin.php',
);

foreach ($cus_bootstrap_files as $cus_bootstrap_file) {
    if (!cus_bootstrap_require_file($cus_bootstrap_file)) {
        cus_bootstrap_report_missing_files(array($cus_bootstrap_file));
        return;
    }
}

/**
 * Register hooks (runtime + lifecycle).
 */
\Infinitnet\ContentUpdateScheduler\Plugin::init();
register_activation_hook(__FILE__, array('\Infinitnet\ContentUpdateScheduler\Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('\Infinitnet\ContentUpdateScheduler\Plugin', 'deactivate'));
