<?php
/**
 * Filter functions to adjust markup output.
 *
 * @package BU_Navigation
 */

namespace BU\Plugins\Navigation;

/**
 * Filter to apply "active" class to a navigation item container if it is the current page
 *
 * @param array  $classes Associative array of css classes.
 * @param object $page Page object.
 * @return array Array of classes
 */
function filter_item_attrs( $classes, $page ) {
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

add_filter( 'bu_navigation_filter_item_attrs', __NAMESPACE__ . '\filter_item_attrs', 10, 2 );

/**
 * Filter to apply "active" class to a navigation item if it is the current page
 *
 * @param array  $attributes Associative array of anchor attributes.
 * @param object $page Page object.
 */
function filter_item_active_page( $attributes, $page ) {
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

add_filter( 'bu_navigation_filter_anchor_attrs', __NAMESPACE__ . '\filter_item_active_page', 10, 2 );

// Add default filters from "the_title" when displaying navigation label.
add_filter( 'bu_navigation_format_page_label', 'wptexturize' );
add_filter( 'bu_navigation_format_page_label', 'convert_chars' );
add_filter( 'bu_navigation_format_page_label', 'trim' );
