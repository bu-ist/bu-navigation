// @todo move these outside of global scope
var navman_dirty = false;

var navman_delete = [];
var navman_edits = {};
var navman_editing = null;

// Check prerequisites
if((typeof bu === 'undefined') || (typeof bu.navigation === 'undefined') || (typeof bu.navigation.tree === 'undefined'))
	throw new TypeError('BU Navigation Manager script dependencies have not been met!');

// @todo only run Navman.init on DOM ready
jQuery(document).ready( function($) {

	// If we are the first view object, set up our namespace
	bu.navigation.views = bu.navigation.views || {};

	var Navman, Linkman, Navtree;

	// @todo implement for consistency with navigation-metabox.js
	// Navman = bu.navigation.views.Navman = {

	// 	el: '#navman_container',

	// 	ui: {
	// 		deleteBtns: '',
	// 		editBtns: '',
	// 		saveBtn: ''
	// 	},

	// 	events: [
	// 		'selector event'
	// 	],

	// 	initialize: function( config ) {

	// 	}

	// };

	// @todo implement for consistency with navigation-metabox.js
	// Linkman = bu.navigation.views.Linkman = {

	// 	el: '#navman_container',

	// 	ui: {
	// 		urlField: '',
	// 		labelField: '',
	// 		targetField: '',
	// 		cancelBtn: '',
	// 		saveBtn: ''
	// 	},

	// 	events: [
	// 		'selector event'
	// 	],

	// 	initialize: function( config ) {

	// 	}

	// };

	// Setup initial state
	$("div.inner-sidebar").show();
	$("input[name='bu_navman_delete']").attr("disabled", "disabled");
	$("input[name='bu_navman_edit']").attr("disabled", "disabled");

	// Create post navigation tree, pass in initial posts from server
	Navtree = bu.navigation.tree('navman', {el: '#navman_container'});

	// Navigation tree listeners

	// @todo this goes away with new ui
	Navtree.listenFor( 'selectPost', function( post ) {
		$("input[name='bu_navman_delete']").removeAttr("disabled");
		$("input[name='bu_navman_edit']").removeAttr("disabled");
	});

	// @todo this goes away with new ui
	Navtree.listenFor( 'deselectPost', function( post ) {
		$("input[name='bu_navman_delete']").attr("disabled", "disabled");
		$("input[name='bu_navman_edit']").attr("disabled", "disabled");
	});

	Navtree.listenFor( 'editPost', function( post ) {
		editPost( post );
	});
	
	Navtree.listenFor( 'removePost', function( post ) {
		var id = post.ID;
		if (id) {
			navman_delete.push(id);
			navman_dirty = true;
		}
	});

	// @todo this goes away with new ui
	$('input[name="bu_navman_edit"]').click(function (e) {
		var post = Navtree.getSelected();
		editPost( post );
	});

	// @todo this goes away with new ui
	$('input[name="bu_navman_delete"]').click(function (e) {
		var post = Navtree.getSelected();
		Navtree.removePost( post );
	});

	// Toolbar event handlers

	// Expand all
	$('#navman_expand_all, #navman_expand_all_b').click(function(e) {
		e.preventDefault();
		e.stopImmediatePropagation();
		Navtree.showAll();
	});

	// Collapse all
	$('#navman_collapse_all, #navman_collapse_all_b').click(function(e) {
		e.preventDefault();
		e.stopImmediatePropagation();
		Navtree.hideAll();
	});

	// Save
	$('#bu_navman_save, #bu_navman_save_b').click(function(e) {
		navman_dirty = false;
	});

	$('#navman_form').submit(function (e) {
		var posts = Navtree.getPosts();
		$("#navman_data").attr("value", JSON.stringify(posts));
		$("#navman_delete").attr("value", JSON.stringify(navman_delete));
		$("#navman_edits").attr("value", JSON.stringify(navman_edits));
	});

	// Edit link dialog
	$('#navman_editlink').dialog({
		autoOpen: false,
		buttons: {
			"Ok": function() {

				if ($("#navman_editlink_form").valid()) {

					// Global link being edited
					var link = navman_editing;
	
					link.content = $("#editlink_address").attr("value");
					link.title = $("#editlink_label").attr("value");
					link.meta.bu_link_target = $("input[name='editlink_target']:checked").attr("value");

					// Editing existing link
					if ( link.status !== 'new' ) {
						navman_edits[link.ID] = link;
					}

					Navtree.updatePost( link );

					// Clear dialog
					$("#navman_editlink").dialog('close');
					$("#editlink_id").attr("value", "");
					$("#editlink_address").attr("value", "");
					$("#editlink_label").attr("value", "");
					$("#editlink_target_same").attr("checked", "");

					navman_dirty = true;
					navman_editing = null;
				}
			},
			"Cancel": function() {
				$("#navman_editlink").dialog('close');
				$("#editlink_id").attr("value", "");
				$("#editlink_address").attr("value", "");
				$("#editlink_label").attr("value", "");
				$("#editlink_target_same").attr("checked", "");

				navman_editing = null;
			}
		},
		minWidth: 400,
		width: 500,
		modal: true,
		resizable: false
	});

	// Add link handler

	$("#addlink_add").click(function (e) {

		if ($("#navman_addlink_form").valid()) {

			var address = $("#addlink_address").attr("value").replace(/^\s+|\s+$/g,"");
			var label = $("#addlink_label").attr("value").replace(/^\s+|\s+$/g,"");
			var target = $("input[name='addlink_target']:checked").attr("value");

			$("#addlink_address").attr("value", "");
			$("#addlink_label").attr("value", "");

			var post = {
				"status": "new",
				"type": "link",
				"content": address,
				"title": label,
				"meta": {
					"bu_link_target": target
				}
			};

			// Insert link
			Navtree.insertPost( post );

			navman_dirty = true;
		}

	});

	function editPost( post ) {

		if( post.type == 'link' ) {

			$("#editlink_id").attr("value", post.ID );
			$("#editlink_address").attr("value", post.content);
			$("#editlink_label").attr("value", post.title);

			if (post.meta.bu_link_target == "new")
			{
				$("#editlink_target_new").attr("checked", "checked");
			}
			else
			{
				$("#editlink_target_same").attr("checked", "checked");
			}

			navman_editing = post;
			$("#navman_editlink").dialog('open');

		} else {

			var url = "post.php?action=edit&post=" + post.ID;
			window.location = url;

		}

	}

});

window.onbeforeunload = function() {
	if (navman_dirty) {
		return 'You have made changes to your navigation that have not yet been saved.';
	}

	return;
};