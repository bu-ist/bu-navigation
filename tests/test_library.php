<?php

require_once dirname( __FILE__ ) . '/bu_navigation_test.php';

/**
 * Coverage for functions in the BU Navigation library
 *
 * @group bu-navigation
 * @group bu-navigation-library
 */
class WP_Test_Navigation_Library extends BU_Navigation_Test_Case {

	public $posts;

	public function setUp() {

		parent::setUp();

		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure('/%year%/%monthnum%/%day%/%postname%/');

		// Set up custom post type - 'test'
		$args = array( 'hierarchical' => true, 'public' => true );
		register_post_type( 'test', $args );

		// Setup posts
		$posts_json = file_get_contents( dirname(__FILE__) . '/data/test_pages.json');
		$posts = json_decode($posts_json, true);
		$this->load_test_posts( $posts );

	}

	/**
	 *	Covers bu_navigation_supported_post_types()
	 */
	public function test_bu_navigation_supported_post_types() {

		// Post Types without Link pages + test
		$exp_post_types = array( "page" => "page", "test" => "test" );
		$post_types = bu_navigation_supported_post_types();
		$this->assertEquals( $exp_post_types, $post_types );

		// Add "Link" to expected post types
		$exp_post_types["bu_link"] = "bu_link";
		$post_types = bu_navigation_supported_post_types( true );
		$this->assertEquals( $exp_post_types, $post_types );

	}

	/**
	 *	Covers bu_navigation_load_sections()
	 */
	public function test_bu_navigation_load_sections() {

		// Load all values and split into section and pages
		$returned = bu_navigation_load_sections();
		$sections = $returned['sections'];
		$pages 	  = $returned['pages'];

		/*
		*	Test Sections
		*/

		// These are the expected section result arrays
		$section_parent = array(
				(string)$this->posts['parent'],
				(string)$this->posts['parent_two'],
				(string)$this->posts['hidden'],
				(string)$this->posts['edit'],
				(string)$this->posts['google'],
				(string)$this->posts['last_page']
			);

		$section_child = array(
				(string)$this->posts['child']
		);

		$section_grandchildren = array(
				(string)$this->posts['grandchild_one'],
				(string)$this->posts['grandchild_two']
		);

		$section_greatgrandchildren = array(
				(string)$this->posts['greatgrandchild']
				);

		// Sort through the sections and compare them to the
		// expected results. If each section doesn't match one
		// of the expected, $good_section = false
		$good_sections = false;
		foreach( $sections as $section ) {

			$diff1 = array_diff( $section, $section_parent );
			$diff2 = array_diff( $section, $section_child );
			$diff3 = array_diff( $section, $section_grandchildren );
			$diff4 = array_diff( $section, $section_greatgrandchildren );

			if ( $diff1 == false or $diff2 == false or $diff3 == false or $diff4 == false ) {
				$good_sections = true;
			} else {
				$good_sections = false;
			}

		}

		// See if all of the sections match
		$this->assertTrue( $good_sections );

		/*
		*	Test Pages
		*/

		$good_pages = false;
		foreach( $pages as $key => $value ) {

			// If the Child Page, value should be parent
			if( $key == $this->posts['child'] ) {
				if ( $value != $this->posts['parent'] ) $good_pages = true;
				else $good_pages = false;
			}

			// If Grandchild page, value should be child
			elseif( $key == $this->posts['grandchild_one'] or
					$key == $this->posts['grandchild_two']) {
				if( $value == $this->posts['child'] ) $good_pages = true;
				else $good_pages = false;
			}

			// If Greatgrandchild page, value should be grandchild_one
			elseif( $key == $this->posts['greatgrandchild'] ) {
				if( $value == $this->posts['grandchild_one'] ) $good_pages = true;
				else $good_pages = false;
			}

			// Else value should equal 0
			else {
				if( $value == 0 ) $good_pages = true;
				else $good_pages = false;
			}
		}

		// See if all the pages match
		$this->assertTrue( $good_pages );

		/*
		*	Test Post Types
		*/

		// Test that when the 'test' post type is included, it appears
		// in the pages section but not when it isn't included.
		$returned_w_post_type = bu_navigation_load_sections( array( 'test' ) );
		$pages_w_post_type = $returned_w_post_type['pages'];

		$good_posttype = false;

		// If the 'test' page isn't in orignial pages but is in new pages
		if ( !isset( $pages[$this->posts['test']]) and
			  isset( $pages_w_post_type[$this->posts['test']]) ) {
				  $good_posttype = true;
			  }

		$this->assertTrue( $good_posttype );
	}

