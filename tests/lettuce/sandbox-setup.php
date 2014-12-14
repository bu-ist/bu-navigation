<?php

/**
 * Add a bu-navigation site if this plugin is being tested by lettuce.
 * This sets up for the "User is warned when leaving changes" scenario
 */
function bu_navigation_sandbox_init($response, $coverage, $users)
{
    if (in_array('plugin-bu-navigation', $coverage)) {
        $network = get_current_site();
        $domain = $network->domain;
        $admin_id = get_current_user_id();
        $options = array(
            'users' => array(
                'site_admin' => array($users['site_admin']),
                'contributor' => array($users['contributor']),
            ),
            'network_id' => $network->id,
        );
        
        // Public site, no ACL
        $path = '/bu-navigation/';
        $title = 'BU Navigation';
        $site_id = bu_create_site($domain, $path, $title, $admin_id, $options);
        bu_navigation_sandbox_setup_site($response, $site_id, 'bu-navigation');
    }
}
if( defined( 'BU_TS_FILTER_SETUP' ) ) add_action(BU_TS_FILTER_SETUP, 'bu_navigation_sandbox_init', 10, 3);

/**
 * Adds some pages to the site and then adds the site to the response
 */
function bu_navigation_sandbox_setup_site($response, $site_id, $site_key)
{
    switch_to_blog($site_id);
    
    // Generate pages for public site
    $posts = array();
    $page = array(
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_content' => 'Lorem ipsum',
        'post_title' => 'Page 1'
    );
    $posts['page_1'] = wp_insert_post($page);
    $page['post_title'] = 'Page 2';
    $posts['page_2'] = wp_insert_post($page);
    $page['post_title'] = 'Page 3';
    $posts['page_3'] = wp_insert_post($page);

    foreach ($posts as $key => $page_id) {
        if (!$page_id || is_wp_error($page_id)) {
            throw new BU_Test_Support_Exception('Unable to create post: ' . $key);
        }
    }

    $response->addSite($site_id, $site_key);
    foreach ($posts as $key => $post_id) {
        $response->addPost($post_id, $key, $site_key);
    }

    restore_current_blog();
}

