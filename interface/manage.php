<div class="wrap">
    <h2>Edit Navigation</h2>
	<?php 
	$editing_user = bu_navman_check_lock();

	if ($editing_user) {
		$user_detail = get_userdata($editing_user);
	?>
	<div id="message" class="updated fade">
		<p>Warning: <strong><?php echo $user_detail->display_name; ?></strong> is currently editing this site's navigation.</p>
	</div>
	<?php } ?>

	<?php if ($saved === TRUE) { ?>
		<div id="message" class="updated fade">
			<p>Your navigation changes were saved.</p>
		</div>
	<?php } else if ($saved === FALSE) { ?>
		<div class="error">
			<p><strong>Error:</strong> Errors occurred while saving your navigation changes.</p>
		</div>
	<?php } ?>
	<div class="metabox-holder has-right-sidebar">
		<?php if ($post_type == 'page') { ?>
		<div class="inner-sidebar">
			<div id="navman_addlink" class="postbox">
				<div class="handlediv" title="Click to toggle"><br /></div>
				<h3 class="hndle"><span>Add a Link</span></h3>
				<div class="inside">
					<div class="submitbox navform">
						<form id="navman_addlink_form">
							<div>
								<label for="addlink_address"><sup class="req">*</sup>Link URL</label>
								<input type="text" name="addlink_address" id="addlink_address" class="required url" value="" />
							</div>
							<div>
								<label for="addlink_label"><sup class="req">*</sup>Link Label</label>
								<input type="text" name="addlink_label" id="addlink_label" class="required" value="" />
							</div>
							<div class="radios">
								<input type="radio" name="addlink_target" id="addlink_target_same" value="" checked="checked" />
								<label class="inline" for="addlink_target_same">Open Link in Same Window</label>
							</div>
							<div class="radios">
								<input type="radio" name="addlink_target" id="addlink_target_new" value="new" />
								<label class="inline" for="addlink_target_new">Open Link in New Window</label>
							</div>
							<div class="buttons">
								<input type="button" class="button" id="addlink_add" name="addlink_add" value="Add to Menu" />
							</div>
						</form>
					</div>
				</div>
			</div>			
		</div>
		<?php } ?>

		<div id="navman_main">
			<form method="post" id="navman_form" action="">
				<input type="hidden" id="navman_data" name="navman_data" value="" />
				<input type="hidden" id="navman_links" name="navman_links" value="" />
				<input type="hidden" id="navman_delete" name="navman_delete" value="" />
				<input type="hidden" id="navman_edits" name="navman_edits" value="" />
				
				<p>
					<input class="button button-highlighted" id="bu_navman_save" name="bu_navman_save" type="submit" value="Save Changes" />
				</p>

				<p>
					<a href="#navman_container" id="navman_expand_all">Expand All</a> |
					<a href="#navman_container" id="navman_collapse_all">Collapse All</a> 
				</p>

				<p>
					<input class="button" id="bu_navman_delete" name="bu_navman_delete" type="button" value="Delete Selected" />
					<input class="button" id="bu_navman_edit" name="bu_navman_edit" type="button" value="Edit Selected" />
				</p>

				<div id="navman_container">

				</div>

				<p style="clear: both;">
					<a href="#navman_container" id="navman_expand_all_b">Expand All</a> |
					<a href="#navman_container" id="navman_collapse_all_b">Collapse All</a> 
				</p>

				<p>
					<input class="button button-highlighted" id="bu_navman_save_b" name="bu_navman_save" type="submit" value="Save Changes" />
				</p>
			</form>
		</div>
		
		<?php if ($post_type == 'page') { ?>
		<div id="navman_editlink" title="Edit a Link">
			<div class="submitbox navform navformwide">
				<form id="navman_editlink_form">
					<input type="hidden" name="editlink_id" id="editlink_id" value="" />
					<div>
						<label for="editlink_address"><sup class="req">*</sup>Link URL</label>
						<input size="40" type="text" name="editlink_address" id="editlink_address" class="required url" value="" />
					</div>
					<div>
						<label for="editlink_label"><sup class="req">*</sup>Link Label</label>
						<input size="30" type="text" name="editlink_label" id="editlink_label" class="required" value="" />
					</div>
					<div class="radios">
						<input type="radio" name="editlink_target" id="editlink_target_same" value="" checked="checked" />
						<label class="inline" for="editlink_target_same">Open Link in Same Window</label>
					</div>
					<div class="radios">
						<input type="radio" name="editlink_target" id="editlink_target_new" value="new" />
						<label class="inline" for="editlink_target_new">Open Link in New Window</label>
					</div>
				</form>
			</div>
		</div>
		<?php } ?>
	</div>
</div>
<script type="text/javascript">
//<![CDATA[
/* top-level pages */
var pages = [<?php echo $pages_json; ?>];
var interfacePath = "<?php echo $interface_path; ?>";
var rpcURL = "<?php echo $rpc_url; ?>";
var rpcPageURL = "<?php echo $rpc_page_url; ?>";
//]]>
</script>