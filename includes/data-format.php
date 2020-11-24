<?php
/**
 * Formatting methods
 *
 * @package BU_Navigation
 */

namespace BU\Plugins\Navigation;

/**
 * Takes the results of the custom parents query and maps them into the 'section' and 'pages' format.
 *
 * @since 1.2.24
 *
 * @param array $rows Array of objects from $wpdb, where each object has a 'section' and 'children property.
 * @return array
 */
function transform_rows( $rows ) {

	// If $rows is malformed or empty, return an empty result.
	if ( ! is_array( $rows ) || 0 === count( $rows ) ) {
		return array(
			'sections' => array(),
			'pages'    => array(),
		);
	}

	// Construct the 'section' array with elements where the key is the parent post ID and the value is an array of child post ids.
	$sections = array();
	foreach ( $rows as $row ) {
		$sections[ $row->section ] = explode( ',', $row->children );
	}

	// Construct the 'pages' array with elements where the key is the child id and the value is the parent id.
	// Seems like something like array_reduce() would be more elegant, but returning significant keys is a challenge.
	$pages = array();
	foreach ( $sections as $parent_id => $children_ids ) {
		foreach ( $children_ids as $child_id ) {
			$pages[ $child_id ] = strval( $parent_id );
		}
	}

	return array(
		'sections' => $sections,
		'pages'    => $pages,
	);
}
