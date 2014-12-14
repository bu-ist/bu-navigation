<?php

/**
 * Coverage functionaly privided in the extras directory
 *
 * @group bu
 * @group bu-navigation
 * @group bu-navigation-extras
 */
class Test_BU_Navigation_Extras extends BU_Navigation_UnitTestCase {

	/**
	 * Ensure that excluded posts and links behave as expected
	 */
	public function test_navigation_exclude() {

		$parent = $this->factory->post->create( array( 'post_type' => 'page' ) );
		$child = $this->factory->post->create( array( 'post_type' => 'page', 'post_parent' => $parent ) );
		$hidden_child = $this->factory->post->create( array( 'post_type' => 'page','post_parent' => $parent ) );
		$hidden_link = $this->factory->post->create( array( 'post_type' => 'bu_link','post_parent' => $parent ) );

		// 1. Test default value (do not exclude)
		$posts = bu_navigation_get_posts();
		$this->assertCount( 4, $posts );

		// 2. Test explicit show for posts and nav links
		update_post_meta( $hidden_child, BU_NAV_META_PAGE_EXCLUDE, 0 );
		update_post_meta( $hidden_link, BU_NAV_META_PAGE_EXCLUDE, 0 );
		$posts = bu_navigation_get_posts();
		$this->assertCount( 4, $posts );

		// 3. Test explicit hide for posts and nav links
		update_post_meta( $hidden_child, BU_NAV_META_PAGE_EXCLUDE, 1 );
		update_post_meta( $hidden_link, BU_NAV_META_PAGE_EXCLUDE, 1 );
		$posts = bu_navigation_get_posts();
		$this->assertCount( 2, $posts );

		// 4. Test sans filter
		remove_filter( 'bu_navigation_filter_pages', 'bu_navigation_filter_pages_exclude' );
		$posts = bu_navigation_get_posts();
		$this->assertCount( 4, $posts );
		add_filter( 'bu_navigation_filter_pages', 'bu_navigation_filter_pages_exclude' );
	}

}