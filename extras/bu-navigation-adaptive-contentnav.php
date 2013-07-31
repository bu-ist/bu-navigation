<?php
/**
 * Adaptive navigation mode tames list size for large site hierarchies by
 * "drilling down" based on the currently active post, limiting  listings
 * to no more than two levels (plus section title if configured).
 * 
 * @see [Content Navigation Widget Modes](https://github.com/bu-ist/bu-navigation/wiki/Content-Navigation-Widget)
 * @todo bu_navigation_list_pages has a lot of adaptive mode logic -- refactor to consolidate
 */ 

/**
 * Attach adaptive-mode specific hooks 
 */ 
function bu_navigation_widget_adaptive_before_list() {
	add_filter( 'bu_navigation_filter_pages_by_parent', 'bu_navigation_filter_pages_adaptive' );
	add_filter( 'widget_bu_pages_args', 'widget_bu_pages_args_adaptive' );
}

/**
 * Filters arguments passed to bu_navigation_list_pages from widget display
 * 
 * This is an ugly way of short circuiting the logic within bu_navigation_list_pages to not
 * display all sections.
 */ 
function widget_bu_pages_args_adaptive( $args ) {
	remove_filter( 'widget_bu_pages_args', 'widget_bu_pages_args_adaptive' );
	if ( $args['page_id'] ) {
		$section_args = array( 'post_types' => $args['post_types'] );
		$sections = bu_navigation_gather_sections( $args['page_id'], $section_args );

		$args['sections'] = $sections;
		$args['page_id'] = NULL;
	}
	return $args;
}

/**
 * Filters posts returned from bu_navigation_pages_by_parent to only include those
 * centered around the current post
 */ 
function bu_navigation_filter_pages_adaptive( $pages_by_parent ) {
	global $post;

	remove_filter('bu_navigation_filter_pages_by_parent', 'bu_navigation_filter_pages_adaptive' );

	$filtered = array();
	$has_children = FALSE;

	if ( array_key_exists( $post->ID, $pages_by_parent ) && ( count( $pages_by_parent[$post->ID] ) > 0 ) ) {
		$has_children = TRUE;
	}

	foreach ( $pages_by_parent as $parent_id => $posts ) {
		if ( ( is_array( $posts ) ) && ( count( $posts ) > 0 ) ) {
			$potentials = array();

			foreach ( $posts as $p ) {
				if ( $has_children ) {
					// Only include the current page from the list of siblings if we have children
					if ( $p->ID == $post->ID ) {
						array_push( $potentials, $p );
					}
				} else {
					// If we don't have children...
					// Display siblings of current page also
					if ( $p->post_parent == $post->post_parent ) {
						array_push( $potentials, $p );
					}
					// Display the parent page
					if ( $p->ID == $post->post_parent ) {
						array_push( $potentials, $p );
					}
				}

				// Include pages that are children of the current page
				if ( $p->post_parent == $post->ID ) {
					array_push( $potentials, $p );
				}
			}

			if ( count( $potentials ) > 0 ) {
				$filtered[$parent_id] = $potentials;
			}
		}
	}

	return $filtered;
}

?>
