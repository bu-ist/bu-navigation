<?php
/**
 * Data model methods
 *
 * Provides several methods to the global scope for pulling
 * navigation related data from the database.
 *
 * A 'section' here is an associative array of parent pages bundled with their children.
 * The key of each element is the parent page id, and the value is an array of the children page ids.
 *
 * @package BU_Navigation
 */

use BU\Plugins\Navigation as Navigation;

if ( defined( 'BU_NAVIGATION_LIB_LOADED' ) && BU_NAVIGATION_LIB_LOADED ) {
	return;
}

define( 'BU_NAVIGATION_LIB_LOADED', true );


/**
 * Gets the supported post_types by the bu-navigation plugin.
 *
 * This is just a wrapper that calls the underlying global plugin class method.
 *
 * @param boolean $include_link true|false link post_type is something special, so we don't always need it.
 * @param string  $output type of output (names|objects).
 * @return array of post_type strings
 */
function bu_navigation_supported_post_types( $include_link = false, $output = 'names' ) {
	global $bu_navigation_plugin;

	return $bu_navigation_plugin->supported_post_types( $include_link, $output );

}

/**
 * Returns a complex array that describes the entire navigation tree for the specified post types.
 *
 * The return array contains 2 arrays named 'sections' and 'pages'.
 *
 * The 'sections' array contains elements where the key is the ID of a parent post
 * and the value is an array of page IDs of all direct descendents.  The 'sections' array
 * then is a collection of *every post that has direct children*, grouped with an array of the children one level deep.
 * It may be a somewhat counter-intuitive way to return the data, but is is a data efficient way
 * to fetch it because it leverages the 'GROUP BY' SQL operator.  The 'sections' array must be further parsed
 * to assemble all of the branches of the hierarchical tree.
 *
 * The 'pages' array contains an element for *every post that has a parent*.
 * The key of each element is the post ID, and the value is the post ID of the parent post.
 *
 * These 2 arrays contain the entire tree expressed as atomic one-level-deep elements, with
 * 'sections' expressing the top down view, and 'pages' expressing the bottom up view.
 *
 * The nomenclature of 'sections' and 'pages' is not the most descriptive, the are really something more like
 * 'parents_with_children' and 'children_with_parents'.
 *
 * This is now a global stub function for compatibility with themes that expect the global prefixed function.
 * The primary function has moved to a namespaced function.
 *
 * @global object $wpdb
 * @param mixed $post_types Optional, can be an array of post types, or a string containing post type names.
 * @param bool  $include_links Whether or not to include links (with pages only).
 * @return array (sections => array(parent1_id => [child1_id, ...], ...), pages => array( child1_id => parent1_id, ... )
 */
function bu_navigation_load_sections( $post_types = array( 'page' ), $include_links = true ) {
	return Navigation\load_sections( $post_types, $include_links );
}

/**
 * A front end to bu_navigation_load_sections() that provides some pre and post processing.
 *
 * Theory: where load_sections() returns the entire family tree, gather_sections is
 * more directed to providing just ancestors or decendants.
 * This function is in direct use from global scope by several themes.
 * A survey of the use in BU themes indicates that there are only 2 options for direction: 'up' or 'down'.
 *
 * This is now a global stub function for compatibility with themes that expect the global prefixed function.
 * The primary function has moved to a namespaced function.
 *
 * @see bu_navigation_load_sections()
 * @see bu_navigation_gather_childsections()
 *
 * @param mixed $page_id ID of the page to gather sections for (string | int).
 * @param mixed $args Wordpress-style arguments (string or array).
 * @param array $all_sections Associative array of parents with all of their direct children.  Appears to be actually unused and should be removed as an argument.
 * @return array
 */
function bu_navigation_gather_sections( $page_id, $args = '', $all_sections = null ) {
	return Navigation\gather_sections( $page_id, $args, $all_sections );
}

/**
 * This is the only function that appears to allow for the the 3rd 'all_sections' arg from gather_sections.
 * It is entirely unusued at BU except for the bu-tech-workflow.php template in the bu-tech-2014 theme.
 * Ideally this function should be deprecated, and the 'all_sections' arg should be removed from gather_sections.
 *
 */
