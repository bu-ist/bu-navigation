# BU Navigation #
**Contributors:** ntk, mgburns, gcorne  
**Tags:** navigation, hierarchical, post type, bu  
**Requires at least:** 3.1  
**Tested up to:** 3.5  
**Stable tag:** 1.1  
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

Intuitive management interface for hierarchical post types.

## Description ##
The BU Navigation plugin makes it easy to set up navigation lists that are fed from your natural page hieararchy.

This plugin provides several useful features:
* Replaces the built-in “Page Parent” and “Menu Order” dropdowns with an intuitive drag and drop interface for managing your page hierarchy
* The “Edit Order” screen presents you with a holistic view of your sites structure for bulk ordering operations
* The Content Navigation widget presents a customizable sidebar navigation list fed from your natural page hierarchy
* Add external links to navigation lists with the “Add a Link” tool

Additionally, themes that support the primary navigation feature gain the ability to display a primary navigation list fed from page order.  With two lines of code any theme can benefit from this feature-rich custom menu alternative.
* Display a primary navigation menu using the natural page hierarchy, eliminating the need for end-users to manage navigation lists separatly from page order
* Navigation labels give you the ability to vary your page’s navigation label from their title
* Easily exclude specific pages or sections of pages from your navigation menus
* Customize primary navigation settings through the “Primay Navigation” screen

For developers, the plugin comes bundled with the navigation library — a powerful set of functions that serve as an efficient alternative to WP_Query for querying large amounts of hierarchical post types.

For more information, visit our Github Wiki.

## Installation ##

1. Upload `bu-navigation` directory to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

## Frequently Asked Questions ##

### I don’t see the “Primary Navigation” menu!  How do I enable it? ###
The “Primary Navigation” will only appear of the current theme supports it.  The primary navigation feature can be enabled by adding two lines of code.

See this wiki page for more information:

### How do I prevent my users from adding top level content to the primary navigation menu? ###

### I’m a theme / plugin developer that would like to take advantage of the navigation library, but I don’t want any of the beautiful admin interface enhancements.  Is there a way to disable them? ###
Yes!  Please see this Wiki page for the details.

## Screenshots ##

###1. Manage your site’s page hierarchy with an easy to use drag and drop interface###
![Manage your site’s page hierarchy with an easy to use drag and drop interface](http://s.wordpress.org/extend/plugins/bu-navigation/screenshot-1.png)

###2. The “Add a Link” tool allows you to add external links to your navigation lists###
![The “Add a Link” tool allows you to add external links to your navigation lists](http://s.wordpress.org/extend/plugins/bu-navigation/screenshot-2.png)

###3. The “Content Navigation” widget presents a configurable sidebar navigation list###
![The “Content Navigation” widget presents a configurable sidebar navigation list](http://s.wordpress.org/extend/plugins/bu-navigation/screenshot-3.png)

###4. The “Navigation Attributes” metabox replaces the built-in “Page Parent” and “Menu Order” dropdowns###
![The “Navigation Attributes” metabox replaces the built-in “Page Parent” and “Menu Order” dropdowns](http://s.wordpress.org/extend/plugins/bu-navigation/screenshot-4.png)

###5. The same drag and drop view is available to move pages while editing them###
![The same drag and drop view is available to move pages while editing them](http://s.wordpress.org/extend/plugins/bu-navigation/screenshot-5.png)


## Changelog ##

### 1.1 ###
* Initial WP.org release
* Added localization support
* Added feature configuration through the Theme Features API and PHP constants
* Added navigation links as a true custom post type
* Assorted cleanup and optimizations

### 1.0 ###
* Initial release