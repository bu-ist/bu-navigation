<?php
// For WP < 3.2, there is a hidden input name="parent_id" that exists outside of the default "Page Attributes" metabox.
// In more recent versions, this has been removed in favor of the "Page Attributes" select box which has a name of "parent_id".
// We are looking at the current version here to decide whether or not it is needed.
global $wp_version;
if( version_compare( $wp_version, '3.2', '>=' ) ): ?>
<input type="hidden" id="parent_id" name="parent_id" value="<?php echo $post->post_parent; ?>" />
<?php endif; ?>
<input type="hidden" name="menu_order" value="<?php echo $post->menu_order; ?>" />
<div id="bu-move-post" class="container">
	<?php echo $breadcrumbs; ?>
	<a id="move-post-button" href="#TB_inline?width=640&inlineId=edit-post-placement" title="<?php printf( __( '%s Location', 'bu-navigation' ), $post_type->labels->singular_name  ); ?>" class="thickbox button" >
		<?php printf( __( 'Move %s', 'bu-navigation' ), strtolower( $post_type->labels->singular_name ) ); ?>
	</a>
</div>
<div id="bu-navigation-label" class="container">
		<label for="bu-post-nav-label" class="label"><?php _e( 'Label', 'bu-navigation' ); ?></label>
		<input id="bu-post-nav-label" name="nav_label" type="text" value="<?php echo esc_textarea( $nav_label ); ?>" />
</div>
<div id="bu-navigation-visibility" class="container">
	<span class="label"><?php _e( 'Visibility', 'bu-navigation' ); ?></span>
	<input id="bu-post-nav-display" name="nav_display" type="checkbox" value="yes" <?php checked( $nav_display, true ); ?> />
	<label for="bu-post-nav-display" class="hint"><?php _e( 'Display in navigation lists.', 'bu-navigation' ); ?></label>
</div>
<div id="edit-post-placement" style="display: none">
	<div class="post-placement-toolbar">
		<div class="edit-buttons">
			<a href="#" id="bu-post-placement-cancel" class="button"><?php _e( 'Cancel', 'bu-navigation' ); ?></a>
			<input id="bu-post-placement-save" class="button-primary" type="submit" value="<?php _e( 'Update Location', 'bu-navigation' ); ?>" />
		</div>
		<p class="hint"><?php printf( __( 'Drag %s to change the %s location in the hierarchy.', 'bu-navigation' ), strtolower( $post_type->labels->singular_name ), strtolower( $post_type->labels->name )  ); ?></p>
		<?php if( $user_cannot_publish ): ?>
		<p class="hint">
			<?php _e( 'Note: The page that you are moving is not published. You can move it to a new location, but you do not have permission to publish in sections marked as denied ', 'bu-navigation' ); ?>
			<img id="modal-denied-icon"src="<?php echo $images_url; ?>/img-section-denied.png" alt="denied"/>
		</p>
		<?php endif; ?>
	</div>
	<div id="edit-post-tree" class="jstree-bu"></div>
</div>
