<?php
define('BU_NAV_META_PAGE_EXCLUDE', '_bu_cms_navigation_exclude'); // name of meta_key used to exclude pages from navigation

function bu_navigation_filter_pages_exclude($pages)
{
	global $wpdb;

	$filtered = array();

	if ((is_array($pages)) && (count($pages) > 0))
	{
		$ids = array_keys($pages);

		$query = sprintf("SELECT post_id, meta_value FROM %s WHERE meta_key = '%s' AND post_id IN (%s) AND meta_value != '0'", $wpdb->postmeta, BU_NAV_META_PAGE_EXCLUDE, implode(',', $ids));

		$exclusions = $wpdb->get_results($query, OBJECT_K); // get results as objects in an array keyed on post_id

		if ((is_array($exclusions)) && (count($exclusions) > 0))
		{
			foreach ($pages as $page)
			{
				if (!array_key_exists($page->ID, $exclusions)) $filtered[$page->ID] = $page;
			}
		}
		else
		{
			$filtered = $pages;
		}
	}

	return $filtered;
}

add_filter('bu_navigation_filter_pages', 'bu_navigation_filter_pages_exclude');

?>
