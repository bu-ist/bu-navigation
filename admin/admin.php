<?php

/**
 * Administrative code loader
 *
 * Navigation admin componenents:
 * 	- Primary settings page - "Appearance > Primary Navigation" (bu-navigation-admin-primary.php)
 *  - Navigation Manager - "Edit Order" page (bu-navigation-admin-navman.php)
 *  - Navigation attributes metabox (bu-navigation-admin-metabox.php)
 *  - Manage posts "Section" dropdown (bu-navigation-admin-filter-pages.php)
 */
class BU_Navigation_Admin {

	// Administrative component classes
	public $settings_page;
	public $navman;
	public $edit_post;
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

		// Components with menu items need to be registered for every admin request
		if ( $this->plugin->supports( 'primary' ) )
			$this->load_primary();

		$post_type = isset( $_GET['post_type'] ) ? $_GET['post_type'] : 'page';

		// Load navigation manager interfaces
		if ( $this->plugin->supports( 'manager' ) ) {

			// Navigation manager screen
			$this->load_manager( $post_type );

			// Edit post screens
			add_action( 'load-post.php', array( $this, 'load_edit_post' ) );
			add_action( 'load-post-new.php', array( $this, 'load_edit_post' ) );

		}

		// Other admin components can be loaded more selectively
		add_action( 'load-edit.php', array( $this, 'load_filter_pages' ) );

		// for WP 3.2: change delete_post to before_delete_post (because at that point, the children posts haven't moved up)
		if( version_compare( $wp_version, '3.2', '<' ) ) {
			add_action( 'delete_post', array( $this, 'handle_hidden_page_deletion' ) );
		} else {
			add_action( 'before_delete_post', array( $this, 'handle_hidden_page_deletion' ) );
		}

