<?php
/**
 * Methods for getting urls associated with posts loaded elsewhere.
 *
 * @package BU_Navigation
 */

namespace BU\Plugins\Navigation;

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
function get_urls( $pages ) {
	// If the $pages parameter isn't an array, or is empty, return it back unaltered.
	if ( empty( $pages ) || ! is_array( $pages ) ) {
		return $pages;
	}

	$pages_with_url = array_map(
		function ( $page ) use ( $pages ) {
			// Use get_page_link for pages.
			if ( 'page' === $page->post_type ) {
				$page->url = get_nav_page_link( $page, $pages );
				return $page;
			}

			// Use post_content as url for the 'link' type.
			if ( BU_NAVIGATION_LINK_POST_TYPE === $page->post_type ) {
				$page->url = $page->post_content;
				return $page;
			}

			// Use post_link for everything else.
			$page->url = get_nav_post_link( $page, $pages );
			return $page;

		},
		$pages
	);

	return $pages_with_url;
}

/**
 * Retrieve the page permalink.
 *
 * Intended as an efficient alternative to get_page_link() / _get_page_link().
 * Allows you to provide an array of post ancestors for use calculating post name path.
 *
 * Was originally called bu_navigation_get_page_link()
 *
 * @see _get_page_link()
 *
 * @param  object  $page       Post object to calculate permalink for.
 * @param  array   $ancestors  Optional. An array of post objects keyed on post ID. Should contain all ancestors of $page.
 * @param  boolean $sample     Optional. Is it a sample permalink.
 * @return string              Post permalink.
 */
function get_nav_page_link( $page, $ancestors = array(), $sample = false ) {
	global $wp_rewrite;

	$page_link        = $wp_rewrite->get_page_permastruct();
	$draft_or_pending = true;
	if ( isset( $page->post_status ) ) {
		$draft_or_pending = in_array( $page->post_status, array( 'draft', 'pending', 'auto-draft' ), true );
	}
	$use_permastruct = ( ! empty( $page_link ) && ( ! $draft_or_pending || $sample ) );

	if ( 'page' === get_option( 'show_on_front' ) && get_option( 'page_on_front' ) == $page->ID ) {
		$page_link = home_url( '/' );
	} elseif ( $use_permastruct ) {
		$slug      = get_page_uri( $page, $ancestors );
		$page_link = str_replace( '%pagename%', $slug, $page_link );
		$page_link = home_url( user_trailingslashit( $page_link, 'page' ) );
	} else {
		$page_link = home_url( '?page_id=' . $page->ID );
	}

	return $page_link;
}

/**
 * Retrieve the permalink for a post with a custom post type.
 *
 * Intended as an efficient alternative to get_post_permalink().
 * Allows you to provide an array of post ancestors for use calculating post name path.
 *
 * Was originally bu_navigation_get_post_link().
 *
 * @see get_post_permalink()
 *
 * @param  object  $post       Post object to calculate permalink for.
 * @param  array   $ancestors  Optional. An array of post objects keyed on post ID. Should contain all ancestors of $post.
 * @param  boolean $sample     Optional. Is it a sample permalink.
 * @return string              Post permalink.
 */
function get_nav_post_link( $post, $ancestors = array(), $sample = false ) {
	global $wp_rewrite;

	$post_link        = $wp_rewrite->get_extra_permastruct( $post->post_type );
	$draft_or_pending = true;
	if ( isset( $post->post_status ) ) {
		$draft_or_pending = in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft' ), true );
	}
	$use_permastruct = ( ! empty( $post_link ) && ( ! $draft_or_pending || $sample ) );
	$post_type       = get_post_type_object( $post->post_type );
	$slug            = $post->post_name;

	if ( $post_type->hierarchical ) {
		$slug = get_page_uri( $post, $ancestors );
	}

	if ( $use_permastruct ) {
		$post_link = str_replace( "%$post->post_type%", $slug, $post_link );
		$post_link = home_url( user_trailingslashit( $post_link ) );
	} else {
		if ( $post_type->query_var && ! $draft_or_pending ) {
			$post_link = add_query_arg( $post_type->query_var, $slug, '' );
		} else {
			$post_link = add_query_arg(
				array(
					'post_type' => $post->post_type,
					'p'         => $post->ID,
				),
				''
			);
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
function get_page_uri( $page, $ancestors ) {

	// Used to cache pages we load that aren't contained in $ancestors.
	static $extra_pages   = array();
	static $missing_pages = array();

	$uri = $page->post_name;

	// The loose 0 comparison here is a problem, as it needs to match the string "0", not the number 0.
	// Likely this should be "0" !== $page->post_parent instead.
	while ( isset( $page->post_parent ) && 0 != $page->post_parent ) {

		// Avoid infinite loops.
		// I can't imagine that switching to a strict check here won't cause a problem.
		// But it's a little hard to say for sure.
		if ( $page->post_parent == $page->ID ) {
			break;
		}

		// Attempt to load missing ancestors.
		if ( ! array_key_exists( $page->post_parent, $ancestors ) ) {
			if ( ! array_key_exists( $page->post_parent, $extra_pages ) && ! in_array( $page->post_parent, $missing_pages ) ) {
				$missing_ancestors = get_page_uri_ancestors( $page );
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

/**
 * Only used by 'get_page_uri', to calculate "missing ancestors".
 *
 * It remained undocumented for years, and this is unsurprising as it is a bit mysterious.
 * It also looks very expensive as it calls the major data loading components, load_sections() and get_nav_posts().
 * There may be some more elegant way to account for missing ancestors; potentially a topic for a future release.
 *
 * @param object $post Post object to find ancestors for.
 * @return array Array of objects representing ancestor posts.
 */
function get_page_uri_ancestors( $post ) {

	$ancestors    = array();
	$all_sections = load_sections( $post->post_type );

	// Load ancestors post IDs.
	$section_ids = gather_sections( $post->ID, array( 'post_types' => $post->post_type ), $all_sections );
	$section_ids = array_filter( $section_ids );

	// Fetch ancestor posts, with only the columns we need to determine permalinks.
	if ( ! empty( $section_ids ) ) {
		$args = array(
			'post__in'              => $section_ids,
			'post_types'            => 'any',
			'post_status'           => 'any',
			'suppress_urls'         => true,
			'suppress_filter_posts' => true,
		);

		// Only need a few fields to determine the correct URL.
		// Adding and removing a filter here seems inelegant, and might be better accomplished with a $fields parameter?
		add_filter( 'bu_navigation_filter_fields', __NAMESPACE__ . '\page_uri_ancestors_fields', 9999 );
		$ancestors = get_nav_posts( $args );
		remove_filter( 'bu_navigation_filter_fields', __NAMESPACE__ . '\page_uri_ancestors_fields', 9999 );

		if ( false === $ancestors ) {
			$ancestors = array();
		}
	}

	return $ancestors;
}

/**
 * Convenience callback function for get_page_uri_ancestors() to filter return fields.
 *
 * The callback just provides a hard-coded array of post fields. Returning fewer fields should
 * make the underlying call to get_nav_posts() faster.  The callback takes the incoming $fields parameter,
 * but does nothing with it, just returns the hard-coded array.
 *
 * Ultimately there has to be a better way to accomplish this than a filter that is added and
 * removed just for this one query.
 *
 * @param array $fields Not used.
 * @return array
 */
function page_uri_ancestors_fields( $fields ) {
	return array( 'ID', 'post_name', 'post_parent' );
}
