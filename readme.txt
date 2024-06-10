=== Content Update Scheduler ===
Contributors: infinitnet
Tags: schedule, timing, scheduler, content, update, publish, time, publication
Requires at least: 3.7.0
Tested up to: 6.5.4
Stable tag: 1.1
Requires PHP: 5.3
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.en.html

Schedule content updates for any WordPress page or post type.

== Description ==

WordPress lacks the ability to schedule content updates. Keeping your posts and pages up to date manually can often be a waste of valuable time, especially when you know you'll need to update the same page again soon.

== Use Cases ==
* Promotions:** Automatically publish versions of your pages that contain temporary promotions and schedule content updates that remove these promotions once they expire.
* **Events:** Schedule content updates for pages that list events. Automatically publish an updated version of the page after an event ends.
* **SEO:** Schedule series of content updates to keep your pages and publishing dates current and satisfy the freshness algorithm.

== Key Features ==
* Updates page content and publishing date
* Compatible with any post type
* Compatible with Elementor and Oxygen Builder
* Nested content updates (multiple updates of the same page scheduled in a row)
* Lightweight code

== Credits ==
Developed by [Infinitnet](https://infinitnet.io/) and based on the abandoned [tao-schedule-update](https://github.com/tao-software/tao-schedule-update) plugin. Major contributions by [Immediate Media](https://immediate.co.uk/)

**Github:** [https://github.com/infinitnet/content-update-scheduler/](https://github.com/infinitnet/content-update-scheduler/)

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/content-update-scheduler` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Each page and post now has a 'Scheduled Content Update' link where you can schedule the content updates. Click on it.
4. Select the date and time and then save as draft.

== Frequently Asked Questions ==

= How do I schedule a content update?

Each page and post has a 'Scheduled Content Update' link in the overview, which allows you to schedule content updates. Click on it. Then select the date and time of the update and save it as a draft (do not click Publish).

= Does this work with page builders?

Yes, it has been tested with Elementor and Oxygen Builder. It may also work with other page builders.

== Changelog ==

= 1.1 =
* Pull request #4 from Immediate Media merged (Github)
* Deduplication of some pieces of code
* Deprecated functions and practices reduced
* Replaced the date_i18n() function with the newer wp_date() function
* Simplified the checkTime function in js/publish-datepicker.js

= 1.0 =
* First version.
