<?php

/**
 * Navigation tree view API
 *
 * Used to build post tree interfaces client side
 */
class BU_Navigation_Tree_View {

	private $instance;
	private $settings;
	private $plugin;

	private $query;

	/**
	 * Setup an object capable of creating the navigation management interface
	 *
	 * @param $instance unique instance name for this interface
	 * @param $script_context an array of settings to be passed to client scripts
	 */
	public function __construct( $instance = 'legacy', $script_context = array() ) {

		$this->plugin = $GLOBALS['bu_navigation_plugin'];
		$this->instance = $instance;
		$this->no_title_text = __('(no title)', 'bu-navigation' );

		// Merge default script context with arg
		$defaults = array(
			'childOf' => 0,
			'postTypes' => array('page'),
			'postStatuses' => array('publish'),
			'includeLinks' => true,
			'suppressUrls' => false,
			'themePath' => plugins_url('js/vendor/jstree/themes/bu-jstree', BU_NAV_PLUGIN ),
			'rpcUrl' => admin_url('admin-ajax.php?action=bu-get-navtree' ),
			'getPostRpcUrl' => admin_url('admin-ajax.php?action=bu-get-post'),
			'allowTop' => $this->plugin->settings->get( 'allow_top' ),
			'linksSupported' => $this->plugin->supports( 'links' ),
			'linksPostType' => BU_NAVIGATION_LINK_POST_TYPE,
			'loadInitialData' => false,
			'lazyLoad' => true,
			'showCounts' => true,
			'showStatuses' => true,
			'nodePrefix' => 'p',
			'statusBadgeExcluded' => __( 'not in nav', 'bu-navigation' ),
			'statusBadgeProtected' => __( 'protected', 'bu-navigation' ),
			);
		$this->settings = wp_parse_args( $script_context, $defaults );

		if( ! $this->plugin->supports( 'links' ) )
			$this->settings['includeLinks'] = false;

		// Setup query args based on script context
		$query_args = array(
			'child_of' => $this->settings['childOf'],
			'post_types' => $this->settings['postTypes'],
			'post_status' => $this->settings['postStatuses'],
			'include_links' => $this->settings['includeLinks'],
			'suppress_urls' => $this->settings['suppressUrls']
		);
		$this->query = new BU_Navigation_Tree_Query( $query_args );

		// No need to register scripts during AJAX requests
		if( ! defined('DOING_AJAX') || ! DOING_AJAX ) {
			$this->register_scripts();
		}
	}

	/**
	 * Enqueue all scripts and styles needed to create the navigation management interface
	 *
	 * Must be called before scripts are printed
	 */
	public function register_scripts() {
		global $wp_version;

		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
		$scripts_url = plugins_url( 'js', BU_NAV_PLUGIN );
		$vendor_url = plugins_url( 'js/vendor', BU_NAV_PLUGIN );

		// Vendor scripts
		wp_register_script( 'bu-jquery-cookie', $vendor_url . '/jquery.cookie' . $suffix . '.js', array( 'jquery' ), '00168770', true );
		wp_register_script( 'bu-jquery-tree', $vendor_url . '/jstree/jquery.jstree' . $suffix . '.js', array( 'jquery', 'bu-jquery-cookie' ), '1.0-rc3', true );

		// Main navigation scripts & styles
		wp_register_script( 'bu-navigation', $scripts_url . '/bu-navigation' . $suffix . '.js', array( 'jquery', 'bu-jquery-tree', 'bu-jquery-cookie', 'json2' ), BU_Navigation_Plugin::VERSION, true );
		wp_register_style( 'bu-navigation', $vendor_url . '/jstree/themes/bu-jstree/style.css', array(), BU_Navigation_Plugin::VERSION );

	}

