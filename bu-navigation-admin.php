<?php

require_once(dirname(__FILE__) . '/bu-navigation-interface.php'); // bu jstree config

/**
 * Administrative code loader
 */ 
class BU_Navigation_Admin {

	// Administrative page component classes
	static $metabox;
	static $navman;	// @todo implement
	static $manage_posts; // @todo implement
	static $primary_nav_settings;	// @todo implement

	public function __construct() {

		// Attach all hooks
		$this->register_hooks();

	}

	public function register_hooks() {

		include(dirname(__FILE__) . '/bu-navman.php'); // Navman "Edit Order" interface, custom admin page
		// self::$navman = new BU_Navigation_Admin_Navman();

		// Add menu items
		// @todo self::$navman->register_hooks()
		// `--> move menu code to navman class
		add_action('admin_menus', 'setup_menus' );

		// Manage posts section dropdown
		add_action( 'load-edit.php', array( $this, 'load_manage_posts' ) );

		// Edit post navigation metabox
		add_action( 'load-post.php', array( $this, 'load_edit_post' ) );
		add_action( 'load-post-new.php', array( $this, 'load_edit_post' ) );

	}

	public function setup_menus() {

		// Add "Edit Order" links to the submenu of each supported post type
		$post_types = bu_navigation_supported_post_types();

		foreach( $post_types as $pt ) {
			$parent_slug = 'edit.php?post_type=' . $pt;
			// @todo loads for array( self::$navman, 'render' )
			$hook = add_submenu_page($parent_slug, null, 'Edit Order', 'edit_pages', __FILE__, 'bu_navman_admin_menu_display');
		}
			
		// @todo find a better place for this
		bu_navman_clear_lock();

	}

	public function load_manage_posts() {

		// @todo maybe be more selective in WHICH edit.php pages we load section dropdown on
		include(dirname(__FILE__) . '/bu-filter-pages.php'); // Filter pages, only needed on manage posts
		// self::$manage_posts = new BU_Navigation_Admin_ManagePosts();

	}

	public function load_edit_post() {
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
		include(dirname(__FILE__) . '/bu-navigation-admin-metabox.php'); // Position & Visibility

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