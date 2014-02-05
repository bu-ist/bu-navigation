<?php
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
class BU_Navigation_Admin_Manager {

	public $page;
	public $reorder_tracker;

	const OPTION_LOCK_TIME = '_bu_navman_lock_time';
	const OPTION_LOCK_USER = '_bu_navman_lock_user';

	const MESSAGE_UPDATED = 1;
	const NOTICE_ERRORS = 1;
	const NOTICE_LOCKED =2;

	private $messages = array();
	private $plugin;

	public function __construct( $post_type, $plugin ) {

		$this->plugin = $plugin;
		$this->post_type = $post_type;
		$this->pages = array();

		$this->reorder_tracker = new BU_Navigation_Reorder_Tracker( $this->post_type );

		// Attach WP actions/filters
		$this->register_hooks();

	}

	/**
	* Attach WP actions and filters utilized by our meta boxes
	*/
	public function register_hooks() {

		add_action('admin_menu', array( $this, 'register_menu' ) );
		add_action('admin_enqueue_scripts', array( $this, 'add_scripts' ) );

	}

	/**
	 * Generate admin menu cap for the given post type
	 *
	 * Includes logic that makes menu accessible for section editors
	 */
	public function get_menu_cap_for_post_type( $post_type ) {
		$pto = get_post_type_object( $post_type );

		if ( $pto->map_meta_cap ) {
			if ( current_user_can( $pto->cap->edit_published_posts ) ) {
				$cap = $pto->cap->edit_published_posts;
			} else {
				$cap = 'edit_' . $post_type . '_in_section';
			}
		} else {
			if ( current_user_can( $pto->cap->edit_others_posts ) ) {
				$cap = $pto->cap->edit_others_posts;
			} else {
				$cap = 'edit_' . $post_type . '_in_section';
			}
		}

		return $cap;
	}

