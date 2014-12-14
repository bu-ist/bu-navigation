<?php

/**
 * Edit Order page (Edit Order + Post Type)
 */
class BUN_Settings_Page {

	/* URL's */
	const URL = '/wp-admin/themes.php?page=bu-navigation-settings';
	const PAGE_HEADING_XPATH = "//h2[contains(text(),'Primary Navigation')]";

	const FORM_ID = 'bu-nav-primary-settings';
	private $form;
	private $option_fields = array(
		'display' => array( 'name' => 'bu-nav-settings[display]', 'type' => 'checkbox' ),
		'max_items' => array( 'name' => 'bu-nav-settings[max_items]', 'type' => 'text' ),
		'dive' => array( 'name' => 'bu-nav-settings[dive]', 'type' => 'checkbox' ),
		'depth' => array( 'name' => 'bu-nav-settings[depth]', 'type' => 'select' ),
		'allow_top' => array( 'name' => 'bu-nav-settings[allow_top]', 'type' => 'checkbox' ),
		);

	// Xpath templates
	const NOTICE_UPDATED_XPATH = '//div[contains(@class,"updated")]';
	const NOTICE_ERRORS_XPATH = '//div[contains(@class,"error")]';

	/**
	 * Load the navigation settings page
	 */
	function __construct( $webdriver ) {

		$this->webdriver = $webdriver;

		// Generate request URL
		$this->webdriver->url( self::URL  );

		try {
			$this->webdriver->byXpath( self::PAGE_HEADING_XPATH );
		} catch( RuntimeException $e ) {
			throw new Exception('BU Navigation Settings page failed to load with URL: ' . self::URL );
		}

		$this->form = new SeleniumFormHelper( $webdriver, self::FORM_ID );

	}

	public function setOptions( $fields ) {

		$formdata = array();

		foreach( $fields as $key => $val ) {
			$field = $this->option_fields[$key];

			// Format data for form helper
			$formdata[$field['name']] = array( 'type' => $field['type'], 'value' => $val );
		}

		if( ! empty( $formdata ) )
			$this->form->populateFields( $formdata );

	}

	public function getOption( $key ) {

		$field = $this->option_fields[$key];

		return $this->form->getFieldValue( $field['name'] );

	}

	public function save() {

		$this->form->submit();
		$this->assertChangesWereSaved();

		// Reload settings page to make SURE form data has updated
		$this->webdriver->url( self::URL );
		$this->form = new SeleniumFormHelper( $this->webdriver, self::FORM_ID );


	}

	public function save_with_errors() {

		$this->form->submit();
		$this->assertErrorSavingChanges();

		// Reload settings page to make SURE form data has updated
		$this->webdriver->url( self::URL );
		$this->form = new SeleniumFormHelper( $this->webdriver, self::FORM_ID );

	}

	/* Assertions */

	public function assertChangesWereSaved() {

		$notice = $this->webdriver->byXpath( self::NOTICE_UPDATED_XPATH );

	}

	public function assertErrorSavingChanges() {

		$notice = $this->webdriver->byXpath( self::NOTICE_ERRORS_XPATH );

	}

}
