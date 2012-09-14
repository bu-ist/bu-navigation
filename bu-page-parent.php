<?php


if (defined('BU_PLUGIN_PAGE_PARENT')) {
	return;
}

// This plugin is loaded (use this for graceful degradation).
define('BU_PLUGIN_PAGE_PARENT', true);

class BuPageParent
{
	public static function init()
	{
		add_action('add_meta_boxes', array(__CLASS__, 'do_meta_boxes'));
		add_action('admin_enqueue_scripts', array(__CLASS__, 'admin_page_scripts'));
		add_action('admin_enqueue_scripts', array(__CLASS__, 'admin_page_styles'));
		
		add_action('save_post', array(__CLASS__, 'save_nav_meta_data'));
		// for WP 3.2: change delete_post to before_delete_post (because at that point, the children posts haven't moved up)
		add_action('delete_post', array(__CLASS__, 'handle_hidden_page_deletion'));
		add_action('wp_ajax_check_hidden_page', array(__CLASS__, 'ajax_check_hidden_page'));
		
	}
	
	
	public static function admin_page_scripts($hook_suffix)
	{
        global $current_screen;
		
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
        $editpage_suffixes = array ( 'post.php', 'post-new.php' );				// post.php and post-new.php are the edit/add new pages
		$listpage_suffixes = array ( 'post.php', 'post-new.php', 'edit.php' );	// edit.php is the listing page
		
		if( !isset($current_screen->post_type) || !in_array($current_screen->post_type, bu_navigation_supported_post_types()) ) return;
		
		wp_enqueue_style( 'bu-page-parent-browser', plugins_url('interface/page-parent.css', __FILE__) );

		// post type labels (singular, plural)
		wp_localize_script('jquery', 'bu_navigation_pt_labels', self::getPostTypeLabels());
		
		if ( in_array($hook_suffix, $listpage_suffixes) ) {
			wp_enqueue_script('bu-page-parent-deletion', plugins_url('js/deletion' . $suffix . '.js', __FILE__), array('jquery'));
		}

        if( !in_array($hook_suffix, $editpage_suffixes) ) return;
		
		wp_enqueue_script('jquery-qtip', plugins_url('js/jquery.qtip-1.0.0-rc3' . $suffix . '.js', __FILE__), array('jquery'), '1.0.0-rc3', true);

		wp_enqueue_script('bu-page-parent-browser', plugins_url('js/parent-browser' . $suffix . '.js', __FILE__), array('jquery'));

	}
	
	public static function admin_page_styles($hook_suffix)
	{
        global $current_screen;
		
		$possible_hook_suffix = array ( 'post.php', 'post-new.php' );
        if( !isset($current_screen->post_type) || !in_array($current_screen->post_type, bu_navigation_supported_post_types()) || !in_array($hook_suffix, $possible_hook_suffix) ) return;
		
		wp_enqueue_style('bu-page-parent-browser', plugins_url('interface/style.css', __FILE__));
	}
	
	public static function filterPostFields($fields)
	{
		$fields = array('ID', 'post_title', 'post_parent', 'post_type', 'menu_order', 'post_name', 'post_status');
		return $fields;
	}
	
	public static function allowTopLevelPage()
	{
		global $post;
		// the 'allow top level page' option (in Site Design -> Primary Navigation screen) only applies to pages
		if ($post->post_type == 'page' && defined('BU_NAV_OPTION_ALLOW_TOP')) {
			return (bool)get_option(BU_NAV_OPTION_ALLOW_TOP);
		} else {
			return true;
		}
	}
	
	public static function getPostTypeLabels($post_type = '') {
		global $current_screen;
		
		if (!$post_type) $post_type = $current_screen->post_type;
		
		$pt_obj = get_post_type_object($post_type);
		return array(
			'post_type' => $post_type,
			'singular' => $pt_obj->labels->singular_name,
			'plural' => $pt_obj->labels->name,
		);
	}

	public static function filterValidParents($pages)
	{

		foreach ($pages as $index => $page) {

			if (!$page->post_name) {

				unset($pages[$index]);

			}

			if ($page->post_status === 'trash') {

				unset($pages[$index]);

			}

		}

		return $pages;
	}
	
	public static function do_meta_boxes()
	{
		remove_meta_box('pageparentdiv', 'page', 'side');

		if (is_array($tpls = get_page_templates()) && (count($tpls) > 0)) {
			add_meta_box('bupagetemplatediv', __('Page Template'), array(__CLASS__, 'page_template_meta_box'), 'page', 'side', 'core');
		}

		$post_types = bu_navigation_supported_post_types();
		foreach($post_types as $pt) {
			$pt_labels = self::getPostTypeLabels($pt);
			add_meta_box('bupageparentdiv', __($pt_labels['singular'] . ' Attributes'), array(__CLASS__, 'metaBox'), $pt, 'side', 'core');
		}
	}
	
	
	public static function metaBox($post)
	{
		include('interface/page-attributes-new.php');
	}
	
	
	public static function page_template_meta_box($post)
	{
		include('interface/page-template.php');
	}


	/* retrieve and save navigation-related post meta data */

	public static function get_nav_meta_data($post) {
	  $nav_label = get_post_meta($post->ID, '_bu_cms_navigation_page_label', true);
	  $exclude = get_post_meta($post->ID, '_bu_cms_navigation_exclude', true);
	  
	  return array('label' => (trim($nav_label) ? $nav_label : $post->post_title),
		       'exclude' => (int) $exclude);
	}

	public static function save_nav_meta_data($post_id) {
	  global $wpdb;

	  $post = get_post($post_id);
	  if ( !in_array($post->post_type, bu_navigation_supported_post_types()) ) return;

	  if (array_key_exists('nav_label', $_POST)) {
	    
	    // update the navigation meta data
	    
	    $nav_label = $_POST['nav_label'];
	    $exclude = (array_key_exists('nav_display', $_POST) ? 0 : 1);
	    
	    update_post_meta($post_id, '_bu_cms_navigation_page_label', $nav_label);
	    update_post_meta($post_id, '_bu_cms_navigation_exclude', $exclude);

	    
	    // renumber the sibling pages
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
	 * when deleting posts that are hidden, the children will get moved up one level (to take place of the current post),
	 * but if they were hidden (as a result of the current post being hidden), they will become unhidden.
	 * So we must go through all the children and mark them hidden.
	 */
	public static function handle_hidden_page_deletion($post_id) {
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
	
	/*
	 * - prints json formatted data that tells the browser to either show a warning or not
	 * - dies.
	 */
	public static function ajax_check_hidden_page() {
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
		$pt_labels = self::getPostTypeLabels($post->post_type);
		
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
add_action('admin_init', array('BuPageParent', 'init'));

