<?php
/**
 * BU Navigation Block
 *
 * @package BU_Navigation
 */

namespace BU\Plugins\Navigation;

/**
 * Dynamic render callback for the navigation block
 *
 * @param array $attributes The block's attributes.
 */
function navigation_block_render_callback( $attributes ) {
	global $post;

	// For some reason when saving the default value, the attribute is empty, so set it to the default if so.
	$nav_mode = empty( $attributes ) ? 'section' : $attributes['navMode'];

	$list_args = array(
		'page_id'      => $post->ID,
		'title_li'     => '',
		'echo'         => 0,
		'container_id' => '',
		'post_types'   => $post->post_type,
		'style'        => $nav_mode,
		'widget'       => true,
	);

	$list = list_pages( $list_args );

	return sprintf( '<div class="bu-nav-block">%s</div>', $list );
}

/**
 * Registers the block
 */
function navigation_block_init() {

	// Load dependencies.
	$asset_file = include plugin_dir_path( __FILE__ ) . '/../build/index.asset.php';

	wp_register_script(
		'bu-navigation-block',
		plugins_url( '/../build/index.js', __FILE__ ),
		$asset_file['dependencies'],
		$asset_file['version']
	);

	// Shared Frontend/Editor Styles.
	wp_register_style(
		'bu-navigation-block-editor-style',
		plugins_url( '/../build/index.css', __FILE__ ),
		array(),
		$asset_file['version']
	);

	register_block_type(
		'bu-navigation/navigation-block', array(
			'api_version'     => 2,
			'editor_script'   => 'bu-navigation-block',
			'editor_style'    => 'bu-navigation-block-editor-style',
			'render_callback' => __NAMESPACE__ . '\navigation_block_render_callback',
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\navigation_block_init' );
