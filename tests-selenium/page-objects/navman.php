<?php

/**
 * Edit Order page (Edit Order + Post Type)
 */ 
class BUN_Navman_Page {

	protected $webdriver = null;
	protected $group_form = null;

	protected $leaf_id_prefix;

	/* URL's */
	const NAVMAN_BASE_URL = '/wp-admin/edit.php?page=bu-navigation/bu-navman.php';
	const TEST_XPATH = "//h2[contains(text(),'Edit Navigation')]";

	/* Markup constants */

	// Forms
	const NAVMAN_FORM = 'navman_form';

	const NAVMAN_EDIT_BTN = 'bu_navman_edit';
	const NAVMAN_DELETE_BTN = 'bu_navman_delete';
	const NAVMAN_SAVE_BTN = 'bu_navman_save';
	const NAVMAN_EXPAND_BTN = 'navman_expand_all';
	const NAVMAN_COLLAPSE_BTN = 'navman_collapse_all';

	// Types
	const TYPES_PAGE = 'page';
	const TYPES_FOLDER = 'folder';
	const TYPES_EXCLUDED = 'page_excluded';

	/**
	 * Load the Navman page
	 */ 
	function __construct( $webdriver, $post_type = 'page' ) {

		$this->webdriver = $webdriver;

		// Generate request URL
		$request_url = self::NAVMAN_BASE_URL . '&post_type=' . $post_type;
		$this->webdriver->open( $request_url  );

		try {
			$this->webdriver->findElementBy( LocatorStrategy::xpath, self::TEST_XPATH );
		} catch( NoSuchElementException $e ) {
			throw new Exception('BU Navigation Edit Order failed to load -- Unable to load URL: ' . $request_url );
		}

		$this->form = new SeleniumFormHelper( $this->webdriver, self::NAVMAN_FORM );
		$this->leaf_id_prefix = 'p';
	}

	const GET_PAGE_XPATH = '//li[@id="%s"]';

	/**
	 * Return either the list item or clickable anchor for the given page ID
	 * 
	 * @param $id int Post ID to look for
	 * @param $el string which markup component to look for -- root 'li', or child 'a'
	 */ 
	public function getPage( $id, $el = 'li' ) {

		$id = $this->leaf_id_prefix . $id;

		$xpath = sprintf(self::GET_PAGE_XPATH,$id);

		if( 'a' == $el )
			$xpath .= '/a';

 		return $this->webdriver->findElementBy( LocatorStrategy::xpath, $xpath );

	}

	/**
	 * @todo option to expand all before running this
	 */ 
	public function getSections() {
 		
 		return $this->webdriver->findElementsBy( LocatorStrategy::cssSelector, '#navman_container li[rel="folder"]');

	}

	public function selectPage( $id ) {

		$id = $this->leaf_id_prefix . $id;

		$xpath = sprintf(self::GET_PAGE_XPATH . '/a' ,$id);

 		try {
 			$page = $this->webdriver->findElementBy( LocatorStrategy::xpath, $xpath );
 			$page->click();
 			return true;
 		} catch( NoSuchElementException $e ) {
 			return false;
 		}

	}

	public function editPage( $id ) {

		$this->selectPage( $id );
		$btn = $this->webdriver->findElementBy( LocatorStrategy::id, self::NAVMAN_EDIT_BTN );
		$btn->click();
		sleep(2);

	}

	public function deletePage( $id ) {

		$this->selectPage( $id );
		$btn = $this->webdriver->findElementBy( LocatorStrategy::id, self::NAVMAN_DELETE_BTN );
		$btn->click();
		sleep(1);

	}

	const OPEN_SECTION_ICON_XPATH = '//li[@id="%s"]/ins[@class="jstree-icon"]';

	public function openSection( $id ) {
		$id = $this->leaf_id_prefix . $id;

		$xpath = sprintf( self::OPEN_SECTION_ICON_XPATH, $id );
		$el = $this->webdriver->findElementBy( LocatorStrategy::xpath, $xpath );

		$el->click();

		sleep(1);

	}

	const LEAF_XPATH = '//li[@id="%s"]/a';
	const MOVE_BEFORE_Y_OFFSET = 2;
	const MOVE_AFTER_Y_OFFSET = 5;

	/**
	 * Move a page using the jstree drag and drop interface
	 * 
	 * @todo this isn't working correctly, perhaps due to issues with webdrivers moveto method
	 */ 
	public function movePage( $src_id, $dest_id, $location = 'inside' ) {

 		$src_xpath = sprintf( self::LEAF_XPATH, $this->leaf_id_prefix . $src_id );
 		$dest_xpath = sprintf( self::LEAF_XPATH, $this->leaf_id_prefix . $dest_id );
 		
 		// Find source and destination pages
		$src = $this->webdriver->findElementBy( LocatorStrategy::xpath, $src_xpath );
		// $dest = $this->webdriver->findElementBy( LocatorStrategy::id, $this->leaf_id_prefix . $dest_id );
		$dest = $this->webdriver->findElementBy( LocatorStrategy::xpath, $dest_xpath );

		// Store size for use calculating offsets
		$dest_size = $dest->getSize();
		// var_dump('Destination size: ' . print_r($dest_size,true) );

		// Calculate offset based on desired movement location
		$y_offset = 0;
		$x_offset = intval($dest_size->width) / 2;

		switch( $location ) {
			case 'before':
				// Relative to top-left of destination element
				$y_offset = self::MOVE_BEFORE_Y_OFFSET;
				break;

			case 'after':
				// Relative to top-left of destination element
				$y_offset = intval($dest_size->height) + self::MOVE_AFTER_Y_OFFSET;
				break;

			case 'inside': default:
				$y_offset = intval($dest_size->height) / 2;
		}

		// Select source page
		$src->click();

		// var_dump('Move location: ' . $location );
		// var_dump('X offset: ' . $x_offset );
		// var_dump('Y offset: ' . $y_offset );

		// $file_before = "selenium/screenshots/{$src_id}_{$location}_{$dest_id}-1.png";
		// $file_after = "selenium/screenshots/{$src_id}_{$location}_{$dest_id}-2.png";

		// moveTo, buttownDown and buttonUp are not implemented in php-webdriver
		// we have added them in to ours
		$this->webdriver->moveTo( $src );
		$this->webdriver->buttonDown();
		$this->webdriver->moveTo( $dest, $x_offset, $y_offset );
		// $this->webdriver->getScreenshotAndSaveToFile( $file_before );
		$this->webdriver->buttonUp();
		// $this->webdriver->getScreenshotAndSaveToFile( $file_after );

	}

	public function expandAll() {

		$btn = $this->webdriver->findElementBy( LocatorStrategy::id, self::NAVMAN_EXPAND_BTN );
		$btn->click();
		sleep(1);

	}

	public function collapseAll() {

		$btn = $this->webdriver->findElementBy( LocatorStrategy::id, self::NAVMAN_COLLAPSE_BTN );
		$btn->click();
		sleep(1);
	}

	public function save() {

		$button = $this->webdriver->findElementBy( LocatorStrategy::id, self::NAVMAN_SAVE_BTN );
		$button->click();
		sleep(1);

	}

	/* Assertions */

	const NAVMAN_SAVE_NOTIFICATION_XPATH = '//div[@id="message"]/p';

	public function assertChangesWereSaved() {

		$msg = $this->webdriver->findElementBy( LocatorStrategy::xpath, self::NAVMAN_SAVE_NOTIFICATION_XPATH );
		$this->webdriver->assertEquals( $msg->getText(), 'Your navigation changes were saved.' );

	}

}