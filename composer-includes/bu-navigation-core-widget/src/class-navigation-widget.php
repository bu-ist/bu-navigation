<?php
/**
 * Alternative content (side) navigation widget
 *
 * @package BU_Navigation
 */

namespace BU\Plugins\Navigation;

// Default element id for list.
define( 'BU_WIDGET_PAGES_LIST_ID', 'contentnavlist' );

/**
 * Widget for displaying navigation.
 */
class Navigation_Widget extends \WP_Widget {

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
	 * @see \BU\Plugins\Navigation\get_nav_label()
	 *
	 * @param WP_Post $post Root post as passed through global to the widget() method.
	 * @param array   $instance widget instance args, as passed to WP_Widget::widget.
	 * @return string HTML fragment with title
	 */
	public function section_title( $post, $instance ) {
		// Format string to deliver the title and href as an HTML fragment.
		$wrapped_title_format = '<a class="content_nav_header" href="%s">%s</a>';

		$section_id = $this->get_title_post_id_for_child( $post, $instance['navigation_style'] );

		// If no title post is returned, use the site title.
		if ( ! $section_id ) {
			return $this->get_site_title( $wrapped_title_format );
		}

		// If there is a title post for this child ("section_id"), then try using it for the title.
		$section = get_post( $section_id );

		// Get title, the second argument prevents usage of default (no title) label.
		$title = get_nav_label( $section, '' );

		// Prevent usage of non-published posts or empty titles, use site title instead.
		if ( ( 'publish' !== $section->post_status ) || empty( $title ) ) {
			return $this->get_site_title( $wrapped_title_format );
		}

		$href = get_permalink( $section->ID );

		return sprintf( $wrapped_title_format, esc_attr( $href ), $title );

	}

	/**
	 * Get site title
	 *
	 * Gets the title and href from the site and returns them formatted for use as a widget title.
	 * It is a protected convenience method to make it easier to give section_title multiple early return options.
	 * There is more than one condition that causes section_title to return the overall site title
	 * as the widget title.
	 *
	 * @since 1.2.22
	 *
	 * @param string $wrapped_title_format An sprintf format string to render the title and href as an html fragement.
	 * @return string Formatted html fragment with the site title and link.
	 */
	protected function get_site_title( $wrapped_title_format ) {
		$title = get_bloginfo( 'name' );
		$href  = trailingslashit( get_bloginfo( 'url' ) );
		return sprintf( $wrapped_title_format, esc_attr( $href ), $title );
	}

	/**
	 * Echos the content navigation widget content, overrides parent method.
	 *
	 * @see bu_navigation_supported_post_types() from library.php
	 * @see \BU\Plugins\Navigation\list_pages()
	 * @see \BU\Plugins\Navigation\adaptive_pages_args()
	 *
	 * @param array $args Display arguments for WP_Widget.
	 * @param array $instance The settings for the particular instance of the widget.
	 */
	public function widget( $args, $instance ) {
		global $post;

		// Only display navigation widget for supported post types.
		if ( ! in_array( $post->post_type, bu_navigation_supported_post_types(), true ) ) {
			return;
		}

		$title = $this->get_widget_title( $post, $instance );

		// Set list arguments based on post type and navigation style.
		$list_args = $this->get_list_args( $post, $instance );

		// Alter args for adaptive mode.
		if ( 'adaptive' === $instance['navigation_style'] ) {
			$list_args = adaptive_pages_args( $list_args );
		}

		// Fetch markup.
		$nav_list_markup = list_pages( $list_args );

		// Only output anything at all if there is existing markup from list_pages.
		if ( empty( $nav_list_markup ) ) {
			return;
		}

		// Assemble the markup into $output, starting with the opening tags.
		$output = sprintf( '%s<div id="contentnav">', $args['before_widget'] );

		// Only add the title markup if the title isn't blank.
		if ( $title ) {
			$output .= $args['before_title'] . $title . $args['after_title'];
		}

		// Add content markup and closing tags.
		$output .= sprintf( '%s</div>', $nav_list_markup ) . $args['after_widget'];

		// Echo assembled widget markup.
		echo wp_kses_post( $output );
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

		include __DIR__ . '/../templates/widget-form.php';
	}

