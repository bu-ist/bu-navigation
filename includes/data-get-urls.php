<?php
/**
 * Methods for getting urls associated with posts loaded elsewhere.
 *
 * @package BU_Navigation
 */

namespace BU\Plugins\Navigation;

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
