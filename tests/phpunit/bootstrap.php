<?php
/**
 * PHPUnit Bootstrap
 *
 * To run these tests:
 * 	1. Install Composer (https://getcomposer.org/)
 *  2. Install plugin development dependencies (`composer install`)
 *  3. Install WordPress unit tests library (`bin/install-wp-tests.sh`)
 *  4. Run `./vendor/bin/phpunit` from the root directory of this plugin
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

// Load plugin files from this directory manually (as mu-plugin)
require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
	require dirname( __FILE__ ) . '/../../bu-navigation.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Import WordPress unit test bootstrap
require $_tests_dir . '/includes/bootstrap.php';

// Custom WP_UnitTestCase extension
require dirname( __FILE__ ) . '/nav-testcase.php';
