<?php

require_once 'page-objects/navman.php';

/**
 * @group bu
 * @group bu-navigation
 * @group bu-navigation-navman
 *
 * @todo
 * 	- custom post types
 * 	- section editing tests
 *  - fix move before/after
 *  - post status badges (draft, pending, restricted, not in nav)
 *
 *	- LOGIC TESTS:
 *		- Allow top level
 *			- CAN't Add top level links
 *			- CAN't move non-top level content to top-level
 *			- CAN move existing top-level content back and forth
 *		- Inserting links after posts
 *		- Status badges recalculate correctly on move
 *		- Counts change correctly on move
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

	/* Test types */

	/**
	 * @group bu-navigation-types
	 * @group bu-navigation-single
	 */
	public function test_leaf() {

		$navman = new BUN_Navman_Page( $this, 'page' );
		$page = $navman->getPage( $this->pages['last_page'] );

		$this->assertEquals( $page->getAttribute('rel'), BUN_Navman_Page::TYPES_PAGE );

	}

	/**
	 * @group bu-navigation-types
	 */
	public function test_link() {

		$navman = new BUN_Navman_Page( $this, 'page' );
		$page = $navman->getPage( $this->pages['google'] );

		$this->assertEquals( $page->getAttribute('rel'), BUN_Navman_Page::TYPES_LINK );

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
		$navman->movePost( $src_id, $dest_id, 'before' );

		// Verify move
		// $navman->assertMovedBefore( $src_id, $dest_id );

		$this->markTestIncomplete('Moving posts is only partially implemented due to webdriver issues.');

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
		$navman->movePost( $src_id, $dest_id, 'after' );

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
		$navman->movePost( $src_id, $dest_id, 'inside' );

		// Verify move
		$navman->assertMovedInside( $src_id, $dest_id );

	}

	/**
	 * @group bu-navigation-actions
	 * @group bu-navigation-edit-options
	 */
	public function test_edit_page() {

		$navman = new BUN_Navman_Page( $this, 'page' );
		$edit_id = $this->pages['edit'];

		// Select and edit a page
		$navman->editPost( $edit_id );

		// Check new URL
		$url = $this->getCurrentUrl();
		$this->assertRegExp( "/wp\-admin\/post\.php\?action=edit&post=$edit_id/", $this->getCurrentUrl() );

	}

	/**
	 * @group bu-navigation-actions
	 * @group bu-navigation-edit-options
	 */
	public function test_trash_page() {

		$navman = new BUN_Navman_Page( $this, 'page' );
		$edit_id = $this->pages['edit'];

		// Select and remove a page
		$navman->movePostToTrash( $edit_id );

		$navman->assertPostNotExists( $edit_id );

	}

	/**
	 * @group bu-navigation-actions
	 * @group bu-navigation-links
	 */
	public function test_add_link() {

		$navman = new BUN_Navman_Page( $this, 'page' );

		$id = $navman->addLink( array( 'label' => 'Test Link', 'url' => 'http://www.bu.edu' ) );

		$navman->assertNewLinkExists( $id );

	}

	/**
	 * @group bu-navigation-actions
	 * @group bu-navigation-links
	 */
	public function test_edit_link() {

		$navman = new BUN_Navman_Page( $this, 'page' );
		$id = $this->pages['google'];

		$navman->editLink( $id, array( 'label' => 'Bing', 'url' => 'http://www.bing.com', 'target' => 'new' ) );

	}

	/**
	 * @group bu-navigation-actions
	 * @group bu-navigation-links
	 */
	public function test_delete_link() {

		$navman = new BUN_Navman_Page( $this, 'page' );
		$id = $this->pages['google'];

		$navman->movePostToTrash( $id );

		$navman->assertPostNotExists( $id );
	}

	// Count and status badges
	// @todo implement

	/**
	 * @group bu-navigation-statuses
	 */
	public function test_status_draft() {

		$this->markTestIncomplete();

	}


	/**
	 * @group bu-navigation-statuses
	 */
	public function test_status_pending() {

		$this->markTestIncomplete();

	}


	/**
	 * @group bu-navigation-statuses
	 */
	public function test_status_excluded() {

		$this->markTestIncomplete();

	}


	/**
	 * @group bu-navigation-statuses
	 */
	public function test_status_restricted() {

		$this->markTestIncomplete();

	}


	/**
	 * @group bu-navigation-statuses
	 */
	public function test_counts() {

		$this->markTestIncomplete();

	}

	/**
	 * @group bu-navigation-statuses
	 */
	public function test_exclude_inheritance() {

		$this->markTestIncomplete();

	}

	/**
	 * @group bu-navigation-statuses
	 */
	public function test_count_calculations() {

		$this->markTestIncomplete();

	}

	/**
	 * @group bu-navigation-actions
	 */
	public function test_dirty_alert_leave() {

		$navman = new BUN_Navman_Page( $this, 'page' );

		// Delete a page and then attempt to edit one, which prompts the diry warning
		$navman->movePostToTrash( $this->pages['edit'] );
		$navman->editPost( $this->pages['last_page'] );

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
		$navman->movePostToTrash( $this->pages['edit'] );
		$navman->editPost( $this->pages['last_page'] );

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
		$navman->movePost( $src_id, $dest_id, 'inside' );

		// Save and verify changes were committed
		$navman->save();
		$navman->assertChangesWereSaved();

		// Reopen sections
		$navman->openSection( $this->pages['parent'] );
		$navman->openSection( $this->pages['child'] );
		$navman->openSection( $this->pages['grandchild_one'] );

		// Verify move
		$this->assertMoveInside( $src_id, $dest_id );

	}

	/**
	 * @group bu-navigation-save
	 * @group bu-navigation-links
	 */
	public function test_save_with_links() {

		$navman = new BUN_Navman_Page( $this, 'page' );

		$id = $navman->addLink( array( 'label' => 'Test Link', 'url' => 'http://www.bu.edu' ) );

		$navman->assertNewLinkExists( $id );

		$navman->save();

		$navman->assertLinkExistsWithLabel( 'Test Link' );
	}

	/**
	 * @group bu-navigation-save
	 */
	public function test_save_with_deletions() {

		$navman = new BUN_Navman_Page( $this, 'page' );
		$id = $this->pages['parent'];

		// Remove page
		$navman->movePostToTrash( $id );

		// Save and verify changes were committed
		$navman->save();

		$navman->assertChangesWereSaved();
		$navman->assertPostNotExists( $id );

	}

 }