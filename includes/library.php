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

define( 'GROUP_CONCAT_MAX_LEN', 20480 );

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
 * Takes the results of the custom parents query and maps them into the 'section' and 'pages' format.
 *
 * This is now a global stub function for compatibility with themes that expect the global prefixed function.
 * The primary function has moved to a namespaced function.
 *
 * @since 1.2.24
 *
 * @param array $rows Array of objects from $wpdb, where each object has a 'section' and 'children property.
 * @return array
 */
function bu_navigation_transform_rows( $rows ) {
	return Navigation\transform_rows( $rows );
}

/**
 * A front end to bu_navigation_load_sections() that provides some pre and post processing.
 *
 * Theory: where load_sections() returns the entire family tree, gather_sections is
 * more directed to providing just ancestors or decendants.
 * This function is in direct use from global scope by several themes.
 * A survey of the use in BU themes indicates that there are only 2 options for direction: 'up' or 'down'.
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
 * Adds nodes above a given page id to a given section array.
 *
 * @param mixed $page_id ID of the page to gather sections for (string | int).
 * @param array $pages Array of pages from load_sections.
 * @param array $sections The sections array being added to.
 * @return array New array of sections with the ancestors added.
 */
function bu_navigation_gather_ancestor_sections( $page_id, $pages, $sections ) {
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
 * Gets a section of children given a post ID and some arguments.
 *
 * @param string  $parent_id ID of a parent post expressed as a string.
 * @param array   $sections All of the sections at the depth being gathered.
 * @param integer $max_depth Maximum depth to gather.
 * @param integer $current_depth Current depth from gather_sections() args.
 * @return array Array of page ids.
 */
function bu_navigation_gather_childsections( $parent_id, $sections, $max_depth = 0, $current_depth = 1 ) {
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
				$child_sections = array_merge( $child_sections, bu_navigation_gather_childsections( $child_id, $sections, $max_depth, ( $current_depth + 1 ) ) );
			}
		}
	}

	return $child_sections;
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
 * Add the post permalink as a property on the post object.
 *
 * Helpful when you need URLs for a large number of posts and don't want to
 * melt your server with 3000 calls to `get_permalink()`.
 *
 * This is most efficient when $pages contains the complete ancestry for each post. If any post
 * ancestors are missing when calculating hierarchical post names it will load them,
 * at the expensive of a few extra queries.
 *
 * This is a stub for the new namespaced function, but there's no reason to think
 * any other themes or components are calling this. Likely it should be removed.
 *
 * @param  array $pages An array of post objects keyed on post ID. Works with all post types.
 * @return array $pages The input array with $post->url set to the permalink for each post.
 */
function bu_navigation_get_urls( $pages ) {
	return Navigation\get_urls( $pages );
}

/**
 * Retrieve the page permalink.
 *
 * Intended as an efficient alternative to `get_page_link()` / `_get_page_link()`.
 * Allows you to provide an array of post ancestors for use calculating post name path.
 *
 * This is a stub for the new namespaced function, but there's no reason to think
 * any other themes or components are calling this. Likely it should be removed.
 *
 * @see `_get_page_link()`
 *
 * @param  object  $page       Post object to calculate permalink for.
 * @param  array   $ancestors  Optional. An array of post objects keyed on post ID. Should contain all ancestors of $page.
 * @param  boolean $sample     Optional. Is it a sample permalink.
 * @return string              Post permalink.
 */
function bu_navigation_get_page_link( $page, $ancestors = array(), $sample = false ) {
	return Navigation\get_nav_page_link( $page, $ancestors, $sample );
}

/**
 * Retrieve the permalink for a post with a custom post type.
 *
 * Intended as an efficient alternative to `get_post_permalink()`.
 * Allows you to provide an array of post ancestors for use calculating post name path.
 *
 * @see `get_post_permalink()`
 *
 * This is a stub for the new namespaced function, but there's no reason to think
 * any other themes or components are calling this. Likely it should be removed.
 *
 * @param  object  $post       Post object to calculate permalink for.
 * @param  array   $ancestors  Optional. An array of post objects keyed on post ID. Should contain all ancestors of $post.
 * @param  boolean $sample     Optional. Is it a sample permalink.
 * @return string              Post permalink.
 */
function bu_navigation_get_post_link( $post, $ancestors = array(), $sample = false ) {
	return Navigation\get_nav_post_link( $post, $ancestors, $sample );
}

/**
 * Calculate the post path for a post.
 *
 * Loops backwards from $page through $ancestors to determine full post path.
 * If any ancestor is not present in $ancestors it will attempt to load them on demand.
 * Utilizes static caching to minimize repeat queries across calls.
 *
 * This is a stub for the new namespaced function, but there's no reason to think
 * any other themes or components are calling this. Likely it should be removed.
 *
 * @param  object $page      Post object to query path for. Must contain ID, post_name and post_parent fields.
 * @param  array  $ancestors An array of post objects keyed on post ID.  Should contain ancestors of $page,
 *                           with ID, post_name and post_parent fields for each.
 * @return string            Page path.
 */