	/**
	 * Add "Edit Order" submenu pages to allow editing the navigation of the supported post types
	 */
	public function register_menu() {

		// Add "Edit Order" links to the submenu of each supported post type
		$post_types = $this->plugin->supported_post_types();

		foreach( $post_types as $pt ) {

			$parent_slug = 'edit.php?post_type=' . $pt;

			$page = add_submenu_page(
				$parent_slug,
				__('Edit Order', 'bu-navigation' ),
				__('Edit Order', 'bu-navigation' ),
				$this->get_menu_cap_for_post_type( $pt ),
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
			$scripts_url = plugins_url( 'js', BU_NAV_PLUGIN );
			$vendor_url = plugins_url( 'js/vendor', BU_NAV_PLUGIN );
			$styles_url = plugins_url( 'css', BU_NAV_PLUGIN );

			// Scripts
			wp_register_script( 'bu-jquery-validate', $vendor_url . '/jquery.validate' . $suffix . '.js', array('jquery'), '1.8.1', true );
			wp_register_script( 'bu-navman', $scripts_url . '/manage' . $suffix . '.js', array('bu-navigation','jquery-ui-dialog','bu-jquery-validate'), BU_Navigation_Plugin::VERSION, true );

			// Strings for localization
			$nav_menu_label = __( 'Appearance > Primary Navigation', 'bu-navigation' );
			$strings = array(
				'optionsLabel' => __( 'options', 'bu-navigation' ),
				'optionsEditLabel' => __( 'Edit', 'bu-navigation' ),
				'optionsViewLabel' => __( 'View', 'bu-navigation' ),
				'optionsDeleteLabel' => __( 'Delete', 'bu-navigation' ),
				'optionsTrashLabel' => __( 'Move to Trash', 'bu-navigation' ),
				'addLinkDialogTitle' => __( 'Add a Link', 'bu-navigation' ),
				'editLinkDialogTitle' => __( 'Edit Link', 'bu-navigation' ),
				'cancelLinkBtn' => __( 'Cancel', 'bu-navigation' ),
				'confirmLinkBtn' => __( 'Ok', 'bu-navigation' ),
				'noTopLevelNotice' => __( 'You are not allowed to create top level published content.', 'bu-navigation' ),
				'noLinksNotice' => __( 'You are not allowed to add links', 'bu-navigation' ),
				'createLinkNotice' => __( 'Select a page that you can edit and click "Add a Link" to create a new link below the selected page.', 'bu-navigation' ),
				'allowTopNotice' => sprintf( __( 'Site administrators can change this behavior by visiting %s and enabling the "Allow Top-Level Pages" setting.', 'bu-navigation' ), $nav_menu_label ),
				'noChildLinkNotice' => __( 'Links are not permitted to have children.', 'bu-navigation' ),
				'unloadWarning' => __( 'You have made changes to your navigation that have not yet been saved.', 'bu-navigation' ),
				'saveNotice' => __( 'Saving navigation changes...', 'bu-navigation' ),
				);

			// Setup dynamic script context for manage.js
			$script_context = array(
				'postTypes' => $this->post_type,
				'postStatuses' => array( 'publish', 'private' ),
				'nodePrefix' => 'nm',
				'lazyLoad' => true,
				'showCounts' => true
				);
			// Navigation tree view will handle actual enqueuing of our script
			$treeview = new BU_Navigation_Tree_View( 'bu_navman', array_merge( $script_context, $strings ) );
			$treeview->enqueue_script( 'bu-navman' );

			// Register custom jQuery UI stylesheet if it isn't already
			if ( ! wp_style_is( 'bu-jquery-ui', 'registered' ) ) {
				if ( 'classic' == get_user_option( 'admin_color') ) {
					wp_register_style( 'bu-jquery-ui', $styles_url . '/jquery-ui-classic.css', array(), BU_Navigation_Plugin::VERSION );
				} else {
					wp_register_style( 'bu-jquery-ui', $styles_url . '/jquery-ui-fresh.css', array(), BU_Navigation_Plugin::VERSION );
				}
			}

			wp_enqueue_style( 'bu-navman', $styles_url . '/manage.css', array( 'bu-jquery-ui' ), BU_Navigation_Plugin::VERSION );

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
			if( $saved === true ) $url = add_query_arg( 'message', 1, $url );
			else $url = add_query_arg( 'notice', 1, $url );

			wp_redirect( $url );

		}

		$this->setup_notices();
		$this->setup_locks();

	}

	/**
	 * Add notices if we have any in the queue
	 */
	public function setup_notices() {

		// Setup initial empty data structure
		$this->messages['message'] = array();
		$this->messages['notice'] = array();

		// Grab any notices from query string
		$message_code = isset($_GET['message']) ? intval($_GET['message']) : 0;
		$notice_code = isset($_GET['notice']) ? intval($_GET['notice']) : 0;

		$message = $this->get_notice_by_code( 'message', $message_code );
		$notice = $this->get_notice_by_code( 'notice', $notice_code );

		// Append to member property for display during get_notice_list
		if( $message ) $this->messages['message'][] = $message;
		if( $notice ) $this->messages['notice'][] = $notice;

	}

	/**
	 * Retrieve notice message by type and numeric code:
	 *
	 * @param string $type the type of notice (either 'message' or 'notice')
	 * @param int $code the notice code (see const NOTICE_* and const MESSAGE_*)
	 */
	public function get_notice_by_code( $type, $code ) {

		$user_markup = '<strong>%s</strong>';

		$notices = array(
			'message' => array(
				0 => '', // Unused. Messages start at index 1.
				1 => __( 'Your navigation changes have been saved', 'bu-navigation' )
			),
			'notice' => array(
				0 => '',
				1 => __( 'Errors occurred while saving your navigation changes.', 'bu-navigation' ),
				2 => sprintf( __( "Warning: %s is currently editing this site's navigation.", 'bu-navigation' ), $user_markup )
			)
		);

		if( array_key_exists( $type, $notices ) && array_key_exists( $code, $notices[$type] )) {
			return $notices[$type][$code];
		}

		return '';

	}

	/**
	 * Formats existing messages & notices for display
	 */
	public function get_notice_list() {

		$output = '';

		foreach( $this->messages as $type => $messages ) {

			$i = 0;
			$inner_content = '';

			if( count( $messages ) > 0 ) {
				$classes = 'message' == $type ? 'updated fade' : 'error';

				while( $i < count( $messages ) ) {
					$inner_content = sprintf( "<p>%s</p>\n", $messages[$i] );
					$output .= sprintf( "<div class=\"%s below-h2\">%s</div>\n", $classes, $inner_content );

					$i++;
				}

			}

		}

		return $output;

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
			$notice = $this->get_notice_by_code( 'notice', self::NOTICE_LOCKED );
			$this->messages['notice'][] = sprintf( $notice, $user_detail->display_name );
		}

	}

