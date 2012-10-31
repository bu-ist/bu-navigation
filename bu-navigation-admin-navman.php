<?php
require_once(dirname(__FILE__) . '/classes.nav-tree.php' );

/*
@todo
	- test more thoroughly with multiple custom post types

@todo unit tests
	- lock methods
	- processing post methods

@todo selenium tests
	- locking behavior
	- check validation / invalid save
*/

/**
 * BU Navigation Admin Navigation Manager interface
 */ 
class BU_Navigation_Admin_Navman {

	public $page;

	private $plugin;

	const OPTION_LOCK_TIME = '_bu_navman_lock_time';
	const OPTION_LOCK_USER = '_bu_navman_lock_user';

	const MESSAGE_UPDATED = 1;
	const NOTICE_ERRORS = 1;
	const NOTICE_LOCKED =2;

	private $message_queue = array();

	public function __construct( $post_type ) {

		$this->plugin = $GLOBALS['bu_navigation_plugin'];
		$this->post_type = $post_type;

		// Attach WP actions/filters
		$this->register_hooks();

	}

	/**
	* Attach WP actions and filters utilized by our meta boxes
	*/ 
	public function register_hooks() {

		add_action('admin_menu', array( $this, 'register_menu' ) );
		add_action('admin_enqueue_scripts', array( $this, 'add_scripts' ) );

		add_filter('bu_nav_tree_view_format_post_bu_navman', array( $this, 'format_post'), 10, 2 );

	}

	/**
	 * Add "Edit Order" submenu pages to allow editing the navigation of the supported post types
	 */
	public function register_menu() {
		global $pagenow;

		// Add "Edit Order" links to the submenu of each supported post type
		$post_types = bu_navigation_supported_post_types();

		foreach( $post_types as $pt ) {

			$parent_slug = 'edit.php?post_type=' . $pt;
			$post_type = get_post_type_object( $pt );

			if ( $post_type->map_meta_cap ) {
				if ( current_user_can( $post_type->cap->edit_published_posts ) ) {
					$cap = $post_type->cap->edit_published_posts;
				} else {
					$cap = 'edit_' . $pt . '_in_section';
				}
			} else {
				if ( current_user_can( $post_type->cap->edit_others_posts ) ) {
					$cap = $post_type->cap->edit_others_posts;
				} else {
					$cap = 'edit_' . $pt . '_in_section';
				}
			}

			$page = add_submenu_page(
				$parent_slug,
				__('Edit Order'),
				__('Edit Order'),
				$cap,
				'bu-navigation-manager',
				array( $this, 'render' )
				);

			$this->pages[] = $page;

			add_action('load-' . $page, array( $this, 'load' ) );

		}

		// @todo check if current page is navman before clearing
		$this->clear_lock();

	}

	/**
	 * Register dependent Javscript and CSS files
	 */ 
	public function add_scripts( $page ) {

		if( is_array( $this->pages ) && in_array( $page, $this->pages ) ) {
			
			$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';

			// Scripts
			wp_register_script('bu-jquery-validate', plugins_url('js/vendor/jquery.validate' . $suffix . '.js', __FILE__), array('jquery'), '1.8.1', true);
			wp_register_script('bu-navman', plugins_url('js/manage' . $suffix . '.js', __FILE__), array('bu-navigation','jquery-ui-dialog','bu-jquery-validate'), '0.3.1', true);

			// Setup dynamic script context for manage.js
			$post_types = ( $this->post_type == 'page' ? array( 'page', 'link' ) : array( $this->post_type ) );

			$script_context = array(
				'postTypes' => $post_types,
				'postStatuses' => array('draft','pending','publish'),
				'nodePrefix' => 'nm',
				'lazyLoad' => true,
				'showCounts' => true
				);
			// Navigation tree view will handle actual enqueuing of our script
			$treeview = new BU_Navigation_Tree_View( 'bu_navman', $script_context );
			$treeview->enqueue_script('bu-navman');

			// Styles
			if ( 'classic' == get_user_option( 'admin_color') ) {
				wp_enqueue_style ( 'bu-jquery-ui-css',  plugins_url( '/css/jquery-ui-classic.css', __FILE__ ), array(), '0.3' );
			} else {
				wp_enqueue_style ( 'bu-jquery-ui-css',  plugins_url( '/css/jquery-ui-fresh.css', __FILE__ ), array(), '0.3' );
			}

			wp_enqueue_style('bu-navman', plugins_url('css/manage.css', __FILE__), array(), '0.3');
			
		}
		
	}

