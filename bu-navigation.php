<?php
/**
 * Plugin Name: BU Navigation
 * Plugin URI: http://developer.bu.edu/bu-navigation/
 * Author: Boston University (IS&T)
 * Author URI: http://sites.bu.edu/web/
 * Description: Provides alternative navigation elements designed for blogs with large page counts
 * Version: 1.3.4
 * Text Domain: bu-navigation
 * Domain Path: /languages
 * License: GPL2+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * @package BU_Navigation
 */

/**
 * Copyright 2014 by Boston University
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 **/

/*
@author Niall Kavanagh <ntk@bu.edu>
@author Gregory Cornelius <gcorne@gmail.com>
@author Mike Burns <mgburns@bu.edu>
@author Tyler Wiest <jtwiest@gmail.com>
@author Andrew Bauer <awbauer@bu.edu>
@author Jonathan Williams <jaydub@bu.edu>
*/

// Absolute server path to this plugin dir and file for use by included files.
define( 'BU_NAV_PLUGIN', __FILE__ );
define( 'BU_NAV_PLUGIN_DIR', dirname( __FILE__ ) );

// Primary navigation max items to display per level.
define( 'BU_NAVIGATION_PRIMARY_MAX', 6 );

// Primary navigation maxium depth.
define( 'BU_NAVIGATION_PRIMARY_DEPTH', 1 );

require_once BU_NAV_PLUGIN_DIR . '/includes/settings.php';
require_once BU_NAV_PLUGIN_DIR . '/includes/library.php';
require_once BU_NAV_PLUGIN_DIR . '/includes/class-tree-view.php';
require_once BU_NAV_PLUGIN_DIR . '/includes/class-reorder.php';

require_once BU_NAV_PLUGIN_DIR . '/composer-includes/bu-navigation-core-widget/src/data-model.php';
require_once BU_NAV_PLUGIN_DIR . '/composer-includes/bu-navigation-core-widget/src/data-format.php';
require_once BU_NAV_PLUGIN_DIR . '/composer-includes/bu-navigation-core-widget/src/data-nav-labels.php';
require_once BU_NAV_PLUGIN_DIR . '/composer-includes/bu-navigation-core-widget/src/data-widget.php';
require_once BU_NAV_PLUGIN_DIR . '/composer-includes/bu-navigation-core-widget/src/data-get-urls.php';
require_once BU_NAV_PLUGIN_DIR . '/composer-includes/bu-navigation-core-widget/src/data-active-section.php';
require_once BU_NAV_PLUGIN_DIR . '/composer-includes/bu-navigation-core-widget/src/filters.php';
require_once BU_NAV_PLUGIN_DIR . '/composer-includes/bu-navigation-core-widget/src/class-navigation-widget.php';

// Include Block root file.
require_once BU_NAV_PLUGIN_DIR . '/src/block.php';

/**
 * Convenience class to wrap loading and init functions.
 */
class BU_Navigation_Plugin {

	/**
	 * Singleton object that handles loading and initialization for the Admin interface.
	 *
	 * @var object $admin Instance of the BU_Navigation_Admin class.
	 */
	public $admin;

	/**
	 * Singleton object that handles settings that affect the entire plugin.
	 *
	 * @var object $settings Instance of the BU_Navigation_Settings class.
	 */
	public $settings;

	/**
	 * Plugin version number.
	 *
	 * Useful for cache busting on enqueued assets.
	 *
	 * @var string
	 */
	const VERSION = '1.3.4';

	/**
	 * Plugin class constructor.
	 *
	 * Instantiates the settings object and registers hooks.
	 *
	 * @return void
	 */
	public function __construct() {

		$this->settings = new BU_Navigation_Settings( $this );
		$this->register_hooks();

	}

	/**
	 * Attach WordPress hook callbacks
	 *
	 * @return void
	 */
	public function register_hooks() {

		add_action( 'plugins_loaded', array( $this, 'add_cache_groups' ) );
		add_action( 'init', array( $this, 'init' ), 1 );
		add_action( 'widgets_init', array( $this, 'load_widget' ) );

	}

	/**
	 * Calls wp_cache_add_non_persistent_groups()
	 *
	 * Calling wp_cache_add_non_persistent_groups() doesn't appear to do anything
	 * in modern WordPress?
	 *
	 * @return void
	 */
	public function add_cache_groups() {
		if ( function_exists( 'wp_cache_add_non_persistent_groups' ) ) {
			wp_cache_add_non_persistent_groups( array( 'bu-navigation' ) );
		}
	}

	/**
	 * Initialization function for navigation plugin
	 *
	 * @hook init
	 * @return void
	 */
	public function init() {

		load_plugin_textdomain( 'bu-navigation', false, plugin_basename( dirname( __FILE__ ) ) . '/languages/' );

		if ( defined( 'BU_TS_IS_LOADED' ) ) {
			require_once BU_NAV_PLUGIN_DIR . '/tests/lettuce/sandbox-setup.php';
		}

		$this->load_extras();

		if ( is_admin() ) {
			$this->load_admin();
		}

		do_action( 'bu_navigation_init' );

	}

