<!-- @todo rewrite helper text -->
<h4><?php _e('Current Location'); ?></h4>

<?php 
// For WP < 3.2, there is a hidden input name="parent_id" that exists outside of the default "Page Attributes" metabox.
// In more recent versions, this has been removed in favor of the "Page Attributes" select box which has a name of "parent_id".
// We are looking at the current version here to decide whether or not it is needed.
global $wp_version;
if( version_compare( $wp_version, '3.2', '>=' ) ): ?>
<input type="hidden" name="parent_id" value="<?php echo $post->post_parent; ?>">
<?php endif; ?>
<input type="hidden" name="menu_order" value="<?php echo $post->menu_order; ?>">

<div id="bu_nav_attributes_location_breadcrumbs">
	<p><?php echo $breadcrumbs; ?></p>
</div>

<p>
<a id="select-parent" href="#TB_inline?width=640&inlineId=edit_page_location" title="<?php echo esc_attr($dialog_title); ?>" class="button" >
		<?php echo $move_post_btn_txt; ?>
	</a>
</p>

<div id="edit_page_location" style="display:none;">
	<div class="page_location_toolbar">
		<div class="edit_buttons">
			<a href="#" id="bu_page_parent_cancel" class="button">Cancel</a>
			<input id="bu_page_parent_save" class="button-primary" type="submit" value="Update Location">
		</div>
		<p class="hint">Drag <?php echo $lc_label; ?> to change the <?php echo $lc_label; ?>'s location in the hierarchy.</p>
	</div>
	<div id="edit_page_tree" class="jstree-bu"></div>
</div>

<h4><?php _e('Label & Visibility'); ?></h4>

<p>This label will be used instead of the <?php echo strtolower($pt_labels['singular']); ?> title in all navigation lists.</p>

<div id="bu-page-navigation" class="page-attributes-section">
	<p>
		<input id="bu-page-navigation-label" name="nav_label" type="text" size="30" value="<?php echo $nav_label; ?>"/>
	</p>
	<p>
		<input id="bu-page-navigation-display" name="nav_display" type="checkbox" value="yes" <?php checked( $nav_display, true ); ?>/>
		<label class="inline" for="bu-page-navigation-display">Display this <?php echo strtolower($pt_labels['singular']); ?> in navigation lists.</label>
	</p>
</div>
