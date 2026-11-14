<?php

// Name of meta_key used to exclude pages from navigation
define( 'BU_NAV_META_PAGE_EXCLUDE', '_bu_cms_navigation_exclude' );

// Default post exclusion value for posts that don't have a post meta row yet
if ( ! defined( 'BU_NAVIGATION_POST_EXCLUDE_DEFAULT' ) ) {
	define( 'BU_NAVIGATION_POST_EXCLUDE_DEFAULT', false );
}

/**
 * Built-in filter for bu_navigation_get_pages
 *
 * Removes any posts that have "Display in navigation lists" unchecked
 *
 * A post with no meta value uses BU_NAVIGATION_POST_EXCLUDE_DEFAULT. Posts created in either
 * editor get a value on their first save (see bu_navigation_set_new_post_visibility()).
 */
function bu_navigation_filter_pages_exclude( $pages ) {
	global $wpdb;

	$filtered = array();

	if ( is_array( $pages ) && count( $pages ) > 0 ) {

		// Fetch pages that have been explicitly excluded from navigation lists
		$ids   = array_keys( $pages );
		$query = sprintf(
			"SELECT post_id, meta_value FROM %s WHERE meta_key = '%s' AND post_id IN (%s)",
			$wpdb->postmeta,
			BU_NAV_META_PAGE_EXCLUDE,
			implode( ',', $ids )
		);

		$exclude_meta = $wpdb->get_results( $query, OBJECT_K );

		if ( false === $exclude_meta ) {
			$this->plugin->log( '%s - Error querying navigation exclusions: %s', __METHOD__, $wpdb->last_error );
			return $pages;
		}

		foreach ( $pages as $page ) {

			// Post meta row exists, determine exclusion based on meta_value
			if ( array_key_exists( $page->ID, $exclude_meta ) ) {
				$excluded = (bool) $exclude_meta[ $page->ID ]->meta_value;
			} else {

				// No post meta row has been inserted yet
				if ( isset( $page->post_type ) && BU_NAVIGATION_LINK_POST_TYPE == $page->post_type ) {
					// Navigation links get special treatment since they will always be visible
					$excluded = false;
				} else {
					// Otherwise fall back to default constant
					$excluded = BU_NAVIGATION_POST_EXCLUDE_DEFAULT;
				}
			}

			if ( ! $excluded ) {
				$filtered[ $page->ID ] = $page;
			}
		}
	}

	return $filtered;
}

add_filter( 'bu_navigation_filter_pages', 'bu_navigation_filter_pages_exclude' );

/**
 * Tells you if a post is excluded from navigation lists
 *
 * Content editors set this value through the "Display in navigation lists" checkbox
 * in the "Placement in Navigation" metabox.
 *
 * @param mixed $post a post ID or object to determine excluded status for
 * @return bool true if the post is excluded, false otherwise
 */
function bu_navigation_post_excluded( $post ) {
	if( is_numeric( $post ) ) {
		$post = get_post( $post );
	}

	if( ! is_object( $post ) ) {
		return false;
	}

	$excluded = get_post_meta( $post->ID, BU_NAV_META_PAGE_EXCLUDE, true );

	// No value set yet, fall back to default
	if ( $excluded === '' )
		$excluded = BU_NAVIGATION_POST_EXCLUDE_DEFAULT;

	return (bool) $excluded;

}

/**
 * Navigation visibility value for a new post: '0' (shown), or '1' (hidden) for a top level post
 * while top level posts are not allowed in navigation lists.
 *
 * @param WP_Post $post The new post.
 * @return string '0' or '1'.
 */
function bu_navigation_new_post_exclude_value( $post ) {
	global $bu_navigation_plugin;

	$hide_top_level = 0 === (int) $post->post_parent && ! $bu_navigation_plugin->settings->get( 'allow_top' );

	return $hide_top_level ? '1' : '0';
}

/**
 * Store the navigation visibility of a post created in an editor, on its first save.
 *
 * Both editors create a new post as an auto-draft, and its first save leaves that status, whether
 * the save is a draft save, a publish or an autosave. The value is stored then, so a post shows in
 * navigation lists by default whichever editor creates it or edits it later. A value already stored
 * is kept. The editors save the value chosen in them after this runs: the Classic Editor metabox
 * from its checkbox, the Block Editor panel through the REST API.
 *
 * Posts created by code without an auto-draft (imports, WP-CLI) get no value here and use
 * BU_NAVIGATION_POST_EXCLUDE_DEFAULT.
 *
 * @param string  $new_status The new post status.
 * @param string  $old_status The previous post status.
 * @param WP_Post $post       The post.
 * @return void
 */
function bu_navigation_set_new_post_visibility( $new_status, $old_status, $post ) {
	global $bu_navigation_plugin;

	if ( 'auto-draft' !== $old_status || in_array( $new_status, array( 'auto-draft', 'inherit', 'trash' ), true ) ) {
		return;
	}

	if ( empty( $bu_navigation_plugin ) || ! in_array( $post->post_type, bu_navigation_supported_post_types(), true ) ) {
		return;
	}

	if ( '' !== (string) get_post_meta( $post->ID, BU_NAV_META_PAGE_EXCLUDE, true ) ) {
		return;
	}

	update_post_meta( $post->ID, BU_NAV_META_PAGE_EXCLUDE, bu_navigation_new_post_exclude_value( $post ) );
}

add_action( 'transition_post_status', 'bu_navigation_set_new_post_visibility', 10, 3 );
