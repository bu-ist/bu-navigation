<?php
/**
 * Data model methods
 *
 * @package BU_Navigation
 */

namespace BU\Plugins\Navigation;

// Defines an upper bound for results from direct SQL queries.
define( 'GROUP_CONCAT_MAX_LEN', 20480 );

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
 * This function and one other (get_posts) are the only methods that directly query the database.
 *
 * @global object $wpdb
 * @param mixed $post_types Optional, can be an array of post types, or a string containing post type names.
 * @param bool  $include_links Whether or not to include links (with pages only).
 * @return array (sections => array(parent1_id => [child1_id, ...], ...), pages => array( child1_id => parent1_id, ... )
 */
function load_sections( $post_types = array( 'page' ), $include_links = true ) {
	global $wpdb, $bu_navigation_plugin;

	// Convert string style args to an array.
	if ( is_string( $post_types ) ) {
		$post_types = explode( ',', $post_types );
	}

	// There should not be any scenarios where $post_types isn't already an array, so this clause looks extraneous.
	// Leaving it here for compatibility with previous behavior, but should be evaluated for removal in future releases.
	if ( ! is_array( $post_types ) ) {
		$post_types = (array) $post_types;
	}

	// Handle links.
	if ( $include_links
		&& ! in_array( BU_NAVIGATION_LINK_POST_TYPE, $post_types, true )
		&& in_array( 'page', $post_types, true )
		&& ( 1 === count( $post_types ) )
	) {
		// Stepping through this, I'm not sure why links would only be added if it is pages being listed.
		// Also, I'm not sure why links should be skipped if there's more than one type already.
		// It may be that removing that conditional clause will help simplify the nested conditional here.
		$post_types[] = BU_NAVIGATION_LINK_POST_TYPE;
	}

	// This clause removes links if the plugin support for links has been removed elsewhere.
	// It is not clear from the supports() function how often this is being done.
	if ( is_object( $bu_navigation_plugin ) && ! $bu_navigation_plugin->supports( 'links' ) ) {
		$index = array_search( BU_NAVIGATION_LINK_POST_TYPE, $post_types, true );
		if ( false !== $index ) {
			unset( $post_types[ $index ] );
		}
	}

	// Render the post_types array to a string that can be injected in the the SQL IN clause.
	$in_post_types = implode( "','", $post_types );

	// Try the cache first
	// Cache is timestamped for maximum freshness (see `get_pages`)
	// The `last_changed` key is updated by core in `clean_post_cache`.
	$last_changed = wp_cache_get( 'last_changed', 'posts' );
	if ( ! $last_changed ) {
		// The cache timing here appears designed to make the cache last long enough for a single request.
		// Subsequent requests seem to reliably trigger a new query.  The timing seems at least inspired by WP core get_pages() caching.
		$last_changed = microtime();
		wp_cache_set( 'last_changed', $last_changed, 'posts' );
	}

	$cache_key = 'all_sections:' . md5( serialize( $post_types ) . ":$last_changed" );
	if ( $all_sections = wp_cache_get( $cache_key, 'bu-navigation' ) ) {
		return $all_sections;
	}

	$wpdb->query( 'SET SESSION group_concat_max_len = ' . GROUP_CONCAT_MAX_LEN ); // db call ok; no-cache ok.
	$query = sprintf(
		"SELECT DISTINCT(post_parent) AS section, GROUP_CONCAT(ID) AS children
		  FROM %s
		 WHERE post_type IN ('$in_post_types')
		 GROUP BY post_parent
		 ORDER BY post_parent ASC",
		$wpdb->posts
	);
	$rows  = $wpdb->get_results( $query ); // db call ok; no-cache ok.

	$all_sections = transform_rows( $rows );

	// Cache results.
	wp_cache_set( $cache_key, $all_sections, 'bu-navigation' );

	return $all_sections;
}


/**
 * Returns an array of page objects indexed by page ID
 *
 * This function and one other (load_sections) are the only actual data loading methods.
 *
 * Renamed from the original 'bu_navigation_get_posts'.
 * Name is 'get_nav_posts' to avoid any potential confusion with the global WP method 'get_posts'.
 *
 * @param mixed $args Wordpress-style arguments (string or array).
 * @return array Array of pages keyed on page ID or FALSE on problem
 */
function get_nav_posts( $args = '' ) {
	global $wpdb;

	$defaults    = array(
		'post_types'            => array( 'page' ),
		'post_status'           => array( 'publish' ),
		'sections'              => null,
		'post__in'              => null,
		'max_items'             => '',
		'include_links'         => true,
		'suppress_filter_posts' => false,
		'suppress_urls'         => false,
	);
	$parsed_args = wp_parse_args( $args, $defaults );

	// Post fields to return.
	$fields = array(
		'ID',
		'post_date',
		'post_title',
		'post_excerpt',
		'post_name',
		'post_parent',
		'guid',
		'menu_order',
		'post_type',
		'post_status',
		'post_password',
	);
	$fields = apply_filters( 'bu_navigation_filter_fields', $fields );
	$fields = implode( ',', $fields );

	$where = get_nav_posts_where_clause(
		$parsed_args['post_types'],
		$parsed_args['include_links'],
		$parsed_args['post_status'],
		$parsed_args['sections'],
		$parsed_args['post__in']
	);

	// Result sorting clause.
	$orderby = 'ORDER BY post_parent ASC, menu_order ASC';

	// Execute query, fetch results as objects in an array keyed on posts.ID.
	$posts = $wpdb->get_results(
		"SELECT $fields FROM $wpdb->posts WHERE 1=1 $where $orderby",
		OBJECT_K
	); // db call ok; no-cache ok.

	if ( ! is_array( $posts ) || ( count( $posts ) === 0 ) ) {
		return false;
	}

	// Add url property to each post object ($post->url = permalink).
	if ( ! $parsed_args['suppress_urls'] ) {
		$posts = get_urls( $posts );
	}

	// Allow custom filtering of posts retrieved using this function.
	if ( ! $parsed_args['suppress_filter_posts'] ) {
		$posts = apply_filters( 'bu_navigation_filter_posts', $posts );
		$posts = apply_filters( 'bu_navigation_filter_pages', $posts );
	}

	// Chop off anything great than max_items, if set.
	if ( $parsed_args['max_items'] ) {
		$posts = array_slice( $posts, 0, $parsed_args['max_items'], true );
	}

	return $posts;

}

/**
 * Assembles a SQL where clause based on query parameters
 *
 * Used by get_posts() to assemble the custom query.
 *
 * @since 1.2.24
 *
 * @param mixed   $post_types String or array representing all of the post types to be retrieved with the query.
 * @param boolean $include_links Whether or not to include the 'links' post type in the list.
 * @param mixed   $post_status String or array representing all of the allowed post statuses.
 * @param array   $sections Array of page ids (not like the other uses of 'section', this deserves renaming).
 * @param array   $post__in Array of post_ids to include in the query.
 * @return string A SQL 'where' clause limiting the query results according to the filtering parameters.
 */
function get_nav_posts_where_clause( $post_types, $include_links, $post_status, $sections, $post__in ) {
	$where = '';

	// If the requests post types is 'any', then don't restrict the post type with a where clause.
	if ( 'any' !== $post_types ) {
		// Otherwise append post types where clause to the SQL query.
		$post_types_list = post_types_to_select( $post_types, $include_links );
		$post_types_list = implode( "','", $post_types_list );
		$where          .= " AND post_type IN ('$post_types_list')";
	}

	// Append post statuses.
	if ( 'any' !== $post_status ) {
		// Explode strings to arrays, and coerce anything else to an array.  Probably overkill, but matches previous behavior.
		$post_status = ( is_string( $post_status ) ) ? explode( ',', $post_status ) : (array) $post_status;

		$post_status = implode( "','", array_map( 'trim', $post_status ) );
		$where      .= " AND post_status IN ('$post_status')";
	}

	// Limit result set to posts in specific sections.
	if ( is_array( $sections ) && ( count( $sections ) > 0 ) ) {
		$sections = array_map( 'absint', $sections );
		$sections = implode( ',', array_unique( $sections ) );
		$where   .= " AND post_parent IN ($sections)";
	}

	// Validate posts__in parameter such that it is an array, coerce the values to absolute integers, and enforce uniqueness.
	$parsed_post__in = is_array( $post__in ) ? array_unique( array_map( 'absint', $post__in ) ) : array();
	$post__in_list   = implode( ',', $parsed_post__in );

	// Limit to specific posts, if present.
	$where .= ! empty( $post__in_list ) ? " AND ID IN($post__in_list)" : '';

	return $where;
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
function post_types_to_select( $post_types, $include_links ) {
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
		$post_types = array_filter(
			$post_types,
			function( $post_type ) {
				return BU_NAVIGATION_LINK_POST_TYPE !== $post_type;
			}
		);
	}

	return $post_types;
}

/**
 * Legacy alias for bu_navigation_get_posts
 *
 * Translates legacy arguments that have been updated for consistency with WP_Query
 *
 * @param mixed $args  Wordpress-style arguments (string or array).
 * @return array Array of pages keyed on page ID or FALSE on problem
 */
function get_nav_pages( $args = '' ) {
	$defaults = array(
		'pages'                 => null,
		'suppress_filter_pages' => false,
	);
	$new_args = wp_parse_args( $args, $defaults );

	// Legacy arg translation.
	if ( ! is_null( $new_args['pages'] ) ) {
		$new_args['post__in'] = $new_args['pages'];
		unset( $new_args['pages'] );
	}

	$new_args['suppress_filter_posts'] = $new_args['suppress_filter_pages'];
	unset( $new_args['suppress_filter_pages'] );

	return get_nav_posts( $new_args );
}

/**
 * Alternative to WordPress' wp_list_pages function
 *
 * Inside the plugin, only the widget uses this function.
 * Externally it is also used by the r-editorial theme and associated child themes.
 *
 * @todo refactor to decouple widget-specific logic
 *
 * @see BU\Plugins\Navigation\load_sections()
 * @see BU\Plugins\Navigation\gather_sections()
 * @see BU\Plugins\Navigation\pages_by_parent()
 *
 * @param mixed $args Array or string of WP-style arguments.
 * @return string HTML fragment containing navigation list
 */
function list_pages( $args = '' ) {
	$defaults    = array(
		'page_id'             => null,
		'sections'            => null,
		'post_types'          => array( 'page' ),
		'include_links'       => true,
		'echo'                => 0,
		'title_li'            => '',
		'navigate_in_section' => '',
		'container_tag'       => 'ul',
		'container_id'        => '',
		'container_class'     => '',
		'item_tag'            => 'li',
		'title_before'        => '',
		'title_after'         => '',
		'style'               => null,
		'widget'              => false,
	);
	$parsed_args = wp_parse_args( $args, $defaults );

	$section_ids = array();

	// Get ancestors if a specific post is being listed.
	if ( $parsed_args['page_id'] ) {
		$all_sections = load_sections( $parsed_args['post_types'], $parsed_args['include_links'] );
		$section_ids  = array_keys( $all_sections['sections'] );
		$section_args = array(
			'post_types'    => $parsed_args['post_types'],
			'include_links' => $parsed_args['include_links'],
		);

		$parsed_args['sections'] = gather_sections( $parsed_args['page_id'], $section_args, $all_sections );

	}

	// Fetch post list, possibly limited to specific sections.
	$page_args       = array(
		'sections'      => $parsed_args['sections'],
		'post_types'    => $parsed_args['post_types'],
		'include_links' => $parsed_args['include_links'],
	);
	$pages           = get_nav_pages( $page_args );
	$pages_by_parent = pages_by_parent( $pages );

	if ( $parsed_args['widget'] && 'adaptive' === $parsed_args['style'] ) {
		$pages_by_parent = adaptive_pages_filter( $pages_by_parent );
	}

	$sections = ! empty( $parsed_args['sections'] ) ? $parsed_args['sections'] : array_keys( $pages_by_parent );

	if ( 'adaptive' === $parsed_args['style'] ) {
		$sections = adaptive_section_slice( $parsed_args['page_id'], $pages_by_parent, $sections );
	}

	// Default to top level pages.
	$section = $sections[0];

	// Handle sectional navigation style.
	if ( $parsed_args['navigate_in_section'] ) {
		// Sectional navigation requires at least two levels, return null otherwise.
		$section = ( isset( $sections[1] ) ) ? $sections[1] : null;
	}

	// Check that $pages_by_parent[ $section ] has elements, if not return an empty string.
	if ( ! isset( $pages_by_parent[ $section ] ) || ! is_array( $pages_by_parent[ $section ] ) || ( count( $pages_by_parent[ $section ] ) < 1 ) ) {
		return '';
	}

	$list_attributes = '';

	if ( $parsed_args['container_id'] ) {
		$list_attributes .= sprintf( ' id="%s"', $parsed_args['container_id'] );
	}
	if ( $parsed_args['container_class'] ) {
		$list_attributes .= sprintf( ' class="%s"', $parsed_args['container_class'] );
	}

	$html = sprintf( "<%s %s>\n", $parsed_args['container_tag'], $list_attributes );

	// Loop over top section.
	$sargs = array(
		'container_tag' => $parsed_args['container_tag'],
		'item_tag'      => $parsed_args['item_tag'],
		'depth'         => 2,
		'section_ids'   => $section_ids,
	);

	$page_position   = 1;
	$number_siblings = count( $pages_by_parent[ $section ] );

	foreach ( $pages_by_parent[ $section ] as $page ) {

		$child_html = list_section( $page->ID, $pages_by_parent, $sargs );

		$pargs = array(
			'html'        => $child_html,
			'depth'       => 1,
			'position'    => $page_position,
			'siblings'    => $number_siblings,
			'item_tag'    => $parsed_args['item_tag'],
			'section_ids' => $section_ids,
		);

		$html .= format_page( $page, $pargs );

		$page_position++;
	}

	$html .= sprintf( "</%s>\n", $parsed_args['container_tag'] );

	if ( $parsed_args['echo'] ) {
		echo $html;
	}

	return $html;
}