	/**
	 *	Covers bu_navigation_gather_childsections()
	 */
	public function test_bu_navigation_gather_childsections() {

		$all_sections = bu_navigation_load_sections();
		$parent = $this->posts['parent'];
		$child = $this->posts['child'];
		$grandchild_one = $this->posts['grandchild_one'];
		$good_childsections = false;

		// $parent should return child and grandchild_one as the only sections
		$child_sections = bu_navigation_gather_childsections( $parent, $all_sections['sections'] );
		if( count( $child_sections ) == 2 and
			$child_sections[0] == $child  and
			$child_sections[1] == $grandchild_one )
			$good_childsections = true;

		$this->assertTrue( $good_childsections );

		// Test Depth Functionality
		$depth_one = bu_navigation_gather_childsections( $parent, $all_sections['sections'], 1 );
		$depth_two = bu_navigation_gather_childsections( $parent, $all_sections['sections'], 2 );

		// Expected results
		$depth_one_exp = array( $child );
		$depth_two_exp = array( $child, $grandchild_one );

		// Test actual and expected are equal
		$this->assertEquals( $depth_one, $depth_one_exp );
		$this->assertEquals( $depth_two, $depth_two_exp );

	}

	/**
	 * Covers bu_navigation_get_page_depth()
	 */
	public function test_bu_navigation_get_page_depth() {

		$parent = $this->posts['parent'];
		$child = $this->posts['child'];
		$grandchild_one = $this->posts['grandchild_one'];
		$greatgrandchild = $this->posts['greatgrandchild'];

		// Get depth results
		$grandchild_depth_results = bu_navigation_get_page_depth( $grandchild_one );
		$greatgrandchild_depth_results = bu_navigation_get_page_depth( $greatgrandchild );

		// Test depth functionality
		$this->assertEquals( $grandchild_depth_results, 3 );
		$this->assertEquals( $greatgrandchild_depth_results, 4 );

		/* Selective Section Functionality */

		$test_grandchild = $this->posts['test_grandchild'];
		$selective_section = bu_navigation_load_sections('test');
		$selective_depth_results =  bu_navigation_get_page_depth( $test_grandchild, $selective_section );

		// Test selective section functionality
		$this->assertEquals( $selective_depth_results, 3 );

	}

