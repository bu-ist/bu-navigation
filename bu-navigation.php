<?php
/*
Plugin Name: Page Navigation
Version: 0.2.1
Author URI: http://www.bu.edu/tech/help/
Description: Provides alternative navigation elements designed for blogs with large page counts
Author: Boston University (IS&T)
*/

/**
 * Components:
 *
 * BU Page Parent Meta Box
 * Navigation Management Screens
 * Content navigation widget
 * Filter for drilling into a particular section when view the edit pages screen
 *
 * @todo Include javascript and enqueue only if needed.
 */

/* BU Navigation constants */
define('BU_NAV_PLUGIN_DIR', dirname(__FILE__));

if (!defined('BU_INCLUDES_PATH')) {
    if(!defined('BU_NAVIGATION_LIB_LOADED')) {
        require_once('lib/bu-navigation/bu-navigation.php');
        define('BU_NAVIGATION_LIB_LOADED', true);
    }
} else {
    require_once(BU_INCLUDES_PATH . '/bu-navigation/bu-navigation.php');
}

include(dirname(__FILE__) . '/bu-navigation-widget.php'); // Content navigation widget
if(is_admin()) {
    include(dirname(__FILE__) . '/bu-filter-pages.php'); // Filter pages
    include(dirname(__FILE__) . '/bu-navman.php'); // Navigation manager
    include(dirname(__FILE__) . '/bu-page-parent.php'); // Page parent selector
}
/**
 * Initialization function for navigation plugin
 * @return void
 */
function bu_navigation_init()
{
	bu_navigation_load_extras();
	bu_navigation_widgets_init();

	do_action('bu_navigation_init');
}
add_action('init', 'bu_navigation_init', 1);

/**
 * Initializes navigation widgets
 * @return void
 */
function bu_navigation_widgets_init()
{
	if ( !is_blog_installed() )
		return;

	register_widget('BU_Widget_Pages');
}

/**
 * Loads plugins for this... plugin
 * Any .php file placed in the extras directory will be automatically loaded.
 * @return void
 */
function bu_navigation_load_extras()
{
	$pattern = sprintf('%s/extras/*.php', BU_NAV_PLUGIN_DIR);

	$files = glob($pattern);

	if ((is_array($files)) && (count($files) > 0))
	{
		foreach ($files as $filename)
		{
			@include_once($filename);
		}
	}
}
?>