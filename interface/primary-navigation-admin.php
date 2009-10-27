<div class="wrap">
	<h2><?php _e('Primary Navigation'); ?></h2>

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

		<p>
			<input type="checkbox" id="bu_navigation_primarynav_dive" name="bu_navigation_primarynav_dive" value="1" <?php if ($bu_navigation_primarynav_dive) echo 'checked="checked"'; ?> /> 
			<strong><label for="bu_navigation_primarynav_dive">Use drop-down menus</label></strong>
			<br />
			If checked, any top-level pages with children will expand to display 
			those children when somebody moves their mouse over the link.	
		</p>
		<p style="margin-left: 2em;">
			<strong><label for="bu_navigation_primarynav_depth">Display</label></strong>
			<input type="textbox" id="bu_navigation_primarynav_depth" name="bu_navigation_primarynav_depth" value="<?php echo $bu_navigation_primarynav_depth; ?>" size="2" maxlength="2" />
			<strong><label for="bu_navigation_primarynav_depth">level(s) of children</label></strong>
			<br />
			Note that not all themes are able to display more than one level of children.
			<?php if (defined('BU_NAVIGATION_SUPPORTED_DEPTH')) { ?>
				<br />
				The theme your site is currently using supports displaying <strong><?php echo BU_NAVIGATION_SUPPORTED_DEPTH; ?></strong> level(s) of children in the primary navigation bar.
			<?php } ?>
		</p>
		<p>
			<input type="checkbox" name="bu_allow_top_level_page" id="bu_allow_top_level_page" value="1" <?php echo $bu_allow_top_level_page ? 'checked="checked"' : '';?> />
			<strong><label for="bu_allow_top_level_page">Allow Top-Level Pages</label></strong><br/>
			If checked, users will be allowed to create new pages at the top level of the site hierarchy.
		</p>
		<p class="submit">
			<input type="submit" name="Submit" value="Save Changes" />
		</p>
	</form>
</div>