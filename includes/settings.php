<?php

class BU_Navigation_Settings {

	// Reference to plugin
	private $plugin;

	// Plugin settings option names
	const OPTION_DISPLAY = 'bu_navigation_primarynav';
	const OPTION_MAX_ITEMS = 'bu_navigation_primarynav_max';
	const OPTION_DIVE = 'bu_navigation_primarynav_dive';
	const OPTION_DEPTH = 'bu_navigation_primarynav_depth';
	const OPTION_ALLOW_TOP = 'bu_allow_top_level_page';

	public function __construct( $plugin ) {

		$this->plugin = $plugin;

		// Filter plugin settings utilized by bu_navigation_display_primary function
		// @todo move to primary navigation class
		add_filter( 'bu_filter_primarynav_defaults', array( $this, 'primary_nav_defaults' ) );

	}

	/**
	 * Get a single plugin setting by slug
	 */
	public function get( $name ) {

		$settings = $this->get_all();

		if( array_key_exists( $name, $settings ) )
			return $settings[$name];

		return false;

	}

	/**
	 * Get all plugin settings
	 */
	public function get_all() {

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
	public function update( $updates ) {

		$settings = $this->get_all();

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
	 *
	 * Useful for unit tests that want to check actual DB values
	 */
	public function clear() {

		$this->settings = array();

	}

	/**
	 * Filter the navigation settings used by bu_navigation_display_primary to
	 * utilize plugin settings
	 *
	 * @todo move to primary navigation class
	 */
	public function primary_nav_defaults( $defaults = array() ) {

		$defaults['echo'] = $this->get('display');
		$defaults['depth'] = $this->get('depth');
		$defaults['max_items'] = $this->get('max_items');
		$defaults['dive'] = $this->get('dive');

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

}