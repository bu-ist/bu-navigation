<?php
/**
 * BU Navigation Block
 *
 * @package BU_Navigation
 */

namespace BU\Plugins\Navigation;

/**
 * Dynamic render callback for the navigation block
 */
function navigation_block_render_callback() {
	global $post;

	// Fake widget settings.
	$instance                     = array();
	$instance['navigation_style'] = 'adaptive';

	$list_args = array(
		'page_id'      => $post->ID,
		'title_li'     => '',
		'echo'         => 0,
		'container_id' => 'lorem-ipsum',
		'post_types'   => $post->post_type,
		'style'        => 'section',
		'widget'       => true,
	);

	$list = list_pages( $list_args );

	return $list;
}

/**
 * Registers the block
 */
function navigation_block_init() {

	// Load dependencies.
	$asset_file = include plugin_dir_path( __FILE__ ) . '/../build/index.asset.php';

	wp_register_script(
		'navigation-block',
		plugins_url( '/../build/index.js', __FILE__ ),
		$asset_file['dependencies'],
		$asset_file['version']
	);

	register_block_type(
		'bu-navigation/navigation-block', array(
			'api_version'     => 2,
			'editor_script'   => 'navigation-block',
			'render_callback' => __NAMESPACE__ . '\navigation_block_render_callback',
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\navigation_block_init' );
