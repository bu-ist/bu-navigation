<?php

/**
 * Administrative code loader
 *
 * Navigation admin componenents:
 * 	- Primary settings page - "Appearance > Primary Navigation" (bu-navigation-admin-primary.php)
 *  - Navigation Manager - "Edit Order" page (bu-navman.php)
 *  - Navigation attributes metabox (bu-navigation-admin-metabox.php)
 *  - Manage posts "Section" dropdown (bu-navigation-admin-filter-pages.php)
 */
class BU_Navigation_Admin {

	// Administrative component classes
	public $settings_page;
	public $navman;
	public $metabox;
	public $filter_pages;

	private $plugin;

	public function __construct( $plugin ) {

		$this->plugin = $plugin;

		// Attach all hooks
		$this->register_hooks();

	}

	public function register_hooks() {
		global $wp_version;

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

		// Componenents with menu items need to be registered for every admin request
		$this->load_primary_settings_page();

		$post_type = isset( $_GET['post_type'] ) ? $_GET['post_type'] : 'page';
		$this->load_navman_page( $post_type );

		// Other admin components can be loaded more selectively
		add_action( 'load-edit.php', array( $this, 'load_filter_pages' ) );
		add_action( 'load-post.php', array( $this, 'load_metaboxes' ) );
		add_action( 'load-post-new.php', array( $this, 'load_metaboxes' ) );

		// for WP 3.2: change delete_post to before_delete_post (because at that point, the children posts haven't moved up)
		if( version_compare( $wp_version, '3.2', '<' ) ) {
			add_action('delete_post', array( $this, 'handle_hidden_page_deletion'));
		} else {
			add_action('before_delete_post', array( $this, 'handle_hidden_page_deletion'));
		}

		if( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			add_action('wp_ajax_check_hidden_page', array( $this, 'ajax_check_hidden_page'));
		}

	}

	public function admin_scripts() {

		$screen = get_current_screen();

		if( in_array( $screen->base, array( 'edit', 'post' ) ) ) {

			$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
			$scripts_path = plugins_url('js',__FILE__);
			$vendor_path = plugins_url('js/vendor',__FILE__);

			$post_type = $this->plugin->get_post_type( $screen->post_type );

			// Intended for edit.php, post.php and post-new.php
			wp_enqueue_script( 'bu-page-parent-deletion', $scripts_path . '/deletion' . $suffix . '.js', array('jquery'), BU_Navigation_Plugin::VERSION );
			wp_localize_script( 'bu-page-parent-deletion', 'bu_navigation_pt_labels', $this->plugin->get_post_type_labels( $post_type ) );

		}

	}

	/**
	 * Primary plugin settings page
	 *
	 * Accessed via "Appearance > Primary Navigation" menu item
	 */
	public function load_primary_settings_page() {

		require_once(dirname(__FILE__) . '/bu-navigation-admin-primary.php');
		$this->settings_page = new BU_Navigation_Admin_Primary( $this->plugin );

	}

	/**
	 * Site navigation manager interface
	 *
	 * Accessed via the "Edit Order" menu item under support post type menus
	 */
	public function load_navman_page( $post_type = 'page' ) {

		require_once(dirname(__FILE__) . '/bu-navigation-admin-navman.php');
		$this->navman = new BU_Navigation_Admin_Navman( $post_type, $this->plugin );

		return $this->navman;
	}

	/**
	 * Filter manage post tables by section dropdown
	 *
	 * Found on the manage posts page (edit.php) for supported post types
	 */
	public function load_filter_pages() {

		$post_type = isset( $_GET['post_type'] ) ? $_GET['post_type'] : 'page';
		$post_parent = isset( $_GET['post_parent'] ) ? intval($_GET['post_parent']) : 0;

		if( in_array( $post_type, bu_navigation_supported_post_types() ) ) {

			require_once(dirname(__FILE__) . '/bu-navigation-admin-filter-pages.php');
			$this->filter_pages = new BU_Navigation_Admin_Filter_Pages( $post_type, $post_parent, $this->plugin );

		}

	}

	/**
	 * Navigation attributes meta box
	 *
	 * Displayed for supported post types.  Allows repositioning of page via modal tree interface, setting of
	 * navigation label, and toggling of display in nav menus.
	 */
	public function load_metaboxes() {

		$post_id = $post_type = null;

		$screen = get_current_screen();

		// Adding new post
		if( 'add' == $screen->action ) {

			$post_id = null;	// new post, no ID yet
			$post_type = isset( $_GET['post_type'] ) ? $_GET['post_type'] : 'post';

		} else {

			// Edit post request
			if( isset( $_GET['post'] ) ) {
				$post_id = intval( $_GET['post'] );
			}

			// Save post request
			else if( isset( $_POST['action'] ) && $_POST['action'] == 'editpost' ) {
				$post_id = intval($_POST['post_ID'] );
			}

			// Report any unexpected cases and bail
			else {
				error_log('Unexpected request for load_edit_post:' );
				error_log('REQUEST: ' . print_r( $_REQUEST, true ) );
				return;
			}

			// Get correct post type
			$post_type = $this->plugin->get_post_type( $post_id );

		}

		// Assert valid post ID and type before continuing
		if( is_null( $post_id ) && is_null( $post_type ) ) {
			error_log('BU Navigation Admin Metabox cannot be created without post ID and type');
			return;
		}

		// Load admin metabox class
		require_once(dirname(__FILE__) . '/bu-navigation-admin-metabox.php'); // Position & Visibility
		$this->metabox = new BU_Navigation_Admin_Metabox( $post_id, $post_type, $this->plugin );

	}

