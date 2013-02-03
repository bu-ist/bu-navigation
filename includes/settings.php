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
	 */
	public function primary_nav_defaults( $defaults = array() ) {

		$defaults['echo'] = $this->get('display');
		$defaults['depth'] = $this->get('depth');
		$defaults['max_items'] = $this->get('max_items');
		$defaults['dive'] = $this->get('dive');

		return $defaults;

	}

}