	/**
	 * Handle admin page setup
	 */ 
	public function load() {

		// Save if post data is present
		$saved = $this->save();

		// Post/Redirect/Get
		if( ! is_null( $saved ) ) {

			// Prune redirect uri
			$url = remove_query_arg(array('message','notice'), wp_get_referer());

			// Notifications
			if( $saved === true ) $url = add_query_arg( 'message', 1 );
			else $url = add_query_arg( 'notice', 1 );

			wp_redirect( $url );

		}

		// Clear message queue
		$this->message_queue['message'] = array();
		$this->message_queue['notice'] = array();

		$this->setup_locks();
		$this->setup_notices();

	}

	/**
	 * Set and check user locks
	 */ 
	public function setup_locks() {

		// Attempt to set lock
		$this->set_lock();

		// Check the lock to see if there is a user currently editing this page
		$editing_user = $this->check_lock();

		// Push locked notice to admin_notices
		if( is_numeric( $editing_user ) ) {
			$user_detail = get_userdata(intval($editing_user));
			$notice = $this->get_notice( 'notice', self::NOTICE_LOCKED );
			$this->message_queue['notice'][] = sprintf( $notice, $user_detail->display_name );
		}

	}

	/**
	 * Add notices if we have any in the queue
	 */ 
	public function setup_notices() {

		$message_code = isset($_GET['message']) ? intval($_GET['message']) : 0;
		$notice_code = isset($_GET['notice']) ? intval($_GET['notice']) : 0;

		$message = $this->get_notice( 'message', $message_code );
		$notice = $this->get_notice( 'notice', $notice_code );

		if( $message ) $this->message_queue['message'][] = $message;
		if( $notice ) $this->message_queue['notice'][] = $notice;

		if( $this->message_queue['message'] || $this->message_queue['notice'] ) {
			add_action('admin_notices', array( $this, 'admin_notices' ) );
		}

	}

	/**
	 * Retrieve notice message by type and numeric code:
	 * 
	 * @param string $type the type of notice (either 'message' or 'notice')
	 * @param int $code the notice code (see const NOTICE_* and const MESSAGE_*)
	 */ 
	public function get_notice( $type, $code ) {

		$notices = apply_filters( 'bu_navman_notices', array(
			'message' => array(
				0 => '', // Unused. Messages start at index 1.
				1 => __('Your navigation changes have been saved')
			),
			'notice' => array(
				0 => '',
				1 => __('<strong>Error:</strong> Errors occurred while saving your navigation changes.'),
				2 => __('Warning: <strong>%s</strong> is currently editing this site\'s navigation.')
			)
		));

		if( array_key_exists( $type, $notices ) && array_key_exists( $code, $notices[$type] )) {
			return $notices[$type][$code];
		}

		return '';

	}

	/**
	 * Prints any messages or notices that we have stored in the message queue
	 */ 
	public function admin_notices() {

		foreach( $this->message_queue as $type => $messages ) {

			if( empty( $messages) )
				continue;

			if( $type == 'message' ) {
				echo '<div id="message" class="updated fade">';
			} else if( $type == 'notice' ) {
				echo '<div class="error">';
			}

			foreach( $messages as $msg ) {
				echo "<p>$msg</p>";
			}

			echo '</div>';

		}

	}
	
