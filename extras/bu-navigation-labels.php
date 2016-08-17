<?php
// Name of meta_key used to hold navigation labels
define('BU_NAV_META_PAGE_LABEL', '_bu_cms_navigation_page_label');

/**
 * Built-in filter for bu_navigation_get_pages
 *
 * Adds a "navigation_label" property to each post object, fed from
 * the "Placement in Navigation" "metabox "Label" text field
 */
function bu_navigation_filter_pages_navlabels( $pages ) {
	global $wpdb;

	$filtered = array();

	if ( is_array( $pages ) && count( $pages ) > 0 ) {

		$ids = array_keys( $pages );
		$query = sprintf( "SELECT post_id, meta_value FROM %s WHERE meta_key = '%s' AND post_id IN (%s) AND meta_value != ''",
			$wpdb->postmeta,
			BU_NAV_META_PAGE_LABEL,
			implode( ',', $ids )
			);
		$labels = $wpdb->get_results( $query, OBJECT_K );

		if ( is_array( $labels ) && count( $labels ) > 0 ) {
			foreach ( $pages as $page ) {
				if ( array_key_exists( $page->ID, $labels ) ) {
					$label = $labels[ $page->ID ];
					$page->navigation_label = $label->meta_value;
				}
				$filtered[ $page->ID ] = $page;
			}
		} else {
			$filtered = $pages;
		}
	}

	return $filtered;
}

add_filter( 'bu_navigation_filter_pages', 'bu_navigation_filter_pages_navlabels' );
add_filter( 'bu_navigation_filter_page_labels', 'bu_navigation_filter_pages_navlabels' );

/**
 * Get the navigation label for a post
 *
 * Content editors set this value through the "Label" text field in the
 * "Placement in Navigation" metabox.
 *
 * @param mixed $post post ID or object to fetch label for
 * @param string $empty_label label to use if no existing value was found
 * @return string the post's navigation, or $empty_label if none was found
 */
function bu_navigation_get_label( $post, $empty_label = '(no title)' ) {
	if( is_numeric( $post ) ) {
		$post = get_post( $post );
	}

	if( ! is_object( $post ) ) {
		return false;
	}

	$label = get_post_meta( $post->ID, BU_NAV_META_PAGE_LABEL, true );

	if( ! $label ) {
		$label = $post->post_title;
	}

	if( empty( $label ) ) {
		$label = $empty_label;
	}

	return $label;

}
