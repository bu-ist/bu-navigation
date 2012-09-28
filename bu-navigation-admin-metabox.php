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
 */ 
class BU_Navigation_Admin_Metabox {

	static $interface;

	// @todo use these
	public $post;
	public $post_type;
	public $post_type_labels;

	public function __construct( $post ) {

		if( ! $post )
			return;

		// Set up properties
		if( is_numeric( $post ) )
			$post = get_post( $post );

		$this->post = $post;
		$this->post_type = $this->get_post_type( $post );
		$this->post_type_labels = $this->get_post_type_labels( $this->post_type );

		// Register for WP hooks
		$this->register_hooks();

	}

	public function register_hooks() {
		global $wp_version;

		add_action('admin_enqueue_scripts', array($this, 'admin_page_scripts'));
		add_action('admin_enqueue_scripts', array($this, 'admin_page_styles'));
		add_action('add_meta_boxes', array($this, 'register_metaboxes'), 10, 2);

		add_action('save_post', array($this, 'save_nav_meta_data'));
		
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
	 * Load necessary Javascript
	 */ 
	public function admin_page_scripts($hook_suffix) {
		
		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';

		$scripts_path = plugins_url('js',__FILE__);
		$vendor_path = plugins_url('js/vendor',__FILE__);
		
		// @todo this script needs to be loaded for edit.php as well -- should be moved out of here and
		// to higher levels
		wp_enqueue_script( 'bu-page-parent-deletion', $scripts_path . '/deletion' . $suffix . '.js', array('jquery'));
		wp_localize_script( 'bu-page-parent-deletion', 'bu_navigation_pt_labels', $this->post_type_labels );

		wp_enqueue_script('bu-navigation-metabox', $scripts_path . '/navigation-metabox' . $suffix . '.js', array('jquery','bu-jquery-tree'));

		$post_types = ( $this->post_type == 'page' ? array( 'page', 'link' ) : array( $this->post_type ) );

		// jstree
		self::$interface = new BU_Navman_Interface( $post_types );
		self::$interface->enqueue_scripts();

		// Setup JS context
		$data = $this->get_script_context();
		wp_localize_script('bu-navigation-metabox', 'BUPP', $data );

	}

	public function admin_page_styles($hook_suffix) {

		$styles_path = plugins_url('css',__FILE__);

		wp_enqueue_style( 'bu-navigation-metabox', $styles_path . '/navigation-metabox.css' );

	}
	
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
		$current_parent_txt = $select_parent_txt = '';
		$lc_label = strtolower( $pt_labels['singular'] );

		if( empty( $current_parent ) ) {
			if( $post->post_status == 'publish' ) {
				$current_parent_txt = 'Current Parent: <span>None (top-level page)</span>';
				$select_parent_txt = "Move $lc_label";
			} else {
				$current_parent_txt = 'No post parent has been set';
				$select_parent_txt = "Move $lc_label";
			}
		} else {
			$current_parent_txt = 'Current Parent: <span>' . get_post( $post->post_parent )->post_title . '</span>';
			$select_parent_txt = "Move $lc_label";
		}

		include('interface/metabox-navigation-attributes.php');

	}
	
	public function custom_template_metabox($post) {

		$current_template = isset( $post->page_template ) ? $post->page_template : 'default';

		include('interface/metabox-custom-template.php');
	
	}

	/**
	 * Dynamic variables to be passed to the navigation-attributes.js script
	 */ 
	public function get_script_context() {
		global $post;

		$post_id = $post->post_status == 'auto-draft' ? null : $post->ID;

		$ancestors = null;
		if( isset( $post->ancestors ) && ! empty( $post->ancestors )) $ancestors = $post->ancestors;

		$is_section_editor = false;

		if( class_exists( 'BU_Section_Editing_Plugin' ) ) {
			$is_section_editor = BU_Section_Editing_Plugin::is_allowed_user( get_current_user_id() );
		}

		$data = array(
			'tree' => self::$interface->get_top_level(),
			'ancestors' => $ancestors,
			'currentPage' => $post_id,
			'allowTop' => $this->allow_top_level_page(),
			'isSectionEditor' => $is_section_editor,
			);

		return $data;

	}

	public function allow_top_level_page() {

		// the 'allow top level page' option (in Site Design -> Primary Navigation screen) only applies to pages
		if ($this->post_type == 'page' && defined('BU_NAV_OPTION_ALLOW_TOP')) {
			return (bool)get_option(BU_NAV_OPTION_ALLOW_TOP);
		} else {
			return true;
		}
	}
	
	protected function get_post_type( $post ) {

		$post_type = '';

		if( is_object( $post ) )
			$post_type = $post->post_type;
		elseif( is_string( $post ) )
			$post_type = $post;

		// @todo add BU Versions logic here
		return $post_type;

	}

	protected function get_post_type_labels( $post_type ) {
				
		$post_type = $this->get_post_type( $post_type );
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

	public function save_nav_meta_data($post_id) {
		global $wpdb;

		$post = get_post($post_id);
		if ( !in_array($post->post_type, bu_navigation_supported_post_types()) ) return;

		if(array_key_exists('nav_label', $_POST)) {
		
			// update the navigation meta data
			$nav_label = $_POST['nav_label'];
			$exclude = (array_key_exists('nav_display', $_POST) ? 0 : 1);
			
			update_post_meta($post_id, '_bu_cms_navigation_page_label', $nav_label);
			update_post_meta($post_id, '_bu_cms_navigation_exclude', $exclude);
			
			// renumber the sibling pages
			// @todo investigate this behavior, wasn't working when attempting to move page to first
			// ----
			// 1. get the siblings of the current post, in menu_order
			// 2. update their menu_order fields, starting at 0, skipping the menu_order of the current post
			$post_types = ( $post->post_type == 'page' ? array('page', 'link') : array($post->post_type) );
			$siblings = bu_navigation_get_pages(array(
				'sections' => array($post->post_parent),
				'post_status' => false,
				'supress_filter_pages' => true,
				'post_type' => $post_types,	// handle custom post types support
			));
			
			$i = 1;
			if ($siblings) {
				foreach ($siblings as $sib) {
					if ($sib->ID == $post_id) continue;

					if ($i == $post->menu_order) $i++;

					$stmt = $wpdb->prepare("UPDATE $wpdb->posts SET menu_order = %d WHERE ID = %d", $i, $sib->ID);
					$wpdb->query($stmt);
			
					$i++;
				}
			}
		}
	}

	/**
	 * 
	 * @todo relocate
	 * 
	 * when deleting posts that are hidden, the children will get moved up one level (to take place of the current post),
	 * but if they were hidden (as a result of the current post being hidden), they will become unhidden.
	 * So we must go through all the children and mark them hidden.
	 */
	public function handle_hidden_page_deletion($post_id) {
		global $wpdb;
		
		error_log('Handling hidden page deletion!');
		
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
	 * @todo relocate
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