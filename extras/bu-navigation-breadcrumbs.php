<?php

function bu_navigation_breadcrumbs($args = '')
{
	global $post;
	
	$defaults = array(
		'glue' => '&nbsp;&raquo;&nbsp;',
		'container_tag' => 'div',
		'container_id' => 'breadcrumbs',
		'container_class' => '',
		'anchor_class' => 'crumb',
		'crumb_current' => 1,
		'anchor_current' => 0,
		'echo' => 0,
		'home' => false,
		'home_label' => 'Home',
		'prefix' => '',
		'suffix' => ''
		);
	$r = wp_parse_args($args, $defaults);
	
	$attrs = '';
	
	if ($r['container_id']) $attrs .= sprintf(' id="%s"', $r['container_id']);
	if ($r['container_class']) $attrs .= sprintf(' class="%s"', $r['container_class']);
	
	$html = sprintf('<%s%s>%s', $r['container_tag'], $attrs, $r['prefix']);
	
	/* grab ancestors */
	$post_types = ( $post->post_type == 'page' ? array('page', 'link') : array($post->post_type) );
	$ancestors = bu_navigation_gather_sections($post->ID, array( 'post_types' => $post_types ));
	if (!in_array($post->ID, $ancestors)) array_push($ancestors, $post->ID);
	
//	$front_page = get_option('page_on_front');
//	if ($r['home'] && (!$ancestors[0])) {
//		$ancestors[0] = $front_page;
//	}
	$pages = bu_navigation_get_pages(array('pages' => $ancestors, 'supress_filter_pages' => true, 'post_types' => $post_types));

	$crumbs = array(); // array of HTML fragments for each crumb

	if ((is_array($pages)) && (count($pages) > 0))
	{
		foreach ($ancestors as $page_id)
		{
			if (!$page_id && $r['home']) {
				$crumb = sprintf('<a href="%s" class="%s">%s</a>', get_bloginfo('url'), $r['anchor_class'], $r['home_label']);
				array_push($crumbs, $crumb);
				continue;
			} else if (!array_key_exists($page_id, $pages)) continue;
			
			$p = $pages[$page_id];
			
			if (!isset($p->navigation_label)) $p->navigation_label = apply_filters('the_title', $p->post_title);

			$title = attribute_escape($p->navigation_label);
			if ($page_id == $front_page) {
				$title = str_replace('[label]', $title, $r['home_label']);
			}
			
			$href = $p->url;
			$classname = $r['anchor_class'];
			
			$crumb = '';
			
			if ($p->ID == $post->ID) $classname .= ' active';
			
			if (($p->ID == $post->ID) && (!$r['anchor_current']))
			{
				$crumb = sprintf('<a class="%s">%s</a>', $classname, $title);
			}
			else
			{
				$crumb = sprintf('<a href="%s" class="%s">%s</a>', $href, $classname, $title);
			}
			
			$crumb = apply_filters('bu_navigation_filter_crumb_html', $crumb, $p, $r);
			
			/* only crumb if not current page or if we're crumbing the current page */
			if (($p->ID != $post->ID) || ($r['crumb_current']))
				array_push($crumbs, $crumb);
		}
		
		$html .= implode($r['glue'], $crumbs);
	}
	
	$html .= sprintf('%s</%s>', $r['suffix'], $r['container_tag']);
	
	if ($r['echo']) echo $html;
	
	return $html;
}

/**
 * Returns breadcrumbs to the current page
 * Shortcode handler for 'breadcrumbs' code
 * @param $atts mixed Parameters
 * @return string HTML fragment
 */
function bu_navigation_breadcrumbs_sc($atts) 
{
	global $post;
	
	$defaults = array(
		'glue' => '&nbsp;&raquo;&nbsp;',
		'container_tag' => 'div',
		'container_id' => '',
		'container_class' => '',
		'anchor_class' => 'crumb',
		'crumb_current' => 1,
		'anchor_current' => 0,
		'echo' => 0
		);
			
	$r = shortcode_atts($defaults, $atts);
	
	$r['echo'] = 0; // never echo
		
	$crumbs = bu_navigation_breadcrumbs($r);
	
	return $crumbs;
}
add_shortcode('breadcrumbs', 'bu_navigation_breadcrumbs_sc');
?>
