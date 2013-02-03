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

// Absolute server path to this plugin dir and file for use by included files
define( 'BU_NAV_PLUGIN', __FILE__ );
define( 'BU_NAV_PLUGIN_DIR', dirname( __FILE__ ) );

// Primary navigation max items to display per level
define( 'BU_NAVIGATION_PRIMARY_MAX', 6 );

// Primary navigation maxium depth
define( 'BU_NAVIGATION_PRIMARY_DEPTH', 1 );

require_once BU_NAV_PLUGIN_DIR . '/includes/settings.php';
require_once BU_NAV_PLUGIN_DIR . '/includes/library.php';
require_once BU_NAV_PLUGIN_DIR . '/includes/class-tree-view.php';
require_once BU_NAV_PLUGIN_DIR . '/includes/class-reorder.php';

class BU_Navigation_Plugin {

	// Admin object
	public $admin;

	// Plugin settings
	public $settings;

	const VERSION = '1.0.1';
	const TEXT_DOMAIN = 'bu-navigation';

	public function __construct() {

		$this->settings = new BU_Navigation_Settings( $this );

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
			$this->load_admin();
		}

		$this->load_extras();

		if ( $this->supports( 'widget' ) )
			$this->load_widget();

		do_action('bu_navigation_init');

	}

	public function load_admin() {

		require_once(dirname(__FILE__) . '/admin/admin.php');
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
