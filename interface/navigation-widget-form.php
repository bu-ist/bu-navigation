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

<p>
	List style:
	<br />
	<input type="radio" name="<?php echo $this->get_field_name('navigation_style'); ?>" id="<?php echo $this->get_field_id('navigation_style_site'); ?>" value="site" <?php if ($navigation_style == 'site') echo 'checked="checked"'; ?> />
	<label for="<?php echo $this->get_field_id('navigation_style_site'); ?>">Site</label>
	<br />
	<input type="radio" name="<?php echo $this->get_field_name('navigation_style'); ?>" id="<?php echo $this->get_field_id('navigation_style_section'); ?>" value="section" <?php if ($navigation_style == 'section') echo 'checked="checked"'; ?> />
	<label for="<?php echo $this->get_field_id('navigation_style_section'); ?>">Section</label>
	<br />
	<input type="radio" name="<?php echo $this->get_field_name('navigation_style'); ?>" id="<?php echo $this->get_field_id('navigation_style_adaptive'); ?>" value="adaptive" <?php if ($navigation_style == 'adaptive') echo 'checked="checked"'; ?> />
	<label for="<?php echo $this->get_field_id('navigation_style_adaptive'); ?>">Adaptive</label>
	<br />
	<small id="<?php echo $this->get_field_id('bu_navigation_style_description'); ?>"></small>
</p>
<script type="text/javascript">
//<![CDATA[

function bu_navigation_widget_<?php echo $this->number; ?>_style_description()
{
	var description = "";
	
	var style = jQuery("input[name='<?php echo $this->get_field_name('navigation_style'); ?>']:checked").val();
	
	switch (style)
	{
		case 'site':
		description = "The entire site's structure will be used for content navigation. ";
		break;
		
		case 'section':
		description = "Only the active section's structure will be used for content navigation. ";
		break;
		
		case 'adaptive':
		description = "The content navigation widget will adapt to your site's content. This option is recommended for larger sites. ";
		break;
	}
	
	description += "<br />The current page will always be displayed in the content navigation. ";
		
	jQuery("#<?php echo $this->get_field_id('bu_navigation_style_description'); ?>").html(description);
}

function bu_navigation_widget_<?php echo $this->number; ?>_title_label()
{
	var title = "<?php _e('Use the site name as the title'); ?>";

	if (jQuery("input[name='<?php echo $this->get_field_name('navigation_style'); ?>']:checked").val() != 'site')
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

	jQuery("input[name='<?php echo $this->get_field_name('navigation_style'); ?>']").change(function (e) {
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
	
	jQuery("input[name='<?php echo $this->get_field_name('navigation_style'); ?>']").change(function () {
		bu_navigation_widget_<?php echo $this->number; ?>_style_description();
	});
	bu_navigation_widget_<?php echo $this->number; ?>_style_description();

	var form = jQuery("#<?php echo $this->get_field_id('navigation_title_url'); ?>").parents("form:first")[0];

	jQuery(form).find("input.widget-control-save").click(validationHandler);
});
//]]>
</script>
