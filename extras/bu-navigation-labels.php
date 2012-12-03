<?php
define('BU_NAV_META_PAGE_LABEL', '_bu_cms_navigation_page_label'); // name of meta_key used to hold navigation labels

function bu_navigation_filter_pages_navlabels($pages)
{
	global $wpdb;

	$filtered = array();

	if ((is_array($pages)) && (count($pages) > 0))
	{
		$ids = array_keys($pages);

		$query = sprintf("SELECT post_id, meta_value FROM %s WHERE meta_key = '%s' AND post_id IN (%s) AND meta_value != ''", $wpdb->postmeta, BU_NAV_META_PAGE_LABEL, implode(',', $ids));

		$labels = $wpdb->get_results($query, OBJECT_K); // get results as objects in an array keyed on post_id

		if ((is_array($labels)) && (count($labels) > 0))
		{
			foreach ($pages as $page)
			{
				if (array_key_exists($page->ID, $labels))
				{
					$label = $labels[$page->ID];
					$page->navigation_label = $label->meta_value;
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

add_filter('bu_navigation_filter_pages', 'bu_navigation_filter_pages_navlabels');
add_filter('bu_navigation_filter_page_labels', 'bu_navigation_filter_pages_navlabels');

function bu_navigation_get_label( $post, $empty_label = '(no title)' ) {
	if( is_numeric( $post ) ) {
		$post = get_post( $post );
	}

	if( ! is_object( $post ) ) {
		return false;
	}
	
	$label = get_post_meta( $post->ID, BU_NAV_META_PAGE_LABEL, true );
	
	if( ! $label ) {
		$label = $post->post_title;
	}
	
	if( empty( $label ) ) {
		$label = $empty_label;
	}

	return $label;
	
}

?>
