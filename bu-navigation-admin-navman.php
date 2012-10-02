<?php

require_once(dirname(__FILE__) . '/bu-navigation-interface.php' );

/*
@todo
	- Handle dynamic script context generation consistently with navigation-attributes metabox (as DRY'ly as possible)

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
	static $interface;

	const OPTION_LOCK_TIME = '_bu_navman_lock_time';
	const OPTION_LOCK_USER = '_bu_navman_lock_user';

	public function __construct( $post_type ) {

		// Set current post type
		if( in_array( $post_type, bu_navigation_supported_post_types() ) ) {

			$this->post_type = $post_type;

		}

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
		
		// Add "Edit Order" links to the submenu of each supported post type
		$post_types = bu_navigation_supported_post_types();

		foreach( $post_types as $pt ) {

			$parent_slug = 'edit.php?post_type=' . $pt;

			$this->pages[] = add_submenu_page(
				$parent_slug,
				null,
				__('Edit Order'),
				'edit_pages',
				'bu-navigation-manager',
				array( $this, 'render' )
				);

		}
			
		$this->clear_lock();

	}

	/**
	 * Enqueue dependent Javscript and CSS files
	 */ 
	public function enqueue_scripts( $page ) {

		// Enqueue navman styles and scripts
		if( in_array( $page, $this->pages ) ) {
			
	        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';

			// Instantiate navman tree interface object
			// @todo this logic should be centralized somewhere else
			$post_types = ( $this->post_type == 'page' ? array( 'page', 'link' ) : array( $this->post_type ) );
			self::$interface = new BU_Navman_Interface( $post_types );

			// Load default navigation manager scripts & styles
			self::$interface->enqueue_scripts();

			// Vendor scripts & styles
	        wp_enqueue_script('jquery-ui-dialog');
	        wp_enqueue_script('bu-jquery-validate', plugins_url('js/vendor/jquery.validate' . $suffix . '.js', __FILE__), array('jquery'), '1.8.1', true);
			wp_enqueue_style('bu-jquery-ui-navman', plugins_url('css/vendor/jquery-ui/jquery-ui-1.8.13.custom.css', __FILE__), array(), '1.8.13');

			// Scripts and styles for this page
	        wp_enqueue_script('bu-navman', plugins_url('js/manage' . $suffix . '.js', __FILE__), array('bu-jquery-tree'), '0.3.1', true);
			wp_enqueue_style('bu-navman', plugins_url('css/manage.css', __FILE__), array(), '0.3');

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

		/* process any post */
		$saved = $this->save();

		/* set lock */
		$this->set_lock();

		// Actual post type and post types to fetch with get pages (remove that one after context is dealt with)
		$post_type = $this->post_type;
		$post_types = ( $post_type == 'page' ? array('page', 'link') : array($post_type) );

		// ------------------ SNIP ------------------//

		// @todo use get_script_context / print_script_context like in navigation attributesm metabox class

	    // @todo get rid of ALL of these, rely on configuration set in interface class
		$interface_path = plugins_url('interface', __FILE__);
		$post_types_param = ($post_types ? '&post_type='.implode(',',$post_types) : '');
		$rpc_url = 'admin-ajax.php?action=bu_getpages' . $post_types_param;	// used to get all the posts for the tree
		$rpc_page_url = 'admin-ajax.php?action=bu_getpage';	// used with links, so it doesn't need post_type

		// Get pages, formatted for jstree
		$pages = self::$interface->get_pages( 0, array( 'depth' => 1 ) );
		$pages_json = json_encode($pages);

		// section editing
		$is_section_editor = false;

		if( class_exists( 'BU_Section_Editing_Plugin' ) ) {
			$is_section_editor = BU_Section_Editing_Plugin::is_allowed_user( get_current_user_id() );
		}

		// Allowing top level pages
		$allow_top = $GLOBALS['bu_navigation_plugin']->get_setting('allow_top');

		// --------------- END SNIP ----------------//

		// Check the lock to see if there is a user currently editing this page
		$editing_user = $this->check_lock();

		// Render interface
		include(BU_NAV_PLUGIN_DIR . '/interface/manage.php');

	}

	/**
	 * Handle $_POST submissions for navigation management page
	 */ 
	public function save() {
		global $wpdb;

		$saved = NULL;

		if (array_key_exists('bu_navman_save', $_POST))
		{
			$saved = FALSE;
			$problems = FALSE;

			// @todo move to another method

			/* handle deletions */

			$pages_to_delete = json_decode(stripslashes($_POST['navman_delete']));

			if ((is_array($pages_to_delete)) && (count($pages_to_delete) > 0))
			{
				foreach ($pages_to_delete as $id)
				{
					if (!wp_delete_post($id))
					{
						error_log(sprintf('navman: Unable to delete post %d', $id));
						$problems = TRUE;
					}
				}
			}

			// @todo move in to another method

			/* handle edits */
			$pages_to_edit = (array)json_decode(stripslashes($_POST['navman_edits']));

			if ((is_array($pages_to_edit)) && (count($pages_to_edit) > 0))
			{
				foreach ($pages_to_edit as $p)
				{
					$data = array(
						'ID' => $p->ID,
						'post_title' => $p->post_title,
						'post_content' => $p->post_content
						);

					$id = wp_update_post($data);

					if ($id)
					{
						update_post_meta($id, 'bu_link_target', ($p->target === 'new') ? 'new' : 'same');
					}
					else
					{
						$problems = TRUE;
					}
				}
			}

			/* handle page location and ordering */
			$nodes = json_decode(stripslashes($_POST['navman_data']));
			$links = (array)json_decode(stripslashes($_POST['navman_links']));

			// @todo move to another method

			$updates = array();

			if ((is_array($nodes)) && (count($nodes) > 0))
			{
				$parent_id = 0;

				$updates = $this->process_nodes($parent_id, $nodes, $links);
			}

			if ((is_array($updates)) && (count($updates) > 0))
			{
				/* get a complete navigation tree before we make changes */
				remove_filter('bu_navigation_filter_pages', 'bu_navigation_filter_pages_exclude');
				$all_pages = bu_navigation_get_pages();
				$pages_moved = array();

				do_action('bu_navman_pages_pre_move');

				foreach ($updates as $parent_id => $pages)
				{
					if ((is_array($pages)) && (count($pages) > 0))
					{
						$position = 1;

						foreach ($pages as $page)
						{
							$stmt = $wpdb->prepare('UPDATE ' . $wpdb->posts . ' SET post_parent = %d, menu_order = %d WHERE ID = %d', $parent_id, $position, $page);
							$rc = $wpdb->query($stmt);

							if ($rc === FALSE) $problems = TRUE;

							$position++;

							if (array_key_exists($page, $all_pages))
							{
								if ($all_pages[$page]->post_parent != $parent_id) array_push($pages_moved, $page);
							}
							else
							{
								/* it appears we don't know about this page, let's report it as moved */
								array_push($pages_moved, $page);
							}
						}
					}
				}

				do_action('bu_navman_pages_moved', $pages_moved);
			}

			if (function_exists('invalidate_blog_cache')) invalidate_blog_cache();

			$this->clear_lock();

			if (!$problems) $saved = TRUE;
		}

		return $saved;
	}

	/**
	 * @todo implement
	 * @todo write unit tests
	 */ 
	public function process_edits() {

	}

	/**
	 * @todo implement
	 * @todo write unit tests
	 */ 
	public function process_deletions() {

	}

	/**
	 * @todo implement
	 * @todo write unit tests
	 */ 
	public function process_moves() {

	}

	/**
	 * @todo write unit tests
	 */ 
	public function process_nodes( $parent_id, $nodes, $links ) {
		$updates = array();
		$updates[$parent_id] = array();

		foreach ($nodes as $node)
		{
			$id = intval(substr($node->attr->id, 1));

			if (!$id)
			{
				if (substr($node->attr->class, 0, 8) == 'newlink_')
				{
					$link_id = intval(substr($node->attr->class, 8));

					$target = 'same';

					if (array_key_exists($link_id, $links))
					{
						$data = array(
							'post_title' => $links[$link_id]->label,
							'post_content' => $links[$link_id]->address,
							'post_excerpt' => '',
							'post_status' => 'publish',
							'post_type' => 'link',
							'post_parent' => $parent_id,
							'menu_order' => 0
							);

						$target = $links[$link_id]->target;
					}

					$id = wp_insert_post($data);

					if ($id === 0)
					{
						error_log(sprintf('bu_navman_process_nodes could not create link: %s', print_r($node, TRUE)));
						continue;
					}

					update_post_meta($id, 'bu_link_target', ($target === 'new') ? 'new' : 'same');

				}
				else
				{
					error_log(sprintf('bu_navman_process_nodes: %s', print_r($node, TRUE)));
					continue;
				}
			}

			array_push($updates[$parent_id], $id);

			if ((isset($node->children)) && (is_array($node->children)) && (count($node->children) > 0))
			{
				$child_updates = $this->process_nodes( $id, $node->children, $links );

				foreach ($child_updates as $page_id => $children)
				{
					$updates[$page_id] = $children;
				}
			}
		}

		return $updates;

	}

	/**
	 * @todo needs unit tests
	 */
	public function check_lock() {
		global $current_user;

		$lock_time = get_option( self::OPTION_LOCK_TIME );
		$lock_user = get_option( self::OPTION_LOCK_USER );

		$time_window = apply_filters('wp_check_post_lock_window', AUTOSAVE_INTERVAL * 2);

		if ( $lock_time && $lock_time > time() - $time_window && $lock_user != $current_user->ID )
			return $lock_user;

		return FALSE;
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

}

?>