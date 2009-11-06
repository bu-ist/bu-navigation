<?php
define('BU_NAV_OPTION_DISPLAY', 'bu_navigation_primarynav');
define('BU_NAV_OPTION_MAX', 'bu_navigation_primarynav_max');
define('BU_NAV_OPTION_DIVE', 'bu_navigation_primarynav_dive');
define('BU_NAV_OPTION_DEPTH', 'bu_navigation_primarynav_depth');
define('BU_NAV_OPTION_ALLOW_TOP', 'bu_allow_top_level_page');

function bu_navigation_admin_menu_display_init()
{
	global $menu;
	
	$perm = is_site_admin() ? 0 : 'edit_pages';
	
	$parents = array('Navigation', 'Site Options');
		
	$page = bu_add_submenu_page($parents, __('Primary Navigation'), __('Primary Navigation'), $perm, __FILE__, 'bu_navigation_admin_menu_display');
}
add_action('admin_menu', 'bu_navigation_admin_menu_display_init');

function bu_navigation_admin_menu_display()
{
	$saved = bu_navigation_admin_menu_post();
	
	/* default options */
	$bu_navigation_primarynav = TRUE;
	$bu_navigation_primarynav_max = BU_NAVIGATION_PRIMARY_MAX;
	$bu_navigation_primarynav_dive = TRUE;
	$bu_navigation_primarynav_depth = BU_NAVIGATION_PRIMARY_DEPTH;
	$bu_allow_top_level_page = FALSE;
	
	/* blog options if set */
	if (bu_navigation_blog_has_nav_options())
	{
		$bu_navigation_primarynav = get_option(BU_NAV_OPTION_DISPLAY);
		$bu_navigation_primarynav_max = get_option(BU_NAV_OPTION_MAX);
		if (!$bu_navigation_primarynav_max) $bu_navigation_primarynav = BU_NAVIGATION_PRIMARY_MAX;
		$bu_navigation_primarynav_dive = get_option(BU_NAV_OPTION_DIVE);
		$bu_navigation_primarynav_depth = get_option(BU_NAV_OPTION_DEPTH);
		$bu_allow_top_level_page = get_option(BU_NAV_OPTION_ALLOW_TOP);
	}
	
	require_once(BU_NAV_PLUGIN_DIR . '/interface/primary-navigation-admin.php');
}

function bu_navigation_admin_menu_post()
{
	$saved = NULL;

	if ((array_key_exists('bu_navigation_primary_save', $_POST)) && ($_POST['bu_navigation_primary_save'] == 'save'))
	{
		$saved = TRUE; /* no useful return from update_option */
		
		$primarynav = intval($_POST['bu_navigation_primarynav']);
		$primarynav_max = intval($_POST['bu_navigation_primarynav_max']);
		if (!$primarynav_max) $primarynav_max = BU_NAVIGATION_PRIMARY_MAX;
		$primarynav_dive = intval($_POST['bu_navigation_primarynav_dive']);
		$primarynav_depth = intval($_POST['bu_navigation_primarynav_depth']);
		if (!$primarynav_depth) $primarynav_depth = BU_NAVIGATION_PRIMARY_DEPTH;
		$bu_allow_top_level_page = intval($_POST['bu_allow_top_level_page']);

		update_option(BU_NAV_OPTION_DISPLAY, $primarynav);\
		update_option(BU_NAV_OPTION_MAX, $primarynav_max);
		update_option(BU_NAV_OPTION_DIVE, $primarynav_dive);
		update_option(BU_NAV_OPTION_DEPTH, $primarynav_depth);
		update_option(BU_NAV_OPTION_ALLOW_TOP, $bu_allow_top_level_page);
		
		$bu_navigation_changes_saved = true;
		
		if (function_exists('invalidate_blog_cache')) invalidate_blog_cache();
	}
	
	return $saved;
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
		$bu_allow_top_level_page = get_option(BU_NAV_OPTION_ALLOW_TOP);

		$defaults['max_items'] = $bu_navigation_primarynav_max;
		$defaults['dive'] = $bu_navigation_primarynav_dive;
		$defaults['echo'] = $bu_navigation_primarynav;
		$defaults['depth'] = $bu_navigation_primarynav_depth;
		$defaults['allow_top'] = $bu_allow_top_level_page;
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
