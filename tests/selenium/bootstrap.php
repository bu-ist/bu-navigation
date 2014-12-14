<?php
/**
 * PHPUnit Bootstrap
 *
 * To run these tests:
 * 	1. Install PHPUnit (http://www.phpunit.de)
 *  2. Install the WordPress Unit Testing Framework (http://github.com/bu-ist/wp-unit-tests) and switch to the `selenium` branch
 *  3. Configure wp-tests-config.php, install WordPress and create a clean DB
 *  4. Set the WP_TESTS_DIR environment variable to point at the WP Unit Testing Framework
 *
 * $ cd wp-content/plugins/bu-navigation
 * $ phpunit
 */

$GLOBALS['wp_tests_options'] = array(
	'active_plugins' => array( 'bu-navigation/bu-navigation.php' ),
);

require getenv( 'WP_TESTS_DIR' ) . '/includes/bootstrap.php';
