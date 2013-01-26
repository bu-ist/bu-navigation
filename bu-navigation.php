<?php
/*
Plugin Name: Page Navigation
Version: 1.0.1
Author URI: http://www.bu.edu/tech/help/
Description: Provides alternative navigation elements designed for blogs with large page counts
Author: Boston University (IS&T)
*/

/**
Copyright 2012 by Boston University

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

**/

/*
@author Niall Kavanagh <ntk@bu.edu>
@author Gregory Cornelius <gcorne@bu.edu>
@author Mike Burns <mgburns@bu.edu>
*/

/**
 * Components:
 *
 * Navigation Management Screens ("Edit Order" and "Primary Navigation")
 * Navigation Attributes Meta Box
 * Content navigation widget
 * Filter for drilling into a particular section when view the edit pages screen
 */

define('BU_NAV_PLUGIN_DIR', dirname(__FILE__));

require_once dirname( __FILE__ ) . '/includes/library.php';

class BU_Navigation_Plugin {

	public $admin;

	// Plugin settings
	private $settings = array();

	// Plugin settings option names
	const OPTION_DISPLAY = 'bu_navigation_primarynav';
	const OPTION_MAX_ITEMS = 'bu_navigation_primarynav_max';
	const OPTION_DIVE = 'bu_navigation_primarynav_dive';
	const OPTION_DEPTH = 'bu_navigation_primarynav_depth';
	const OPTION_ALLOW_TOP = 'bu_allow_top_level_page';

	const VERSION = '1.0.1';

	public function __construct() {

		$this->register_hooks();

	}

	/**
	 * Attach WordPress hook callbacks
	 */
	public function register_hooks() {

		add_action( 'init', array( $this, 'init' ), 1 );

		// Filter plugin settings utilized by bu_navigation_display_primary function
		add_filter('bu_filter_primarynav_defaults', array( $this, 'filter_primary_nav_defaults' ) );

	}

	/**
	 * Initialization function for navigation plugin
	 *
	 * @hook init
	 * @return void
	 */
	public function init() {

		if( is_admin() ) {
			$this->load_admin();
		}

		$this->load_extras();

		$this->load_widget();

		do_action('bu_navigation_init');

	}

	public function load_admin() {

		require_once(dirname(__FILE__) . '/bu-navigation-admin.php');
		$this->admin = new BU_Navigation_Admin( $this );

	}

	/**
	 * Initializes navigation widgets
	 * @return void
	 */
	public function load_widget() {

		if ( !is_blog_installed() )
			return;

		require_once(dirname(__FILE__) . '/bu-navigation-widget.php'); // Content navigation widget
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

	// Plugin settings

	/**
	 * Get a single plugin setting by slug
	 */
	public function get_setting( $name ) {

		$settings = $this->get_settings();

		if( array_key_exists( $name, $settings ) )
			return $settings[$name];

		return false;

	}

	/**
	 * Get all plugin settings
	 */
	public function get_settings() {

		if( empty( $this->settings ) ) {

			$settings = array();

			$settings['display'] = (bool) get_option( self::OPTION_DISPLAY, true );
			$settings['max_items'] = (int) get_option( self::OPTION_MAX_ITEMS, BU_NAVIGATION_PRIMARY_MAX );
			$settings['dive'] = (bool) get_option( self::OPTION_DIVE, true );
			$settings['depth'] = (int) get_option( self::OPTION_DEPTH, BU_NAVIGATION_PRIMARY_DEPTH );
			$settings['allow_top'] = (bool) get_option( self::OPTION_ALLOW_TOP, true );

			$this->settings = $settings;

		}

		return $this->settings;

	}

	/**
	 * Update plugin settings
	 */
	public function update_settings( $updates ) {

		$settings = $this->get_settings();

		foreach( $updates as $key => $val ) {

			if( array_key_exists( $key, $settings ) ) {

				// Prevent depth setting from exceeding theme limit (BU_NAVIGATION_SUPPORTED_DEPTH)
				if( $key == 'depth' )
					$val = $this->depth_fix( $val );

				// Cooerce booleans into ints for update_option
				if( is_bool( $val ) ) $val = intval( $val );

				// Commit to db
				$option = constant( 'self::OPTION_' . strtoupper( $key ) );
				$result = update_option( $option, $val );

				// Update internal settings on successful commit
				if( $result ) {

					// Update internal settings property
					$this->settings[$key] = $val;

				}

			}

		}

	}

	/**
	 * Clear internal settings object
	 *
	 * Useful for unit tests that want to check actual DB values
	 */
	public function clear_settings() {

		$this->settings = array();

	}

	/**
	 * Filter the navigation settings used by bu_navigation_display_primary to
	 * utilize plugin settings
	 */
	public function filter_primary_nav_defaults( $defaults ) {

		$defaults['echo'] = $this->get_setting('display');
		$defaults['depth'] = $this->get_setting('depth');
		$defaults['max_items'] = $this->get_setting('max_items');
		$defaults['dive'] = $this->get_setting('dive');

		return $defaults;

	}

	/**
	 * Assure that current max depth is below the threshold set by the current themes BU_NAVIGATION_SUPPORTED_DEPTH constant
	 */
	public function depth_fix( $curr_depth = 0 ) {

		if ( defined('BU_NAVIGATION_SUPPORTED_DEPTH') && $curr_depth > BU_NAVIGATION_SUPPORTED_DEPTH ) {
			return BU_NAVIGATION_SUPPORTED_DEPTH;
		}

		if ( !$curr_depth ) $curr_depth = BU_NAVIGATION_PRIMARY_DEPTH;

		return $curr_depth;

	}

	/**
	 * Returns the original post type for an existing post
	 *
	 * @param mixed $post post ID, object, or post type string
	 * @return string $post_type post type name
	 */
	public function get_post_type( $post ) {

		// Default arg -- post type string
		$post_type = $post;

		if( is_numeric( $post ) ) {
			$post = get_post( $post );
			if( $post === false )
				return false;

			$post_type = $post->post_type;

		} else if ( is_object( $post ) ) {

			$post_type = $post->post_type;

		}

		// @todo add BU Versions logic here

		return $post_type;

	}

	/**
	 * Helper for creating a post type labels arrays
	 *
	 * @param $post_type name of a registered post type to get labels for
	 */
	public function get_post_type_labels( $post_type ) {

		$pt_obj = get_post_type_object($post_type);

		if( ! is_object( $pt_obj ) )
			return false;

		return array(
			'post_type' => $post_type,
			'singular' => $pt_obj->labels->singular_name,
			'plural' => $pt_obj->labels->name,
		);

	}

}

// Instantiate plugin (only once)
if( ! isset( $bu_navigation_plugin ) ) {
	$bu_navigation_plugin = new BU_Navigation_Plugin();
}

?>
