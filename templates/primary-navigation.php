<div class="wrap">
	<h2><?php _e('Primary Navigation'); ?></h2>

	<?php if ( is_array( $status ) && $status['success'] ): ?>
	<div id="message" class="updated fade">
		<p>Primary navigation settings saved.</p>
	</div>
	<?php elseif ( is_array( $status ) && false == $status['success'] ): ?>
	<div class="error">
		<p><strong>Error:</strong> The following error(s) occurred while attempting to save your primary navigation settings.</p>
		<ul>
			<li><?php echo implode( '</li><li>', $status['errors'] ); ?></li>
		</ul>
	</div>
	<?php endif; ?>

	<p>
		Your primary navigation bar is the horizontal bar at the top of every
		page which shows users your top-level navigation no matter where they
		are.
	</p>

	<form id="bu-nav-primary-settings" action="" method="post">
		<?php wp_nonce_field( $nonce ); ?>

		<p>
			<input type="checkbox" id="bu-nav-setting-display" name="bu-nav-settings[display]" value="1" <?php checked( $settings['display'] ); ?> />
			<strong><label for="bu-nav-setting-display">Display primary navigation bar</label></strong>
			<br />
			Toggles the primary navigation bar on and off.
		</p>

		<p>
			<input type="text" id="bu-nav-setting-max-items" name="bu-nav-settings[max_items]" value="<?php echo esc_attr( $settings['max_items'] ); ?>" size="2" maxlength="2" />
			<strong><label for="bu-nav-setting-max-items">Maximum items</label></strong>
			<br />
			Maximum number of top-level items to display in the primary
			navigation bar. (Must be a positive number)
		</p>
		<?php if ( $supported_depth ): ?>
		<p>
			<input type="checkbox" id="bu-nav-setting-dive" name="bu-nav-settings[dive]" value="1" <?php checked( $settings['dive'] ); ?> />
			<strong><label for="bu-nav-setting-dive">Use drop-down menus</label></strong>
			<br />
			If checked, any top-level pages with children will expand to display
			those children when somebody moves their mouse over the link.

			<?php if ( defined('BU_NAVIGATION_SUPPORTED_DEPTH') || current_theme_supports( 'bu-navigation-primary' ) ): ?>
			<br />
			The theme your site is currently using supports displaying <strong><?php echo $supported_depth; ?></strong> level(s) of children in the primary navigation bar.
			<?php endif; ?>
		</p>
		<?php if ( $supported_depth > 1 ): ?>
		<p class="sub-option">
			<strong><label for="bu-nav-setting-depth">Display</label></strong>
			<select id="bu-nav-setting-depth" name="bu-nav-settings[depth]">
			<?php for ( $i = 1; $i <= $supported_depth; $i++ ): ?>
				<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $i, $settings['depth'] ); ?>>
					<?php echo $i; ?>
				</option>
			<?php endfor; ?>
			</select>
			<strong><label for="bu-nav-setting-depth">level(s) of children</label></strong>
		</p>
		<?php endif; ?>
		<?php endif; ?>

		<p>
			<input type="checkbox" name="bu-nav-settings[allow_top]" id="bu-nav-setting-allow-top" value="1" <?php checked( $settings['allow_top'] ); ?> />
			<strong><label for="bu-nav-setting-allow-top">Allow Top-Level Pages</label></strong><br/>
			If checked, users will be allowed to add top-level pages to the navigation.
		</p>
		<p class="submit">
			<input type="submit" name="submit" value="Save Changes" />
		</p>
	</form>
</div>