	/**
	 * Display navigation manager page
	 */
	public function render() {

		if( is_null( $this->post_type ) ) {
			wp_die('Edit order page is not available for post type: ' . $this->post_type );
			return;
		}
		$cap = $this->get_menu_cap_for_post_type( $this->post_type );
		if( ! current_user_can( $cap ) ) {
			wp_die('Cheatin, uh?');
		}

		// Template context
		$ajax_spinner = plugins_url( 'images/wpspin_light.gif', BU_NAV_PLUGIN );
		$post_type = get_post_type_object( $this->post_type );
		$notices = $this->get_notice_list();
		$include_links = $this->plugin->supports( 'links' ) && 'page' == $this->post_type;
		$disable_add_link = ! $this->can_publish_top_level( $this->post_type );

		// Render interface
		include( BU_NAV_PLUGIN_DIR . '/templates/edit-order.php' );

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

			$saved = false;

			// Process post removals
			$deletions = json_decode( stripslashes($_POST['navman-deletions']) );
			$result = $this->process_deletions( $deletions );

			if( is_wp_error( $result ) ) {
				array_push( $errors, $result );
			}

			// Process link updates
			$updates = (array) json_decode( stripslashes($_POST['navman-updates']) );
			$result = $this->process_updates( $updates );

			if( is_wp_error( $result ) ) {
				array_push( $errors, $result );
			}

			// Process link insertions
			$inserts = (array) json_decode( stripslashes($_POST['navman-inserts']) );
			$result = $this->process_insertions( $inserts );

			if( is_wp_error( $result ) ) {
				array_push( $errors, $result );
			}

			// Process moves
			$moves = (array) json_decode( stripslashes($_POST['navman-moves']) );
			$result = $this->process_moves( $moves );

			if( is_wp_error( $result ) ) {
				array_push( $errors, $result );
			}

			// Update menu order for affected children
			$result = $this->reorder_tracker->run();

			if( false === $result ) {
				array_merge( $errors, $this->reorder_tracker->errors );
			}

			if( 0 == count( $errors ) ) {
				$saved = true;
			} else {
				// @todo notify user of error messages from WP_Error objects
				error_log('Errors encountered during navman save:' . print_r( $errors, true ) );
			}

		}

