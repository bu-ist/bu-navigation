<?php

require_once 'page-objects/navman.php';

/**
 * @group bu
 * @group bu-navigation
 * @group bu-navigation-navman
 * 
 * @todo
 * 	- custom post types
 *  - context menus (depend on right click)
 * 	- edit link (dependent on right click / context menu)
 * 	- section editing tests
 *  - fix move before/after
 * 
 *  AFTER REFACTORING:
 *  - post status labels (draft, pending, trash)
 *  - adjust test_save_with_deletions to pass (deleted post still shows up as it's in the trash)
 */
 class BU_Navigation_Navman_Test extends WP_SeleniumTestCase {

	public function setUp() {

		parent::setUp();

		// Generate test pages and store ID's for test cases
		$pid_one = $this->factory->post->create(array('post_title' => 'Parent page', 'post_type' => 'page' ) );
		$pid_two = $this->factory->post->create(array('post_title' => 'Child page', 'post_parent' => $pid_one, 'post_type' => 'page' ) );
		$pid_three = $this->factory->post->create(array('post_title' => 'Grand child page 1', 'post_parent' => $pid_two, 'post_type' => 'page' ) );
		$pid_four = $this->factory->post->create(array('post_title' => 'Grand child page 2', 'post_parent' => $pid_two, 'post_type' => 'page' ) );
		$pid_five = $this->factory->post->create(array('post_title' => 'Hidden page', 'post_type' => 'page' ) );
		$pid_six = $this->factory->post->create(array('post_title' => 'Edit and Delete me', 'post_type' => 'page' ) );
		$pid_seven = $this->factory->post->create(array('post_title' => 'Last page', 'post_type' => 'page' ) );

		$this->pages = array(
			'parent' => $pid_one,
			'child' => $pid_two,
			'grandchild_one' => $pid_three,
			'grandchild_two' => $pid_four,
			'hidden' => $pid_five,
			'edit' => $pid_six,
			'last_page' => $pid_seven
			);

		// Hide last page
		update_post_meta( $pid_five, '_bu_cms_navigation_exclude', "1" );

	}

	public function tearDown() {

		parent::tearDown();

		foreach( $this->pages as $slug => $id ) {
			wp_delete_post( $id, true );
		}

	}

	/* Test types */

	/**
	 * @group bu-navigation-types
	 */ 
	public function test_leaf() {

		$navman = new BUN_Navman_Page( $this, 'page' );
		$page = $navman->getPage( $this->pages['last_page'] );
		
		$this->assertEquals( $page->getAttribute('rel'), BUN_Navman_Page::TYPES_PAGE );

	}

	/**
	 * @group bu-navigation-types
	 * 
	 * Post 3.3, _update_blog_date_on_post_publish in includes/ms-blogs.php throws an error that we need to supress here
	 * Error occurs because it attempts to grab a post type object for 'link', which is not a registerd post type
	 * 
	 * @expectedException PHPUnit_Framework_Error
	 */ 
	public function test_link() {

		$id = wp_insert_post(array('post_title' => 'Google', 'post_type' => 'link','post_content' => 'http://www.google.com', 'post_status' => 'publish' ));

		$navman = new BUN_Navman_Page( $this, 'page' );
		$page = $navman->getPage( $id );
		
		$this->assertEquals( $page->getAttribute('rel'), BUN_Navman_Page::TYPES_LINK );

		wp_delete_post( $id, true );
	}

	/**
	 * @group bu-navigation-types
	 */ 
	public function test_section() {

		$navman = new BUN_Navman_Page( $this, 'page' );
		$page = $navman->getPage( $this->pages['parent'] );
		
		$this->assertEquals( $page->getAttribute('rel'), BUN_Navman_Page::TYPES_SECTION );

	}

	/**
	 * ACL - Restricted
	 */ 
	// public function test_restricted_page() {

	// }

	/**
	 * BU Section Editing - Denied
	 */
	// public function test_denied_page() {

	// } 

	/* Basic tree interactions */

	/**
	 * @group bu-navigation-actions
	 * 
	 * @todo utilize page object for class attributes
	 */ 
	public function test_hover() {

		$navman = new BUN_Navman_Page( $this, 'page' );
		$page = $navman->getPage( $this->pages['parent'], 'a' );

		$this->moveTo( $page );

		$this->assertTrue( false !== strpos( $page->getAttribute('class'), BUN_Navman_Page::JSTREE_HOVERED ) );

	}

	/**
	 * @group bu-navigation-actions
	 * @todo utilize page object for class attributes
	 */ 
	public function test_select() {

		$navman = new BUN_Navman_Page( $this, 'page' );
		$page = $navman->getPage( $this->pages['parent'], 'a' );

		$page->click();

		$this->assertTrue( false !== strpos( $page->getAttribute('class'), BUN_Navman_Page::JSTREE_CLICKED ) );

	}

	/**
	 * @group bu-navigation-actions
	 * 
	 * @todo utilize page object for class attributes
	 */ 
	public function test_open() {
		
		$navman = new BUN_Navman_Page( $this, 'page' );
		$page = $navman->getPage( $this->pages['parent'] );

		$this->assertTrue( false !== strpos( $page->getAttribute('class'), BUN_Navman_Page::JSTREE_CLOSED ) );

		$navman->openSection( $this->pages['parent'] );

		$this->assertTrue( false !== strpos( $page->getAttribute('class'), BUN_Navman_Page::JSTREE_OPEN ) );

	}


	/**
	 * @group bu-navigation-actions
	 * 
	 * @todo utilize page object for class attributes
	 */ 
	public function test_expand_all(){

		$navman = new BUN_Navman_Page( $this, 'page' );

		// Get all closed sections (will not open/load hidden sections)
		$sections = $navman->getSections( );

		foreach( $sections as $section ) {
			$this->assertRegExp( '/' . BUN_Navman_Page::JSTREE_CLOSED . '/', $section->getAttribute('class') );
		}

		$navman->expandAll();

		foreach( $sections as $section ) {
			$this->assertRegExp( '/' . BUN_Navman_Page::JSTREE_OPEN . '/', $section->getAttribute('class') );
		}

	}

	/**
	 * @group bu-navigation-actions
	 * 
	 * @todo utilize page object for class attributes
	 */ 
	public function test_collapse_all() {

		$navman = new BUN_Navman_Page( $this, 'page' );

		// Get sections, loading and opening all closed sections first
		$sections = $navman->getSections( true );

		foreach( $sections as $section ) {
			$this->assertRegExp( '/' . BUN_Navman_Page::JSTREE_OPEN . '/', $section->getAttribute('class') );
		}

		$navman->collapseAll();

		foreach( $sections as $section ) {
			$this->assertRegExp( '/' . BUN_Navman_Page::JSTREE_CLOSED . '/', $section->getAttribute('class') );
		}

	}

	/* Page movement */

	/**
	 * @group bu-navigation-moves
	 */ 
	public function test_move_page_before() {

		$navman = new BUN_Navman_Page( $this, 'page' );
		$src_id = $this->pages['grandchild_two'];
		$dest_id = $this->pages['grandchild_one'];

		// Open parent sections and move page
		$navman->openSection( $this->pages['parent'] );
		$navman->openSection( $this->pages['child'] );
		$navman->movePage( $src_id, $dest_id, 'before' );

		// Verify move
		$navman->assertMovedBefore( $src_id, $dest_id );

	}

	/**
	 * @group bu-navigation-moves
	 */ 
	public function test_move_page_after() {

		$navman = new BUN_Navman_Page( $this, 'page' );
		$src_id = $this->pages['grandchild_one'];
		$dest_id = $this->pages['grandchild_two'];

		// Open parent sections and move page
		$navman->openSection( $this->pages['parent'] );
		$navman->openSection( $this->pages['child'] );
		$navman->movePage( $src_id, $dest_id, 'after' );

		// Verify move
		$navman->assertMovedAfter( $src_id, $dest_id );

	}

	/**
	 * @group bu-navigation-moves
	 */ 
	public function test_move_page_inside() {

		$navman = new BUN_Navman_Page( $this, 'page' );
		$src_id = $this->pages['grandchild_two'];
		$dest_id = $this->pages['grandchild_one'];

		// Open parent sections and move page
		$navman->openSection( $this->pages['parent'] );
		$navman->openSection( $this->pages['child'] );
		$navman->movePage( $src_id, $dest_id, 'inside' );

		// Verify move
		$navman->assertMovedInside( $src_id, $dest_id );

	}

	/**
	 * @group bu-navigation-actions
	 */
	public function test_edit_page() {

		$navman = new BUN_Navman_Page( $this, 'page' );

		// Select and edit a page
		$navman->editPage( $this->pages['edit'] );

		// Check new URL
		$url = $this->getCurrentUrl();
		$edit_id = $this->pages['edit'];
		$this->assertRegExp( "/wp\-admin\/post\.php\?action=edit&post=$edit_id/", $this->getCurrentUrl() );

	}

	/**
	 * @group bu-navigation-actions 
	 * @expectedException PHPUnit_Framework_AssertionFailedError
	 */
	public function test_delete_page() {

		$navman = new BUN_Navman_Page( $this, 'page' );

		// Select and remove a page
		$navman->deletePage( $this->pages['edit'] );

		// Will throw an expected PHPUnit_Framework_AssertionFailedError
		$navman->getPage( $this->pages['edit'] );

	}

	/**
	 * @group bu-navigation-links 
	 */ 
	public function test_add_link() {

		$navman = new BUN_Navman_Page( $this, 'page' );

		$id = $navman->addLink( 'Test Link', 'http://www.bu.edu' );

		$navman->assertNewLinkExists( $id );

	}

	// @todo need to figure out how to right click first
	// public function test_edit_link() {

	// }

	// @todo need to figure out how to right click first
	// public function test_delete_link() {

	// }

	/**
	 * @group bu-navigation-actions
	 */
	public function test_dirty_alert_leave() {

		$navman = new BUN_Navman_Page( $this, 'page' );

		// Delete a page and then attempt to edit one, which prompts the diry warning
		$navman->deletePage( $this->pages['edit'] );
		$navman->editPage( $this->pages['last_page'] );

		// Confirm presence of alert
		$txt = $this->getAlertText();
		$this->assertEquals( $txt, 'This page is asking you to confirm that you want to leave - data you have entered may not be saved.');

		// Accept alert (leave this page)
		$this->acceptAlert();

		sleep(1);

		// Confirm new URL
		$url = $this->getCurrentUrl();
		$edit_id = $this->pages['last_page'];
		$this->assertRegExp( "/wp\-admin\/post\.php\?action=edit&post=$edit_id/", $this->getCurrentUrl() );

	}

	/**
	 * @group bu-navigation-actions
	 */
	public function test_dirty_alert_stay() {

		$navman = new BUN_Navman_Page( $this, 'page' );

		$url_before = $this->getCurrentUrl();

		// Delete a page and then attempt to edit one, which prompts the diry warning
		$navman->deletePage( $this->pages['edit'] );
		$navman->editPage( $this->pages['last_page'] );

		// Confirm presence of alert
		$txt = $this->getAlertText();
		$this->assertEquals( $txt, 'This page is asking you to confirm that you want to leave - data you have entered may not be saved.');

		// Dismiss alert (stay on this page)
		$this->dismissAlert();
		sleep(1);

		// Confirm URL is the same
		$url_after = $this->getCurrentUrl();
		$this->assertEquals( $url_before, $url_after );

	}

	/**
	 * @group bu-navigation-save
	 */
	public function test_save_changes() {

		$navman = new BUN_Navman_Page( $this, 'page' );

		// Save and verify changes were committed
		$navman->save();
		$navman->assertChangesWereSaved();

	}

	/**
	 * @group bu-navigation-save
	 * @group bu-navigation-moves
	 */
	public function test_save_with_moves() {

		$navman = new BUN_Navman_Page( $this, 'page' );

		$src_id = $this->pages['grandchild_two'];
		$dest_id = $this->pages['grandchild_one'];

		// Open parent sections
		$navman->openSection( $this->pages['parent'] );
		$navman->openSection( $this->pages['child'] );
		$navman->movePage( $src_id, $dest_id, 'inside' );

		// Save and verify changes were committed
		$navman->save();
		$navman->assertChangesWereSaved();

		// Reopen sections
		$navman->openSection( $this->pages['parent'] );
		$navman->openSection( $this->pages['child'] );
		$navman->openSection( $this->pages['grandchild_one'] );

		// Verify move
		$selector = sprintf("#p%s > ul > #p%s", $dest_id, $src_id );
		$newPage = $this->findElementBy( LocatorStrategy::cssSelector, $selector );
		$this->assertNotNull( $newPage );

	}

	/**
	 * @group bu-navigation-save
	 * @group bu-navigation-links
	 */ 
	public function test_save_with_links() {

		$navman = new BUN_Navman_Page( $this, 'page' );

		$id = $navman->addLink( 'Test Link', 'http://www.bu.edu' );

		$navman->assertNewLinkExists( $id );

		$navman->save();

		$navman->assertLinkExistsWithLabel( 'Test Link' );
	}

	/**
	 * @group bu-navigation-save
	 * @expectedException PHPUnit_Framework_AssertionFailedError
	 */
	public function test_save_with_deletions() {

		$navman = new BUN_Navman_Page( $this, 'page' );

		// Remove page
		$navman->deletePage( $this->pages['parent'] );

		// Save and verify changes were committed
		$navman->save();
		$navman->assertChangesWereSaved();

		// Will throw an expected PHPUnit_Framework_AssertionFailedError
		$navman->getPage( $this->pages['parent'] );

	}

	/**
	 * Contextual menu 
	 * @todo right clicking is not implemented in webdriver at the moment
	 */

	// public function test_context_open() {

	// 	$navman = new BUN_Navman_Page( $this, 'page' );
	// 	$page = $navman->getPage( $this->pages['parent'], 'a' );

	// 	$menu = $this->getElement( LocatorStrategy::id, 'vakata-contextmenu' );

	// 	$this->assertEquals( $menu->getCssProperty('visibility'), 'hidden' ));

  // 		// Move the mouse and right click to open
	// 	$this->moveTo( $page, 5, 5 );
	// 	$this->buttonDown( 2 ); // 2 = right click
	// 	$this->buttonUp( 2 ); // 2 = right click

	// 	$this->assertEquals( $menu->getCssProperty('visibility'), 'visible' ));

	// }

	// public function test_context_edit() {

	// }

	// public function test_context_remove() {

	// }

 }