	/**
	 * Gets the widget title based on the instance options.
	 *
	 * There are 3 return scenarios:
	 * 1- empty string for a widget that doesn't render a title at all
	 * 2- the static title
	 * 3- the section title as returned by section_title()
	 *
	 * This helper function sorts out those scenarios based on the instance options.
	 *
	 * @since 1.2.22
	 *
	 * @param WP_Post $post Root post as passed through global to the widget() method.
	 * @param array   $instance The settings for the particular instance of the widget.
	 * @return string $title Empty string, plain text title, or anchor tag wrapped title string.
	 */
	protected function get_widget_title( $post, $instance ) {
		if ( 'none' === $instance['navigation_title'] ) {
			return '';
		}

		if ( 'static' === $instance['navigation_title'] ) {
			// Do not make a special condition if the navigation_title_text is empty.
			// Empty values for navigation_title_text are valid, it just means the widget doesn't render a title.
			$filtered_title = apply_filters( 'widget_title', $instance['navigation_title_text'] );

			// Wrap the title in an anchor tag if a URL was specified, otherwise just return the title.
			return ( '' !== $instance['navigation_title_url'] ) ? sprintf( '<a class="content_nav_header" href="%s">%s</a>', $instance['navigation_title_url'], $filtered_title ) : $filtered_title;
		}

		if ( 'section' === $instance['navigation_title'] ) {
			return $this->section_title( $post, $instance );
		}

		// In case the navigation_title option is something else, just return an empty string.
		return '';
	}

	/**
	 * Get arguments for the page list query.
	 *
	 * A helper method that sets up the list query arguements based on the instance style.
	 * These arguments are structured for the BU\Plugins\Navigation\list_pages() query.
	 *
	 * @since 1.2.22
	 *
	 * @param WP_Post $post The post being rendered.
	 * @param array   $instance The settings for this instance of the widget.
	 *
	 * @return array Arguments for the BU\Plugins\Navigation\list_pages() query
	 */
	protected function get_list_args( $post, $instance ) {

		// Prepare arguments to BU\Plugins\Navigation\list_pages().
		$list_args = array(
			'page_id'      => $post->ID,
			'title_li'     => '',
			'echo'         => 0,
			'container_id' => BU_WIDGET_PAGES_LIST_ID,
			'post_types'   => $post->post_type,
			'style'        => $instance['navigation_style'],
			'widget'       => true,
		);

		// Not sure this check is necessary as there should always be an instance style, but leaving it in to preserve original behavior.
		if ( ! array_key_exists( 'navigation_style', $instance ) ) {
			return $list_args;
		}

		// 'section' style has special handling.
		if ( 'section' === $instance['navigation_style'] ) {
			$list_args['navigate_in_section'] = 1;
			// Not sure why it is necessary to check for a 404 here, but this is the original handling.
			return ( is_404() ) ? '' : $list_args;
		}

		// 'site' navigation_style doesn't require additional handling.
		return $list_args;
	}

	/**
	 * Get adaptive section id.
	 *
	 * Adaptive navigation style uses the title of the grandparent of current post.
	 * Given a post type and the post ids of the current section members, this function
	 * returns the post id of the grandparent post.
	 *
	 * @since 1.2.22
	 *
	 * @see \BU\Plugins\Navigation\get_nav_pages()
	 * @see \BU\Plugins\Navigation\pages_by_parent()
	 *
	 * @param array  $sections Array of post ids.
	 * @param string $post_type Post type of the post being rendered.
	 * @return string Post Id of the grandparent post for the widget title.
	 */
	protected function get_adaptive_section_id( $sections, $post_type ) {
		// Fetch post list, possibly limited to specific sections.
		$page_args       = array(
			'sections'      => $sections,
			'post_types'    => array( $post_type ),
			'include_links' => false,
		);
		$pages           = get_nav_pages( $page_args );
		$pages_by_parent = pages_by_parent( $pages );

		// This looks strange, but is just a way to quickly get the last element of an array in php.
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

		// Return the calculated grandparent post id, or 0 if none found.
		return ( isset( $sections[ $grandparent_offset ] ) ) ? $sections[ $grandparent_offset ] : 0;
	}

	/**
	 * Get the title post id for a given child post.
	 *
	 * Given a post in the hierarchy, returns a post id for a "title" post, based on the current navigation style (mode).
	 *
	 * @since 1.2.22
	 *
	 * @see \BU\Plugins\Navigation\gather_sections()
	 *
	 * @param WP_Post $post The post object as passed to the the widget() method.
	 * @param string  $nav_style The navigation style of the widget (mode).
	 * @return int Either a post id for the title post, or zero if there is no appropriate match.
	 */
	protected function get_title_post_id_for_child( $post, $nav_style ) {
		// Site mode doesn't need a title post, skip gather_section().
		if ( 'site' === $nav_style ) {
			return 0;
		}

		// Gets an array of page ids representing the "section" for a given post.
		$sections = gather_sections( $post->ID, array( 'post_types' => $post->post_type ) );

		if ( 'section' === $nav_style ) {
			// Default to top level post of the section (if we have one).
			return isset( $sections[1] ) ? $sections[1] : 0;
		}

		if ( 'adaptive' === $nav_style ) {
			return $this->get_adaptive_section_id( $sections, $post->post_type );
		}

		// Default to zero for any unknown $nav_style (mode).
		return 0;
	}
}
