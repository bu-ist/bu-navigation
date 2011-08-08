<?php


/* BU Navigation Manager constants */
if (!defined('BU_NAV_PLUGIN_DIR'))
	define('BU_NAV_PLUGIN_DIR', dirname(__FILE__));
define('BU_NAVMAN_LOCK_TIME', '_bu_navman_lock_time');
define('BU_NAVMAN_LOCK_USER', '_bu_navman_lock_user');


/**
 * Initialization function for navigation manager admin page
 * @return void
 */
function bu_navman_admin_menu_init()
{
	global $menu, $current_site;

	$perm = 'edit_pages';

	$interface_path = plugins_url('interface', __FILE__);
	$icon = sprintf('%s/icons/nav-icon-gray.png', $interface_path);

	$page = add_menu_page(__('Navigation Manager'), __('Navigation'), $perm, __FILE__, 'bu_navman_admin_menu_display', $icon);

	if ($page)
	{
		add_submenu_page(__FILE__, __('Navigation Manager'), 'Edit Order', $perm, __FILE__, 'bu_navman_admin_menu_display');

	}

	bu_navman_clear_lock();
}
add_action('admin_menu', 'bu_navman_admin_menu_init');

/**
 * Enqueues scripts and styles
 * @return void
 */
function bu_navman_enqueue_media($hook)
{
	if ($hook == 'toplevel_page_bu-navigation/bu-navman')
	{
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';

        //switched from jquery-json to json2 @see http://ejohn.org/blog/ecmascript-5-strict-mode-json-and-more/
        wp_enqueue_script('json2');
        wp_enqueue_script('jquery-ui-dialog');

        //for now, I am prefixing bu- to the various jquery plugins to avoid conflicts with the bu-js-lib
        wp_enqueue_script('bu-jquery-tree', plugins_url('js/jstree/jquery.jstree' . $suffix . '.js', __FILE__), array('jquery', 'bu-jquery-cookie'), '1.0-rc3', true);
        //uses the most-recent github commit as the version identifier
		wp_enqueue_script('bu-jquery-cookie', plugins_url('js/jquery.cookie' . $suffix . '.js', __FILE__), array('jquery'), '00168770', true);

        wp_enqueue_script('bu-jquery-validate', plugins_url('js/jquery.validate' . $suffix . '.js', __FILE__), array('jquery'), '1.8.1', true);
        wp_enqueue_script('bu-navman', plugins_url('js/manage' . $suffix . '.js', __FILE__), array('bu-jquery-tree'), '0.3.1', true);

		wp_enqueue_style('bu-jquery-ui-navman', plugins_url('interface/jquery-ui-1.8.13.custom.css', __FILE__), array(), '1.8.13');

		wp_enqueue_style('bu-navman', plugins_url('interface/manage.css', __FILE__), array(), '0.3');


        wp_enqueue_style('bu-jquery-tree-classic', plugins_url('js/jstree/themes/classic/style.css', __FILE__), array(), '1.8.1');
	}
}
add_action('admin_enqueue_scripts', 'bu_navman_enqueue_media');

/**
 * Displays the navigation manager interface
 * @return void
 */
