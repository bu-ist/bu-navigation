<?php

/**
 * Helper class for creating the admin tree view of site content
 * 
 * Aids in queing up the appropriate JS/CSS files, as well as loading
 * the initial tree data.
 */ 
class BU_Navman_Interface {

	private $config;
	private $post_types;

	/**
	 * Setup an object capable of creating the navigation management interface
	 * 
	 * @todo clean this up
	 * 
	 * @param $post_types an array or comma-separated string of post types
	 * @param $config an array of extra optional configuration items
	 */
	public function __construct( $post_types = 'post', $config = array() ) {

		if( is_array( $post_types ) )
			$post_types = implode(',', $post_types );

		$defaults = array(
			'interface_path' => plugins_url( 'images', __FILE__ ),
			'rpc_url' => admin_url('admin-ajax.php?action=bu_getpages&post_type=' . $post_types ),
			'post_types' => $post_types
			);

		$this->config = wp_parse_args( $config, $defaults );

		$this->post_types = explode(',', $post_types );
		
	}

	/**
	 * Enqueue all scripts and styles needed to create the navigation management interface
	 */ 
	public function enqueue_scripts() {

		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';

		$scripts_path = plugins_url('js',__FILE__);
		$vendor_path = plugins_url('js/vendor',__FILE__);
		$styles_path = plugins_url('css',__FILE__);

		// Vendor scripts
		wp_register_script( 'bu-jquery-cookie', $vendor_path . '/jquery.cookie' . $suffix . '.js', array( 'jquery' ), '00168770', true);
		wp_register_script( 'bu-jquery-tree', $vendor_path . '/jstree/jquery.jstree' . $suffix . '.js', array( 'jquery', 'bu-jquery-cookie' ), '1.0-rc3', true );
		
		wp_enqueue_script( 'json2' );

		// Main configuration file
		wp_enqueue_script( 'bu-jquery-tree-config', $scripts_path . '/bu.jstree.config.js', array( 'jquery', 'bu-jquery-tree', 'bu-jquery-cookie', 'json2' ) );

		// Styles
		wp_enqueue_style( 'bu-jquery-tree-classic', $vendor_path . '/jstree/themes/classic/style.css', array(), '1.8.1');
		wp_enqueue_style( 'bu-jquery-tree', $styles_path . '/bu-navigation-tree.css' );

		// Dynamic script context
		$data = array(
			'interfacePath' => $this->config['interface_path'],
			'rpcUrl' => $this->config['rpc_url'],
			);

		wp_localize_script( 'bu-jquery-tree-config', 'buNavTree', $data );

	}

	/**
	 * Fetches top level pages, formatting for jstree consumption
	 * 
	 * @todo
	 * 	- rename this to "get_pages" and take arguments that will dictate depth loaded
	 *  - use this method to load subsequent sections from a specific page ID
	 *  - make this extendable, so that plugins can tie in to the formatting portion to add their own attributes
	 */ 
	public function get_top_level() {

		$pages = array();

		/* remove default page filter and add our own */
		remove_filter('bu_navigation_filter_pages', 'bu_navigation_filter_pages_exclude');
		add_filter('bu_navigation_filter_pages', array( __CLASS__, 'filter_pages' ) );

		$section_args = array('direction' => 'down', 'depth' => 1, 'sections' => array(0), 'post_types' => $this->post_types);
		$sections = bu_navigation_gather_sections(0, $section_args);
		
		$root_pages = bu_navigation_get_pages(array('sections' => $sections, 'post_types' => $this->post_types));

		$pages_by_parent = bu_navigation_pages_by_parent($root_pages);

		if ((is_array($pages_by_parent[0])) && (count($pages_by_parent[0]) > 0))
		{
			foreach ($pages_by_parent[0] as $page)
			{
				if (!isset($page->navigation_label)) $page->navigation_label = apply_filters('the_title', $page->post_title);

				$title = $page->navigation_label;

				$classes = array(); // css classes
				
				$p = array(
					'attr' => array('id' => sprintf('p%d', $page->ID), 'class' => ''),
					'data' => $title,
					);

				if (isset($pages_by_parent[$page->ID]) && (is_array($pages_by_parent[$page->ID])) && (count($pages_by_parent[$page->ID]) > 0))
				{
					$p['state'] = 'closed';
					$p['attr']['rel'] = 'page';
				}

				if (!array_key_exists('state', $p))
				{
					$p['attr']['rel'] = ($page->post_type == 'link' ? $page->post_type : 'page');
				}

				if (isset($page->excluded) && $page->excluded)
				{
					$p['attr']['rel'] .= '_excluded';
					array_push($classes, 'excluded');
				}

				if ($page->restricted)
				{
					$p['attr']['rel'] .= '_restricted';
					array_push($classes, 'restricted');
				}

				if(isset($page->perm))
				{
					if( $page->perm == 'denied' )
						$p['attr']['rel'] .= '_denied';
					
					array_push($classes,$page->perm);
				}

				$p['attr']['class'] = implode(' ', $classes);

				array_push($pages, $p);
			}

			/* remove our page filter and add back in the default */
			remove_filter('bu_navigation_filter_pages', array( __CLASS__, 'filter_pages' ) );
			add_filter('bu_navigation_filter_pages', 'bu_navigation_filter_pages_exclude');

			return $pages;

		}

		/* remove our page filter and add back in the default */
		remove_filter('bu_navigation_filter_pages', array( __CLASS__, 'filter_pages' ) );
		add_filter('bu_navigation_filter_pages', 'bu_navigation_filter_pages_exclude');

	}

