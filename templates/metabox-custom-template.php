<h5><?php _e( 'Template', BU_NAV_TEXTDOMAIN ) ?></h5>
<label class="screen-reader-text" for="page_template"><?php printf( __( '%s Template', BU_NAV_TEXTDOMAIN ), $post_type->labels->singular_name ); ?></label>
<select name="page_template" id="page_template">
	<option value='default'><?php _e( 'Default Template', BU_NAV_TEXTDOMAIN ); ?></option>
	<?php page_template_dropdown( $current_template ); ?>
</select>
<p><?php printf( __( 'Some themes have custom templates you can use for certain %s that might have additional features or custom layouts.', BU_NAV_TEXTDOMAIN ), strtolower( $post_type->labels->name ) ); ?></p>