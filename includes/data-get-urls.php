<?php
/**
 * Methods for getting urls associated with posts loaded elsewhere.
 *
 * @package BU_Navigation
 */

namespace BU\Plugins\Navigation;

/**
 * Calculate the post path for a post.
 *
 * Loops backwards from $page through $ancestors to determine full post path.
 * If any ancestor is not present in $ancestors it will attempt to load them on demand.
 * Utilizes static caching to minimize repeat queries across calls.
 *
 * @param  object $page      Post object to query path for. Must contain ID, post_name and post_parent fields.
 * @param  array  $ancestors An array of post objects keyed on post ID.  Should contain ancestors of $page,
 *                           with ID, post_name and post_parent fields for each.
 * @return string            Page path.
 */
function get_page_uri( $page, $ancestors ) {

	// Used to cache pages we load that aren't contained in $ancestors.
	static $extra_pages   = array();
	static $missing_pages = array();

	$uri = $page->post_name;

	while ( isset( $page->post_parent ) && $page->post_parent != 0 ) {

		// Avoid infinite loops
		if ( $page->post_parent == $page->ID ) {
			break;
		}

		// Attempt to load missing ancestors.
		if ( ! array_key_exists( $page->post_parent, $ancestors ) ) {
			if ( ! array_key_exists( $page->post_parent, $extra_pages ) && ! in_array( $page->post_parent, $missing_pages ) ) {
				$missing_ancestors = get_page_uri_ancestors( $page );
				// Cache any ancestors we load here or can't find in separate data structures.
				if ( ! empty( $missing_ancestors ) ) {
					$extra_pages = $extra_pages + $missing_ancestors;
				} else {
					// Add to our tracking list of pages we've already looked for.
					$missing_pages[] = $page->post_parent;
				}
			}

			// Merge passed in ancestors with extras we've loaded along the way.
			$ancestors = $ancestors + $extra_pages;
		}

		// We can't return an incomplete path -- bail with indication of failure.
		if ( ! array_key_exists( $page->post_parent, $ancestors ) ) {
			break;
		}

		// Append parent post name and keep looping backwards.
		$parent = $ancestors[ $page->post_parent ];
		if ( is_object( $parent ) && ! empty( $parent->post_name ) ) {
			$uri = $parent->post_name . '/' . $uri;
		}

		$page = $parent;
	}

	return $uri;
}

/**
 * Undocumented function
 *
 * Docs in progress.
 */
function get_page_uri_ancestors( $post ) {

	$ancestors    = array();
	$all_sections = bu_navigation_load_sections( $post->post_type );

	// Load ancestors post IDs
	$section_ids = bu_navigation_gather_sections( $post->ID, array( 'post_types' => $post->post_type ), $all_sections );
	$section_ids = array_filter( $section_ids );

	// Fetch ancestor posts, with only the columns we need to determine permalinks
	if ( ! empty( $section_ids ) ) {
		$args = array(
			'post__in'              => $section_ids,
			'post_types'            => 'any',
			'post_status'           => 'any',
			'suppress_urls'         => true,
			'suppress_filter_posts' => true,
		);

		// Only need a few fields to determine the correct URL.
		add_filter( 'bu_navigation_filter_fields', '_bu_navigation_page_uri_ancestors_fields', 9999 );
		$ancestors = bu_navigation_get_posts( $args );
		remove_filter( 'bu_navigation_filter_fields', '_bu_navigation_page_uri_ancestors_fields', 9999 );

		if ( false === $ancestors ) {
			$ancestors = array();
		}
	}

	return $ancestors;
}
