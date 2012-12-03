<?php

/**
 * BU Navigation Admin Settings interface
 */
class BU_Navigation_Admin_Primary {

	public $page;
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

	public function register_menu() {

		// Add primary navigation settings page
		$this->page = add_submenu_page(
			'themes.php',
			__('Primary Navigation'),
			__('Primary Navigation'),
			$this->get_cap(),
			'bu-navigation-settings',
			array( $this, 'render' )
			);

	}

	public function get_cap() {
		if ( defined( 'BU_CMS' ) && BU_CMS == true ) {
			return 'bu_edit_options';
		} else {
			return 'edit_theme_options';
		}
	}


	public function enqueue_styles( $page ) {

		if( $page == $this->page ) {
			wp_enqueue_style( 'primary-navigation-admin', plugins_url( '/css/primary-navigation-admin.css', __FILE__ ), array(), BU_Navigation_Plugin::VERSION );
		}

	}

	public function render() {

		// Save first
		$saved = $this->save();

		$settings = $this->plugin->get_settings();

		/* default options */
		$bu_navigation_primarynav = $settings['display'];
		$bu_navigation_primarynav_max = $settings['max_items'];
		$bu_navigation_primarynav_dive = $settings['dive'];
		$bu_navigation_primarynav_depth = $settings['depth'];
		$bu_allow_top_level_page = $settings['allow_top'];

		require_once(BU_NAV_PLUGIN_DIR . '/interface/primary-navigation-admin.php');

	}

	public function save() {
		$saved = NULL;

		if( ( array_key_exists( 'bu_navigation_primary_save', $_POST ) ) && ( $_POST['bu_navigation_primary_save'] == 'save' ) ) {

			if( ! current_user_can( $this->get_cap() ) ) {
				return false;
			}

			$saved = TRUE; /* no useful return from update_option */

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

			$updates = array(
				'display' => (int) $primarynav_display,
				'max_items' => (int) $primarynav_max,
				'dive' => (int) $primarynav_dive,
				'depth' => (int) $primarynav_depth,
				'allow_top' => (int) $bu_allow_top_level_page
				);

			$this->plugin->update_settings( $updates );

			$bu_navigation_changes_saved = true;

			if (function_exists('invalidate_blog_cache')) invalidate_blog_cache();

		}

		return $saved;

	}
}

?>
