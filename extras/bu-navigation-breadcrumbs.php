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
		'echo' => 0
		);
	
	$r = wp_parse_args($args, $defaults);
	
	$attrs = '';
	
	if ($r['container_id']) $attrs .= sprintf(' id="%s"', $r['container_id']);
	if ($r['container_class']) $attrs .= sprintf(' class="%s"', $r['container_class']);
	
	$html = sprintf('<%s%s>', $r['container_tag'], $attrs);
	
	/* grab ancestors */
	$ancestors = bu_navigation_gather_sections($post->ID);
	if (!in_array($post->ID, $ancestors)) array_push($ancestors, $post->ID);
		
	$pages = bu_navigation_get_pages(array('pages' => $ancestors));
	
	$crumbs = array(); // array of HTML fragments for each crumb

	if ((is_array($pages)) && (count($pages) > 0))
	{
		foreach ($ancestors as $page_id)
		{
			if (!array_key_exists($page_id, $pages)) continue;
			
			$p = $pages[$page_id];
			
			if (!isset($p->navigation_label)) $p->navigation_label = apply_filters('the_title', $p->post_title);

			$title = attribute_escape($p->navigation_label);
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
	
	$html .= sprintf('</%s>', $r['container_tag']);
	
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