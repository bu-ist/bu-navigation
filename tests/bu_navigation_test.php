<?php

class BU_Navigation_Test_Case extends WP_UnitTestCase {

	public $plugin;

	public function setUp() {

		parent::setUp();

		// Store reference to navigation plugin instance
		$this->plugin = new BU_Navigation_Plugin();

	}

	/**
	 * Helper method that processes a specially formatted array structure to insert
	 * posts and post metadata recursively
	 *
	 * Handles
	 *  - all post fields (through the "data" attribute)
	 *  - post meta data (by specifying "metakey" => "value" for the "metadata" attribute)
	 * 	- hierarchical posts (by nesting post data in the "children" attribute)
	 *
	 * Each post should be given a unique key, which will be used to store the post ID
	 * for reference during tests.
	 *
	 * See the file for an example:
	 * 	tests/data/test_posts.json
	 */
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

	public function create_test_group() {

		$section_editor = $this->factory->user->create(array('role'=>'section_editor','user_email'=>'wptest3@bu.edu'));
		$this->users['section_editor'] = $section_editor;

		$allowed = array( $this->posts['child'], $this->posts['grandchild_one'], $this->posts['grandchild_two'] );

		$groupdata = array(
			'name' => 'Test group',
			'description' => 'Test description',
			'users' => array($this->users['section_editor']),
			'perms' => array(
				'page' => array( 'allowed' => $allowed )
			)
		);

		$group = BU_Edit_Groups::get_instance()->add_group( $groupdata );

		$this->section_groups = array( 'test' => $group );

	}

}
