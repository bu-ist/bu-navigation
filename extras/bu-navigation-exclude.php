<?php

// Name of meta_key used to exclude pages from navigation
define( 'BU_NAV_META_PAGE_EXCLUDE', '_bu_cms_navigation_exclude' );

// Default post exclusion value for posts that don't have a post meta row yet
if ( ! defined( 'BU_NAVIGATION_POST_EXCLUDE_DEFAULT' ) )
	define( 'BU_NAVIGATION_POST_EXCLUDE_DEFAULT', false );

/**
 * Built-in filter for bu_navigation_get_pages
 *
 * Removes any posts that have "Display in navigation lists" unchecked
 *
 * Note that new posts are excluded by default.  In the case where no meta value exists yet,
 * the post will be excluded from navigation lists.
 */
function bu_navigation_filter_pages_exclude( $pages ) {
	global $wpdb;

	$filtered = array();

	if ( is_array( $pages ) && count( $pages ) > 0 ) {

		// Fetch pages that have been explicitly excluded from navigation lists
		$ids = array_keys( $pages );
		$query = sprintf( "SELECT post_id, meta_value FROM %s WHERE meta_key = '%s' AND post_id IN (%s)",
			$wpdb->postmeta,
			BU_NAV_META_PAGE_EXCLUDE,
			implode( ',', $ids )
			);
		$exclude_meta = $wpdb->get_results( $query, OBJECT_K );

		if ( false === $exclude_meta ) {
			error_log( __FUNCTION__ . " - Error querying navigation exclusions: {$wpdb->last_error}" );
			return $pages;
		}

		foreach ( $pages as $page ) {

			// Post meta row exists, determine exclusion based on meta_value
			if ( array_key_exists( $page->ID, $exclude_meta ) ) {
				$excluded = (bool) $exclude_meta[ $page->ID ]->meta_value;
			} else {

				// No post meta row has been inserted yet
				if ( isset( $page->post_type ) && BU_NAVIGATION_LINK_POST_TYPE == $page->post_type ) {
					// Navigation links get special treatment since they will always be visible
					$excluded = false;
				} else {
					// Otherwise fall back to default constant
					$excluded = BU_NAVIGATION_POST_EXCLUDE_DEFAULT;
				}
			}

			if ( ! $excluded )
				$filtered[ $page->ID ] = $page;
		}

	}

	return $filtered;
}

add_filter( 'bu_navigation_filter_pages', 'bu_navigation_filter_pages_exclude' );

/**
 * Tells you if a post is excluded from navigation lists
 *
 * Content editors set this value through the "Display in navigation lists" checkbox
 * in the "Placement in Navigation" metabox.
 *
 * @param mixed $post a post ID or object to determine excluded status for
 * @return bool true if the post is excluded, false otherwise
 */
function bu_navigation_post_excluded( $post ) {
	if( is_numeric( $post ) ) {
		$post = get_post( $post );
	}

	if( ! is_object( $post ) ) {
		return false;
	}

	$excluded = get_post_meta( $post->ID, BU_NAV_META_PAGE_EXCLUDE, true );

	// No value set yet, fall back to default
	if ( $excluded === '' )
		$excluded = BU_NAVIGATION_POST_EXCLUDE_DEFAULT;

	return (bool) $excluded;

}

?>