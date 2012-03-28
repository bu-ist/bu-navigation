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
?>

<script type="text/javascript">
	var page_summary = <?php echo json_encode(BuPageParent::getTree( $post_types ));?>,
		current_parent = <?php echo $post->post_parent;?>,
		post_id = <?php echo $bu_post_id;?>,
		allow_top = <?php echo json_encode($allow_top);?>,
		already_in_nav = <?php echo json_encode( $already_in_nav ); ?>,
		page_on_front = <?php printf('%d', get_option('page_on_front'));?>;
</script>

<div style="display:none;" id="bu-page-parent-help-content">
	<h3>Page Parent</h3>
	<p>Page Parent specifies where this page is located in the site tree (hierarchy) and in navigation menus. To set the page parent, use the hierarchy browser to locate a parent, and click the radio button next to the page title.</p>
	
	<p>Pages with a right-pointing arrow have children. Click the page title or the arrow to drill down into a section. Use the "Back to Top" or "Back One Level" links to move up in the hierarchy. The links under the menu show the path to your current selection as clickable breadcrumbs.</p>
	
	<p>Once you have chosen a page parent, your selection is displayed in the shaded box above the hierarchy menu. You can continue to browse the hierarchy without losing your selection. The page parent selection is remembered for a short period, making it easier for you to specify the same page parent when creating several pages in a section.</p>
	
	<p>Top-level pages do not have a page parent. The option to create a new one may be enabled or disabled on individual sites. If you need to create a top-level page and this option is not available in your hierarchy menu, ask your site administrator.</p>
</div>

<div style="display: none;" id="bu-page-position-help-content">
	<h3>Page Position</h3>
	<p>Page Position specifies the order of this page in relation to its sibling pages (ie, other pages that share the same page parent).  You must specify a page parent before setting the page position.</p>
	
	<p>If you change the parent, you should also specify a new position.</p>
	
	<p>If a page position is not selected, the page will be placed last on the list (in relation to its sibling pages).</p>
</div>

<div style="display: none;" id="bu-navigation-help-content">
	<h3>Navigation</h3>
	<p>Label: Use this to specify an alternate title for this page. This title will be shown in all navigation menus.</p> 
	
	<p>Display This Page: Click the checkbox next to "Display this page in navigation lists" to expose the link to this page in all navigation menus. You must select a page parent in order to display the link in navigation lists.</p>
</div>

<h4><?php _e('Page Parent');?><span id="bu-page-parent-help">&nbsp;</span></h4>
<p>Navigate the site tree below, locate the page parent, click the radio button to set parent.</p>

<div id="bu-page-parent-current">
	<strong>Selection: </strong><span>none</span>
</div>
<div id="bu-page-parent"></div>

<hr class="divider"/>

<div style="display: none;" id="bu-page-position-help-content">
  <h3>Positioning Your Page</h3>
  <p>blah, blah, blah...</p>
</div>

<h4><?php _e('Page Position'); ?><span id="bu-page-position-help">&nbsp;</span></h4>

<div id="bu-page-position" class="page-attributes-section">
  <label for="bu-page-position-menu-order">After setting the page parent (above), use this menu to specify the position of this page within the list of sibling pages.</label>

  <select id="bu-page-position-menu-order" name="menu_order" disabled="disabled">
    <option value="1">Make FIRST item</option>
  </select>
</div>

<hr class="divider"/>

<h4><?php _e('Navigation'); ?><span id="bu-navigation-help">&nbsp;</span></h4>

<div id="bu-page-navigation" class="page-attributes-section">
  <p>
    <label for="bu-page-navigation-label"><strong>Label:</strong> This label will be used instead of the page title in all navigation lists.</label>
    <input id="bu-page-navigation-label" name="nav_label" type="text" size="30" value="<?php echo $nav_label; ?>"/>
  </p>

  <p>
    <input id="bu-page-navigation-display" name="nav_display" type="checkbox" value="yes" <?php if ( $already_in_nav ) { ?>checked="checked"<?php }?>/>
    <label class="inline" for="bu-page-navigation-display"><strong>Display this page in navigation lists.</strong></label>
  </p>
</div>
