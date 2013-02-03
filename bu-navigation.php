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

// Absolute server path to this plugin dir for use by included files
define( 'BU_NAV_PLUGIN_DIR', dirname( __FILE__ ) );

// Primary navigation max items to display per level
define( 'BU_NAVIGATION_PRIMARY_MAX', 6 );

// Primary navigation maxium depth
define( 'BU_NAVIGATION_PRIMARY_DEPTH', 1 );

require_once dirname( __FILE__ ) . '/includes/library.php';

class BU_Navigation_Plugin {

	// Admin object
	public $admin;

	// Plugin settings
	// @todo move to separate settings object
	private $settings = array();

	// Plugin settings option names
	// @todo move to separate settings object
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
		// @todo move to primary navigation class
		add_filter( 'bu_filter_primarynav_defaults', array( $this, 'primary_nav_defaults' ) );

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

		if ( $this->supports( 'widget' ) )
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
	 * @todo move to separate settings object
	 */
	public function get_setting( $name ) {

		$settings = $this->get_settings();

		if( array_key_exists( $name, $settings ) )
			return $settings[$name];

		return false;

	}

	/**
	 * Get all plugin settings
	 * @todo move to separate settings object
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
	 * @todo move to separate settings object
	 */
	public function update_settings( $updates ) {

		$settings = $this->get_settings();

		foreach( $updates as $key => $val ) {

			if( array_key_exists( $key, $settings ) ) {

				// Prevent depth setting from exceeding theme limit
				// @todo move to primary navigation class
				if( $key == 'depth' ) {
					$max_depth = $this->primary_max_depth();

					if( $val > $max_depth )
						$val = $max_depth;
				}

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
	 * @todo move to separate settings object
	 *
	 * Useful for unit tests that want to check actual DB values
	 */
	public function clear_settings() {

		$this->settings = array();

	}

	/**
	 * Filter the navigation settings used by bu_navigation_display_primary to
	 * utilize plugin settings
	 *
	 * @todo move to primary navigation class
	 */
	public function primary_nav_defaults( $defaults = array() ) {

		$defaults['echo'] = $this->get_setting('display');
		$defaults['depth'] = $this->get_setting('depth');
		$defaults['max_items'] = $this->get_setting('max_items');
		$defaults['dive'] = $this->get_setting('dive');

		return $defaults;

	}

	/**
	 * Return the current max primary navigation depth
	 *
	 * @todo move to primary navigation class
	 *
	 * The depth can be set by:
	 *  BU_NAVIGATION_SUPPORTED_DEPTH constant
	 *  'bu-navigation-primary' theme feature
	 *
	 * Themes calling add_theme_support( 'bu-navigation-primary' ) can pass an optional second argument --
	 * an associative array.  At this time, only one option is configurable:
	 *
	 * 	'depth' - Maxinum levels to nest in navigation lists
	 *
	 * Thus `add_theme_support( 'bu-navigation-primary', array( 'depth' => 3 ) )` would allow for three levels
	 * of pages to appear in the primary navigation menu.
	 */
	public function primary_max_depth() {

		$override_const = defined( 'BU_NAVIGATION_SUPPORTED_DEPTH' ) ? BU_NAVIGATION_SUPPORTED_DEPTH : null;
		$override_theme = get_theme_support( 'bu-navigation-primary' );

		// Get default primary navigation settings
		$defaults = $this->primary_nav_defaults();
		$theme_opts = array();

		// Merge with any possible values set using first arg of add_theme_support
		if( is_array( $override_theme ) && count( $override_theme ) >= 1 ) {
			$theme_opts = wp_parse_args( (array) $override_theme[0], $defaults );
		}

		if( $override_const ) return $override_const;
		if( $override_theme && array_key_exists( 'depth', (array) $theme_opts ) ) return $theme_opts['depth'];

		return BU_NAVIGATION_PRIMARY_DEPTH;

	}

	/**
	 * Navigation plugin features
	 *
	 * @todo discuss these open source defaults with BU
	 *
	 * The navigation plugin is configurable in a few different regards.  This function returns an associative array
	 * of features, with the key representing the feature name and the value holding the default.
	 *
	 * bu-navigation-manager (on by default)
	 * 	- turn on or off the navigation management interfaces ("Edit Order" pages, "Navigation Attributes" metabox)
	 *
	 * bu-navigation-widget (on by default)
	 * 	- turn on or off the "Content Navigation" widget (on by default)
	 *
	 * bu-navigation-primary (off by default -- theme authors, use add_theme_support( 'bu-navigation-primary' ))
	 *  - turn on or off the "Primary Navigation" appearance menu item
	 *
	 * bu-navigation-links
	 *  - turn on or off the external link feature, include with 'page' post type nav menus (on by default)
	 */
	public function features() {

		return array(
			'manager' => true,
			'widget' => true,
			'links' => false,
			'primary' => false,
			);

	}

	/**
	 * Does the current install or theme support a navigation feature?
	 *
	 * There are three different ways to configure navigation features -- two with PHP constants, and
	 * one using the theme support API.
	 *
	 * Installs can use the constants to enable or disable navigation features for all themes.
	 *
	 * Individual themes can use the theme support API to add theme support for any feature that is off by default.
	 *
	 * The structure and priority of the feature support mechanism is as follows:
	 * 	BU_NAVIGATON_DISABLE_* - Allows installs to explicitly disable feature (highest priority)
	 * 	BU_NAVIGATION_SUPPORTS_* - Allows installs to explicitly enable feature (second highest priority)
	 * 	add_theme_support( 'bu-navigation-*' ) - Allows individual themes to register support for a feature (recommended for theme authors)
	 */
	public function supports( $feature ) {

		$feature = strtolower( $feature );
		$defaults = $this->features();

		if ( ! in_array( $feature, array_keys( $defaults ) ) ) {
			error_log( "[bu-navigation] Unknown feature: $feature" );
			return false;
		}

		$disabled_const = 'BU_NAVIGATION_DISABLE_' . strtoupper( $feature );
		$supported_const = 'BU_NAVIGATION_SUPPORTS_' . strtoupper( $feature );

		$disabled = defined( $disabled_const ) && constant( $disabled_const );
		$const_supported = defined( $supported_const ) ? constant( $supported_const ) : $defaults[$feature];
		$theme_supported = current_theme_supports( 'bu-navigation-' . $feature );

		return ( ! $disabled && ( $const_supported || $theme_supported ) );

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
