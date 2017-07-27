<?php
/**
 * Generic navigation function for WordPress 2.8+
 * Niall Kavanagh
 * ntk@bu.edu
 */

if ( defined( 'BU_NAVIGATION_LIB_LOADED' ) && BU_NAVIGATION_LIB_LOADED )
	return;

define( 'BU_NAVIGATION_LIB_LOADED', TRUE );

define( 'GROUP_CONCAT_MAX_LEN', 20480 );

/**
 * Gets the supported post_types by the bu-navigation plugin.
 *
 * @param boolean $include_link true|false link post_type is something special, so we don't always need it
 * @return array of post_type strings
 */
function bu_navigation_supported_post_types( $include_link = false, $output = 'names' ) {
	global $bu_navigation_plugin;

 	return $bu_navigation_plugin->supported_post_types( $include_link, $output );

}

/**
 * Returns all the sections with children and all the pages with parents (so both ways)
 *
 * @global type $wpdb
 * @param array $post_types focus on a specific post_type
 * @param bool $include_links whether or not to include links (with pages only)
 * @return array (sections => array(sectionid1 => [pageid1, ...], ...), pages => array( pageid1 => sectionid1, ... )
 */
function bu_navigation_load_sections( $post_types = array(), $include_links = true ) {
	global $wpdb, $bu_navigation_plugin;

	// Setup target post type(s)
	if ( empty( $post_types ) ) {
		$post_types = array( 'page' );
	} else if ( is_string( $post_types ) ) {
		$post_types = explode( ',', $post_types );
	} else {
		$post_types = (array) $post_types;
	}

	// Handle links
	if ( $include_links && ! in_array( BU_NAVIGATION_LINK_POST_TYPE, $post_types ) ) {
		if ( in_array( 'page', $post_types ) && ( count( $post_types ) == 1 ) )
			$post_types[] = BU_NAVIGATION_LINK_POST_TYPE;
	}
	if( is_object( $bu_navigation_plugin ) && ! $bu_navigation_plugin->supports( 'links' ) ) {
		$index = array_search( BU_NAVIGATION_LINK_POST_TYPE, $post_types );
		if ( $index !== false ) {
			unset( $post_types[ $index ] );
		}
	}
	$in_post_types = implode( "','", $post_types );

	// Try the cache first

	// Cache is timestamped for maximum freshness (see `get_pages`)
	// The `last_changed` key is updated by core in `clean_post_cache`
	$last_changed = wp_cache_get( 'last_changed', 'posts' );
	if ( ! $last_changed ) {
		$last_changed = microtime();
		wp_cache_set( 'last_changed', $last_changed, 'posts' );
	}

	$cache_key = 'all_sections:' . md5( serialize( $post_types ) . ":$last_changed" );
	if ( $all_sections = wp_cache_get( $cache_key, 'bu-navigation' ) ) {
		return $all_sections;
	}

	$wpdb->query('SET SESSION group_concat_max_len = ' . GROUP_CONCAT_MAX_LEN);
	$query = sprintf("
		SELECT DISTINCT(post_parent) AS section, GROUP_CONCAT(ID) AS children
		  FROM %s
		 WHERE post_type IN ('$in_post_types')
		 GROUP BY post_parent
		 ORDER BY post_parent ASC", $wpdb->posts);
	$rows = $wpdb->get_results($query);

	$sections = array();
	$pages = array();

	if ( is_array( $rows ) && ( count( $rows ) > 0 ) ) {
		foreach ( $rows as $row ) {
			$sections[$row->section] = explode(',', $row->children);

			if ( is_array( $sections[$row->section] ) && ( count( $sections[ $row->section ] ) > 0 ) ) {
				foreach ( $sections[$row->section] as $child ) {
					$pages[$child] = $row->section;
				}
			}
		}
	}

	// Cache results
	$all_sections = array( 'sections' => $sections, 'pages' => $pages );
	wp_cache_set( $cache_key, $all_sections, 'bu-navigation' );

	return $all_sections;
}

/**
 * @todo needs docblock
 */
function bu_navigation_gather_sections( $page_id, $args = '', $all_sections = NULL ) {
	$defaults = array(
		'direction' => 'up',
		'depth' => 0,
		'post_types' => array( 'page' ),
		'include_links' => true
		);
	$r = wp_parse_args($args, $defaults);

	if ( is_null( $all_sections ) )
		$all_sections = bu_navigation_load_sections( $r['post_types'], $r['include_links'] );

	$pages = $all_sections['pages'];
	$sections = array();

	// Include the current page as a section if it has any children
	if ( array_key_exists( $page_id, $all_sections['sections'] ) )
		array_push( $sections, $page_id );

	// Gather descendants or ancestors depending on direction
	if ($r['direction'] == 'down') {

		$child_sections = bu_navigation_gather_childsections( $page_id, $all_sections['sections'], $r['depth'] );

		if ( count( $child_sections ) > 0 )
			$sections = array_merge( $sections, $child_sections );

	} else {

		if ( array_key_exists( $page_id, $pages ) ) {

			$current_section = $pages[$page_id];
			array_push( $sections, $current_section );

			while ( $current_section != 0 ) {
				if ( array_key_exists( $current_section, $pages ) ) {
					$current_section = $pages[$current_section];
					array_push($sections, $current_section);
				} else {
					break;
				}
			}
		}
	}

	$sections = array_reverse( $sections );

	return $sections;
}

/**
 * @todo needs docblock
 */
function bu_navigation_gather_childsections($parent_id, $sections, $max_depth = 0, $current_depth = 1)
{
	$child_sections = array();

	if ((array_key_exists($parent_id, $sections)) && (count($sections[$parent_id]) > 0))
	{
		foreach ($sections[$parent_id] as $child_id)
		{
			if ((array_key_exists($child_id, $sections)) && (count($sections[$child_id]) > 0))
			{
				array_push($child_sections, $child_id);

				if (($max_depth == 0) || ($current_depth < $max_depth))
				{
					$child_sections = array_merge($child_sections, bu_navigation_gather_childsections($child_id, $sections, $max_depth, ($current_depth + 1)));
				}
			}
		}
	}

	return $child_sections;
}

/**
 * @todo needs docblock
 */
function bu_navigation_get_page_depth($page_id, $all_sections = NULL)
{
	$ancestry = bu_navigation_gather_sections($page_id, NULL, $all_sections);

	$depth = count($ancestry);

	if (!in_array($page_id, $ancestry)) $depth++;

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
 * @param  array $pages An array of post objects keyed on post ID. Works with all post types.
 * @return array $pages The input array with $post->url set to the permalink for each post.
 */
function bu_navigation_get_urls( $pages ) {
	if ( ( is_array( $pages ) ) && ( count( $pages ) > 0 ) ) {
		foreach ( $pages as $page ) {
			$url = '';
			if ( 'page' === $page->post_type ) {
				$url = bu_navigation_get_page_link( $page, $pages );
			} else if ( BU_NAVIGATION_LINK_POST_TYPE === $page->post_type ) {
				$url = $page->post_content;
			} else {
				$url = bu_navigation_get_post_link( $page, $pages );
			}
			$page->url = $url;
		}
	}
	return $pages;
}

/**
 * Retrieve the page permalink.
 *
 * Intended as an efficient alternative to `get_page_link()` / `_get_page_link()`.
 * Allows you to provide an array of post ancestors for use calculating post name path.
 *
 * @see `_get_page_link()`
 *
 * @param  object  $page       Post object to calculate permalink for.
 * @param  array   $ancestors  Optional. An array of post objects keyed on post ID. Should contain all ancestors of $page.
 * @param  boolean $sample     Optional. Is it a sample permalink.
 * @return string              Post permalink.
 */
function bu_navigation_get_page_link( $page, $ancestors = array(), $sample = false ) {
	global $wp_rewrite;

	$page_link = $wp_rewrite->get_page_permastruct();
	$draft_or_pending = true;
	if ( isset( $page->post_status ) ) {
		$draft_or_pending = in_array( $page->post_status, array( 'draft', 'pending', 'auto-draft' ) );
	}
	$use_permastruct = ( ! empty( $page_link ) && ( ! $draft_or_pending || $sample ) );

	if ( 'page' == get_option( 'show_on_front' ) && $page->ID == get_option( 'page_on_front' ) ) {
		$page_link = home_url( '/' );
	} else if ( $use_permastruct ) {
		$slug = bu_navigation_get_page_uri( $page, $ancestors );
		$page_link = str_replace( '%pagename%', $slug, $page_link );
		$page_link = home_url( user_trailingslashit( $page_link, 'page' ) );
	} else {
		$page_link = home_url( "?page_id=" . $page->ID );
	}

	return $page_link;
}

/**
 * Retrieve the permalink for a post with a custom post type.
 *
 * Intended as an efficient alternative to `get_post_permalink()`.
 * Allows you to provide an array of post ancestors for use calculating post name path.
 *
 * @see `get_post_permalink()`
 *
 * @param  object  $post       Post object to calculate permalink for.
 * @param  array   $ancestors  Optional. An array of post objects keyed on post ID. Should contain all ancestors of $post.
 * @param  boolean $sample     Optional. Is it a sample permalink.
 * @return string              Post permalink.
 */
function bu_navigation_get_post_link( $post, $ancestors = array(), $sample = false ) {
	global $wp_rewrite;

	$post_link = $wp_rewrite->get_extra_permastruct( $post->post_type );
	$draft_or_pending = true;
	if ( isset( $post->post_status ) ) {
		$draft_or_pending = in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft' ) );
	}
	$use_permastruct = ( ! empty( $post_link ) && ( ! $draft_or_pending || $sample ) );
	$post_type = get_post_type_object( $post->post_type );
	$slug = $post->post_name;

	if ( $post_type->hierarchical ) {
		$slug = bu_navigation_get_page_uri( $post, $ancestors );
	}

	if ( $use_permastruct ) {
		$post_link = str_replace( "%$post->post_type%", $slug, $post_link );
		$post_link = home_url( user_trailingslashit( $post_link ) );
	} else {
		if ( $post_type->query_var && ! $draft_or_pending ) {
			$post_link = add_query_arg( $post_type->query_var, $slug, '' );
		} else {
			$post_link = add_query_arg( array( 'post_type' => $post->post_type, 'p' => $post->ID ), '' );
		}
		$post_link = home_url( $post_link );
	}

	return $post_link;
}

/**
 * Calculate the post path for a post.
 *
 * Loops backwards from $page through $ancestors to determine full post path.
 * If any ancestor is not present in $ancestors it will attempt to load them on demand.
 * Utilizes static caching to minimize repeat queries across calls.
 *
 * @param  object $page      Post object to query path for. Must contain ID, post_name and post_parent fields.
 * @param  array  $ancestors An array of post objects keyed on post ID.  Should contain ancestors of $page,
 *                           with ID, post_name and post_parent fields for each.
 * @return string            Page path.
 */
function bu_navigation_get_page_uri( $page, $ancestors ) {

	// Used to cache pages we load that aren't contained in $ancestors.
	static $extra_pages = array();
	static $missing_pages = array();

	$uri = $page->post_name;

	while ( isset( $page->post_parent ) && $page->post_parent != 0 ) {

		// Avoid infinite loops
		if ( $page->post_parent == $page->ID ) {
			break;
		}

		// Attempt to load missing ancestors.
		if ( ! array_key_exists( $page->post_parent, $ancestors ) ) {
			if ( ! array_key_exists( $page->post_parent, $extra_pages ) && ! in_array( $page->post_parent, $missing_pages ) ) {
				$missing_ancestors = _bu_navigation_page_uri_ancestors( $page );
				// Cache any ancestors we load here or can't find in separate data structures.
				if ( ! empty( $missing_ancestors ) ) {
					$extra_pages = $extra_pages + $missing_ancestors;
				} else {
					// Add to our tracking list of pages we've already looked for.
					$missing_pages[] = $page->post_parent;
				}
			}

			// Merge passed in ancestors with extras we've loaded along the way.
			$ancestors = $ancestors + $extra_pages;
		}

		// We can't return an incomplete path -- bail with indication of failure.
		if ( ! array_key_exists( $page->post_parent, $ancestors ) ) {
			break;
		}

		// Append parent post name and keep looping backwards.
		$parent = $ancestors[ $page->post_parent ];
		if ( is_object( $parent ) && ! empty( $parent->post_name ) ) {
			$uri = $parent->post_name . '/' . $uri;
		}

		$page = $parent;
	}

	return $uri;
}

function _bu_navigation_page_uri_ancestors( $post ) {

	$ancestors = array();
	$all_sections = bu_navigation_load_sections( $post->post_type );

	// Load ancestors post IDs
	$section_ids = bu_navigation_gather_sections( $post->ID, array( 'post_types' => $post->post_type ), $all_sections );
	$section_ids = array_filter( $section_ids );

	// Fetch ancestor posts, with only the columns we need to determine permalinks
	if ( ! empty( $section_ids ) ) {
		$args =  array(
			'post__in' => $section_ids,
			'post_types' => 'any',
			'post_status' => 'any',
			'suppress_urls' => true,
			'suppress_filter_posts' => true
			);

		// Only need a few fields to determine the correct URL.
		add_filter( 'bu_navigation_filter_fields', '_bu_navigation_page_uri_ancestors_fields', 9999 );
		$ancestors = bu_navigation_get_posts( $args );
		remove_filter( 'bu_navigation_filter_fields', '_bu_navigation_page_uri_ancestors_fields', 9999 );

		if ( false === $ancestors ) {
			$ancestors = array();
		}
	}

	return $ancestors;
}

function _bu_navigation_page_uri_ancestors_fields( $fields ) {
	return array( 'ID', 'post_name', 'post_parent' );
}

/**
* Returns an array of page objects indexed by page ID
*
* TODO: Function incomplete; most arguments ignored. Sort order should allow +1 column
* @param $args mixed Wordpress-style arguments (string or array)
* @return array Array of pages keyed on page ID or FALSE on problem
*/
function bu_navigation_get_posts( $args = '' ) {
	global $wpdb, $bu_navigation_plugin;

	$defaults = array(
		'post_types' => array( 'page' ),
		'post_status' => array( 'publish' ),
		'sections' => null,
		'post__in' => null,
		'max_items' => '',
		'include_links' => true,
		'suppress_filter_posts' => false,
		'suppress_urls' => false,
		);
	$r = wp_parse_args( $args, $defaults );

	// Start building the query
	$where = $orderby = '';

	// Post fields to return
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
		'post_password'
		);
	$fields = apply_filters( 'bu_navigation_filter_fields', $fields );
	$fields = implode( ",", $fields );

	// Append post types
	$post_types = $r['post_types'];
	if ( 'any' != $post_types ) {
		if ( is_string( $post_types ) )
			$post_types = explode( ',', $post_types );

		$post_types = (array) $post_types;
		$post_types = array_map( 'trim', $post_types );

		// Include links?
		if ( $r['include_links'] && ! in_array( BU_NAVIGATION_LINK_POST_TYPE, $post_types ) ) {
			if ( in_array( 'page', $post_types ) && ( count( $post_types ) == 1 ) )
				$post_types[] = BU_NAVIGATION_LINK_POST_TYPE;
		}
		if ( is_object( $bu_navigation_plugin ) && ! $bu_navigation_plugin->supports( 'links' ) ) {
			$index = array_search( BU_NAVIGATION_LINK_POST_TYPE, $post_types );
			if ( $index !== false ) {
				unset( $post_types[ $index ] );
			}
		}

		$post_types = implode( "','", $post_types );
		$where .= " AND post_type IN ('$post_types')";
	}

	// Append post statuses
	$post_status = $r['post_status'];
	if ( 'any' != $post_status ) {
		if ( is_string( $post_status ) )
			$post_status = explode( ',', $post_status );

		$post_status = (array) $post_status;
		$post_status = implode( "','", array_map( 'trim', $post_status ) );
		$where .= " AND post_status IN ('$post_status')";
	}

	// Limit result set to posts in specific sections
	if ( is_array( $r['sections'] ) && ( count( $r['sections'] ) > 0 ) ) {
		$sections = array_map( 'absint', $r['sections'] );
		$sections = implode( ',', array_unique( $sections ) );
		$where .= " AND post_parent IN ($sections)";
	}

	// Limit to specific posts
	if ( is_array( $r['post__in'] ) && ( count( $r['post__in'] ) > 0 ) ) {
		$post__in = array_map( 'absint', $r['post__in'] );
		$post__in = implode( ',', array_unique( $post__in ) );
		$where .= " AND ID IN($post__in)";
	}

	// Result sorting
	$orderby = 'ORDER BY post_parent ASC, menu_order ASC';

 	// Execute query, fetch results as objects in an array keyed on posts.ID
	$posts = $wpdb->get_results(
		"SELECT $fields FROM $wpdb->posts WHERE 1=1 $where $orderby",
		OBJECT_K
		);
	if ( ! is_array( $posts ) || ( count( $posts ) == 0 ) )
		return false;

	// Add url property to each post object ($post->url = permalink)
	if ( ! $r['suppress_urls'] )
		$posts = bu_navigation_get_urls( $posts );

	// Allow custom filtering of posts retrieved using this function
	if ( ! $r['suppress_filter_posts'] ) {
		$posts = apply_filters( 'bu_navigation_filter_posts', $posts );
		$posts = apply_filters( 'bu_navigation_filter_pages', $posts );
	}

	// Chop off anything great than max_items
	if ( $r['max_items'] && is_array( $posts ) && ( count( $posts ) > 0 ) ) {
		$items = array();
		$nItems = 0;

		foreach ( $posts as $id => $post ) {
			if ( $nItems >= $r['max_items'] ) break;
			$items[ $id ] = $post;
			$nItems++;
		}
		$posts = $items;
	}

	return $posts;

}

/**
* Legacy alias for bu_navigation_get_posts
*
* Translates legacy arguments that have been updated for consistency with WP_Query
*
* @param $args mixed Wordpress-style arguments (string or array)
* @return array Array of pages keyed on page ID or FALSE on problem
*/
function bu_navigation_get_pages( $args = '' ) {
	$defaults = array(
		'pages' => null,
		'suppress_filter_pages' => false
		);
	$r = wp_parse_args( $args, $defaults );

	// Legacy arg translation
	if ( ! is_null( $r['pages'] ) ) {
		$r['post__in'] = $r['pages'];
		unset( $r['pages'] );
	}

	$r['suppress_filter_posts'] = $r['suppress_filter_pages'];
	unset( $r['suppress_filter_pages'] );

	return bu_navigation_get_posts( $r );
}

/**
 * Indexes an array of pages by their parent page ID
 *
 * @param $pages array Array of page objects (usually indexed by the post.ID)
 * @return array Array of arrays indexed on post.ID with second-level array containing the immediate children of that post
 */
function bu_navigation_pages_by_parent( $pages ) {
	$pages_by_parent = array();

	if ( is_array($pages) && count($pages) > 0 ) {
		foreach ( $pages as $page ) {
			if ( ! array_key_exists( $page->post_parent, $pages_by_parent ) )
				$pages_by_parent[$page->post_parent] = array();
			array_push( $pages_by_parent[$page->post_parent], $page );
		}
	}

	$pages_by_parent = apply_filters( 'bu_navigation_filter_pages_by_parent', $pages_by_parent );

	return $pages_by_parent;
}

/**
 * Add this filter before calling bu_navigation_pages_by_parent to sort each sub-array by menu order.
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
 * @param $page object Page object
 * @param $html string Option HTML to place inside the list item after the page
 * @return string HTML fragment containing list item
 */
function bu_navigation_format_page( $page, $args = '' ) {
	$defaults = array(
		'item_tag' => 'li',
		'item_id' => null,
		'html' => '',
		'depth' => null,
		'position' => null,
		'siblings' => null,
		'anchor_class' => '',
		'anchor' => true,
		'title_before' => '',
		'title_after' => '',
		'section_ids' => null
		);
	$r = wp_parse_args( $args, $defaults );

	if ( ! isset( $page->navigation_label ) ) {
		$page->navigation_label = apply_filters( 'the_title', $page->post_title, $page->ID );
	}

	$title = $page->navigation_label;
	$href = $page->url;
	$anchor_class = $r['anchor_class'];

	if ( is_numeric( $r['depth'] ) )
		$anchor_class .= sprintf( ' level_%d', intval( $r['depth'] ) );

	$attrs = array(
		'class' => trim( $anchor_class ),
		);

	if ( isset( $page->url ) && ! empty( $page->url ) )
		$attrs['href'] = $page->url;

	if ( isset( $page->target ) && $page->target == 'new' )
		$attrs['target'] = '_blank';

	$attrs = apply_filters( 'bu_navigation_filter_anchor_attrs', $attrs, $page );

	$attributes = '';

	if ( is_array( $attrs ) && count( $attrs ) > 0 ) {
		foreach ( $attrs as $attr => $val ) {
			if ( $val )
				$attributes .= sprintf( ' %s="%s"', $attr, $val );
		}
	}

	$item_classes = array( 'page_item', 'page-item-' . $page->ID );

	if ( is_array( $r['section_ids'] ) && in_array( $page->ID, $r['section_ids'] ) )
		array_push( $item_classes, 'has_children' );

	if ( is_numeric( $r['position'] ) && is_numeric( $r['siblings'] ) ) {
		if ( $r['position'] == 1 )
			array_push( $item_classes, 'first_item' );
		if ( $r['position'] == $r['siblings'] )
			array_push( $item_classes, 'last_item' );
	}

	$item_classes = apply_filters( 'bu_navigation_filter_item_attrs', $item_classes, $page );
	$item_classes = apply_filters( 'page_css_class', $item_classes, $page );

	$title = apply_filters( 'bu_page_title', $title );
	$label = apply_filters( 'bu_navigation_format_page_label', $title, $page );

	$label = $r['title_before'] . $label . $r['title_after'];
	$anchor = $r['anchor'] ? sprintf( '<a%s>%s</a>', $attributes, $label ) : $label;

	$html = sprintf( "<%s class=\"%s\">\n%s\n %s</%s>\n",
		$r['item_tag'],
		implode( ' ', $item_classes ),
		$anchor,
		$r['html'],
		$r['item_tag']
		);

	if ( $r['item_id'] ) {
		$html = sprintf( "<%s id=\"%s\" class=\"%s\">\n%s\n %s</%s>\n",
			$r['item_tag'],
			$r['item_id'],
			implode( ' ', $item_classes ),
			$anchor,
			$r['html'],
			$r['item_tag']
			);
	}

	$args = $r;
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

		if ( $current_page->ID == $page->ID )
			array_push( $classes, 'current_page_item' );

		if ( isset( $page->active_section ) && $page->active_section )
			array_push( $classes, 'current_page_ancestor' );

		if ( $page->ID == $current_page->post_parent )
			array_push( $classes, 'current_page_parent' );
	}

	return $classes;
}

