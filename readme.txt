=== BU Navigation ===
Contributors: ntk, mgburns, gcorne, jtwiest, awbauer, inderpreet99
Tags: navigation, hierarchical, post type, boston university, bu
Requires at least: 3.1
Tested up to: 5.7
Stable tag: 1.3.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Robust tools for managing hierarchical page content in WordPress. Ideal for blogs with large page counts.

== Description ==

BU Navigation provides key tools you need to manage large numbers of pages.

* Replaces the built-in “Page Parent” and “Menu Order” dropdowns with an intuitive drag and drop interface for managing your page hierarchy
* The “Edit Order” screen presents you with a holistic view of your site’s structure for bulk ordering operations
* The Content Navigation widget presents a customizable sidebar navigation list fed from your natural page hierarchy
* The Navigation Block provides a navigation menu based on a customizeable parent post
* Add external links to navigation lists with the “Add a Link” tool

Additionally, themes that support the primary navigation feature gain the ability to display a primary navigation list fed from page order. With two lines of code any theme can benefit from this feature-rich custom menu alternative.

* Display a primary navigation menu using the natural page hierarchy, eliminating the need for end-users to manage navigation lists separately from page order
* Navigation labels give you the ability to vary your page’s navigation label from their title
* Easily toggle the visibility of specific pages or sections of pages from your navigation menus
* Customize primary navigation settings through the “Primary Navigation” screen

