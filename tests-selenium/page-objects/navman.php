<?php

/**
 * Edit Order page (Edit Order + Post Type)
 */
class BUN_Navman_Page {

	protected $webdriver = null;

	/* URL's */
	const NAVMAN_BASE_URL = '/wp-admin/edit.php?page=bu-navigation-manager';
	const NAVMAN_PAGE_HEADER_XPATH = "//h2[contains(text(),'Page Order')]";

	/* Markup constants */

	// Form elements
	const NAVMAN_FORM = 'navman_form';

	// Toolbar
	const NAVMAN_SAVE_BTN = 'bu_navman_save';
	const NAVMAN_EXPAND_BTN = 'navman_expand_all';
	const NAVMAN_COLLAPSE_BTN = 'navman_collapse_all';

	// Links
	const ADD_LINK_ANCHOR = 'navman_add_link';
	const EDIT_LINK_ADDRESS_INPUT = 'editlink_address';
	const EDIT_LINK_LABEL_INPUT = 'editlink_label';
	const EDIT_LINK_TARGET_RADIO = 'editlink_target';
	const EDIT_LINK_BUTTON_OK = '//div[@class="ui-dialog-buttonset"]//span[contains(text(),"Ok")]/..';
	const EDIT_LINK_BUTTON_CANCEL = '//div[@class="ui-dialog-buttonset"]//span[contains(text(),"Cancel")]/..';
	const NEW_LINK_XPATH = '//li[@rel="link" and contains(@id, "post-new-%s")]';

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
	const TYPES_SECTION = 'section';
	const TYPES_DENIED = 'denied';

	// HTML ID prefix for jstree LIs
	const LEAF_ID_PREFIX = 'nm';

	// Tree operations
	const GET_POST_BASE_XPATH = '//li[@id="%s"]';
	const GET_POST_ANCHOR_XPATH = '//li[@id="%s"]/a';
	const OPEN_CONTEXT_MENU_XPATH = '//li[@id="%s"]//button[@class="edit-options"]';
	const CONTEXT_MENU_ITEM = '//div[@id="vakata-contextmenu"]//a[@rel="%s"]';
	const OPEN_SECTION_ICON_XPATH = '//li[@id="%s"]/ins[@class="jstree-icon"]';

	// Notices
	const NAVMAN_NOTICE_UPDATED_XPATH = '//div[@id="navman-notices"]//div[contains(@class,"updated")]/p';
	const NAVMAN_NOTICE_ERRORS_XPATH = '//div[@id="navman-notices"]//div[contains(@class,"updated")]/p';

	// Move page constants
	const JSTREE_LI_HEIGHT = 50;
	const MOVE_POST_BEFORE_Y_OFFSET = 2;
	const MOVE_POST_AFTER_Y_OFFSET = -2;

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
				$xpath = sprintf(self::GET_POST_ANCHOR_XPATH,$id);
				break;
			case 'li': default:
				$xpath = sprintf(self::GET_POST_BASE_XPATH,$id);
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

