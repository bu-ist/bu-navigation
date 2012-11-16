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
		$this->no_title_text = __('(no title)');

		// Merge default script context with arg
		$defaults = array(
			'childOf' => 0,
			'postTypes' => array('page','link'),
			'postStatuses' => array('draft','pending','publish'),
			'themePath' => plugins_url('css/vendor/jstree/themes/bu-jstree', __FILE__ ),
			'rpcUrl' => admin_url('admin-ajax.php?action=bu-get-navtree' ),
			'getPostRpcUrl' => admin_url('admin-ajax.php?action=bu-get-post'),
			'allowTop' => $this->plugin->get_setting('allow_top'),
			'loadInitialData' => false,
			'lazyLoad' => true,
			'showCounts' => true,
			'showStatuses' => true,
			'nodePrefix' => 'p'
			);
		$this->settings = wp_parse_args( $script_context, $defaults );

		// Setup query args based on script context
		$query_args = array(
				'child_of' => $this->settings['childOf'],
				'post_types' => $this->settings['postTypes'],
				'post_status' => $this->settings['postStatuses']
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

		$scripts_path = plugins_url('js',__FILE__);
		$vendor_path = plugins_url('js/vendor',__FILE__);
		$styles_path = plugins_url('css',__FILE__);

		// Vendor scripts
		wp_register_script( 'bu-jquery-cookie', $vendor_path . '/jquery.cookie' . $suffix . '.js', array( 'jquery' ), '00168770', true );
		wp_register_script( 'bu-jquery-tree', $vendor_path . '/jstree/jquery.jstree' . $suffix . '.js', array( 'jquery', 'bu-jquery-cookie' ), '1.0-rc3', true );

		// Main navigation scripts & styles
		wp_register_script( 'bu-navigation', $scripts_path . '/bu-navigation' . $suffix . '.js', array( 'jquery', 'bu-jquery-tree', 'bu-jquery-cookie', 'json2' ), BU_Navigation_Plugin::VERSION, true );
		wp_register_style( 'bu-navigation', $styles_path . '/vendor/jstree/themes/bu-jstree/style.css', array(), BU_Navigation_Plugin::VERSION );

	}

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
		$posts_by_parent = bu_navigation_pages_by_parent($this->query->posts);

		// Display children only for non-top level page requests
		if( $child_of == 0 ) {
			$load_children = false;
		} else {
			$load_children = true;
		}

		// Convert to jstree formatted posts
		$formatted_posts = $this->get_formatted_posts( $child_of, $posts_by_parent, $load_children );

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

		// Base format
		$p = array(
			'attr' => array(
				'id' => $this->add_node_prefix( $post->ID ),
				'rel' => ( $post->post_type == 'link' ? $post->post_type : 'page' ),
				),
			'data' => $post->navigation_label,
			'metadata' => array(
				'post_status' => $post->post_status,
				'post_type' => $post->post_type,
				'post_parent' => $post->post_parent,
				'menu_order' => $post->menu_order,
				'post_meta' => array(
					'excluded' => ( isset($post->excluded) ? $post->excluded : false ),
					'restricted' => ( isset($post->restricted) ? $post->restricted : false )
					),
				'originalParent' => $post->post_parent,
				'originalOrder' => $post->menu_order,
				'originalExclude' => ( isset($post->excluded) ? $post->excluded : false )
				)
			);

		if( 'link' == $post->post_type ) {
			$p['metadata']['post_content'] = $post->post_content;
			$p['metadata']['post_meta'] = array(
				BU_NAV_META_TARGET => $post->target
				);
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
 */
class BU_Navigation_Tree_Query {

	public $posts;
	public $post_count;
	public $args;

	public function __construct( $query_args = array() ) {
		$this->setup_query( $query_args );
		$this->query();
	}

	protected function setup_query( $query_args = array() ) {

		$defaults = array(
				'child_of' => 0,
				'post_types' => array('page', 'link'),
				'post_status' => array('draft','pending','publish'),
				'direction' => 'down',
				'depth' => 0
		);

		$this->args = array();
		$this->args = wp_parse_args( $query_args, $defaults );

		if( ! empty( $this->args['post_types'] ) && is_string( $this->args['post_types'] ) ) {
			$this->args['post_types'] = explode( ',', $this->args['post_types'] );
		}

		if( ! empty( $this->args['post_status'] ) && is_string( $this->args['post_status'] ) ) {
			$this->args['post_status'] = explode( ',', $this->args['post_status'] );
		}

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
		remove_filter('bu_navigation_filter_pages', 'bu_navigation_filter_pages_exclude');
		add_filter('bu_navigation_filter_pages', array( __CLASS__, 'filter_posts' ) );
		add_filter('bu_navigation_filter_fields', array( __CLASS__, 'filter_fields' ) );

		// Gather sections
		$section_args = array('direction' => $this->args['direction'], 'depth' => $this->args['depth'], 'post_types' => $this->args['post_types']);
		$sections = bu_navigation_gather_sections( $this->args['child_of'], $section_args );

		// Load pages in sections
		$this->posts = bu_navigation_get_pages( array(
			'sections' => $sections,
			'post_types' => $this->args['post_types'],
			'post_status' => $this->args['post_status']
			)
		);

		$this->post_count = count($this->posts);

		// Restore filters
		remove_filter('bu_navigation_filter_fields', array( __CLASS__, 'filter_fields' ) );
		remove_filter('bu_navigation_filter_pages', array( __CLASS__, 'filter_posts' ) );
		add_filter('bu_navigation_filter_pages', 'bu_navigation_filter_pages_exclude');

	}

	/**
	 * Default navigation manager page filter
	 *
	 * Appends "excluded" and "restricted" properties to each post object
	 */
	public function filter_posts( $posts ) {
		global $wpdb;

		$filtered = array();

		if( is_array( $posts ) && count( $posts ) > 0 ) {

			$ids = array_keys($posts);

			// Bulk fetch navigation exclusions data for passed posts
			$exclude_option = BU_NAV_META_PAGE_EXCLUDE;
			$query = sprintf("SELECT post_id, meta_value FROM %s WHERE meta_key = '%s' AND post_id IN (%s) AND meta_value != '0'", $wpdb->postmeta, $exclude_option, implode(',', $ids));
			$exclusions = $wpdb->get_results($query, OBJECT_K); // get results as objects in an array keyed on post_id
			if (!is_array($exclusions)) $exclusions = array();

			// Bulk fetch ACL restriction data for passed posts
			// @todo move this query to the access control plugin
			// look at bu-section-editing/plugin-support/bu-navigation
			$restricted = array();

			if (class_exists('BuAccessControlPlugin')) {

				$acl_option = defined( 'BuAccessControlList::PAGE_ACL_OPTION' ) ? BuAccessControlList::PAGE_ACL_OPTION : BU_ACL_PAGE_OPTION;
				$query = sprintf("SELECT post_id, meta_value FROM %s WHERE meta_key = '%s' AND post_id IN (%s) AND meta_value != '0'", $wpdb->postmeta, $acl_option, implode(',', $ids));
				$restricted = $wpdb->get_results($query, OBJECT_K); // get results as objects in an array keyed on post_id
				if (!is_array($restricted)) $restricted = array();

			}

			// Add 'excluded' and 'resticted' field to all posts
			foreach( $posts as $post ) {

				// Post exclusions (hidden from navigation lists)
				if( array_key_exists( $post->ID, $exclusions ) ) {

					$post->excluded = TRUE;

				}

				// Post restrictions (ACL restricted by access-control plugin)
				// @todo move to access-control plugin
				$post->restricted = FALSE;

				if( array_key_exists($post->ID, $restricted) ) {

					$post->restricted = TRUE;

				}

				$filtered[$post->ID] = $post;
			}

		}

		return $filtered;

	}

	/**
	 * Filter wp_post columns to fetch from DB
	 */
	public static function filter_fields( $fields ) {

		// Adding post status so we can include status indicators in tree view
		$fields[] = 'post_status';

		return $fields;

	}

}

/**
 * RPC endpoint for rendering a list of posts in jstree format
 */
function bu_navigation_ajax_get_navtree() {
	if( defined('DOING_AJAX') && DOING_AJAX ) {

		$child_of = isset($_POST['child_of']) ? $_POST['child_of'] : 0;
		$post_types = isset($_POST['post_types']) ? $_POST['post_types'] : array('page','link');
		$post_statuses = isset($_POST['post_statuses']) ? $_POST['post_statuses'] : 'publish';
		$instance = isset($_POST['instance']) ? $_POST['instance'] : 'default';
		$prefix = isset($_POST['prefix']) ? $_POST['prefix'] : 'p';

		$tree_view = new BU_Navigation_Tree_View( $instance, array(
			'childOf' => $child_of,
			'postTypes' => $post_types,
			'postStatuses' => $post_statuses,
			'nodePrefix' => $prefix
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
		if( $post->post_type == 'link' ){
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
