<?php
/* headers for preventing cache */

header('Pragma: no-cache');
header('Cache-control: no-cache, no-store');
header(sprintf("Expires: %s GMT", gmdate('D, d M Y H:i:s')));
header(sprintf("Last-Modified: %s GMT", gmdate('D, d M Y H:i:s')));

/* get page detail and send as JSON */

$id = intval(substr($_GET['id'], 1));

$response = get_page($id);

$response->target = get_post_meta($id, 'bu_link_target', TRUE);
if($response->post_type == 'link'){
	$response->url = $response->post_content;
} else {
	$response->url = get_permalink($id);
}

echo json_encode($response);
?>