	/**
	 * @todo
	 *  - needs unit tests (selenium)
	 *
	 * when deleting posts that are hidden, the children will get moved up one level (to take place of the current post),
	 * but if they were hidden (as a result of the current post being hidden), they will become unhidden.
	 * So we must go through all the children and mark them hidden.
	 */
	public function handle_hidden_page_deletion($post_id) {
		global $wpdb;

		$post = get_post($post_id);
		if ( !in_array($post->post_type, bu_navigation_supported_post_types()) ) return;

		$exclude = get_post_meta($post_id, '_bu_cms_navigation_exclude', true);

		if ($exclude) {	// post was hidden
			error_log("$post_id is now exclude: $exclude");
			// get children
			$children_query = $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_parent = %d", $post_id);
			$children = $wpdb->get_results($children_query);

			// mark each hidden
			foreach ( (array) $children as $child ) {
				error_log("setting the child $post_id to exclude: $exclude");
				update_post_meta($child->ID, '_bu_cms_navigation_exclude', $exclude);
			}
		}
	}

	/**
	 * @todo
	 *  - needs unit tests (selenium)
	 *
	 * - prints json formatted data that tells the browser to either show a warning or not
	 * - dies.
	 */
	public function ajax_check_hidden_page() {
		global $wpdb;

		$response = array();
		$post_id = (int) $_POST['post_id'];
		$post = get_post($post_id);

		// case: not a supported post_type
		if ( !in_array($post->post_type, bu_navigation_supported_post_types()) ) {
			echo json_encode( array( 'ignore' => true ) );
			die;
		}

		// get post type labels
		$pt_labels = $this->plugin->get_post_type_labels( $post->post_type );

		// get children pages/links
		$page_children_query = $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_parent = %d AND post_type='$post->post_type'", $post_id);
		$page_children = $wpdb->get_results($page_children_query);
		$link_children_query = $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_parent = %d AND post_type='link'", $post_id);
		$link_children = $wpdb->get_results($link_children_query);

		// case no children, output the "ignore" flag
		if ( count($page_children) == 0 and count($link_children) == 0 ) {
			echo json_encode( array( 'ignore' => true, 'children' => 0 ) );
			die;
		}

		$hidden = get_post_meta($post_id, '_bu_cms_navigation_exclude', true);

		// case: wasn't hidden, output the "ignore" flag
		if ( !$hidden ) {
			echo json_encode( array( 'ignore' => true, 'children' => 0 ) );
			die;
		}

		// case: child pages and/or links exist
		// construct output msg based on how many child pages/links exist
		$msg = sprintf('"%s" is a hidden ' . strtolower($pt_labels['singular']) . ' with ', $post->post_title);
		$children_msgs = array();

		if (count($page_children) > 1) {
			$children_msgs['page'] = count($page_children) . " child " . strtolower($pt_labels['plural']);
		} else if ( count($page_children) == 1 ) {
			$children_msgs['page'] = "a child " . strtolower($pt_labels['singular']);
		}

		if (count($link_children) > 1) {
			$children_msgs['link'] = count($link_children) . " child links";
		} else if ( count($link_children) == 1 ) {
			$children_msgs['link'] = "a child link";
		}

		$children_msgs_vals = array_values($children_msgs);
		$children_msg = count($children_msgs) > 1 ? implode(' and ', $children_msgs_vals) : current($children_msgs);
		$msg .= $children_msg . ".";

		if ( isset( $children_msgs['page'] ) )
			$msg .= sprintf(' If you delete this %1$s, %2$s will move up one node in the %1$s hierarchy, and will autmatically be marked as hidden.', strtolower($pt_labels['singular']), $children_msgs['page']);

		if ( isset( $children_msgs['link'] ) )
			$msg .= sprintf(' If you delete this %1$s, %2$s will move up one node in the %1$s hierarchy, and will be displayed in navigation menus.', strtolower($pt_labels['singular']), $children_msgs['link']);

		$response = array (
			'ignore' => false,
			'msg' => $msg,
		);

		echo json_encode( $response );
		die;

	}

}
