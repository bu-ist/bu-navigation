<?php
require_once(dirname(__FILE__) . '/bu-navigation-interface.php' );

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

	static $interface;
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

		// @todo test with multiple supported custom post types
		$this->post_type = $post_type;

		// Instantiate navman tree interface object
		$post_types = ( $this->post_type == 'page' ? array( 'page', 'link' ) : array( $this->post_type ) );

		$settings = array(
			'format' => 'navman',
			'postStatuses' => array( 'draft', 'pending', 'publish' ),
			'nodePrefix' => 'nm',
			'lazyLoad' => true,
			'showCounts' => true
			);

		self::$interface = new BU_Navman_Interface( $post_types, $settings );

		// Attach WP actions/filters
		$this->register_hooks();

	}

	/**
	* Attach WP actions and filters utilized by our meta boxes
	*/ 
	public function register_hooks() {

		add_action('admin_menu', array( $this, 'register_menu' ) );
		add_action('admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

	}

	/**
	 * Add "Edit Order" submenu pages to allow editing the navigation of the supported post types
	 * 
	 * @return void
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
	 * Enqueue dependent Javscript and CSS files
	 */ 
	public function enqueue_scripts( $page ) {

		// Enqueue navman styles and scripts
		if( is_array( $this->pages ) && in_array( $page, $this->pages ) ) {
			
			$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';

			// Load default navigation manager scripts & styles
			self::$interface->enqueue_scripts();

			// Vendor scripts & styles
			wp_enqueue_script('jquery-ui-dialog');
			wp_enqueue_script('bu-jquery-validate', plugins_url('js/vendor/jquery.validate' . $suffix . '.js', __FILE__), array('jquery'), '1.8.1', true);
			wp_enqueue_style('bu-jquery-ui-navman', plugins_url('css/vendor/jquery-ui/jquery-ui-1.8.13.custom.css', __FILE__), array(), '1.8.13');

			// Scripts and styles for this page
			wp_enqueue_script('bu-navman', plugins_url('js/manage' . $suffix . '.js', __FILE__), array('bu-navigation'), '0.3.1', true);
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
	 */ 
	public function save() {
		$saved = NULL;

		if (array_key_exists('bu_navman_save', $_POST)) {
//			error_log('Starting navman save:');
//			$time_start = microtime(true);
			
			$saved = $problems = false;

			// Process removals
			$pages_to_delete = json_decode(stripslashes($_POST['navman_delete']));

			$success = $this->process_deletions( $pages_to_delete );

			if (!$success)
				$problems = true;

			// Process link updates
			$pages_to_edit = (array)json_decode(stripslashes($_POST['navman_edits']));

			$success = $this->process_edits( $pages_to_edit );

			if (!$success)
				$problems = true;

			// Process moves
			$nodes = json_decode(stripslashes($_POST['navman_data']));

//			error_log('Finished navman json_decoding in' . sprintf('%f',(microtime(true) - $time_start)) . ' seconds');

			$updates = array();

			if ((is_array($nodes)) && (count($nodes) > 0)) {
				$parent_id = 0;
				$updates = $this->process_nodes($parent_id, $nodes);
			}

			$success = $this->process_moves( $updates );
			
//			error_log('Finished processing nodes in ' . sprintf('%f',(microtime(true) - $time_start)) . ' seconds');

			if (!$success)
				$problems = true;

			// @todo remove for new environment
			if (function_exists('invalidate_blog_cache')) invalidate_blog_cache();

//			error_log('Finished navman save in ' . sprintf('%f',(microtime(true) - $time_start)) . ' seconds');

			if (!$problems) $saved = true;
		}

		return $saved;
	}

	/**
	 * @todo implement
	 * @todo write unit tests
	 */ 
	public function process_deletions( $pages_to_delete ) {
		// error_log('=== Processing post removals ===');
		$success = true;

		if ((is_array($pages_to_delete)) && (count($pages_to_delete) > 0)) {

			foreach ($pages_to_delete as $id) {
				if (!wp_delete_post($id)) {
					error_log(sprintf('navman: Unable to delete post %d', $id));
					$success = false;
				} else {
					// error_log('Post deleted: ' . $id );
				}
			}
		}

		return $success;
	}

	/**
	 * @todo implement
	 * @todo write unit tests
	 */ 
	public function process_edits( $pages_to_edit ) {
		// error_log('=== Processing link updates ===');
		$success = true;

		if ((is_array($pages_to_edit)) && (count($pages_to_edit) > 0)) {
			foreach ($pages_to_edit as $p) {

				if( ! is_object( $p ) ) {
					error_log('Bad value for post: ');
					error_log(print_r($p,true));
					continue;
				}

				$data = array(
					'ID' => $p->ID,
					'post_title' => $p->title,
					'post_content' => $p->content
					);

				$id = wp_update_post($data);

				if (!$id) {
					$success = false;
					continue;
				}

				$target = ($p->meta->bu_link_target === 'new') ? 'new' : 'same';

				update_post_meta($id, 'bu_link_target', $target);

				// error_log("Link $id updated!");
				// error_log("Label: {$data['post_title']}, URL: {$data['post_content']}, Target: $target");
			}
		}

		// error_log('Finished processessing edits, return status: ' . $success );
		return $success;	
	}

	/**
	 * @todo write unit tests
	 */ 
	public function process_nodes( $parent_id, $nodes ) {

		// error_log("=== Processing nodes for post parent $parent_id ===");

		$updates = array();
		$updates[$parent_id] = array();

		foreach ($nodes as $node) {
			$id = $node->ID;

			// Special handling for new links -- need to get a valid post ID 
			if ( 'link' == $node->type && 'new' == $node->status ) {

				$data = array(
					'post_title' => $node->title,
					'post_content' => $node->content,
					'post_excerpt' => '',
					'post_status' => 'publish',
					'post_type' => 'link',
					'post_parent' => $parent_id,
					'menu_order' => 0	// will be updated
					);

				$id = wp_insert_post($data);

					// error_log('Insert ID: ' . $id );

				if ($id === 0) {
					error_log(sprintf('bu_navman_process_nodes could not create link: %s', print_r($node, true)));
					continue;
				}

				$target = ($node->meta->bu_link_target === 'new') ? 'new' : 'same';

				update_post_meta($id, 'bu_link_target', $target );
					// error_log("Inserting new link - ID: $id, Label: {$data['post_title']}, URL: {$data['post_content']}, Target: $target, Parent: $parent_id");

			}

			array_push($updates[$parent_id], $id);

			// Recurse through descendents
			if ((isset($node->children)) && (is_array($node->children)) && (count($node->children) > 0)) {
				$child_updates = $this->process_nodes( $id, $node->children );

				foreach ($child_updates as $page_id => $children) {
					$updates[$page_id] = $children;
				}
			}
		}

		// error_log('Finished processessing nodes, updates made: ' . count($updates) );
		return $updates;

	}

	/**
	 * @todo implement
	 * @todo write unit tests
	 */ 
	public function process_moves( $pages_to_move ) {
		// error_log("=== Processing moves ===");
		global $wpdb;
		$success = true;
		
		if ((is_array($pages_to_move)) && (count($pages_to_move) > 0)) {

			// get a complete navigation tree before we make changes
			remove_filter('bu_navigation_filter_pages', 'bu_navigation_filter_pages_exclude');
			$all_pages = bu_navigation_get_pages();
			$pages_moved = array();

			do_action('bu_navman_pages_pre_move');

			foreach ($pages_to_move as $parent_id => $pages) {

				if ((is_array($pages)) && (count($pages) > 0)) {

					$position = 1;

					foreach ($pages as $page) {

						$stmt = $wpdb->prepare('UPDATE ' . $wpdb->posts . ' SET post_parent = %d, menu_order = %d WHERE ID = %d', $parent_id, $position, $page);
						$rc = $wpdb->query($stmt);
						// error_log("Updating post $page - Parent: $parent_id, Order: $position");

						if ($rc === false) $success = false;

						$position++;

						if (array_key_exists($page, $all_pages)) {
							if ($all_pages[$page]->post_parent != $parent_id)
								array_push($pages_moved, $page);
						} else {
							/* it appears we don't know about this page, let's report it as moved */
							array_push($pages_moved, $page);
						}
					}
				}
			}

			// @todo $pages_moved is not used by link-rebuilder or any other filters, remove?
			do_action('bu_navman_pages_moved', $pages_moved);
		}

		// error_log('Finished processessing edits, pages moved: ' . count($pages_moved) . ', return status: ' . $success );
		return $success;

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

}

?>
