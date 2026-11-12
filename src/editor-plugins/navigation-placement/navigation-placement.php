<?php
/**
 * Navigation Placement Block Editor Plugin Registration
 *
 * Registers the post meta, REST route and scripts used by the Navigation Placement
 * Block Editor Plugin (`index.js` in this folder). The plugin is rendered in a Slot Fill
 * in the Document Settings Sidebar and replaces the "Placement in Navigation" metabox
 * on Block Editor screens. The Classic Editor keeps using the metabox in `admin/post.php`.
 *
 * Features:
 * - Registers the navigation label and navigation visibility post meta for the REST API.
 * - Adds a REST route that returns one level of the page tree for the Move dialog.
 * - Reorders sibling pages after a page is moved with a REST API save.
 * - Shows new pages in navigation lists unless the editor hides them.
 * - Enqueues the editor plugin script with the settings it needs.
 *
 * @package BU_Navigation
 * @since 1.4.0
 */

namespace BU\Plugins\Navigation\Navigation_Placement;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Initialize the Block Editor plugin integration.
 *
 * @return void
 */
function init() {
	// Priority 20 so that post types registered at the default priority are supported.
	add_action( 'init', __NAMESPACE__ . '\\register_meta_fields', 20 );
	add_action( 'rest_api_init', __NAMESPACE__ . '\\register_rest_routes' );
	add_action( 'post_updated', __NAMESPACE__ . '\\update_navigation_after_rest_save', 10, 3 );
	add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_block_editor_scripts' );
}

/**
 * Whether the navigation management interfaces are turned on.
 *
 * The editor plugin follows the same "manager" feature switch as the Classic Editor metabox.
 *
 * @return bool
 */
function is_enabled() {
	global $bu_navigation_plugin;

	return isset( $bu_navigation_plugin ) && $bu_navigation_plugin->supports( 'manager' );
}

/**
 * Post types that get the editor plugin: the supported navigation post types that use the Block Editor.
 *
 * @return array Post type names.
 */
function get_supported_post_types() {
	$post_types = array();

	foreach ( bu_navigation_supported_post_types() as $post_type ) {
		$post_type_object = get_post_type_object( $post_type );

		if ( $post_type_object && ! empty( $post_type_object->show_in_rest ) ) {
			$post_types[] = $post_type;
		}
	}

	return $post_types;
}

/**
 * Register the navigation meta fields for the Block Editor.
 *
 * The fields need `show_in_rest` to be read and saved with the `useEntityProp` hook in the
 * editor plugin. Neither field registers a `default`: a registered default changes what
 * `get_post_meta()` returns for posts without a value, and `bu_navigation_post_excluded()`
 * relies on an empty value to fall back to BU_NAVIGATION_POST_EXCLUDE_DEFAULT.
 *
 * @return void
 */
function register_meta_fields() {
	if ( ! is_enabled() ) {
		return;
	}

	foreach ( get_supported_post_types() as $post_type ) {
		// The REST API only reads and saves post meta for post types that support custom fields,
		// and the post order (`menu_order`) for post types that support page attributes.
		foreach ( array( 'custom-fields', 'page-attributes' ) as $feature ) {
			if ( ! post_type_supports( $post_type, $feature ) ) {
				add_post_type_support( $post_type, $feature );
			}
		}

		// Navigation label. Sanitized the same way as the Classic Editor metabox save.
		register_post_meta(
			$post_type,
			BU_NAV_META_PAGE_LABEL,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'wp_kses_post',
				'auth_callback'     => __NAMESPACE__ . '\\can_edit_navigation_meta',
			)
		);

		// Hide from navigation lists: '1' hides the page, '0' shows it, and an empty value uses the site default.
		register_post_meta(
			$post_type,
			BU_NAV_META_PAGE_EXCLUDE,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => __NAMESPACE__ . '\\sanitize_exclude',
				'auth_callback'     => __NAMESPACE__ . '\\can_edit_navigation_meta',
			)
		);
	}
}

/**
 * Only users who can edit the post can change its navigation settings.
 *
 * @param bool   $allowed  Whether the user can edit the meta key. Unused.
 * @param string $meta_key The meta key. Unused.
 * @param int    $post_id  The post ID.
 * @return bool
 */
function can_edit_navigation_meta( $allowed, $meta_key, $post_id ) {
	return current_user_can( 'edit_post', $post_id );
}

/**
 * Store the navigation visibility setting as '1' or '0', and keep an empty value empty.
 *
 * @param mixed $value The value to store.
 * @return string
 */
function sanitize_exclude( $value ) {
	if ( null === $value || '' === $value ) {
		return '';
	}

	return rest_sanitize_boolean( $value ) ? '1' : '0';
}

/**
 * Register the REST route used by the Move dialog.
 *
 * @return void
 */
function register_rest_routes() {
	if ( ! is_enabled() ) {
		return;
	}

	register_rest_route(
		'bu-navigation/v1',
		'/tree',
		array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\\get_tree_level',
			'permission_callback' => __NAMESPACE__ . '\\can_view_tree',
			'args'                => array(
				'post_type' => array(
					'type'              => 'string',
					'required'          => true,
					'validate_callback' => __NAMESPACE__ . '\\is_supported_post_type',
				),
				'child_of'  => array(
					'type'              => 'integer',
					'default'           => 0,
					'minimum'           => 0,
					'sanitize_callback' => 'absint',
				),
			),
		)
	);
}

