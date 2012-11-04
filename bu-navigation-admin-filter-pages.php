<?php
/*
 @todo
 	- original file had "fix pagination" -- i'm not sure what's wrong with it
 */

class BU_Navigation_Admin_Filter_Pages {

	public $post_type;

	public function __construct( $post_type, $post_parent ) {

		$this->post_type = $post_type;
		$this->post_parent = $post_parent;

		$this->register_hooks();

	}

	public function register_hooks() {

		add_action( 'restrict_manage_posts', array( $this, 'render_dropdown' ) );
		add_filter( 'the_posts', array( $this, 'filter_posts' ) );

	}

	public function render_dropdown() {

		$selected = $this->post_parent;

		bu_navigation_page_parent_dropdown( $this->post_type, $selected );

	}

	public function filter_posts( $posts ) {

		if( $this->post_parent ) {

			$section_args = array('direction' => 'down', 'depth' => 0, 'post_types' => array($this->post_type));
			$sections = bu_navigation_gather_sections($this->post_parent, $section_args);

			if( (is_array($sections)) && (count($sections) > 0)) {

				$filtered = array();

				foreach ($posts as $p) {
					if ((in_array($p->post_parent, $sections)) || (in_array($p->ID, $sections)))
						array_push($filtered, $p);
				}

				$posts = $filtered;
			}
		}

		return $posts;

	}

}

?>
