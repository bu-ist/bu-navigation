<?php
/**
 * Formatting methods, to transform data that has been loaded by the
 * data model methods.
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

/**
 * A front end to load_sections() that provides some pre and post processing.
 *
 * Theory: where load_sections() returns the entire family tree, gather_sections is
 * more directed to providing just ancestors or decendants.
 * This function is in direct use from global scope by several themes.
 * A survey of the use in BU themes indicates that there are only 2 options for direction: 'up' or 'down'.
 *
 * @see BU\Plugins\Navigation\load_sections()
 * @see BU\Plugins\Navigation\gather_childsections()
 *
 * @param mixed $page_id ID of the page to gather sections for (string | int).
 * @param mixed $args Wordpress-style arguments (string or array).
 * @param array $all_sections Associative array of parents with all of their direct children.  Appears to be actually unused and should be removed as an argument.
 * @return array
 */
function gather_sections( $page_id, $args = '', $all_sections = null ) {
	$defaults    = array(
		'direction'     => 'up',
		'depth'         => 0,
		'post_types'    => array( 'page' ),
		'include_links' => true,
	);
	$parsed_args = wp_parse_args( $args, $defaults );

	if ( is_null( $all_sections ) ) {
		$all_sections = load_sections( $parsed_args['post_types'], $parsed_args['include_links'] );
	}

	$pages    = $all_sections['pages'];
	$sections = array();

	// Include the current page as a section if it has any children.
	if ( array_key_exists( $page_id, $all_sections['sections'] ) ) {
		array_push( $sections, $page_id );
	}

	// Gather descendants or ancestors depending on direction.
	if ( 'down' === $parsed_args['direction'] ) {

		$child_sections = gather_childsections( $page_id, $all_sections['sections'], $parsed_args['depth'] );

		if ( count( $child_sections ) > 0 ) {
			$sections = array_merge( $sections, $child_sections );
		}
	}

	if ( 'up' === $parsed_args['direction'] && array_key_exists( $page_id, $pages ) ) {
		$sections = gather_ancestor_sections( $page_id, $pages, $sections );
	}

	return array_reverse( $sections );
}

/**
 * Gets a section of children given a post ID and some arguments.
 *
 * Originally called bu_navigation_gather_childsections().
 *
 * @param string  $parent_id ID of a parent post expressed as a string.
 * @param array   $sections All of the sections at the depth being gathered.
 * @param integer $max_depth Maximum depth to gather.
 * @param integer $current_depth Current depth from gather_sections() args.
 * @return array Array of page ids.
 */
function gather_childsections( $parent_id, $sections, $max_depth = 0, $current_depth = 1 ) {
	$child_sections = array();

	// Validate the existence of children, otherwise return an empty array early.
	if ( ( ! array_key_exists( $parent_id, $sections ) ) || ( 0 === count( $sections[ $parent_id ] ) ) ) {
		return $child_sections;
	}

	// Iterate over the array of children of the given parent.
	foreach ( $sections[ $parent_id ] as $child_id ) {
		if ( ( array_key_exists( $child_id, $sections ) ) && ( count( $sections[ $child_id ] ) > 0 ) ) {
			array_push( $child_sections, $child_id );

			if ( ( 0 === $max_depth ) || ( $current_depth < $max_depth ) ) {
				$child_sections = array_merge( $child_sections, gather_childsections( $child_id, $sections, $max_depth, ( $current_depth + 1 ) ) );
			}
		}
	}

	return $child_sections;
}

/**
 * Adds nodes above a given page id to a given section array.
 *
 * Originally called bu_navigation_gather_ancestor_sections().
 *
 * @param mixed $page_id ID of the page to gather sections for (string | int).
 * @param array $pages Array of pages from load_sections.
 * @param array $sections The sections array being added to.
 * @return array New array of sections with the ancestors added.
 */
function gather_ancestor_sections( $page_id, $pages, $sections ) {
	$current_section = $pages[ $page_id ];
	array_push( $sections, $current_section );

	while ( 0 !== $current_section ) {
		if ( array_key_exists( $current_section, $pages ) ) {
			$current_section = $pages[ $current_section ];
			array_push( $sections, $current_section );
		} else {
			break;
		}
	}

	return $sections;
}

/**
 * Indexes an array of pages by their parent page ID
 *
 * @param array $pages Array of page objects (usually indexed by the post.ID).
 * @return array Array of arrays indexed on post.ID with second-level array containing the immediate children of that post
 */
function pages_by_parent( $pages ) {

	if ( ! is_array( $pages ) && ! count( $pages ) > 0 ) {
		return array();
	}

	$pages_by_parent = array();
	foreach ( $pages as $page ) {
		if ( ! array_key_exists( $page->post_parent, $pages_by_parent ) ) {
			$pages_by_parent[ $page->post_parent ] = array();
		}
		array_push( $pages_by_parent[ $page->post_parent ], $page );
	}

	return $pages_by_parent;
}
