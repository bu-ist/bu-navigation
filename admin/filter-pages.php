<?php
/*
 @todo this doesn't deserve a whole file/class -- move to admin.php
 */

class BU_Navigation_Admin_Filter_Pages {

	public $post_type;
	public $post_parent;
	private $plugin;

	public function __construct( $post_type, $post_parent, $plugin ) {

		$this->post_type = $post_type;
		$this->post_parent = $post_parent;
		$this->plugin = $plugin;

		$this->register_hooks();

	}

	public function register_hooks() {

		add_action( 'restrict_manage_posts', array( $this, 'render_dropdown' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_query' ) );

	}

	public function render_dropdown() {

		bu_navigation_page_parent_dropdown( $this->post_type, $this->post_parent );

	}

	/**
	 * Limit the list table query to the selected section (the section page and everything below it)
	 *
	 * This has to happen in the query rather than on 'the_posts': hierarchical list tables query with
	 * fields => 'id=>parent', which returns before 'the_posts' runs, and trimming the results afterwards
	 * leaves the item count and pagination counting the whole post type.
	 *
	 * @param WP_Query $query The query being prepared.
	 */
	public function filter_query( $query ) {

		if ( ! $this->post_parent || ! $query->is_main_query() || $query->get( 'post_type' ) !== $this->post_type ) {
			return;
		}

		$section_args = array(
			'direction'  => 'down',
			'depth'      => 0,
			'post_types' => array( $this->post_type ),
		);
		$sections     = bu_navigation_gather_sections( $this->post_parent, $section_args );
		$all_sections = bu_navigation_load_sections( array( $this->post_type ) );

		// The root is always included, so post__in is never empty (an empty post__in means "no restriction").
		$ids = array( $this->post_parent );
		foreach ( $sections as $section ) {
			$ids = array_merge( $ids, $all_sections['sections'][ $section ] );
		}

		$query->set( 'post__in', array_unique( array_map( 'intval', $ids ) ) );

	}

}
