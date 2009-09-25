<?php
/*
Plugin Name: Page Navigation 
Version: 0.1
Author URI: http://www.bu.edu/tech/help/
Description: Provides alternative navigation elements designed for blogs with large page counts
Author: Networked Information Services
*/

/* BU Navigation constants */
define('BU_NAV_PLUGIN_DIR', dirname(__FILE__));

if (!defined('BU_NAVIGATION_LIB_LOADED')) require_once('lib/bu-navigation/bu-navigation.php');

/* Widgets */
require_once('bu-navigation-widget.php');

function bu_navigation_widgets_init() 
{
	if ( !is_blog_installed() )
		return;

	register_widget('BU_Widget_Pages');

	bu_navigation_load_plugins();
	
	do_action('widgets_init');
}

function bu_navigation_load_plugins()
{
	$pattern = sprintf('%s/plugins/*.php', BU_NAV_PLUGIN_DIR);
	
	$files = glob($pattern);
	
	if ((is_array($files)) && (count($files) > 0))
	{
		foreach ($files as $filename)
		{
			@include_once($filename);
		}
	}
}

add_action('init', 'bu_navigation_widgets_init', 1);
?>