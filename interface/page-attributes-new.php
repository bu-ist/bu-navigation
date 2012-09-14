<?php

// retrieve previously saved settings for this post (if any)
$nav_meta_data = BuPageParent::get_nav_meta_data($post);	
$nav_label = htmlspecialchars($nav_meta_data['label']);
$nav_exclude = $nav_meta_data['exclude'];

$allow_top = BuPageParent::allowTopLevelPage();		// are top level pages allowed to be displayed in primary nav (option from primary navigation settings)
$new_page = ($post->post_status == 'auto-draft');	// ver 3.x: when you go to create a new post, in the background it is an auto-draft

/*
 * for new page, reset the post id to null
 * because ver 3.x assigns parent to 0 and post_id to auto_increment value)
 */
$bu_post_id = $new_page ? 'null' : $post->ID;

// new pages are not in the nav already, so we need to fix this
$already_in_nav = $new_page ? false : (bool) !$nav_exclude;

$post_types = ( $post->post_type == 'page' ? array('page', 'link') : array($post->post_type) );

$post_types_param = ($post_types ? '&post_type='.implode(',',$post_types) : '');
$rpc_url = 'admin-ajax.php?action=bu_getpages' . $post_types_param;	// used to get all the posts for the tree
$rpc_page_url = 'admin-ajax.php?action=bu_getpage';	// used with links, so it doesn't need post_type
$interface_path = plugins_url('', __FILE__);//str_replace(site_url(), '', plugins_url('interface', __FILE__));

// for display purposes
$pt_labels = BuPageParent::getPostTypeLabels($post->post_type);
$pages = bu_navman_get_page_tree( $post_types );
$ancestors = null;
if( isset( $post->ancestors ) && ! empty( $post->ancestors )) $ancestors = $post->ancestors;

$current_parent = $post->post_parent ? $post->post_parent : '';
$current_parent_txt = $select_parent_txt = '';
$lc_label = strtolower( $pt_labels['singular'] );

if( empty( $current_parent ) ) {
	if( $post->post_status == 'publish' ) {
		$current_parent_txt = 'Current Parent: <span>None (top-level page)</span>';
		$select_parent_txt = "Move $lc_label";
	} else {
		$current_parent_txt = 'No post parent has been set';
		$select_parent_txt = "Move $lc_label";
	}
} else {
	$current_parent_txt = 'Current Parent: <span>' . get_post( $post->post_parent )->post_title . '</span>';
	$select_parent_txt = "Move $lc_label";
}

// section editing (@todo rethink the placement of this functionality)
$is_section_editor = false;

if( class_exists( 'BU_Section_Editing_Plugin' ) ) {
	$is_section_editor = BU_Section_Editing_Plugin::is_allowed_user( get_current_user_id() );
}
?>

<script type="text/javascript">
	var pageTree = <?php echo json_encode( $pages );?>,
		ancestors = <?php echo json_encode( $ancestors ); ?>,
		currentPage = <?php echo $bu_post_id;?>,
		allowTop = <?php echo json_encode($allow_top);?>,
		isSectionEditor = <?php echo json_encode( $is_section_editor ); ?>,
		interfacePath = "<?php echo $interface_path; ?>",
		rpcURL = "<?php echo $rpc_url; ?>";
		rpcPageURL = "<?php echo $rpc_page_url; ?>";
</script>

<div style="display:none;" id="bu-page-parent-help-content">
	<h3><?php echo $pt_labels['singular']; ?> Parent</h3>
	<p><?php echo $pt_labels['singular']; ?> Parent specifies where this <?php echo strtolower($pt_labels['singular']); ?> is located in the site tree (hierarchy) and in navigation menus. To set the <?php echo strtolower($pt_labels['singular']); ?> parent, use the hierarchy browser to locate a parent, and click the radio button next to the <?php echo strtolower($pt_labels['singular']); ?> title.</p>
	
	<p><?php echo $pt_labels['plural']; ?> with a right-pointing arrow have children. Click the <?php echo strtolower($pt_labels['singular']); ?> title or the arrow to drill down into a section. Use the "Back to Top" or "Back One Level" links to move up in the hierarchy. The links under the menu show the path to your current selection as clickable breadcrumbs.</p>
	
	<p>Once you have chosen a <?php echo strtolower($pt_labels['singular']); ?> parent, your selection is displayed in the shaded box above the hierarchy menu. You can continue to browse the hierarchy without losing your selection. The <?php echo strtolower($pt_labels['singular']); ?> parent selection is remembered for a short period, making it easier for you to specify the same <?php echo strtolower($pt_labels['singular']); ?> parent when creating several <?php echo strtolower($pt_labels['plural']); ?> in a section.</p>
	
	<p>Top-level <?php echo strtolower($pt_labels['plural']); ?> do not have a <?php echo strtolower($pt_labels['singular']); ?> parent. The option to create a new one may be enabled or disabled on individual sites. If you need to create a top-level <?php echo strtolower($pt_labels['singular']); ?> and this option is not available in your hierarchy menu, ask your site administrator.</p>
</div>

	<input type="hidden" name="menu_order" value="">

	<h4><?php _e('Position');?><span id="bu-page-parent-help">&nbsp;</span></h4>
	<div id="bu_page_parent_current_label"><?php echo $current_parent_txt; ?></div><br>
	<a id="select-parent" href="#TB_inline?width=640&inlineId=edit_page_parent&width=640&height=982" class="thickbox" ><?php echo $select_parent_txt; ?></a></p>

<div id="edit_page_parent" style="display:none;">
	<input type="hidden" name="tmp_parent_id" value="">
	<input type="hidden" name="tmp_parent_label" value="">
	<input type="hidden" name="tmp_menu_order" value="">

	<h2>Choose <?php _e($lc_label); ?> position</h2>
	<p>Drag the current <?php _e($lc_label); ?> to the desired location.</p>
	<div id="edit_page_tree"></div>
	<div id="bu_page_parent_edit_buttons">
		<input id="bu_page_parent_save" class="button-primary" type="submit" value="Update Position">
		<a href="#" id="bu_page_parent_cancel" class="button">Cancel</a>
	</div>
</div>

<h4><?php _e('Navigation'); ?><span id="bu-navigation-help">&nbsp;</span></h4>

<div id="bu-page-navigation" class="page-attributes-section">
  <p>
    <label for="bu-page-navigation-label"><strong>Label:</strong> This label will be used instead of the <?php echo strtolower($pt_labels['singular']); ?> title in all navigation lists.</label>
    <input id="bu-page-navigation-label" name="nav_label" type="text" size="30" value="<?php echo $nav_label; ?>"/>
  </p>

  <p>
    <input id="bu-page-navigation-display" name="nav_display" type="checkbox" value="yes" <?php if ( $already_in_nav ) { ?>checked="checked"<?php }?>/>
    <label class="inline" for="bu-page-navigation-display"><strong>Display this <?php echo strtolower($pt_labels['singular']); ?> in navigation lists.</strong></label>
  </p>
</div>
