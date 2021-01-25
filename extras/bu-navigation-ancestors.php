<?php

/**
 * Appends an "active_section" property to every post being returned during bu_navigation_get_pages
 *
 * @param array $pages Associative array of pages keyed on page ID
 * @return array Filtered associative array of pages with active_section member variable set
 */
function bu_navigation_filter_pages_ancestors( $pages ) {
	global $post;

	// Only useful during single post query, so return early if there's no global post.
	if ( ! $post ) {
		return $pages;
	}

	// Only needed for hierarchical post types, so return early if this post isn't.
	$post_type_object = get_post_type_object( $post->post_type );
	if ( ! $post_type_object->hierarchical ) {
		return $pages;
	}

	// If there aren't any elements in $pages, just return an empty array.
	if ( ! is_array( $pages ) || ! ( count( $pages ) > 0 ) ) {
		return array();
	}

	$ancestors = bu_navigation_gather_sections( $post->ID, array( 'post_types' => $post->post_type ) );

	$filtered = array();

	if ( is_array( $ancestors ) && ( count( $ancestors ) > 0 ) ) {
		foreach ( $pages as $page ) {

			$page->active_section = false;

			if ( in_array( $page->ID, $ancestors ) && $page->ID != $post->ID ) {
				$page->active_section = true;
			}

			$filtered[ $page->ID ] = $page;
		}
	} else {
		$filtered = $pages;
	}

	return $filtered;
}

add_filter( 'bu_navigation_filter_pages', 'bu_navigation_filter_pages_ancestors' );