	/**
	 * Special wrapper around wp_enqueue_script that handles generating script context
	 * for scripts utilizing this class.
	 *
	 * @see bu-navigation-admin-navman.php or bu-navigation-admin-metabox.php for usage examples
	 *
	 * @global string $wp_version
	 * @param string $name script name to enqueue
	 */
	public function enqueue_script( $name ) {
		global $wp_version;

		// Queue up dependencies
		wp_enqueue_style( 'bu-navigation' );
		wp_enqueue_script( 'bu-navigation' );

		// Queue up instance script
		wp_enqueue_script( $name );

		// Allow for filtering
		do_action( 'bu_nav_tree_enqeueue_scripts' );

		// Load initial tree data and allow plugins to enhance instance settings object
		$this->settings['instance'] = $this->instance;

		if( $this->settings['loadInitialData'] ) {
			$this->settings['initialTreeData'] = $this->render();
		}

		$this->settings = apply_filters( 'bu_nav_tree_script_context', $this->settings, $this->instance );

		// Hack due to lack of support for array data to wp_localize_script in WP < 3.3
		if( version_compare( $wp_version, '3.3', '<' ) ) {
			add_action( 'admin_print_footer_scripts', array( $this, 'print_footer_scripts' ) );
		} else {
			wp_localize_script( 'bu-navigation', $this->instance . '_settings', $this->settings );
		}

	}

	/**
	 * Print dynamic script context with plugin settings for JS
	 */
	public function print_footer_scripts() {
		global $wp_scripts;

		// Check if bu-navigation script is queued
		if( in_array( 'bu-navigation', array_keys( $wp_scripts->registered ) ) ) {
			$this->localize( $this->instance . '_settings', $this->settings );
		}
	}

	/**
	 * Custom version of WP_Scripts::localize to provide localization for array data
	 *
	 * @see WP_Scripts::localize in WP 3.3 on
	 */
	public function localize( $object_name, $script_data, $echo = true ) {

		foreach ( (array) $script_data as $key => $value ) {
			if ( !is_scalar($value) )
				continue;

			$script_data[$key] = html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8');
		}

		$script = "var $object_name = " . json_encode($script_data) . ';';

		if ( $echo ) {
			echo "<script type='text/javascript'>\n";
			echo "/* <![CDATA[ */\n";
			echo $script;
			echo "/* ]]> */\n";
			echo "</script>\n";
			return true;
		} else {
			return $data;
		}
	}

	public function render() {

		$child_of = $this->query->args['child_of'];

		// Structure in to parent/child sections keyed by parent ID
		$posts = $this->query->posts_by_parent();

		// Display children only for non-top level page requests
		if( $child_of == 0 ) {
			$load_children = false;
		} else {
			$load_children = true;
		}

		// Convert to jstree formatted posts
		$formatted_posts = $this->get_formatted_posts( $child_of, $posts, $load_children );

		return $formatted_posts;
	}

	/**
	 * Handles fetching child posts and formatting for jstree consumption
	 *
	 * @param int $child_of post ID to fetch children of
	 * @param array $posts_by_parent array of all pages, keyed by post ID and grouped in to sections
	 * @return array $children children of specified parent, formatted for jstree json_data consumption
	 */
	public function get_formatted_posts( $child_of, $posts_by_parent, $load_children = true ) {

		$children = array();

		if( array_key_exists( $child_of, $posts_by_parent ) ) {

			$posts = $posts_by_parent[$child_of];

			if( is_array( $posts ) && ( count( $posts ) > 0 ) ) {

				foreach( $posts as $post ) {

					$has_children = false;

					if( isset( $posts_by_parent[$post->ID] ) && ( is_array( $posts_by_parent[$post->ID] ) ) && ( count( $posts_by_parent[$post->ID] ) > 0 ) )
						$has_children = true;

					// Format attributes for jstree
					$p = $this->format_post( $post, $has_children );

					// Fetch children recursively
					if( $has_children ) {

						$p['state'] = 'closed';

						if( $load_children ) {
							$descendants = $this->get_formatted_posts( $post->ID, $posts_by_parent );

							if( count( $descendants ) > 0 ) {
								$p['children'] = $descendants;
							}
						}

					}

					array_push( $children, $p );
				}

			}

		}

		return $children;

	}