	/**
	 * Display navigation manager page
	 */ 
	public function render() {

		if( ! current_user_can( 'edit_pages' ) ) {
			wp_die('Cheatin, uh?');
		}

		if( is_null( $this->post_type ) ) {
			wp_die('Edit order page is not available for post type: ' . $this->post_type );
			return;
		}

		// Actual post type and post types to fetch with get pages (remove that one after context is dealt with)
		$post_type = $this->post_type;
		$post_types = ( $post_type == 'page' ? array('page', 'link') : array($post_type) );

		// Render interface
		include(BU_NAV_PLUGIN_DIR . '/interface/manage.php');

	}

	/**
	 * Handle $_POST submissions for navigation management page
	 * 
	 * @todo decide how best to handle failures
	 */ 
	public function save() {
		$saved = NULL;
		$errors = array();

		if( array_key_exists( 'bu_navman_save', $_POST ) ) {

			// error_log('===== Starting navman save =====');
			// $time_start = microtime(true);

			$saved = false;
			$reorder_map = array();

			// Process post removals
			$deletions = json_decode(stripslashes($_POST['navman-deletions']));
			$result = $this->process_deletions( $deletions );

			if( is_wp_error( $result ) ) {
				array_push( $errors, $result );
			}

			// Process link updates
			$updates = (array)json_decode(stripslashes($_POST['navman-updates']));
			$result = $this->process_updates( $updates );

			if( is_wp_error( $result ) ) {
				array_push( $errors, $result );
			}

			// Process link insertions
			$inserts = (array)json_decode(stripslashes($_POST['navman-inserts']));
			$result = $this->process_insertions( $inserts, $reorder_map );

			if( is_wp_error( $result ) ) {
				array_push( $errors, $result );
			}

			// Process moves
			$moves = (array)json_decode(stripslashes($_POST['navman-moves']));
			$result = $this->process_moves( $moves, $reorder_map );

			if( is_wp_error( $result ) ) {
				array_push( $errors, $result );
			}

			// Update menu order for affected children
			$this->reorder_affected_sections( $reorder_map );

			// error_log('Finished navman save in ' . sprintf('%f',(microtime(true) - $time_start)) . ' seconds');

			if (function_exists('invalidate_blog_cache')) invalidate_blog_cache();

			if( count( $errors ) == 0 ) {
				$saved = true;
			} else {
				// @todo do something with all the WP_Error objects
			}

		}

		return $saved;
	}

	/**
	 * Trashes posts that have been removed using the navman interface
	 * 
	 * @todo write unit tests
	 * 
	 * @param array $post_ids an array of post ID's for trashing
	 * @return bool|WP_Error $result the result of the post deletions
	 */ 
	public function process_deletions( $post_ids ) {
		// error_log('===== Processing deletions =====');
		// error_log('To delete: ' . print_r($post_ids,true ) );

		$result = null;
		$failures = array();

		if ( ( is_array( $post_ids ) ) && ( count( $post_ids ) > 0 ) ) {

			foreach( $post_ids as $id ) {

				// @todo current_user_can(...) check

				$deleted = wp_delete_post( (int) $id );

				if( ! $deleted ) {
					error_log(sprintf('[BU Navigation Navman] Unable to delete post %d', $id));
					array_push( $failures, $id );
				} else {
					// Temporary logging
					// error_log('[+] Post deleted: ' . $id );
				}

			}

		}

		if( count( $failures ) ) {
			$result = new WP_Error('Error deleting posts: ' . implode(', ', $failures ) );
		} else {
			$result = true;
		}

		return $result;
	}

