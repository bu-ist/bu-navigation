<?php
/**
 * Formatting methods that are specific to the adaptive mode widget.
 *
 * Adaptive navigation mode tames list size for large site hierarchies by
 * "drilling down" based on the currently active post, limiting listings
 * to no more than two levels (plus section title if configured).
 *
 * @see [Content Navigation Widget Modes](https://github.com/bu-ist/bu-navigation/wiki/Content-Navigation-Widget)
 *
 * @package BU_Navigation
 */

namespace BU\Plugins\Navigation;

/**
 * Return a slice of the section elements for the 'adaptive' widget mode
 *
 * The page_id parameter should be examined in conjunction with the widget_bu_pages_args filter,
 * which may be an artifact than can be unwound.
 *
 * @since 1.2.24
 * @see \BU\Plugins\Navigation\list_pages()
 *
 * @param mixed $page_id String or int; see note below, this is likely never anything but null due to the widget_bu_pages_args filter.
 * @param array $pages_by_parent Array where the key is a post id, and the value is an array of post objects.
 * @param array $sections Array of post IDs, either strings or ints.
 * @return array Array of post ids.
 */
function adaptive_section_slice( $page_id, $pages_by_parent, $sections ) {
	// If the "active" page isn't in the list of sections (because it has no children), add it
	// @todo I don't think this can ever be true based on the code in bu-navigation-adaptive-contentnav.php.
	if ( $page_id && ! in_array( $page_id, $sections, true ) ) {
		array_push( $sections, $page_id );
	}

	// If the section count is only 2 or below, return it unmodified as it does not need slicing.
	if ( count( $sections ) < 3 ) {
		return $sections;
	}

	$last_section = array_pop( $sections );
	array_push( $sections, $last_section );

	if ( array_key_exists( $last_section, $pages_by_parent )
		&& is_array( $pages_by_parent[ $last_section ] )
		&& ( count( $pages_by_parent[ $last_section ] ) > 0 )
	) {
		// Last section has children, so it will be the "top".
		return array_slice( $sections, -2 );
	}

	// Last section has no children, so its parent will be the "top".
	return array_slice( $sections, -3 );

}

/**
 * Filters arguments passed to bu_navigation_list_pages from widget display
 *
 * This is an ugly way of short circuiting the logic within bu_navigation_list_pages to not
 * display all sections.
 *
 * @param array $args Associative array of arguments for the list pages query.
 * @return array Array of arguments transformed for adaptive mode.
 */
function adaptive_pages_args( $args ) {
	if ( $args['page_id'] ) {
		$section_args = array( 'post_types' => $args['post_types'] );
		$sections     = gather_sections( $args['page_id'], $section_args );

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
function adaptive_pages_filter( $pages_by_parent ) {
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

	$filtered = array_filter(
		$children,
		function ( $child ) use ( $display_has_children, $display_post ) {
			// Only include the current page from the list of siblings if the current page has children.
			if ( $display_has_children && (int) $child->ID === (int) $display_post->ID ) {
				return true;
			}

			// If the display post doensn't have children, display siblings of current page also.
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
		}
	);

	// Re-index the array keys, since array_filter preserves them.
	// This is just to match the previous behavior, it is unclear if it is necessary and can probably be removed.
	return array_values( $filtered );

}
