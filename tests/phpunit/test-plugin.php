<?php

/**
 * Coverage for the BU_Navigation_Plugin class
 *
 * @group bu
 * @group bu-navigation
 */
class Test_BU_Navigation_Plugin extends BU_Navigation_UnitTestCase {

	public function setUp(){
		parent::setUp();

		$this->plugin->load_widget();
	}

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

	public function test_widget_adaptive_with_section_title(){
		global $wp_widget_factory,
		       $post;

		/**
		 * IA
		 * - Page 1
	 	 *     - Subpage 1.A
	 	 *         - Subpage 1.A.1 (widget loaded here)
	 	 *             - Subpage 1.A.1.A (excluded from nav)
	 	 *         - Subpage 1.A.2
		 */

		$page1 = $this->factory->post->create( array( 'post_type' => 'page', 'post_title' => 'Page 1', ) );
		$page1_A = $this->factory->post->create( array( 'post_type' => 'page', 'post_title' => 'Subpage 1-A', 'post_parent' => $page1 ) );
		$page1_A_1 = $this->factory->post->create( array( 'post_type' => 'page', 'post_title' => 'Subpage 1-A-1 (Has Child)', 'post_parent' => $page1_A ) );
		$page1_A_1_A = $this->factory->post->create( array( 'post_type' => 'page', 'post_title' => 'Subpage 1-A-1-A', 'post_parent' => $page1_A_1 ) );
		$page1_A_2 = $this->factory->post->create( array( 'post_type' => 'page', 'post_title' => 'Subpage 1-A-2 (No Child)', 'post_parent' => $page1_A ) );

		// make sure we've got everyone
		$posts = bu_navigation_get_posts();
		$this->assertCount( 5, $posts );

		// set page1_A_1_A to hidden in nav
		update_post_meta( $page1_A_1_A, BU_NAV_META_PAGE_EXCLUDE, 1 );

		// make sure we're missing page1_A_1
		$posts = bu_navigation_get_posts();
		$this->assertCount( 4, $posts );

		// load perspective of page page1_A_1
		$post = get_post( $page1_A_1 );
		setup_postdata( $post );

		$instance = array(
			'navigation_title' => 'section',
			'navigation_title_text' => '',
			'navigation_title_url' => '',
			'navigation_style' => 'adaptive',
			);

		ob_start();
		the_widget( 'BU_Widget_Pages', $instance );
		$widget = ob_get_contents();
		ob_end_clean();

		$this->assertRegExp('/<h2.+Page 1<\/a>/i', $widget);
		$this->assertRegExp('/class="level_1".+Subpage 1-A<\/a>/is', $widget);

		// load perspective of page page1_A
		$post = get_post( $page1_A );
		setup_postdata( $post );

		$instance = array(
			'navigation_title' => 'section',
			'navigation_title_text' => '',
			'navigation_title_url' => '',
			'navigation_style' => 'adaptive',
			);

		ob_start();
		the_widget( 'BU_Widget_Pages', $instance );
		$widget = ob_get_contents();
		ob_end_clean();

		$this->assertRegExp('/<h2.+Page 1<\/a>/i', $widget);
		$this->assertRegExp('/class="level_1".+Subpage 1-A<\/a>/is', $widget);
	}
}
