<?php
/**
 * Alternative content (side) navigation widget
 * Niall Kavanagh
 * ntk@bu.edu
 *
 * @package BU_Navigation
 */

// Default class for list.  UNUSED in the plugin and the BU CMS build.  Should be removed.
define( 'BU_WIDGET_PAGES_LIST_CLASS', 'smartnav level1' );

// Default element id for list.
define( 'BU_WIDGET_PAGES_LIST_ID', 'contentnavlist' );

// Default HTML fragment open. UNUSED in the plugin and the BU CMS build.  Should be removed.
define( 'BU_WIDGET_CONTENTNAV_BEFORE', '<div id="contentnav">' );

// Default HTML fragment close. UNUSED in the plugin and the BU CMS build.  Should be removed.
define( 'BU_WIDGET_CONTENTNAV_AFTER', '</div>' );

/**
 * Widget for displaying navigation.
 */
class BU_Widget_Pages extends WP_Widget {

	/**
	 * Options for displaying the widget title.
	 *
	 * @var array
	 */
	public $title_options = array( 'none', 'section', 'static' );

	/**
	 * Options for the widget display style.
	 *
	 * @var array
	 */
	public $styles = array( 'site', 'section', 'adaptive' );

	/**
	 * Array of defaults for the widget.
	 *
	 * @var array
	 */
	public $defaults = array(
		'navigation_title'      => 'none',
		'navigation_title_text' => '',
		'navigation_title_url'  => '',
		'navigation_style'      => 'site',
	);

	/**
	 * Constructor that registers with the parent class.
	 */
	public function __construct() {
		$widget_ops = array(
			'classname'   => 'widget_bu_pages',
			'description' => __( "Navigation list of your site's pages", 'bu-navigation' ),
		);
		parent::__construct( 'bu_pages', __( 'Content Navigation', 'bu-navigation' ), $widget_ops );
	}

	/**
	 * Returns HTML fragment containing a section title
	 *
	 * @param array $args widget args, as passed to WP_Widget::widget.
	 * @param array $instance widget instance args, as passed to WP_Widget::widget.
	 * @return string HTML fragment with title
	 */
	public function section_title( $args, $instance ) {
		global $post;

		$html       = '';
		$title      = '';
		$href       = '';
		$section_id = 0;

		// Determine which post to use for the section title.
		if ( ! empty( $instance['navigation_style'] ) && $instance['navigation_style'] != 'site' ) {

			// Gather ancestors.
			$sections = bu_navigation_gather_sections( $post->ID, array( 'post_types' => $post->post_type ) );

			// Adaptive navigation style uses the grandparent of current post.
			if ( $instance['navigation_style'] == 'adaptive' ) {

				// Fetch post list, possibly limited to specific sections.
				$page_args       = array(
					'sections'      => $sections,
					'post_types'    => array( $post->post_type ),
					'include_links' => false,
				);
				$pages           = bu_navigation_get_pages( $page_args );
				$pages_by_parent = bu_navigation_pages_by_parent( $pages );

				$last_section = array_pop( $sections );
				array_push( $sections, $last_section );

				if ( array_key_exists( $last_section, $pages_by_parent ) &&
					is_array( $pages_by_parent[ $last_section ] ) &&
					( count( $pages_by_parent[ $last_section ] ) > 0 )
				   ) {
					// Last section has children, so its parent will be section title.
					$grandparent_offset = count( $sections ) - 2;
				} else {
					// Last section has no children, so its grandparent will be the section title.
					$grandparent_offset = count( $sections ) - 3;
				}

				if ( isset( $sections[ $grandparent_offset ] ) ) {
					$section_id = $sections[ $grandparent_offset ];
				}
			} else {
				// Default to top level post (if we have one).
				if ( isset( $sections[1] ) ) {
					$section_id = $sections[1];
				}
			}
		}

		// Use section post for title.
		if ( $section_id ) {
			$section = get_post( $section_id );

			// Prevent usage of non-published posts as titles.
			if ( 'publish' === $section->post_status ) {
				// Second argument prevents usage of default (no title) label.
				$title = bu_navigation_get_label( $section, '' );
				$href  = get_permalink( $section->ID );
			}
		}

		// Fallback to site title if we're still empty.
		if ( empty( $title ) ) {
			$title = get_bloginfo( 'name' );
			$href  = trailingslashit( get_bloginfo( 'url' ) );
		}

		if ( $title && $href ) {
			$html = sprintf( "<a class=\"content_nav_header\" href=\"%s\">%s</a>\n", esc_attr( $href ), $title );
		}

		return $html;
	}

