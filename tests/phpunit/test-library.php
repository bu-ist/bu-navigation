<?php

/**
 * Coverage for functions in the BU Navigation library
 *
 * @group bu-navigation
 * @group bu-navigation-library
 */
class Test_BU_Navigation_Library extends BU_Navigation_UnitTestCase {

	public $posts;

	public function setUp() {

		parent::setUp();

		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure('/%year%/%monthnum%/%day%/%postname%/');

		// Set up custom post type - 'test'
		$args = array( 'hierarchical' => true, 'public' => true );
		register_post_type( 'test', $args );

		// Setup posts
		$this->posts = $this->load_fixture( 'posts', 'lib_posts.json' );

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
	 * @see https://github.com/bu-ist/bu-navigation/issues/5
	 * @group bu-navigation-issues
	 */
	public function test_bu_navigation_get_urls_without_ancestors() {

		// Fetch all posts in "parent" section
		$parent = $this->posts['parent'];
		$sections = bu_navigation_gather_sections( $parent, array( 'direction' => 'down' ) );
		$args = array( 'sections' => $sections, 'post_types' => array( 'page', 'bu_link', 'test' ));
		$pages  = bu_navigation_get_pages( $args );

		foreach ( $pages as $page ) {
			$this->assertEquals( get_permalink( $page->ID ), $page->url );
		}
	}

	public function test_bu_navigation_get_page_link() {
		$grandchild = $this->posts['grandchild_one'];
		$grandchild = get_post( $grandchild );

		// With ancestors
		$ancestors = bu_navigation_gather_sections( $grandchild->ID, array( 'direction' => 'up' ) );
		$this->assertEquals( get_page_link( $grandchild ), bu_navigation_get_page_link( $grandchild, $ancestors ) );

		// Without ancestors
		$this->assertEquals( get_page_link( $grandchild ), bu_navigation_get_page_link( $grandchild ) );

		// Page on front
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $grandchild->ID );
		$this->assertEquals( get_page_link( $grandchild ), bu_navigation_get_page_link( $grandchild ) );
	}

	public function test_bu_navigation_get_page_link_unpublished() {
		$public = $this->factory->post->create(array('post_type' => 'page', 'post_status' => 'publish'));
		$draft_child = $this->factory->post->create(array('post_type' => 'page', 'post_status' => 'draft', 'post_parent' => $public));
		$pending_child = $this->factory->post->create(array('post_type' => 'page', 'post_status' => 'pending', 'post_parent' => $public));
		$draft = $this->factory->post->create(array('post_type' => 'page', 'post_status' => 'draft'));
		$public_draft_child = $this->factory->post->create(array('post_type' => 'page', 'post_status' => 'publish', 'post_parent' => $draft));
		$pending = $this->factory->post->create(array('post_type' => 'page', 'post_status' => 'pending'));
		$public_pending_child = $this->factory->post->create(array('post_type' => 'page', 'post_status' => 'publish', 'post_parent' => $pending));

		// Our functions require post objects
		$draft = get_post( $draft );
		$draft_child = get_post( $draft_child );
		$pending = get_post( $draft_child );
		$pending_child = get_post( $pending_child );
		$public_draft_child = get_post( $public_draft_child );
		$public_pending_child = get_post( $public_pending_child );

		// Root unpublished
		$this->assertEquals( get_page_link( $draft ), bu_navigation_get_page_link( $draft ) );
		$this->assertEquals( get_page_link( $pending ), bu_navigation_get_page_link( $pending ) );

		// Public parent, unpublished children
		$this->assertEquals( get_page_link( $draft_child ), bu_navigation_get_page_link( $draft_child ) );
		$this->assertEquals( get_page_link( $pending_child ), bu_navigation_get_page_link( $pending_child ) );

		// Draft parent, public children
		$this->assertEquals( get_page_link( $public_draft_child ), bu_navigation_get_page_link( $public_draft_child ) );
		$this->assertEquals( get_page_link( $public_pending_child ), bu_navigation_get_page_link( $public_pending_child ) );

		// Sample permalinks for unpublished pages
		$this->assertEquals( get_page_link( $draft, false, true ), bu_navigation_get_page_link( $draft, array(), true ) );
		$this->assertEquals( get_page_link( $draft_child, false, true ), bu_navigation_get_page_link( $draft_child, array(), true ) );
		$this->assertEquals( get_page_link( $pending, false, true ), bu_navigation_get_page_link( $pending, array(), true ) );
		$this->assertEquals( get_page_link( $pending_child, false, true ), bu_navigation_get_page_link( $pending_child, array(), true ) );
	}

	public function test_bu_navigation_get_page_link_no_permalinks() {
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '' );
		delete_option( 'rewrite_rules' );

