<?php

require_once 'page-objects/navman.php';

/**
 * @group bu-navigation-navman
 */
 class BU_Navigation_Navman_Test extends WP_SeleniumTestCase {

	public function setUp() {

		parent::setUp();

		// Load initial post data from JSON
		$posts_json = file_get_contents( dirname(__FILE__) . '/data/test_posts.json');
		$posts = json_decode($posts_json, true);

		$this->load_test_posts( $posts );

	}

	public function tearDown() {

		parent::tearDown();

		$this->delete_test_posts();

	}

	public function load_test_posts( $posts, $parent_id = 0 ) {

		foreach( $posts as $key => $post ) {

			$data = $post['data'];

			// Maybe set parent
			if( $parent_id )
				$data['post_parent'] = $parent_id;

			$id = $this->factory->post->create( $data );

			// Post meta
			$metadata = $post['metadata'];

			if( !empty( $metadata ) ) {
				foreach( $metadata as $meta_key => $meta_val ) {
					update_post_meta( $id, $meta_key, $meta_val );
				}
			}

			// Load children
			$children = $post['children'];
			if( !empty( $children ) ) {
				$this->load_test_posts( $children, $id );
			}

			// Cache internally for access during tests
			$this->pages[$key] = $id;

		}

	}

	public function delete_test_posts() {
		foreach( $this->pages as $id ) {
			wp_delete_post( $id, true );
		}
	}

	/**
	 * Webdriver session does not exist during setUp, otherwise this would
	 * be called from there...
	 */
	public function pre_test_setup() {
		$this->timeouts()->implicitWait(5000);
		$this->wp->login( $this->settings['login'], $this->settings['password'] );
	}

	/**
	 * @group bu-navigation-types
	 * @group bu-navigation-single
	 */
	public function test_leaf() {

		$this->pre_test_setup();

		$navman = new BUN_Navman_Page( $this, 'page' );
		$page = $navman->getPage( $this->pages['last_page'] );

		$this->assertEquals( $page->attribute('rel'), BUN_Navman_Page::TYPES_PAGE );

	}

	/**
	 * @group bu-navigation-types
	 */
	public function test_link() {

		$this->pre_test_setup();

		$navman = new BUN_Navman_Page( $this, 'page' );
		$page = $navman->getPage( $this->pages['google'] );

		$this->assertEquals( $page->attribute('rel'), BUN_Navman_Page::TYPES_LINK );

	}

	/**
	 * @group bu-navigation-types
	 */
	public function test_section() {

		$this->pre_test_setup();

		$navman = new BUN_Navman_Page( $this, 'page' );
		$page = $navman->getPage( $this->pages['parent'] );

		$this->assertEquals( $page->attribute('rel'), BUN_Navman_Page::TYPES_SECTION );

	}


	/**
	 * @group bu-navigation-actions
	 *
	 * @todo utilize page object for class attributes
	 */
	public function test_hover() {

		$this->pre_test_setup();

		$navman = new BUN_Navman_Page( $this, 'page' );
		$page = $navman->getPage( $this->pages['parent'], 'a' );

		$this->moveto( $page );

		$this->assertTrue( false !== strpos( $page->attribute('class'), BUN_Navman_Page::JSTREE_HOVERED ) );

	}

	/**
	 * @group bu-navigation-actions
	 * @todo utilize page object for class attributes
	 */
	public function test_select() {

		$this->pre_test_setup();

		$navman = new BUN_Navman_Page( $this, 'page' );
		$page = $navman->getPage( $this->pages['parent'], 'a' );

		$page->click();

		$this->assertTrue( false !== strpos( $page->attribute('class'), BUN_Navman_Page::JSTREE_CLICKED ) );

	}

	/**
	 * @group bu-navigation-open
	 *
	 * @todo utilize page object for class attributes
	 */
	public function test_open() {

		$this->pre_test_setup();

		$navman = new BUN_Navman_Page( $this, 'page' );
		$page = $navman->getPage( $this->pages['parent'] );

		$this->assertTrue( false !== strpos( $page->attribute('class'), BUN_Navman_Page::JSTREE_CLOSED ) );

		$navman->openSection( $this->pages['parent'] );

		$this->assertTrue( false !== strpos( $page->attribute('class'), BUN_Navman_Page::JSTREE_OPEN ) );

	}

	/**
	 * @group bu-navigation-actions
	 *
	 * @todo utilize page object for class attributes
	 */
	public function test_expand_all(){

		$this->pre_test_setup();

		$navman = new BUN_Navman_Page( $this, 'page' );

		// Get all closed sections (will not open/load hidden sections)
		$sections = $navman->getSections( );

		foreach( $sections as $section ) {
			$this->assertRegExp( '/' . BUN_Navman_Page::JSTREE_CLOSED . '/', $section->attribute('class') );
		}

		$navman->expandAll();

		foreach( $sections as $section ) {
			$this->assertRegExp( '/' . BUN_Navman_Page::JSTREE_OPEN . '/', $section->attribute('class') );
		}

	}

	/**
	 * @group bu-navigation-actions
	 *
	 * @todo utilize page object for class attributes
	 */
	public function test_collapse_all() {

		$this->pre_test_setup();

		$navman = new BUN_Navman_Page( $this, 'page' );

		// Get sections, loading and opening all closed sections first
		$sections = $navman->getSections( true );

		foreach( $sections as $section ) {
			$this->assertRegExp( '/' . BUN_Navman_Page::JSTREE_OPEN . '/', $section->attribute('class') );
		}

		$navman->collapseAll();

		foreach( $sections as $section ) {
			$this->assertRegExp( '/' . BUN_Navman_Page::JSTREE_CLOSED . '/', $section->attribute('class') );
		}

	}

	/* Page movement */

	/**
	 * @group bu-navigation-moves
	 */
	public function test_move_page_before() {

		$this->markTestIncomplete('Moving posts is only partially implemented due to webdriver issues.');

		$this->pre_test_setup();

		$navman = new BUN_Navman_Page( $this, 'page' );
		$src_id = $this->pages['grandchild_two'];
		$dest_id = $this->pages['grandchild_one'];

		// Open parent sections and move page
		$navman->openSection( $this->pages['parent'] );
		$navman->openSection( $this->pages['child'] );
		$navman->movePost( $src_id, $dest_id, 'before' );

		// Verify move
		$navman->assertMovedBefore( $src_id, $dest_id );

	}

	/**
	 * @group bu-navigation-moves
	 */
	public function test_move_page_after() {

		$this->pre_test_setup();

		$navman = new BUN_Navman_Page( $this, 'page' );
		$src_id = $this->pages['grandchild_one'];
		$dest_id = $this->pages['grandchild_two'];

		// Open parent sections and move page
		$navman->openSection( $this->pages['parent'] );
		$navman->openSection( $this->pages['child'] );
		$navman->movePost( $src_id, $dest_id, 'after' );

		// Verify move
		$navman->assertMovedAfter( $src_id, $dest_id );

	}

	/**
	 * @group bu-navigation-moves
	 */
	public function test_move_page_inside() {

		$this->pre_test_setup();

		$navman = new BUN_Navman_Page( $this, 'page' );
		$src_id = $this->pages['grandchild_two'];
		$dest_id = $this->pages['grandchild_one'];

		// Open parent sections and move page
		$navman->openSection( $this->pages['parent'] );
		$navman->openSection( $this->pages['child'] );
		$navman->movePost( $src_id, $dest_id, 'inside' );

		// Verify move
		$navman->assertMovedInside( $src_id, $dest_id );

	}

}

?>