function bu_navigation_get_page_uri( $page, $ancestors ) {
	return Navigation\get_page_uri( $page, $ancestors );
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
 * Get a list of post types for inclusion in a database select query
 *
 * Given the initial post_types parameter, this checks to see if the link type should be included,
 * also checking the global plugin settings.
 *
 * @since 1.2.24
 *
 * @global object $bu_navigation_plugin
 * @param mixed   $post_types String or array representing all of the post types to be retrieved with the query.
 * @param boolean $include_links Whether or not to include the 'links' post type in the list.
 * @return array Array of post types to include in database query.
 */
function bu_navigation_post_types_to_select( $post_types, $include_links ) {
	global $bu_navigation_plugin;

	if ( is_string( $post_types ) ) {
		$post_types = explode( ',', $post_types );
	}

	$post_types = (array) $post_types;
	$post_types = array_map( 'trim', $post_types );

	// If include_links is set in the args, add the link type to the post types array (if it's not there already).
	if ( $include_links
		&& ! in_array( BU_NAVIGATION_LINK_POST_TYPE, $post_types, true )
		&& in_array( 'page', $post_types, true ) // Not clear why links are only added if pages are there.
		&& count( $post_types ) === 1 // Not clear why links are only added if there's only one other existing post type.
	) {
		$post_types[] = BU_NAVIGATION_LINK_POST_TYPE;
	}

	// Check the plugin level 'supports' function to see if 'link' type support has been removed.
	if ( is_object( $bu_navigation_plugin ) && ! $bu_navigation_plugin->supports( 'links' ) ) {
		// If so, filter out the link type if it is there.
		$post_types = array_filter( $post_types, function( $post_type ) {
			return BU_NAVIGATION_LINK_POST_TYPE !== $post_type;
		} );
	}

	return $post_types;
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
 * @param object $page Page object.
 * @param mixed  $args Wordpress-style arguments (string or array).
 * @return string HTML fragment containing list item
 */
function bu_navigation_format_page( $page, $args = '' ) {
	$defaults = array(
		'item_tag'     => 'li',
		'item_id'      => null,
		'html'         => '',
		'depth'        => null,
		'position'     => null,
		'siblings'     => null,
		'anchor_class' => '',
		'anchor'       => true,
		'title_before' => '',
		'title_after'  => '',
		'section_ids'  => null,
	);
	$r        = wp_parse_args( $args, $defaults );

	if ( ! isset( $page->navigation_label ) ) {
		$page->navigation_label = apply_filters( 'the_title', $page->post_title, $page->ID );
	}

	$title        = $page->navigation_label;
	$href         = $page->url;
	$anchor_class = $r['anchor_class'];

	if ( is_numeric( $r['depth'] ) ) {
		$anchor_class .= sprintf( ' level_%d', intval( $r['depth'] ) );
	}

	$attrs = array(
		'class' => trim( $anchor_class ),
	);

	if ( isset( $page->url ) && ! empty( $page->url ) ) {
		$attrs['href'] = esc_url( $page->url );
	}

	if ( isset( $page->target ) && $page->target == 'new' ) {
		$attrs['target'] = '_blank';
	}

	$attrs = apply_filters( 'bu_navigation_filter_anchor_attrs', $attrs, $page );

	$attributes = '';

	if ( is_array( $attrs ) && count( $attrs ) > 0 ) {
		foreach ( $attrs as $attr => $val ) {
			if ( $val ) {
				$attributes .= sprintf( ' %s="%s"', $attr, $val );
			}
		}
	}

	$item_classes = array( 'page_item', 'page-item-' . $page->ID );

	if ( is_array( $r['section_ids'] ) && in_array( $page->ID, $r['section_ids'] ) ) {
		array_push( $item_classes, 'has_children' );
	}

	if ( is_numeric( $r['position'] ) && is_numeric( $r['siblings'] ) ) {
		if ( $r['position'] == 1 ) {
			array_push( $item_classes, 'first_item' );
		}
		if ( $r['position'] == $r['siblings'] ) {
			array_push( $item_classes, 'last_item' );
		}
	}

	$item_classes = apply_filters( 'bu_navigation_filter_item_attrs', $item_classes, $page );
	$item_classes = apply_filters( 'page_css_class', $item_classes, $page );

	$title = apply_filters( 'bu_page_title', $title );
	$label = apply_filters( 'bu_navigation_format_page_label', $title, $page );

	$label  = $r['title_before'] . $label . $r['title_after'];
	$anchor = $r['anchor'] ? sprintf( '<a%s>%s</a>', $attributes, $label ) : $label;

	$html = sprintf(
		"<%s class=\"%s\">\n%s\n %s</%s>\n",
		$r['item_tag'],
		implode( ' ', $item_classes ),
		$anchor,
		$r['html'],
		$r['item_tag']
	);

	if ( $r['item_id'] ) {
		$html = sprintf(
			"<%s id=\"%s\" class=\"%s\">\n%s\n %s</%s>\n",
			$r['item_tag'],
			$r['item_id'],
			implode( ' ', $item_classes ),
			$anchor,
			$r['html'],
			$r['item_tag']
		);
	}

	$args               = $r;
	$args['attributes'] = $attrs;

	$html = apply_filters( 'bu_navigation_filter_item_html', $html, $page, $args );

	return $html;
}

/**
 * Filter to apply "active" class to a navigation item container if it is the current page
 *
 * @todo relocate to a default filters file
 *
 * @param $attributes array Associative array of anchor attributes
 * @param $page object Page object
 * @return array Array of classes
 */
function bu_navigation_filter_item_attrs( $classes, $page ) {
	global $wp_query;

	if ( is_singular() || $wp_query->is_posts_page ) {
		$current_page = $wp_query->get_queried_object();

		if ( $current_page->ID == $page->ID ) {
			array_push( $classes, 'current_page_item' );
		}

		if ( isset( $page->active_section ) && $page->active_section ) {
			array_push( $classes, 'current_page_ancestor' );
		}

		if ( $page->ID == $current_page->post_parent ) {
			array_push( $classes, 'current_page_parent' );
		}
	}

	return $classes;
}

add_filter( 'bu_navigation_filter_item_attrs', 'bu_navigation_filter_item_attrs', 10, 2 );

/**
 * Filter to apply "active" class to a navigation item if it is the current page
 *
 * @todo relocate to a default filters file
 *
 * @param array  $attributes Associative array of anchor attributes.
 * @param object $page Page object.
 */
function bu_navigation_filter_item_active_page( $attributes, $page ) {
	global $wp_query;

	if ( is_singular() || $wp_query->is_posts_page ) {
		$current_page = $wp_query->get_queried_object();

		if ( $current_page->ID == $page->ID ) {
			$attributes['class'] .= ' active';
		}

		if ( isset( $page->active_section ) && $page->active_section ) {
			$attributes['class'] .= ' active_section';
		}
	}

	return $attributes;
}

add_filter( 'bu_navigation_filter_anchor_attrs', 'bu_navigation_filter_item_active_page', 10, 2 );

// Add default filters from "the_title" when displaying navigation label
add_filter( 'bu_navigation_format_page_label', 'wptexturize' );
add_filter( 'bu_navigation_format_page_label', 'convert_chars' );
add_filter( 'bu_navigation_format_page_label', 'trim' );

/**
 * Generates an unordered list tree of pages in a particular section
 *
 * @param int   $parent_id ID of section (page parent).
 * @param array $pages_by_parent An array of pages indexed by their parent page (see bu_navigation_pages_by_parent).
 * @param mixed $args Array or string of WP-style arguments.
 * @return string HTML fragment containing unordered list
 */
function bu_navigation_list_section( $parent_id, $pages_by_parent, $args = '' ) {
	$defaults = array(
		'depth'         => 1,
		'container_tag' => 'ul',
		'item_tag'      => 'li',
		'section_ids'   => null,
	);

	$parsed_args = wp_parse_args( $args, $defaults );

	if ( ! array_key_exists( $parent_id, $pages_by_parent ) ) {
		return '';
	}

	$html     = '';
	$children = $pages_by_parent[ $parent_id ];

	if ( ! is_array( $children ) || ! ( count( $children ) > 0 ) ) {
		return '';
	}

	$html .= sprintf( "\n<%s>\n", $parsed_args['container_tag'] );

	foreach ( $children as $page ) {
		$sargs = $parsed_args;
		$sargs['depth']++;

		$child_html = bu_navigation_list_section( $page->ID, $pages_by_parent, $sargs );
		$html      .= bu_navigation_format_page(
			$page, array(
				'html'        => $child_html,
				'depth'       => $parsed_args['depth'],
				'item_tag'    => $parsed_args['item_tag'],
				'section_ids' => $parsed_args['section_ids'],
			)
		);
	}

	$html .= sprintf( "\n</%s>\n", $parsed_args['container_tag'] );

	return $html;
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
				$child_html = bu_navigation_list_section( $page->ID, $pages_by_parent, $sargs );
			}

			// Display formatted page (optionally with post name as ID).
			if ( $r['identify_top'] ) {
				$html .= bu_navigation_format_page(
					$page, array(
						'html'     => $child_html,
						'depth'    => 1,
						'item_tag' => $r['item_tag'],
						'item_id'  => $page->post_name,
					)
				);
			} else {
				$html .= bu_navigation_format_page(
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
