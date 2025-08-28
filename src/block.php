<?php
/**
 * BU Navigation Block
 *
 * @package BU_Navigation
 */

namespace BU\Plugins\Navigation;

/**
 * Query to pull just those posts that have children.
 *
 * @return array Array of post ids.
 */
function get_only_parents() {
	global $bu_navigation_plugin;

	$sections = load_sections(
		$bu_navigation_plugin->supported_post_types(),
		true
	);

	$parent_ids = array_keys( $sections['sections'] );

	// Use get_nav_posts to load titles.
	$parents = array_map( function( $parent_id ) {
		return array(
			'postid' => $parent_id,
			'title'  => html_entity_decode( \get_the_title( $parent_id ), ENT_QUOTES, 'UTF-8' ),
			'type'   => \get_post_type( $parent_id ),
		);
	}, $parent_ids );

	return $parents;
}

/**
 * Returns block markup for editing preview
 *
 * Takes block attributes as parameters and returns the current markup output.
 * Used for block preview.
 *
 * @param WP_REST_Request $data Parameters from the rest request.
 * @return string Rendered block markup.
 */
function block_markup( $data ) {

	if ( ! $data['id'] || ! $data['navMode'] ) {
		// Bail early if attributes are missing.
		return rest_ensure_response( 'No valid navigation items' );
	}

	$attributes = [
		'rootPostID' => $data['id'],
		'navMode'    => $data['navMode'],
	];
	return rest_ensure_response( navigation_block_render_callback( $attributes ) );

}

/**
 * Add REST endpoints for parents query and block preview.
 */
add_action(
	'rest_api_init', function() {
		// Endpoint for parent posts.
		register_rest_route(
			'bu-navigation/v1', '/parents/', array(
				'methods'             => 'GET',
				'callback'            => __NAMESPACE__ . '\get_only_parents',
				'permission_callback' => function () {
					return current_user_can( 'edit_others_posts' );
				},
			)
		);

		// Endpoint for block preview.
		register_rest_route(
			'bu-navigation/v1', '/markup', [
				'methods'             => 'GET',
				'callback'            => __NAMESPACE__ . '\block_markup',
				'permission_callback' => function () {
					return current_user_can( 'edit_others_posts' );
				},
			]
		);
	}
);

/**
 * Dynamic render callback for the navigation block
 *
 * @param array $attributes The block's attributes.
 */
function navigation_block_render_callback( $attributes ) {
	global $post, $bu_navigation_plugin;

	// For some reason when saving the default value, the attribute is empty, so set it to the default if so.
	$nav_mode     = empty( $attributes['navMode'] ) ? 'section' : $attributes['navMode'];
	$root_post_id = empty( $attributes['rootPostID'] ) ? 0 : $attributes['rootPostID'];

	$list_args = array(
		'page_id'      => 0 === $root_post_id ? $post->ID : $root_post_id,
		'title_li'     => '',
		'echo'         => 0,
		'container_id' => '',
		'post_types'   => $bu_navigation_plugin->supported_post_types(),
		'style'        => $nav_mode,
		'widget'       => true,
	);

	$list = list_pages( $list_args );

	return sprintf( '<div class="widget widget_bu_pages bu-nav-block">%s</div>', $list );
}

/**
 * Registers the block
 */
function navigation_block_init() {

	// Load dependencies.
	$asset_file = include plugin_dir_path( __FILE__ ) . '/../build/block.asset.php';

	wp_register_script(
		'bu-navigation-block',
		plugins_url( '/../build/block.js', __FILE__ ),
		$asset_file['dependencies'],
		$asset_file['version'],
		true
	);

	// Shared Frontend/Editor Styles.
	wp_register_style(
		'bu-navigation-block-editor-style',
		plugins_url( '/../build/block.css', __FILE__ ),
		array(),
		$asset_file['version']
	);

	register_block_type(
		'bu-navigation/navigation-block', array(
			'editor_script'   => 'bu-navigation-block',
			'editor_style'    => 'bu-navigation-block-editor-style',
			'render_callback' => __NAMESPACE__ . '\navigation_block_render_callback',
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\navigation_block_init' );

// Enqueue a small chunk of css for responsive theme compatibility.
// Should ultimately go through a compile stage, but it's just a very small chunk of css right now.
add_action( 'enqueue_block_assets', function() {
	wp_enqueue_style(
		'bu-navigation-block-frontend-style',
		plugins_url( 'block-frontend.css', __FILE__ ),
		array(),
		\BU_Navigation_Plugin::VERSION
	);
} );
