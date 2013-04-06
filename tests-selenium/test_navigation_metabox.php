<?php

require_once dirname( __FILE__ ) . '/nav-selenium-testcase.php';
require_once dirname( __FILE__ ) . '/page-objects/navigation-metabox.php';

/**
 * @group bu
 * @group bu-navigation
 * @group bu-navigation-metabox
 */
class BU_Navigation_Metabox extends BU_Navigation_Selenium_Test_Case {

	public function setUp() {

		parent::setUp();

		$this->pages = array();

		$this->pages[] = $this->factory->post->create(array('post_title'=>'Test page','post_type'=>'page'));

	}

	/**
	 * Edit new page
	 */
	public function test_new_page() {
		$this->pre_test_setup();
		$page = new BUN_EditPage( $this, array( 'post_type' => 'page' ) );
	}

	/**
	 * Edit existing page
	 */
	public function test_edit_page() {
		$this->pre_test_setup();
		$page = new BUN_EditPage( $this, array( 'post_id' => $this->pages[0] ) );

	}

	/**
	 * Test cases needed
	 *
	 * - metabox present
	 *
	 * Position
	 *
	 * - breadcrumbs / position label
	 * 		- for new page
	 * 		- for top level page
	 * 		- for child page
	 * - move page
	 * 		- modal opens on "Move page" click
	 * 		- opens to and selects current page
	 * 		- can't select other pages
	 * 		- can't drag other pages
	 * 		- can't move to top level if navigation label is displaying
	 * 		- can select current page
	 * 		- can move current page
	 * 		- modal cancel
	 * 		- modal save
	 * - section editing integration
	 * 		- can't move denied post
	 * 		- can't move allowed post to denied section
	 * 		- can't ever move to top-level (unless already top-level)
	 *
	 * Labels
	 *
	 * - change label
	 * - change display in navigation lists option
	 * - prohibit navigation label display for top level pages
	 */

}
