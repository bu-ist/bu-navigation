<?php
/**
 * Navigation post_meta labels
 *
 * Functions that load and attach the navigation label to posts.
 *
 * @package BU_Navigation
 */

namespace BU\Plugins\Navigation;

// Name of meta_key used to hold navigation labels.
define( 'BU_NAV_META_PAGE_LABEL', '_bu_cms_navigation_page_label' );

/**
 * Built-in filter for bu_navigation_get_pages
 *
 * Adds a "navigation_label" property to each post object, fed from
 * the "Placement in Navigation" "metabox "Label" text field.
 *
 * Originally called bu_navigation_filter_pages_navlabels.
 *
 * @param array $pages Array of objects representing individual posts.
 *
 * @return array Array of objects representing individual posts with the navigation_label added
 */
function attach_nav_labels( $pages ) {
	global $wpdb;

	// If $pages isn't valid, just return an empty array.
	if ( ! is_array( $pages ) || ! count( $pages ) > 0 ) {
		return array();
	}

	// Otherwise, calculate the labels for all of the given pages.
	// First, load the label meta values for every post ID with a direct database query.
	$ids   = array_keys( $pages );
	$query = sprintf(
		"SELECT post_id, meta_value FROM %s WHERE meta_key = '%s' AND post_id IN (%s) AND meta_value != ''",
		$wpdb->postmeta,
		BU_NAV_META_PAGE_LABEL,
		implode( ',', $ids )
	);

	$labels = $wpdb->get_results( $query, OBJECT_K );

	// If no labels were retrieved, just return the original unmodified $pages array.
	if ( ! is_array( $labels ) || ! count( $labels ) > 0 ) {
		return $pages;
	}

	// Otherwise attach any found labels.
	$filtered = array_map(
		function ( $page ) use ( $labels ) {
			if ( array_key_exists( $page->ID, $labels ) ) {
				$page->navigation_label = $labels[ $page->ID ]->meta_value;
			}
			return $page;
		},
		$pages
	);

	return $filtered;
}

add_filter( 'bu_navigation_filter_pages', __NAMESPACE__ . '\attach_nav_labels' );
add_filter( 'bu_navigation_filter_page_labels', __NAMESPACE__ . '\attach_nav_labels' );

/**
 * Get the navigation label for a post
 *
 * Content editors set this value through the "Label" text field in the
 * "Placement in Navigation" metabox.
 *
 * Originally named bu_navigation_get_label().
 *
 * @param mixed  $post Post ID or object to fetch label for.
 * @param string $empty_label Label to use if no existing value was found.
 * @return string The post's navigation label, or $empty_label if none was found
 */
function get_nav_label( $post, $empty_label = '(no title)' ) {
	if ( is_numeric( $post ) ) {
		$post = get_post( $post );
	}

	if ( ! is_object( $post ) ) {
		return false;
	}

	$label = get_post_meta( $post->ID, BU_NAV_META_PAGE_LABEL, true );

	if ( ! $label ) {
		$label = $post->post_title;
	}

	if ( empty( $label ) ) {
		$label = $empty_label;
	}

	return $label;

}
