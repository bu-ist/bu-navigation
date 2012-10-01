<?php

require_once(dirname(__FILE__) . '/bu-navigation-interface.php'); // bu jstree config

/**
 * Administrative code loader
 * 
 * Navigation admin componenents:
 * 	- Primary settings page - "Appearance > Primary Navigation" (bu-navigation-admin-primary.php)
 *  - Navigation Manager - "Edit Order" page (bu-navman.php)
 *  - Navigation attributes metabox (bu-navigation-admin-metabox.php)
 *  - Manage posts "Section" dropdown (bu-filter-pages.php)
 */ 
class BU_Navigation_Admin {

	// Administrative component classes
	static $settings_page;
	static $navman;	// @todo implement
	static $metabox;
	static $manage_posts; // @todo implement

	public function __construct() {

		// Attach all hooks
		$this->register_hooks();

	}

	public function register_hooks() {

		// Componenents with menu items need to be registered for every admin request
		$this->load_primary_settings_page();
		$this->load_navman_page();

		// Other admin components can be loaded more selectively
		add_action( 'load-edit.php', array( $this, 'load_filter_pages' ) );
		add_action( 'load-post.php', array( $this, 'load_metaboxes' ) );
		add_action( 'load-post-new.php', array( $this, 'load_metaboxes' ) );

	}

	/**
	 * Primary plugin settings page
	 * 
	 * Accessed via "Appearance > Primary Navigation" menu item
	 */ 
	public function load_primary_settings_page() {

		require_once(dirname(__FILE__) . '/bu-navigation-admin-primary.php');
		self::$settings_page = new BU_Navigation_Admin_Primary();

	}

	/**
	 * Site navigation manager interface
	 * 
	 * Accessed via the "Edit Order" menu item under support post type menus
	 */ 
	public function load_navman_page() {

		require_once(dirname(__FILE__) . '/bu-navman.php'); // Navman "Edit Order" interface, custom admin page
		// self::$navman = new BU_Navigation_Admin_Navman();

	}

	/**
	 * Filter manage post tables by section dropdown
	 * 
	 * Found on the manage posts page (edit.php) for supported post types
	 */ 
	public function load_filter_pages() {

		// @todo maybe be more selective in WHICH edit.php pages we load section dropdown on
		require_once(dirname(__FILE__) . '/bu-filter-pages.php'); // Filter pages, only needed on manage posts
		// self::$manage_posts = new BU_Navigation_Admin_ManagePosts();

	}

	/**
	 * Navigation attributes meta box
	 * 
	 * Displayed for supported post types.  Allows repositioning of page via modal tree interface, setting of
	 * navigation label, and toggling of display in nav menus.
	 */ 
	public function load_metaboxes() {
		global $pagenow;

		$post_id = $post_type = null;

		// Editing existing post
		if( 'post.php' == $pagenow ) {

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
			$post_type = $this->get_post_type( $post_id ); 

		// Adding new post
		} else if( 'post-new.php' == $pagenow ) {

			$post_id = null;	// new post, no ID yet
			$post_type = isset( $_GET['post_type'] ) ? $_GET['post_type'] : 'post';

		}

		// Assert valid post ID and type before continuing
		if( is_null( $post_id ) && is_null( $post_type ) ) {
			error_log('BU Navigation Admin Metabox cannot be created without post ID and type');
			return;
		}

		// Load admin metabox class
		require_once(dirname(__FILE__) . '/bu-navigation-admin-metabox.php'); // Position & Visibility

		// Instantiate for current post
		self::$metabox = new BU_Navigation_Admin_Metabox( $post_id, $post_type );

	}

	/**
	 * Returns the original post type for an existing post
	 * 
	 * @param mixed $post post ID or post object
	 * @return string $post_type post type name
	 */ 
	protected function get_post_type( $post ) {

		if( is_numeric( $post ) ) {
			$post = get_post( $post );
		}

		$post_type = $post->post_type;

		// @todo add BU Versions logic here
		return $post_type;

	}

}