	/**
	 * Given a WP post object, return an array of data formated for consumption by jstree
	 *
	 * @param StdClass $post WP post object
	 * @return array $p array of data with markup attributes for jstree json_data plugin
	 */
	public function format_post( $post, $has_children = false ) {
		// Label
		if( !isset( $post->navigation_label ) ) {
			$post->navigation_label = apply_filters( 'the_title', $post->post_title );
		}

		if ( empty( $post->navigation_label ) ) {
			$post->navigation_label = $this->no_title_text;
		}

		$post_data = array(
			'post_status' => $post->post_status,
			'post_type' => $post->post_type,
			'post_parent' => $post->post_parent,
			'menu_order' => $post->menu_order,
			'post_meta' => array(
				'protected' => ( isset( $post->post_password ) && ! empty( $post->post_password ) ) ? true : false,
				'excluded' => isset( $post->excluded ) ? $post->excluded : false,
				),
			'url' => $post->url,
			'originalParent' => $post->post_parent,
			'originalOrder' => $post->menu_order,
			'originalExclude' => isset( $post->excluded ) ? $post->excluded : false
		);

		// Base format
		$p = array(
			'attr' => array(
				'id' => $this->add_node_prefix( $post->ID ),
				'rel' => BU_NAVIGATION_LINK_POST_TYPE == $post->post_type ? 'link' : 'page',
				),
			'data' => $post->navigation_label,
			'metadata' => array(
				'post' => $post_data
				)
			);

		if( BU_NAVIGATION_LINK_POST_TYPE == $post->post_type ) {
			$p['metadata']['post']['post_content'] = $post->post_content;
			$p['metadata']['post']['post_meta'][BU_NAV_META_TARGET] = $post->target;
		}

		if( $has_children ) {
			$p['attr']['rel'] = 'section';
		}

		// Apply general format post filters first
		$p = apply_filters( 'bu_nav_tree_view_format_post', $p, $post, $has_children );

		// But give priority to more specific format filters
		return apply_filters( 'bu_nav_tree_view_format_post_' . $this->instance, $p, $post, $has_children );

	}

	public function strip_node_prefix( $id ) {

		return intval(str_replace( $this->settings['nodePrefix'], '', $id ));

	}

	public function add_node_prefix( $id ) {

		return sprintf('%s%d', $this->settings['nodePrefix'], $id );

	}

}

/**
 * WP_Query-like class optimized for querying hierarchical post types
 *
 * @todo move to separate file
 * @todo move logic from library in to this query class, make the library functions wrappers around this object
 */
class BU_Navigation_Tree_Query {

	public $posts;
	public $post_count;
	public $args;

	public $plugin;

	public function __construct( $query_args = array() ) {

		$this->plugin = $GLOBALS['bu_navigation_plugin'];

		$this->setup_query( $query_args );
		$this->query();

	}

	/**
	 * Special processing for query args
	 */
	protected function setup_query( $query_args = array() ) {

		$defaults = array(
			'child_of' => 0,
			'post_types' => array( 'page' ),
			'post_status' => array( 'publish' ),
			'include_links' => true,
			'suppress_urls' => false,
			'depth' => 0,
		);

		$this->args = array();
		$this->args = wp_parse_args( $query_args, $defaults );

		// Don't allow fetching of entire tree
		if( $this->args['child_of'] == 0 ) {
			$this->args['depth'] = 1;
		}

	}

	/**
	 * Execute query set up during construction
	 */
	protected function query() {

		// Setup filters
		remove_filter( 'bu_navigation_filter_pages', 'bu_navigation_filter_pages_exclude' );
		add_filter( 'bu_navigation_filter_pages', array( $this, 'filter_posts' ) );

		// Gather sections
		$section_args = array( 'direction' => 'down', 'depth' => $this->args['depth'], 'post_types' => $this->args['post_types'] );
		$sections = bu_navigation_gather_sections( $this->args['child_of'], $section_args );

		// Load pages in sections
		$this->posts = bu_navigation_get_posts( array(
			'sections' => $sections,
			'post_types' => $this->args['post_types'],
			'post_status' => $this->args['post_status'],
			'include_links' => $this->args['include_links'],
			'suppress_urls' => $this->args['suppress_urls']
			)
		);

		$this->post_count = count($this->posts);

		// Restore filters
		remove_filter('bu_navigation_filter_pages', array( $this, 'filter_posts' ) );
		add_filter('bu_navigation_filter_pages', 'bu_navigation_filter_pages_exclude');

	}

