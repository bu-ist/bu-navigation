<?php

/**
 * Traditional unit tests for BU Navigation plugin
 * 
 * @group bu
 * @group bu-navigation
 */
class BU_Navigation_Settings_Test extends WP_UnitTestCase {

	public $plugin;

	public function setUp() {

		parent::setUp();

		// Store reference to navigation plugin instance
		$this->plugin = $GLOBALS['bu_navigation_plugin'];

	}

	/**
	 * @group bu-navigation-settings
	 */ 
	public function test_get_setting() {
		
		$this->assertTrue( $this->plugin->get_setting( 'display' ) );
		$this->assertEquals( BU_NAVIGATION_PRIMARY_MAX, $this->plugin->get_setting( 'max_items' ) );
		$this->assertTrue( $this->plugin->get_setting( 'dive' ) );
		$this->assertEquals( BU_NAVIGATION_PRIMARY_DEPTH, $this->plugin->get_setting( 'depth' ) );
		$this->assertFalse( $this->plugin->get_setting( 'allow_top' ) );

	}

	/**
	 * @group bu-navigation-settings
	 */ 
	public function test_get_settings() {

		$expected_settings = array(
			'display' => true,
			'max_items' => BU_NAVIGATION_PRIMARY_MAX,
			'dive' => true,
			'depth' => BU_NAVIGATION_PRIMARY_DEPTH,
			'allow_top' => false
			);

		$this->assertSame( $expected_settings, $this->plugin->get_settings() );

	}

	/**
	 * @group bu-navigation-settings
	 */ 
	public function test_update_settings() {

		$updates = array(
			'display' => false,
			'max_items' => 3,
			'dive' => false,
			'depth' => 2,
			'allow_top' => true
			);

		$this->plugin->update_settings( $updates );
		$this->plugin->clear_settings();

		$this->assertSame( $updates, $this->plugin->get_settings() );

	}

	/**
	 * @group bu-navigation-settings
	 */
	 public function test_depth_fix() {

	 	$this->assertEquals( BU_NAVIGATION_PRIMARY_DEPTH, $this->plugin->depth_fix() );

	 	$this->assertEquals( 5, $this->plugin->depth_fix( 5 ) );

	 	define( 'BU_NAVIGATION_SUPPORTED_DEPTH', 2 );
	 	$this->assertEquals( 2, $this->plugin->depth_fix( 6 ) );

	 }

	 public function test_update_with_invalid_depth() {

	 	define( 'BU_NAVIGATION_SUPPORTED_DEPTH', 5 );
	 	$updates = array( 'depth' => 10 );

	 	$this->plugin->update_settings( $updates );
		$this->plugin->clear_settings();

	 	$this->assertEquals( 5, $this->plugin->get_setting( 'depth' ) );

	 }

}