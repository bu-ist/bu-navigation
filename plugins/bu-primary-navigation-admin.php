<?php
define('BU_NAV_OPTION_DISPLAY', 'bu_navigation_primarynav');
define('BU_NAV_OPTION_MAX', 'bu_navigation_primarynav_max');
define('BU_NAV_OPTION_DIVE', 'bu_navigation_primarynav_dive');
define('BU_NAV_OPTION_DEPTH', 'bu_navigation_primarynav_depth');

function bu_navigation_admin_menu_init()
{
	global $menu;
	
	$perm = is_site_admin() ? 0 : 'edit_pages';
	
	$parent = 'Site Options';
	
	$map = array();
	
	if ((is_array($menu)) && (count($menu) > 0))
	{
		foreach ($menu as $menu_item)
		{
			if ($menu_item[0])
			{
				$map[$menu_item[0]] = $menu_item[2];
			}	
		}
	}
	
	$filename = 'tools.php';
	
	if (array_key_exists($parent, $map))
	{
		$filename = $map[$parent];
	}
	
	$page = add_submenu_page($filename, __('Primary Navigation'), __('Primary Navigation'), $perm, __FILE__, 'bu_navigation_admin_menu_display');
	
	if ($page)
	{
		add_action('load-' . $page, 'bu_navigation_admin_menu_post');	
	}
}
add_action('admin_menu', 'bu_navigation_admin_menu_init');

function bu_navigation_admin_menu_display()
{
	/* default options */
	$bu_navigation_primarynav = TRUE;
	$bu_navigation_primarynav_max = BU_NAVIGATION_PRIMARY_MAX;
	$bu_navigation_primarynav_dive = TRUE;
	$bu_navigation_primarynav_depth = BU_NAVIGATION_PRIMARY_DEPTH;
	
	/* blog options if set */
	if (bu_navigation_blog_has_nav_options())
	{
		$bu_navigation_primarynav = get_option(BU_NAV_OPTION_DISPLAY);
		$bu_navigation_primarynav_max = get_option(BU_NAV_OPTION_MAX);
		if (!$bu_navigation_primarynav_max) $bu_navigation_primarynav = BU_NAVIGATION_PRIMARY_MAX;
		$bu_navigation_primarynav_dive = get_option(BU_NAV_OPTION_DIVE);
		$bu_navigation_primarynav_depth = get_option(BU_NAV_OPTION_DEPTH);
	}
	
	require_once(BU_NAV_PLUGIN_DIR . '/interface/primary-navigation-admin.php');
}

function bu_navigation_admin_menu_post()
{
	if ((array_key_exists('bu_navigation_primary_save', $_POST)) && ($_POST['bu_navigation_primary_save'] == 'save'))
	{
		$primarynav = intval($_POST['bu_navigation_primarynav']);
		$primarynav_max = intval($_POST['bu_navigation_primarynav_max']);
		if (!$primarynav_max) $primarynav_max = BU_NAVIGATION_PRIMARY_MAX;
		$primarynav_dive = intval($_POST['bu_navigation_primarynav_dive']);
		$primarynav_depth = intval($_POST['bu_navigation_primarynav_depth']);
		if (!$primarynav_depth) $primarynav_depth = BU_NAVIGATION_PRIMARY_DEPTH;

		update_option(BU_NAV_OPTION_DISPLAY, $primarynav);
		update_option(BU_NAV_OPTION_MAX, $primarynav_max);
		update_option(BU_NAV_OPTION_DIVE, $primarynav_dive);
		update_option(BU_NAV_OPTION_DEPTH, $primarynav_depth);
		
		if (function_exists('invalidate_blog_cache')) invalidate_blog_cache();
	}
}

function bu_navigation_filter_primarynav_defaults($defaults)
{
	if (bu_navigation_blog_has_nav_options())
	{
		$bu_navigation_primarynav = get_option(BU_NAV_OPTION_DISPLAY);
		$bu_navigation_primarynav_max = get_option(BU_NAV_OPTION_MAX);
		if (!$bu_navigation_primarynav_max) $bu_navigation_primarynav = BU_NAVIGATION_PRIMARY_MAX;
		$bu_navigation_primarynav_dive = get_option(BU_NAV_OPTION_DIVE);
		$bu_navigation_primarynav_depth = get_option(BU_NAV_OPTION_DEPTH);
		if (!$bu_navigation_primarynav_depth) $bu_navigation_primarynav_depth = BU_NAVIGATION_PRIMARY_DEPTH;

		$defaults['max_items'] = $bu_navigation_primarynav_max;
		$defaults['dive'] = $bu_navigation_primarynav_dive;
		$defaults['echo'] = $bu_navigation_primarynav;
		$defaults['depth'] = $bu_navigation_primarynav_depth;
	}
	
	return $defaults;
}
add_filter('bu_filter_primarynav_defaults', 'bu_navigation_filter_primarynav_defaults');

function bu_navigation_blog_has_nav_options()
{
	global $wpdb;
	
	$query = sprintf("SELECT COUNT(*) FROM %s WHERE option_name = '%s'", $wpdb->options, BU_NAV_OPTION_DISPLAY);
	
	$options = $wpdb->get_var($query);
	
	return (boolean)$options;
}
?>