function bu_navigation_get_page_depth( $page_id, $all_sections = null ) {
	$ancestry = bu_navigation_gather_sections( $page_id, null, $all_sections );

	$depth = count( $ancestry );

	if ( ! in_array( $page_id, $ancestry ) ) {
		$depth++;
	}

	$depth--;

	return $depth;
}

/**
 * Get the navigation label for a post
 *
 * Content editors set this value through the "Label" text field in the
 * "Placement in Navigation" metabox.
 *
 * This is now a global stub function for compatibility with themes that expect the global prefixed function.
 * The primary function has moved to a namespaced function.
 *
 * @param mixed  $post Post ID or object to fetch label for.
 * @param string $empty_label Label to use if no existing value was found.
 * @return string The post's navigation label, or $empty_label if none was found
 */
function bu_navigation_get_label( $post, $empty_label = '(no title)' ) {
	return Navigation\get_nav_label( $post, $empty_label );
}

/**
 * Returns an array of page objects indexed by page ID
 *
 * This function and one other (load_sections) are the only actual data loading methods.
 *
 * This is now a global stub function for compatibility with themes that expect the global prefixed function.
 * The primary function has moved to a namespaced function \BU\Plugins\Navigation\get_nav_posts.
 *
 * @param mixed $args Wordpress-style arguments (string or array).
 * @return array Array of pages keyed on page ID or FALSE on problem
 */
function bu_navigation_get_posts( $args = '' ) {

	return Navigation\get_nav_posts( $args );

}

/**
 * Legacy alias for bu_navigation_get_posts
 *
 * Translates legacy arguments that have been updated for consistency with WP_Query
 *
 * This is now a global stub function for compatibility with themes that expect the global prefixed function.
 * The primary function has moved to a namespaced function.
 *
 * @param mixed $args  Wordpress-style arguments (string or array).
 * @return array Array of pages keyed on page ID or FALSE on problem
 */
function bu_navigation_get_pages( $args = '' ) {
	return Navigation\get_nav_pages( $args );
}

/**
 * Indexes an array of pages by their parent page ID
 *
 * @param array $pages Array of page objects (usually indexed by the post.ID).
 * @return array Array of arrays indexed on post.ID with second-level array containing the immediate children of that post
 */
function bu_navigation_pages_by_parent( $pages ) {
	return Navigation\pages_by_parent( $pages );
}

/**
 * Add this filter before calling bu_navigation_pages_by_parent to sort each sub-array by menu order.
 *
 * @param array $pages
 * @return array
 */
function bu_navigation_pages_by_parent_menu_sort( $pages ) {
	if ( is_array( $pages ) ) {
		foreach ( $pages as $parent_id => &$children ) {
			usort( $children, 'bu_navigation_pages_by_parent_menu_sort_cb' );
		}
	}

	return $pages;
}

/**
 * Callback for bu_navigation_pages_by_parent_menu_sort.
 */
function bu_navigation_pages_by_parent_menu_sort_cb( $a, $b ) {
	return ( $a->menu_order - $b->menu_order );
}

/**
 * Formats a single page for display in a HTML list
 *
 * This is now a global stub function for compatibility with themes that expect the global prefixed function.
 * The primary function has moved to a namespaced function.
 *
 * @param object $page Page object.
 * @param mixed  $args Wordpress-style arguments (string or array).
 * @return string HTML fragment containing list item
 */
function bu_navigation_format_page( $page, $args = '' ) {
	return Navigation\format_page( $page, $args );
}

/**
 * Generates an unordered list tree of pages in a particular section
 *
 * This is now a global stub function for compatibility with themes that expect the global prefixed function.
 * The primary function has moved to a namespaced function.
 *
 * @param int   $parent_id ID of section (page parent).
 * @param array $pages_by_parent An array of pages indexed by their parent page (see bu_navigation_pages_by_parent).
 * @param mixed $args Array or string of WP-style arguments.
 * @return string HTML fragment containing unordered list
 */
function bu_navigation_list_section( $parent_id, $pages_by_parent, $args = '' ) {
	return Navigation\list_section( $parent_id, $pages_by_parent, $args );
}

