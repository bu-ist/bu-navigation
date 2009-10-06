<p>
	<input type="radio" name="<?php echo $this->get_field_name('navigation_title'); ?>" id="<?php echo $this->get_field_id('navigation_title_none'); ?>" value="none" <?php if ($navigation_title == 'none') echo 'checked="checked"'; ?> />
	<label for="<?php echo $this->get_field_id('navigation_title_none'); ?>">Do not display a title</label>
	<br />
	<input type="radio" name="<?php echo $this->get_field_name('navigation_title'); ?>" id="<?php echo $this->get_field_id('navigation_title_section'); ?>" value="section" <?php if ($navigation_title == 'section') echo 'checked="checked"'; ?> />
	<label for="<?php echo $this->get_field_id('navigation_title_section'); ?>"><?php _e('Use the site name as the title'); ?></label>
	<br />
	<input type="radio" name="<?php echo $this->get_field_name('navigation_title'); ?>" id="<?php echo $this->get_field_id('navigation_title_static'); ?>" value="static" <?php if ($navigation_title == 'static') echo 'checked="checked"'; ?> />
	<label for="<?php echo $this->get_field_id('navigation_title_static'); ?>">Use this text for title:</label>
	<input class="widefat" id="<?php echo $this->get_field_id('navigation_title_text'); ?>" name="<?php echo $this->get_field_name('navigation_title_text'); ?>" type="text" value="<?php echo $navigation_title_text; ?>" />				
	<br />
	<label for="<?php echo $this->get_field_id('navigation_title_url'); ?>">URL:</label>
	<input class="widefat" id="<?php echo $this->get_field_id('navigation_title_url'); ?>" name="<?php echo $this->get_field_name('navigation_title_url'); ?>" type="text" value="<?php echo $navigation_title_url; ?>" />
	<br />
	<span id="<?php echo $this->get_field_id('navigation_url_error'); ?>" style="color: #cc0000; font-weight: bold;"></span>
</p>
<!--
<p>
	<label for="<?php echo $this->get_field_id('contentnav'); ?>"><?php _e( 'Content Navigation:' ); ?></label> 
	<input type="checkbox" value="1" name="<?php echo $this->get_field_name('contentnav'); ?>" id="<?php echo $this->get_field_id('contentnav'); ?>" <?php if ($contentnav == 1) echo 'checked="checked"'; ?> />
	<br />
	<small><?php _e( 'Only one navigation widget may act as content navigation.' ); ?></small>
</p>
-->
<p>
	<label for="<?php echo $this->get_field_id('navigate_in_section'); ?>"><?php _e( 'Section Navigation:' ); ?></label> 
	<input type="checkbox" value="1" name="<?php echo $this->get_field_name('navigate_in_section'); ?>" id="<?php echo $this->get_field_id('navigate_in_section'); ?>" <?php if ($navigate_in_section == 1) echo 'checked="checked"'; ?> />
	<br />
	<small><?php _e( 'Only display links for the main family of pages being browsed.' ); ?></small>
</p>
<script type="text/javascript">
//<![CDATA[

function bu_navigation_widget_<?php echo $this->number; ?>_title_label()
{
	var title = "<?php _e('Use the site name as the title'); ?>";

	if (jQuery("#<?php echo $this->get_field_id('navigate_in_section'); ?>:checked").val())
	{
		title = "<?php _e('Use the section name as the title'); ?>";
	}

	jQuery("label[for='<?php echo $this->get_field_id('navigation_title_section'); ?>']").html(title);
}

function bu_navigation_widget_<?php echo $this->number; ?>_validate(e)
{
	var re = /(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/;

	var url = jQuery("#<?php echo $this->get_field_id('navigation_title_url'); ?>").attr("value");

	if ((url.length == 0) || (re.test(url)))
	{
		jQuery("#<?php echo $this->get_field_id('navigation_url_error'); ?>").html("");
	}
	else
	{
		jQuery("#<?php echo $this->get_field_id('navigation_url_error'); ?>").html("That URL is invalid.");
	}
}

jQuery(document).ready( function($) 
{
	jQuery("#<?php echo $this->get_field_id('navigation_title_static'); ?>").change(function () {
		if (jQuery("#<?php echo $this->get_field_id('navigation_title_static'); ?>:checked").val())
		jQuery("#<?php echo $this->get_field_id('navigation_title_text'); ?>").focus();
	});

	jQuery("#<?php echo $this->get_field_id('navigate_in_section'); ?>").change(function (e) {
		bu_navigation_widget_<?php echo $this->number; ?>_title_label();
	});
	bu_navigation_widget_<?php echo $this->number; ?>_title_label();
	bu_navigation_widget_<?php echo $this->number; ?>_validate(null);
	
	var validationHandler = function (e) {
		bu_navigation_widget_<?php echo $this->number; ?>_validate(e);
	};

	jQuery("#<?php echo $this->get_field_id('navigation_title_url'); ?>").change(function () {
		if (jQuery("#<?php echo $this->get_field_id('navigation_title_url'); ?>").val())
		{
			jQuery("#<?php echo $this->get_field_id('navigation_title_static'); ?>").attr("checked", "checked");
		}
		
		validationHandler(null);
	});
	
	jQuery("#<?php echo $this->get_field_id('navigation_title_text'); ?>").change(function () {
		if (jQuery("#<?php echo $this->get_field_id('navigation_title_text'); ?>").val())
		{
			jQuery("#<?php echo $this->get_field_id('navigation_title_static'); ?>").attr("checked", "checked");
		}
	});

	var form = jQuery("#<?php echo $this->get_field_id('navigation_title_url'); ?>").parents("form:first")[0];

	jQuery(form).find("input.widget-control-save").click(validationHandler);
});
//]]>
</script>
