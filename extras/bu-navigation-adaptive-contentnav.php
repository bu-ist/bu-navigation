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
 * @param array   $children Array of post objects.
 * @param boolean $display_has_children Whether the post being displayed has children.
 * @param WP_Post $display_post The post being rendered (from the global $post).
 * @return array  Array of filtered post objects.
 */
function adaptive_filter_children( $children, $display_has_children, $display_post ) {

	// If there aren't child posts, return nothing.
	if ( ( ! is_array( $children ) ) || ( ! count( $children ) > 0 ) ) {
		return;
	}

	$potentials = array();

	foreach ( $children as $child ) {

		// Only include the current page from the list of siblings if we have children.
		if ( $display_has_children && (int) $child->ID === (int) $display_post->ID ) {
			array_push( $potentials, $child );
		}

		if ( ! $display_has_children ) {
			// If we don't have children...
			// Display siblings of current page also
			if ( $child->post_parent == $display_post->post_parent ) {
				array_push( $potentials, $child );
			}
			// Display the parent page
			if ( $child->ID == $display_post->post_parent ) {
				array_push( $potentials, $child );
			}
		}

		// Include pages that are children of the current page
		if ( $child->post_parent == $display_post->ID ) {
			array_push( $potentials, $child );
		}
	}

	return $potentials;

}
