<!-- @todo rewrite helper text -->
<h4><?php _e('Location'); ?></h4>

<!-- @todo investigate if these fields are present outside of metabox pre-3.5 -->
<input type="hidden" name="menu_order" value="<?php echo $current_menu_order; ?>">
<input type="hidden" name="parent_id" value="<?php echo $current_parent; ?>">

<div id="bu_nav_attributes_location_breadcrumbs">
	<?php // bu_navigation_breadcrumbs( array( 'echo' => true, 'crumb_current' => false ) ); ?>
	<?php echo $current_parent_label; ?>
</div>

<p>
	<a id="select-parent" href="#TB_inline?width=640&inlineId=edit_page_location" class="thickbox button" ><?php echo $select_parent_txt; ?></a>
</p>

<div id="edit_page_location" style="display:none;">
	<input type="hidden" name="tmp_parent_id" value="">
	<input type="hidden" name="tmp_parent_label" value="">
	<input type="hidden" name="tmp_menu_order" value="">

	<h2>Set <?php _e($lc_label); ?> location</h2>
	<p>Drag the current <?php _e($lc_label); ?> to the desired location.</p>

	<div id="edit_page_tree" class="bu_nav_tree"></div>
	
	<div class="page_position_edit_buttons">
		<input id="bu_page_parent_save" class="button-primary" type="submit" value="Update Location">
		<a href="#" id="bu_page_parent_cancel" class="button">Cancel</a>
	</div>

</div>

<h4><?php _e('Label & Visibility'); ?></h4>

<p>This label will be used instead of the <?php echo strtolower($pt_labels['singular']); ?> title in all navigation lists.</p>

<div id="bu-page-navigation" class="page-attributes-section">
	<p>
		<input id="bu-page-navigation-label" name="nav_label" type="text" size="30" value="<?php echo $nav_label; ?>"/>
	</p>
	<p>
		<input id="bu-page-navigation-display" name="nav_display" type="checkbox" value="yes" <?php if ( $already_in_nav ) { ?>checked="checked"<?php }?>/>
		<label class="inline" for="bu-page-navigation-display">Display this <?php echo strtolower($pt_labels['singular']); ?> in navigation lists.</label>
	</p>
</div>