	/**
	 * Updates posts (really just links at this time) that have been modified using the navman interface
	 * 
	 * @todo write unit tests
	 * 
	 * @param array $posts an array of posts which have been modified
	 * @return bool|WP_Error $result the result of the post updates
	 */ 
	public function process_updates( $posts ) {
		// error_log('===== Processing updates =====');
		// error_log('To update: ' . print_r($posts,true ) );

		$result = null;
		$failures = array();

		if( ( is_array( $posts ) ) && ( count( $posts ) > 0 ) ) {

			foreach( $posts as $post_id => $post ) {

				// @todo current_user_can(...) check

				$data = array(
					'ID' => (int) $post_id,
					'post_title' => $post->title,	// sanitize?
					'post_content' => $post->content // sanitize?
					);

				$id = wp_update_post( $data );

				if( $id ) {

					$target = ($post->meta->bu_link_target === 'new') ? 'new' : 'same';
					update_post_meta( $id, 'bu_link_target', $target );

					// Temporary logging
					// error_log('Link updated: ' . $post->title );

				} else {

					error_log(sprintf('[BU Navigation Navman] Could not update link: %s', print_r($post, true)));
					array_push( $failures, $post->title );				

				}

			}

		}

		if( count( $failures ) ) {
			$result = new WP_Error('Error updating posts: ' . implode(', ', $failures ) );
		} else {
			$result = true;
		}

		return $result;
	}

	/**
	 * Insert posts (really just links at this time) that have been added using the navman interface
	 * 
	 * @todo write unit tests
	 * 
	 * @param array $posts an array of posts which have been added
	 * @param array $reorder_map a map of data used to keep track of parents that will require reordering
	 * @return bool|WP_Error $result the result of the post insertions
	 */ 
	public function process_insertions( $posts, & $reorder_map ) {
		// error_log('===== Processing insertions =====');
		// error_log('To insert: ' . print_r($posts,true ) );

		$result = null;
		$failures = array();

		if( ( is_array( $posts ) ) && ( count( $posts ) > 0 ) ) {

			foreach( $posts as $post ) {

				$id = $post->ID;

				// @todo current_user_can(...) check

				// Special handling for new links -- need to get a valid post ID 
				if ( 'link' == $post->type ) {
					$data = array(
						'post_title' => $post->title,
						'post_content' => $post->content,
						'post_excerpt' => '',
						'post_status' => 'publish',
						'post_type' => 'link',
						'post_parent' => (int) $post->parent,
						'menu_order' => (int) $post->menu_order
						);

					$id = wp_insert_post($data);

					if( $id ) {

						$target = ($post->meta->bu_link_target === 'new') ? 'new' : 'same';
						update_post_meta($id, 'bu_link_target', $target );

						// Push update to the map that triggers parent reordering
						if( ! array_key_exists( $post->parent, $reorder_map ) ) {
							$reorder_map[$post->parent] = array( 'ids' => array(), 'positions' => array() );
						}

						$reorder_map[$post->parent]['ids'][] = $id;
						$reorder_map[$post->parent]['positions'][] = $post->menu_order;

						// Temporary logging
						// error_log('Link inserted: ' . $post->title . ' (' . $id . ')' );

					} else {

						error_log(sprintf('[BU Navigation Navman] Could not create link: %s', print_r($post, true)));
						array_push( $failures, $post->title );

					}

				}

			}

		}

		if( count( $failures ) ) {
			$result = new WP_Error('Error inserting posts: ' . implode(', ', $failures ) );
		} else {
			$result = true;
		}

		return $result;
	}

	/**
	 * Updates posts that have been moved using the navman interface
	 * 
	 * @todo write unit tests
	 * 
	 * @param array $posts an array of posts which have new menu_order or post_parent fields
	 * @param array $reorder_map a map of data used to keep track of parents that will require reordering
	 * @return bool|WP_Error $result the result of the post movements
	 */ 
	public function process_moves( $posts, & $reorder_map  ) {
		// error_log('===== Processing moves =====');
		// error_log('To move: ' . print_r($posts,true ) );

		global $wpdb;

		$result = null;
		$failures = array();
		
		if( ( is_array( $posts ) ) && ( count( $posts ) > 0 ) ) {

			do_action('bu_navman_pages_pre_move');

			foreach( $posts as $post_id => $post ) {

				// @todo current_user_can(...) check

				// Update post parent and menu order
				$id = wp_update_post(array('ID'=>$post->ID,'post_parent'=>$post->parent,'menu_order'=>$post->menu_order));

				if( $id ) {

					// Push update to the map that triggers parent reordering
					if( ! array_key_exists( $post->parent, $reorder_map ) ) {
						$reorder_map[$post->parent] = array( 'ids' => array(), 'positions' => array() );
					}

					$reorder_map[$post->parent]['ids'][] = $id;
					$reorder_map[$post->parent]['positions'][] = $post->menu_order;

					// Temporary logging
					// error_log('Post moved: ' . $post->ID );

				} else {

					error_log(sprintf('[BU Navigation Navman] Could not move post: %s', print_r($post, true)));
					array_push( $failures, $post->ID );

				}

			}

			do_action('bu_navman_pages_moved');

		}

		if( count( $failures ) ) {
			$result = new WP_Error('Error moving posts: ' . implode(', ', $failures ) );
		} else {
			$result = true;
		}

		return $result;

	}

