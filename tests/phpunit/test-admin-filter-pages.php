<?php

/**
 * Coverage for the BU_Navigation_Admin_Filter_Pages class
 *
 * Runs the Pages list table query through core's wp_edit_posts_query(), so both the
 * default tree view (fields => 'id=>parent') and the sorted views are exercised.
 *
 * @group bu
 * @group bu-navigation
 * @group bu-navigation-admin
 * @group bu-navigation-admin-filter-pages
 */
class Test_BU_Navigation_Admin_Filter_Pages extends WP_UnitTestCase {

	public static $admin;
	public static $pages = array();

	/**
	 * Creates the page fixtures shared by every test.
	 *
	 * Page tree (keys of self::$pages):
	 *  a
	 *  - a1
	 *    - a1a
	 *  - a2
	 *  b
	 *  - b1
	 *  c (no children)
	 */
	public static function wpSetUpBeforeClass( $factory ) {

		self::$admin = $factory->user->create( array( 'role' => 'administrator' ) );

		$tree = array(
			'a'   => 0,
			'a1'  => 'a',
			'a1a' => 'a1',
			'a2'  => 'a',
			'b'   => 0,
			'b1'  => 'b',
			'c'   => 0,
		);

		foreach ( $tree as $key => $parent ) {
			self::$pages[ $key ] = $factory->post->create(
				array(
					'post_type'   => 'page',
					'post_title'  => $key,
					'post_parent' => $parent ? self::$pages[ $parent ] : 0,
				)
			);
		}
	}

	/**
	 * Run the Pages list table query with the section filter active, as wp-admin/edit.php does
	 */
	protected function run_list_table_query( $post_parent, $args = array() ) {

		require_once ABSPATH . 'wp-admin/includes/post.php';
		require_once BU_NAV_PLUGIN_DIR . '/admin/filter-pages.php';

		wp_set_current_user( self::$admin );
		set_current_screen( 'edit-page' );

		new BU_Navigation_Admin_Filter_Pages( 'page', $post_parent, $GLOBALS['bu_navigation_plugin'] );

		$_GET = array_merge( array( 'post_type' => 'page' ), $args );
		wp_edit_posts_query();

		set_current_screen( 'front' );

		return $GLOBALS['wp_query'];
	}

	protected function page_ids( $keys ) {

		$ids = array();
		foreach ( $keys as $key ) {
			$ids[] = self::$pages[ $key ];
		}

		return $ids;
	}

	public function test_tree_view_is_limited_to_section() {

		$query = $this->run_list_table_query( self::$pages['a'] );

		// The default view takes the 'id=>parent' path, which returns before 'the_posts' runs.
		$this->assertSame( 'id=>parent', $query->get( 'fields' ) );

		$this->assertEqualSets( $this->page_ids( array( 'a', 'a1', 'a1a', 'a2' ) ), wp_list_pluck( $query->posts, 'ID' ) );
		$this->assertEquals( 4, $query->found_posts );
	}

	public function test_sorted_view_counts_and_paginates_only_section() {

		add_filter( 'edit_posts_per_page', array( $this, 'two_per_page' ) );

		$query = $this->run_list_table_query(
			self::$pages['a'],
			array(
				'orderby' => 'title',
				'order'   => 'asc',
			)
		);

		$this->assertSame( $this->page_ids( array( 'a', 'a1' ) ), wp_list_pluck( $query->posts, 'ID' ) );
		$this->assertEquals( 4, $query->found_posts );
		$this->assertEquals( 2, $query->max_num_pages );
	}

	public function two_per_page() {
		return 2;
	}

	public function test_section_without_children_lists_only_itself() {

		$query = $this->run_list_table_query( self::$pages['c'] );

		$this->assertEqualSets( $this->page_ids( array( 'c' ) ), wp_list_pluck( $query->posts, 'ID' ) );
	}

	public function test_no_section_is_unfiltered() {

		$query = $this->run_list_table_query( 0 );

		$this->assertEqualSets( array_values( self::$pages ), wp_list_pluck( $query->posts, 'ID' ) );
	}

	public function test_secondary_queries_are_untouched() {

		$this->run_list_table_query( self::$pages['a'] );

		$query = new WP_Query(
			array(
				'post_type'      => 'page',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$this->assertEqualSets( array_values( self::$pages ), $query->posts );
	}
}
