<?php
/*
Plugin Name: Page Navigation 
Version: 0.2
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

/* Load navigation library */
if (!defined('BU_INCLUDES_PATH')) {
    if(!defined('BU_NAVIGATION_LIB_LOADED')) {
        require_once('lib/bu-navigation/bu-navigation.php');
        define('BU_NAVIGATION_LIB_LOADED', true);
    }
} else {
    require_once(BU_INCLUDES_PATH . '/bu-navigation/bu-navigation.php');
}

class BU_Navigation_Plugin {

	static $admin;

	public function __construct() {

		add_action( 'init', array( $this, 'init' ), 1 );

	}

	/**
	 * Initialization function for navigation plugin
	 * 
	 * @hook init
	 * @return void 
	 */
	public function init() {

		if( is_admin() ) {

			include(dirname(__FILE__) . '/bu-navigation-admin.php');
			self::$admin = new BU_Navigation_Admin();

		}

		$this->load_extras();

		$this->load_widget();
		
		do_action('bu_navigation_init');

	}

	/**
	 * Initializes navigation widgets
	 * @return void
	 */
	public function load_widget() {

		if ( !is_blog_installed() )
			return;

		include(dirname(__FILE__) . '/bu-navigation-widget.php'); // Content navigation widget
		register_widget('BU_Widget_Pages');

	}

	/**
	 * Loads plugins for this... plugin
	 * Any .php file placed in the extras directory will be automatically loaded.
	 * @return void
	 */
	public function load_extras() {

		$pattern = sprintf('%s/extras/*.php', BU_NAV_PLUGIN_DIR);
		
		$files = glob($pattern);
		
		if ((is_array($files)) && (count($files) > 0)) {
			foreach ($files as $filename) {
				@include_once($filename);
			}
		}

	}

}

// Instantiate plugin (only once)
if( ! isset( $bu_navigation_plugin ) ) {
	$bu_navigation_plugin = new BU_Navigation_Plugin();
}

?>