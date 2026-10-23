<?php
/**
 * Regression: scheduled revisions must keep Elementor document JSON intact.
 *
 * WordPress update_metadata() unslashes input. Elementor JSON contains
 * escaped quotes. Copying it without wp_slash() makes json_decode() fail,
 * and the Elementor editor then imports post_content as one text widget.
 *
 * Run via wp eval-file inside a site where this plugin is active.
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Must run inside WordPress.\n");
    exit(1);
}

if (!defined('ELEMENTOR_VERSION')) {
    define('ELEMENTOR_VERSION', '3.24.0');
}

if (class_exists('ContentUpdateScheduler')) {
    ContentUpdateScheduler::register_post_status();
}

$failures = array();

$elements = array(
    array(
        'id' => 'aaa1111',
        'elType' => 'container',
        'settings' => array(
            'content_width' => 'boxed',
            'flex_direction' => 'column',
        ),
        'elements' => array(
            array(
                'id' => 'bbb2222',
                'elType' => 'widget',
                'widgetType' => 'heading',
                'settings' => array(
                    'title' => 'Hello "world"',
                    'header_size' => 'h1',
                ),
                'elements' => array(),
            ),
            array(
                'id' => 'ccc3333',
                'elType' => 'widget',
                'widgetType' => 'text-editor',
                'settings' => array(
                    'editor' => '<p>Body with a quote: "keep me" and a path C:\\files\\photo.jpg</p>',
                ),
                'elements' => array(),
            ),
            array(
                'id' => 'ddd4444',
                'elType' => 'widget',
                'widgetType' => 'image',
                'settings' => array(
                    'image' => array(
                        'url' => 'https://example.com/wp-content/uploads/2024/01/photo.jpg',
                        'id' => 12,
                    ),
                ),
                'elements' => array(),
            ),
        ),
    ),
);

$elementor_json = wp_json_encode($elements);
$post_content = '<div data-elementor-type="wp-page"><h1>Hello "world"</h1><p>Body with a quote: "keep me"</p><img src="https://example.com/wp-content/uploads/2024/01/photo.jpg" alt=""></div>';

$page_id = wp_insert_post(array(
    'post_type' => 'page',
    'post_status' => 'publish',
    'post_title' => 'Elementor scheduled revision regression',
    'post_content' => $post_content,
), true);

if (is_wp_error($page_id)) {
    fwrite(STDERR, 'insert failed: ' . $page_id->get_error_message() . "\n");
    exit(1);
}

update_metadata('post', $page_id, '_elementor_data', wp_slash($elementor_json));
update_post_meta($page_id, '_elementor_edit_mode', 'builder');
update_post_meta($page_id, '_elementor_template_type', 'wp-page');
update_post_meta($page_id, '_elementor_version', '3.24.0');
update_post_meta($page_id, '_elementor_page_settings', array('hide_title' => 'yes'));

$stored = get_post_meta($page_id, '_elementor_data', true);
$stored_decoded = is_string($stored) ? json_decode($stored, true) : null;
if (!is_array($stored_decoded)) {
    fwrite(STDERR, "fixture _elementor_data was not stored as valid JSON\n");
    exit(1);
}

$page = get_post($page_id);
$copy_id = ContentUpdateScheduler::create_publishing_post($page);
if (is_wp_error($copy_id) || !$copy_id) {
    $message = is_wp_error($copy_id) ? $copy_id->get_error_message() : 'no id';
    fwrite(STDERR, "create_publishing_post failed: {$message}\n");
    exit(1);
}

$copied = get_post_meta($copy_id, '_elementor_data', true);
$copied_decoded = is_string($copied) ? json_decode($copied, true) : null;

if (!is_string($copied) || !is_array($copied_decoded)) {
    $failures[] = 'scheduled revision _elementor_data is not valid JSON (' . (is_string($copied) ? json_last_error_msg() : gettype($copied)) . ')';
} else {
    $source_types = cus_elementor_widget_types($stored_decoded);
    $copied_types = cus_elementor_widget_types($copied_decoded);
    if ($copied_types !== $source_types) {
        $failures[] = 'widget tree changed from ' . implode(',', $source_types) . ' to ' . implode(',', $copied_types);
    }
    if ($copied !== $stored) {
        $failures[] = 'scheduled revision _elementor_data does not match the source JSON';
    }
}

if (get_post_meta($copy_id, '_elementor_edit_mode', true) !== 'builder') {
    $failures[] = 'scheduled revision lost _elementor_edit_mode=builder';
}

$page_settings = get_post_meta($copy_id, '_elementor_page_settings', true);
if (!is_array($page_settings) || ($page_settings['hide_title'] ?? '') !== 'yes') {
    $failures[] = 'scheduled revision lost _elementor_page_settings';
}

$published = ContentUpdateScheduler::publish_post($copy_id);
if (is_wp_error($published)) {
    $failures[] = 'publish_post failed: ' . $published->get_error_message();
} else {
    $published_data = get_post_meta($page_id, '_elementor_data', true);
    $published_decoded = is_string($published_data) ? json_decode($published_data, true) : null;
    if (!is_array($published_decoded) || cus_elementor_widget_types($published_decoded) !== cus_elementor_widget_types($stored_decoded)) {
        $failures[] = 'publishing the revision corrupted the original Elementor document';
    }
}

$result = array(
    'ok' => $failures === array(),
    'failures' => $failures,
    'page_id' => $page_id,
    'copy_id' => $copy_id,
);

echo wp_json_encode($result, JSON_PRETTY_PRINT) . "\n";

if ($failures !== array()) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

function cus_elementor_widget_types($elements) {
    $types = array();
    foreach ($elements as $element) {
        if (!is_array($element)) {
            continue;
        }
        if (isset($element['widgetType'])) {
            $types[] = $element['widgetType'];
        }
        if (!empty($element['elements']) && is_array($element['elements'])) {
            $types = array_merge($types, cus_elementor_widget_types($element['elements']));
        }
    }
    return $types;
}
