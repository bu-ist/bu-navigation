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
	public $post_type_object;
	public $post_type_labels;

	private $plugin;

	public function __construct( $plugin ) {

		$this->plugin = $plugin;

		// Use current screen to determine post info
		$screen = get_current_screen();

		// Determine current post
		// var_dump( $screen );

		// Determine current post type
		$this->post_type = $screen->post_type;

		if ( in_array( $this->post_type, $this->plugin->supported_post_types() ) ) {

			// Store post type object & labels
			$this->post_type_object = get_post_type_object( $this->post_type );
			$this->post_type_labels = $this->plugin->get_post_type_labels( $this->post_type );

			// Determine current post
			if ( 'add' == $screen->action ) {
				$this->post_id = 0;
			} else if ( isset( $_GET['post'] ) ) {
				$this->post_id = $_GET['post'];
			} else if ( isset( $_POST['post_ID'] ) ) {
				$this->post_id = $_POST['post_ID'];
			}

			// Store current post
			if ( $this->post_id )
				$this->post = get_post( $this->post_id );

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

		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
		$scripts_url = plugins_url( 'js', BU_NAV_PLUGIN );
		$styles_url = plugins_url( 'css', BU_NAV_PLUGIN );

		// Scripts
		wp_register_script('bu-navigation-metabox', $scripts_url . '/navigation-metabox' . $suffix . '.js', array('bu-navigation'), BU_Navigation_Plugin::VERSION, true );

		// Setup dynamic script context for navigation-metabox.js
		$ancestors = $this->get_formatted_ancestors();

		$script_context = array(
			'postTypes' => $this->post_type,
			'currentPost' => $this->post_id,
			'ancestors' => $ancestors,
			'lazyLoad' => false,
			'showCounts' => false,
			'nodePrefix' => 'na',
			'deselectOnDocumentClick' => false,
			);
		// Navigation tree view will handle actual enqueuing of our script
		$treeview = new BU_Navigation_Tree_View( 'nav_metabox', $script_context );
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

		// Remove built in page attributes meta box
		remove_meta_box('pageparentdiv', 'page', 'side');

		$templates = get_page_templates();

		if ( is_array( $templates ) && ( count( $templates ) > 0 ) ) {
			add_meta_box(
				'bupagetemplatediv',
				__( sprintf( "%s Template", $this->post_type_labels['singular'] ), BU_Navigation_Plugin::TEXT_DOMAIN  ),
				array($this, 'display_custom_template'),
				$post_type,
				'side',
				'core'
				);
		}

		add_meta_box(
			'bunavattrsdiv',
			__( 'Placement in Navigation', BU_Navigation_Plugin::TEXT_DOMAIN ),
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

		// retrieve previously saved settings for this post (if any)
		$nav_label = esc_attr( bu_navigation_get_label( $post, '' ) );
		$nav_exclude = bu_navigation_post_excluded( $post );

		// new pages are not in the nav already, so we need to fix this
		$nav_display = $post->post_status == 'auto-draft' ? false : ! $nav_exclude;

		// Labels
		$breadcrumbs = $this->get_post_breadcrumbs_label( $post );
		$pt_labels = $this->post_type_labels;
		$lc_label = strtolower( $pt_labels['singular'] );
		$dialog_title = ucfirst($pt_labels['singular']) . ' location';
		$images_url = plugins_url( '/images', __FILE__ );

		$move_post_btn_txt = "Move $lc_label";

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
			$nav_label = $_POST['nav_label'];
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
	 * @todo investigate using get_post_ancestors instead of manually fetching post parents
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

		// @todo -- move to ACL plugin
		// Get necessary metadata
		$acl_option = defined( 'BuAccessControlList::PAGE_ACL_OPTION' ) ? BuAccessControlList::PAGE_ACL_OPTION : BU_ACL_PAGE_OPTION;

		$post->excluded = bu_navigation_post_excluded( $post );
		$post->protected = ! empty( $post->post_password );
		$post->restricted = get_post_meta( $post->ID, $acl_option, true );
		$post->restricted = ! empty( $post->restricted ) ? $post->restricted : false;

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
				'restricted' => $post->restricted
				)
		);

		return $formatted;
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
			$output = "<ul id=\"bu-post-breadcrumbs\"><li class=\"current\">" . __('Top level page') . "</li></ul>\n";
		}

		return $output;
	}

	public function get_post_breadcrumbs( $post ) {

		$output = "<ul id=\"bu-post-breadcrumbs\">";

		$ancestors = array_reverse( get_post_ancestors( $post->ID ) );
		array_push( $ancestors, $post->ID );

		foreach( $ancestors as $ancestor ) {
			$p = get_post($ancestor);
			$label = bu_navigation_get_label( $p );

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