	public function posts_by_parent() {

		return bu_navigation_pages_by_parent($this->posts);

	}

	/**
	 * Default navigation manager post filter
	 *
	 * Appends extra post meta properties to each post object
	 */
	public function filter_posts( $posts ) {
		global $wpdb;

		$filtered = array();

		if ( is_array( $posts ) && count( $posts ) > 0 ) {

			// Fetch posts that have been explicitly excluded from navigation lists
			$ids = array_keys( $posts );
			$query = sprintf( "SELECT post_id, meta_value FROM %s WHERE meta_key = '%s' AND post_id IN (%s)",
				$wpdb->postmeta,
				BU_NAV_META_PAGE_EXCLUDE,
				implode( ',', $ids )
				);
			$exclude_meta = $wpdb->get_results( $query, OBJECT_K );

			if ( false === $exclude_meta ) {
				error_log( __FUNCTION__ . " - Error querying navigation exclusions: {$wpdb->last_error}" );
				return apply_filters( 'bu_nav_tree_view_filter_posts', $posts );
			}

			foreach ( $posts as $post ) {

				// Post meta row exists, determine exclusion based on meta_value
				if ( array_key_exists( $post->ID, $exclude_meta ) ) {
					$excluded = (bool) $exclude_meta[ $post->ID ]->meta_value;
				} else {
					// No post meta row has been inserted yet
					if ( isset( $post->post_type ) && BU_NAVIGATION_LINK_POST_TYPE == $post->post_type ) {
						// Navigation links get special treatment since they will always be visible
						$excluded = false;
					} else {
						// Otherwise fall back to default constant
						$excluded = BU_NAVIGATION_POST_EXCLUDE_DEFAULT;
					}
				}
				$post->excluded = $excluded;

				$filtered[ $post->ID ] = $post;
			}

		}

		return apply_filters( 'bu_nav_tree_view_filter_posts', $filtered );

	}

}

/**
 * RPC endpoint for rendering a list of posts in jstree format
 */
function bu_navigation_ajax_get_navtree() {
	if( defined('DOING_AJAX') && DOING_AJAX ) {

		$child_of = isset($_POST['child_of']) ? $_POST['child_of'] : 0;
		$post_types = isset($_POST['post_types']) ? $_POST['post_types'] : 'page';
		$post_statuses = isset($_POST['post_statuses']) ? $_POST['post_statuses'] : 'publish';
		$instance = isset($_POST['instance']) ? $_POST['instance'] : 'default';
		$prefix = isset($_POST['prefix']) ? $_POST['prefix'] : 'p';
		$include_links = isset($_POST['include_links']) ? (bool) $_POST['include_links'] : true;

		$tree_view = new BU_Navigation_Tree_View( $instance, array(
			'childOf' => $child_of,
			'postTypes' => $post_types,
			'postStatuses' => $post_statuses,
			'nodePrefix' => $prefix,
			'includeLinks' => $include_links
			)
		);

		echo json_encode( $tree_view->render() );
		die();
	}
}

add_action( 'wp_ajax_bu-get-navtree', 'bu_navigation_ajax_get_navtree' );

/**
 * RPC endpoint for fetching a post object
 *
 * In addition to the standard post fields, this call will
 * also return the post permalink in the "url" field.
 *
 * If the post is a custom BU link, it will also add a
 * "target" field ("same" or "new")
 */
function bu_navigation_ajax_get_post() {
	if( defined('DOING_AJAX') && DOING_AJAX ) {

		$post_id = isset($_GET['post_id']) ? $_GET['post_id'] : 0;
		$post = get_post($post_id);

		// Add extra fields to response for links
		if( BU_NAVIGATION_LINK_POST_TYPE == $post->post_type ){
			$post->target = get_post_meta( $post_id, 'bu_link_target', TRUE );
			$post->url = $post->post_content;
		} else {
			$post->url = get_permalink($post_id);
		}

		echo json_encode( $post );
		die();

	}
}

add_action( 'wp_ajax_bu-get-post', 'bu_navigation_ajax_get_post' );
