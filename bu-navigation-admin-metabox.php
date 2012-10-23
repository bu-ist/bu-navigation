<?php
require_once(dirname(__FILE__) . '/bu-navigation-interface.php' );

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

	static $interface;
	public $plugin;

	public $post;
	public $post_type;
	public $post_type_labels;

	public function __construct( $post, $post_type ) {

		$this->plugin = $GLOBALS['bu_navigation_plugin'];

		// Set up properties
		if( is_numeric( $post ) )
			$post = get_post( $post );

		$this->post = $post;
		$this->post_type = $post_type;
		$this->post_type_labels = BU_Navigation_Plugin::$admin->get_post_type_labels( $this->post_type );

		// Instantiate navman tree interface object
		$post_types = ( $this->post_type == 'page' ? array( 'page', 'link' ) : array( $this->post_type ) );

		$post_id = is_object( $this->post ) ? $this->post->ID : null;
		$is_new = is_null( $post_id ) ? true : false;
		$ancestors = null;

		// @todo setup an else clause here that fetches ancestors if they aren't set on the
		// post object.  Something in our environment seems to be removing them randomly,
		// and with memcache that can stick around for a while in the cache.

		if( is_object( $post ) && isset( $post->ancestors ) && ! empty( $post->ancestors ))
			$ancestors = $post->ancestors;

		$settings = array(
			'postTypes' => $post_types,
			'postStatuses' => array( 'draft', 'pending', 'publish' ),
			'currentPost' => $post_id,
			'ancestors' => $ancestors,
			'isNewPost' => $is_new,
			'lazyLoad' => true,
			'nodePrefix' => 'na'
			);

		// Instantiate post tree interface
		self::$interface = new BU_Navman_Interface( 'nav_metabox', $settings );

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
	public function add_scripts($page) {
		
		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
		$scripts_path = plugins_url('js',__FILE__);
		$styles_path = plugins_url('css',__FILE__);

		wp_register_script('bu-navigation-metabox', $scripts_path . '/navigation-metabox' . $suffix . '.js', array('bu-navigation'), '0.3', true );
		
		wp_enqueue_style( 'bu-navigation-metabox', $styles_path . '/navigation-metabox.css' );

		// Let nav interface class handle enqueue
		self::$interface->enqueue_script('bu-navigation-metabox');

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
		$nav_label = esc_attr($nav_meta_data['label']);
		$nav_exclude = $nav_meta_data['exclude'];

		// new pages are not in the nav already, so we need to fix this
		$nav_display = $post->post_status == 'auto-draft' ? false : (bool) !$nav_exclude;

		// Labels 
		$breadcrumbs = $this->get_post_breadcrumbs_label( $post );
		$pt_labels = $this->post_type_labels;
		$lc_label = strtolower( $pt_labels['singular'] );
		$move_post_btn_txt = "Move $lc_label";

		$pages = self::$interface->get_pages( 0, array( 'depth' => 1 ) );
		
		include('interface/metabox-navigation-attributes.php');

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
			$output = bu_navigation_breadcrumbs(array('show_links' => false, 'include_hidden' => true, 'include_statuses' => array('draft','pending','publish')));
		} else {
			$output = __('Top level page');
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

		if(array_key_exists('nav_label', $_POST)) {
		
			// update the navigation meta data
			$nav_label = $_POST['nav_label'];
			$exclude = (array_key_exists('nav_display', $_POST) ? 0 : 1);
			
			update_post_meta($post_id, '_bu_cms_navigation_page_label', $nav_label);
			update_post_meta($post_id, '_bu_cms_navigation_exclude', $exclude);
			
		}

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
			'post_status' => array('publish','pending','draft'),	// ignore post statuses that are not being displayed
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
				// @todo why not wp_update_post?  this will cause issues in new environment due to cacheing
				$update = $wpdb->prepare("UPDATE $wpdb->posts SET menu_order = %d WHERE ID = %d", $i, $sib->ID);
				$wpdb->query( $update );
				// error_log("Updating menu order for {$sib->post_title} to: $i");
				$i++;

			}

		} else {

			// error_log("No siblings found for post {$post->ID}, done!");

		}

	}

}