/**
 * Validate the post_type argument of the tree route.
 *
 * @param string $value The requested post type.
 * @return bool
 */
function is_supported_post_type( $value ) {
	return in_array( $value, get_supported_post_types(), true );
}

/**
 * The tree lists every page of the post type, so only users who can edit that post type can read it.
 *
 * @param \WP_REST_Request $request The REST request.
 * @return bool
 */
function can_view_tree( $request ) {
	$post_type_object = get_post_type_object( $request['post_type'] );

	return $post_type_object && current_user_can( $post_type_object->cap->edit_posts );
}

/**
 * Return the children of one page (or the top level pages) for the Move dialog.
 *
 * The tree is built by BU_Navigation_Tree_View with the same instance name as the Classic
 * Editor metabox ('nav_metabox'), so filters on that instance apply to both.
 *
 * BU Section Editing marks the pages a section editor cannot edit through those filters, but
 * it adds them on admin requests only. When they are missing, as on this REST request, the
 * pages are marked with its permission check, BU_Group_Permissions::can_edit_section(),
 * which reads the same section group data.
 *
 * @param \WP_REST_Request $request The REST request.
 * @return \WP_REST_Response
 */
function get_tree_level( $request ) {
	$tree_view = new \BU_Navigation_Tree_View(
		'nav_metabox',
		array(
			'childOf'      => $request['child_of'],
			'postTypes'    => array( $request['post_type'] ),
			'postStatuses' => array( 'publish', 'private' ),
			'suppressUrls' => true,
		)
	);

	$pages = array_map( __NAMESPACE__ . '\\format_tree_node', $tree_view->render() );

	// BU Section Editing defines this function where it adds the filters.
	if ( is_section_editor() && ! function_exists( 'buse_bu_navigation_format_post' ) ) {
		$user  = wp_get_current_user();
		$pages = array_map(
			function ( $page ) use ( $user ) {
				$page['canEdit'] = \BU_Group_Permissions::can_edit_section( $user, $page['id'] );
				return $page;
			},
			$pages
		);
	}

	return rest_ensure_response( $pages );
}

/**
 * Whether the current user is a section editor in BU Section Editing.
 *
 * @return bool
 */
function is_section_editor() {
	return class_exists( 'BU_Section_Editing_Plugin' )
		&& class_exists( 'BU_Group_Permissions' )
		&& \BU_Section_Editing_Plugin::is_allowed_user( get_current_user_id() );
}

/**
 * Reduce a node in the format BU_Navigation_Tree_View returns to the fields the Move dialog uses.
 *
 * Only one level is returned: a page with children has `hasChildren` set, and its children
 * are requested when the page is expanded.
 *
 * @param array $node A tree node from BU_Navigation_Tree_View::render().
 * @return array
 */
function format_tree_node( $node ) {
	$post      = $node['metadata']['post'];
	$post_meta = $post['post_meta'];

	return array(
		'id'          => (int) preg_replace( '/\D/', '', $node['attr']['id'] ),
		'label'       => html_entity_decode( $node['data'], ENT_QUOTES, get_bloginfo( 'charset' ) ),
		'type'        => $post['post_type'],
		'status'      => $post['post_status'],
		'parent'      => (int) $post['post_parent'],
		'menuOrder'   => (int) $post['menu_order'],
		'excluded'    => (bool) $post_meta['excluded'],
		'hasChildren' => ! empty( $node['state'] ),
		// Set for section editors (see get_tree_level()). Everyone else can edit every page in the tree.
		'canEdit'     => isset( $post_meta['canEdit'] ) ? (bool) $post_meta['canEdit'] : true,
	);
}

/**
 * Update navigation data after a post is saved through the REST API.
 *
 * The Block Editor saves posts through the REST API, so BU_Navigation_Admin_Post::save() does not run.
 * This does the same reordering for those saves: it renumbers the siblings in the old and new sections
 * when the post moves to another parent, or in its section when only its position changes.
 *
 * On the first save of a new post, it also sets up `set_new_post_visibility()` to run after the
 * REST API has saved the post meta sent with the request.
 *
 * @param int      $post_id     The post ID.
 * @param \WP_Post $post_after  The post after the update.
 * @param \WP_Post $post_before The post before the update.
 * @return void
 */
function update_navigation_after_rest_save( $post_id, $post_after, $post_before ) {
	if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST || ! is_enabled() ) {
		return;
	}

	if ( ! in_array( $post_after->post_type, get_supported_post_types(), true ) || 'auto-draft' === $post_after->post_status ) {
		return;
	}

	if ( 'auto-draft' === $post_before->post_status ) {
		add_action( "rest_after_insert_{$post_after->post_type}", __NAMESPACE__ . '\\set_new_post_visibility' );
	}

	$reorder = new \BU_Navigation_Reorder_Tracker( $post_after->post_type );

	if ( (int) $post_before->post_parent !== (int) $post_after->post_parent ) {
		// Moved to another section: renumber the old section and place the post in the new one.
		$reorder->mark_post_as_moved( $post_after );
		$reorder->mark_section_for_reordering( $post_before->post_parent );
	} elseif ( (int) $post_before->menu_order !== (int) $post_after->menu_order ) {
		// Moved within its section.
		$reorder->mark_post_as_moved( $post_after );
	}

	if ( $reorder->has_moves() ) {
		$reorder->run();
	}
}

