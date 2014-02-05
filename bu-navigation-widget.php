<?php
/*
 * Alternative content (side) navigation widget
 * Niall Kavanagh
 * ntk@bu.edu
 */

define( 'BU_WIDGET_PAGES_LIST_CLASS', 'smartnav level1' );			// default class for list
define( 'BU_WIDGET_PAGES_LIST_ID', 'contentnavlist' );				// default element id for list

define( 'BU_WIDGET_CONTENTNAV_BEFORE', '<div id="contentnav">' );	// default HTML fragment open
define( 'BU_WIDGET_CONTENTNAV_AFTER', '</div>' );					// default HTML fragment close

class BU_Widget_Pages extends WP_Widget {

	public $title_options = array( 'none', 'section', 'static' );
	public $styles = array( 'site', 'section', 'adaptive' );

	public $defaults = array(
			'navigation_title' => 'none',
			'navigation_title_text' => '',
			'navigation_title_url' => '',
			'navigation_style' => 'site'
			);

	function BU_Widget_Pages() {
		$widget_ops = array( 'classname' => 'widget_bu_pages', 'description' => __( "Navigation list of your site's pages", 'bu-navigation' ) );
		$this->WP_Widget( 'bu_pages', __('Content Navigation', 'bu-navigation' ), $widget_ops );
	}

	/**
	 * Returns HTML fragment containing a section title
	 *
	 * @param array $args widget args, as passed to WP_Widget::widget
	 * @param array $instance widget instance args, as passed to WP_Widget::widget
	 * @return string HTML fragment with title
	 */
	function section_title( $args, $instance ) {
		global $post;

		$html = $title = $href = '';
		$section_id = 0;

		// Determine which post to use for the section title
		if ( $instance['navigation_style'] != 'site' ) {

			// Gather ancestors
			$sections = bu_navigation_gather_sections( $post->ID, array( 'post_types' => $post->post_type ) );

			// Adaptive navigation style uses the grandparent of current post
			if ( $instance['navigation_style'] == 'adaptive' ) {
				$grandparent_offset = count( $sections ) - 2;
				if ( isset( $sections[$grandparent_offset] ) ) {
					$section_id = $sections[$grandparent_offset];
				}
			} else {
				// Default to top level post (if we have one)
				if ( isset( $sections[1] ) ) {
					$section_id = $sections[1];
				}
			}

		}

		// Use section post for title
		if ( $section_id ) {
			$section = get_post( $section_id );

			// Prevent usage of non-published posts as titles
			if ( 'publish' === $section->post_status ) {
				// Second argument prevents usage of default (no title) label
				$title = bu_navigation_get_label( $section, '' );
				$href = get_permalink( $section->ID );
			}
		}

		// Fallback to site title if we're still empty
		if ( empty( $title ) ) {
			$title = get_bloginfo( 'name' );
			$href = trailingslashit( get_bloginfo( 'url' ) );
		}

		if ( $title && $href ) {
			$html =  sprintf( "<a class=\"content_nav_header\" href=\"%s\">%s</a>\n", esc_attr( $href ), $title );
		}

		return $html;

	}

	/**
	 * Display the content navigation widget
	 *
	 * @param array $args widget args
	 * @param array $instance widget instance args
	 */
	function widget( $args, $instance ) {
		global $post;

		// Only display navigation widget for supported post types
		if ( ! in_array( $post->post_type, bu_navigation_supported_post_types() ) )
			return;

		extract( $args );

		$title = '';

		// Set widget title
		if ( ( $instance['navigation_title'] == 'static' ) && ( ! empty( $instance['navigation_title_text'] ) ) ) {

			$title = apply_filters( 'widget_title', $instance['navigation_title_text'] );

			// Wrap with anchor tag if URL is present
			if ( ! empty( $instance['navigation_title_url'] ) ) {
				$title = sprintf( '<a class="content_nav_header" href="%s">%s</a>', $instance['navigation_title_url'], $title );
			}

		} else if ( $instance['navigation_title'] == 'section' ) {

			// Use navigation label of top level post for current section
			$title = $this->section_title( $args, $instance );

		}

		// Prepare arguments to bu_navigation_list_pages
		$list_args = array(
			'page_id' => $post->ID,
			'title_li' => '',
			'echo' => 0,
			'container_id' => BU_WIDGET_PAGES_LIST_ID,
			'post_types' => $post->post_type,
			);

		// Set list arguments based on navigation style
		if ( array_key_exists( 'navigation_style', $instance ) ) {

		  $list_args['style'] = $instance['navigation_style'];

			if ( $instance['navigation_style'] == 'section' ) {
				$list_args['navigate_in_section'] = 1;
				if ( is_404() ) return '';
			} else if ( $instance['navigation_style'] == 'adaptive' ) {
				add_action( 'bu_navigation_widget_before_list', 'bu_navigation_widget_adaptive_before_list' );
			}

		} else {
			error_log( "No nav label widget style set!" );
		}

		do_action( 'bu_navigation_widget_before_list' );

		// Fetch markup and display
		$out = bu_navigation_list_pages( apply_filters( 'widget_bu_pages_args', $list_args ) );

		if ( ! empty( $out ) ) {

			printf('%s<div id="contentnav">', $before_widget);

			if ( $title )
				echo $before_title . $title . $after_title;

			printf('%s</div>', $out);

			echo $after_widget;

		}

	}

	/**
	 * Save handler for the content navigation widget
	 *
	 * @param array $new_instance updated widget parameters
	 * @param array $old_instance original widget parameters
	 */
	function update( $new_instance, $old_instance ) {

		$instance = $old_instance;
		$instance = wp_parse_args( $instance, $this->defaults );

		$instance['navigation_title'] = ( in_array( $new_instance['navigation_title'], $this->title_options ) ) ? $new_instance['navigation_title'] : 'none';
		$instance['navigation_title_text'] = ( $instance['navigation_title'] == 'static' ) ? sanitize_text_field( $new_instance['navigation_title_text'] ) : '';
		$instance['navigation_title_url'] = ( $instance['navigation_title'] == 'static' ) ? sanitize_text_field( $new_instance['navigation_title_url'] ) : '';
		$instance['navigation_style'] = ( in_array( $new_instance['navigation_style'], $this->styles ) ) ? $new_instance['navigation_style'] : 'site';

		return $instance;
	}

	/**
	 * Display the content navigation widget form
	 *
	 * @param array $instance the specific widget instance being displayed
	 */
	function form( $instance ) {

		$instance = wp_parse_args( $instance, $this->defaults );

		$navigation_title = ( in_array( $instance['navigation_title'], $this->title_options ) ) ? $instance['navigation_title'] : 'none';
		$navigation_title_text = esc_attr( $instance['navigation_title_text'] );
		$navigation_title_url = esc_attr( $instance['navigation_title_url'] );
		$navigation_style = ( in_array( $instance['navigation_style'], $this->styles ) ) ? $instance['navigation_style'] : 'site';

		include( BU_NAV_PLUGIN_DIR . '/templates/widget-form.php' );

	}

}