		// Re-run previous tests, but without pretty permalinks
		$this->test_bu_navigation_get_page_link();
		$this->test_bu_navigation_get_page_link_unpublished();
	}

	public function test_bu_navigation_get_post_link() {
		global $wp_rewrite;

		// Non-Page Hierarchical Post Type 'Default Permalinks' do not work for child posts prior to 4.0
		// @see https://core.trac.wordpress.org/ticket/29615
		if ( $wp_rewrite->using_permalinks() || version_compare( $GLOBALS['wp_version'], '4.0', '>' ) ) {
			$grandchild = $this->posts['test_grandchild'];
			$grandchild = get_post( $grandchild );

			// With ancestors
			$ancestors = bu_navigation_gather_sections( $grandchild->ID, array( 'direction' => 'up' ) );
			$this->assertEquals( get_post_permalink( $grandchild ), bu_navigation_get_post_link( $grandchild, $ancestors ) );

			// Without ancestors
			$this->assertEquals( get_post_permalink( $grandchild ), bu_navigation_get_post_link( $grandchild ) );
		}
	}

	public function test_bu_navigation_get_post_link_unpublished() {
		global $wp_rewrite;

		$public = $this->factory->post->create(array('post_type' => 'test', 'post_status' => 'publish'));
		$draft_child = $this->factory->post->create(array('post_type' => 'test', 'post_status' => 'draft', 'post_parent' => $public));
		$pending_child = $this->factory->post->create(array('post_type' => 'test', 'post_status' => 'pending', 'post_parent' => $public));
		$draft = $this->factory->post->create(array('post_type' => 'test', 'post_status' => 'draft'));
		$public_draft_child = $this->factory->post->create(array('post_type' => 'test', 'post_status' => 'publish', 'post_parent' => $draft));
		$pending = $this->factory->post->create(array('post_type' => 'test', 'post_status' => 'pending'));
		$public_pending_child = $this->factory->post->create(array('post_type' => 'test', 'post_status' => 'publish', 'post_parent' => $pending));

		// Our functions require post objects
		$draft = get_post( $draft );
		$draft_child = get_post( $draft_child );
		$pending = get_post( $draft_child );
		$pending_child = get_post( $pending_child );
		$public_draft_child = get_post( $public_draft_child );
		$public_pending_child = get_post( $public_pending_child );

		// Root unpublished
		$this->assertEquals( get_post_permalink( $draft ), bu_navigation_get_post_link( $draft ) );
		$this->assertEquals( get_post_permalink( $pending ), bu_navigation_get_post_link( $pending ) );

		// Public parent, unpublished children
		$this->assertEquals( get_post_permalink( $draft_child ), bu_navigation_get_post_link( $draft_child ) );
		$this->assertEquals( get_post_permalink( $pending_child ), bu_navigation_get_post_link( $pending_child ) );

		// Non-Page Hierarchical Post Type 'Default Permalinks' do not work for child posts prior to 4.0
		// @see https://core.trac.wordpress.org/ticket/29615
		if ( $wp_rewrite->using_permalinks() || version_compare( $GLOBALS['wp_version'], '4.0', '>' ) ) {
			// Draft parent, public children
			$this->assertEquals( get_post_permalink( $public_draft_child ), bu_navigation_get_post_link( $public_draft_child ) );
			$this->assertEquals( get_post_permalink( $public_pending_child ), bu_navigation_get_post_link( $public_pending_child ) );
		}

		// Sample permalinks for unpublished posts
		$this->assertEquals( get_post_permalink( $draft, false, true ), bu_navigation_get_post_link( $draft, array(), true ) );
		$this->assertEquals( get_post_permalink( $draft_child, false, true ), bu_navigation_get_post_link( $draft_child, array(), true ) );
		$this->assertEquals( get_post_permalink( $pending, false, true ), bu_navigation_get_post_link( $pending, array(), true ) );
		$this->assertEquals( get_post_permalink( $pending_child, false, true ), bu_navigation_get_post_link( $pending_child, array(), true ) );
	}

	public function test_bu_navigation_get_post_link_no_permalinks() {
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '' );
		delete_option( 'rewrite_rules' );

		// Re-run previous tests, but without pretty permalinks
		$this->test_bu_navigation_get_post_link();
		$this->test_bu_navigation_get_post_link_unpublished();
	}

	public function test_bu_navigation_get_post_link_query_var() {
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '' );
		delete_option( 'rewrite_rules' );

		register_post_type( 'cpt_one', array( 'public' => true, 'hierarchical' => true, 'query_var' => false ) );
		register_post_type( 'cpt_two', array( 'public' => true, 'hierarchical' => true, 'query_var' => true ) );
		register_post_type( 'cpt_three', array( 'public' => true, 'hierarchical' => true, 'query_var' => 'foo' ) );

		$cpt_one = $this->factory->post->create(array('post_type' => 'cpt_one', 'post_status' => 'publish'));
		$cpt_two = $this->factory->post->create(array('post_type' => 'cpt_two', 'post_status' => 'publish'));
		$cpt_three = $this->factory->post->create(array('post_type' => 'cpt_three', 'post_status' => 'publish'));

		$cpt_one = get_post( $cpt_one );
		$cpt_two = get_post( $cpt_two );
		$cpt_three = get_post( $cpt_three );

		$this->assertEquals( get_post_permalink( $cpt_one ), bu_navigation_get_post_link( $cpt_one ) );
		$this->assertEquals( get_post_permalink( $cpt_two ), bu_navigation_get_post_link( $cpt_two ) );
		$this->assertEquals( get_post_permalink( $cpt_three ), bu_navigation_get_post_link( $cpt_three ) );
	}

	/**
	 *  Covers bu_navigation_get_pages()
	 */
	public function test_bu_navigation_get_pages() {

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
		add_filter( 'bu_navigation_filter_pages', 'bu_navigation_test_filter' );
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

		$parent 		 = $this->posts['parent'];
		$child 			 = $this->posts['child'];
		$parent_two 	 = $this->posts['parent_two'];
		$edit 			 = $this->posts['edit'];
		$google 		 = $this->posts['google'];
		$last_page		 = $this->posts['last_page'];
		$grandchild_one  = $this->posts['grandchild_one'];
		$grandchild_two  = $this->posts['grandchild_two'];
		$greatgrandchild = $this->posts['greatgrandchild'];

		$all_pages = bu_navigation_get_pages();

		$parent_value 		= array( $all_pages[ $child ] );
		$child_value 		= array(
								$all_pages[ $grandchild_one ],
								$all_pages[ $grandchild_two ],
								);
		$grandchild_value 	= array( $all_pages[ $greatgrandchild ]);

		$pages_results = bu_navigation_pages_by_parent( $all_pages );

		// Check all of the key values exist
		$this->assertArrayHasKey( 0, $pages_results );
		$this->assertArrayHasKey( $parent, $pages_results );
		$this->assertArrayHasKey( $child, $pages_results );
		$this->assertArrayHasKey( $grandchild_one, $pages_results );

		// Check all of the Values exist -> The order of $page_results[0] varies so test each value is present
		$this->assertContains( $all_pages[ $parent ], $pages_results[0] );
		$this->assertContains( $all_pages[ $parent_two ], $pages_results[0] );
		$this->assertContains( $all_pages[ $edit ], $pages_results[0] );
		$this->assertContains( $all_pages[ $google ], $pages_results[0] );
		$this->assertContains( $all_pages[ $parent ], $pages_results[0] );
		$this->assertContains( $all_pages[ $last_page ], $pages_results[0] );
		$this->assertContains( $parent_value, $pages_results );
		$this->assertContains( $child_value, $pages_results );
		$this->assertContains( $grandchild_value, $pages_results );

	}

	/**
	 * Covers bu_navigation_pages_by_parent_menu_sort()
	 */
	public function test_bu_navigation_pages_by_parent_menu_sort() {

		$parent 		 = $this->posts['parent'];
		$child 			 = $this->posts['child'];
		$parent_two 	 = $this->posts['parent_two'];
		$edit 			 = $this->posts['edit'];
		$hidden 		 = $this->posts['hidden'];
		$google 		 = $this->posts['google'];
		$last_page		 = $this->posts['last_page'];
		$grandchild_one  = $this->posts['grandchild_one'];
		$grandchild_two  = $this->posts['grandchild_two'];
		$greatgrandchild = $this->posts['greatgrandchild'];
		$test   		 = $this->posts['test'];
		$test_child		 = $this->posts['test_child'];
		$test_grandchild = $this->posts['test_grandchild'];

		$all_pages 	   = bu_navigation_get_pages();
		$page_results = bu_navigation_pages_by_parent( $all_pages );

		// Reorder the pages
		$page_results[0][4]->menu_order = "1"; // Last
		$page_results[0][2]->menu_order = "2"; // Edit
		$page_results[0][1]->menu_order = "3"; // Parent Two
		$page_results[0][0]->menu_order = "4"; // Parent One
		$page_results[0][3]->menu_order = "5"; // Google

		$sorted_expected = array(
					(string)$last_page,
					(string)$edit,
					(string)$parent_two,
					(string)$parent,
					(string)$google,
					(string)$child,
					(string)$grandchild_one,
					(string)$grandchild_two,
					(string)$greatgrandchild
				);

		$all_pages_sorted = bu_navigation_pages_by_parent_menu_sort( $page_results );
		$sorted_results = array();

		foreach( $all_pages_sorted as $page ) {
			foreach( $page as $p ) {
				array_push( $sorted_results, $p->ID );
			}
		}

		$this->assertEquals( $sorted_expected, $sorted_results );

	}

	/**
	 * Covers bu_navigation_pages_by_parent_menu_sort_cb()
	 */
	public function test_bu_navigation_pages_by_parent_menu_sort_cb() {

		$a = (object)array( 'menu_order' => 1 );
		$b = (object)array( 'menu_order' => 4 );

		$sorted_val_one = bu_navigation_pages_by_parent_menu_sort_cb( $a, $b );
		$sorted_val_two = bu_navigation_pages_by_parent_menu_sort_cb( $b, $a );

		$this->assertEquals( $sorted_val_one, -3 );
		$this->assertEquals( $sorted_val_two, 3 );

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
		$this->assertEquals( $expected_formatted_page, $formatted_page );

		/*
		*	Test Item Tag Functionaltiy
		*/

		$args = array( 'item_tag' => 'td' );
		$formatted_page = bu_navigation_format_page( $page, $args );
		$expected_formatted_page = sprintf("<td class=\"page_item page-item-%s\">\n<a title=\"%s\" href=\"%s\">%s</a>\n </td>\n", $parent, $title, $url, $title );

		$this->assertEquals( $expected_formatted_page, $formatted_page);

		/*
		*	Test Anchor Class Functionaltiy
		*/

		$args = array( 'anchor_class' => 'test_class' );
		$formatted_page = bu_navigation_format_page( $page, $args );
		$expected_formatted_page = sprintf("<li class=\"page_item page-item-%s\">\n<a title=\"%s\" class=\"test_class\" href=\"%s\">%s</a>\n </li>\n", $parent, $title, $url, $title );

		$this->assertEquals( $expected_formatted_page, $formatted_page );

		/*
		*	Test Depth Class Functionaltiy
		*/

		$args = array( 'depth' => 5 );
		$formatted_page = bu_navigation_format_page( $page, $args );
		$expected_formatted_page = sprintf("<li class=\"page_item page-item-%s\">\n<a title=\"%s\" class=\"level_5\" href=\"%s\">%s</a>\n </li>\n", $parent, $title, $url, $title );

		$this->assertEquals( $expected_formatted_page, $formatted_page );

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

		$this->assertEquals( $expected_formatted_page, $formatted_page );

		/*
		*	Test Position Functionaltiy
		*/

		// First Item
		$args = array( 'position' => 1, 'siblings' => 3 );
		$formatted_page = bu_navigation_format_page( $page, $args );
		$expected_formatted_page = '<li class="page_item page-item-' . $parent . ' first_item">' . "\n" . '<a title="' . $title . '" href="' . $url . '">' . $title . '</a>' . "\n" . ' </li>' . "\n";

		$this->assertEquals( $expected_formatted_page, $formatted_page );

		// Last Item
		$args = array( 'position' => 3, 'siblings' => 3 );
		$formatted_page = bu_navigation_format_page( $page, $args );
		$expected_formatted_page = '<li class="page_item page-item-' . $parent . ' last_item">' . "\n" . '<a title="' . $title . '" href="' . $url . '">' . $title . '</a>' . "\n" . ' </li>' . "\n";

		$this->assertEquals( $expected_formatted_page, $formatted_page );

		/*
		*	Test HTML Functionaltiy
		*/

		$args = array( 'html' => 'some html' );
		$formatted_page = bu_navigation_format_page( $page, $args );
		$expected_formatted_page = '<li class="page_item page-item-' . $parent . '">' . "\n" . '<a title="' . $title . '" href="' . $url . '">' . $title . '</a>' . "\n" . ' some html</li>' . "\n";

		$this->assertEquals( $expected_formatted_page, $formatted_page );

		/*
		*	Test ID Functionaltiy
		*/

		$args = array( 'item_id' => 'test_item_id' );
		$formatted_page = bu_navigation_format_page( $page, $args );
		$expected_formatted_page = '<li id="test_item_id" class="page_item page-item-' . $parent . '">' . "\n" . '<a title="' . $title . '" href="' . $url . '">' . $title . '</a>' . "\n" . ' </li>' . "\n";

		$this->assertEquals( $expected_formatted_page, $formatted_page );

	}

	/**
	 * Covers bu_navigation_filter_item_attrs()
	 */
	public function test_bu_navigation_filter_item_attrs() {

		$parent = $this->posts['parent'];
		$child = $this->posts['child'];
		$grandchild_one = $this->posts['grandchild_one'];
		$parent_two = $this->posts['parent_two'];

		/**
		* 	 Parent Page link on the Parent Page
		*/

		$this->go_to( get_permalink( $parent ));

		$pages = bu_navigation_get_pages();
		$page = $pages[ $parent ];
		$item_classes = array('page_item', 'page-item-' . $page->ID);

		$parent_expected = array('page_item', 'page-item-' . $page->ID, "current_page_item");
		$parent_results = apply_filters( 'bu_navigation_filter_item_attrs', $item_classes, $page );
		$this->assertEquals( $parent_results, $parent_expected );

		/**
		* 	 Parent Page Link on the Child Page
		*/

		$this->go_to( get_permalink( $child ));

		$pages = bu_navigation_get_pages();
		$page = $pages[ $parent ];
		$item_classes = array('page_item', 'page-item-' . $page->ID);

		$child_expected = array('page_item', 'page-item-' . $page->ID, "current_page_ancestor", "current_page_parent");
		$child_results = apply_filters( 'bu_navigation_filter_item_attrs', $item_classes, $page );

		$this->assertEquals( $child_results, $child_expected );

		/**
		* 	 Parent Page Link on the Grandchild Page
		*/

		$this->go_to( get_permalink( $grandchild_one ));

		$pages = bu_navigation_get_pages();
		$page = $pages[ $parent ];
		$item_classes = array('page_item', 'page-item-' . $page->ID);

		$grandchild_expected = array('page_item', 'page-item-' . $page->ID, "current_page_ancestor");
		$grandchild_results = apply_filters( 'bu_navigation_filter_item_attrs', $item_classes, $page );

		$this->assertEquals( $grandchild_results, $grandchild_expected );

		/**
		* 	 Child Page Link on the Parent Page Two (unrelated page)
		*/

		$this->go_to( get_permalink( $parent_two ));

		$pages = bu_navigation_get_pages();
		$page = $pages[ $child ];
		$item_classes = array('page_item', 'page-item-' . $page->ID);

		$parent_two_expected = array('page_item', 'page-item-' . $page->ID );
		$parent_two_results = apply_filters( 'bu_navigation_filter_item_attrs', $item_classes, $page );

		$this->assertEquals( $parent_two_expected, $parent_two_results );

	}

	/**
	 * Covers bu_navigation_filter_item_active_page()
	 */
	public function test_bu_navigation_filter_item_active_page() {

		$parent = $this->posts['parent'];
		$child = $this->posts['child'];
		$grandchild_one = $this->posts['grandchild_one'];
		$parent_two = $this->posts['parent_two'];

		/**
		* 	 Parent Page Link on the Parent Page
		*/

		$this->go_to( get_permalink( $parent ));

		$pages = bu_navigation_get_pages();
		$page = $pages[ $parent ];
		$parent_expected = array( 'class' => '' );

		$parent_results = apply_filters( 'bu_navigation_filter_anchor_attrs', $parent_expected, $page );
		$parent_expected['class'] = ' active';

		$this->assertEquals( $parent_expected, $parent_results );

		/**
		* 	 Parent Page Link on the Child Page
		*/

		$this->go_to( get_permalink( $child ));

		$pages = bu_navigation_get_pages();
		$page = $pages[ $parent ];
		$child_expected = array( 'class' => '' );

		$child_results = apply_filters( 'bu_navigation_filter_anchor_attrs', $child_expected, $page );
		$child_expected['class'] = ' active_section';

		$this->assertEquals( $child_expected, $child_results );

		/**
		* 	 Parent Page Two Link on the Child Page (Unrelated)
		*/

		$this->go_to( get_permalink( $child ));

		$pages = bu_navigation_get_pages();
		$page = $pages[ $parent_two ];
		$parent_two_expected = array( 'class' => '' );

		$parent_two_results = apply_filters( 'bu_navigation_filter_anchor_attrs', $parent_two_expected, $page );

		$this->assertEquals( $parent_two_expected, $parent_two_results );

	}

	/**
	 * Covers bu_navigation_list_section()
	 */
	public function test_bu_navigation_list_section() {

		$parent			 = $this->posts['parent'];
		$child 			 = $this->posts['child'];
		$grandchild_one  = $this->posts['grandchild_one'];
		$grandchild_two  = $this->posts['grandchild_two'];
		$greatgrandchild = $this->posts['greatgrandchild'];
		$pages 			 = bu_navigation_get_pages();
		$pages_by_parent = bu_navigation_pages_by_parent( $pages );

		// Generate Expected Results - Child Section
		$child_section_expected = "\n<ul>\n" .
				'<li class="page_item page-item-' . $grandchild_one . '">' . "\n" .
				'<a title="' . $pages[$grandchild_one]->post_title . '" class="level_1" href="' . $pages[$grandchild_one]->url . '">'. $pages[$grandchild_one]->post_title . '</a>' . "\n \n" .
				"<ul>\n" .
				'<li class="page_item page-item-' . $greatgrandchild . '">' . "\n" .
				'<a title="' . $pages[$greatgrandchild]->post_title . '" class="level_2" href="' . $pages[$greatgrandchild]->url . '">' . $pages[$greatgrandchild]->post_title . '</a>' . "\n" .
				" </li>\n\n" .
				"</ul>\n" .
				"</li>\n" .
				'<li class="page_item page-item-' . $grandchild_two . '">' . "\n" .
				'<a title="' . $pages[$grandchild_two]->post_title . '" class="level_1" href="' . $pages[$grandchild_two]->url . '">' . $pages[$grandchild_two]->post_title . '</a>' . "\n" .
				" </li>\n\n" .
				"</ul>\n";

		// Generate Expected Results - Parent Section
		$parent_section_expected = "\n<ul>\n" .
				'<li class="page_item page-item-' . $child . '">' . "\n" .
				'<a title="' . $pages[$child]->post_title . '" class="level_1" href="' . $pages[$child]->url . '">'. $pages[$child]->post_title . '</a>' . "\n \n" .
				"<ul>\n" .
				'<li class="page_item page-item-' . $grandchild_one . '">' . "\n" .
				'<a title="' . $pages[$grandchild_one]->post_title . '" class="level_2" href="' . $pages[$grandchild_one]->url . '">' . $pages[$grandchild_one]->post_title . '</a>' . "\n \n" .
				"<ul>\n" .
				'<li class="page_item page-item-' . $greatgrandchild . '">' . "\n" .
				'<a title="' . $pages[$greatgrandchild]->post_title . '" class="level_3" href="' . $pages[$greatgrandchild]->url . '">' . $pages[$greatgrandchild]->post_title . '</a>' . "\n" .
				" </li>\n\n" .
				"</ul>\n" .
				"</li>\n" .
				'<li class="page_item page-item-' . $grandchild_two . '">' . "\n" .
				'<a title="' . $pages[$grandchild_two]->post_title . '" class="level_2" href="' . $pages[$grandchild_two]->url . '">' . $pages[$grandchild_two]->post_title . '</a>' . "\n" .
				" </li>\n\n" .
				"</ul>\n</li>\n\n" .
				"</ul>\n";

		// Get Results
		$child_section_results = bu_navigation_list_section( $child, $pages_by_parent );
		$parent_section_results = bu_navigation_list_section( $parent, $pages_by_parent );

		// Test Results
		$this->assertEquals( $child_section_expected, $child_section_results );
		$this->assertEquals( $parent_section_expected, $parent_section_results );

		/**
		* 	Test Depth Functionaltiy
		*/

		$parent_section_depth_expected = "\n<ul>\n" .
				'<li class="page_item page-item-' . $child . '">' . "\n" .
				'<a title="' . $pages[$child]->post_title . '" class="level_3" href="' . $pages[$child]->url . '">'. $pages[$child]->post_title . '</a>' . "\n \n" .
				"<ul>\n" .
				'<li class="page_item page-item-' . $grandchild_one . '">' . "\n" .
				'<a title="' . $pages[$grandchild_one]->post_title . '" class="level_4" href="' . $pages[$grandchild_one]->url . '">' . $pages[$grandchild_one]->post_title . '</a>' . "\n \n" .
				"<ul>\n" .
				'<li class="page_item page-item-' . $greatgrandchild . '">' . "\n" .
				'<a title="' . $pages[$greatgrandchild]->post_title . '" class="level_5" href="' . $pages[$greatgrandchild]->url . '">' . $pages[$greatgrandchild]->post_title . '</a>' . "\n" .
				" </li>\n\n" .
				"</ul>\n" .
				"</li>\n" .
				'<li class="page_item page-item-' . $grandchild_two . '">' . "\n" .
				'<a title="' . $pages[$grandchild_two]->post_title . '" class="level_4" href="' . $pages[$grandchild_two]->url . '">' . $pages[$grandchild_two]->post_title . '</a>' . "\n" .
				" </li>\n\n" .
				"</ul>\n</li>\n\n" .
				"</ul>\n";

		$args = array( 'depth' => 3 );
		$parent_section_depth_results = bu_navigation_list_section( $parent, $pages_by_parent, $args );
		$this->assertEquals( $parent_section_depth_expected, $parent_section_depth_results );

		/**
		* 	Test Container Tag Functionaltiy
		*/

		$parent_section_container_tag_expected = "\n<ol>\n" .
				'<li class="page_item page-item-' . $child . '">' . "\n" .
				'<a title="' . $pages[$child]->post_title . '" class="level_1" href="' . $pages[$child]->url . '">'. $pages[$child]->post_title . '</a>' . "\n \n" .
				"<ol>\n" .
				'<li class="page_item page-item-' . $grandchild_one . '">' . "\n" .
				'<a title="' . $pages[$grandchild_one]->post_title . '" class="level_2" href="' . $pages[$grandchild_one]->url . '">' . $pages[$grandchild_one]->post_title . '</a>' . "\n \n" .
				"<ol>\n" .
				'<li class="page_item page-item-' . $greatgrandchild . '">' . "\n" .
				'<a title="' . $pages[$greatgrandchild]->post_title . '" class="level_3" href="' . $pages[$greatgrandchild]->url . '">' . $pages[$greatgrandchild]->post_title . '</a>' . "\n" .
				" </li>\n\n" .
				"</ol>\n" .
				"</li>\n" .
				'<li class="page_item page-item-' . $grandchild_two . '">' . "\n" .
				'<a title="' . $pages[$grandchild_two]->post_title . '" class="level_2" href="' . $pages[$grandchild_two]->url . '">' . $pages[$grandchild_two]->post_title . '</a>' . "\n" .
				" </li>\n\n" .
				"</ol>\n</li>\n\n" .
				"</ol>\n";

		$args = array( 'container_tag' => 'ol' );
		$parent_section_container_tag_results = bu_navigation_list_section( $parent, $pages_by_parent, $args );
		$this->assertEquals( $parent_section_container_tag_expected, $parent_section_container_tag_results );

		/**
		* 	Test Item Tag Functionaltiy
		*/

		$parent_section_item_tag_expected = "\n<ul>\n" .
				'<test class="page_item page-item-' . $child . '">' . "\n" .
				'<a title="' . $pages[$child]->post_title . '" class="level_1" href="' . $pages[$child]->url . '">'. $pages[$child]->post_title . '</a>' . "\n \n" .
				"<ul>\n" .
				'<test class="page_item page-item-' . $grandchild_one . '">' . "\n" .
				'<a title="' . $pages[$grandchild_one]->post_title . '" class="level_2" href="' . $pages[$grandchild_one]->url . '">' . $pages[$grandchild_one]->post_title . '</a>' . "\n \n" .
				"<ul>\n" .
				'<test class="page_item page-item-' . $greatgrandchild . '">' . "\n" .
				'<a title="' . $pages[$greatgrandchild]->post_title . '" class="level_3" href="' . $pages[$greatgrandchild]->url . '">' . $pages[$greatgrandchild]->post_title . '</a>' . "\n" .
				" </test>\n\n" .
				"</ul>\n" .
				"</test>\n" .
				'<test class="page_item page-item-' . $grandchild_two . '">' . "\n" .
				'<a title="' . $pages[$grandchild_two]->post_title . '" class="level_2" href="' . $pages[$grandchild_two]->url . '">' . $pages[$grandchild_two]->post_title . '</a>' . "\n" .
				" </test>\n\n" .
				"</ul>\n</test>\n\n" .
				"</ul>\n";

		$args = array( 'item_tag' => 'test' );
		$parent_section_item_tag_results = bu_navigation_list_section( $parent, $pages_by_parent, $args );
		$this->assertEquals( $parent_section_item_tag_expected, $parent_section_item_tag_results );

		/**
		* 	Test Section Ids Functionaltiy
		*/

		$parent_section_section_id_expected = "\n<ul>\n" .
				'<li class="page_item page-item-' . $child . ' has_children">' . "\n" .
				'<a title="' . $pages[$child]->post_title . '" class="level_1" href="' . $pages[$child]->url . '">'. $pages[$child]->post_title . '</a>' . "\n \n" .
				"<ul>\n" .
				'<li class="page_item page-item-' . $grandchild_one . ' has_children">' . "\n" .
				'<a title="' . $pages[$grandchild_one]->post_title . '" class="level_2" href="' . $pages[$grandchild_one]->url . '">' . $pages[$grandchild_one]->post_title . '</a>' . "\n \n" .
				"<ul>\n" .
				'<li class="page_item page-item-' . $greatgrandchild . '">' . "\n" .
				'<a title="' . $pages[$greatgrandchild]->post_title . '" class="level_3" href="' . $pages[$greatgrandchild]->url . '">' . $pages[$greatgrandchild]->post_title . '</a>' . "\n" .
				" </li>\n\n" .
				"</ul>\n" .
				"</li>\n" .
				'<li class="page_item page-item-' . $grandchild_two . '">' . "\n" .
				'<a title="' . $pages[$grandchild_two]->post_title . '" class="level_2" href="' . $pages[$grandchild_two]->url . '">' . $pages[$grandchild_two]->post_title . '</a>' . "\n" .
				" </li>\n\n" .
				"</ul>\n</li>\n\n" .
				"</ul>\n";

		$args = array( 'section_ids' => array( $child, $grandchild_one ));
		$parent_section_section_id_results = bu_navigation_list_section( $parent, $pages_by_parent, $args );
		$this->assertEquals( $parent_section_section_id_expected, $parent_section_section_id_results );

	}

	/**
	 * Covers bu_navigation_list_pages()
	 */
	public function test_bu_navigation_list_pages() {

		$parent 		 = $this->posts['parent'];
		$child 			 = $this->posts['child'];
		$parent_two 	 = $this->posts['parent_two'];
		$hidden 		 = $this->posts['hidden'];
		$edit 			 = $this->posts['edit'];
		$google 		 = $this->posts['google'];
		$last_page		 = $this->posts['last_page'];
		$grandchild_one  = $this->posts['grandchild_one'];
		$grandchild_two  = $this->posts['grandchild_two'];
		$greatgrandchild = $this->posts['greatgrandchild'];
		$test   		 = $this->posts['test'];
		$test_child		 = $this->posts['test_child'];
		$test_grandchild = $this->posts['test_grandchild'];

		$list_pages_expected = "<ul >\n" .
			'<li class="page_item page-item-' . $parent . ' first_item">' . "\n" .
			'<a title="Parent Page" class="level_1" href="' . get_permalink( $parent ) . '">Parent Page</a>' . "\n \n" .
			"<ul>\n" .
			'<li class="page_item page-item-' . $child . '">' . "\n" .
			'<a title="Child Page" class="level_2" href="' . get_permalink( $child ) . '">Child Page</a>' . "\n \n" .
			"<ul>\n" .
			'<li class="page_item page-item-' . $grandchild_one . '">' . "\n" .
			'<a title="Grand Child Page 1" class="level_3" href="' . get_permalink( $grandchild_one ) . '">Grand Child Page 1</a>' . "\n \n" .
			"<ul>\n" .
			'<li class="page_item page-item-' . $greatgrandchild . '">' . "\n" .
			'<a title="Great Grand Child" class="level_4" href="' . get_permalink( $greatgrandchild ) . '">Great Grand Child</a>' . "\n" .
			" </li>\n\n" .
			"</ul>\n" .
			"</li>\n" .
			'<li class="page_item page-item-' . $grandchild_two . '">' . "\n".
			'<a title="Grand Child Page 2" class="level_3" href="' . get_permalink( $grandchild_two ) . '">Grand Child Page 2</a>' . "\n" .
			" </li>\n\n" .
			"</ul>\n" .
			"</li>\n\n" .
			"</ul>\n" .
			"</li>\n" .
			'<li class="page_item page-item-' . $parent_two . '">' . "\n" .
			'<a title="Parent Page Two" class="level_1" href="' . get_permalink( $parent_two ) . '">Parent Page Two</a>' . "\n" .
			" </li>\n" .
			'<li class="page_item page-item-' . $edit . '">' . "\n" .
			'<a title="Edit and Delete Me" class="level_1" href="' . get_permalink( $edit ) . '">Edit and Delete Me</a>' . "\n" .
			" </li>\n" .
			'<li class="page_item page-item-' . $google . '">' . "\n" .
			'<a title="Google" class="level_1" href="' . get_permalink( $google ) . '" target="_blank">Google</a>' . "\n" .
			" </li>\n" .
			'<li class="page_item page-item-' . $last_page . ' last_item">' . "\n" .
			'<a title="Last Page" class="level_1" href="' . get_permalink( $last_page ) . '">Last Page</a>' . "\n" .
			" </li>\n" .
			"</ul>\n";

		$list_pages_results = bu_navigation_list_pages();
		$this->assertEquals( $list_pages_expected, $list_pages_results );

		/**
		* 	Test Page ID Function
		*/

		$list_pages_page_id_expected = "<ul >\n" .
			'<li class="page_item page-item-' . $parent . ' has_children first_item">' . "\n" .
			'<a title="Parent Page" class="level_1" href="' . get_permalink( $parent ) . '">Parent Page</a>' . "\n \n" .
			"<ul>\n" .
			'<li class="page_item page-item-' . $child . ' has_children">' . "\n" .
			'<a title="Child Page" class="level_2" href="' . get_permalink( $child ) . '">Child Page</a>' . "\n" .
			" </li>\n\n" .
			"</ul>\n" .
			"</li>\n" .
			'<li class="page_item page-item-' . $parent_two . '">' . "\n" .
			'<a title="Parent Page Two" class="level_1" href="' . get_permalink( $parent_two ) . '">Parent Page Two</a>' . "\n" .
			" </li>\n" .
			'<li class="page_item page-item-' . $edit . '">' . "\n" .
			'<a title="Edit and Delete Me" class="level_1" href="' . get_permalink( $edit ) . '">Edit and Delete Me</a>' . "\n" .
			" </li>\n" .
			'<li class="page_item page-item-' . $google . '">' . "\n" .
			'<a title="Google" class="level_1" href="' . get_permalink( $google ) . '" target="_blank">Google</a>' . "\n" .
			" </li>\n" .
			'<li class="page_item page-item-' . $last_page . ' last_item">' . "\n" .
			'<a title="Last Page" class="level_1" href="' . get_permalink( $last_page ) . '">Last Page</a>' . "\n" .
			" </li>\n" .
			"</ul>\n";

		$args = array( 'page_id' => $parent );
		$list_pages_page_id_results = bu_navigation_list_pages( $args );
		$this->assertEquals( $list_pages_page_id_expected, $list_pages_page_id_results );

		/**
		* 	Test Echo Function
		*/

		ob_start();
		$args = array( 'echo' => 1 );
		bu_navigation_list_pages( $args );
		$list_pages_echo_results = ob_get_contents();
		ob_end_clean();

		$this->assertEquals( $list_pages_expected, $list_pages_echo_results );

		/**
		* 	Test Navigate in Section Function
		*/

		$list_pages_navigate_in_section_expected = "<ul >\n" .
			'<li class="page_item page-item-' . $child . ' first_item last_item">' . "\n" .
			'<a title="Child Page" class="level_1" href="' . get_permalink( $child ) . '">Child Page</a>' . "\n \n" .
			"<ul>\n" .
			'<li class="page_item page-item-' . $grandchild_one . '">' . "\n" .
			'<a title="Grand Child Page 1" class="level_2" href="' . get_permalink( $grandchild_one ) . '">Grand Child Page 1</a>' . "\n \n" .
			"<ul>\n" .
			'<li class="page_item page-item-' . $greatgrandchild . '">' . "\n" .
			'<a title="Great Grand Child" class="level_3" href="' . get_permalink( $greatgrandchild ) . '">Great Grand Child</a>' . "\n" .
			" </li>\n\n" .
			"</ul>\n" .
			"</li>\n" .
			'<li class="page_item page-item-' . $grandchild_two . '">' . "\n".
			'<a title="Grand Child Page 2" class="level_2" href="' . get_permalink( $grandchild_two ) . '">Grand Child Page 2</a>' . "\n" .
			" </li>\n\n" .
			"</ul>\n" .
			"</li>\n" .
			"</ul>\n";

		$args = array( 'navigate_in_section' => true );
		$list_pages_navigate_in_section_results = bu_navigation_list_pages( $args );
		$this->assertEquals( $list_pages_navigate_in_section_expected, $list_pages_navigate_in_section_results );

		/**
		* 	Test Container Tag Function
		*/

		$list_pages_container_tag_expected = "<ol >\n" .
			'<li class="page_item page-item-' . $parent . ' first_item">' . "\n" .
			'<a title="Parent Page" class="level_1" href="' . get_permalink( $parent ) . '">Parent Page</a>' . "\n \n" .
			"<ol>\n" .
			'<li class="page_item page-item-' . $child . '">' . "\n" .
			'<a title="Child Page" class="level_2" href="' . get_permalink( $child ) . '">Child Page</a>' . "\n \n" .
			"<ol>\n" .
			'<li class="page_item page-item-' . $grandchild_one . '">' . "\n" .
			'<a title="Grand Child Page 1" class="level_3" href="' . get_permalink( $grandchild_one ) . '">Grand Child Page 1</a>' . "\n \n" .
			"<ol>\n" .
			'<li class="page_item page-item-' . $greatgrandchild . '">' . "\n" .
			'<a title="Great Grand Child" class="level_4" href="' . get_permalink( $greatgrandchild ) . '">Great Grand Child</a>' . "\n" .
			" </li>\n\n" .
			"</ol>\n" .
			"</li>\n" .
			'<li class="page_item page-item-' . $grandchild_two . '">' . "\n".
			'<a title="Grand Child Page 2" class="level_3" href="' . get_permalink( $grandchild_two ) . '">Grand Child Page 2</a>' . "\n" .
			" </li>\n\n" .
			"</ol>\n" .
			"</li>\n\n" .
			"</ol>\n" .
			"</li>\n" .
			'<li class="page_item page-item-' . $parent_two . '">' . "\n" .
			'<a title="Parent Page Two" class="level_1" href="' . get_permalink( $parent_two ) . '">Parent Page Two</a>' . "\n" .
			" </li>\n" .
			'<li class="page_item page-item-' . $edit . '">' . "\n" .
			'<a title="Edit and Delete Me" class="level_1" href="' . get_permalink( $edit ) . '">Edit and Delete Me</a>' . "\n" .
			" </li>\n" .
			'<li class="page_item page-item-' . $google . '">' . "\n" .
			'<a title="Google" class="level_1" href="' . get_permalink( $google ) . '" target="_blank">Google</a>' . "\n" .
			" </li>\n" .
			'<li class="page_item page-item-' . $last_page . ' last_item">' . "\n" .
			'<a title="Last Page" class="level_1" href="' . get_permalink( $last_page ) . '">Last Page</a>' . "\n" .
			" </li>\n" .
			"</ol>\n";

		$args = array( 'container_tag' => 'ol' );
		$list_pages_container_tag_results = bu_navigation_list_pages( $args );
		$this->assertEquals( $list_pages_container_tag_expected, $list_pages_container_tag_results );

		/**
		* 	Test Container ID Function
		*/

		$list_pages_container_id_expected = '<ul  id="test_container_id">' . "\n" .
			'<li class="page_item page-item-' . $parent . ' first_item">' . "\n" .
			'<a title="Parent Page" class="level_1" href="' . get_permalink( $parent ) . '">Parent Page</a>' . "\n \n" .
			"<ul>\n" .
			'<li class="page_item page-item-' . $child . '">' . "\n" .
			'<a title="Child Page" class="level_2" href="' . get_permalink( $child ) . '">Child Page</a>' . "\n \n" .
			"<ul>\n" .
			'<li class="page_item page-item-' . $grandchild_one . '">' . "\n" .
			'<a title="Grand Child Page 1" class="level_3" href="' . get_permalink( $grandchild_one ) . '">Grand Child Page 1</a>' . "\n \n" .
			"<ul>\n" .
			'<li class="page_item page-item-' . $greatgrandchild . '">' . "\n" .
			'<a title="Great Grand Child" class="level_4" href="' . get_permalink( $greatgrandchild ) . '">Great Grand Child</a>' . "\n" .
			" </li>\n\n" .
			"</ul>\n" .
			"</li>\n" .
			'<li class="page_item page-item-' . $grandchild_two . '">' . "\n".
			'<a title="Grand Child Page 2" class="level_3" href="' . get_permalink( $grandchild_two ) . '">Grand Child Page 2</a>' . "\n" .
			" </li>\n\n" .
			"</ul>\n" .
			"</li>\n\n" .
			"</ul>\n" .
			"</li>\n" .
			'<li class="page_item page-item-' . $parent_two . '">' . "\n" .
			'<a title="Parent Page Two" class="level_1" href="' . get_permalink( $parent_two ) . '">Parent Page Two</a>' . "\n" .
			" </li>\n" .
			'<li class="page_item page-item-' . $edit . '">' . "\n" .
			'<a title="Edit and Delete Me" class="level_1" href="' . get_permalink( $edit ) . '">Edit and Delete Me</a>' . "\n" .
			" </li>\n" .
			'<li class="page_item page-item-' . $google . '">' . "\n" .
			'<a title="Google" class="level_1" href="' . get_permalink( $google ) . '" target="_blank">Google</a>' . "\n" .
			" </li>\n" .
			'<li class="page_item page-item-' . $last_page . ' last_item">' . "\n" .
			'<a title="Last Page" class="level_1" href="' . get_permalink( $last_page ) . '">Last Page</a>' . "\n" .
			" </li>\n" .
			"</ul>\n";

		$args = array( 'container_id' => 'test_container_id' );
		$list_pages_container_id_results = bu_navigation_list_pages( $args );
		$this->assertEquals( $list_pages_container_id_expected, $list_pages_container_id_results );


		/**
		* 	Test Container Class Function
		*/

		$list_pages_container_class_expected = '<ul  class="test_container_class">' . "\n" .
			'<li class="page_item page-item-' . $parent . ' first_item">' . "\n" .
			'<a title="Parent Page" class="level_1" href="' . get_permalink( $parent ) . '">Parent Page</a>' . "\n \n" .
			"<ul>\n" .
			'<li class="page_item page-item-' . $child . '">' . "\n" .
			'<a title="Child Page" class="level_2" href="' . get_permalink( $child ) . '">Child Page</a>' . "\n \n" .
			"<ul>\n" .
			'<li class="page_item page-item-' . $grandchild_one . '">' . "\n" .
			'<a title="Grand Child Page 1" class="level_3" href="' . get_permalink( $grandchild_one ) . '">Grand Child Page 1</a>' . "\n \n" .
			"<ul>\n" .
			'<li class="page_item page-item-' . $greatgrandchild . '">' . "\n" .
			'<a title="Great Grand Child" class="level_4" href="' . get_permalink( $greatgrandchild ) . '">Great Grand Child</a>' . "\n" .
			" </li>\n\n" .
			"</ul>\n" .
			"</li>\n" .
			'<li class="page_item page-item-' . $grandchild_two . '">' . "\n".
			'<a title="Grand Child Page 2" class="level_3" href="' . get_permalink( $grandchild_two ) . '">Grand Child Page 2</a>' . "\n" .
			" </li>\n\n" .
			"</ul>\n" .
			"</li>\n\n" .
			"</ul>\n" .
			"</li>\n" .
			'<li class="page_item page-item-' . $parent_two . '">' . "\n" .
			'<a title="Parent Page Two" class="level_1" href="' . get_permalink( $parent_two ) . '">Parent Page Two</a>' . "\n" .
			" </li>\n" .
			'<li class="page_item page-item-' . $edit . '">' . "\n" .
			'<a title="Edit and Delete Me" class="level_1" href="' . get_permalink( $edit ) . '">Edit and Delete Me</a>' . "\n" .
			" </li>\n" .
			'<li class="page_item page-item-' . $google . '">' . "\n" .
			'<a title="Google" class="level_1" href="' . get_permalink( $google ) . '" target="_blank">Google</a>' . "\n" .
			" </li>\n" .
			'<li class="page_item page-item-' . $last_page . ' last_item">' . "\n" .
			'<a title="Last Page" class="level_1" href="' . get_permalink( $last_page ) . '">Last Page</a>' . "\n" .
			" </li>\n" .
			"</ul>\n";

		$args = array( 'container_class' => 'test_container_class' );
		$list_pages_container_class_results = bu_navigation_list_pages( $args );
		$this->assertEquals( $list_pages_container_class_expected, $list_pages_container_class_results );

		/**
		* 	Test Item Tag Function
		*/

		$list_pages_item_tag_expected = "<ul >\n" .
			'<ll class="page_item page-item-' . $parent . ' first_item">' . "\n" .
			'<a title="Parent Page" class="level_1" href="' . get_permalink( $parent ) . '">Parent Page</a>' . "\n \n" .
			"<ul>\n" .
			'<ll class="page_item page-item-' . $child . '">' . "\n" .
			'<a title="Child Page" class="level_2" href="' . get_permalink( $child ) . '">Child Page</a>' . "\n \n" .
			"<ul>\n" .
			'<ll class="page_item page-item-' . $grandchild_one . '">' . "\n" .
			'<a title="Grand Child Page 1" class="level_3" href="' . get_permalink( $grandchild_one ) . '">Grand Child Page 1</a>' . "\n \n" .
			"<ul>\n" .
			'<ll class="page_item page-item-' . $greatgrandchild . '">' . "\n" .
			'<a title="Great Grand Child" class="level_4" href="' . get_permalink( $greatgrandchild ) . '">Great Grand Child</a>' . "\n" .
			" </ll>\n\n" .
			"</ul>\n" .
			"</ll>\n" .
			'<ll class="page_item page-item-' . $grandchild_two . '">' . "\n".
			'<a title="Grand Child Page 2" class="level_3" href="' . get_permalink( $grandchild_two ) . '">Grand Child Page 2</a>' . "\n" .
			" </ll>\n\n" .
			"</ul>\n" .
			"</ll>\n\n" .
			"</ul>\n" .
			"</ll>\n" .
			'<ll class="page_item page-item-' . $parent_two . '">' . "\n" .
			'<a title="Parent Page Two" class="level_1" href="' . get_permalink( $parent_two ) . '">Parent Page Two</a>' . "\n" .
			" </ll>\n" .
			'<ll class="page_item page-item-' . $edit . '">' . "\n" .
			'<a title="Edit and Delete Me" class="level_1" href="' . get_permalink( $edit ) . '">Edit and Delete Me</a>' . "\n" .
			" </ll>\n" .
			'<ll class="page_item page-item-' . $google . '">' . "\n" .
			'<a title="Google" class="level_1" href="' . get_permalink( $google ) . '" target="_blank">Google</a>' . "\n" .
			" </ll>\n" .
			'<ll class="page_item page-item-' . $last_page . ' last_item">' . "\n" .
			'<a title="Last Page" class="level_1" href="' . get_permalink( $last_page ) . '">Last Page</a>' . "\n" .
			" </ll>\n" .
			"</ul>\n";

		$args = array( 'item_tag' => 'll' );
		$list_pages_item_tag_results = bu_navigation_list_pages( $args );
		$this->assertEquals( $list_pages_item_tag_expected, $list_pages_item_tag_results );

		/**
		* 	Test Style (Adaptive) Function
		*/

		$list_pages_style_expected = "<ul >\n" .
			'<li class="page_item page-item-' . $child . ' has_children first_item last_item">' . "\n" .
			'<a title="Child Page" class="level_1" href="' . get_permalink( $child ) . '">Child Page</a>' . "\n \n" .
			"<ul>\n" .
			'<li class="page_item page-item-' . $grandchild_one . ' has_children">' . "\n" .
			'<a title="Grand Child Page 1" class="level_2" href="' . get_permalink( $grandchild_one ) . '">Grand Child Page 1</a>' . "\n" .
			" </li>\n" .
			'<li class="page_item page-item-' . $grandchild_two . '">' . "\n".
			'<a title="Grand Child Page 2" class="level_2" href="' . get_permalink( $grandchild_two ) . '">Grand Child Page 2</a>' . "\n" .
			" </li>\n\n" .
			"</ul>\n" .
			"</li>\n" .
			"</ul>\n";

		$args = array( 'page_id' => $child, 'style' => 'adaptive' );
		$list_pages_style_results = bu_navigation_list_pages( $args );
		$this->assertEquals( $list_pages_style_expected, $list_pages_style_results );

		/**
		* 	Test Post Types Function
		*/
		$list_pages_post_type_expected = "<ul >\n" .
			'<li class="page_item page-item-' . $test . ' first_item last_item">' . "\n" .
			'<a title="Test Type Page" class="level_1" href="' . get_permalink( $test ) . '">Test Type Page</a>' . "\n \n" .
			"<ul>\n" .
			'<li class="page_item page-item-' . $test_child . '">' . "\n" .
			'<a title="Test Child" class="level_2" href="' . get_permalink( $test_child ) . '">Test Child</a>' . "\n \n" .
			"<ul>\n" .
			'<li class="page_item page-item-' . $test_grandchild . '">' . "\n".
			'<a title="Test Grandchild" class="level_3" href="' . get_permalink( $test_grandchild ) . '">Test Grandchild</a>' . "\n" .
			" </li>\n\n" .
			"</ul>\n" .
			"</li>\n\n" .
			"</ul>\n" .
			"</li>\n" .
			"</ul>\n";

		$args = array( 'post_types' => array( 'test' ));
		$list_pages_post_type_results = bu_navigation_list_pages( $args );
		$this->assertEquals( $list_pages_post_type_expected, $list_pages_post_type_results );

	}

	/**
	 * Covers bu_navigation_page_parent_dropdown()
	 */
	public function test_bu_navigation_page_parent_dropdown() {

		$parent = $this->posts['parent'];
		$child = $this->posts['child'];
		$test = $this->posts['test'];
		$post_types = 'page';

		$dropdown_expected = "<select id=\"bu_filter_pages\" name=\"post_parent\">\r" .
				"\n\t<option value=\"0\">Show all sections</option>\r" .
				"\n\t<option value=\"" . $parent . "\" >Parent Page</option>\r" .
				"\r</select>\r";

		ob_start();
		bu_navigation_page_parent_dropdown( $post_types );
		$dropdown_results = ob_get_contents();
		ob_end_clean();

		$this->assertEquals( $dropdown_expected, $dropdown_results );

		/**
		*	Test Post Type Function
		*/

		$post_types_test = 'test';
		$dropdown_posttype_expected = "<select id=\"bu_filter_pages\" name=\"post_parent\">\r" .
				"\n\t<option value=\"" . $test . "\" >Test Type Page</option>\r" .
				"\r</select>\r";

		ob_start();
		bu_navigation_page_parent_dropdown( $post_types_test );
		$dropdown_posttype_results = ob_get_contents();
		ob_end_clean();

		$this->assertEquals( $dropdown_posttype_expected, $dropdown_posttype_expected );

		/**
		*	Test Selected Function
		*/

		$selected = $parent;
		$dropdown_selected_expected = "<select id=\"bu_filter_pages\" name=\"post_parent\">\r" .
				"\n\t<option value=\"0\">Show all sections</option>\r" .
				"\n\t<option value=\"" . $parent . "\" selected=\"selected\">Parent Page</option>\r" .
				"\r</select>\r";

		ob_start();
		bu_navigation_page_parent_dropdown( $post_types, $selected );
		$dropdown_selected_results = ob_get_contents();
		ob_end_clean();

		$this->assertEquals( $dropdown_selected_expected, $dropdown_selected_results );

		/**
		*	Test Echo Function
		*/

		$dropdown_echo_expected = "<select id=\"bu_filter_pages\" name=\"post_parent\">\r" .
				"\n\t<option value=\"0\">Show all sections</option>\r" .
				"\n\t<option value=\"" . $parent . "\" >Parent Page</option>\r" .
				"\r</select>\r";

		$args = array( 'echo' => 0 );
		$dropdown_echo_results = bu_navigation_page_parent_dropdown( $post_types, 0, $args );

		$this->assertEquals( $dropdown_echo_expected, $dropdown_echo_results );

		/**
		*	Test Select ID Function
		*/

		$dropdown_select_id_expected = "<select id=\"test_select_id\" name=\"post_parent\">\r" .
				"\n\t<option value=\"0\">Show all sections</option>\r" .
				"\n\t<option value=\"" . $parent . "\" >Parent Page</option>\r" .
				"\r</select>\r";

		$args = array( 'echo' => 0, 'select_id' => 'test_select_id' );
		$dropdown_select_id_results = bu_navigation_page_parent_dropdown( $post_types, 0, $args );

		$this->assertEquals( $dropdown_select_id_expected, $dropdown_select_id_results );

		/**
		*	Test Select Name Function
		*/

		$dropdown_select_name_expected = "<select id=\"bu_filter_pages\" name=\"test_name\">\r" .
				"\n\t<option value=\"0\">Show all sections</option>\r" .
				"\n\t<option value=\"" . $parent . "\" >Parent Page</option>\r" .
				"\r</select>\r";

		$args = array( 'echo' => 0, 'select_name' => 'test_name' );
		$dropdown_select_name_results = bu_navigation_page_parent_dropdown( $post_types, 0, $args );

		$this->assertEquals( $dropdown_select_name_expected, $dropdown_select_name_results );

		/**
		*	Test Select Classes Function
		*/

		$dropdown_classes_expected = "<select id=\"bu_filter_pages\" name=\"post_parent\" class=\"test_class\">\r" .
				"\n\t<option value=\"0\">Show all sections</option>\r" .
				"\n\t<option value=\"" . $parent . "\" >Parent Page</option>\r" .
				"\r</select>\r";

		$args = array( 'echo' => 0, 'select_classes' => 'test_class' );
		$dropdown_classes_results = bu_navigation_page_parent_dropdown( $post_types, 0, $args );

		$this->assertEquals( $dropdown_classes_expected, $dropdown_classes_results );

	}

	/**
	 * Covers bu_filter_pages_parent_dropdown()
	 */
	public function test_bu_filter_pages_parent_dropdown() {
		$parent = $this->posts['parent'];
		$child = $this->posts['child'];
		$grandchild_one = $this->posts['grandchild_one'];

		$pages = bu_navigation_get_pages();
		$pages_by_parent = bu_navigation_pages_by_parent($pages);

		$page_options_expected = "\n\t" . '<option value="' . $child . '" >Child Page</option>' . "\r" .
			"\n\t" . '<option value="' . $grandchild_one .  '" >&nbsp;&nbsp;&nbsp;Grand Child Page 1</option>' . "\r";

		ob_start();
		bu_filter_pages_parent_dropdown( $pages_by_parent, 0, $parent );
		$page_options_results = ob_get_contents();
		ob_end_clean();

		$this->assertEquals( $page_options_results, $page_options_expected );

		/**
		* 	Test Default Function
		*/

		$page_options_default_expected = "\n\t" . '<option value="' . $child . '" selected="selected">Child Page</option>' . "\r" .
			"\n\t" . '<option value="' . $grandchild_one .  '" >&nbsp;&nbsp;&nbsp;Grand Child Page 1</option>' . "\r";

		ob_start();
		bu_filter_pages_parent_dropdown( $pages_by_parent, $child, $parent );
		$page_options_default_results = ob_get_contents();
		ob_end_clean();

		$this->assertEquals( $page_options_default_expected, $page_options_default_results );

		/**
		* 	Test Parent Function
		*/

		$page_options_parent_expected = "\n\t" . '<option value="' . $grandchild_one .  '" >Grand Child Page 1</option>' . "\r";

		ob_start();
		bu_filter_pages_parent_dropdown( $pages_by_parent, 0, $child );
		$page_options_parent_results = ob_get_contents();
		ob_end_clean();

		$this->assertEquals( $page_options_parent_expected, $page_options_parent_results );

		/**
		* 	Test Level Function
		*/

		$page_options_level_expected = "\n\t" . '<option value="' . $child . '" selected="selected">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Child Page</option>' . "\r" .
			"\n\t" . '<option value="' . $grandchild_one .  '" >&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Grand Child Page 1</option>' . "\r";

		ob_start();
		bu_filter_pages_parent_dropdown( $pages_by_parent, $child, $parent, 2 );
		$page_options_level_results = ob_get_contents();
		ob_end_clean();

		$this->assertEquals( $page_options_level_expected, $page_options_level_results );

	}

}


/*
*	Test Filter to work with test_bu_navigation_get_pages
*/
function bu_navigation_test_filter( $pages ) {

	foreach ( $pages as $page ) {
		$page->post_filter_example = 'test-filter';
	}

	return $pages;
}
