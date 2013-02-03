<div class="wrap">
	<h2><?php _e('Primary Navigation'); ?></h2>

	<?php if ($saved === TRUE) { ?>
		<div id="message" class="updated fade">
			<p>Primary navigation settings saved.</p>
		</div>
	<?php } else if ($saved === FALSE || (is_array($saved) && $saved['success'] == false)) { ?>
		<div class="error">
			<p><strong>Error:</strong> Error(s) occurred while saving your primary navigation settings.</p>
			<?php if (is_array($saved) && $saved['success'] == false) { ?>
				<p><?php echo $saved['msg']; ?></p>
			<?php } ?>
		</div>
	<?php } ?>
	<p>
		Your primary navigation bar is the horizontal bar at the top of every
		page which shows users your top-level navigation no matter where they
		are.
	</p>

	<form id="bu_navigation_primary_navigation" action="" method="post">
		<input type="hidden" name="bu_navigation_primary_save" value="save" />
		<p>
			<input type="checkbox" id="bu_navigation_primarynav" name="bu_navigation_primarynav" value="1" <?php checked( $bu_navigation_primarynav ); ?> />
			<strong><label for="bu_navigation_primarynav">Display primary navigation bar</label></strong>
			<br />
			Toggles the primary navigation bar on and off.
		</p>

		<p>
			<input type="text" id="bu_navigation_primarynav_max" name="bu_navigation_primarynav_max" value="<?php echo $bu_navigation_primarynav_max; ?>" size="2" maxlength="2" />
			<strong><label for="bu_navigation_primarynav_max">Maximum items</label></strong>
			<br />
			Maximum number of top-level items to display in the primary
			navigation bar. (Must be a positive number)
		</p>
		<?php if ( $supported_depth ): ?>
			<p>
				<input type="checkbox" id="bu_navigation_primarynav_dive" name="bu_navigation_primarynav_dive" value="1" <?php checked($bu_navigation_primarynav_dive); ?> />
				<strong><label for="bu_navigation_primarynav_dive">Use drop-down menus</label></strong>
				<br />
				If checked, any top-level pages with children will expand to display
				those children when somebody moves their mouse over the link.

				<?php if ( defined('BU_NAVIGATION_SUPPORTED_DEPTH') || current_theme_supports( 'bu-navigation-primary' ) ): ?>
					<br />
					The theme your site is currently using supports displaying <strong><?php echo $supported_depth; ?></strong> level(s) of children in the primary navigation bar.
				<?php endif; ?>
			</p>
			<?php if ( $supported_depth > 1 ): ?>
				<p class="bu_navigation_sub-option">
					<strong><label for="bu_navigation_primarynav_depth">Display</label></strong>
					<select id="bu_navigation_primarynav_depth" name="bu_navigation_primarynav_depth">
						<?php for ( $i = 1; $i <= $supported_depth; $i++ ) { ?>
							<?php

							/* choose which option is selected using the following criteria:
							 * - the previously-saved depth is in the list, select it...
							 * - OR, if the previously-saved depth is above the number on the list (i.e. switching from a big depth theme to low depth theme),
							 * 		then we should use the last available depth as the selected one
							 */

							$bu_pn_depth_selected = ( $bu_navigation_primarynav_depth == $i ) ||
								( $bu_navigation_primarynav_depth > $i && $i == $supported_depth );
							?>
							<option value="<?php echo $i; ?>" <?php echo ( $bu_pn_depth_selected ) ? 'selected="selected"' : '' ?>>
								<?php echo $i; ?>
							</option>
						<?php } ?>
					</select>
					<strong><label for="bu_navigation_primarynav_depth">level(s) of children</label></strong>
				</p>
			<?php endif; ?>
		<?php endif; ?>

		<p>
			<input type="checkbox" name="bu_allow_top_level_page" id="bu_allow_top_level_page" value="1" <?php checked( $bu_allow_top_level_page ); ?> />
			<strong><label for="bu_allow_top_level_page">Allow Top-Level Pages</label></strong><br/>
			If checked, users will be allowed to add top-level pages to the navigation.
		</p>
		<p class="submit">
			<input type="submit" name="Submit" value="Save Changes" />
		</p>
	</form>
</div>