/**
 * Alternative to WordPress' wp_list_pages function
 *
 * Externally it is used by the r-editorial theme and associated child themes.
 *
 * This is now a global stub function for compatibility with themes that expect the global prefixed function.
 * The primary function has moved to a namespaced function.
 *
 * @param mixed $args Array or string of WP-style arguments.
 * @return string HTML fragment containing navigation list
 */
function bu_navigation_list_pages( $args = '' ) {
	return Navigation\list_pages( $args );
}

/**
 * Displays a primary navigation bar
 *
 * This function isn't invoked anywhere from the plugin, but is called from the global scope by several themes.
 * The return value here is ambiguous.  The function consistently does return the html sting,
 * however by default is also directly echos the string (based on an overrideable parameter in args
 * called 'echo').
 *
 * @todo Consider resolving the return/echo behavior of the function and refactor it to do just one or the other.
 *
 * @param mixed $args Wordpress-style arguments (string or array).
 * @return string A string of formatted html.
 */
function bu_navigation_display_primary( $args = '' ) {
	$defaults = array(
		'post_types'      => array( 'page' ),
		'include_links'   => true,
		'depth'           => BU_NAVIGATION_PRIMARY_DEPTH,
		'max_items'       => BU_NAVIGATION_PRIMARY_MAX,
		'dive'            => true,
		'container_tag'   => 'ul',
		'container_id'    => 'nav',
		'container_class' => '',
		'item_tag'        => 'li',
		'identify_top'    => false,
		'whitelist_top'   => null,
		'echo'            => 1,
		'title_before'    => '',
		'title_after'     => '',
	);
	$r        = wp_parse_args( $args, apply_filters( 'bu_filter_primarynav_defaults', $defaults ) );

	// Gather all sections.
	$section_args = array(
		'direction'     => 'down',
		'depth'         => $r['depth'],
		'post_types'    => $r['post_types'],
		'include_links' => $r['include_links'],
	);
	$sections     = bu_navigation_gather_sections( 0, $section_args );

	// Fetch only posts in sections that we need.
	$post_args       = array(
		'sections'      => $sections,
		'post_types'    => $r['post_types'],
		'include_links' => $r['include_links'],
	);
	$pages           = bu_navigation_get_pages( $post_args );
	$pages_by_parent = bu_navigation_pages_by_parent( $pages );

	$top_level_pages = array();
	$html            = '';

	// Start displaying top level posts.
	if ( is_array( $pages_by_parent ) && isset( $pages_by_parent[0] ) && ( count( $pages_by_parent[0] ) > 0 ) ) {
		$top_level_pages = $pages_by_parent[0];
	}

	if ( ! empty( $top_level_pages ) ) {

		$nItems    = 0;
		$whitelist = null;

		// Optionally restrict top level posts to white list of post names.
		if ( $r['whitelist_top'] ) {
			if ( is_string( $r['whitelist_top'] ) ) {
				$whitelist = explode( ',', $r['whitelist_top'] );
			}
			if ( is_array( $r['whitelist_top'] ) ) {
				$whitelist = $r['whitelist_top'];
			}
		}

		// Start list.
		$html = sprintf(
			'<%s id="%s" class="%s %s">',
			$r['container_tag'],
			$r['container_id'],
			$r['container_class'],
			$r['dive'] ? '' : 'no-dive'
		);

		// Section arguments.
		$sargs = array(
			'container_tag' => $r['container_tag'],
			'item_tag'      => $r['item_tag'],
			'depth'         => 2,
		);

		foreach ( $top_level_pages as $page ) {

			// Check whitelist if it's being used.
			if ( is_array( $whitelist ) && ! in_array( $page->post_name, $whitelist ) ) {
				continue;
			}

			$child_html = '';

			// List children if we're diving.
			if ( $r['dive'] ) {
				$child_html = Navigation\list_section( $page->ID, $pages_by_parent, $sargs );
			}

			// Display formatted page (optionally with post name as ID).
			if ( $r['identify_top'] ) {
				$html .= Navigation\format_page(
					$page, array(
						'html'     => $child_html,
						'depth'    => 1,
						'item_tag' => $r['item_tag'],
						'item_id'  => $page->post_name,
					)
				);
			} else {
				$html .= Navigation\format_page(
					$page, array(
						'html'     => $child_html,
						'depth'    => 1,
						'item_tag' => $r['item_tag'],
					)
				);
			}

			$nItems++;

			// Limit to max number of posts.
			if ( $nItems >= $r['max_items'] ) {
				break;
			}
		}

		// Close list.
		$html .= sprintf( "\n</%s>\n", $r['container_tag'] );
	}

	if ( $r['echo'] ) {
		echo $html;
	}

	return $html;

}