	/**
	 * Handles reordering for sections that contain children that have updated post_parent or menu_order fields
	 * 
	 * @todo write unit tests
	 * 
	 * @param array $reorder_map a map of data needed to calculate reorders, keyed on parent ID's
	 */ 
	public function reorder_affected_sections( $reorder_map ) {
		global $wpdb;

		foreach( $reorder_map as $parent_id => $already_set ) {

			// Fetch children of affected sections for reordering 
			$post_types = ($this->post_type == 'page') ? array('page','link') : $this->post_type;
			$children = bu_navigation_get_pages( array( 
				'sections' => array($parent_id),
				'suppress_filter_pages' => true,
				'post_status' => array('draft','pending','publish'),
				'post_types' => $post_types
				)
			);

			$position = 1;

			foreach( $children as $child_id => $child ) {

				// Skip update for any children that were already handled in process_insertions and process_moves 
				if( in_array( $child_id, $already_set['ids'] ) ) {
					// error_log('Child already has correct menu order, skipping myself (' . $child->post_title . ')');
					continue;
				}

				// Skip over any positions that were set for previously updated children
				while( in_array( $position, $already_set['positions'] ) ) {
					// error_log('Position has already been set, skipping ' . $position );
					$position++;
				}

				$stmt = $wpdb->prepare('UPDATE ' . $wpdb->posts . ' SET menu_order = %d WHERE ID = %d', $position, $child_id );
				$rc = $wpdb->query($stmt);

				// Temporary logging
				// error_log('Setting menu order for post "' . $child->post_title . '": ' . $position );

				$position++;
			}

		}

	}

	/**
	 * @todo needs unit tests
	 */
	public function set_lock() {
		global $current_user;

		if( ! $this->check_lock() ) {
			$now = time();

			update_option( self::OPTION_LOCK_TIME , $now);
			update_option( self::OPTION_LOCK_USER , $current_user->ID);

		}
	}

	/**
	 * @todo needs unit tests
	 */
	public function check_lock() {
		global $current_user;

		$lock_time = get_option( self::OPTION_LOCK_TIME );
		$lock_user = get_option( self::OPTION_LOCK_USER );

		$time_window = apply_filters('wp_check_post_lock_window', AUTOSAVE_INTERVAL * 2);

		if ( $lock_time && $lock_time > time() - $time_window && $lock_user != $current_user->ID ) {
			return $lock_user;
		}

		return false;
	}

	/**
	 * @todo needs unit tests
	 */
	public function clear_lock() {
		global $current_user;

		$lock_user = get_option( self::OPTION_LOCK_USER );

		if( $lock_user == $current_user->ID ) {
			delete_option( self::OPTION_LOCK_TIME );
			delete_option( self::OPTION_LOCK_USER );
		}

	}

	/**
	 * Extra data to pass to DOM nodes for more accurate tracking of moves
	 * 
	 * @param array $p DOM data for jstree parsing
	 * @param array $post post object for page being formatted
	 * @return array filtered DOM data for jstree parsing
	 */
	public function format_post( $p, $post ) {

		// We want to store original page parent and menu order
		$p['metadata']['originalParent'] = $post->post_parent;
		$p['metadata']['originalOrder'] = $post->menu_order;

		return $p;
	}

}

?>