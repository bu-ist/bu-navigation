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

		if ($instance['navigate_in_section'])
		{
			/* displaying family */			
			$sections = bu_navigation_gather_sections($post->ID);
			
			$section_id = $sections[1];
		}
		else
		{
			/* displaying entire site tree */
			$section_id = get_option('page_on_front');
		}
		
		if ($section_id)
		{
			$section = get_page($section_id);	

			$sections = apply_filters('bu_navigation_filter_page_labels', array($section->ID => $section));
			$section = array_shift($sections);

			if (!isset($section->navigation_label)) $section->navigation_label = apply_filters('the_title', $section->post_title);

			$title = attribute_escape($section->navigation_label);
			$href = get_page_link($section->ID);

			$html = sprintf('<a href="%s">%s</a>', $href, $title);
			$html .= "\n";
		}
		return $html;
	}
	
	function widget( $args, $instance ) 
	{
		global $post;

		extract( $args );
		
		$title = '';
		
		if (($instance['navigation_title'] == 'static') && (!empty($instance['navigation_title_text'])) && (!empty($instance['navigation_title_url'])))
		{
			$title = sprintf('<a href="%s">%s</a>', $instance['navigation_title_url'], apply_filters('widget_title', empty( $instance['navigation_title_text'] ) ? '' : $instance['navigation_title_text']));
		}
		else if ($instance['navigation_title'] == 'section')
		{
			$title = $this->section_title($args, $instance);
		}
		
		$exclude = empty( $instance['exclude'] ) ? '' : $instance['exclude'];
		
		//$before_widget_attrs = ($instance['contentnav'] == 1) ? ' id="contentnav" ' : '';
		$before_widget_attrs = ' id="contentnav" ';
		
		$before_widget = sprintf('<div %s class="">', $before_widget_attrs);
		$after_widget = '</div>';

		$list_args = array(
			'page_id' => $post->ID,
			'title_li' => '', 
			'echo' => 0, 
			'sort_column' => $sortby, 
			'exclude' => $exclude,
			'element_id' => BU_WIDGET_PAGES_LIST_ID,
			'element_class' => BU_WIDGET_PAGES_LIST_CLASS
			);
			
		if ((array_key_exists('navigate_in_section', $instance)) && ($instance['navigate_in_section'] == '1')) 
			$list_args['navigate_in_section'] = 1;
			
		do_action('bu_navigation_widget_before_list');
		
		$out = bu_navigation_list_pages( apply_filters('widget_bu_pages_args', $list_args ) );

		if ( !empty( $out ) ) 
		{
			echo $before_widget;
			if ( $title)
				echo $before_title . $title . $after_title;
			
			echo $out; // the list
			
			echo $after_widget;
		}
	}

	function update( $new_instance, $old_instance ) 
	{
		$instance = $old_instance;
		
		$instance['navigation_title'] = (in_array($new_instance['navigation_title'], $this->title_options)) ? $new_instance['navigation_title'] : 'none';
		
		$instance['navigation_title_text'] = ($instance['navigation_title'] == 'static') ? strip_tags($new_instance['navigation_title_text']) : '';
		$instance['navigation_title_url'] = ($instance['navigation_title'] == 'static') ? strip_tags($new_instance['navigation_title_url']) : '';
		
		$instance['contentnav'] = ($new_instance['contentnav'] == 1) ? 1 : 0;
		$instance['navigate_in_section'] = ($new_instance['navigate_in_section'] == 1) ? 1 : 0;
		
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
		
		$contentnav = $instance['contentnav'];
		$navigate_in_section = $instance['navigate_in_section'];
		
		require(BU_NAV_PLUGIN_DIR . '/interface/navigation-widget-form.php');
	}
}
?>