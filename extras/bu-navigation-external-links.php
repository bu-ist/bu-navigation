<?php
define('BU_NAV_META_TARGET', 'bu_link_target'); // name of meta_key used to hold target window

/**
 * Filter fields retrieved from DB when grabbing navigation data to add post_content when post_type=link
 * @return array Filtered list of fields
 */
function bu_navigation_filter_fields_external_links($fields)
{
	array_push($fields, "(IF(post_type='link',post_content,'')) AS post_content");
	return $fields;
}
add_filter('bu_navigation_filter_fields', 'bu_navigation_filter_fields_external_links');

/** 
 * Filter pages before displaying navigation to set external URL and window target for external links
 * @return array Filtered list of pages
 */
function bu_navigation_filter_pages_external_links($pages)
{	
	global $wpdb;
	
	$filtered = array();
	
	if ((is_array($pages)) && (count($pages) > 0))
	{
		$ids = array_keys($pages);
		
		$query = sprintf("SELECT post_id, meta_value FROM %s WHERE meta_key = '%s' AND post_id IN (%s)", $wpdb->postmeta, BU_NAV_META_TARGET, implode(',', $ids));
		
		$targets = $wpdb->get_results($query, OBJECT_K); // get results as objects in an array keyed on post_id
		
		foreach ($pages as $page)
		{
			if ($page->post_type == 'link')
			{
				$page->url = $page->post_content;
				
				if ((is_array($targets)) && (array_key_exists($page->ID, $targets))) $page->target = $targets[$page->ID]->meta_value;
			}
			
			$filtered[$page->ID] = $page;
		}
	}

	return $filtered;
}
add_filter('bu_navigation_filter_pages', 'bu_navigation_filter_pages_external_links');

/**
 * Filter HTML attributes set on a navigation item anchor element to add window target where applicable
 * @return array Filtered anchor attributes
 */
function bu_navigation_filter_anchor_attrs_external_links($attrs, $page = NULL)
{		
	if ((!is_null($page)) && (isset($page->target)) && ($page->target == 'new')) $attrs['target'] = '_blank';
	
	return $attrs;
}
add_filter('bu_navigation_filter_anchor_attrs', 'bu_navigation_filter_anchor_attrs_external_links');
?>