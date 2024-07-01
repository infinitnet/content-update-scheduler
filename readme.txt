=== Content Update Scheduler ===
Contributors: infinitnet
Tags: schedule, scheduling, update, republish, publication
Requires at least: 3.7.0
Tested up to: 6.5.4
Stable tag: 2.3
Requires PHP: 5.3
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.en.html

Schedule content updates for any WordPress page or post type.

== Description ==

WordPress lacks the ability to schedule content updates. Keeping your posts and pages up to date manually can often be a waste of valuable time, especially when you know you'll need to update the same page again soon.

== Use Cases ==
* **Promotions:** Automatically publish versions of your pages that contain temporary promotions and schedule content updates that remove these promotions once they expire.
* **Events:** Schedule content updates for pages that list events. Automatically publish an updated version of the page after an event ends.
* **SEO:** Schedule series of content updates to keep your pages and publishing dates current and satisfy the freshness algorithm.

== Key Features ==
* Updates page content and publishing date
* Compatible with any post type
* Compatible with Elementor and Oxygen Builder
* Nested content updates (multiple updates of the same page scheduled in a row)
* Lightweight code

== Credits ==
Developed by [Infinitnet](https://infinitnet.io/) and based on the abandoned [tao-schedule-update](https://github.com/tao-software/tao-schedule-update) plugin. Major contributions by [Immediate Media](https://immediate.co.uk/).

**Github:** [https://github.com/infinitnet/content-update-scheduler/](https://github.com/infinitnet/content-update-scheduler/)

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/content-update-scheduler` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Each page and post now has a 'Scheduled Content Update' link where you can schedule the content updates. Click on it.
4. Select the date and time in the new 'Scheduled Content Update' meta box on 'Page' level and then click 'Publish' to schedule it.

== Frequently Asked Questions ==

= How do I schedule a content update?

Each page and post has a 'Scheduled Content Update' link in the overview, which allows you to schedule content updates. Click on it. Then select the date and time in the new 'Scheduled Content Update' meta box on 'Page' level and then click 'Publish' to schedule it.

= Does this work with page builders?

Yes, it has been tested with Elementor and Oxygen Builder. It may also work with other page builders.

== Changelog ==

= 2.3 =
* Refactored datepicker: fixed bugs, allow time selection by minute
* Improved WooCommerce compatibility
* Don't show "Republication Date" for original posts but only for republication drafts

= 2.2 =
* Improved fix for 404 issue
* Date picker timezone is now always the site's timezone

= 2.1 =
* Fixed 404 error for republished posts: Removed unused code, added check for scheduled publish date, and added deactivation hook to remove custom post meta.
* Adjusted the datepicker to start the week on Sunday and added an onSelect event handler to call the checkTime function.
* Improved the date parsing logic and scheduling of content updates in the content-update-scheduler.php file.

= 2.0 =
* Fixed bug with date selection

= 1.9 =
* Ensure correct copying and maintenance of WooCommerce stock status during republication process and when saving the republication draft
* Update WooCommerce stock status and quantity from the original product before updating during republication

= 1.8 =
* Various bug fixes

= 1.7 =
* Fixed fatal error when class definition of metadata is missing and skip copying over metadata entries that fail to unserialize

= 1.6 =
* Updated functions to handle post ID references correctly and ensure "Republication Date" column appears for all post types
* Corrected nonce verification, function call, and meta data deletion for scheduled date handling

= 1.5 =
* Updated meta field references during republication to handle original post ID correctly

= 1.4 =
* Copy all meta fields dynamically for maximum compatibility with custom fields, WooCommerce products, etc.

= 1.3 =
* Elementor compatibility: Updated handling of Elementor CSS and added meta data copying

= 1.2 =
* Fixed the incorrect usage of action and filter hooks
* Moved CSS output to admin_head action to avoid 'header already sent' error
* Ensured all meta fields are correctly copied when creating the republication draft for WooCommerce variable products

= 1.1 =
* Pull request #4 from Immediate Media merged (Github)
* Use local WordPress time zone instead of UTC+1
* Retain original post author
* Deduplication of some pieces of code
* Deprecated functions and practices reduced
* Replaced the date_i18n() function with the newer wp_date() function
* Simplified the checkTime function in js/publish-datepicker.js
* Wrapped wp_get_current_user() call in a conditional to check if the user is logged in

= 1.0 =
* First version.
