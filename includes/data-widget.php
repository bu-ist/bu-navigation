<?php
/**
 * Formatting methods that are specific to the widget (mostly adaptive mode).
 *
 * @package BU_Navigation
 */

namespace BU\Plugins\Navigation;

/**
 * Return a slice of the section elements for the 'adaptive' widget mode
 *
 * The page_id parameter should be examined in conjunction with the widget_bu_pages_args filter,
 * which may be an artifact than can be unwound.
 *
 * @since 1.2.24
 * @see \BU\Plugins\Navigation\list_pages()
 *
 * @param mixed $page_id String or int; see note below, this is likely never anything but null due to the widget_bu_pages_args filter.
 * @param array $pages_by_parent Array where the key is a post id, and the value is an array of post objects.
 * @param array $sections Array of post IDs, either strings or ints.
 * @return array Array of post ids.
 */
function adaptive_section_slice( $page_id, $pages_by_parent, $sections ) {
	// If the "active" page isn't in the list of sections (because it has no children), add it
	// @todo I don't think this can ever be true based on the code in bu-navigation-adaptive-contentnav.php.
	if ( $page_id && ! in_array( $page_id, $sections, true ) ) {
		array_push( $sections, $page_id );
	}

	// If the section count is only 2 or below, return it unmodified as it does not need slicing.
	if ( count( $sections ) < 3 ) {
		return $sections;
	}

	$last_section = array_pop( $sections );
	array_push( $sections, $last_section );

	if ( array_key_exists( $last_section, $pages_by_parent )
		&& is_array( $pages_by_parent[ $last_section ] )
		&& ( count( $pages_by_parent[ $last_section ] ) > 0 )
	) {
		// Last section has children, so it will be the "top".
		return array_slice( $sections, -2 );
	}

	// Last section has no children, so its parent will be the "top".
	return array_slice( $sections, -3 );

}
