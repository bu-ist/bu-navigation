<?php
/*
 * Alternative content (side) navigation widget
 * Niall Kavanagh
 * ntk@bu.edu
 */

define('BU_WIDGET_PAGES_LIST_CLASS', 'smartnav'); 				// default class for list 
define('BU_WIDGET_PAGES_LIST_ID', 'contentnavlist'); 			// default element id for list 

define('BU_WIDGET_CONTENTNAV_BEFORE', '<div id="contentnav">'); 	// default HTML fragment open
define('BU_WIDGET_CONTENTNAV_AFTER', '</div>'); 					// default HTML fragment close

class BU_Widget_Pages extends WP_Widget 
{
	var $title_options = array('none', 'section', 'static');
	
	function BU_Widget_Pages() 
	{
		$widget_ops = array('classname' => 'widget_bu_pages', 'description' => __( "Navigation list of your site's pages" ) );
		$this->WP_Widget('bu_pages', __('Navigation'), $widget_ops);
	}

	function widget( $args, $instance ) 
	{
		global $post;

		extract( $args );
		
		$title = apply_filters('widget_title', empty( $instance['title'] ) ? '' : $instance['title']);
		$sortby = empty( $instance['sortby'] ) ? 'menu_order' : $instance['sortby'];
		$exclude = empty( $instance['exclude'] ) ? '' : $instance['exclude'];
		
		$before_widget = ((array_key_exists('contentnav', $instance)) && ($instance['contentnav'] == '1')) ? BU_WIDGET_CONTENTNAV_BEFORE : '';
		$after_widget = ((array_key_exists('contentnav', $instance)) && ($instance['contentnav'] == '1')) ? BU_WIDGET_CONTENTNAV_AFTER : '';
		

		if ( $sortby == 'menu_order' )
			$sortby = 'menu_order, post_title';

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
		
		error_log(print_r($instance, TRUE));
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
	?>
		<p>
			<input type="radio" name="<?php echo $this->get_field_name('navigation_title'); ?>" id="<?php echo $this->get_field_id('navigation_title_none'); ?>" value="none" <?php if ($navigation_title == 'none') echo 'checked="checked"'; ?>
			<label for="<?php echo $this->get_field_id('navigation_title_none'); ?>">Do not display a title</label>
		</p>
		<p>
			<input type="radio" name="<?php echo $this->get_field_name('navigation_title'); ?>" id="<?php echo $this->get_field_id('navigation_title_section'); ?>" value="section" <?php if ($navigation_title == 'section') echo 'checked="checked"'; ?>
			<label for="<?php echo $this->get_field_id('navigation_title_section'); ?>">Use the section's name for title</label>
		</p>
		<p>
			<input type="radio" name="<?php echo $this->get_field_name('navigation_title'); ?>" id="<?php echo $this->get_field_id('navigation_title_static'); ?>" value="static" <?php if ($navigation_title == 'static') echo 'checked="checked"'; ?>
			<label for="<?php echo $this->get_field_id('navigation_title_static'); ?>">Use this text for title:</label>
			<input class="widefat" id="<?php echo $this->get_field_id('navigation_title_text'); ?>" name="<?php echo $this->get_field_name('navigation_title_text'); ?>" type="text" value="<?php echo $navigation_title_text; ?>" />				
			<label for="<?php echo $this->get_field_id('navigation_title_url'); ?>">URL:</label>
			<input class="widefat" id="<?php echo $this->get_field_id('navigation_title_url'); ?>" name="<?php echo $this->get_field_name('navigation_title_url'); ?>" type="text" value="<?php echo $navigation_title_url; ?>" />
		</p>

		<p>
			<label for="<?php echo $this->get_field_id('contentnav'); ?>"><?php _e( 'Content Navigation:' ); ?></label> 
			<input type="checkbox" value="1" name="<?php echo $this->get_field_name('contentnav'); ?>" id="<?php echo $this->get_field_id('contentnav'); ?>" <?php if ($contentnav == 1) echo 'checked="checked"'; ?> />
			<br />
			<small><?php _e( 'Only one navigation widget may act as content navigation.' ); ?></small>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('navigate_in_section'); ?>"><?php _e( 'Section Navigation:' ); ?></label> 
			<input type="checkbox" value="1" name="<?php echo $this->get_field_name('navigate_in_section'); ?>" id="<?php echo $this->get_field_id('navigate_in_section'); ?>" <?php if ($navigate_in_section == 1) echo 'checked="checked"'; ?> />
			<br />
			<small><?php _e( 'Only display links for the main family of pages being browsed.' ); ?></small>
		</p>
		<script type="text/javascript">
		//<![CDATA[
		jQuery(document).ready( function($) 
		{
			console.log('ready for <?php echo $this->get_field_id('navigation_title_static'); ?>');
		});
		//]]>
		</script>
	<?php
	}
}
?>