<?php
require_once(dirname(__FILE__) . '/classes.nav-tree.php' );
require_once(dirname(__FILE__) . '/classes.reorder.php' );

/**
 * BU Navigation Admin Metabox controller
 *
 * Handles rendering and behavior of the navigation attributes metabox
 * 	-> Setting post parent and order
 *  -> Setting navigation label
 * 	-> Showing/hiding of post in navigation menus
 *
 * @todo
 *	- Need to trigger sibling reorganization for restore from trash action
 *  - Add "Help" for navigation, label, visibilty
 */
class BU_Navigation_Admin_Metabox {

	public $post;
	public $post_type;
	public $post_type_labels;

	private $plugin;

	public function __construct( $post, $post_type, $plugin ) {

		$this->plugin = $plugin;

		// Set up properties
		if( is_numeric( $post ) )
			$post = get_post( $post );

		$this->post = $post;
		$this->post_type = $post_type;
		$this->post_type_labels = $this->plugin->get_post_type_labels( $this->post_type );

		// Attach WP actions/filters
		$this->register_hooks();

	}

	/**
	 * Attach WP actions and filters utilized by our meta boxes
	 */
	public function register_hooks() {

		add_action('admin_enqueue_scripts', array($this, 'add_scripts'));
		add_action('add_meta_boxes', array($this, 'register_metaboxes'), 10, 2);

		add_action('save_post', array($this, 'save_nav_meta_data'), 10, 2);

	}

	/**
	 * Load metabox scripts
	 */
	public function add_scripts( $page ) {

		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
		$scripts_path = plugins_url('js',__FILE__);
		$styles_path = plugins_url('css',__FILE__);

		// Scripts
		wp_register_script('bu-navigation-metabox', $scripts_path . '/navigation-metabox' . $suffix . '.js', array('bu-navigation'), BU_Navigation_Plugin::VERSION, true );

		// Setup dynamic script context for navigation-metabox.js
		$post_id = is_object( $this->post ) ? $this->post->ID : null;
		$post_types = ( $this->post_type == 'page' ? array( 'page', 'link' ) : array( $this->post_type ) );
		$ancestors = $this->get_formatted_ancestors();

		$script_context = array(
			'postTypes' => $post_types,
			'currentPost' => $post_id,
			'ancestors' => $ancestors,
			'lazyLoad' => false,
			'showCounts' => false,
			'nodePrefix' => 'na',
			'deselectOnDocumentClick' => false
			);
		// Navigation tree view will handle actual enqueuing of our script
		$treeview = new BU_Navigation_Tree_View( 'nav_metabox', $script_context );
		$treeview->enqueue_script('bu-navigation-metabox');

		// Styles
		wp_enqueue_style( 'bu-navigation-metabox', $styles_path . '/navigation-metabox.css', array(), BU_Navigation_Plugin::VERSION );

	}