	/**
	 * Display the content navigation widget, overrides parent method.
	 *
	 * @param array $args Display arguments for WP_Widget.
	 * @param array $instance The settings for the particular instance of the widget.
	 */
	public function widget( $args, $instance ) {
		global $post;

		// Only display navigation widget for supported post types.
		if ( ! in_array( $post->post_type, bu_navigation_supported_post_types() ) ) {
			return;
		}

		extract( $args );

		$title = $this->get_widget_title( $args, $instance );

		// Set widget title.
		// Prepare arguments to bu_navigation_list_pages
		$list_args = array(
			'page_id'      => $post->ID,
			'title_li'     => '',
			'echo'         => 0,
			'container_id' => BU_WIDGET_PAGES_LIST_ID,
			'post_types'   => $post->post_type,
		);

		// Set list arguments based on navigation style.
		if ( array_key_exists( 'navigation_style', $instance ) ) {

			$list_args['style'] = $instance['navigation_style'];

			if ( $instance['navigation_style'] == 'section' ) {
				$list_args['navigate_in_section'] = 1;
				if ( is_404() ) {
					return '';
				}
			} elseif ( $instance['navigation_style'] == 'adaptive' ) {
				add_action( 'bu_navigation_widget_before_list', 'bu_navigation_widget_adaptive_before_list' );
			}
		} else {
			$GLOBALS['bu_navigation_plugin']->log( 'No nav label widget style set!' );
		}

		do_action( 'bu_navigation_widget_before_list' );

		// Fetch markup and display.
		$out = bu_navigation_list_pages( apply_filters( 'widget_bu_pages_args', $list_args ) );

		if ( ! empty( $out ) ) {

			printf( '%s<div id="contentnav">', $before_widget );

			if ( $title ) {
				echo $before_title . $title . $after_title;
			}

			printf( '%s</div>', $out );

			echo $after_widget;

		}
	}

	/**
	 * Save handler for the content navigation widget
	 *
	 * @param array $new_instance updated widget parameters.
	 * @param array $old_instance original widget parameters.
	 */
	public function update( $new_instance, $old_instance ) {

		$instance = $old_instance;
		$instance = wp_parse_args( $instance, $this->defaults );

		$instance['navigation_title']      = ( in_array( $new_instance['navigation_title'], $this->title_options, true ) ) ? $new_instance['navigation_title'] : 'none';
		$instance['navigation_title_text'] = ( 'static' === $instance['navigation_title'] ) ? sanitize_text_field( $new_instance['navigation_title_text'] ) : '';
		$instance['navigation_title_url']  = ( 'static' === $instance['navigation_title'] ) ? sanitize_text_field( $new_instance['navigation_title_url'] ) : '';
		$instance['navigation_style']      = ( in_array( $new_instance['navigation_style'], $this->styles, true ) ) ? $new_instance['navigation_style'] : 'site';

		return $instance;
	}

	/**
	 * Display the content navigation widget form
	 *
	 * @param array $instance the specific widget instance being displayed.
	 */
	public function form( $instance ) {

		$instance = wp_parse_args( $instance, $this->defaults );

		$navigation_title      = ( in_array( $instance['navigation_title'], $this->title_options, true ) ) ? $instance['navigation_title'] : 'none';
		$navigation_title_text = esc_attr( $instance['navigation_title_text'] );
		$navigation_title_url  = esc_attr( $instance['navigation_title_url'] );
		$navigation_style      = ( in_array( $instance['navigation_style'], $this->styles, true ) ) ? $instance['navigation_style'] : 'site';

		include BU_NAV_PLUGIN_DIR . '/templates/widget-form.php';
	}

	/**
	 * Gets the widget title based on the instance options.
	 *
	 * There are 3 return scenarios:
	 * 1- empty string for a widget that doesn't render a title at all
	 * 2- the static title
	 * 3- the section title as returned by section_title()
	 *
	 * This private helper function sorts out those scenarios based on the instance options.
	 *
	 * @param array $args widget args, as passed to WP_Widget::widget.
	 * @param array $instance The settings for the particular instance of the widget.
	 * @return string $title Empty string, plain text title, or anchor tag wrapped title string.
	 */
	private function get_widget_title( $args, $instance ) {
		if ( 'none' === $instance['navigation_title'] ) {
			return '';
		}

		if ( 'static' === $instance['navigation_title'] ) {
			// Do not make a special condition if the navigation_title_text is empty.
			// Empty values for navigation_title_text are valid, it just means the widget doesn't render a title.
			// Wrap the title in an anchor tag if a URL was specified, otherwise just return the title.
			return ( '' !== $instance['navigation_title_url'] ) ? sprintf( '<a class="content_nav_header" href="%s">%s</a>', $instance['navigation_title_url'], $instance['navigation_title_text'] ) : $instance['navigation_title_text'];
		}

		if ( 'section' === $instance['navigation_title'] ) {
			return $this->section_title( $args, $instance );
		}

		// In case the navigation_title option is something else, just return an empty string.
		return '';
	}

}