function bu_navman_admin_menu_display()
{
	/* process any post */
	$saved = bu_navman_admin_menu_post();

	/* remove default page filter and add our own */
	remove_filter('bu_navigation_filter_pages', 'bu_navigation_filter_pages_exclude');
	add_filter('bu_navigation_filter_pages', 'bu_navman_filter_pages');


	/* set lock */
	bu_navman_set_lock();

	$interface_path = plugins_url('interface', __FILE__);//str_replace(site_url(), '', plugins_url('interface', __FILE__));

    /* RPC urls */

	$rpc_url = 'admin-ajax.php?action=bu_getpages';
	$rpc_page_url = 'admin-ajax.php?action=bu_getpage';

	$pages = array();

	$section_args = array('direction' => 'down', 'depth' => 1, 'sections' => array(0));
	$sections = bu_navigation_gather_sections(0, $section_args);

	$root_pages = bu_navigation_get_pages(array('sections' => $sections));

	$pages_by_parent = bu_navigation_pages_by_parent($root_pages);

	if ((is_array($pages_by_parent[0])) && (count($pages_by_parent[0]) > 0))
	{
		foreach ($pages_by_parent[0] as $page)
		{
			if (!isset($page->navigation_label)) $page->navigation_label = apply_filters('the_title', $page->post_title);

			$title = $page->navigation_label;

			$classes = array(); // css classes

			$p = array(
				'attr' => array('id' => sprintf('p%d', $page->ID), 'class' => ''),
				'data' => $title,
				'author' => $page->author
				);

			if ((is_array($pages_by_parent[$page->ID])) && (count($pages_by_parent[$page->ID]) > 0))
			{
				$p['state'] = 'closed';
				$p['attr']['rel'] = 'folder';
			}

			if (!array_key_exists('state', $p))
			{
				$p['attr']['rel'] = $page->post_type;
			}

			if ($page->excluded)
			{
				$p['attr']['rel'] .= '_excluded';
				array_push($classes, 'excluded');
			}

			if ($page->restricted)
			{
				$p['attr']['rel'] .= '_restricted';
				array_push($classes, 'restricted');
			}

			$p['attr']['class'] = implode(' ', $classes);

			array_push($pages, $p);
		}
	}

	$pages_json = json_encode($pages);

	include(BU_NAV_PLUGIN_DIR . '/interface/manage.php');

	/* remove our page filter and add back in the default */
	remove_filter('bu_navigation_filter_pages', 'bu_navman_filter_pages');
	add_filter('bu_navigation_filter_pages', 'bu_navigation_filter_pages_exclude');
}

function bu_navman_page_restricted($page_id, $restricted_pages)
{
	$restricted = FALSE;

	if (array_key_exists($page_id, $restricted_pages))
	{
		$restricted = TRUE;
	}

	return $restricted;
}

function bu_navman_filter_pages($pages)
{
	global $wpdb;

	$filtered = array();

	if ((is_array($pages)) && (count($pages) > 0))
	{
		/* page exclusions */
		$ids = array_keys($pages);

		$query = sprintf("SELECT post_id, meta_value FROM %s WHERE meta_key = '%s' AND post_id IN (%s) AND meta_value != '0'", $wpdb->postmeta, BU_NAV_META_PAGE_EXCLUDE, implode(',', $ids));

		$exclusions = $wpdb->get_results($query, OBJECT_K); // get results as objects in an array keyed on post_id
		if (!is_array($exclusions)) $exclusions = array();

		/* access restrictions */

		$restricted = array();

		if (class_exists('BuAccessControlPlugin'))
		{
			$query = sprintf("SELECT post_id, meta_value FROM %s WHERE meta_key = '%s' AND post_id IN (%s) AND meta_value != '0'", $wpdb->postmeta, BuAccessControlList::PAGE_ACL_OPTION, implode(',', $ids));

			$restricted = $wpdb->get_results($query, OBJECT_K); // get results as objects in an array keyed on post_id
			if (!is_array($restricted)) $restricted = array();
		}

		/* set exclusions and acls */
		foreach ($pages as $page)
		{
			/* exclusions */
			if (array_key_exists($page->ID, $exclusions))
			{
				$page->excluded = TRUE;
			}
			else
			{
				$parent_id = $page->post_parent;

				while (($parent_id) && (array_key_exists($parent_id, $pages)))
				{
					if (array_key_exists($parent_id, $exclusions))
					{
						$page->excluded = TRUE;
						break;
					}

					$parent_id = $pages[$parent_id]->post_parent;
				}
			}

			/* restrictions */
			$page->restricted = FALSE;

			if (array_key_exists($page->ID, $restricted))
			{
				$page->restricted = TRUE;
			}
			else
			{
				$parent_id = $page->post_parent;

				while (($parent_id) && (array_key_exists($parent_id, $pages)))
				{
					if (array_key_exists($parent_id, $restricted))
					{
						$page->restricted = TRUE;
						break;
					}

					$parent_id = $pages[$parent_id]->post_parent;
				}
			}

			$filtered[$page->ID] = $page;
		}
	}

	return $filtered;
}


function bu_navman_process_nodes($parent_id, $nodes, $links)
{
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

		if (($node->children) && (is_array($node->children)) && (count($node->children) > 0))
		{
			$child_updates = bu_navman_process_nodes($id, $node->children, $links);

			foreach ($child_updates as $page_id => $children)
			{
				$updates[$page_id] = $children;
			}
		}
	}

	return $updates;
}
/**
 * Handles a POST from the navigation manager interface
 * @return void
 */
