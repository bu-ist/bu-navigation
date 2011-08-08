<div class="wrap">
	<h2><?php _e('Primary Navigation'); ?></h2>
	
	<?php if ($saved === TRUE) { ?>
		<div id="message" class="updated fade">
			<p>Primary navigation settings saved.</p>
		</div>
	<?php } else if ($saved === FALSE) { ?>
		<div class="error">
			<p><strong>Error:</strong> Error(s) occurred while saving your primary navigation settings.</p>
		</div>
	<? } ?>
	<p>
		Your primary navigation bar is the horizontal bar at the top of every 
		page which shows users your top-level navigation no matter where they
		are.
	</p>

	<form id="bu_navigation_primary_navigation" action="" method="post">
		<input type="hidden" name="bu_navigation_primary_save" value="save" />
		<p>
			<input type="checkbox" id="bu_navigation_primarynav" name="bu_navigation_primarynav" value="1" <?php if ($bu_navigation_primarynav) echo 'checked="checked"'; ?> /> 
			<strong><label for="bu_navigation_primarynav">Display primary navigation bar</label></strong>
			<br />
			Toggles the primary navigation bar on and off.
		</p>

		<p>
			<input type="text" id="bu_navigation_primarynav_max" name="bu_navigation_primarynav_max" value="<?php echo $bu_navigation_primarynav_max; ?>" size="2" maxlength="2" />
			<strong><label for="bu_navigation_primarynav_max">Maximum items</label></strong>
			<br />
			Maximum number of top-level items to display in the primary 
			navigation bar.
		</p>
		<?php if ( (defined('BU_NAVIGATION_SUPPORTED_DEPTH') && BU_NAVIGATION_SUPPORTED_DEPTH != 0) || !defined('BU_NAVIGATION_SUPPORTED_DEPTH') ) { ?>
			<?php $bu_pn_depth_limit = ( defined('BU_NAVIGATION_SUPPORTED_DEPTH') ) ? BU_NAVIGATION_SUPPORTED_DEPTH : BU_NAVIGATION_PRIMARY_DEPTH; ?>
			<p>
				<input type="checkbox" id="bu_navigation_primarynav_dive" name="bu_navigation_primarynav_dive" value="1" <?php if ($bu_navigation_primarynav_dive) echo 'checked="checked"'; ?> /> 
				<strong><label for="bu_navigation_primarynav_dive">Use drop-down menus</label></strong>
				<br />
				If checked, any top-level pages with children will expand to display 
				those children when somebody moves their mouse over the link.
	
				<?php if (defined('BU_NAVIGATION_SUPPORTED_DEPTH')) { ?>
					<br />
					The theme your site is currently using supports displaying <strong><?php echo BU_NAVIGATION_SUPPORTED_DEPTH; ?></strong> level(s) of children in the primary navigation bar.
				<?php } ?>
			</p>
			<?php if ($bu_pn_depth_limit > 1) { ?>
				<p class="bu_navigation_sub-option">
					<strong><label for="bu_navigation_primarynav_depth">Display</label></strong>
					<select id="bu_navigation_primarynav_depth" name="bu_navigation_primarynav_depth">
						<?php for ( $i = 1; $i <= $bu_pn_depth_limit; $i++ ) { ?>
							<?php
							
							/* choose which option is selected using the following criteria:
							 * - the previously-saved depth is in the list, select it...
							 * - OR, if the previously-saved depth is above the number on the list (i.e. switching from a big depth theme to low depth theme),
							 * 		then we should use the last available depth as the selected one
							 */
							
							$bu_pn_depth_selected = ( $bu_navigation_primarynav_depth == $i ) ||
								( $bu_navigation_primarynav_depth > $i && $i == $bu_pn_depth_limit );
							?>
							<option value="<?php echo $i; ?>" <?php echo ( $bu_pn_depth_selected ) ? 'selected="selected"' : '' ?>>
								<?php echo $i; ?>
							</option>
						<?php } ?>
					</select>
					<strong><label for="bu_navigation_primarynav_depth">level(s) of children</label></strong>
				</p>
			<?php } ?>
		<?php } ?>
		
		<p>
			<input type="checkbox" name="bu_allow_top_level_page" id="bu_allow_top_level_page" value="1" <?php echo $bu_allow_top_level_page ? 'checked="checked"' : '';?> />
			<strong><label for="bu_allow_top_level_page">Allow Top-Level Pages</label></strong><br/>
			If checked, users will be allowed to display top level pages to the navigation.
		</p>
		<p class="submit">
			<input type="submit" name="Submit" value="Save Changes" />
		</p>
	</form>
</div>
