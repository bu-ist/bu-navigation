<?php

/**
 * BU Navigation Admin Metabox controller
 * 
 * Handles rendering and behavior of the navigation attributes metabox
 * 	-> Setting post parent and order
 *  -> Setting navigation label
 * 	-> Showing/hiding of post in navigation menus
 * 
 * @todo
 *  - Move hidden page deletion behavior to higher level admin class
 *  - Add "Help" for navigation, label, visibilty
 */ 
class BU_Navigation_Admin_Metabox {

	static $interface;

	public $post;
	public $post_type;
	public $post_type_labels;

	public function __construct( $post, $post_type ) {

		// Set up properties
		if( is_numeric( $post ) )
			$post = get_post( $post );

		$this->post = $post;
		$this->post_type = $post_type;
		$this->post_type_labels = $this->get_post_type_labels( $this->post_type );

		// Instantiate navman tree interface object
		$tree_post_types = ( $this->post_type == 'page' ? array( 'page', 'link' ) : array( $this->post_type ) );
		self::$interface = new BU_Navman_Interface( $tree_post_types );

		// Attach WP actions/filters
		$this->register_hooks();

	}

	/**
	 * Attach WP actions and filters utilized by our meta boxes
	 */ 
	public function register_hooks() {
		global $wp_version;

		add_action('admin_enqueue_scripts', array($this, 'admin_page_scripts'));
		add_action('admin_enqueue_scripts', array($this, 'admin_page_styles'));
		add_action('add_meta_boxes', array($this, 'register_metaboxes'), 10, 2);

		add_action('save_post', array($this, 'save_nav_meta_data'), 10, 2);

		// for WP 3.2: change delete_post to before_delete_post (because at that point, the children posts haven't moved up)
		if( version_compare( $wp_version, '3.2', '<' ) ) {
			add_action('delete_post', array($this, 'handle_hidden_page_deletion'));
		} else {
			add_action('before_delete_post', array($this, 'handle_hidden_page_deletion'));
		}

		// @todo move this outisde of here
		if( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			add_action('wp_ajax_check_hidden_page', array($this, 'ajax_check_hidden_page'));
		}
		
	}
	
	/**
	 * Load metabox scripts
	 */ 
	public function admin_page_scripts($hook_suffix) {
		
		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';

		$scripts_path = plugins_url('js',__FILE__);
		$vendor_path = plugins_url('js/vendor',__FILE__);
		
		// @todo this script needs to be loaded for edit.php as well -- should be moved out of here and
		// to higher levels
		wp_enqueue_script( 'bu-page-parent-deletion', $scripts_path . '/deletion' . $suffix . '.js', array('jquery'));
		wp_localize_script( 'bu-page-parent-deletion', 'bu_navigation_pt_labels', $this->post_type_labels );

		// jstree scripts
		self::$interface->enqueue_scripts();

		wp_enqueue_script('bu-navigation-metabox', $scripts_path . '/navigation-metabox' . $suffix . '.js', array('jquery','bu-jquery-tree'));

	}

