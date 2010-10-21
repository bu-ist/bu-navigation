<?php
/*
 * Alternative content (side) navigation widget
 * Niall Kavanagh
 * ntk@bu.edu
 */

define('BU_WIDGET_PAGES_LIST_CLASS', 'smartnav level1'); 				// default class for list 
define('BU_WIDGET_PAGES_LIST_ID', 'contentnavlist'); 			// default element id for list 

define('BU_WIDGET_CONTENTNAV_BEFORE', '<div id="contentnav">'); 	// default HTML fragment open
define('BU_WIDGET_CONTENTNAV_AFTER', '</div>'); 					// default HTML fragment close

class BU_Widget_Pages extends WP_Widget 
{
	var $title_options = array('none', 'section', 'static');
	var $styles = array('site', 'section', 'adaptive');
	
	function BU_Widget_Pages() 
	{
		$widget_ops = array('classname' => 'widget_bu_pages', 'description' => __( "Navigation list of your site's pages" ) );
		$this->WP_Widget('bu_pages', __('Content Navigation'), $widget_ops);
	}

	/**
	 * Returns HTML fragment containing a section title
	 * @return string HTML fragment with title
	 */
	function section_title($args, $instance)
	{
		global $post;

		$html = '';
		$title = '';
		$href = '';
		
		$section_id = 0;

		if ($instance['navigation_style'] != 'site')
		{
			/* displaying family */			
			$sections = bu_navigation_gather_sections($post->ID);
			
			if ($instance['navigation_style'] == 'adaptive')
			{
				$section_id = $sections[count($sections) - 2];
			}
			else
			{
				/* default to current top-level section */
				$section_id = $sections[1];
			}
		}
		
		if ($section_id)
		{
			$section = get_page($section_id);	

			$sections = apply_filters('bu_navigation_filter_page_labels', array($section->ID => $section));
			$section = array_shift($sections);

			if (!isset($section->navigation_label)) $section->navigation_label = apply_filters('the_title', $section->post_title);

			$title = attribute_escape($section->navigation_label);
			$href = get_page_link($section->ID);

			$html = sprintf('<a class="content_nav_header" href="%s">%s</a>', $href, $title);
			$html .= "\n";
		}
		else
		{
			/* Use site name as title */
			/* Note: I consider this a bug; we should use the navigation label of the homepage */
			/* Note 2: It's kind of pompous and/or passive-agressive to note my objections to this */
			/* here as nobody will ever read it. */
			
			$title = get_bloginfo('name');
			$href = get_bloginfo('url') . '/';

			$html = sprintf('<a class="content_nav_header" href="%s">%s</a>', $href, $title);
			$html .= "\n";
		}
		
		return $html;
	}
	
	function widget( $args, $instance ) 
	{
		global $post;

		extract( $args );
		
		$title = '';
		
		if (($instance['navigation_title'] == 'static') && (!empty($instance['navigation_title_text'])))
		{
			if (!empty($instance['navigation_title_url']))
			{
				$title = sprintf('<a class="content_nav_header" href="%s">%s</a>', $instance['navigation_title_url'], apply_filters('widget_title', empty( $instance['navigation_title_text'] ) ? '' : $instance['navigation_title_text']));
			}
			else
			{
				$title = apply_filters('widget_title', $instance['navigation_title_text']);
			}
		}
		else if ($instance['navigation_title'] == 'section')
		{
			$title = $this->section_title($args, $instance);
		}
		
		$exclude = empty( $instance['exclude'] ) ? '' : $instance['exclude'];
		
		$list_args = array(
			'page_id' => $post->ID,
			'title_li' => '', 
			'echo' => 0, 
			'sort_column' => $sortby, 
			'exclude' => $exclude,
			'container_id' => BU_WIDGET_PAGES_LIST_ID
			);
			
		if (array_key_exists('navigation_style', $instance))
		{
		  $list_args['style'] = $instance['navigation_style'];

			if ($instance['navigation_style'] == 'section')
			{
				$list_args['navigate_in_section'] = 1;
				if(is_404()) return '';
			} 
			else if ($instance['navigation_style'] == 'adaptive')
			{
				add_action('bu_navigation_widget_before_list', 'bu_navigation_widget_adaptive_before_list');
			}
		}
			
		do_action('bu_navigation_widget_before_list');

		$out = bu_navigation_list_pages( apply_filters('widget_bu_pages_args', $list_args ) );

		if ( !empty( $out ) ) 
		{
			printf('%s<div id="contentnav">', $before_widget);
			
			if ( $title)
				echo $before_title . $title . $after_title;
			
			printf('%s</div>', $out);
			
			echo $after_widget;
		}
	}

	function update( $new_instance, $old_instance ) 
	{
		$instance = $old_instance;
		
		$instance['navigation_title'] = (in_array($new_instance['navigation_title'], $this->title_options)) ? $new_instance['navigation_title'] : 'none';
		
		$instance['navigation_title_text'] = ($instance['navigation_title'] == 'static') ? strip_tags($new_instance['navigation_title_text']) : '';
		$instance['navigation_title_url'] = ($instance['navigation_title'] == 'static') ? strip_tags($new_instance['navigation_title_url']) : '';
		
		$instance['navigation_style'] = (in_array($new_instance['navigation_style'], $this->styles)) ? $new_instance['navigation_style'] : 'site';
		
		if (function_exists('invalidate_blog_cache')) invalidate_blog_cache();

		return $instance;
	}

	function form( $instance ) 
	{
		//Defaults
		$instance = wp_parse_args( (array) $instance, array( 'sortby' => 'post_title', 'title' => '', 'exclude' => '') );
				
		$navigation_title = (in_array($instance['navigation_title'], $this->title_options)) ? $instance['navigation_title'] : 'none';
		
		$navigation_title_text = esc_attr( $instance['navigation_title_text'] );
		$navigation_title_url = esc_attr( $instance['navigation_title_url'] );
	
		$exclude = esc_attr( $instance['exclude'] );
		
		$navigation_style = (in_array($instance['navigation_style'], $this->styles)) ? $instance['navigation_style'] : 'site';
		
		require(BU_NAV_PLUGIN_DIR . '/interface/navigation-widget-form.php');
	}
}
?>
