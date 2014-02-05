<div class="wrap">
	<div id="icon-themes" class="icon32"><br /></div>
	<h2><?php _e( 'Primary Navigation', 'bu-navigation' ); ?></h2>
	<?php if ( is_array( $status ) && $status['success'] ): ?>
	<div id="message" class="updated fade">
		<p><?php _e( 'Primary navigation settings saved.', 'bu-navigation' ); ?></p>
	</div>
	<?php elseif ( is_array( $status ) && false == $status['success'] ): ?>
	<div class="error">
		<p><strong><?php _e( 'Error', 'bu-navigation' ); ?>:</strong> <?php _e( 'The following error(s) occurred while attempting to save your primary navigation settings.', 'bu-navigation' ); ?></p>
		<ul>
			<li><?php echo implode( '</li><li>', $status['errors'] ); ?></li>
		</ul>
	</div>
	<?php endif; ?>
	<p><?php _e( 'Your primary navigation bar is the horizontal bar at the top of every page which shows users your top-level navigation no matter where they are.', 'bu-navigation' ); ?></p>

	<form id="bu-nav-primary-settings" action="" method="post">
		<?php wp_nonce_field( $nonce ); ?>
		<p>
			<input type="checkbox" id="bu-nav-setting-display" name="bu-nav-settings[display]" value="1" <?php checked( $settings['display'] ); ?> />
			<strong><label for="bu-nav-setting-display"><?php _e( 'Display primary navigation bar', 'bu-navigation' ); ?></label></strong>
			<br />
			<?php _e( 'Toggles the primary navigation bar on and off.', 'bu-navigation' ); ?>
		</p>

		<p>
			<input type="text" id="bu-nav-setting-max-items" name="bu-nav-settings[max_items]" value="<?php echo esc_attr( $settings['max_items'] ); ?>" size="2" maxlength="2" />
			<strong><label for="bu-nav-setting-max-items"><?php _e( 'Maximum items', 'bu-navigation' ); ?></label></strong>
			<br />
			<?php _e( 'Maximum number of top-level items to display in the primary navigation bar. (Must be a positive number)', 'bu-navigation' ); ?>
		</p>
		<?php if ( $supported_depth ): ?>
		<p>
			<input type="checkbox" id="bu-nav-setting-dive" name="bu-nav-settings[dive]" value="1" <?php checked( $settings['dive'] ); ?> />
			<strong><label for="bu-nav-setting-dive"><?php _e( 'Use drop-down menus', 'bu-navigation' ); ?></label></strong>
			<br />
			<?php _e( 'If checked, any top-level pages with children will expand to display those children when somebody moves their mouse over the link.', 'bu-navigation' ); ?>
			<?php if ( defined( 'BU_NAVIGATION_SUPPORTED_DEPTH' ) || current_theme_supports( 'bu-navigation-primary' ) ): ?>
			<br />
			<?php $supported_markup = "<strong>$supported_depth</strong>"; ?>
			<?php printf(
				_n( 'The theme your site is currently using supports displaying %s level of children in the primary navigation bar.',
					'The theme your site is currently using supports displaying %s levels of children in the primary navigation bar.',
					$supported_depth, 'bu-navigation' ),
				$supported_markup );
			?>
			<?php endif; ?>
		</p>
		<?php if ( $supported_depth > 1 ): ?>
		<p class="sub-option">
			<strong><label for="bu-nav-setting-depth"><?php _e( 'Display', 'bu-navigation' ); ?></label></strong>
			<select id="bu-nav-setting-depth" name="bu-nav-settings[depth]">
			<?php for ( $i = 1; $i <= $supported_depth; $i++ ): ?>
				<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $i, $settings['depth'] ); ?>>
					<?php echo $i; ?>
				</option>
			<?php endfor; ?>
			</select>
			<strong><label for="bu-nav-setting-depth"><?php _e( 'level(s) of children', 'bu-navigation' ); ?></label></strong>
		</p>
		<?php endif; ?>
		<?php endif; ?>
		<p>
			<input type="checkbox" name="bu-nav-settings[allow_top]" id="bu-nav-setting-allow-top" value="1" <?php checked( $settings['allow_top'] ); ?> />
			<strong><label for="bu-nav-setting-allow-top"><?php _e( 'Allow Top-Level Pages', 'bu-navigation' ); ?></label></strong><br/>
			<?php _e( 'If checked, users will be allowed to add top-level pages to the navigation.', 'bu-navigation' ); ?>
		</p>
		<p class="submit">
			<input type="submit" name="submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes', 'bu-navigation' ); ?>" />
		</p>
	</form>
</div>