		return $saved;
	}

	/**
	 * Trashes posts that have been removed using the navman interface
	 *
	 * @param array $post_ids an array of post ID's for trashing
	 * @return bool|WP_Error $result the result of the post deletions
	 */
	public function process_deletions( $post_ids ) {

		$result = null;
		$failures = array();

		if ( ( is_array( $post_ids ) ) && ( count( $post_ids ) > 0 ) ) {

			foreach( $post_ids as $id ) {

				$post = get_post( $id );

				$deleted = $force_delete = false;

				// Permanently delete links, as there is currently no way to recover them from trash
				if( BU_NAVIGATION_LINK_POST_TYPE == $post->post_type ) {
					$force_delete = true;
				}

				if( current_user_can( 'delete_post', $post->ID ) ) {
					$deleted = wp_delete_post( (int) $id, $force_delete );
				}

				if( ! $deleted ) {
					error_log(sprintf('[BU Navigation Navman] Unable to delete post %d', $id));
					array_push( $failures, $id );
				} else {

					$this->reorder_tracker->mark_section_for_reordering( $post->post_parent );

				}

			}

		}

		if( count( $failures ) ) {
			$result = new WP_Error( 'bu_navigation_save_error', 'Could not delete post(s): ' . implode(', ', $failures ) );
		} else {
			$result = true;
		}

		return $result;
	}

	/**
	 * Updates posts (really just links at this time) that have been modified using the navman interface
	 *
	 * @param array $posts an array of posts which have been modified
	 * @return bool|WP_Error $result the result of the post updates
	 */
	public function process_updates( $posts ) {

		$result = null;
		$failures = array();

		if( ( is_array( $posts ) ) && ( count( $posts ) > 0 ) ) {

			foreach( $posts as $post ) {

				$updated = false;

				$post->ID = (int) $post->ID;

				if( current_user_can( 'edit_post', $post->ID ) ) {

					$data = array(
						'ID' => $post->ID,
						'post_title' => $post->post_title,
						'post_content' => $post->post_content
						);

					$updated = wp_update_post( $data, true );

				}

				if( false == $updated || is_wp_error( $updated ) ) {

					error_log(sprintf('[BU Navigation Navman] Could not update link: %s', print_r($post, true)));
					error_log(print_r($updated,true));
					array_push( $failures, $post->post_title );

				} else {

					$target = ($post->post_meta->bu_link_target === 'new') ? 'new' : 'same';
					update_post_meta( $post->ID, 'bu_link_target', $target );

				}

			}

		}

		if( count( $failures ) ) {
			$result = new WP_Error( 'bu_navigation_save_error', 'Could not update link(s): ' . implode(', ', $failures ) );
		} else {
			$result = true;
		}

		return $result;
	}

	/**
	 * Insert posts (really just links at this time) that have been added using the navman interface
	 *
	 * @param array $posts an array of posts which have been added
	 * @return bool|WP_Error $result the result of the post insertions
	 */
	public function process_insertions( $posts ) {

		$result = null;
		$failures = array();

		if( ( is_array( $posts ) ) && ( count( $posts ) > 0 ) ) {

			foreach( $posts as $post ) {

				// Special handling for new links -- need to get a valid post ID
				if ( BU_NAVIGATION_LINK_POST_TYPE == $post->post_type ) {

					$inserted = false;

					$post->post_parent = (int) $post->post_parent;
					$post->menu_order = (int) $post->menu_order;

					if( $this->can_place_in_section( $post ) ) {

						$data = array(
							'post_title' => $post->post_title,
							'post_content' => $post->post_content,
							'post_excerpt' => '',
							'post_status' => 'publish',
							'post_type' => BU_NAVIGATION_LINK_POST_TYPE,
							'post_parent' => $post->post_parent,
							'menu_order' => $post->menu_order
							);

						$inserted = wp_insert_post( $data, true );

					}

					if( false == $inserted || is_wp_error( $inserted ) ) {

						error_log(sprintf('[BU Navigation Navman] Could not create link: %s', print_r($post, true)));
						error_log(print_r($inserted,true));
						array_push( $failures, $post->post_title );

					} else {

						$post->ID = $inserted;

						$target = ($post->post_meta->bu_link_target === 'new') ? 'new' : 'same';
						update_post_meta($post->ID, 'bu_link_target', $target );

						// Mark for reordering
						$this->reorder_tracker->mark_post_as_moved( $post );

					}

				}

			}

		}

		if( count( $failures ) ) {
			$result = new WP_Error( 'bu_navigation_save_error', 'Could not insert link(s): ' . implode(', ', $failures ) );
		} else {
			$result = true;
		}

		return $result;
	}

	/**
	 * Updates posts that have been moved using the navman interface
	 *
	 * @param array $posts an array of posts which have new menu_order or post_parent fields
	 * @return bool|WP_Error $result the result of the post movements
	 */
	public function process_moves( $posts ) {

		$result = null;
		$failures = array();

		if( ( is_array( $posts ) ) && ( count( $posts ) > 0 ) ) {

			do_action('bu_navman_pages_pre_move');

			foreach( $posts as $post ) {

				$updated = false;

				$original = get_post($post->ID);

				if( $this->can_move( $post, $original ) ) {

					// Update post parent and menu order
					$updated = wp_update_post(array('ID'=>$post->ID,'post_parent'=>$post->post_parent,'menu_order'=>$post->menu_order), true );

					// Edge case detection ... this error appears even though the post has actually been updated
					if ( is_wp_error( $updated ) && in_array( 'invalid_page_template', $updated->get_error_codes() ) ) {
						if ( 1 == count( $updated->errors ) )
							$updated = true;
					}

				}

				if( false == $updated || is_wp_error( $updated ) ) {

					error_log(sprintf('[BU Navigation Navman] Could not move post: %s', print_r($post, true)));
					error_log(print_r($updated, true));
					array_push( $failures, $post->ID );

				} else {

					// Mark for reordering
					$this->reorder_tracker->mark_post_as_moved( $post );

					if( $post->post_parent != $original->post_parent ) {
						$this->reorder_tracker->mark_section_for_reordering( $original->post_parent );
					}

				}

			}

			do_action('bu_navman_pages_moved');

		}

		if( count( $failures ) ) {
			$result = new WP_Error( 'bu_navigation_save_error', 'Could not move post(s): ' . implode(', ', $failures ) );
		} else {
			$result = true;
		}

		return $result;

	}

	/**
	 * Whether or not the current user can publish top level content for the given post type
	 */
	public function can_publish_top_level( $post_type = 'post' ) {

		$pto = get_post_type_object( $post_type );
		if ( ! is_object( $pto ) )
			return false;

		$can_publish = current_user_can( $pto->cap->publish_posts );
		$allow_top = (bool) $this->plugin->settings->get( 'allow_top' );

		return $can_publish && $allow_top;

	}

	/**
	 * Can the current user switch post parent for the supplied post
	 *
	 * @param object|int $post post obj or post ID to check move for
	 */
	public function can_place_in_section( $post, $prev_parent = null ) {
		$allowed = false;

		if( is_numeric( $post ) ) {
			$post = get_post($post);
		}
		if( ! is_object( $post ) ) {
			return false;
		}

		// Need a valid post type to continue
		$pto = get_post_type_object( $post->post_type );

		if ( ! is_object( $pto ) )
			return false;

		$can_publish = current_user_can( $pto->cap->publish_posts );

		// Top level move
		if( 0 == $post->post_parent ) {

			// Move is promotion to top level
			if( 0 !== $prev_parent ) {

				// Top-level moves are okay as long as top level publishing is allowed or the post is excluded from nav menus
				$allow_top = $this->plugin->settings->get( 'allow_top' );
				$excluded_from_nav = bu_navigation_post_excluded( $post );

				$allowed = current_user_can( $pto->cap->publish_posts ) && ( $allow_top || $excluded_from_nav );

			} else {

				$allowed = current_user_can( $pto->cap->publish_posts );

			}

		} else {

			// Check parent
			$parent = get_post($post->post_parent);

			if ( ! is_object( $parent ) ) {

				$allowed = false;

			} else {

				// Move under another post -- check if parent is editable
				$allowed = current_user_can( 'edit_post', $parent->ID );


				// Links can't have children
				if ( BU_NAVIGATION_LINK_POST_TYPE == $parent->post_type ) {
					$allowed = false;
				}

			}

		}

		return $allowed;

	}

	/**
	 * Can the current user move the supplied post
	 *
	 * @param object|int $post post obj or post ID to check move for
	 * @param object|int $original post obj or post ID of previous parent
	 */
	public function can_move( $post, $original ) {

		if( is_numeric( $post ) ) {
			$post = get_post($post);
		}
		if( ! is_object( $post ) ) {
			return false;
		}

		$prev_parent = null;

		if( is_numeric( $original ) ) {
			$original = get_post($original);
		}
		if( is_object( $original ) ) {
			$prev_parent = $original->post_parent;
		}

		$can_edit_post = current_user_can( 'edit_post', $post->ID );
		$can_edit_parent = $this->can_place_in_section( $post, $prev_parent );

		return $can_edit_post && $can_edit_parent;
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
