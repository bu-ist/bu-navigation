<?php

/**
 * Edit Order page (Edit Order + Post Type)
 */ 
class BUN_Settings_Page {

	/* URL's */
	const URL = '/wp-admin/edit.php?page=bu-navigation/bu-navman.php';
	const PAGE_HEADING_XPATH = "//h2[contains(text(),'Edit Navigation')]";

	/**
	 * Load the navigation settings page
	 */ 
	function __construct( $webdriver ) {

		$this->webdriver = $webdriver;

		// Generate request URL
		$this->webdriver->open( self::URL  );

		try {
			$this->webdriver->findElementBy( LocatorStrategy::xpath, self::PAGE_HEADING_XPATH );
		} catch( NoSuchElementException $e ) {
			throw new Exception('BU Navigation Settings page failed to load with URL: ' . self::URL );
		}

	}

}