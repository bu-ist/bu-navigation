<?php

/**
 * BU Navigation Primary Navigation settings management interface
 */
class BU_Navigation_Admin_Primary {

	// Primary Navigation page hook
	public $page;

	// Reference to global plugin object
	private $plugin;

	const NONCE_ACTION = 'bu-nav-primary-settings-update';

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
	 * Add "Primary Navigation" settings page to "Appearance" menu
	 */
	public function register_menu() {

		$this->page = add_submenu_page(
			'themes.php',
			__( 'Primary Navigation', 'bu-navigation' ),
			__( 'Primary Navigation', 'bu-navigation' ),
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

		// Handle $_POST first
		$status = $this->save();

		// Initial values
		$settings = $this->plugin->settings->get_all();

		// Maxiumum allowed depth, as dictated by theme or install constant
		$supported_depth = $this->plugin->settings->max_supported_depth();

		// Theme changes can affect this setting -- force the value to be within legal bounds prior to display
		if ( $settings['depth'] > $supported_depth )
			$settings['depth'] = $supported_depth;

		$nonce = self::NONCE_ACTION;

		include( BU_NAV_PLUGIN_DIR . '/templates/primary-navigation.php' );

	}

	/**
	 * Handle $_POST to "Primary Navigation" admin page
	 */
	public function save() {

		if ( empty( $_POST ) )
			return;

		// Prevent illegal updates
		if( ! current_user_can( $this->get_cap() ) || ! check_admin_referer( self::NONCE_ACTION ) ) {
			error_log('[bu-navigation] Illegal access to "Primary Navigaiton" page!');
			wp_die('Cheatin, eh?');
		}

		// Navigation settings have beenu pdated
		if( array_key_exists( 'bu-nav-settings', $_POST ) ) {

			$success = true;
			$errors = array();

			// Grab defaults for merging
			$defaults = $this->plugin->settings->defaults();

			// Sanitize
			$updates = $_POST['bu-nav-settings'];

			// Text fields
			$updates['max_items'] = isset( $updates['max_items'] ) ? absint( sanitize_text_field( $updates['max_items'] ) ) : $defaults['max_items'];
			$updates['depth'] = isset( $updates['depth'] ) ? absint( sanitize_text_field( $updates['depth'] ) ) : $defaults['depth'];

			// Checkboxes
			$updates['display'] = isset( $updates['display'] ) ? (bool) $updates['display'] : 0;
			$updates['dive'] = isset( $updates['dive'] ) ? (bool) $updates['dive'] : 0;
			$updates['allow_top'] = isset( $updates['allow_top'] ) ? (bool) $updates['allow_top'] : 0;

			// Valdidate

			// Force positive values for max items
			if( array_key_exists( 'max_items', $_POST['bu-nav-settings'] ) && $updates['max_items'] !== (int) $_POST['bu-nav-settings']['max_items'] ) {
				$errors[] = __( 'The "Maximum Items" setting most be a positive value.', 'bu-navigation' );
				$success = false;
			}

			// Prevent depth setting from exceeding limit set by theme or install
			$max_depth = $this->plugin->settings->max_supported_depth();

			if( $updates['depth'] > $max_depth )
				$updates['depth'] = $max_depth;

			if( array_key_exists( 'depth', $_POST['bu-nav-settings'] ) && $updates['depth'] !== (int) $_POST['bu-nav-settings']['depth'] ) {
				$errors[] = sprintf(
					_n( 'The current theme only supports %s level of children.',
						'The current theme only supports up to %s levels of children.',
						$max_depth, 'bu-navigation'  ),
					$max_depth );
				$success = false;
			}

			// Update
			if( $success && empty( $errors ) ) {
				$this->plugin->settings->update( $updates );
			}

			return array( 'success' => $success, 'errors' => $errors );

		}

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
