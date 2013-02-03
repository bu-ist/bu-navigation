<?php
/**
 * Generic navigation function for WordPress 2.8+
 * Niall Kavanagh
 * ntk@bu.edu
 */

define( 'BU_NAVIGATION_LIB_LOADED', TRUE );

define( 'GROUP_CONCAT_MAX_LEN', 20480 );

/**
 * Gets the supported post_types by the bu-navigation plugin.
 *
 * This function is located here in the bu-includes because sometimes only this file is loaded to work with bu-navigation (like on the frontend listing pages).
 *
 * @param boolean $include_link true|false link post_type is something special, so we don't always need it
 * @return array of post_type strings
 */
function bu_navigation_supported_post_types($include_link = false) {

	$post_types = get_post_types(array('hierarchical' => true), 'names');
	$post_types = apply_filters('bu_navigation_post_types', $post_types);

	if ( $GLOBALS['bu_navigation_plugin']->supports( 'links' ) && $include_link )
		$post_types[] = BU_NAVIGATION_LINK_POST_TYPE;

	return $post_types;

}

/**
 * Returns all the sections with children and all the pages with parents (so both ways)
 *
 * @todo add an "include_links" argument
 *
 * @global type $wpdb
 * @param array $post_types focus on a specific post_type
 * @return array (sections => array(sectionid1 => [pageid1, ...], ...), pages => array( pageid1 => sectionid1, ... )
 */
