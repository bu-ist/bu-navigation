<?php

class BU_Navigation_UnitTestCase extends WP_UnitTestCase {

	public $plugin;

	public function setUp() {

		parent::setUp();

		// Store reference to navigation plugin instance
		$this->plugin = new BU_Navigation_Plugin();

	}

	/**
	 * Helper for loading fixtures from json files
	 */
	public function load_fixture( $type, $filename = null ) {

		if ( is_null( $filename ) ) {
			$filename = $type . '.json';
		}

		$path = dirname( __FILE__ ) . '/fixtures/' . $filename;
		$data = array();

		if ( is_readable( $path ) ) {
			$data = json_decode( file_get_contents( $path ), true );
			if ( is_array( $data ) && ! empty( $data ) ) {
				return call_user_func( array( $this, "load_test_$type" ), $data );
			}

		}

		$this->fail("Error loading fixture: $path");

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
	 * 	tests/fixtures/posts.json
	 */
	public function load_test_posts( $data, $parent_id = 0 ) {

		$posts = array();

		foreach( $data as $key => $post ) {

			$data = $post['data'];

			// Maybe set parent
			if( $parent_id )
				$data['post_parent'] = $parent_id;

			// Create post
			$id = $this->factory->post->create( $data );

			// Add any post meta
			$metadata = $post['metadata'];

			if( !empty( $metadata ) ) {
				foreach( $metadata as $meta_key => $meta_val ) {
					update_post_meta( $id, $meta_key, $meta_val );
				}
			}

			// Load children
			$children = $post['children'];
			if( ! empty( $children ) ) {
				$posts = array_merge( $posts, $this->load_test_posts( $children, $id ) );
			}

			// Cache internally for access during tests
			$posts[$key] = $id;

		}

		return $posts;

	}

	/**
	 * Helper to generate test section editing group
	 *
	 * @requires BU Section Editing plugin
	 */
	public function generate_section_group() {
		if ( ! is_plugin_active( 'bu-section-editing/bu-section-editing.php' ) )
			return;

		$section_editor = $this->factory->user->create(array('role'=>'section_editor'));
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
