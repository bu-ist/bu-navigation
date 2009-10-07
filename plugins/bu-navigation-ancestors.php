<?php
/**
 *
 * @param $pages array Associative array of pages keyed on page ID
 * @return array Filtered associative array of pages with active_section member variable set
 */
function bu_navigation_filter_pages_ancestors($pages)
{
	global $wpdb, $post;
	
	$ancestors = bu_navigation_gather_sections($post->ID);
	
	$filtered = array();
	
	if ((is_array($pages)) && (count($pages) > 0))
	{
		if ((is_array($ancestors)) && (count($ancestors) > 0))
		{
			foreach ($pages as $page)
			{
				$page->active_section = FALSE;
				
				if ((in_array($page->ID, $ancestors)) && ($page->ID != $post->ID))
				{
					$page->active_section = TRUE;
				}
				
				$filtered[$page->ID] = $page;
			}
		}
		else
		{
			$filtered = $pages;
		}
	}

	return $filtered;
}

add_filter('bu_navigation_filter_pages', 'bu_navigation_filter_pages_ancestors');

?>