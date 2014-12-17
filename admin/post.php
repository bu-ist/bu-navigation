<?php

/**
 * BU Navigation Admin Post controller
 *
 * Loaded while editing posts
 *
 * Handles rendering and behavior of the navigation attributes metabox
 * 	-> Setting post parent and order
 *  -> Setting navigation label
 * 	-> Showing/hiding of post in navigation menus
 *
 * @todo
 *  - Add "Help" for navigation, label, visibilty
 */
class BU_Navigation_Admin_Post {

	public $post_id;
	public $post;
	public $post_type;

	private $plugin;

	public function __construct( $plugin ) {

		$this->plugin = $plugin;

		// Use current screen to determine post info
		$screen = get_current_screen();

		// Prior to WP < 3.3 the current screen object can not be used to reliably determine current post type at the time the load-* actions are fired.
		// Otherwise, we'd just do this...
		// $this->post_type = $screen->post_type;

		// Determine current post and post type
		if ( 'add' == $screen->action ) {
			$this->post_id = 0;
			$this->post_type = isset( $_GET['post_type'] ) ? $_GET['post_type'] : 'post';
		} else if ( isset( $_GET['post'] ) ) {
			$this->post_id = $_GET['post'];
		} else if ( isset( $_POST['post_ID'] ) ) {
			$this->post_id = $_POST['post_ID'];
		}

		if ( $this->post_id ) {
			$this->post = get_post( $this->post_id );
			if ( ! is_object( $this->post ) || ! isset( $this->post->post_type ) )
				return;
			$this->post_type = $this->post->post_type;
		}

		// Only continue with a valid and supported post type
		if ( in_array( $this->post_type, $this->plugin->supported_post_types() ) ) {

			// Attach WP actions/filters
			$this->register_hooks();

		}

	}

