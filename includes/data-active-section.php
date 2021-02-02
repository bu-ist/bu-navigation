<?php
/**
 * Filter to add an "active_section" property to an array of pages, based on the global post ancestors
 *
 * The function retruns a transformed array where the active_section property is true or false
 * based whether the array element is part of the displayed post's ancestors.
 * Should probably be consolidated with other filter functions.
 *
 * @package BU_Navigation
 */

namespace BU\Plugins\Navigation;

/**
 * Appends an "active_section" property to every post being returned during bu_navigation_get_pages
 *
 * Originally called bu_navigation_filter_pages_ancestors()
 *
 * @param array $pages Associative array of pages keyed on page ID.
 * @return array Filtered associative array of pages with active_section member variable set
 */
function add_active_section( $pages ) {
	global $post;

	// Only useful during single post query, so return early if there's no global post.
	// Or if the current post_type isn't hierarchical.
	if ( ! $post || ! get_post_type_object( $post->post_type )->hierarchical ) {
		return $pages;
	}

	// If there aren't any elements in $pages, just return an empty array.
	if ( ! is_array( $pages ) || ! ( count( $pages ) > 0 ) ) {
		return array();
	}

	$ancestors = gather_sections( $post->ID, array( 'post_types' => $post->post_type ) );

	// Return the pages unmodified if there are no ancestors.
	if ( ! is_array( $ancestors ) && ! ( count( $ancestors ) > 0 ) ) {
		return $pages;
	}

	// Otherwise, iterate over all the pages and add the active_section property.
	$filtered = array_map(
		function ( $page ) use ( $post, $ancestors ) {
			$page->active_section = ( in_array( $page->ID, $ancestors ) && $page->ID != $post->ID );
			return $page;
		},
		$pages
	);

	return $filtered;
}

add_filter( 'bu_navigation_filter_pages', __NAMESPACE__ . '\add_active_section' );