		if( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			add_action( 'wp_ajax_check_hidden_page', array( $this, 'ajax_check_hidden_page' ) );
		}

	}

	public function admin_scripts() {

		$screen = get_current_screen();

		// Intended for edit.php, post.php and post-new.php
		if( in_array( $screen->base, array( 'edit', 'post' ) ) ) {

			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '.dev' : '';
			$scripts_url = plugins_url( 'js', BU_NAV_PLUGIN );

			$post_type = get_post_type_object( $screen->post_type );
			$strings = array(
				'confirmDeleteSingular' => sprintf( __( 'Are you sure you want to delete this %s?', 'bu-navigation' ), strtolower( $post_type->labels->singular_name ) ),
				'confirmDeletePlural' => sprintf( __( 'Are you sure you want to delete these %s?', 'bu-navigation' ), strtolower( $post_type->labels->name ) )
				);

			wp_enqueue_script( 'bu-page-parent-deletion', $scripts_url . '/deletion' . $suffix . '.js', array('jquery'), BU_Navigation_Plugin::VERSION, true );
			wp_localize_script( 'bu-page-parent-deletion', 'bu_page_parent_deletion', $strings );

		}

	}

	/**
	 * Primary plugin settings page
	 *
	 * Accessed via "Appearance > Primary Navigation" menu item
	 */
	public function load_primary() {

		require_once( dirname( __FILE__ ) . '/primary.php' );
		$this->settings_page = new BU_Navigation_Admin_Primary( $this->plugin );

	}

	/**
	 * Site navigation manager interface
	 *
	 * Accessed via the "Edit Order" menu item under support post type menus
	 */
	public function load_manager( $post_type = 'page' ) {

		require_once( dirname( __FILE__ ) . '/manager.php' );
		$this->navman = new BU_Navigation_Admin_Manager( $post_type, $this->plugin );

		return $this->navman;
	}

	/**
	 * Navigation attributes edit post interface
	 *
	 * @todo test with BU Versions
	 *
	 * Displayed for supported post types.  Allows repositioning of page via modal tree interface, setting of
	 * navigation label, and toggling of display in nav menus.
	 */
	public function load_edit_post() {

		// Load admin post class
		require_once( dirname( __FILE__ ) . '/post.php' );
		$this->edit_post = new BU_Navigation_Admin_Post( $this->plugin );

	}

	/**
	 * Filter manage post tables by section dropdown
	 *
	 * @todo incorporate filter posts dropdown in to this class
	 *
	 * Found on the manage posts page (edit.php) for supported post types
	 */
	public function load_filter_pages() {

		$post_type = isset( $_GET['post_type'] ) ? $_GET['post_type'] : 'post';
		$post_parent = isset( $_GET['post_parent'] ) ? intval($_GET['post_parent']) : 0;

		if( in_array( $post_type, $this->plugin->supported_post_types() ) ) {

			require_once( dirname( __FILE__ ) . '/filter-pages.php' );
			$this->filter_pages = new BU_Navigation_Admin_Filter_Pages( $post_type, $post_parent, $this->plugin );

		}

	}

	/**
	 * @todo
	 *  - needs unit tests (selenium)
	 *
	 * when deleting posts that are hidden, the children will get moved up one level (to take place of the current post),
	 * but if they were hidden (as a result of the current post being hidden), they will become unhidden.
	 * So we must go through all the children and mark them hidden.
	 */
	public function handle_hidden_page_deletion( $post_id ) {
		global $wpdb;

		$post = get_post( $post_id );
		if ( ! in_array( $post->post_type, $this->plugin->supported_post_types() ) )
			return;

		$exclude = bu_navigation_post_excluded( $post );

		if ( $exclude ) {	// post was hidden
			// get children
			$children_query = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_parent = %d", $post_id );
			$children = $wpdb->get_results( $children_query );

			// mark each hidden
			foreach ( (array) $children as $child ) {
				update_post_meta( $child->ID, BU_NAV_META_PAGE_EXCLUDE, (int) $exclude );
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
		$post = get_post( $post_id );

		// case: not a supported post_type
		if ( ! in_array( $post->post_type, $this->plugin->supported_post_types() ) ) {
			echo json_encode( array( 'ignore' => true ) );
			die;
		}

		// get post type labels
		$post_type = get_post_type_object( $post->post_type );

		// get children pages/links
		$page_children_query = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_parent = %d AND post_type='$post->post_type'", $post_id );
		$page_children = $wpdb->get_results( $page_children_query );
		$link_children_query = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_parent = %d AND post_type='".BU_NAVIGATION_LINK_POST_TYPE."'", $post_id );
		$link_children = $wpdb->get_results( $link_children_query );

		// case no children, output the "ignore" flag
		if ( count( $page_children ) == 0 && count( $link_children ) == 0 ) {
			echo json_encode( array( 'ignore' => true, 'children' => 0 ) );
			die;
		}

		$hidden = bu_navigation_post_excluded( $post );

		// case: wasn't hidden, output the "ignore" flag
		if ( ! $hidden ) {
			echo json_encode( array( 'ignore' => true, 'children' => 0 ) );
			die;
		}

		// case: child pages and/or links exist
		// construct output msg based on how many child pages/links exist
		$msg = sprintf( __( '"%s" is a hidden %s with ', 'bu-navigation' ), $post->post_title, strtolower( $post_type->labels->singular_name ) );
		$children_msgs = array();

		if ( count( $page_children ) >= 1 ) {
			$children_msgs['page'] = sprintf( _n( 'a child ', '%d child ', count( $page_children ), 'bu-navigation' ), count( $page_children ) );
			$children_msgs['page'] .= ( count( $page_children ) == 1 ) ? strtolower( $post_type->labels->singular_name ) : strtolower( $post_type->labels->name );
		}

		if ( count( $link_children ) >= 1 ) {
			$children_msgs['link'] = sprintf( _n( 'a child link', '%d child links', count( $link_children ), 'bu-navigation' ), count( $link_children ) );
		}

		$children_msgs_vals = array_values( $children_msgs );
		$children_msg = count( $children_msgs ) > 1 ? implode( __( ' and ', 'bu-navigation' ), $children_msgs_vals ) : current( $children_msgs );
		$msg .= $children_msg . ".";

		if ( isset( $children_msgs['page'] ) )
			$msg .= sprintf( __(' If you delete this %1$s, %2$s will move up one node in the %1$s hierarchy, and will automatically be marked as hidden.', 'bu-navigation' ),
				strtolower( $post_type->labels->singular_name ),
				$children_msgs['page']
				);

		if ( isset( $children_msgs['link'] ) )
			$msg .= sprintf( __(' If you delete this %1$s, %2$s will move up one node in the %1$s hierarchy, and will be displayed in navigation menus.', 'bu-navigation' ),
				strtolower( $post_type->labels->singular_name ),
				$children_msgs['link']
				);

		$response = array(
			'ignore' => false,
			'msg' => $msg
			);

		echo json_encode( $response );
		die;

	}

}
