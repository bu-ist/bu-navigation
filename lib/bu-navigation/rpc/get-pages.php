<?php

$id = isset($_POST['id']) ? $_POST['id'] : 0;
$post_type = isset($_GET['post_type']) ? $_GET['post_type'] : 'page,link';
$post_types = explode(',', $post_type);

$id = intval(substr($id, 1));

$section_args = array('direction' => 'down', 'depth' => 0, 'sections' => array($id), 'post_types' => $post_types);
$sections = bu_navigation_gather_sections(0, $section_args);

/* remove default page filter and add our own */
remove_filter('bu_navigation_filter_pages', 'bu_navigation_filter_pages_exclude');
add_filter('bu_navigation_filter_pages', 'bu_navman_filter_pages');

$pages = bu_navigation_get_pages(array('sections' => $sections, 'post_types' => $post_types));

/* remove our page filter and add back in the default */
remove_filter('bu_navigation_filter_pages', 'bu_navman_filter_pages');
add_filter('bu_navigation_filter_pages', 'bu_navigation_filter_pages_exclude');

$pages_by_parent = bu_navigation_pages_by_parent($pages);
$pages = bu_navman_get_children($id, $pages_by_parent);

echo json_encode($pages);
?>