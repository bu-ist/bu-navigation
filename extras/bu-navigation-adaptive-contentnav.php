<?php
/**
 * Adaptive navigation mode tames list size for large site hierarchies by
 * "drilling down" based on the currently active post, limiting  listings
 * to no more than two levels (plus section title if configured).
 *
 * @package BU_Navigation
 *
 * @see [Content Navigation Widget Modes](https://github.com/bu-ist/bu-navigation/wiki/Content-Navigation-Widget)
 * @todo bu_navigation_list_pages has a lot of adaptive mode logic -- refactor to consolidate
 */

/**
 * Filters arguments passed to bu_navigation_list_pages from widget display
 *
 * This is an ugly way of short circuiting the logic within bu_navigation_list_pages to not
 * display all sections.
 *
 * @param array $args Associative array of arguments for the list pages query.
 * @return array Array of arguments transformed for adaptive mode.
 */
function widget_bu_pages_args_adaptive( $args ) {
	if ( $args['page_id'] ) {
		$section_args = array( 'post_types' => $args['post_types'] );
		$sections     = bu_navigation_gather_sections( $args['page_id'], $section_args );

		$args['sections'] = $sections;
		$args['page_id']  = null;
	}
	return $args;
}

/**
 * Filters posts returned from bu_navigation_pages_by_parent to only include those
 * centered around the current post
 *
 * @param array $pages_by_parent Array of parents and children in the form of a 'section'.
 * @return array Transformed array for adaptive mode.
 */
function bu_navigation_filter_pages_adaptive( $pages_by_parent ) {
	global $post;

	$filtered             = array();
	$display_has_children = array_key_exists( $post->ID, $pages_by_parent ) && ( count( $pages_by_parent[ $post->ID ] ) > 0 );

	foreach ( $pages_by_parent as $parent_id => $children ) {

		$adaptive_children = adaptive_filter_children( $children, $display_has_children, $post );

		if ( count( $adaptive_children ) > 0 ) {
			$filtered[ $parent_id ] = $adaptive_children;
		}
	}

	return $filtered;
}

/**
 * Filters the children of a post relative to the post being rendered for adaptive display
 *
 * This method has too many returns, but is unwound from a more complicated set of nested conditionals.
 * The id's are cast as integers, because the $child ids are currently strings, but the parent ids are integers.
 * Casting everything to an integers is clearer than using un-strict comparisons.
 *
 * @param array   $children Array of post objects.
 * @param boolean $display_has_children Whether the post being displayed has children.
 * @param WP_Post $display_post The post being rendered (from the global $post).
 * @return array  Array of filtered post objects.
 */
function adaptive_filter_children( $children, $display_has_children, $display_post ) {

	$filtered = array_filter( $children, function ( $child ) use ( $display_has_children, $display_post ) {
		// Only include the current page from the list of siblings if the current page has children.
		if ( $display_has_children && (int) $child->ID === (int) $display_post->ID ) {
			return true;
		}

		// If the display post doens't have children, display siblings of current page also.
		if ( ! $display_has_children && (int) $child->post_parent === (int) $display_post->post_parent ) {
			return true;
		}

		// If the display post doens't have children, display the parent page.
		if ( ! $display_has_children && (int) $child->ID === (int) $display_post->post_parent ) {
			return true;
		}

		// Include pages that are children of the current page.
		if ( (int) $child->post_parent === (int) $display_post->ID ) {
			return true;
		}

		// Posts that don't meet any of these criteria are filtered out of the result.
		return false;

	});

	// Re-index the array keys, since array_filter preserves them.
	// This is just to match the previous behavior, it is unclear if it is necessary and can probably be removed.
	return array_values( $filtered );

}