	/**
	 * Attach WP actions and filters utilized by our meta boxes
	 */
	public function register_hooks() {

		add_action( 'admin_enqueue_scripts', array( $this, 'add_scripts' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );

	}

	/**
	 * Load metabox scripts
	 */
	public function add_scripts( $page ) {

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$scripts_url = plugins_url( 'js', BU_NAV_PLUGIN );
		$styles_url = plugins_url( 'css', BU_NAV_PLUGIN );

		// Scripts
		wp_register_script( 'bu-navigation-metabox', $scripts_url . '/navigation-metabox' . $suffix . '.js', array('bu-navigation', 'thickbox', 'media-upload'), BU_Navigation_Plugin::VERSION, true );

		// Setup dynamic script context for navigation-metabox.js
		$post_type = get_post_type_object( $this->post_type );
		$ancestors = $this->get_formatted_ancestors();

		// Strings for localization
		$nav_menu_label = __( 'Appearance > Primary Navigation', 'bu-navigation' );
		$strings = array(
			'topLevelDisabled' => sprintf( __( 'Displaying top-level %s in navigation lists is currently disabled.', 'bu-navigation' ), strtolower( $post_type->labels->name ) ),
			'topLevelNotice' => sprintf( __( 'To change this behavior, visit %s and enable the "Allow Top-Level Pages" setting.', 'bu-navigation' ), $nav_menu_label ),
			'topLevelLabel' => sprintf( __( 'Top level %s', 'bu-navigation' ), strtolower( $post_type->labels->singular_name ) )
			);

		$script_context = array(
			'postTypes' => $this->post_type,
			'postStatuses' => array( 'publish', 'private' ),
			'currentPost' => $this->post_id,
			'ancestors' => $ancestors,
			'lazyLoad' => false,
			'showCounts' => false,
			'nodePrefix' => 'na',
			'deselectOnDocumentClick' => false,
			);
		// Navigation tree view will handle actual enqueuing of our script
		$treeview = new BU_Navigation_Tree_View( 'nav_metabox', array_merge( $script_context, $strings ) );
		$treeview->enqueue_script( 'bu-navigation-metabox' );

		// Styles
		wp_enqueue_style( 'bu-navigation-metabox', $styles_url . '/navigation-metabox.css', array(), BU_Navigation_Plugin::VERSION );

	}

	/**
	 * Register navigation metaboxes for supported post types
	 *
	 * @todo needs selenium tests
	 */
	public function add_meta_boxes( $post_type, $post ) {
		$post_type_object = get_post_type_object( $post_type );

		// Remove built in page attributes meta box
		remove_meta_box('pageparentdiv', 'page', 'side');

		$templates = get_page_templates();

		if ( 'page' === $post_type && is_array( $templates ) && ( count( $templates ) > 0 ) ) {
			add_meta_box(
				'bupagetemplatediv',
				sprintf( __( "%s Template", 'bu-navigation'  ), $post_type_object->labels->singular_name ),
				array($this, 'display_custom_template'),
				$post_type,
				'side',
				'core'
				);
		}

		add_meta_box(
			'bunavattrsdiv',
			__( 'Placement in Navigation', 'bu-navigation' ),
			array( $this, 'display_nav_attributes' ),
			$post_type,
			'side',
			'core'
			);

	}

	/**
	 * Render our replacement for the standard Page Attributes metabox
	 *
	 * Replaces the built-in "Parent" dropdown and "Order" text input with
	 * a modal jstree interface for placing the current post among the
	 * site hierarchy.
	 *
	 * Also adds a custom navigation label, and the ability to hide
	 * this page from navigation lists (i.e. content nav widget) with
	 * a checkbox.
	 */
	public function display_nav_attributes( $post ) {

		// Template context
		$post_type = get_post_type_object( $post->post_type );

		// By default the post object passed to meta boxes is formatted for editing.
		// We need to make sure a *raw* post title is used if no navigation label is set.
		// This prevents us from storing double escaped HTML entities in the database.
		$raw_post = get_post( $post->ID );
		if ( 'auto-draft' === $post->post_status ) {
			// Special handling for new posts -- The version stored in the database
			// has "Auto Draft" as a title at this point, which we don't want.
			// @see `get_default_post_to_edit`
			$raw_post->post_title = '';
		}

		$nav_label = bu_navigation_get_label( $raw_post, '' );
		$breadcrumbs = $this->get_post_breadcrumbs_label( $raw_post );

		$nav_display = ! bu_navigation_post_excluded( $post );
		$images_url = plugins_url( 'images', BU_NAV_PLUGIN );
		$pub_cap = $post_type->cap->publish_posts;
		$user_cannot_publish = ( $post->post_status != 'publish' && ! current_user_can( $pub_cap ) );

		include( BU_NAV_PLUGIN_DIR . '/templates/metabox-navigation-attributes.php' );

	}


	/**
	 * Render custom "Page Template" metabox
	 *
	 * Since we replace the standard "Page Attributes" meta box with our own,
	 * we relocate the "Template" dropdown that usually appears there to its
	 * own custom meta box
	 */
	public function display_custom_template( $post ) {

		$post_type = get_post_type_object( $post->post_type );
		$current_template = isset( $post->page_template ) ? $post->page_template : 'default';

		include( BU_NAV_PLUGIN_DIR . '/templates/metabox-custom-template.php' );

	}

	/**
	 * Update navigation related meta data on post save
	 *
	 * WordPress will handle updating of post_parent and menu_order prior to this callback being run
	 * The post property of stored in this object will hold the post data prior to update
	 *
	 * @todo don't hard code meta keys
	 */
	public function save( $post_id, $post ) {

		if( ! in_array( $post->post_type, $this->plugin->supported_post_types() ) )
			return;

		if( 'auto-draft' == $post->post_status )
			return;

		if( array_key_exists( 'nav_label', $_POST ) ) {

			// update the navigation meta data
			$nav_label = wp_kses_post( $_POST['nav_label'] );
			$exclude = ( array_key_exists( 'nav_display', $_POST ) ? 0 : 1 );

			update_post_meta( $post_id, BU_NAV_META_PAGE_LABEL, $nav_label );
			update_post_meta( $post_id, BU_NAV_META_PAGE_EXCLUDE, $exclude );

		}

		// Perform reordering if post parent or menu order has changed
		$reorder = new BU_Navigation_Reorder_Tracker( $post->post_type );

		// Reorder old and new section if parent has changed
		if( $this->post->post_parent != $post->post_parent ) {

			$reorder->mark_post_as_moved( $post );
			$reorder->mark_section_for_reordering( $this->post->post_parent );

		}

		// Reorder current siblings if only my menu order has changed
		else if( $this->post->menu_order != $post->menu_order ) {

			$reorder->mark_post_as_moved( $post );

		}

		// Reorder
		if( $reorder->has_moves() ) {
			$reorder->run();
		}

	}

	/**
	 * @todo consider moving
	 */
	public function get_formatted_ancestors() {
		$ancestors = array();
		$post = $this->post;

		if ( empty( $post ) )
			return $ancestors;

		while( $post->post_parent != 0 ) {
			$post = get_post( $post->post_parent );
			array_push($ancestors, $this->format_post( $post ) );
		}

		return $ancestors;
	}

	/**
	 * @todo this is redundant with work done in the BU_Navigation_Tree_View class
	 * @todo think about refactoring
	 */
	public function format_post( $post ) {

		// Get necessary metadata
		$post->excluded = bu_navigation_post_excluded( $post );
		$post->protected = ! empty( $post->post_password );

		// Label
		$post->post_title = bu_navigation_get_label( $post );

		$formatted = array(
			'ID' => $post->ID,
			'post_title' => $post->post_title,
			'post_status' => $post->post_status,
			'post_type' => $post->post_type,
			'post_parent' => $post->post_parent,
			'menu_order' => $post->menu_order,
			'post_meta' => array(
				'protected' => $post->protected,
				'excluded' => $post->excluded,
				)
		);

		return apply_filters( 'bu_nav_metabox_format_post', $formatted, $post );
	}

	/**
	 * Generate breadcrumbs label for current post
	 *
	 * Will return full breadcrumbs if current post has ancestors, or
	 * appropriate string if it does not.
	 */
	public function get_post_breadcrumbs_label( $post ) {

		$output = '';

		if( $post->post_parent ) {
			$output = $this->get_post_breadcrumbs( $post );
		} else {
			$output = "<ul id=\"bu-post-breadcrumbs\"><li class=\"current\">" . __('Top level page', 'bu-navigation' ) . "</li></ul>\n";
		}

		return $output;
	}

	public function get_post_breadcrumbs( $post ) {

		$output = "<ul id=\"bu-post-breadcrumbs\">";

		// Manually fetch ancestors to get around BU WP core mod...
		$ancestors = array( $post );
		while( $post->post_parent != 0 ) {
			$post = get_post( $post->post_parent );
			array_push( $ancestors, $post );
		}

		// Start from the root
		$ancestors = array_reverse( $ancestors );

		// Print markup
		foreach( $ancestors as $ancestor ) {
			$label = bu_navigation_get_label( $ancestor );

			if( $ancestor != end($ancestors) ) {
				$output .= "<li>" . $label . "<ul>";
			} else {
				$output .= "<li class=\"current\">" . $label;
				$output .= str_repeat( "</li></ul>", count( $ancestors ) );
			}
		}

		return $output;
	}

}
