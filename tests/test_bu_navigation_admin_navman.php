<?php

/**
 * BU Navigation - Admin - Navman (Edit Order page)
 *
 * @group bu
 * @group bu-navigation
 * @group bu-navigation-admin
 * @group bu-navigation-navman
 */
class BU_Navigation_Navman_Tests extends WP_UnitTestCase {

	public $plugin;
	public $navman;

	public $users;
	public $posts;

	public function setUp() {

		parent::setUp();

		$this->plugin = new BU_Navigation_Plugin();
		$this->plugin->load_admin();

		$this->navman = $this->plugin->admin->load_navman_page();
		$this->navman->reorder_tracker = new BU_Navigation_Reorder_Tracker('page');

		register_post_type( 'link', array('name' => 'Link') );

		// Setup users
		$this->users = array(
			'admin' => $this->factory->user->create(array('role'=>'administrator','user_email'=>'wpcms01@bu.edu')),
			'contrib' => $this->factory->user->create(array('role'=>'contributor','user_email'=>'wpcms02@bu.edu'))
			);

		// Setup posts
		$posts_json = file_get_contents( dirname(__FILE__) . '/data/test_posts.json');
		$posts = json_decode($posts_json, true);
		$this->load_test_posts( $posts );

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
			$this->posts[$key] = $id;

		}

	}

	public function test_can_publish_top_level() {

		wp_set_current_user( $this->users['admin'] );

		$this->assertTrue( $this->navman->can_publish_top_level() );

		// Don't allow top level posts
		$this->plugin->update_settings(array('allow_top'=>false));

		$this->assertFalse( $this->navman->can_publish_top_level() );

		// @todo section editor integration

	}

	public function test_can_edit() {

		wp_set_current_user( $this->users['admin'] );
		$this->assertTrue( $this->navman->can_edit( $this->posts['parent'] ) );
		$this->assertTrue( $this->navman->can_edit( $this->posts['google'] ) );

		wp_set_current_user( $this->users['contrib'] );
		$this->assertFalse( $this->navman->can_edit( $this->posts['parent'] ) );
		$this->assertFalse( $this->navman->can_edit( $this->posts['google'] ) );

		// @todo section editor integration

	}

	public function test_can_delete() {

		wp_set_current_user( $this->users['admin'] );
		$this->assertTrue( $this->navman->can_delete( $this->posts['parent'] ) );
		$this->assertTrue( $this->navman->can_delete( $this->posts['google'] ) );

		wp_set_current_user( $this->users['contrib'] );
		$this->assertFalse( $this->navman->can_delete( $this->posts['parent'] ) );
		$this->assertFalse( $this->navman->can_delete( $this->posts['google'] ) );

		// @todo section editor integration

	}

	public function test_can_place_in_section() {

		wp_set_current_user( $this->users['admin'] );

		// Simulate move to top level
		$post = get_post( $this->posts['grandchild_one'] );
		$post->post_parent = 0;

		$this->assertTrue( $this->navman->can_place_in_section( $post, $this->posts['child'] ) );

		// Don't allow top level posts
		$this->plugin->update_settings(array('allow_top'=>false));

		$this->assertFalse( $this->navman->can_place_in_section( $post, $this->posts['child'] ) );

		// Simulate previously in nav exception
		$this->assertTrue( $this->navman->can_place_in_section( $post, 0 ) );

		// @todo section editor integration

	}

	public function test_can_move() {

		wp_set_current_user( $this->users['admin'] );

		// Simulate move to top level
		$post = get_post( $this->posts['grandchild_one'] );
		$original = clone $post;

		$post->post_parent = 0;

		// Test different arguments
		$this->assertTrue( $this->navman->can_move( $post, $original ) );
		$this->assertTrue( $this->navman->can_move( $post->ID, $original ) );
		$this->assertTrue( $this->navman->can_move( $post->ID, $original->ID ) );
		$this->assertTrue( $this->navman->can_move( $post, $original->ID ) );

		// Don't allow top level posts
		$this->plugin->update_settings(array('allow_top'=>false));
		$this->assertFalse( $this->navman->can_move( $post, $original ) );

		// Fake original location was top level
		$original->post_parent = 0;
		$this->assertTrue( $this->navman->can_move( $post, $original ) );

		// @todo section editor integration

	}

	public function test_can_move_contrib() {

		wp_set_current_user( $this->users['contrib'] );

		// Simulate move to top level
		$post = get_post( $this->posts['grandchild_one'] );
		$original = clone $post;

		$post->post_parent = 0;

		// Test different arguments
		$this->assertFalse( $this->navman->can_move( $post, $original ) );
		$this->assertFalse( $this->navman->can_move( $post->ID, $original ) );
		$this->assertFalse( $this->navman->can_move( $post->ID, $original->ID ) );
		$this->assertFalse( $this->navman->can_move( $post, $original->ID ) );

		// Don't allow top level posts
		$this->plugin->update_settings(array('allow_top'=>false));
		$this->assertFalse( $this->navman->can_move( $post, $original ) );

		// Fake original location was top level
		$original->post_parent = 0;
		$this->assertFalse( $this->navman->can_move( $post, $original ) );

		// @todo section editor integration

	}

	public function test_process_deletions() {

		wp_set_current_user( $this->users['admin'] );

		// array of post ID's to delete
		$deletions = array( $this->posts['grandchild_two'], $this->posts['google'] );

		$result =$this->navman->process_deletions( $deletions );

		$this->assertNotInstanceOf( 'WP_Error', $result );

		$deleted_page = get_post( $this->posts['grandchild_two'] );
		$this->assertEquals( 'trash', $deleted_page->post_status );

		$deleted_link = get_post( $this->posts['google'] );
		$this->assertNull( $deleted_link );

		// @todo section editor integration

	}

	public function test_process_deletions_contrib() {

		wp_set_current_user( $this->users['contrib'] );

		// array of post ID's to delete
		$deletions = array( $this->posts['grandchild_two'], $this->posts['google'] );

		$result =$this->navman->process_deletions( $deletions );

		$this->assertInstanceOf( 'WP_Error', $result );

		$deleted_page = get_post( $this->posts['grandchild_two'] );
		$this->assertEquals( 'publish', $deleted_page->post_status );

		$deleted_link = get_post( $this->posts['google'] );
		$this->assertEquals( 'publish', $deleted_link->post_status );

		// @todo section editor integration

	}

	public function test_process_insertions() {
		$this->markTestIncomplete();
	}

	public function test_process_moves() {
		$this->markTestIncomplete();
	}

	public function test_process_updates() {
		$this->markTestIncomplete();
	}

}