<?php

/**
 * Utility class for tracking and reordering sections affected by moves
 */
class BU_Navigation_Reorder_Tracker {

	public $post_types;
	public $already_moved;
	public $errors;
	public $plugin;

	public function __construct( $post_types, $plugin = null ) {

		if( is_null( $plugin ) )
			$this->plugin = $GLOBALS['bu_navigation_plugin'];

		if( is_string( $post_types ) ) {
			$post_types = explode(',', $post_types );
		} else {
			$post_types = (array) $post_types;
		}

		if( $this->plugin->supports( 'links' ) && in_array( 'page', $post_types ) ) {
			array_push( $post_types, BU_NAVIGATION_LINK_POST_TYPE );
		}

		$this->post_types = $post_types;

		$this->already_moved = array();
		$this->errors = array();

	}

	/**
	 * Mark a post as moved
	 *
	 * @param object $post post object to mark as moved
	 */
	public function mark_post_as_moved( $post ) {

		// Push update to the map that triggers parent reordering
		if( ! array_key_exists( $post->post_parent, $this->already_moved ) ) {
			$this->already_moved[$post->post_parent] = array( 'ids' => array(), 'positions' => array() );
		}

		$this->already_moved[$post->post_parent]['ids'][] = $post->ID;
		$this->already_moved[$post->post_parent]['positions'][] = $post->menu_order;

	}

	/**
	 * Mark a section as in need of reordering
	 *
	 * @param int $parent ID of post that needs children to be reordered
	 */
	public function mark_section_for_reordering( $parent ) {

		// Push update to the map that triggers parent reordering
		if( ! array_key_exists( $parent, $this->already_moved ) ) {
			$this->already_moved[$parent] = array( 'ids' => array(), 'positions' => array() );
		}

	}

	/**
	 * Handles reordering for sections that contain children that have updated post_parent or menu_order fields
	 *
	 * @todo write unit tests
	 */
	public function run() {
		// error_log('======== Navman Reordering =========');
		// error_log('Already moved: ' . print_r( $this->already_moved, true ) );

		global $wpdb;

		if( ! $this->has_moves() ) return;

		$result = true;

		// Fetch all posts in sections marked for reordering
		$sections = array_keys( $this->already_moved );
		$posts = bu_navigation_get_pages( array(
			'sections' => $sections,
			'suppress_filter_pages' => true,
			'post_status' => array('publish', 'private'),
			'post_types' => $this->post_types
			)
		);
		$posts_by_parent = bu_navigation_pages_by_parent( $posts );

		// error_log('Sections for reordering: ' . print_r( $posts_by_parent, true ) );

		// Loop through affected sections, reordering children as needed
		foreach( $posts_by_parent as $parent_id => $children ) {

			$position = 1;

			foreach( $children as $child ) {

				// Skip reorder for posts that were already moved
				if( ! $this->post_already_moved( $child ) ) {

					// Skip any positions that were previously set for a moved post
					while( $this->position_already_set( $position, $parent_id ) ) {

						// Skip over any positions that were set for previously updated children
						// error_log('Position has already been set, skipping ' . $position );
						$position++;

					}

					// Only update if menu order has actually changed
					if( $child->menu_order != $position ) {

						$stmt = $wpdb->prepare('UPDATE ' . $wpdb->posts . ' SET menu_order = %d WHERE ID = %d', $position, $child->ID );
						$rc = $wpdb->query($stmt);

						if( false === $rc ) {
							$error_msg = sprintf('Error updating menu order (%s) for post (%s): %s', $position, $child->post_title, $wpdb->last_error );
							error_log($error_msg);
							array_push( $this->errors, new WP_Error( 'bu_navigation_reorder_error', $error_msg ) );
						} else {

							wp_cache_delete( $child->ID, 'posts' );

						}

						// Temporary logging
						// error_log('Setting menu order for post "' . $child->post_title . '": ' . $position );

					} else {
						/* noop */
						// error_log('Skipping menu order update, already correct for post ' . $child->post_title . ' (' . $position . ')' );
					}

					$position++;

				} else {

					/* noop */
					// error_log('Child already has correct menu order, skipping myself (' . $child->post_title . ')');

				}

			}

		}

		// Global caches
		wp_cache_delete( 'get_pages', 'posts' );

		if( $this->has_errors() ) {
			$result = false;
		}

		return $result;

	}

	public function post_already_moved( $post ) {
		if( is_numeric( $post ) ) {
			$post = get_post( $post );
		}
		if( !is_object( $post ) ) {
			return false;
		}

		return isset( $this->already_moved[$post->post_parent] ) && in_array( $post->ID, $this->already_moved[$post->post_parent]['ids'] );
	}

	public function position_already_set( $position, $parent = 0 ) {

		return isset( $this->already_moved[$parent] ) && in_array( $position, $this->already_moved[$parent]['positions'] );

	}

	public function get_moved_posts() {
		$moved = array();

		foreach( $this->already_moved as $section => $moves ) {
			array_merge( $moved, $moves['ids'] );
		}

		return array_unique( $moved );
	}

	public function has_moves() {
		return ! empty( $this->already_moved );
	}

	public function has_errors() {
		return ! empty( $this->errors );
	}

}

?>