For more information check out [http://developer.bu.edu/bu-navigation/](http://developer.bu.edu/bu-navigation/).

= Developers =

For developer documentation, feature roadmaps and more visit the [plugin repository on Github](https://github.com/bu-ist/bu-navigation/).

[Build Status](https://travis-ci.org/bu-ist/bu-navigation)

== Installation ==

This plugin can be installed automatically through the WordPress admin interface, or by clicking the download link on this page and installing manually.

= Manual Installation =

1. Upload the `bu-navigation` plugin folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= I don’t see the “Primary Navigation” menu! How do I enable it? =
The “Primary Navigation” menu item will only appear if the current theme supports it.

Please see this page for more information:
[Adding Theme Support for Primary Navigation Menus](https://github.com/bu-ist/bu-navigation/wiki/Adding-Theme-Support-for-Primary-Navigation-Menus "BU Navigation Wiki on Github")

= How do I hide a page from my navigation lists? =

While editing your page, uncheck the “Display in navigation lists” checkbox next to the “Visibility” label inside the “Placement in Navigation” metabox. If the metabox is not visible, expand the “Screen Options” panel at the upper right hand corner of the screen and make sure the “Placement in Navigation” checkbox is checked.

= My post title is too long for my navigation lists. Is there a way to pick a different label for navigation lists? =

While editing your page, enter an alternate navigation label in the “Label” text field inside the “Placement in Navigation” metabox. If the metabox is not visible, expand the “Screen Options” panel at the upper right hand corner of the screen and make sure the “Placement in Navigation” checkbox is checked.

= Is there a way to prevent my users from adding top level content to the primary navigation menu? =

Visit the “Appearance > Primary Navigation” screen and uncheck the “Allow Top-Level Pages” checkbox. Be sure to click “Save Changes” to save the setting. With this option unchecked, post authors will not be allowed to publish a top level page if the “Display in navigation lists” checkbox is checked.

= I’m a theme / plugin developer that would like to take advantage of the navigation library, but I don’t want any of the administrative interface enhancements. Is there a way to disable them? =
Yes! The navigation manager interface, content navigation widget, and other plugin features can be disabled on a per-install or theme-by-theme basis.

Please see this page for the details:
[Configuring Plugin Features](https://github.com/bu-ist/bu-navigation/wiki/Configuring-Plugin-Features "BU Navigation Wiki on Github")

== Screenshots ==

1. Manage your site’s page hierarchy with an easy to use drag and drop interface
2. The “Add a Link” tool allows you to add external links to your navigation lists
3. The “Content Navigation” widget presents a configurable sidebar navigation list
4. The “Navigation Attributes” metabox replaces the built-in “Page Parent” and “Menu Order” dropdowns
5. The same drag and drop view is available to move pages while editing them

== Changelog ==

= 1.3.4 =

* Adds a Navigation Block that can display the same output as the widget.  It can also display a navigation tree from any specified parent post.
* Adds a wp-scripts build setup for building the block assets.

= 1.3.3 =

* Removes deprecated jQuery browser check.

= 1.3.2 =

* Restores the widget_bu_pages_args filter.

= 1.3.1 =

* Adds Github action, and removes redundant code.

= 1.3.0 =

* Moves core methods to a Composer package that is then imported back into the plugin.  This is to allow for other plugins to also import the core data and widget code.
* Uses a new namespace for the core data methods.
* Global stubs for the namespaced functions are still available for functions that are in use outside of the plugin. If a function doesn't appear to be in use outside of the plugin, the global function was removed.

= 1.2.24 =

* Refactor library query functions for code standards and maintainability.

= 1.2.23 =

* Initial refactor of includes/library.php to improve code standards. Also renames the widget file.

= 1.2.22 =

* Refactor bu-navigation-widget.php to improve code standards.

= 1.2.21 =

* Updates npm dependencies and Grunt toolchain
* Additions and changes to related to PHP code standards

= 1.2.20 =

* Fix for jQuery bug in the "Add Link" functionality.

= 1.2.19 =

* Update deprecated jQuery methods for 5.5 compatibility. (For 5.5, there is still a known bug with the "Add Link" functionality that adds external links to the navigation tree, which will be patched in a future release.)

= 1.2.18 =

* Fix phpunit test setup scripts.

= 1.2.17 =

* Add styling to override Yoast SEO Thickbox styles.

= 1.2.16 =

* Enqueues thickbox explicitly so that the nav modal will open in the block editor and hides the Block Page Attributes panel when BU Navigation is active.

= 1.2.15 =

* Add hash comparison before saving changes to post hierarchy in Change Order page.

= 1.2.13 =

* Add missing parameter to `the_title` filter call. Part 2.

= 1.2.12 =

* Add missing parameter to `the_title` filter call.
* Update unit test scripts.
* Setup Code Climate.

= 1.2.11 =

* Add before/after attributes to support wrapping title/labels. Fixes #12.
* Fix adaptive nav section title calculation. Fixes #22.

= 1.2.10 =

* Fix hierarchical custom post type support. Fixes #20.

= 1.2.9 =

* Fixes redundant title attributes
* Corrects invalid unit tests

= 1.2.8 =

* Fixes an issue affecting section titles in the navigation widget when using Adaptive Mode
* Corrects invalid unit tests

= 1.2.7 =

* Fixed WP4.3 widget init issues -- props nobleclem
* Fixed PHP4 style constructors

= 1.2.6 =

* Confirmed 4.2 compatibility

= 1.2.5 =

* JSON Ajax callbacks now return correct HTTP response headers
* Fixed cache issue with `bu_navigation_load_sections`
* Updated unit tests to work with current test suite using WP CLI test scaffolding
* Added Grunt for script compilation
* Added TravisCI integration

= 1.2.4 =

* 4.0 Compatibility - Fixes style conflicts
* IE8 style fixes

= 1.2.3 =

* Added buwp-smoketests integration (lettuce)

= 1.2.2 =

* 3.9 Compat: Fixed z-index issue with "Move page" modal caused by r27532

= 1.2.1 =

* Added support for editing of HTML entities in navigation label input field
* Added filter: bu_navigation_format_page_label
* Added WP title filters to bu_navigation_format_page_label

= 1.2 =

* Added support for HTML in navigation labels
* Bug Fix: "View" links on "Edit Order" screen corrected for non-root posts
* Added caching to optimize loading of navigation sections
* For Developers: Added several functions for calculating permalinks efficiently for hierarchical post types
* For Developers: Removed the `bu_navigation_pull_page()` function from the navigation library

= 1.1.5 =

* Added Swedish translation (props almhorn)
* Bug Fix: Fix issue where `bu_navigation_breadcrumbs()` was not restoring excluded post filter
* Bug Fix: Only display replacement page template metabox for pages
* Bug Fix: jQuery UI-related issues in 3.6+

= 1.1.4 =

* WP.org release

= 1.1.3 =

* Add support for privately published posts in navigation management views
* Fix for bug that was preventing links from saving
* Assorted cleanup

= 1.1.2 =

* Initial WP.org release
* Increased test coverage
* Added constant for setting default post exclude value

= 1.1.1 =

* Fix for navigation exclude filter

= 1.1 =

* Added localization support
* Added feature configuration through the Theme Features API and PHP constants
* Added navigation links as a true custom post type
* Assorted cleanup and optimizations
