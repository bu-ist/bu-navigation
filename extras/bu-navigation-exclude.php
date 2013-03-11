<?php
// Name of meta_key used to exclude pages from navigation
define( 'BU_NAV_META_PAGE_EXCLUDE', '_bu_cms_navigation_exclude' );

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

		$ids = array_keys( $pages );
		$query = sprintf( "SELECT post_id, meta_value FROM %s WHERE meta_key = '%s' AND post_id IN (%s) AND meta_value != '0'",
			$wpdb->postmeta,
			BU_NAV_META_PAGE_EXCLUDE,
			implode( ',', $ids )
			);
		$exclusions = $wpdb->get_results( $query, OBJECT_K );

		if ( is_array( $exclusions ) && count( $exclusions ) > 0 ) {
			foreach ( $pages as $page ) {
				if ( ! array_key_exists( $page->ID, $exclusions ) ) {
					$filtered[ $page->ID ] = $page;
				}
			}
		} else {
			$filtered = $pages;
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

	// No value set yet, default to excluded
	if ( $excluded === '' )
		$excluded = true;

	return (bool) $excluded;

}

?>