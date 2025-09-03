# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Content Update Scheduler is a WordPress plugin that allows scheduling content updates for any page or post type. The plugin creates duplicate posts with a special status that get automatically published at specified times, updating the original content.

## Architecture

### Core Files
- `content-update-scheduler.php` - Main plugin file containing the `ContentUpdateScheduler` class with all core functionality
- `options.php` - Contains `ContentUpdateScheduler_Options` class for admin settings and configuration

### Key Components

**ContentUpdateScheduler Class** (`content-update-scheduler.php`)
- Post duplication and scheduling system
- WordPress cron integration for automated publishing
- Meta data and taxonomy copying with special handling for page builders
- WooCommerce product compatibility (variations, stock status, etc.)
- WPML multilingual support
- Custom post status management (`cus_sc_publish`)

**Page Builder Support**
- `copy_elementor_data()` - Handles Elementor-specific meta keys and CSS files
- `copy_oxygen_data()` - Manages Oxygen Builder CSS copying
- JSON and serialized data preservation for builder compatibility

**Publishing System**
- `create_publishing_post()` - Creates scheduled update drafts
- `publish_post()` - Main publishing logic with transaction-like behavior and locking
- `cron_publish_post()` - Cron wrapper for automated publishing
- Custom cron schedule checking for overdue posts

## Development Patterns

### WordPress Hooks
The plugin extensively uses WordPress hooks:
- `save_post` for meta data handling
- `cus_publish_post` for custom cron publishing
- `transition_post_status` for status change prevention
- Multiple filters for UI modifications

### Data Copying Strategy
- Meta data copying preserves serialized and JSON data structures
- Special handling for Elementor's JSON-encoded data with Unicode preservation
- Reference restoration for post ID updates during publishing

### Error Handling
- Extensive error logging throughout critical functions
- Transaction-like behavior in `publish_post()` with rollback capability
- Locking mechanisms to prevent concurrent publishing

## Key Features

### Scheduling
- Custom post status `cus_sc_publish` for scheduled updates
- WordPress timezone-aware date/time handling
- Fallback publishing system for overdue posts
- Option to preserve original publication dates

### Compatibility
- Works with any public post type (filterable via `content_update_scheduler_excluded_post_types`)
- WooCommerce product support including variations and stock management
- Page builder compatibility (Elementor, Oxygen)
- WPML multilingual relationship handling

### Security
- Nonce verification for all admin actions
- Capability checks for publishing permissions
- Content filtering management during meta copying

## Common Tasks

Since this is a WordPress plugin, there are no npm/node commands. Development typically involves:

1. **Testing**: Install in a WordPress environment and test with various post types
2. **Debugging**: Check WordPress debug.log for plugin error messages
3. **Compatibility**: Test with page builders, WooCommerce, and other plugins

## Filters and Actions

### Available Filters
- `content_update_scheduler_excluded_post_types` - Exclude post types from scheduling
- `content_update_scheduler_wpml_new_translation_group` - Control WPML translation groups
- `ContentUpdateScheduler\publish_post_date` - Modify publication date during publishing

### Available Actions
- `ContentUpdateScheduler\create_publishing_post` - Fires when post is duplicated
- `ContentUpdateScheduler\before_publish_post` - Fires before publishing update
- `content_update_scheduler_after_wpml_handling` - Fires after WPML processing

## Plugin Structure

This is a single-file WordPress plugin with minimal dependencies, designed for maximum compatibility. The main class contains all functionality to avoid complex file structures that could cause loading issues in different WordPress environments.