function bu_navman_admin_menu_post()
{
	global $wpdb;

	$saved = NULL;

	if (array_key_exists('bu_navman_save', $_POST))
	{
		$saved = FALSE;
		$problems = FALSE;

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

		$updates = array();

		if ((is_array($nodes)) && (count($nodes) > 0))
		{
			$parent_id = 0;

			$updates = bu_navman_process_nodes($parent_id, $nodes, $links);
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

		bu_navman_clear_lock();

		if (!$problems) $saved = TRUE;
	}

	return $saved;
}

function bu_navman_get_children($parent_id, $pages_by_parent)
{
	$children = array();

	if (array_key_exists($parent_id, $pages_by_parent))
	{
		$pages = $pages_by_parent[$parent_id];

		if ((is_array($pages)) && (count($pages) > 0))
		{
			foreach ($pages as $page)
			{
				if (!isset($page->navigation_label)) $page->navigation_label = apply_filters('the_title', $page->post_title);

				$title = $page->navigation_label;

				$p = array(
					'attr' => array('id' => sprintf('p%d', $page->ID)),
					'data' => $title
					);

				$classes = array(); // CSS classes

				if ((is_array($pages_by_parent[$page->ID])) && (count($pages_by_parent[$page->ID]) > 0))
				{
					$p['state'] = 'closed';
					$p['attr']['rel'] = 'folder';

					$descendants = bu_navman_get_children($page->ID, $pages_by_parent);

					if (count($descendants) > 0) $p['children'] = $descendants;
				}

				if (!array_key_exists('state', $p))
				{
					$p['attr']['rel'] = $page->post_type;
				}

				if ($page->excluded)
				{
					$p['attr']['rel'] .= '_excluded';
					array_push($classes, 'excluded');
				}

				if ($page->restricted)
				{
					$p['attr']['rel'] .= '_restricted';
					array_push($classes, 'restricted');
				}

				$p['attr']['class'] = implode(' ', $classes);

				array_push($children, $p);
			}
		}
	}

	return $children;
}


function bu_navman_page_fields($fields)
{
	if (!is_array($fields)) $fields = array();
	array_push($fields, 'post_author');

	return $fields;
}

function bu_navman_filter_pages_add_authors($pages)
{
	global $wpdb;

	$filtered = array();

	if ((is_array($pages)) && (count($pages) > 0))
	{
		$ids = array_keys($pages);

		$query = sprintf("SELECT u.ID, u.user_nicename FROM %s u LEFT JOIN %s p ON p.post_author = u.ID WHERE p.ID IN (%s)", $wpdb->users, $wpdb->posts, implode(',', $ids));

		$authors = $wpdb->get_results($query, OBJECT_K); // get results as objects in an array keyed on post_id

		foreach ($pages as $page)
		{
			if (array_key_exists($page->post_author, $authors))
			{
				$page->author = $authors[$page->post_author]->user_nicename;
			}

			$filtered[$page->ID] = $page;
		}
	}

	return $filtered;
}

function bu_navman_set_lock()
{
	global $current_user;

	if (!bu_navman_check_lock())
	{
		$now = time();

		update_option(BU_NAVMAN_LOCK_TIME, $now);
		update_option(BU_NAVMAN_LOCK_USER, $current_user->ID);
	}
}

function bu_navman_check_lock()
{
	global $current_user;

	$lock_time = get_option(BU_NAVMAN_LOCK_TIME);
	$lock_user = get_option(BU_NAVMAN_LOCK_USER);

	$time_window = apply_filters('wp_check_post_lock_window', AUTOSAVE_INTERVAL * 2);

	if ( $lock_time && $lock_time > time() - $time_window && $lock_user != $current_user->ID )
		return $lock_user;

	return FALSE;
}

function bu_navman_clear_lock()
{
	global $current_user;

	$lock_user = get_option(BU_NAVMAN_LOCK_USER);

	if ($lock_user == $current_user->ID)
	{
		delete_option(BU_NAVMAN_LOCK_TIME);
		delete_option(BU_NAVMAN_LOCK_USER);
	}
}
?>
