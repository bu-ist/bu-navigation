<?php
/*
Plugin Name: Page Navigation 
Version: 0.1
Author URI: http://www.bu.edu/tech/help/
Description: Provides alternative navigation elements designed for blogs with large page counts
Author: Networked Information Services
*/

/* BU Navigation constants */
define('BU_NAV_META_PAGE_LABEL', '_bu_cms_navigation_page_label'); // name of meta_key used to hold navigation labels
define('BU_NAV_META_PAGE_EXCLUDE', '_bu_cms_navigation_exclude'); // name of meta_key used to exclude pages from navigation

require_once('lib/bu-navigation/bu-navigation.php');

/* Widgets */
require_once('bu-navigation-widget.php');

function bu_navigation_widgets_init() 
{
	if ( !is_blog_installed() )
		return;

	register_widget('BU_Widget_Pages');

	do_action('widgets_init');
}

add_action('init', 'bu_navigation_widgets_init', 1);
?>