	/**
	 *  Covers bu_navigation_gather_sections()
	 */
	public function test_bu_navigation_gather_sections() {

		$parent = $this->posts['parent'];
		$child = $this->posts['child'];
		$grandchild_one = $this->posts['grandchild_one'];
		$greatgrandchild = $this->posts['greatgrandchild'];

		/* Base Functionality */

		$grandchild_expected = array(
			strval(0),
			strval($parent),
			strval($child),
			$grandchild_one
		);

		$child_expected = array(
			strval(0),
			strval($parent),
			$child
		);

		// Gather test results
		$grandchild_results = bu_navigation_gather_sections( $grandchild_one, NULL, NULL );
		$greatgrandchild_results = bu_navigation_gather_sections( $greatgrandchild, NULL, NULL );
		$child_results = bu_navigation_gather_sections( $child, NULL, NULL );

		// Test base functionality
		$this->assertEquals( $child_results, $child_expected );
		$this->assertEquals( $grandchild_results, $grandchild_expected );

		/* Down Functionality (Direction) */

		// Expected down results
		$child_down_expected = array( strval( $grandchild_one ), strval( $child ) );

		$parent_down_expected = $child_down_expected;
		array_push( $parent_down_expected, strval( $parent ));

		// Get Down results
		$args = array( 'direction' => 'down' );
		$child_down_results = bu_navigation_gather_sections( $child, $args, NULL );
		$parent_down_results = bu_navigation_gather_sections( $parent, $args, NULL );

		// Test down functionality
		$this->assertEquals( $child_down_results, $child_down_expected );
		$this->assertEquals( $parent_down_results, $parent_down_expected );

		/* Depth Functionality */

		// Expected depth results
		$parent_depth_expected = array( strval( $child ), strval( $parent ) );

		// Get depth results
		$args = array( 'depth' => '1', 'direction' => 'down' );
		$parent_depth_results = bu_navigation_gather_sections( $parent, $args, NULL );

		// Test depth functionality
		$this->assertEquals( $parent_depth_results, $parent_depth_expected );

		/* Custom Post Type Functionality */

		// Expected custom post type results
		$test = $this->posts['test'];
		$test_child = $this->posts['test_child'];
		$test_grandchild = $this->posts['test_grandchild'];

		$grandchild_posttype_expected = array( strval(0), strval($test), strval( $test_child ));

		// Get custom post type results
		$args = array( 'post_types' => array( 'test' ) );
		$grandchild_posttype_results = bu_navigation_gather_sections( $test_grandchild, $args, NULL );

		// Test custom post type functionality
		$this->assertEquals( $grandchild_posttype_results, $grandchild_posttype_expected );

		/* Selective Sections Functionality */

		// Section results
		// By giving the function only the pages from test and the seleced posts it should
		// yeild the same results if we had give it the post_type parameter (previous assertion)
		$selective_section = bu_navigation_load_sections('test');
		$selective_section_results = bu_navigation_gather_sections( $test_grandchild, NULL, $selective_section );

		// Test selective section functionality
		$this->assertEquals( $selective_section_results, $grandchild_posttype_expected );

	}

	/**
	 *  Covers bu_navigation_pull_page()
	 */
	public function test_bu_navigation_pull_page() {

		$child = $this->posts['child'];
		$parent = $this->posts['parent'];

		$sections = bu_navigation_load_sections();
		$pages = $sections['pages'];

		$child_results = bu_navigation_pull_page( $child, $pages );
		$parent_results = bu_navigation_pull_page( $parent, $pages );

		$this->assertEquals( $child_results, $parent );
		$this->assertEquals( $parent_results, "0" );

	}

	/**
	 *  Covers bu_navigation_get_urls()
	 */
	public function test_bu_navigation_get_urls() {

		// Get test page ids
		$parent 		 = $this->posts['parent'];
		$grandchild_one  = $this->posts['grandchild_one'];
		$test_child 	 = $this->posts['test_child'];

		// Get all pages
		$args = array( 'post_types' => array( 'page', 'bu_link', 'test' ));
		$pages  = bu_navigation_get_pages( $args );

		// Get the base url
		$base_url = trailingslashit( get_option( 'home' ) );


		// Remove current url
		unset( $pages[$parent]->url );
		unset( $pages[$grandchild_one]->url );
		unset( $pages[$test_child]->url );

		// Use get_urls to get the new url
		$pages = bu_navigation_get_urls( $pages );

		// Test to make sure the url is the expected one
		$this->assertEquals( get_permalink($parent), $pages[$parent]->url );
		$this->assertEquals( get_permalink($grandchild_one), $pages[$grandchild_one]->url );
		$this->assertEquals( get_permalink($test_child), $pages[$test_child]->url );

	}

