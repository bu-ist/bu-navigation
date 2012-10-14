<?php

/**
 * Edit Order page (Edit Order + Post Type)
 */ 
class BUN_Navman_Page {

	protected $webdriver = null;

	/* URL's */
	const NAVMAN_BASE_URL = '/wp-admin/edit.php?page=bu-navigation-manager';
	const NAVMAN_PAGE_HEADER_XPATH = "//h2[contains(text(),'Edit Navigation')]";

	/* Markup constants */

	// Form elements
	const NAVMAN_FORM = 'navman_form';

	const NAVMAN_EDIT_BTN = 'bu_navman_edit';
	const NAVMAN_DELETE_BTN = 'bu_navman_delete';
	const NAVMAN_SAVE_BTN = 'bu_navman_save';
	const NAVMAN_EXPAND_BTN = 'navman_expand_all';
	const NAVMAN_COLLAPSE_BTN = 'navman_collapse_all';

	const ADD_LINK_ADDRESS_INPUT = 'addlink_address';
	const ADD_LINK_LABEL_INPUT = 'addlink_label';
	const ADD_LINK_TARGET_RADIO = 'addlink_target';
	const ADD_LINK_BUTTON = 'addlink_add';

	// Tree states (li class attributes)
	const JSTREE_LEAF = 'jstree-leaf';
	const JSTREE_CLOSED = 'jstree-closed';
	const JSTREE_OPEN = 'jstree-open';
	const JSTREE_LAST = 'jstree-last';
	const JSTREE_HOVERED = 'jstree-hovered';
	const JSTREE_CLICKED = 'jstree-clicked';

	// Tree types (li rel attribute)
	const TYPES_PAGE = 'page';
	const TYPES_LINK = 'link';
	const TYPES_LINK = 'section';

	// HTML ID prefix for jstree lis
	const LEAF_ID_PREFIX = 'p';

	// Xpath locator templates
	const GET_PAGE_BASE_XPATH = '//li[@id="%s"]';
	const GET_PAGE_ANCHOR_XPATH = '//li[@id="%s"]/a';
	const OPEN_SECTION_ICON_XPATH = '//li[@id="%s"]/ins[@class="jstree-icon"]';
	const NAVMAN_SAVE_NOTIFICATION_XPATH = '//div[@id="message"]/p';
	const NEW_LINK_XPATH = '//li[@rel="link" and contains(@id, "post-new-%s")]';

	// Move page constants
	const MOVE_PAGE_BEFORE_Y_OFFSET = 2;
	const MOVE_PAGE_AFTER_Y_OFFSET = 5;

	/**
	 * Load the Navman page
	 */ 
	function __construct( $webdriver, $post_type = 'page' ) {

		$this->webdriver = $webdriver;

		// Generate request URL
		$request_url = self::NAVMAN_BASE_URL . '&post_type=' . $post_type;
		$this->webdriver->open( $request_url  );

		try {
			$this->webdriver->findElementBy( LocatorStrategy::xpath, self::NAVMAN_PAGE_HEADER_XPATH );
		} catch( NoSuchElementException $e ) {
			throw new Exception('BU Navigation Edit Order page failed to load with URL: ' . $request_url );
		}

	}

	/* Actions */

	/**
	 * Return either the list item or clickable anchor for the given page ID
	 * 
	 * @param $id int Post ID to look for
	 * @param $el string which markup component to look for -- root 'li', or child 'a'
	 */ 
	public function getPage( $id, $el = 'li' ) {

		$id = self::LEAF_ID_PREFIX . $id;
		$xpath = '';

		switch( $el ) {
			case 'a':
				$xpath = sprintf(self::GET_PAGE_ANCHOR_XPATH,$id);
				break;
			case 'li': default:
				$xpath = sprintf(self::GET_PAGE_BASE_XPATH,$id);
				break;
		}

 		return $this->webdriver->getElement( LocatorStrategy::xpath, $xpath );

	}

	/**
	 * Gets all non-leaf nodes (sections)
	 */ 
	public function getSections( $expand_first = false ) {
 		
 		if( $expand_first )
 			$this->expandAll();

 		return $this->webdriver->getElement( LocatorStrategy::cssSelector, '#navman_container li[class*="jstree-closed"], #navman_container li[class*="jstree-open"]');

	}

	public function selectPage( $id ) {

		$id = self::LEAF_ID_PREFIX . $id;

		$xpath = sprintf(self::GET_PAGE_ANCHOR_XPATH, $id);
		$page = $this->webdriver->getElement( LocatorStrategy::xpath, $xpath );

		$page->click();

	}

	public function editPage( $id ) {

		$this->selectPage( $id );
		$btn = $this->webdriver->getElement( LocatorStrategy::id, self::NAVMAN_EDIT_BTN );
		$btn->click();
		sleep(2);

	}

	public function deletePage( $id ) {

		$this->selectPage( $id );
		$btn = $this->webdriver->getElement( LocatorStrategy::id, self::NAVMAN_DELETE_BTN );
		$btn->click();
		sleep(1);

	}

	/* Links */

