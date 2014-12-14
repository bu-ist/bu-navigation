<?php

/**
 * Coverage for the BU_Navigation_Plugin class
 *
 * @group bu
 * @group bu-navigation
 */
class Test_BU_Navigation_Plugin extends BU_Navigation_UnitTestCase {

	/**
	 * @group bu-navigation-features
	 */
	public function test_supports_by_theme_support() {

		$features = $this->plugin->features();

		foreach( $features as $feature => $default ) {

			$this->assertEquals( $default, $this->plugin->supports( $feature ) );

			add_theme_support( 'bu-navigation-' . $feature );
			$this->assertTrue( $this->plugin->supports( $feature ) );

			remove_theme_support( 'bu-navigation-' . $feature );
			$this->assertEquals( $default, $this->plugin->supports( $feature ) );

		}

	}

	/**
	 * @todo figure out how to deal with constants in isolation...
	 *
	 * @group bu-navigation-features
	 */
	public function test_supports_by_constant() {

		$features = $this->plugin->features();

		foreach( $features as $feature => $default ) {

			$this->assertEquals( $default, $this->plugin->supports( $feature ) );

			define( 'BU_NAVIGATION_SUPPORTS_' . strtoupper( $feature ), true );
			$this->assertTrue( $this->plugin->supports( $feature ) );

		}

	}

}