	/**
	 *  Covers bu_navigation_get_pages()
	 */
	public function test_bu_navigation_get_pages() {

		/*
		* 	Test No Pages Return
		*/

		// $pages = bu_navigation_get_pages();
		// $this->assertEquals( $pages, false );

		/*
		* 	Test Custom Post Type argument
		*/

		// Don't include custom post type in args and check it isn't returned
		$pages = bu_navigation_get_pages();
		$custom_post_type = 'test';
		$custom_post_type_exists = false;
		foreach ( $pages as $page ) {
			if ( $page->post_type == $custom_post_type ) {
				$custom_post_type_exists = true;
			}
		}

		$this->assertArrayNotHasKey( $this->posts['test_child'], $pages );	// look for custom post types posts explicitly
		$this->assertFalse( $custom_post_type_exists );	// assertFalse vs. assertEquals( $arg, false )

		// Add the custom post type to args and make sure it is returned
		$args = array( 'post_types' => array( 'page', 'bu_link', 'test' ));
		$pages = bu_navigation_get_pages( $args );
		$custom_post_type = 'test';
		$custom_post_type_exists = false;
		foreach ( $pages as $page ) {
			if ( $page->post_type == $custom_post_type ) {
				$custom_post_type_exists = true;
			}
		}

		$this->assertArrayHasKey( $this->posts['test_child'], $pages );	// look for custom post types posts explicitly
		$this->assertTrue( $custom_post_type_exists );

		/*
		* 	Test Max Items Argument
		*/

		unset( $args );
		$args = array( 'max_items' => 2 );
		$pages = bu_navigation_get_pages( $args );
		$this->assertEquals( 2, count( $pages ));

		/*
		*	Test for Page status - Draft + Pending
		*/

		unset( $args );
		$args = array( 'post_status' => 'draft' );
		$pages = bu_navigation_get_pages( $args );
		$draft_page = $this->posts['draft'];
		$this->assertTrue( array_key_exists( $draft_page, $pages ));

		unset( $args );
		$args = array( 'post_status' => 'pending' );
		$pages = bu_navigation_get_pages( $args );
		$pending_page = $this->posts['pending'];
		$this->assertTrue( array_key_exists( $pending_page, $pages ));

		// @todo multiple statuses

		/*
		*	Test Gathering Specific Sections
		*/

		$parent = $this->posts['parent'];
		$child = $this->posts['child'];

		// Get results of $parent section
		unset( $args );
		$args = array( 'sections' => array( $parent ));
		$pages = bu_navigation_get_pages( $args );

		$expected_parent_section_results = array( (string)$child );

		// Gather results
		$pages_id = array();
		foreach( $pages as $page ) array_push( $pages_id, $page->ID );

		// Test only the $parent section pages were returned
		$this->assertEquals( $pages_id, $expected_parent_section_results );

		// Test using $child as section
		$grandchild_one = $this->posts['grandchild_one'];
		$grandchild_two = $this->posts['grandchild_two'];

		// Get results
		unset( $args );
		$args = array( 'sections' => array( $child ));
		$pages = bu_navigation_get_pages( $args );

		$expected_child_section_results = array(
			(string)$grandchild_one,
			(string)$grandchild_two
			);

		$pages_id = array();
		foreach( $pages as $page ) array_push( $pages_id, $page->ID );

		// Test only the $child section pages were returned
		$this->assertEquals( $pages_id, $expected_child_section_results );

		/*
		*	Test Gathering Specific Pages
		*/

		unset( $args );
		$args = array( 'pages' => array( $parent, $child ));
		$pages = bu_navigation_get_pages( $args );

		$gather_test = false;
		if( count( $pages ) == 2 and
			array_key_exists( $parent, $pages ) and
			array_key_exists( $child, $pages ))
			$gather_test = true;

		$this->assertTrue( $gather_test );

		/*
		*	Test Sorting Functionality
		*/

		// Create and order three pages using 'order' as a custom post type
		$cpt_args = array( 'hierarchical' => true );
		register_post_type( 'order', $cpt_args );

		// Create pages using the 'order' post type
		$page_args = array( 'post_type' => 'order' , 'menu_order' => '2' );
		$last_order = $this->factory->post->create( $page_args );

		$page_args = array( 'post_type' => 'order' , 'menu_order' => '1' );
		$middle_order = $this->factory->post->create( $page_args );

		$page_args = array( 'post_type' => 'order' , 'menu_order' => '0' );
		$first_order = $this->factory->post->create( $page_args );

		// Construct Expected Order
		$expected_order = array( $first_order, $middle_order, $last_order );

		// Get Pages
		$args = array( 'post_types' => array( 'order' ));
		$pages = bu_navigation_get_pages( $args );

		// Get actual page order
		$actual_order = array();
		foreach( $pages as $page ) array_push( $actual_order, $page->ID );

		// Test order
		$this->assertEquals( $actual_order, $expected_order );

		/*
		*	Test Supress URLS
		*/

		$pages = bu_navigation_get_pages();
		$has_url = false;
		foreach ( $pages as $page ) if( isset( $page->url )) $has_url = true;

		// Test to make sure the pages do have urls
		$this->assertTrue( $has_url );

		// Suppress URLs
		unset( $args );
		$args = array( 'suppress_urls' => true );
		$pages = bu_navigation_get_pages( $args );

		$has_url = false;
		foreach ( $pages as $page ) if( isset( $page->URL )) $has_url = true;

		// Test to make sure the pages don't have urls
		$this->assertFalse( $has_url );

		/*
		*	Test Supress Filters
		*/

		// With the filter, 'post_filter_example' -> 'test-filter' exists in each page
		// Without 'post_filter_example' doesn't exist
		add_action( 'bu_navigation_filter_pages', 'bu_navigation_test_filter' );
		$pages = bu_navigation_get_pages();

		$test_filter_present = false;
		foreach( $pages as $page ) if( isset( $page->post_filter_example ) ) $test_filter_present = true;

		// The test filter should be present
		$this->assertTrue( $test_filter_present );

		unset( $args );
		$args = array( 'suppress_filter_pages' => true );
		$pages = bu_navigation_get_pages( $args );

		$test_filter_present = false;
		foreach( $pages as $page ) if( isset( $page->post_filter_example )) $test_filter_present = true;

		// The test filter should not be present
		$this->assertFalse( $test_filter_present );

	}