	/**
	 * Handles fetching child pages and formatting for jstree consumption
	 * 
	 * @todo
	 * 	- reduce redundancies between this method and get_top_level
	 */ 
	public function get_children( $parent_id, $pages_by_parent ) {

		$children = array();

		if (array_key_exists($parent_id, $pages_by_parent))
		{
			$pages = $pages_by_parent[$parent_id];

			if ((is_array($pages)) && (count($pages) > 0))
			{
				foreach ($pages as $page)
				{
					if (!isset($page->navigation_label)) $page->navigation_label = apply_filters('the_title', $page->post_title);

					$title = $page->navigation_label;

					$p = array(
						'attr' => array('id' => sprintf('p%d', $page->ID)),
						'data' => $title
						);

					$classes = array(); // CSS classes

					if (isset($pages_by_parent[$page->ID]) && (is_array($pages_by_parent[$page->ID])) && (count($pages_by_parent[$page->ID]) > 0))
					{
						$p['state'] = 'closed';
						$p['attr']['rel'] = 'page';

						$descendants = $this->get_children($page->ID, $pages_by_parent);

						if (count($descendants) > 0) $p['children'] = $descendants;
					}

					if (!array_key_exists('state', $p))
					{
						$p['attr']['rel'] = ($page->post_type == 'link' ? $page->post_type : 'page');
					}

					if (isset($page->excluded) && $page->excluded)
					{
						$p['attr']['rel'] .= '_excluded';
						array_push($classes, 'excluded');
					}

					if ($page->restricted)
					{
						$p['attr']['rel'] .= '_restricted';
						array_push($classes, 'restricted');
					}

					if(isset($page->perm))
					{
						if( $page->perm == 'denied' )
							$p['attr']['rel'] .= '_denied';
						
						array_push($classes,$page->perm);
					}

					$p['attr']['class'] = implode(' ', $classes);

					array_push($children, $p);
				}
			}
		}

		return $children;

	}

	/**
	 * Default navigation manager page filter
	 * 
	 * Appends "excluded" and "restricted" properties to each post object
	 * 
	 * @todo
	 * 	- make this more extendable, so that plugins can tie in here and add their own properties
	 */ 
	public static function filter_pages( $pages ) {
		global $wpdb;

		$filtered = array();

		if ((is_array($pages)) && (count($pages) > 0)) {

			/* page exclusions */
			$ids = array_keys($pages);

			$query = sprintf("SELECT post_id, meta_value FROM %s WHERE meta_key = '%s' AND post_id IN (%s) AND meta_value != '0'", $wpdb->postmeta, BU_NAV_META_PAGE_EXCLUDE, implode(',', $ids));

			$exclusions = $wpdb->get_results($query, OBJECT_K); // get results as objects in an array keyed on post_id
			if (!is_array($exclusions)) $exclusions = array();

			/* access restrictions */

			$restricted = array();

			// @todo move this query to the access control plugin
			// look at bu-section-editing/plugin-support/bu-navigation
			if (class_exists('BuAccessControlPlugin'))
			{
				$acl_option = defined( 'BuAccessControlList::PAGE_ACL_OPTION' ) ? BuAccessControlList::PAGE_ACL_OPTION : BU_ACL_PAGE_OPTION;

				$query = sprintf("SELECT post_id, meta_value FROM %s WHERE meta_key = '%s' AND post_id IN (%s) AND meta_value != '0'", $wpdb->postmeta, $acl_option, implode(',', $ids));

				$restricted = $wpdb->get_results($query, OBJECT_K); // get results as objects in an array keyed on post_id
				if (!is_array($restricted)) $restricted = array();
			}

			/* set exclusions and acls */
			foreach ($pages as $page)
			{
				/* exclusions */
				if (array_key_exists($page->ID, $exclusions))
				{
					$page->excluded = TRUE;
				}
				else
				{
					$parent_id = $page->post_parent;

					while (($parent_id) && (array_key_exists($parent_id, $pages)))
					{
						if (array_key_exists($parent_id, $exclusions))
						{
							$page->excluded = TRUE;
							break;
						}

						$parent_id = $pages[$parent_id]->post_parent;
					}
				}

				/* restrictions */
				$page->restricted = FALSE;

				if (array_key_exists($page->ID, $restricted))
				{
					$page->restricted = TRUE;
				}
				else
				{
					$parent_id = $page->post_parent;

					while (($parent_id) && (array_key_exists($parent_id, $pages)))
					{
						if (array_key_exists($parent_id, $restricted))
						{
							$page->restricted = TRUE;
							break;
						}

						$parent_id = $pages[$parent_id]->post_parent;
					}
				}

				$filtered[$page->ID] = $page;
			}
		}

		return $filtered;
	}

}