 		return $this->webdriver->getElement( LocatorStrategy::cssSelector, '#nav-tree-container li[class*="jstree-closed"], #nav-tree-container li[class*="jstree-open"]');

	}

	/**
	 * Select a page by ID in the nav tree
	 */
	public function selectPage( $id ) {

		$id = self::LEAF_ID_PREFIX . $id;

		$xpath = sprintf( self::GET_POST_ANCHOR_XPATH, $id );
		$page = $this->webdriver->getElement( LocatorStrategy::xpath, $xpath );

		$page->click();

	}

	/**
	 * Expand a section in the nav tree by parent ID
	 */
	public function openSection( $id ) {
		$id = self::LEAF_ID_PREFIX . $id;

		$xpath = sprintf( self::OPEN_SECTION_ICON_XPATH, $id );
		$el = $this->webdriver->getElement( LocatorStrategy::xpath, $xpath );

		$el->click();

		sleep(2);

	}

	/* Options menu */

	/**
	 * Open the contextual menu for the given post ID
	 */
	public function openOptionsMenu( $id ) {

		$this->selectPage( $id );

		$id = self::LEAF_ID_PREFIX . $id;

		$xpath = sprintf( self::OPEN_CONTEXT_MENU_XPATH, $id );
		$button = $this->webdriver->getElement( LocatorStrategy::xpath, $xpath );
		$button->click();

		sleep(1);

	}

	/**
	 * Trigger the "Edit" menu item for the given post ID
	 */
	public function editPost( $id ) {

		$this->openOptionsMenu( $id );

		$edit_xpath = sprintf( self::CONTEXT_MENU_ITEM, 'edit' );
		$btn = $this->webdriver->getElement( LocatorStrategy::xpath, $edit_xpath );
		$btn->click();

		sleep(2);

	}

	/**
	 * Trigger the "Move to Trash" menu item for the given post ID
	 */
	public function movePostToTrash( $id ) {

		$this->openOptionsMenu( $id );

		$remove_xpath = sprintf( self::CONTEXT_MENU_ITEM, 'remove' );
		$btn = $this->webdriver->getElement( LocatorStrategy::xpath, $remove_xpath );
		$btn->click();

		sleep(2);

	}

	/* Links */

	/**
	 * Add a new link
	 */
	public function addLink( $data, $nextTo = null ) {
		$defaults = array(
			'url' => '',
			'label' => '(new link)',
			'target' => ''
			);
		$data = wp_parse_args( $data, $defaults );

		// Open modal
		$add_link_anchor = $this->webdriver->getElement( LocatorStrategy::id, self::ADD_LINK_ANCHOR );
		$add_link_anchor->click();

		// Newly created links do not get a proper post ID until after changes are saved.
		// However, all newly created links get a class attribute of newlink_#, where #
		// is the current count of unsaved links.  We can use this as an ID to look up
		// this link later, so store it now and return it after the link is created.
		$id = self::get_next_new_link_id();

		// Locate form elements
		$address_field = $this->webdriver->getElement( LocatorStrategy::id, self::EDIT_LINK_ADDRESS_INPUT );
		$label_field = $this->webdriver->getElement( LocatorStrategy::id, self::EDIT_LINK_LABEL_INPUT );
		$add_btn = $this->webdriver->getElement( LocatorStrategy::xpath, self::EDIT_LINK_BUTTON_OK );

		$target_selector = sprintf('input[name="%s"][value="%s"]', self::EDIT_LINK_TARGET_RADIO, $data['target'] );
		$target_radio = $this->webdriver->getElement( LocatorStrategy::cssSelector, $target_selector );

		// Fill out form
		$address_field->sendKeys( array( $data['url'] ) );
		$label_field->sendKeys( array( $data['label'] ) );
		$target_radio->click();

		// Add link
		$add_btn->click();

		return $id;

	}

	public function editLink( $id, $data, $nextTo = null ) {

		// Open modal via "Options" menu edit link
		$this->openOptionsMenu( $id );
		$this->editPost( $id );

		$defaults = array(
			'url' => '',
			'label' => '(new link)',
			'target' => ''
			);
		$data = wp_parse_args( $data, $defaults );

		// Locate form elements
		$address_field = $this->webdriver->getElement( LocatorStrategy::id, self::EDIT_LINK_ADDRESS_INPUT );
		$label_field = $this->webdriver->getElement( LocatorStrategy::id, self::EDIT_LINK_LABEL_INPUT );
		$add_btn = $this->webdriver->getElement( LocatorStrategy::xpath, self::EDIT_LINK_BUTTON_OK );

		$target_selector = sprintf('input[name="%s"][value="%s"]', self::EDIT_LINK_TARGET_RADIO, $data['target'] );
		$target_radio = $this->webdriver->getElement( LocatorStrategy::cssSelector, $target_selector );

		// Fill out form
		$address_field->clear();
		$label_field->clear();

		$address_field->sendKeys( array( $data['url'] ) );
		$label_field->sendKeys( array( $data['label'] ) );
		$target_radio->click();

		// Add link
		$add_btn->click();

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

	/**
	 * Move a page using the jstree drag and drop interface
	 *
	 * @todo
	 *	- this isn't working correctly, perhaps due to issues with webdrivers moveto method
	 *	- look at other php webdriver bindings
	 */
	public function movePost( $src_id, $dest_id, $location = 'inside' ) {

 		$src_xpath = sprintf( self::GET_POST_ANCHOR_XPATH, self::LEAF_ID_PREFIX . $src_id );
 		$dest_xpath = sprintf( self::GET_POST_ANCHOR_XPATH, self::LEAF_ID_PREFIX . $dest_id );

 		// Find source and destination pages
		$src = $this->webdriver->getElement( LocatorStrategy::xpath, $src_xpath );
		$dest = $this->webdriver->getElement( LocatorStrategy::xpath, $dest_xpath );

		// Calculate offset based on desired movement location
		$y_offset = 0;

		switch( $location ) {
			case 'before':
				// Relative to top-left of destination element
				$y_offset = self::MOVE_POST_BEFORE_Y_OFFSET;
				break;

			case 'after':
				// Relative to top-left of destination element
				$y_offset = self::JSTREE_LI_HEIGHT + self::MOVE_POST_AFTER_Y_OFFSET;
				break;

			case 'inside': default:
				$y_offset = self::JSTREE_LI_HEIGHT / 2;
		}

		// Select source page
		$src->click();

		// var_dump('Move location: ' . $location );
		// var_dump('Y offset: ' . $y_offset );

		// moveTo, buttownDown and buttonUp are not implemented in php-webdriver
		// we have added them in to ours
		// @todo this works in theory, but the second moveTo doesn't appear to be working

		$this->webdriver->moveTo( $src );
		$this->webdriver->buttonDown();
		$this->webdriver->moveTo( $dest, null, round($y_offset) );
		$this->webdriver->buttonUp();

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

	public function assertPostNotExists( $id ) {
		try {
			$xpath = sprintf( self::GET_POST_BASE_XPATH, $id );
			$this->webdriver->findElementBy( LocatorStrategy::xpath, $xpath );
		} catch( NoSuchElementException $e ) {
			return true;
		}

		return false;
	}

	public function assertMovedInside( $src_id, $dest_id ) {

		$src_id = self::LEAF_ID_PREFIX . $src_id;
		$dest_id = self::LEAF_ID_PREFIX . $dest_id;

		$selector = sprintf("#%s > ul > #%s", $dest_id, $src_id );
		$this->webdriver->getElement( LocatorStrategy::cssSelector, $selector );

	}

	public function assertMovedBefore( $src_id, $dest_id ) {

		$src_id = self::LEAF_ID_PREFIX . $src_id;
		$dest_id = self::LEAF_ID_PREFIX . $dest_id;

		$selector = sprintf("#%s + #%s", $src_id, $dest_id );
		$this->webdriver->getElement( LocatorStrategy::cssSelector, $selector );

	}

	public function assertMovedAfter( $src_id, $dest_id ) {

		$src_id = self::LEAF_ID_PREFIX . $src_id;
		$dest_id = self::LEAF_ID_PREFIX . $dest_id;

		$selector = sprintf("#%s + #%s", $dest_id, $src_id );
		$this->webdriver->getElement( LocatorStrategy::cssSelector, $selector );

	}

	public function assertLinkExistsWithLabel( $label ) {

		$xpath = sprintf('//li[@rel="link"]//span[@class="title" and contains(text(),"%s")]', $label );
		$this->webdriver->getElement( LocatorStrategy::xpath, $xpath );

	}

	public function assertNewLinkExists( $id ) {

		$xpath = sprintf(self::NEW_LINK_XPATH,$id);
		$this->webdriver->getElement( LocatorStrategy::xpath, $xpath );

	}

	public function assertExistingLinkExists( $id ) {

		$xpath = sprintf(self::GET_POST_BASE_XPATH, self::LEAF_ID_PREFIX . $id );
		$this->webdriver->getElement( LocatorStrategy::xpath, $xpath );

	}

	public function assertChangesWereSaved() {

		$msg = $this->webdriver->getElement( LocatorStrategy::xpath, self::NAVMAN_NOTICE_UPDATED_XPATH );
		$this->webdriver->assertEquals( 'Your navigation changes have been saved', $msg->getText() );

	}

}