	/**
	 * Covers bu_navigation_pages_by_parent()
	 * @todo finish
	 */
	public function test_bu_navigation_pages_by_parent() {

		$parent = $this->posts['parent'];
		$child = $this->posts['child'];

		$args = array( 'post_types' => array( 'test' ));
		$pages_array = bu_navigation_get_pages( $args );

		$pages = bu_navigation_pages_by_parent( $pages_array );

/*
		foreach( $pages as $page ) {
			foreach ( $page as $p ) {
				$p->post_title . "\n";

			}
		}
*/

		// var_dump( $pages );

		$this->markTestIncomplete();

	}

	/**
	 * Covers bu_navigation_pages_by_parent_menu_sort()
	 * @todo implement
	 */
	public function test_bu_navigation_pages_by_parent_menu_sort() {
		$this->markTestIncomplete();
	}

	/**
	 * Covers bu_navigation_pages_by_parent_menu_sort_cb()
	 * @todo implement
	 */
	public function test_bu_navigation_pages_by_parent_menu_sort_cb() {
		$this->markTestIncomplete();
	}

	/**
	 *  Covers bu_navigation_format_page()
	 */
	public function test_bu_navigation_format_page() {

		/*
		*	Test Base Functionality
		*/

		// Get parent page details
		$parent = $this->posts['parent'];
		$pages = bu_navigation_get_pages();
		$page = $pages[ $parent ];

		// Expected formatted results
		$title = $page->post_title;
		$url = $page->url;
		$expected_formatted_page = '<li class="page_item page-item-' . $parent . '">' . "\n" . '<a title="' . $title . '" href="' . $url . '">' . $title . '</a>' . "\n" . ' </li>' . "\n";

		// Format $parent Page
		$formatted_page = bu_navigation_format_page( $page );

		// Test the page formating worked
		$this->assertEquals( $formatted_page, $expected_formatted_page );

		/*
		*	Test Item Tag Functionaltiy
		*/

		$args = array( 'item_tag' => 'td' );
		$formatted_page = bu_navigation_format_page( $page, $args );
		$expected_formatted_page = '<td class="page_item page-item-' . $parent . '">' . "\n" . '<a title="' . $title . '" href="' . $url . '">' . $title . '</a>' . "\n" . ' </td>' . "\n";

		$this->assertEquals( $formatted_page, $expected_formatted_page );

		/*
		*	Test Anchor Class Functionaltiy
		*/

		$args = array( 'anchor_class' => 'test_class' );
		$formatted_page = bu_navigation_format_page( $page, $args );
		$expected_formatted_page = '<li class="page_item page-item-' . $parent . '">' . "\n" . '<a title="' . $title . '" href="' . $url . '" class="test_class">' . $title . '</a>' . "\n" . ' </li>' . "\n";

		$this->assertEquals( $formatted_page, $expected_formatted_page );

		/*
		*	Test Depth Class Functionaltiy
		*/

		$args = array( 'depth' => 5 );
		$formatted_page = bu_navigation_format_page( $page, $args );
		$expected_formatted_page = '<li class="page_item page-item-' . $parent . '">' . "\n" . '<a title="' . $title . '" href="' . $url . '" class="level_5">' . $title . '</a>' . "\n" . ' </li>' . "\n";

		$this->assertEquals( $formatted_page, $expected_formatted_page );

		/*
		*	Test Section ID Functionaltiy
		*/

		$child = $this->posts['child'];
		$page_child = $pages[ $child ];
		$title_child = $page_child->post_title;
		$url_child = $page_child->url;

		$args = array( 'section_ids' => array( $parent, $child ));
		$formatted_page = bu_navigation_format_page( $page_child, $args );
		$expected_formatted_page = '<li class="page_item page-item-' . $child . ' has_children">' . "\n" . '<a title="' . $title_child . '" href="' . $url_child . '">' . $title_child . '</a>' . "\n" . ' </li>' . "\n";

		$this->assertEquals( $formatted_page, $expected_formatted_page );

		/*
		*	Test Position Functionaltiy
		*/

		// First Item
		$args = array( 'position' => 1, 'siblings' => 3 );
		$formatted_page = bu_navigation_format_page( $page, $args );
		$expected_formatted_page = '<li class="page_item page-item-' . $parent . ' first_item">' . "\n" . '<a title="' . $title . '" href="' . $url . '">' . $title . '</a>' . "\n" . ' </li>' . "\n";

		$this->assertEquals( $formatted_page, $expected_formatted_page );

		// Last Item
		$args = array( 'position' => 3, 'siblings' => 3 );
		$formatted_page = bu_navigation_format_page( $page, $args );
		$expected_formatted_page = '<li class="page_item page-item-' . $parent . ' last_item">' . "\n" . '<a title="' . $title . '" href="' . $url . '">' . $title . '</a>' . "\n" . ' </li>' . "\n";

		$this->assertEquals( $formatted_page, $expected_formatted_page );

		/*
		*	Test HTML Functionaltiy
		*/

		$args = array( 'html' => 'some html' );
		$formatted_page = bu_navigation_format_page( $page, $args );
		$expected_formatted_page = '<li class="page_item page-item-' . $parent . '">' . "\n" . '<a title="' . $title . '" href="' . $url . '">' . $title . '</a>' . "\n" . ' some html</li>' . "\n";

		$this->assertEquals( $formatted_page, $expected_formatted_page );

		/*
		*	Test ID Functionaltiy
		*/

		$args = array( 'item_id' => 'test_item_id' );
		$formatted_page = bu_navigation_format_page( $page, $args );
		$expected_formatted_page = '<li id="test_item_id" class="page_item page-item-' . $parent . '">' . "\n" . '<a title="' . $title . '" href="' . $url . '">' . $title . '</a>' . "\n" . ' </li>' . "\n";

		$this->assertEquals( $formatted_page, $expected_formatted_page );

	}

	/**
	 * Covers bu_navigation_filter_item_attrs()
	 * @todo implement
	 */
	public function test_bu_navigation_filter_item_attrs() {
		$this->markTestIncomplete();
	}

	/**
	 * Covers bu_navigation_filter_item_active_page()
	 * @todo implement
	 */
	public function test_bu_navigation_filter_item_active_page() {
		$this->markTestIncomplete();
	}

	/**
	 * Covers bu_navigation_list_section()
	 * @todo implement
	 */
	public function test_bu_navigation_list_section() {
		$parent = $this->posts['parent'];
		$all_sections = bu_navigation_load_sections();
		$sections = $all_sections['sections'];

		$this->markTestIncomplete();

		/*
		var_dump( $sections[$parent] );
		var_dump( bu_navigation_list_section( $parent, $sections[ $parent ]));
		*/
	}


}


/*
*	Test Filter to work with test_bu_navigation_get_pages
*	Filter will add 'test' to the end of each 'post_name'
*/
function bu_navigation_test_filter( $pages ) {

	foreach ( $pages as $page ) {
		$page->post_filter_example .= 'test-filter';
	}

	return $pages;
}
