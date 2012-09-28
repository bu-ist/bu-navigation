<?php
/*
 @todo
 	- refactor this into a class
 	- only load on manage posts screen
 	- rename file to something more consistent -- bu-navigation-admin-manage-posts.php
 */
 

/** @todo fix pagination? */

/* BU Navigation constants */
define('BU_FILTER_PAGES_ID', 'bu_filter_pages');

/**
 * Action for bu_after_post_stati, a custom action that is called after the list of filter options is displayed.
 * Adds a select box allowing users to filter the list of $_GET['post_type'] posts by page parent
 * @return void
 */
function bu_filter_pages_after_post_stati()
{
	// only show the sections for the current post_type
	$post_type = isset($_GET['post_type']) ? $_GET['post_type'] : 'page';
	
	$post_types = bu_navigation_supported_post_types();
	if( !in_array($post_type, $post_types) )  return;
	
	$section_args = array('direction' => 'down', 'depth' => 1, 'post_types' => array($post_type));
	$args = array(
		'suppress_filter_pages' => TRUE,
		'sections' => bu_navigation_gather_sections(0, $section_args),
		'post_types' => array($post_type),
		);
	
	/* grab all pages  we need for pnav */
	
	$pages = bu_navigation_get_pages($args);
		
	$pages_by_parent = bu_navigation_pages_by_parent($pages);
	
	require_once(BU_NAV_PLUGIN_DIR . '/interface/page-filters.php');
}
add_action('restrict_manage_posts', 'bu_filter_pages_after_post_stati');

/**
 * Filter for the_posts that removes posts that are not a descendant of a specific post
 * @return array Filtered array of posts
 */
function bu_filter_the_posts($posts)
{
	if(!is_admin()) return $posts;
	$post_parent = array_key_exists('post_parent', $_GET) ? intval($_GET['post_parent']) : NULL;
	// only show the sections for the current post_type
	$post_type = isset($_GET['post_type']) ? $_GET['post_type'] : '';
	
	if ($post_parent)
	{
		$section_args = array('direction' => 'down', 'depth' => 0, 'post_types' => array($post_type));
		$sections = bu_navigation_gather_sections($post_parent, $section_args);
		
		if ((is_array($sections)) && (count($sections) > 0))
		{
			$filtered = array();
			
			foreach ($posts as $p)
			{
				if ((in_array($p->post_parent, $sections)) || (in_array($p->ID, $sections))) array_push($filtered, $p);
			}
			
			$posts = $filtered;
		}
	}
	
	return $posts;
}
add_filter('the_posts', 'bu_filter_the_posts');

/**
 * Action for bu_edit_pages_pre_footer to inject a <script> block on edit-pages.php for handling page parent filtering
 * @return void
 */
function bu_filter_pages_edit_pages_pre_footer()
{
	require_once(BU_NAV_PLUGIN_DIR . '/js/page-filters-js.php');
}
add_action('bu_edit_pages_pre_footer', 'bu_filter_pages_edit_pages_pre_footer');

/**
 * Displays a select box containing page parents, used to filter page list by parent
 * @return boolean TRUE if the box was displayed, FALSE otherwise.
 */
function bu_filter_pages_parent_dropdown($pages_by_parent, $default = 0, $parent = 0, $level = 0)
{
	$post_types = bu_navigation_supported_post_types();
	
	if ((is_array($pages_by_parent)) && (array_key_exists($parent, $pages_by_parent)) && (count($pages_by_parent) > 0))
	{
		foreach ($pages_by_parent[$parent] as $p)
		{
			
			if (!in_array($p->post_type, $post_types)) continue; // only show valid post types
			if (!array_key_exists($p->ID, $pages_by_parent)) continue; // don't show pages with no children
			
			$padding = str_repeat('&nbsp;', $level * 3);
			$selected = ($p->ID == $default) ? 'selected="selected"' : '';

			printf("\n\t<option value=\"%d\" %s>%s%s</option>", $p->ID, $selected, $padding, esc_html($p->post_title));
			bu_filter_pages_parent_dropdown($pages_by_parent, $default, $p->ID, $level + 1);
		}

		return TRUE;
	}
	else
	{
		return FALSE;
	}
}
?>