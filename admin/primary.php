<?php

/**
 * BU Navigation Primary Navigation settings management interface
 */
class BU_Navigation_Admin_Primary {

	// Primary Navigation page hook
	public $page;

	// Reference to global plugin object
	private $plugin;

	public function __construct( $plugin ) {

		$this->plugin = $plugin;

		// Attach WP actions/filters
		$this->register_hooks();

	}

	/**
	* Attach WP actions and filters utilized by our meta boxes
	*/
	public function register_hooks() {

		add_action('admin_menu', array( $this, 'register_menu' ) );
		add_action('admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );

	}

	/**
	 * Add "Primary Navigation" settings page to "Appearance" or "Site Design" menu
	 */
	public function register_menu() {

		$this->page = add_submenu_page(
			'themes.php',
			__('Primary Navigation'),
			__('Primary Navigation'),
			$this->get_cap(),
			'bu-navigation-settings',
			array( $this, 'render' )
			);

	}

	/**
	 * Primary Navigation page styles
	 */
	public function enqueue_styles( $page ) {

		if( $page == $this->page ) {
			$styles_url = plugins_url( 'css', BU_NAV_PLUGIN );

			wp_enqueue_style( 'primary-navigation-admin', $styles_url . '/primary-navigation-admin.css', array(), BU_Navigation_Plugin::VERSION );
		}

	}

	/**
	 * Render "Primary Navigation" admin page
	 */
	public function render() {

		// Save first
		$saved = $this->save();

		$settings = $this->plugin->settings->get_all();

		// Initial values
		$bu_navigation_primarynav = $settings['display'];
		$bu_navigation_primarynav_max = $settings['max_items'];
		$bu_navigation_primarynav_dive = $settings['dive'];
		$bu_navigation_primarynav_depth = $settings['depth'];
		$bu_allow_top_level_page = $settings['allow_top'];

		// Maxiumum allowed depth, as dictated by theme or install constant
		$supported_depth = $this->max_supported_depth();

		include( BU_NAV_PLUGIN_DIR . '/templates/primary-navigation.php' );

	}

	/**
	 * Handle $_POST to "Primary Navigation" admin page
	 */
	public function save() {
		$saved = NULL;

		if( ( array_key_exists( 'bu_navigation_primary_save', $_POST ) ) && ( $_POST['bu_navigation_primary_save'] == 'save' ) ) {

			if( ! current_user_can( $this->get_cap() ) ) {
				return false;
			}

			$saved = TRUE;

			$primarynav_display = isset($_POST['bu_navigation_primarynav']) ? intval($_POST['bu_navigation_primarynav']) : 0;

			// primarynav maximum items
			$primarynav_max = absint($_POST['bu_navigation_primarynav_max']);
			if (!$primarynav_max) $primarynav_max = BU_NAVIGATION_PRIMARY_MAX;

			// primarynav maximum items: error handling
			if ($primarynav_max != $_POST['bu_navigation_primarynav_max']) {
				$saved = array(
					'success' => false,
					'msg' => sprintf('The value "%s" entered for "Maximum items" is not correct.', esc_html($_POST['bu_navigation_primarynav_max']), $primarynav_max)
				);
				return $saved;
			}

			$primarynav_dive = isset($_POST['bu_navigation_primarynav_dive']) ? intval($_POST['bu_navigation_primarynav_dive']) : 0;
			$primarynav_depth = isset($_POST['bu_navigation_primarynav_depth']) ? intval($_POST['bu_navigation_primarynav_depth']) : 0;
			$bu_allow_top_level_page = isset($_POST['bu_allow_top_level_page']) ? intval($_POST['bu_allow_top_level_page']) : 0;

			// Prevent depth setting from exceeding limit set by theme or install
			$max_depth = $this->max_supported_depth();

			if( $primarynav_depth > $max_depth )
				$primarynav_depth = $max_depth;

			$updates = array(
				'display' => (int) $primarynav_display,
				'max_items' => (int) $primarynav_max,
				'dive' => (int) $primarynav_dive,
				'depth' => (int) $primarynav_depth,
				'allow_top' => (int) $bu_allow_top_level_page
				);

			$this->plugin->settings->update( $updates );

			$bu_navigation_changes_saved = true;

			if (function_exists('invalidate_blog_cache')) invalidate_blog_cache();

		}

		return $saved;

	}

	/**
	 * Return the current max primary navigation depth
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
	public function max_supported_depth() {

		$override_const = defined( 'BU_NAVIGATION_SUPPORTED_DEPTH' ) ? BU_NAVIGATION_SUPPORTED_DEPTH : null;
		$override_theme = get_theme_support( 'bu-navigation-primary' );

		// Get default primary navigation settings
		$defaults = $this->plugin->settings->primary_nav_defaults();
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
	 * Default capability for accessing this page
	 *
	 * Note: BU has a special capability to limit access
	 */
	public function get_cap() {

		if ( defined( 'BU_CMS' ) && BU_CMS == true ) {
			return 'bu_edit_options';
		} else {
			return 'edit_theme_options';
		}

	}

}