	/**
	 * Register navigation metaboxes for supported post types
	 *
	 * @todo needs selenium tests
	 */
	public function register_metaboxes( $post_type, $post ) {

		// Remove built in page attributes meta box
		remove_meta_box('pageparentdiv', 'page', 'side');

		// @todo use the appropriate post type label here
		// Add in custom "Page templates" metabox if current theme has templates
		$tpls = get_page_templates();
		if (is_array($tpls) && (count($tpls) > 0)) {
			add_meta_box('bupagetemplatediv', __('Page Template'), array($this, 'custom_template_metabox'), 'page', 'side', 'core');
		}

		if( in_array( $post_type, bu_navigation_supported_post_types() ) ) {
			add_meta_box('bunavattrsdiv', __('Placement in Navigation'), array($this, 'navigation_attributes_metabox'), $post_type, 'side', 'core');
		}

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
	public function navigation_attributes_metabox( $post ) {

		// retrieve previously saved settings for this post (if any)
		$nav_meta_data = $this->get_nav_meta_data($post);
		$nav_label = esc_attr($nav_meta_data['label']);
		$nav_exclude = $nav_meta_data['exclude'];

		// new pages are not in the nav already, so we need to fix this
		$nav_display = $post->post_status == 'auto-draft' ? false : (bool) !$nav_exclude;

		// Labels
		$breadcrumbs = $this->get_post_breadcrumbs_label( $post );
		$pt_labels = $this->post_type_labels;
		$lc_label = strtolower( $pt_labels['singular'] );
		$dialog_title = ucfirst($pt_labels['singular']) . ' location';

		$move_post_btn_txt = "Move $lc_label";

		include('interface/metabox-navigation-attributes.php');

	}

	public function get_formatted_ancestors() {
		$ancestors = array();
		$post = $this->post;

		while( $post->post_parent != 0 ) {
			$post = get_post($post->post_parent);
			array_push($ancestors, $this->format_post( $post ) );
		}
		
		return $ancestors;	
	}
	
	public function format_post( $post ) {

		// Get necessary metadata
		$acl_option = defined( 'BuAccessControlList::PAGE_ACL_OPTION' ) ? BuAccessControlList::PAGE_ACL_OPTION : BU_ACL_PAGE_OPTION;
		$post->excluded = get_post_meta( $post->ID, BU_NAV_META_PAGE_EXCLUDE, true);
		$post->excluded = ($post->excluded == "1" ) ? true : false; 
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

	/**
	 * Render custom "Page Template" metabox
	 *
	 * Since we replace the standard "Page Attributes" meta box with our own,
	 * we relocate the "Template" dropdown that usually appears there to its
	 * own custom meta box
	 */
	public function custom_template_metabox($post) {

		$current_template = isset( $post->page_template ) ? $post->page_template : 'default';

		include('interface/metabox-custom-template.php');

	}

	/**
	 * retrieve and save navigation-related post meta data
	 */
	public function get_nav_meta_data($post) {

		$nav_label = get_post_meta($post->ID, '_bu_cms_navigation_page_label', true);
		$exclude = get_post_meta($post->ID, '_bu_cms_navigation_exclude', true);

		return array(
			'label' => (trim($nav_label) ? $nav_label : $post->post_title),
			'exclude' => (int) $exclude
			);

	}

	/**
	 * Update navigation related meta data on post save
	 *
	 * WordPress will handle updating of post_parent and menu_order prior to this callback
	 *
	 * @todo needs selenium test
	 *
	 * @hook save_post
	 */
	public function save_nav_meta_data( $post_id, $post ) {

		if( !in_array($post->post_type, bu_navigation_supported_post_types()) )
			return;

		if( 'auto-draft' == $post->post_status )
			return;

		if( array_key_exists( 'nav_label', $_POST ) ) {

			// update the navigation meta data
			$nav_label = $_POST['nav_label'];
			$exclude = ( array_key_exists( 'nav_display', $_POST ) ? 0 : 1 );

			update_post_meta($post_id, '_bu_cms_navigation_page_label', $nav_label);
			update_post_meta($post_id, '_bu_cms_navigation_exclude', $exclude);

		}

		// Perform reordering if post parent or menu order has changed
		$reorder = new BU_Navigation_Reorder_Tracker( $post->post_type );

		// Reorder old and new section if parent has changed
		if( $this->post->post_parent != $post->post_parent ) {

			// error_log('Post parent has changed!  Reordering old and new siblings...');
			$reorder->mark_post_as_moved( $post );
			$reorder->mark_section_for_reordering( $this->post->post_parent );

		}

		// Reorder current siblings if only my menu order has changed
		else if( $this->post->menu_order != $post->menu_order ) {

			// error_log('Menu order has changed!  Reordering current siblings...');
			$reorder->mark_post_as_moved( $post );

		}

		// Reorder
		if( $reorder->has_moves() ) {
			$reorder->run();
		}

	}

}
