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

	// Plugin settings option names
	const OPTION_DISPLAY = 'bu_navigation_primarynav';
	const OPTION_MAX = 'bu_navigation_primarynav_max';
	const OPTION_DIVE = 'bu_navigation_primarynav_dive';
	const OPTION_DEPTH = 'bu_navigation_primarynav_depth';
	const OPTION_ALLOW_TOP = 'bu_allow_top_level_page';

	// Plugin settings
	private $settings = array();

	public function __construct() {

		$this->register_hooks();

	}

	/**
	 * Attach WordPress hook callbacks
	 */ 
	public function register_hooks() {

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

	public function get_setting( $name ) {

		$settings = $this->get_settings();

		if( array_key_exists( $name, $settings ) )
			return $settings[$name];

		return false;

	}

	public function get_settings() {

		if( empty( $this->settings ) ) {
			$settings = array();

			$settings['display'] = get_option( self::OPTION_DISPLAY, true );
			$settings['max'] = get_option( self::OPTION_MAX, BU_NAVIGATION_PRIMARY_MAX );
			$settings['dive'] = get_option( self::OPTION_DIVE, true );
			$settings['depth'] = get_option( self::OPTION_DEPTH, BU_NAVIGATION_PRIMARY_DEPTH );
			$settings['allow_top'] = get_option( self::OPTION_ALLOW_TOP, false );

			$this->settings = $settings;
		}

		return $this->settings;
	}

	public function update_settings( $updates ) {

		$settings = $this->get_settings();

		foreach( $updates as $key => $val ) {

			if( ! array_key_exists( $key, $settings ) )
				continue;

			// Update internal settings property
			$this->settings[$key] = $val;

			// Commit to db
			$option = constant( 'self::OPTION_' . strtoupper( $key ) );
			update_option( $option, $val );

		}

	}

}

// Instantiate plugin (only once)
if( ! isset( $bu_navigation_plugin ) ) {
	$bu_navigation_plugin = new BU_Navigation_Plugin();
}

?>