add_filter('bu_navigation_filter_item_attrs', 'bu_navigation_filter_item_attrs', 10, 2);

/**
 * Filter to apply "active" class to a navigation item if it is the current page
 *
 * @todo relocate to a default filters file
 *
 * @param $attributes array Associative array of anchor attributes
 * @param $page object Page object
 */
function bu_navigation_filter_item_active_page( $attributes, $page ) {
	global $wp_query;

	if ( is_singular() || $wp_query->is_posts_page ) {
		$current_page = $wp_query->get_queried_object();

		if ( $current_page->ID == $page->ID )
			$attributes['class'] .= ' active';

		if ( isset( $page->active_section ) && $page->active_section )
			$attributes['class'] .= ' active_section';
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
 * @param $parent_id Integer ID of section (page parent)
 * @param $pages_by_parent array An array of pages indexed by their parent page (see bu_navigation_pages_by_parent)
 * @return string HTML fragment containing unordered list
 */
function bu_navigation_list_section($parent_id, $pages_by_parent, $args = '')
{
	$defaults = array(
		'depth' => 1,
		'container_tag' => 'ul',
		'container_class' => NULL,
		'item_tag' => 'li',
		'section_ids' => NULL
		);

	$r = wp_parse_args( $args, apply_filters( 'bu_filter_list_section_defaults', $defaults ) );

	$html = '';

	if (array_key_exists($parent_id, $pages_by_parent))
	{
		$children = $pages_by_parent[$parent_id];

		if ((is_array($children)) && (count($children) > 0))
		{
			$html .= sprintf("\n<%s", $r['container_tag']);;

			if ( $r['container_class'] ) {
				$html .= ' class="' . esc_attr( $r['container_class'] ) . '"';
			}

			$html .= ">\n";

			foreach ($children as $page)
			{
				$sargs = $r;
				$sargs['depth']++;

				$child_html = bu_navigation_list_section($page->ID, $pages_by_parent, $sargs);
				$html .= bu_navigation_format_page($page, array(
					'html' => $child_html,
					'depth' => $r['depth'],
					'item_tag' => $r['item_tag'],
					'section_ids' => $r['section_ids']
					));
			}

			$html .= sprintf("\n</%s>\n", $r['container_tag']);
		}
	}

	return $html;
}

/**
 * Alternative to WordPress' wp_list_pages function
 *
 * @todo refactor to decouple widget-specific logic
 *
 * @param $args mixed Array or string of WP-style arguments
 * @return string HTML fragment containing navigation list
 */
function bu_navigation_list_pages( $args = '' ) {
	$defaults = array(
		'page_id' => null,
		'sections' => null,
		'post_types' => array( 'page' ),
		'include_links' => true,
		'echo' => 0,
		'title_li' => '',
		'navigate_in_section' => '',
		'container_tag' => 'ul',
		'container_id' => '',
		'container_class' => '',
		'item_tag' => 'li',
		'title_before' => '',
		'title_after' => '',
		'style' => null
		);
	$r = wp_parse_args($args, $defaults);

	$output = '';

	$section_ids = array();

	// Get ancestors if a specific post is being listed
	if ( $r['page_id'] ) {
		$all_sections = bu_navigation_load_sections( $r['post_types'], $r['include_links'] );

		$section_ids = array_keys( $all_sections['sections'] );
		$section_args = array(
			'post_types' => $r['post_types'],
			'include_links' => $r['include_links']
			);
		$r['sections'] = bu_navigation_gather_sections( $r['page_id'], $section_args, $all_sections );

	}

	// Fetch post list, possibly limited to specific sections
	$page_args = array(
		'sections' => $r['sections'],
		'post_types' => $r['post_types'],
		'include_links' => $r['include_links'],
		);
	$pages = bu_navigation_get_pages( $page_args );
	$pages_by_parent = bu_navigation_pages_by_parent( $pages );

	$sections = ! empty( $r['sections'] ) ? $r['sections'] : array_keys( $pages_by_parent );

	$list_attributes = '';

	if ( $r['container_id'] ) $list_attributes .= sprintf( ' id="%s"', $r['container_id'] );
	if ( $r['container_class'] ) $list_attributes .= sprintf( ' class="%s"', $r['container_class'] );

	$html = sprintf( "<%s %s>\n", $r['container_tag'], $list_attributes );

	if ( $r['style'] == 'adaptive' ) {

		// If the "active" page isn't in the list of sections (because it has no children), add it
		// @todo I don't think this can ever be true based on the code in bu-navigation-adaptive-contentnav.php
		if ( $r['page_id'] && ! in_array( $r['page_id'], $sections ) ) {
			array_push( $sections, $r['page_id'] );
		}

		if ( count( $sections ) > 2 ) {
			$last_section = array_pop( $sections );
			array_push( $sections, $last_section );

			if ( array_key_exists( $last_section, $pages_by_parent ) &&
			     is_array( $pages_by_parent[$last_section] ) &&
			     ( count( $pages_by_parent[$last_section] ) > 0 )
			   ) {
				// Last section has children, so it will be the "top"
				$sections = array_slice($sections, -2);
			} else {
				// Last section has no children, so its parent will be the "top"
				$sections = array_slice($sections, -3);
			}
		}
	}

 	// Default to top level pages
	$section = $sections[0];

	// Sectional navigation requires at least two levels
	if ( $r['navigate_in_section'] ) {
		if ( isset( $sections[1] ) ) {
			$section = $sections[1];
		} else {
			$section = null;
		}
	}

	// Loop over top section
	if ( isset( $pages_by_parent[$section] ) && is_array( $pages_by_parent[$section] ) && ( count( $pages_by_parent[$section] ) > 0 ) ) {

		$sargs = array(
			'container_tag' => $r['container_tag'],
			'item_tag' => $r['item_tag'],
			'depth' => 2,
			'section_ids' => $section_ids
			);

		$page_position = 1;
		$number_siblings = count( $pages_by_parent[$section] );

		foreach ( $pages_by_parent[$section] as $page ) {

			$child_html = bu_navigation_list_section( $page->ID, $pages_by_parent, $sargs );

			$pargs = array(
				'html' => $child_html,
				'depth' => 1,
				'position' => $page_position,
				'siblings' => $number_siblings,
				'item_tag' => $r['item_tag'],
				'section_ids' => $section_ids
			);

			$html .= bu_navigation_format_page($page, $pargs);

			$page_position++;
		}

	} else {
		return '';
	}

	$html .= sprintf( "</%s>\n", $r['container_tag'] );

	if ( $r['echo'] )
		echo $html;

	return $html;
}

/**
 * Displays a primary navigation bar
 *
 * @return void
 */
function bu_navigation_display_primary( $args = '' ) {
	$defaults = array(
		'post_types' => array( 'page' ),
		'include_links' => true,
		'depth' => BU_NAVIGATION_PRIMARY_DEPTH,
		'max_items' => BU_NAVIGATION_PRIMARY_MAX,
		'dive' => true,
		'container_tag' => 'ul',
		'container_id' => 'nav',
		'container_class' => '',
		'item_tag' => 'li',
		'identify_top' => false,
		'whitelist_top' => null,
		'echo' => 1,
		'title_before' => '',
		'title_after' => '',
		);
	$r = wp_parse_args( $args, apply_filters( 'bu_filter_primarynav_defaults', $defaults ) );

	// Gather all sections
	$section_args = array(
		'direction' => 'down',
		'depth' => $r['depth'],
		'post_types' => $r['post_types'],
		'include_links' => $r['include_links']
		);
	$sections = bu_navigation_gather_sections( 0, $section_args );

	// Fetch only posts in sections that we need
	$post_args =  array(
		'sections' => $sections,
		'post_types' => $r['post_types'],
		'include_links' => $r['include_links']
		);
	$pages = bu_navigation_get_pages( $post_args );
	$pages_by_parent = bu_navigation_pages_by_parent( $pages );

	$top_level_pages = array();
	$html = '';

	// Start displaying top level posts
	if( is_array( $pages_by_parent ) && isset( $pages_by_parent[0] ) && ( count( $pages_by_parent[0] ) > 0 ) )
		$top_level_pages = $pages_by_parent[0];

	if ( ! empty( $top_level_pages ) ) {

		$nItems = 0;
		$whitelist = null;

		// Optionally restrict top level posts to white list of post names
		if ( $r['whitelist_top'] ) {
			if ( is_string( $r['whitelist_top'] ) ) $whitelist = explode( ',', $r['whitelist_top'] );
			if ( is_array( $r['whitelist_top'] ) ) $whitelist = $r['whitelist_top'];
		}

		// Start list
		$html = sprintf('<%s id="%s" class="%s %s">',
			$r['container_tag'],
			$r['container_id'],
			$r['container_class'],
			$r['dive'] ? '' : 'no-dive'
			);

		// Section arguments
		$sargs = array(
			'container_tag' => $r['container_tag'],
			'item_tag' => $r['item_tag'],
			'depth' => 2
			);

		foreach ( $top_level_pages as $page ) {

			// Check whitelist if it's being used
			if ( is_array( $whitelist ) && ! in_array( $page->post_name, $whitelist ) )
				continue;

			$child_html = '';

			// List children if we're diving
			if ( $r['dive'] )
				$child_html = bu_navigation_list_section( $page->ID, $pages_by_parent, $sargs );

			// Display formatted page (optionally with post name as ID)
			if ( $r['identify_top'] ) {
				$html .= bu_navigation_format_page( $page, array( 'html' => $child_html, 'depth' => 1, 'item_tag' => $r['item_tag'], 'item_id' => $page->post_name ) );
			} else {
				$html .= bu_navigation_format_page( $page, array( 'html' => $child_html, 'depth' => 1, 'item_tag' => $r['item_tag'] ) );
			}

			$nItems++;

			// Limit to max number of posts
			if ( $nItems >= $r['max_items'] )
				break;
		}

		// Close list
		$html .= sprintf( "\n</%s>\n", $r['container_tag'] );
	}

	if ( $r['echo'] )
		echo $html;

	return $html;

}

/**
 * Generate page parent select menu
 *
 * @uses bu_filter_pages_parent_dropdown().
 *
 * @param string $post_type required -- post type to filter posts for
 * @param int $selected post ID of the selected post
 * @param array $args optional configuration object
 *
 * @return string the resulting dropdown markup
 */
function bu_navigation_page_parent_dropdown( $post_type, $selected = 0, $args = array() ) {

	$defaults = array(
		'echo' => 1,
		'select_id' => 'bu_filter_pages',
		'select_name' => 'post_parent',
		'select_classes' => '',
		'post_status' => array( 'publish', 'private' )
		);
	$r = wp_parse_args( $args, $defaults);

	// Grab top level pages for current post type
	$args = array(
		'direction' => 'down',
		'depth' => 1,
		'post_types' => (array) $post_type
		);
	$sections = bu_navigation_gather_sections(0, $args);

	$args = array(
		'suppress_filter_pages' => TRUE,
		'sections' => $sections,
		'post_types' => (array) $post_type,
		'post_status' => (array) $r['post_status']
		);
	$pages = bu_navigation_get_pages($args);
	$pages_by_parent = bu_navigation_pages_by_parent($pages);

	$options = "\n\t<option value=\"0\">" . __('Show all sections') . "</option>\r";

	// Get options
	ob_start();
	bu_filter_pages_parent_dropdown( $pages_by_parent, $selected );
	$options .= ob_get_contents();
	ob_end_clean();

	$classes = ! empty( $r['select_classes'] ) ? " class=\"{$r['select_classes']}\"" : '';

	$dropdown = sprintf( "<select id=\"%s\" name=\"%s\"%s>\r%s\r</select>\r", $r['select_id'], $r['select_name'], $classes, $options );

	if( $r['echo'] ) echo $dropdown;

	return $dropdown;

}

/**
 * Displays a select box containing page parents, used to filter page list by parent
 *
 * Relocated from the navigation plugin (bu-filter-pages.php) to remove dependency on plugin.
 *
 * @return boolean TRUE if the box was displayed, FALSE otherwise.
 */
function bu_filter_pages_parent_dropdown($pages_by_parent, $default = 0, $parent = 0, $level = 0) {

	$post_types = bu_navigation_supported_post_types();

	if ((is_array($pages_by_parent)) && (array_key_exists($parent, $pages_by_parent)) && (count($pages_by_parent) > 0)) {
		foreach ($pages_by_parent[$parent] as $p) {

			if (!in_array($p->post_type, $post_types)) continue; // only show valid post types
			if (!array_key_exists($p->ID, $pages_by_parent)) continue; // don't show pages with no children

			$padding = str_repeat('&nbsp;', $level * 3);
			$selected = ($p->ID == $default) ? 'selected="selected"' : '';

			printf("\n\t<option value=\"%d\" %s>%s%s</option>\r", $p->ID, $selected, $padding, esc_html($p->post_title));
			bu_filter_pages_parent_dropdown($pages_by_parent, $default, $p->ID, $level + 1);
		}

		return TRUE;

	}

	return FALSE;

}