/**
 * Generate page parent select menu
 *
 * This appears to be a single use function that is only called by admin/filter-pages.php.
 *
 * @todo Evalute moving this function to one of the admin files.
 *
 * @uses bu_filter_pages_parent_dropdown().
 *
 * @param string $post_type required -- post type to filter posts for.
 * @param int    $selected post ID of the selected post.
 * @param array  $args optional configuration parameters.
 *
 * @return string the resulting dropdown markup
 */
function bu_navigation_page_parent_dropdown( $post_type, $selected = 0, $args = array() ) {

	$defaults = array(
		'echo'           => 1,
		'select_id'      => 'bu_filter_pages',
		'select_name'    => 'post_parent',
		'select_classes' => '',
		'post_status'    => array( 'publish', 'private' ),
	);
	$r        = wp_parse_args( $args, $defaults );

	// Grab top level pages for current post type.
	$args     = array(
		'direction'  => 'down',
		'depth'      => 1,
		'post_types' => (array) $post_type,
	);
	$sections = bu_navigation_gather_sections( 0, $args );

	$args            = array(
		'suppress_filter_pages' => true,
		'sections'              => $sections,
		'post_types'            => (array) $post_type,
		'post_status'           => (array) $r['post_status'],
	);
	$pages           = bu_navigation_get_pages( $args );
	$pages_by_parent = bu_navigation_pages_by_parent( $pages );

	$options = "\n\t<option value=\"0\">" . __( 'Show all sections' ) . "</option>\r";

	// Get options
	ob_start();
	bu_filter_pages_parent_dropdown( $pages_by_parent, $selected );
	$options .= ob_get_contents();
	ob_end_clean();

	$classes = ! empty( $r['select_classes'] ) ? " class=\"{$r['select_classes']}\"" : '';

	$dropdown = sprintf( "<select id=\"%s\" name=\"%s\"%s>\r%s\r</select>\r", $r['select_id'], $r['select_name'], $classes, $options );

	if ( $r['echo'] ) {
		echo $dropdown;
	}

	return $dropdown;

}

/**
 * Displays a select box containing page parents, used to filter page list by parent
 *
 * Relocated from the navigation plugin (bu-filter-pages.php) to remove dependency on plugin.
 * This is only called from bu_navigation_page_parent_dropdown() except for a reference in bu-site-inpection.
 *
 * @param array   $pages_by_parent
 * @param integer $default
 * @param integer $parent
 * @param integer $level
 * @return boolean TRUE if the box was displayed, FALSE otherwise.
 */
function bu_filter_pages_parent_dropdown( $pages_by_parent, $default = 0, $parent = 0, $level = 0 ) {

	$post_types = bu_navigation_supported_post_types();

	if ( ( is_array( $pages_by_parent ) ) && ( array_key_exists( $parent, $pages_by_parent ) ) && ( count( $pages_by_parent ) > 0 ) ) {
		foreach ( $pages_by_parent[ $parent ] as $p ) {

			if ( ! in_array( $p->post_type, $post_types ) ) {
				continue; // only show valid post types
			}
			if ( ! array_key_exists( $p->ID, $pages_by_parent ) ) {
				continue; // don't show pages with no children
			}

			$padding  = str_repeat( '&nbsp;', $level * 3 );
			$selected = ( $p->ID == $default ) ? 'selected="selected"' : '';

			printf( "\n\t<option value=\"%d\" %s>%s%s</option>\r", $p->ID, $selected, $padding, esc_html( $p->post_title ) );
			bu_filter_pages_parent_dropdown( $pages_by_parent, $default, $p->ID, $level + 1 );
		}

		return true;

	}

	return false;

}