	/**
	 * Load metabox styles
	 */ 
	public function admin_page_styles($hook_suffix) {

		$styles_path = plugins_url('css',__FILE__);

		wp_enqueue_style( 'bu-navigation-metabox', $styles_path . '/navigation-metabox.css' );

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
		if (is_array($tpls = get_page_templates()) && (count($tpls) > 0)) {
			add_meta_box('bupagetemplatediv', __('Page Template'), array($this, 'custom_template_metabox'), 'page', 'side', 'core');
		}

		if( in_array( $post_type, bu_navigation_supported_post_types() ) ) {

			$pt_labels = $this->post_type_labels;
			add_meta_box('bupageparentdiv', __('Navigation Attributes'), array($this, 'navigation_attributes_metabox'), $post_type, 'side', 'core');

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
		$nav_label = htmlspecialchars($nav_meta_data['label']);
		$nav_exclude = $nav_meta_data['exclude'];

		// new pages are not in the nav already, so we need to fix this
		$already_in_nav = $post->post_status == 'auto-draft' ? false : (bool) !$nav_exclude;

		// Labels 
		$pt_labels = $this->post_type_labels;

		$current_menu_order = $post->menu_order;
		$current_parent = $post->post_parent ? $post->post_parent : '';
		$current_parent_label = $select_parent_txt = '';
		$lc_label = strtolower( $pt_labels['singular'] );

		if( empty( $current_parent ) ) {
			if( $post->post_status == 'publish' ) {
				$current_parent_label = '<p>Current Parent: <span>None (top-level page)</span></p>';
				$select_parent_txt = "Move $lc_label";
			} else {
				$current_parent_label = '<p>No post parent has been set</p>';
				$select_parent_txt = "Move $lc_label";
			}
		} else {
			$parent = get_post( $current_parent );
			$parent_meta = $this->get_nav_meta_data( $parent );
			$current_parent_label = '<p>Current Parent: <span>' . $parent_meta['label'] . '</span></p>';
			$select_parent_txt = "Move $lc_label";
		}

		// Print dynamic Javascript data
		$this->print_script_context();

		include('interface/metabox-navigation-attributes.php');

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
	 * Outputs a block of Javascript that contains a global object used by
	 * the navigation-attributes.js script
	 */ 
	public function print_script_context() {

		$properties = array();

		$context = $this->get_script_data();

		foreach( $context as $key => $value ) {
			array_push( $properties, "\"$key\": " . json_encode( $value ) );
		}

		echo "<script type=\"text/javascript\">//<![CDATA[\r";
		echo "if( typeof bu === \"undefined\" ) var bu = {};\r";
		echo "if( typeof bu.navigation === \"undefined\" ) bu.navigation = {};\r";
		echo "bu.navigation.settings = {\r" . implode(",\r", $properties ) . "\r};\r";
		echo "//]]>\r</script>";

	}

	/**
	 * Dynamic variables to be passed to the navigation-attributes.js script
	 */ 
	public function get_script_data() {

		$post = $this->post;
		$post_id = is_object( $post ) ? $post->ID : null;

		// Pass current post ancestors if present to assist in selecting current post
		$ancestors = null;

		if( is_object( $post ) && isset( $post->ancestors ) && ! empty( $post->ancestors ))
			$ancestors = $post->ancestors;

		// Does the current user have any editing restrictions from the section editing plugin?
		// @todo loosen this coupling somehow
		$is_section_editor = false;

		if( class_exists( 'BU_Section_Editing_Plugin' ) ) {
			$is_section_editor = BU_Section_Editing_Plugin::is_allowed_user( get_current_user_id() );
		}

		$data = array(
			'tree' => self::$interface->get_pages( 0, array( 'depth' => 1 ) ),
			'ancestors' => $ancestors,
			'currentPage' => $post_id,
			'allowTop' => $this->allow_top_level_page(),
			'isSectionEditor' => $is_section_editor,
			);

		return $data;

	}

	/**
	 * @todo move this out of here, in to a global settings class
	 */ 
	public function allow_top_level_page() {

		// the 'allow top level page' option (in Site Design -> Primary Navigation screen) only applies to pages
		if ($this->post_type == 'page' && defined('BU_NAV_OPTION_ALLOW_TOP')) {
			return (bool)get_option(BU_NAV_OPTION_ALLOW_TOP);
		} else {
			return true;
		}
	}

	protected function get_post_type_labels( $post_type ) {
				
		$pt_obj = get_post_type_object($post_type);

		if( ! is_object( $pt_obj ) )
			return false;

		return array(
			'post_type' => $post_type,
			'singular' => $pt_obj->labels->singular_name,
			'plural' => $pt_obj->labels->name,
		);

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

		if(array_key_exists('nav_label', $_POST)) {
		
			// update the navigation meta data
			$nav_label = $_POST['nav_label'];
			$exclude = (array_key_exists('nav_display', $_POST) ? 0 : 1);
			
			update_post_meta($post_id, '_bu_cms_navigation_page_label', $nav_label);
			update_post_meta($post_id, '_bu_cms_navigation_exclude', $exclude);
			
		}

		/* 
		Reorder siblings, old and new, if post_parent or menu_order has changed
		@todo 
			- review these more carefully
			- make sure to consider new post menu_order of 0 in reordering top level pages
		*/

		// Reorder old siblings if my parent has changed
		if( $this->post->post_parent != $post->post_parent ) {
			// error_log('Post parent has changed!  Reordering old and new siblings...');
			$this->reorder_siblings( $this->post );	// Reorder old siblings by passing original post object
			$this->reorder_siblings( $post ); // Reorder current siblings by passing new one
		}

		// Reorder current siblings if only my menu order has changed
		else if( $this->post->menu_order != $post->menu_order ) {
			// error_log('Menu order has changed!  Reordering current siblings...');
			$this->reorder_siblings( $post );
		}

	}

	/**
	 * Account for a possible change in menu_order by reordering siblings of the saved post
	 * 
	 * @todo review logic more closely, especially args to bu_navigation_get_pages
	 * @todo perhaps move this to a more globally accessible location, could be useful outside of here
	 * @todo needs unit test
	 */ 
	public function reorder_siblings( $post ) {
		global $wpdb;

		// error_log("Reordering siblings for post {$post->post_title}, with parent: {$post->post_parent}");

		$post_types = ( $post->post_type == 'page' ? array('page', 'link') : array($post->post_type) );

		// Fetch siblings, as currently ordered by menu_order
		$siblings = bu_navigation_get_pages( array(
			'sections' => array($post->post_parent),
			'post_status' => false,	// @todo this includes auto-drafts/revisions...
			'suppress_filter_pages' => true,	// suppress is spelled with two p's...
			'post_type' => $post_types,	// handle custom post types support
		));
		
		$i = 1;

		if ($siblings) {

			foreach ($siblings as $sib) {

				// Skip post being saved if present in siblings array (it already has menu_order set correctly)
				if ($sib->ID == $post->ID) {
					// error_log("Skipping myself, I already have the right menu order");
					continue;
				}

				// If post being saved is among siblings, increment menu order counter to account for it
				if ( in_array( $post->ID, array_keys( $siblings ) ) && $i == $post->menu_order) {
					// error_log("Skipping my own menu order...");
					$i++;
				}

				// Commit new order for this sibling
				$update = $wpdb->prepare("UPDATE $wpdb->posts SET menu_order = %d WHERE ID = %d", $i, $sib->ID);
				$wpdb->query( $update );
				// error_log("Updating menu order for {$sib->post_title} to: $i");
				$i++;

			}

		} else {

			// error_log("No siblings found for post {$post->ID}, done!");

		}

	}

	/**
	 * @todo
	 *  - relocate
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
	 *  - relocate
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
		$pt_labels = $this->post_type_labels;
		
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
		
		if ( $children_msgs['page'] )
			$msg .= sprintf(' If you delete this %1$s, %2$s will move up one node in the %1$s hierarchy, and will autmatically be marked as hidden.', strtolower($pt_labels['singular']), $children_msgs['page']);
			
		if ( $children_msgs['link'] )
			$msg .= sprintf(' If you delete this %1$s, %2$s will move up one node in the %1$s hierarchy, and will be displayed in navigation menus.', strtolower($pt_labels['singular']), $children_msgs['link']);
		
		$response = array (
			'ignore' => false,
			'msg' => $msg,
		);
		
		echo json_encode( $response );
		die;

	}

}