function bu_navigation_load_sections($post_types) {

	global $wpdb;

	$wpdb->query('SET SESSION group_concat_max_len = ' . GROUP_CONCAT_MAX_LEN);

	if ( empty( $post_types ) ) {
		$post_types = array( 'page' );

		if( $GLOBALS['bu_navigation_plugin']->supports( 'links' ) )
			$post_types[] = BU_NAVIGATION_LINK_POST_TYPE;
	}

	// handle custom post types support
	$in_post_types = "'" . implode("','", (array)$post_types) . "'";

	$query = sprintf("
		SELECT DISTINCT(post_parent) AS section, GROUP_CONCAT(ID) AS children
		  FROM %s
		 WHERE post_type IN ($in_post_types)
		 GROUP BY post_parent
		 ORDER BY post_parent ASC", $wpdb->posts);
	$rows = $wpdb->get_results($query);

	$sections = array();
	$pages = array();

	if ((is_array($rows)) && (count($rows) > 0))
	{
		foreach ($rows as $row)
		{
			$sections[$row->section] = explode(',', $row->children);

			if ((is_array($sections[$row->section])) && (count($sections[$row->section]) > 0))
			{
				foreach ($sections[$row->section] as $child)
				{
					$pages[$child] = $row->section;
				}
			}
		}
	}

	return array('sections' => $sections, 'pages' => $pages);
}

/**
 * @todo needs docblock
 */
function bu_navigation_gather_childsections($parent_id, $sections, $max_depth = 0, $current_depth = 1)
{
	$child_sections = array();

	if ((array_key_exists($parent_id, $sections)) && (count($sections[$parent_id]) > 0))
	{
		foreach ($sections[$parent_id] as $child_id)
		{
			if ((array_key_exists($child_id, $sections)) && (count($sections[$child_id]) > 0))
			{
				array_push($child_sections, $child_id);

				if (($max_depth == 0) || ($current_depth < $max_depth))
				{
					$child_sections = array_merge($child_sections, bu_navigation_gather_childsections($child_id, $sections, $max_depth, ($current_depth + 1)));
				}
			}
		}
	}

	return $child_sections;
}

/**
 * @todo needs docblock
 */
function bu_navigation_get_page_depth($page_id, $all_sections = NULL)
{
	$ancestry = bu_navigation_gather_sections($page_id, NULL, $all_sections);

	$depth = count($ancestry);

	if (!in_array($page_id, $ancestry)) $depth++;

	$depth--;

	return $depth;
}

/**
 * @todo needs docblock
 * @todo add an "include_links" argument, and remove logic from post_types arg
 */
function bu_navigation_gather_sections($page_id, $args = '', $all_sections = NULL)
{
	$defaults = array(
		'direction' => 'up',
		'depth' => 0,
		'post_types' => $GLOBALS['bu_navigation_plugin']->supports( 'links' ) ? array('page', BU_NAVIGATION_LINK_POST_TYPE ) : array( 'page' ),
		);

	$r = wp_parse_args($args, $defaults);

	if (is_null($all_sections)) $all_sections = bu_navigation_load_sections($r['post_types']);
	$pages = $all_sections['pages'];

	$sections = array();

	/* if this page has children, count it as a section itself */

	if (array_key_exists($page_id, $all_sections['sections'])) array_push($sections, $page_id);

	if ($r['direction'] == 'down')
	{
		/* gather child sections */

		$child_sections = bu_navigation_gather_childsections($page_id, $all_sections['sections'], $r['depth']);

		if (count($child_sections) > 0) $sections = array_merge($sections, $child_sections);
	}
	else
	{
		/*  gather parent sections */

		if (array_key_exists($page_id, $pages))
		{
			$current_section = $pages[$page_id];

			array_push($sections, $current_section);

			while ($current_section != 0)
			{
				if (array_key_exists($current_section, $pages))
				{
					$current_section = $pages[$current_section];
					array_push($sections, $current_section);
				}
				else
				{
					break;
				}
			}
		}
	}

	$sections = array_reverse($sections);

	return $sections;
}

/**
 * @todo needs docblock
 */
function bu_navigation_pull_page($page_id, $pages)
{
	$page = FALSE;

	if (array_key_exists($page_id, $pages)) $page = $pages[$page_id];

	return $page;
}

/**
 * @todo investigate memory usage / queries generated by get_permalink
 * @todo investigate usage of $post->filter = sample, from WP 3.1 -> 3.5
 */
function bu_navigation_get_urls( $pages ) {

	$pages_with_urls = $pages;

	if ( is_array($pages) && count($pages) > 0 ) {
		foreach ( $pages as $page ) {

			$page->url = get_permalink( $page );

			$pages_with_urls[$page->ID] = $page;

		}

	}

	return $pages_with_urls;
}

/**
* Returns an array of page objects indexed by page ID
*
* @todo add an "include_links" argument, and remove the logic from the post_types arg
*
* TODO: Function incomplete; most arguments ignored. Sort order should allow +1 column
* @param $args mixed Wordpress-style arguments (string or array)
* @return array Array of pages keyed on page ID or FALSE on problem
*/
function bu_navigation_get_pages($args = '')
{
	global $wpdb;

	$defaults = array(
		'sections' => NULL,
		'pages' => NULL,
		'child_of' => 0,
		'hierarchical' => 1,
		'primary' => '',
		'max_items' => '',
		'post_types' => $GLOBALS['bu_navigation_plugin']->supports( 'links' ) ? array( 'page', BU_NAVIGATION_LINK_POST_TYPE ) : array( 'page' ),
		'post_status' => 'publish',
		'suppress_filter_pages' => FALSE,
		'suppress_urls' => FALSE,
		'cache_results' => true
		);

	$r = wp_parse_args($args, $defaults); // parsed arguments

	$params = array(); /* will hold parameters to pass to query */

	/* build the query */
	$query = sprintf("SELECT * FROM %s ", $wpdb->posts);

	// handle custom post types support
	$in_post_types = "'" . implode("','", (array)$r['post_types']) . "'";

	/* only get pages and maybe external links */
	$query .= " WHERE post_type IN ($in_post_types) ";

	/* restrict status of pages */
	if ($r['post_status']) {
		$post_statuses = "'" . implode("','", (array) $r['post_status']) . "'";
		if( $post_statuses ) {
			$query .= " AND post_status IN ($post_statuses) ";
		}
	}

	if ((is_array($r['sections'])) && (count($r['sections']) > 0))
	{
		/* only get pages in specific sections */
		$r['sections'] = array_map('absint', $r['sections']);
		$query .= sprintf(' AND post_parent IN(%s) ', implode(',', $r['sections']));
	}

	if ((is_array($r['pages'])) && (count($r['pages']) > 0))
	{
		/* only get specific pages */
		$r['pages'] = array_map('absint', $r['pages']);
		$query .= sprintf(' AND ID IN(%s) ', implode(',', $r['pages']));
	}

	/* result sorting */
	$query .= ' ORDER BY post_parent ASC, menu_order ASC';

	$sql = $wpdb->prepare( $query, $params );

	$pages = $wpdb->get_results( $sql, OBJECT_K ); // get results as objects in an array keyed on posts.ID

	if ((!is_array($pages)) || (count($pages) == 0)) return FALSE;

	// Make subsequent calls to get_post skip the DB query
	if ( $r['cache_results'] )
		update_post_cache( $pages );

	// Add url property to each post object ($post->url = permalink)
	if (!$r['suppress_urls']) $pages = bu_navigation_get_urls( $pages );

	// Allow custom filtering of pages retrieved using this function
	if (!$r['suppress_filter_pages']) $pages = apply_filters('bu_navigation_filter_pages', $pages);

	// Chop off anything great than max_items
	if (($r['max_items']) && (is_array($pages)) && (count($pages) > 0))
	{
		$items = array();
		$nItems = 0;

		foreach ( $pages as $page_id => $page ) {
			if ( $nItems >= $r['max_items']) break;
			$items[$page_id] = $page;
			$nItems++;
		}

		$pages = $items;
	}

	return $pages;
}

/**
 * Indexes an array of pages by their parent page ID
 *
 * @param $pages array Array of page objects (usually indexed by the post.ID)
 * @return array Array of arrays indexed on post.ID with second-level array containing the immediate children of that post
 */
function bu_navigation_pages_by_parent($pages)
{
	$pages_by_parent = array();

	if ((is_array($pages)) && (count($pages) > 0))
	{
		foreach ($pages as $page)
		{
			if (!array_key_exists($page->post_parent, $pages_by_parent)) $pages_by_parent[$page->post_parent] = array();
			array_push($pages_by_parent[$page->post_parent], $page);
		}
	}

	$pages_by_parent = apply_filters('bu_navigation_filter_pages_by_parent', $pages_by_parent);

	return $pages_by_parent;
}

/**
 * Add this filter before calling bu_navigation_pages_by_parent to sort each sub-array by menu order.
 */
function bu_navigation_pages_by_parent_menu_sort($pages)
{
	if (is_array($pages))
	{
		foreach ($pages as $parent_id => &$children)
		{
			usort($children, 'bu_navigation_pages_by_parent_menu_sort_cb');
		}
	}

	return $pages;
}

/**
 * Callback for bu_navigation_pages_by_parent_menu_sort.
 */
function bu_navigation_pages_by_parent_menu_sort_cb($a, $b)
{
	return ($a->menu_order - $b->menu_order);
}

/**
 * Formats a single page for display in a HTML list
 *
 * @param $page object Page object
 * @param $html string Option HTML to place inside the list item after the page
 * @return string HTML fragment containing list item
 */
function bu_navigation_format_page($page, $args = '')
{
	$defaults = array(
		'item_tag' => 'li',
		'item_id' => NULL,
		'html' => '',
		'depth' => NULL,
		'position' => NULL,
		'siblings' => NULL,
		'anchor_class' => '',
		'anchor' => TRUE,
		'section_ids' => NULL
		);

	$r = wp_parse_args($args, $defaults);

	if (!isset($page->navigation_label)) $page->navigation_label = apply_filters('the_title', $page->post_title);

	$title = esc_attr($page->navigation_label);

	$href = $page->url;

	$anchorClass = $r['anchor_class'];

	if (is_numeric($r['depth'])) $anchorClass .= sprintf(' level_%d', intval($r['depth']));

	$attrs = array(
		'title' => esc_attr($title),
		'href' => $page->url,
		'class' => trim($anchorClass)
		);

	if (isset($page->target) && $page->target == 'new') $attrs['target'] = '_blank';

	$attrs = apply_filters('bu_navigation_filter_anchor_attrs', $attrs, $page);

	$attributes = '';

	if ((is_array($attrs)) && (count($attrs) > 0))
	{
		foreach ($attrs as $attr => $val)
		{
			if ($val) $attributes .= sprintf(' %s="%s"', $attr, $val);
		}
	}

	$item_classes = array('page_item', 'page-item-' . $page->ID);

	if ((is_array($r['section_ids'])) && (in_array($page->ID, $r['section_ids']))) array_push($item_classes, 'has_children');


	if ((is_numeric($r['position'])) && (is_numeric($r['siblings'])))
	{
		if ($r['position'] == 1) array_push($item_classes, 'first_item');
		if ($r['position'] == $r['siblings']) array_push($item_classes, 'last_item');
	}

	$item_classes = apply_filters('bu_navigation_filter_item_attrs', $item_classes, $page);
	$item_classes = apply_filters('page_css_class', $item_classes, $page);

	$title = apply_filters('bu_page_title', $title);

	$anchor = $r['anchor'] ? sprintf('<a%s>%s</a>', $attributes, $title) : $title;
	$html = sprintf("<%s class=\"%s\">\n%s\n %s</%s>\n", $r['item_tag'], implode(' ', $item_classes), $anchor, $r['html'], $r['item_tag']);

	if ($r['item_id'])
	{
		$html = sprintf("<%s id=\"%s\" class=\"%s\">\n%s\n %s</%s>\n", $r['item_tag'], $r['item_id'], implode(' ', $item_classes), $anchor, $r['html'], $r['item_tag']);
	}

	$args = $r;
	$args['attributes'] = $attrs;

	$html = apply_filters('bu_navigation_filter_item_html', $html, $page, $args);

	return $html;
}

/**
 * Filter to apply "active" class to a navigation item container if it is the current page
 *
 * @todo relocate to a default filters file
 *
 * @param $attributes array Associative array of anchor attributes
 * @param $page object Page object
 * @return array Array of classes
 */
function bu_navigation_filter_item_attrs($classes, $page)
{
	global $wp_query;
	if ( is_singular() || $wp_query->is_posts_page )
	{
		$current_page = $wp_query->get_queried_object();

		if ($current_page->ID == $page->ID) array_push($classes, 'current_page_item');

		if ($page->active_section) array_push($classes, 'current_page_ancestor');

		if ($page->ID == $current_page->post_parent) array_push($classes, 'current_page_parent');
	}

	return $classes;
}
add_filter('bu_navigation_filter_item_attrs', 'bu_navigation_filter_item_attrs', 10, 2);

/**
 * Filter to apply "active" class to a navigation item if it is the current page
 *
 * @todo relocate to a default filters file
 *
 * @param $attributes array Associative array of anchor attributes
 * @param $page object Page object
 */
function bu_navigation_filter_item_active_page($attributes, $page)
{
	global $wp_query;

	if ( is_singular() || $wp_query->is_posts_page )
	{
		$current_page = $wp_query->get_queried_object();

		if ($current_page->ID == $page->ID) $attributes['class'] .= ' active';

		if ($page->active_section) $attributes['class'] .= ' active_section';
	}

	return $attributes;
}
add_filter('bu_navigation_filter_anchor_attrs', 'bu_navigation_filter_item_active_page', 10, 2);

/**
 * Generates an unordered list tree of pages in a particular section
 *
 * @param $parent_id Integer ID of section (page parent)
 * @param $pages_by_parent array An array of pages indexed by their parent page (see bu_navigation_pages_by_parent)
 * @return string HTML fragment containing unordered list
 */
function bu_navigation_list_section($parent_id, $pages_by_parent, $args = '')
{
	$defaults = array(
		'depth' => 1,
		'container_tag' => 'ul',
		'item_tag' => 'li',
		'section_ids' => NULL
		);

	$r = wp_parse_args($args, $defaults);

	$html = '';

	if (array_key_exists($parent_id, $pages_by_parent))
	{
		$children = $pages_by_parent[$parent_id];

		if ((is_array($children)) && (count($children) > 0))
		{
			$html .= sprintf("\n<%s>\n", $r['container_tag']);;

			foreach ($children as $page)
			{
				$sargs = $r;
				$sargs['depth']++;

				$child_html = bu_navigation_list_section($page->ID, $pages_by_parent, $sargs);
				$html .= bu_navigation_format_page($page, array(
					'html' => $child_html,
					'depth' => $r['depth'],
					'item_tag' => $r['item_tag'],
					'section_ids' => $r['section_ids']
					));
			}

			$html .= sprintf("\n</%s>\n", $r['container_tag']);
		}
	}

	return $html;
}

/**
 * Alternative to WordPress' wp_list_pages function
 *
 * @todo add an "include_links" arg, remove logic from post_types arg (used by content nav widget)
 *
 * @param $args mixed Array or string of WP-style arguments
 * @return string HTML fragment containing navigation list
 */
function bu_navigation_list_pages($args = '')
{
	$defaults = array(
		'page_id' => NULL,
		'title_li' => '',
		'echo' => 0,
		'navigate_in_section' => '',
		'container_tag' => 'ul',
		'container_id' => '',
		'container_class' => '',
		'item_tag' => 'li',
		'style' => NULL,
		'post_types' => $GLOBALS['bu_navigation_plugin']->supports( 'links' ) ? array('page', BU_NAVIGATION_LINK_POST_TYPE ) : array( 'page' )
		);

	$r = wp_parse_args($args, $defaults);

	$output = '';

	$section_ids = array();

	if ((array_key_exists('page_id', $r)) && ($r['page_id']))
	{
		$all_sections = bu_navigation_load_sections($r['post_types']);
		$section_ids = array_keys($all_sections['sections']);

		$r['sections'] = bu_navigation_gather_sections($r['page_id'], NULL, $all_sections);
	}

	$pages = bu_navigation_get_pages($r);

	$list_attributes = '';

	if ($r['container_id']) $list_attributes .= sprintf(' id="%s"', $r['container_id']);
	if ($r['container_class']) $list_attributes .= sprintf(' class="%s"', $r['container_class']);

	$html = sprintf("<%s %s>\n", $r['container_tag'], $list_attributes);

	$pages_by_parent = bu_navigation_pages_by_parent($pages);

	$sections = array_key_exists('sections', $r) ? $r['sections'] : array_keys($pages_by_parent);

	if ($r['style'] == 'adaptive')
	{
		/* if the "active" page isn't in the list of sections
		 * (because it has no children), add it
		 */
		if (($r['page_id']) && (!in_array($r['page_id'], $sections)))
		{
			array_push($sections, $r['page_id']);
		}

		if (count($sections) > 2)
		{
			$last_section = array_pop($sections);
			array_push($sections, $last_section);

			if ((is_array($pages_by_parent[$last_section])) && (count($pages_by_parent[$last_section]) > 0))
			{
				/* The last section has children, so it will be the "top" */
				$sections = array_slice($sections, -2);
			}
			else
			{
				/* Last section has no children, so it's parent will be the "top" */
				$sections = array_slice($sections, -3);
			}
		}
	}

	$section = $sections[0]; // default to top level pages

	if ($r['navigate_in_section']) $section = $sections[1];

	/* loop over the top section */
	if ((is_array($pages_by_parent[$section])) && (count($pages_by_parent[$section]) > 0))
	{
		/* arguments for sections */
		$sargs = array(
			'container_tag' => $r['container_tag'],
			'item_tag' => $r['item_tag'],
			'depth' => 2,
			'section_ids' => $section_ids
			);

		$page_position = 1;
		$number_siblings = count($pages_by_parent[$section]);

		foreach ($pages_by_parent[$section] as $page)
		{
			$child_html = bu_navigation_list_section($page->ID, $pages_by_parent, $sargs);

			$pargs = array(
				'html' => $child_html,
				'depth' => 1,
				'position' => $page_position,
				'siblings' => $number_siblings,
				'item_tag' => $r['item_tag'],
				'section_ids' => $section_ids
			);

			$html .= bu_navigation_format_page($page, $pargs);

			$page_position++;
		}
	}
	else
	{
		return ''; // nothing to display, return nothing
	}

	$html .= sprintf("</%s>\n", $r['container_tag']);

	if ($r['echo']) echo $html;

	return $html;
}

/**
 * Displays a primary navigation bar
 *
 * @todo add a "include_links" arg, remove logic from post_types arg
 *
 * @return void
 */
function bu_navigation_display_primary($args = '')
{
	$defaults = array(
		'echo' => 1,
		'depth' => BU_NAVIGATION_PRIMARY_DEPTH,
		'dive' => TRUE,
		'max_items' => BU_NAVIGATION_PRIMARY_MAX,
		'container_tag' => 'ul',
		'container_id' => 'nav',
		'container_class' => '',
		'item_tag' => 'li',
		'identify_top' => FALSE,
		'whitelist_top' => NULL,
		'post_types' => $GLOBALS['bu_navigation_plugin']->supports( 'links' ) ? array('page', BU_NAVIGATION_LINK_POST_TYPE ) : array( 'page' ),
		);

	$defaults = apply_filters('bu_filter_primarynav_defaults', $defaults);

	$r = wp_parse_args($args, $defaults);
	$max_items = $r['max_items'];

	/* first get top level pages only */
	$sections = array(0);
	$r['sections'] = $sections;
	$pages = bu_navigation_get_pages($r);

	/* now get children needed by pnav */

	if ((is_array($pages)) && (count($pages) > 0))
	{
		foreach ($pages as $page_id => $page)
		{
			array_push($sections, $page_id);
		}
	}

	$section_args = array('direction' => 'down', 'depth' => $r['depth'], 'sections' => $sections, 'post_types' => $r['post_types']);
	$r['sections'] = bu_navigation_gather_sections(0, $section_args);

	/* grab all pages  we need for pnav */
	$r['max_items'] = '';

	$pages = bu_navigation_get_pages($r);

	$html = sprintf('<%s id="%s" class="%s %s">', $r['container_tag'], $r['container_id'], $r['container_class'], ($r['dive']) ? '' : 'no-dive');

	$pages_by_parent = bu_navigation_pages_by_parent($pages);

	$section = 0; // default to top level pages

	$nItems = 0;

	$whitelist = NULL; // whitelist for top level pages

	if ($r['whitelist_top'])
	{
		if (is_string($r['whitelist_top'])) $whitelist = explode(',', $r['whitelist_top']);
		if (is_array($r['whitelist_top'])) $whitelist = $r['whitelist_top'];
	}

	if ((is_array($pages_by_parent[$section])) && (count($pages_by_parent[$section]) > 0))
	{
		/* arguments for sections */
		$sargs = array(
			'container_tag' => $r['container_tag'],
			'item_tag' => $r['item_tag'],
			'depth' => 2
			);

		/* loop over the top section */
		foreach ($pages_by_parent[$section] as $page)
		{
			/* check whitelist if we're using one */
			if ((is_array($whitelist)) && (!in_array($page->post_name, $whitelist))) continue;

			if ($r['dive']) $child_html = bu_navigation_list_section($page->ID, $pages_by_parent, $sargs);

			if ($r['identify_top'])
			{
				$html .= bu_navigation_format_page($page, array('html' => $child_html, 'depth' => 1, 'item_tag' => $r['item_tag'], 'item_id' => $page->post_name));
			}
			else
			{
				$html .= bu_navigation_format_page($page, array('html' => $child_html, 'depth' => 1, 'item_tag' => $r['item_tag']));
			}

			$nItems++;

			if ($nItems >= $max_items) break;
		}

		$html .= sprintf("\n</%s>\n", $r['container_tag']);

		if ($r['echo']) echo $html;

		return $html;
	}
	else
	{
		// no-op, display nothing
	}
}

/**
 * Generate page parent select menu
 *
 * @todo should this have an "include_links" argument as well?
 *
 * @uses bu_filter_pages_parent_dropdown().
 *
 * @param string $post_type required -- post type to filter posts for
 * @param int $selected post ID of the selected post
 * @param array $args optional configuration object
 *
 * @return string the resulting dropdown markup
 */
function bu_navigation_page_parent_dropdown( $post_type, $selected = 0, $args = array() ) {

	$defaults = array(
		'echo' => 1,
		'select_id' => 'bu_filter_pages',
		'select_name' => 'post_parent',
		'select_classes' => ''
		);
	extract( wp_parse_args( $args, $defaults) );

	// Grab top level pages for current post type
	$section_args = array('direction' => 'down', 'depth' => 1, 'post_types' => array($post_type));

	$args = array(
		'suppress_filter_pages' => TRUE,
		'sections' => bu_navigation_gather_sections(0, $section_args),
		'post_types' => array($post_type),
		);

	$pages = bu_navigation_get_pages($args);
	$pages_by_parent = bu_navigation_pages_by_parent($pages);

	$options = "\n\t<option value=\"0\">" . __('Show all sections') . "</option>\r";

	// Get options
	ob_start();
	bu_filter_pages_parent_dropdown( $pages_by_parent, $selected );
	$options .= ob_get_contents();
	ob_end_clean();

	$classes = ! empty( $select_classes ) ? " class=\"$select_classes\"" : '';

	$dropdown = sprintf( "<select id=\"%s\" name=\"%s\"%s>\r%s\r</select>\r", $select_id, $select_name, $classes, $options );

	if( $echo ) echo $dropdown;

	return $dropdown;

}

/**
 * Displays a select box containing page parents, used to filter page list by parent
 *
 * Relocated from the navigation plugin (bu-filter-pages.php) to remove dependency on plugin.
 *
 * @return boolean TRUE if the box was displayed, FALSE otherwise.
 */
function bu_filter_pages_parent_dropdown($pages_by_parent, $default = 0, $parent = 0, $level = 0) {

	$post_types = bu_navigation_supported_post_types();

	if ((is_array($pages_by_parent)) && (array_key_exists($parent, $pages_by_parent)) && (count($pages_by_parent) > 0)) {
		foreach ($pages_by_parent[$parent] as $p) {

			if (!in_array($p->post_type, $post_types)) continue; // only show valid post types
			if (!array_key_exists($p->ID, $pages_by_parent)) continue; // don't show pages with no children

			$padding = str_repeat('&nbsp;', $level * 3);
			$selected = ($p->ID == $default) ? 'selected="selected"' : '';

			printf("\n\t<option value=\"%d\" %s>%s%s</option>\r", $p->ID, $selected, $padding, esc_html($p->post_title));
			bu_filter_pages_parent_dropdown($pages_by_parent, $default, $p->ID, $level + 1);
		}

		return TRUE;

	}

	return FALSE;

}

?>
