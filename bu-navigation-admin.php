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

		include(dirname(__FILE__) . '/bu-navman.php'); // Navman "Edit Order" interface, custom admin page
		// self::$navman = new BU_Navigation_Admin_Navman();

		// Add menu items
		// @todo self::$navman->register_hooks()
		// `--> move menu code to navman class
		add_action('admin_menus', 'setup_menus' );

		$this->register_hooks();

	}

	public function register_hooks() {

		// Edit post screen
		add_action('load-edit.php', array( $this, 'load_manage_posts' ) );

		// Single post edit screen
		add_action('load-post.php', array( $this, 'load_edit_post' ) );
		add_action('load-post-new.php', array( $this, 'load_edit_post' ) );

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

		// Need a valid post ID to continue
		if( ! isset( $_GET['post'] ) )
			return;

		$post_id = intval($_GET['post']);

		include(dirname(__FILE__) . '/bu-navigation-admin-metabox.php'); // Position & Visibility

		self::$metabox = new BU_Navigation_Admin_Metabox( $post_id );

	}

}