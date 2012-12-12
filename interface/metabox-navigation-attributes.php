<?php
// For WP < 3.2, there is a hidden input name="parent_id" that exists outside of the default "Page Attributes" metabox.
// In more recent versions, this has been removed in favor of the "Page Attributes" select box which has a name of "parent_id".
// We are looking at the current version here to decide whether or not it is needed.
global $wp_version;
if( version_compare( $wp_version, '3.2', '>=' ) ): ?>
<input type="hidden" name="parent_id" value="<?php echo $post->post_parent; ?>" />
<?php endif; ?>
<input type="hidden" name="menu_order" value="<?php echo $post->menu_order; ?>" />

<div id="bu-move-post" class="container">
	<?php echo $breadcrumbs; ?>
	<a id="move-post-button" href="#TB_inline?width=640&inlineId=edit-post-placement" title="<?php echo esc_attr($dialog_title); ?>" class="button" >
		<?php echo $move_post_btn_txt; ?>
	</a>
</div>

<div id="bu-navigation-label" class="container">
		<label for="bu-post-nav-label" class="label"><?php _e('Label'); ?></label>
		<input id="bu-post-nav-label" name="nav_label" type="text" value="<?php echo $nav_label; ?>" />
</div>

<div id="bu-navigation-visibility" class="container">
	<span class="label"><?php _e('Visiblity'); ?></span>
	<input id="bu-post-nav-display" name="nav_display" type="checkbox" value="yes" <?php checked( $nav_display, true ); ?> />
	<label for="bu-post-nav-display" class="hint">Display in navigation lists.</label>
</div>

<div id="edit-post-placement" style="display:none;">
	<div class="post-placement-toolbar">
		<div class="edit-buttons">
			<a href="#" id="bu-post-placement-cancel" class="button">Cancel</a>
			<input id="bu-post-placement-save" class="button-primary" type="submit" value="Update Location" />
		</div>
		<p class="hint">Drag <?php echo $lc_label; ?> to change the <?php echo $lc_label; ?>'s location in the hierarchy.</p>
		<?php if( $post->post_status != 'publish' && current_user_can('edit_in_section') && ! is_super_admin() ): ?>
		<p class="hint"><strong>Note:</strong> The page you are moving is not published. You can move it to a new location, but you do not have permission to publish in sections marked as denied <img id="modal-denied-icon"src="<?php echo $images_url; ?>/img-section-denied.png" alt="denied"/></p>
		<?php endif; ?>
	</div>
	<div id="edit-post-tree" class="jstree-bu"></div>
</div>