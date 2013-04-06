<?php

require_once dirname( __FILE__ ) . '/nav-selenium-testcase.php';
require_once dirname( __FILE__ ) . '/page-objects/primary-navigation-settings.php';

/**
 * @group selenium
 * @group bu-navigation
 * @group bu-navigation-settings
 *
 * @todo
 *  - these tests are broken now that the primary navigation page requires theme support...
 * 	- create a dummy theme that utilizes bu_navigation_display_primrary and verify
 * 		settings on the front end
 *  - create a dummy theme that has BU_NAVIGATION_SUPPORTED_DEPTH set to test the
 * 		depth dropdown select for this page
 */
 class BU_Navigation_Primary_Settings_Test extends BU_Navigation_Selenium_Test_Case {

	/**
	* Primary navigation settings menu item is present and page loads correctly
	*/
	public function test_load_page() {

		$this->pre_test_setup();
		$page = new BUN_Settings_Page( $this );

	}

	/**
	* Toggle "Display primary navigation bar" option
	*/
	public function test_display_navbar_field() {
		$this->pre_test_setup();
		$page = new BUN_Settings_Page( $this );

		$value = $page->getOption('display');
		$this->assertTrue( $value );

		$page->setOptions( array( 'display' =>  0 ) );
		$page->save();

		$value = $page->getOption('display');
		$this->assertFalse( $value );

		$page->setOptions( array( 'display' =>  1 ) );
		$page->save();

		$value = $page->getOption('display');
		$this->assertTrue( $value );

	}

	/**
	* Set maximum items count
	*/
	public function test_max_items_field() {
		$this->pre_test_setup();
		$page = new BUN_Settings_Page( $this );

		// Test default value
		$value = $page->getOption('max_items');
		$this->assertEquals( 6, $value );

		// Set to new value
		$page->setOptions( array( 'max_items' =>  '10' ) );
		$page->save();

		$value = $page->getOption('max_items');
		$this->assertEquals( 10, $value );

		// Invalid submittion shouldn't go through
		$page->setOptions( array( 'max_items' =>  '-2' ) );
		$page->save_with_errors();

		$value = $page->getOption('max_items');
		$this->assertEquals( 10, $value );

		// Revert back to original value
		$page->setOptions( array( 'max_items' =>  '6' ) );
		$page->save();

		$value = $page->getOption('max_items');
		$this->assertEquals( 6, $value );

	}

	/**
	* Toggle "Use drop-down menus" option
	*/
	public function test_use_drop_downs_field() {
		$this->pre_test_setup();
		$page = new BUN_Settings_Page( $this );

		$value = $page->getOption('dive');
		$this->assertTrue( $value );

		$page->setOptions( array( 'dive' =>  0 ) );
		$page->save();

		$value = $page->getOption('dive');
		$this->assertFalse( $value );

		$page->setOptions( array( 'dive' =>  1 ) );
		$page->save();

		$value = $page->getOption('dive');
		$this->assertTrue( $value );

	}

	/**
	* Toggle "Allow Top-Level Pages" option
	*/
	public function test_allow_top_level_field() {
		$this->pre_test_setup();
		$page = new BUN_Settings_Page( $this );

		$value = $page->getOption('allow_top');
		$this->assertTrue( $value );

		$page->setOptions( array( 'allow_top' =>  0 ) );
		$page->save();

		$value = $page->getOption('allow_top');
		$this->assertFalse( $value );

		$page->setOptions( array( 'allow_top' =>  1 ) );
		$page->save();

		$value = $page->getOption('allow_top');
		$this->assertTrue( $value );

	}

}
