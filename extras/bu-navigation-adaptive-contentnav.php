<?php

function bu_navigation_widget_before_list_tech()
{
	add_filter('bu_navigation_filter_pages_by_parent', 'bu_navigation_filter_pages_tech');
	add_filter('widget_bu_pages_args', 'widget_bu_pages_args_tech');
}
add_action('bu_navigation_widget_before_list', 'bu_navigation_widget_before_list_tech');

function widget_bu_pages_args_tech($args)
{
	$args['page_id'] = NULL;
	return $args;
}

function bu_navigation_filter_pages_tech($pages_by_parent)
{
	global $post;
	
	$filtered = array();
	
	$hasChildren = FALSE;
	if ((array_key_exists($post->ID, $pages_by_parent)) && (count($pages_by_parent[$post->ID]) > 0)) $hasChildren = TRUE;

	error_log(sprintf('bu_navigation_filter_pages_tech: %d hasChildren = %d', $post->ID, $hasChildren));

	foreach ($pages_by_parent as $parent_id => $posts)
	{
		error_log(sprintf('%d has %d children', $parent_id, count($posts)));
		
		if ((is_array($posts)) && (count($posts) > 0))
		{
			$potentials = array();
			
			foreach ($posts as $p)
			{
				if ($hasChildren)
				{
					/* only include the current page from the list of siblings if we have children */
					if ($p->ID == $post->ID) array_push($potentials, $p);
				}
				else
				{
					/* if we have no children, then display siblings of current page also */
					if ($p->post_parent == $post->post_parent) array_push($potentials, $p);	
					
					/* if we have no children, display the parent page */
					if ($p->ID == $post->post_parent) array_push($potentials, $p);
				}
				
				/* also include pages that are children of the current page */
				if ($p->post_parent == $post->ID) array_push($potentials, $p);
			}
			
			if (count($potentials) > 0) $filtered[$parent_id] = $potentials;
		}
	}
	
	remove_filter('bu_navigation_filter_pages_by_parent', 'bu_navigation_filter_pages_tech');
	
	error_log(sprintf('sections: %s', implode(', ', array_keys($filtered))));
		
	return $filtered;
}
?>