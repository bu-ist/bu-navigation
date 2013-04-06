<?php

/**
 * Edit single post w/ navigation metabox object
 */
class BUN_EditPage {

	/* URL's */
	const EDIT_URL = '/wp-admin/post.php?post=%s&action=edit';
	const NEW_URL = '/wp-admin/post-new.php?post_type=%s';
	const EDIT_HEADING_XPATH = "//h2[contains(text(),'Edit')]";
	const NEW_HEADING_XPATH = "//h2[contains(text(),'Add New')]";

	/**
	 * Load the new or edit post page
	 */
	function __construct( $webdriver, $args = array() ) {

		$defaults = array(
			'post_id' => '',
			'post_type' => 'page'
			);

		$args = wp_parse_args( $args, $defaults);
		extract( $args );

		// Generate request URL
		$request_url = '';
		$test_xpath = '';

		// Valid post ID = edit
		if( $post_id ) $action = 'edit';
		// Otherwise, valid post type = new
		else if( $post_type ) $action = 'new';
		// Otherwise, fail
		else throw new Exception('BU Navigation edit page needs either a valid post ID or post type.' );

		switch( $action ) {
			case 'edit':
				$request_url = sprintf(self::EDIT_URL,$post_id);
				$test_xpath = self::EDIT_HEADING_XPATH;
				break;
			case 'new': default:
				$request_url = sprintf(self::NEW_URL,$post_type);
				$test_xpath = self::NEW_HEADING_XPATH;
				break;
		}

		$this->webdriver = $webdriver;

		$this->webdriver->url( $request_url  );

		try {
			$this->webdriver->byXpath( $test_xpath );
		} catch( RuntimeException $e ) {
			throw new Exception('BU Navigation edit page failed to load with URL: ' . $request_url );
		}

	}


}
