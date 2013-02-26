<?php

class BU_Navigation_Settings {

	// Reference to plugin
	private $plugin;

	// Internal settings cache
	private $settings = array();

	// Individual option fields structure
	private static $fields = array(
		'display' 	=> array( 'option' => 'bu_navigation_primarynav', 'type' => 'bool', 'default' => true ),
		'max_items' => array( 'option' => 'bu_navigation_primarynav_max', 'type' => 'int', 'default' => BU_NAVIGATION_PRIMARY_MAX ),
		'dive' 		=> array( 'option' => 'bu_navigation_primarynav_dive', 'type' => 'bool', 'default' => true ),
		'depth' 	=> array( 'option' => 'bu_navigation_primarynav_depth', 'type' => 'int', 'default' => BU_NAVIGATION_PRIMARY_DEPTH ),
		'allow_top' => array( 'option' => 'bu_allow_top_level_page', 'type' => 'bool', 'default' => true )
		);

	public function __construct( $plugin ) {

		$this->plugin = $plugin;

		// Filter plugin settings utilized by bu_navigation_display_primary function
		add_filter( 'bu_filter_primarynav_defaults', array( $this, 'primary_nav_defaults' ) );

	}

	/**
	 * Default option values for navigation settings fields
	 *
	 * @return array default values, keyed on field name
	 */
	public function defaults() {

		$defaults = array();

		foreach( self::$fields as $name => $field )
			$defaults[$name] = $field['default'];

		return $defaults;

	}

	/**
	 * Get a single plugin setting by slug
	 *
	 * @param string $name field name to retrieve
	 *
	 * @return mixed requested setting value
	 */
	public function get( $name ) {

		// Sanity check
		if( ! array_key_exists( $name, self::$fields ) ) {
			error_log("[bu-navigation] Attempt to access invalid settings key: $name");
			return false;
		}

		// Check internal cache first
		if( array_key_exists( $name, $this->settings ) )
			return $this->settings[$name];

		// Fetch from DB, prepare for use and cache internally
		$field = self::$fields[$name];
		$val = get_option( $field['option'], $field['default'] );
		$val = $this->prepare( $field, $val );

		$this->settings[$name] = $val;

		return $val;

	}

	/**
	 * Get all plugin settings
	 *
	 * @return array all plugin settings, keyed on field names
	 */
	public function get_all() {

		$fields = array_keys( self::$fields );

		foreach( $fields as $name )
			$this->get( $name );

		return $this->settings;

	}

	/**
	 * DRY helper for cleaning values returned from the database before use
	 *
	 * @param array $field array structure representing the field being prepared
	 * @param mixed $value value to prepare
	 *
	 * @return mixed prepared value
	 */
	private function prepare( $field, $value ) {

		switch( $field['type'] ) {
			case 'bool':
				$value = (bool) $value;
				break;

			case 'int':
				$value = intval( $value );
				break;

			case 'string':
			default:
				$value = trim( $value );
				break;
		}

		return $value;
	}

	/**
	 * Update plugin settings
	 *
	 * @param array $updates variable length structure of fields to udpate, field name => value
	 */
	public function update( $updates ) {

		foreach( $updates as $key => $val ) {

			if( array_key_exists( $key, self::$fields ) ) {

				// Cooerce booleans into ints for update_option
				if( is_bool( $val ) ) $val = intval( $val );

				// Commit to db
				$option = self::$fields[$key]['option'];
				$result = update_option( $option, $val );

				// Update internal settings on successful commit
				if( $result ) {

					// Update internal settings property
					$this->settings[$key] = $val;

				}

			} else {

				error_log("[bu-navigation] Attempt to update invalid settings key: $key");

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
	 * Return the current max primary navigation depth
	 *
	 * The depth can be set by:
	 *  BU_NAVIGATION_SUPPORTED_DEPTH constant (highest priority)
	 *  'bu-navigation-primary'
	 *
	 * Themes calling add_theme_support( 'bu-navigation-primary' ) can pass an associative array as a second argument.
	 * At this time, only one option is configurable via theme support:
	 *
	 * 	'depth' - Maximum level of children to support in navigation lists
	 *
	 * @return int max supported primary navigation depth
	 */
	public function max_supported_depth() {

		$override_const = defined( 'BU_NAVIGATION_SUPPORTED_DEPTH' ) ? BU_NAVIGATION_SUPPORTED_DEPTH : null;
		$override_theme = get_theme_support( 'bu-navigation-primary' );

		$defaults = array(
			'depth' => self::$fields['depth']['default']
			);
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