	/**
	 * Loads admin.php and instantiates the admin object.
	 *
	 * @return void
	 */
	public function load_admin() {

		require_once dirname( __FILE__ ) . '/admin/admin.php';
		$this->admin = new BU_Navigation_Admin( $this );

	}

	/**
	 * Initializes navigation widgets
	 *
	 * @return void
	 */
	public function load_widget() {

		if ( ! is_blog_installed() || ! $this->supports( 'widget' ) ) {
			return;
		}

		require_once dirname( __FILE__ ) . '/class-bu-widget-pages.php';
		register_widget( 'BU_Widget_Pages' );

	}

	/**
	 * Loads plugins for this... plugin
	 * Any .php file placed in the extras directory will be automatically loaded.
	 *
	 * @return void
	 */
	public function load_extras() {

		$pattern = sprintf( '%s/extras/*.php', BU_NAV_PLUGIN_DIR );

		$files = glob( $pattern );

		if ( ( is_array( $files ) ) && ( count( $files ) > 0 ) ) {
			foreach ( $files as $filename ) {
				@include_once $filename;
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
	 *  - turn on or off the navigation management interfaces ("Edit Order" pages, "Navigation Attributes" metabox)
	 *
	 * bu-navigation-widget (on by default)
	 *  - turn on or off the "Content Navigation" widget (on by default)
	 *
	 * bu-navigation-primary (off by default -- theme authors, use add_theme_support( 'bu-navigation-primary' ))
	 *  - turn on or off the "Primary Navigation" appearance menu item
	 *
	 * bu-navigation-links
	 *  - turn on or off the external link feature, include with 'page' post type nav menus (on by default)
	 *
	 * @return array Array of supported feature defaults.
	 */
	public function features() {

		return array(
			'manager' => true,
			'widget'  => true,
			'links'   => true,
			'primary' => false,
		);

	}

	/**
	 * Does the current install or theme support a navigation feature?
	 *
	 * There are two different ways to configure navigation features -- with PHP constants, or through the Theme Features API.
	 *
	 * These work as follows:
	 * 1. Define `BU_NAVIGATION_SUPPORTS_*` constant as true or false in wp-config.php or your theme's functions.php (highest priority)
	 * 2. Call add_theme_support( 'bu-navigation-*' ) within your theme's functions.php file (recommended for theme authors)
	 *
	 * @param string $feature Name of the feature.
	 * @return boolean Whether or not the feature is supported.
	 */
	public function supports( $feature ) {

		$feature  = strtolower( $feature );
		$defaults = $this->features();

		if ( ! in_array( $feature, array_keys( $defaults ) ) ) {
			$this->log( '%s - Unknown feature: %s', __METHOD__, $feature );
			return false;
		}

		$supported_const = 'BU_NAVIGATION_SUPPORTS_' . strtoupper( $feature );

		$disabled        = ( defined( $supported_const ) && constant( $supported_const ) == false );
		$supported       = ( defined( $supported_const ) && constant( $supported_const ) == true ) || $defaults[ $feature ];
		$theme_supported = current_theme_supports( 'bu-navigation-' . $feature );

		return ( ! $disabled && ( $supported || $theme_supported ) );

	}

	/**
	 * Gets the supported post_types by the bu-navigation plugin.
	 *
	 * @todo needs-unit-test
	 *
	 * @param boolean $include_link true|false link post_type is something special, so we don't always need it.
	 * @param string  $output type of output (names|objects).
	 * @return array of post_type strings or objects depending on $output param
	 */
	public function supported_post_types( $include_link = false, $output = 'names' ) {

		$post_types = get_post_types(
			array(
				'show_ui'      => true,
				'hierarchical' => true,
			), $output
		);
		$post_types = apply_filters( 'bu_navigation_post_types', $post_types );

		if ( $this->supports( 'links' ) && $include_link ) {
			if ( 'names' === $output ) {
				$post_types[ BU_NAVIGATION_LINK_POST_TYPE ] = BU_NAVIGATION_LINK_POST_TYPE;
			} else {
				$post_types[ BU_NAVIGATION_LINK_POST_TYPE ] = get_post_type_object( BU_NAVIGATION_LINK_POST_TYPE );
			}
		}

		return $post_types;

	}

	/**
	 * Log messages through error_log. Except when running through cli.
	 * Prepends [bu-navigation]
	 *
	 * @return null
	 */
	public function log() {
		if ( php_sapi_name() === 'cli' ) {
			return;
		}

		$args    = func_get_args();
		$args[0] = sprintf( '[bu-navigation] %s', $args[0] );
		error_log( call_user_func_array( 'sprintf', $args ) );
	}

}

// Instantiate plugin (only once).
if ( ! isset( $GLOBALS['bu_navigation_plugin'] ) ) {
	$GLOBALS['bu_navigation_plugin'] = new BU_Navigation_Plugin();
}
