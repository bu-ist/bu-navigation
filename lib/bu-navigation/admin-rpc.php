<?php

/**
 * RPC for getting page lists
 * @return void
 */
function bu_ajax_getpages()
{
	require_once('rpc/get-pages.php');
	die();
}
add_action('wp_ajax_bu_getpages', 'bu_ajax_getpages');

/**
 * RPC for getting a single page detail
 * @return void
 */
function bu_ajax_getpage()
{
	require_once('rpc/get-page.php');
	die();
}
add_action('wp_ajax_bu_getpage', 'bu_ajax_getpage');

?>
