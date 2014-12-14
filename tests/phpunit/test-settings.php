<?php

/**
 * Coverage for the BU_Navigation_Settings class
 *
 * @group bu
 * @group bu-navigation
 * @group bu-navigation-settings
 */
class Test_BU_Navigation_Settings extends BU_Navigation_UnitTestCase {

	public function test_get() {

		$this->assertTrue( $this->plugin->settings->get( 'display' ) );
		$this->assertEquals( BU_NAVIGATION_PRIMARY_MAX, $this->plugin->settings->get( 'max_items' ) );
		$this->assertTrue( $this->plugin->settings->get( 'dive' ) );
		$this->assertEquals( BU_NAVIGATION_PRIMARY_DEPTH, $this->plugin->settings->get( 'depth' ) );
		$this->assertTrue( $this->plugin->settings->get( 'allow_top' ) );

	}

	public function test_get_all() {

		$expected_settings = array(
			'display' => true,
			'max_items' => BU_NAVIGATION_PRIMARY_MAX,
			'dive' => true,
			'depth' => BU_NAVIGATION_PRIMARY_DEPTH,
			'allow_top' => true
			);

		$this->assertSame( $expected_settings, $this->plugin->settings->get_all() );

	}

	public function test_update() {

		$updates = array(
			'display' => false,
			'max_items' => 3,
			'dive' => false,
			'depth' => 2,
			'allow_top' => false
			);

		$this->plugin->settings->update( $updates );
		$this->plugin->settings->clear();

		$this->assertSame( $updates, $this->plugin->settings->get_all() );

	}

}
