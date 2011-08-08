<?php
	if ( 0 != count( get_page_templates() ) ) { ?>
<h5><?php _e('Template') ?></h5>
<label class="screen-reader-text" for="page_template"><?php _e('Page Template') ?></label><select name="page_template" id="page_template">
<option value='default'><?php _e('Default Template'); ?></option>
<?php page_template_dropdown($post->page_template); ?>
</select>
<p><?php _e('Some themes have custom templates you can use for certain pages that might have additional features or custom layouts.'); ?></p>
<?php
	} ?>