	public function addLink( $label, $url, $target = '' ) {

		// Newly created links do not get a proper post ID until after changes are saved.
		// However, all newly created links get a class attribute of newlink_#, where #
		// is the current count of unsaved links.  We can use this as an ID to look up
		// this link later, so store it now and return it after the link is created.
		$id = self::get_next_new_link_id();

		// Locate form elements
		$address_field = $this->webdriver->getElement( LocatorStrategy::id, self::ADD_LINK_ADDRESS_INPUT );
		$label_field = $this->webdriver->getElement( LocatorStrategy::id, self::ADD_LINK_LABEL_INPUT );
		$add_btn = $this->webdriver->getElement( LocatorStrategy::id, self::ADD_LINK_BUTTON );

		$target_selector = sprintf('input[name="%s"][value="%s"]', self::ADD_LINK_TARGET_RADIO, $target );
		$target_radio = $this->webdriver->getElement( LocatorStrategy::cssSelector, $target_selector );

		// Fill out form
		$address_field->sendKeys( array( $url ) );
		$label_field->sendKeys( array( $label ) );
		$target_radio->click();

		// Add link
		$add_btn->click();

		return $id;

	}

	/**
	 * Returns what will be the next new link ID -- the count of existing new link items,
	 * or 0 if none currently exist.
	 */ 
	protected function get_next_new_link_id() {

		$xpath = '//li[@rel="link" and contains(@class,"newlink_")]';
		$new_link_count = 0;

		try {
			$newLinks = $this->webdriver->findElementsBy(LocatorStrategy::xpath, $xpath);
			$new_link_count = count($newLinks);
		} catch( NoSuchElementException $e ) {
			return 0;
		}

		return $new_link_count;
	}

	public function openSection( $id ) {
		$id = self::LEAF_ID_PREFIX . $id;

		$xpath = sprintf( self::OPEN_SECTION_ICON_XPATH, $id );
		$el = $this->webdriver->getElement( LocatorStrategy::xpath, $xpath );

		$el->click();

		sleep(1);

	}

	/**
	 * Move a page using the jstree drag and drop interface
	 * 
	 * @todo this isn't working correctly, perhaps due to issues with webdrivers moveto method
	 */ 
	public function movePage( $src_id, $dest_id, $location = 'inside' ) {

 		$src_xpath = sprintf( self::GET_PAGE_ANCHOR_XPATH, self::LEAF_ID_PREFIX . $src_id );
 		$dest_xpath = sprintf( self::GET_PAGE_ANCHOR_XPATH, self::LEAF_ID_PREFIX . $dest_id );
 		
 		// Find source and destination pages
		$src = $this->webdriver->getElement( LocatorStrategy::xpath, $src_xpath );
		// $dest = $this->webdriver->getElement( LocatorStrategy::id, self::LEAF_ID_PREFIX . $dest_id );
		$dest = $this->webdriver->getElement( LocatorStrategy::xpath, $dest_xpath );

		// Store size for use calculating offsets
		$dest_size = $dest->getSize();
		// var_dump('Destination size: ' . print_r($dest_size,true) );

		// Calculate offset based on desired movement location
		$y_offset = 0;
		$x_offset = intval($dest_size->width) / 2;

		switch( $location ) {
			case 'before':
				// Relative to top-left of destination element
				$y_offset = self::MOVE_PAGE_BEFORE_Y_OFFSET;
				break;

			case 'after':
				// Relative to top-left of destination element
				$y_offset = intval($dest_size->height) + self::MOVE_PAGE_AFTER_Y_OFFSET;
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
		$this->webdriver->moveTo( $dest, round($x_offset), round($y_offset) );
		// $this->webdriver->getScreenshotAndSaveToFile( $file_before );
		$this->webdriver->buttonUp();
		// $this->webdriver->getScreenshotAndSaveToFile( $file_after );

	}

	public function expandAll() {

		$btn = $this->webdriver->getElement( LocatorStrategy::id, self::NAVMAN_EXPAND_BTN );
		$btn->click();
		sleep(1);

	}

	public function collapseAll() {

		$btn = $this->webdriver->getElement( LocatorStrategy::id, self::NAVMAN_COLLAPSE_BTN );
		$btn->click();
		sleep(1);
	}

	public function save() {

		$button = $this->webdriver->getElement( LocatorStrategy::id, self::NAVMAN_SAVE_BTN );
		$button->click();
		sleep(1);

	}

	/* Assertions */

	public function assertMovedInside( $src_id, $dest_id ) {

		$selector = sprintf("#p%s > ul > #p%s", $dest_id, $src_id );
		$this->webdriver->getElement( LocatorStrategy::cssSelector, $selector );

	}

	public function assertMovedBefore( $src_id, $dest_id ) {
		
		$selector = sprintf("#p%s + #p%s", $src_id, $dest_id );
		$this->webdriver->getElement( LocatorStrategy::cssSelector, $selector );

	}

	public function assertMovedAfter( $src_id, $dest_id ) {

		$selector = sprintf("#p%s + #p%s", $dest_id, $src_id );
		$this->webdriver->getElement( LocatorStrategy::cssSelector, $selector );

	}

	public function assertLinkExistsWithLabel( $label ) {

		$xpath = sprintf('//li[@rel="link"]/a[contains(text(),"%s")]', $label );
		$this->webdriver->getElement( LocatorStrategy::xpath, $xpath );

	}

	public function assertNewLinkExists( $id ) {

		$xpath = sprintf(self::NEW_LINK_XPATH,$id);
		$this->webdriver->getElement( LocatorStrategy::xpath, $xpath );

	}

	public function assertExistingLinkExists( $id ) {

		$xpath = sprintf(GET_PAGE_BASE_XPATH, self::LEAF_ID_PREFIX . $id );
		$this->webdriver->getElement( LocatorStrategy::xpath, $xpath );

	}

	public function assertChangesWereSaved() {

		$msg = $this->webdriver->getElement( LocatorStrategy::xpath, self::NAVMAN_SAVE_NOTIFICATION_XPATH );
		$this->webdriver->assertEquals( 'Your navigation changes have been saved', $msg->getText() );

	}

}