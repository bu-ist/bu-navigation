<?php

require_once dirname( __FILE__ ) . '/nav-selenium-testcase.php';
require_once dirname( __FILE__ ) . '/page-objects/navman.php';

/**
 * @group selenium
 * @group bu-navigation
 * @group bu-navigation-manager
 */
 class BU_Navigation_Manager_Test extends BU_Navigation_Selenium_Test_Case {

	public function setUp() {

		parent::setUp();

		// Load test posts
		$this->pages = $this->load_fixture( 'posts' );

	}

	public function tearDown() {

		parent::tearDown();

		$this->delete_test_posts();

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