/**
 * Store the navigation visibility of a new post after its first save through the REST API.
 *
 * New posts show in navigation lists: an empty or missing value is stored as '0'. A top level post is
 * stored as hidden ('1') when top level posts are not allowed in navigation lists. A value chosen in the
 * editor ('0' or '1') is kept.
 *
 * This runs after the REST API saves the request's post meta, because the Block Editor sends every
 * registered meta field when any one of them changes, including an empty navigation visibility value.
 *
 * @param \WP_Post $post The inserted post.
 * @return void
 */
function set_new_post_visibility( $post ) {
	global $bu_navigation_plugin;

	// Only the post whose first save added this callback.
	remove_action( current_action(), __NAMESPACE__ . '\\set_new_post_visibility' );

	if ( '' !== (string) get_post_meta( $post->ID, BU_NAV_META_PAGE_EXCLUDE, true ) ) {
		return;
	}

	$hide_top_level = 0 === (int) $post->post_parent && ! $bu_navigation_plugin->settings->get( 'allow_top' );
	update_post_meta( $post->ID, BU_NAV_META_PAGE_EXCLUDE, $hide_top_level ? '1' : '0' );
}

/**
 * Labels of the post's ancestors, from the top level down, for the panel's breadcrumbs.
 *
 * @param \WP_Post $post The post being edited.
 * @return array List of `array( 'id' => int, 'label' => string )`.
 */
function get_ancestor_labels( $post ) {
	$ancestors = array();
	$parent_id = (int) $post->post_parent;

	while ( $parent_id ) {
		$ancestor = get_post( $parent_id );

		// Stop at a missing parent, or at a loop in the hierarchy.
		if ( ! $ancestor || (int) $ancestor->ID === (int) $post->ID || isset( $ancestors[ $ancestor->ID ] ) ) {
			break;
		}

		$ancestors[ $ancestor->ID ] = array(
			'id'    => (int) $ancestor->ID,
			'label' => html_entity_decode( bu_navigation_get_label( $ancestor ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
		);
		$parent_id                  = (int) $ancestor->post_parent;
	}

	return array_reverse( array_values( $ancestors ) );
}

/**
 * Enqueue the editor plugin on Block Editor screens for supported post types.
 *
 * @return void
 */
function enqueue_block_editor_scripts() {
	global $bu_navigation_plugin;

	if ( ! is_enabled() ) {
		return;
	}

	// The Widgets and Site Editors also fire this action; they have no post type.
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->post_type, get_supported_post_types(), true ) ) {
		return;
	}

	$asset_path = plugin_dir_path( BU_NAV_PLUGIN ) . 'build/editor-plugins/navigation-placement.asset.php';
	$asset      = file_exists( $asset_path )
		? require $asset_path
		: array(
			'dependencies' => array(),
			'version'      => \BU_Navigation_Plugin::VERSION,
		);

	wp_enqueue_script(
		'bu-navigation-placement-editor-plugin',
		plugins_url( 'build/editor-plugins/navigation-placement.js', BU_NAV_PLUGIN ),
		$asset['dependencies'],
		$asset['version'],
		true
	);

	wp_enqueue_style(
		'bu-navigation-placement-editor-plugin',
		plugins_url( 'build/editor-plugins/navigation-placement.css', BU_NAV_PLUGIN ),
		array( 'wp-components' ),
		$asset['version']
	);

	// BU Section Editing adds `isSectionEditor` for section editors through this filter, which
	// BU_Navigation_Tree_View also applies for the Classic Editor metabox's 'nav_metabox' instance.
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The plugin's existing public hook.
	$tree_context = apply_filters( 'bu_nav_tree_script_context', array( 'isSectionEditor' => false ), 'nav_metabox' );

	$post = get_post();

	wp_localize_script(
		'bu-navigation-placement-editor-plugin',
		'buNavigationPlacementSettings',
		array(
			'allowTop'        => (bool) $bu_navigation_plugin->settings->get( 'allow_top' ),
			'excludeDefault'  => (bool) BU_NAVIGATION_POST_EXCLUDE_DEFAULT,
			'linkPostType'    => BU_NAVIGATION_LINK_POST_TYPE,
			'isSectionEditor' => ! empty( $tree_context['isSectionEditor'] ),
			'ancestors'       => $post ? get_ancestor_labels( $post ) : array(),
			'metaKeys'        => array(
				'label'   => BU_NAV_META_PAGE_LABEL,
				'exclude' => BU_NAV_META_PAGE_EXCLUDE,
			),
		)
	);
}

// Initialize the Block Editor plugin integration.
init();
