var navman = null;
var navman_dirty = false;

var navman_links = [];
var navman_delete = [];
var navman_edits = {};
var navman_editing = null;

function showPageDetail(data)
{
	jQuery("#navman_pagedetail h3").html(data.post_title);
	jQuery("#navman_drop_target").html(data.post_title);
	jQuery("#navman_drop").attr("rel", data.ID);
	jQuery("#navman_pagedetail").show();
}

function hidePageDetail()
{
	jQuery("#navman_pagedetail").hide();
}

function editNode(n)
{

    var $node = jQuery(n);

	if ($node.attr("rel") === "link")
	{
		if ($node.is('[class*="newlink_"]'))
		{
			/* this is a new link (unsaved) */
			var re = /newlink_(\d+)/;
			var id = re.exec($node.attr("class"))[1];

			if (id)
			{
				var l = navman_links[id];

				jQuery("#editlink_id").attr("value", "newlink_" + id);
				jQuery("#editlink_address").attr("value", l.address);
				jQuery("#editlink_label").attr("value", l.label);

				if (l.target == "new")
				{
					jQuery("#editlink_target_new").attr("checked", "checked");
				}
				else
				{
					jQuery("#editlink_target_same").attr("checked", "checked");
				}

				navman_editing = $node;
				jQuery("#navman_editlink").dialog('open');
			}
		}
		else
		{
			var re = /^p(\d+)/;
			var id = re.exec($node.attr("id"))[1];

			if ((id) && (navman_edits[id]))
			{
				/* existing link with unsaved changes */
				jQuery("#editlink_id").attr("value", navman_edits[id].ID);
				jQuery("#editlink_address").attr("value", navman_edits[id].post_content);
				jQuery("#editlink_label").attr("value", navman_edits[id].post_title);

				if (navman_edits[id].target == "new")
				{
					jQuery("#editlink_target_new").attr("checked", "checked");
				}
				else
				{
					jQuery("#editlink_target_same").attr("checked", "checked");
				}

				navman_editing = $node;
				jQuery("#navman_editlink").dialog('open');
			}
			else
			{
				/* this is an existing link */
				jQuery.getJSON(rpcPageURL, {"id" : $node.attr("id")}, function (data) {
					jQuery("#editlink_id").attr("value", data.ID);
					jQuery("#editlink_address").attr("value", data.post_content);
					jQuery("#editlink_label").attr("value", data.post_title);

					if (data.target == "new")
					{
						jQuery("#editlink_target_new").attr("checked", "checked");
					}
					else
					{
						jQuery("#editlink_target_same").attr("checked", "checked");
					}

					navman_editing = $node;
					jQuery("#navman_editlink").dialog('open');
					});
				}
			}
	}
	else
	{
		var re = /^p(\d+)/;
		var id = re.exec($node.attr("id"))[1];

		var url = "post.php?action=edit&post=" + id;
		window.location = url;
	}
}

jQuery(document).ready( function($)
{
	/* edit link dialog */
	jQuery("#navman_editlink").dialog({
		autoOpen: false,
		buttons: {
			"Ok": function() {

				if (jQuery("#navman_editlink_form").valid())
				{
					if (jQuery("#editlink_id").attr("value").indexOf("newlink_") != -1)
					{
						/* changes to an unsaved link */
						var re = /newlink_(\d+)/;
						var id = re.exec(jQuery("#editlink_id").attr("value"))[1];

						if (id)
						{
							navman_links[id] = {
								"address": jQuery("#editlink_address").attr("value"),
								"label": jQuery("#editlink_label").attr("value"),
								"target": jQuery("input[name='editlink_target']:checked").attr("value")
							};

							$navman.jstree("rename_node", navman_editing, navman_links[id].label);
						}
					}
					else
					{
						/* changes to an existing link */
						var data =
						{
							"ID": jQuery("#editlink_id").attr("value"),
							"post_title": jQuery("#editlink_label").attr("value"),
							"post_content": jQuery("#editlink_address").attr("value"),
							"target": jQuery("input[name='editlink_target']:checked").attr("value")
						};
						navman_edits[data.ID] = data;

						$navman.jstree("rename_node", navman_editing, data.post_title);
					}

					jQuery("#navman_editlink").dialog('close');

					jQuery("#editlink_id").attr("value", "");
					jQuery("#editlink_address").attr("value", "");
					jQuery("#editlink_label").attr("value", "");
					jQuery("#editlink_target_same").attr("checked", "");

					navman_dirty = true;

					navman_editing = null;
				}
			},
			"Cancel": function() {
				jQuery("#navman_editlink").dialog('close');

				jQuery("#editlink_id").attr("value", "");
				jQuery("#editlink_address").attr("value", "");
				jQuery("#editlink_label").attr("value", "");
				jQuery("#editlink_target_same").attr("checked", "");

				navman_editing = null;
			}
		},
		minWidth: 400,
		width: 500,
		modal: true,
		resizable: false
	});

	/* show the inner sidebar */
	jQuery("div.inner-sidebar").show();
	hidePageDetail();

	var options = {
		themes : {
			theme : "classic"
		},
        core : {
            animation : 0,
	    html_titles: true
        },
        plugins : ["themes", "json_data", "ui", "contextmenu", "types", "dnd", "crrm"],
		types : {
			types : {
                "default" : {
                    clickable	: true,
                    renameable	: true,
                    deletable	: true,
                    creatable	: true,
                    draggable	: true,
                    max_children	: -1,
                    max_depth	: -1,
                    valid_children	: "all",

                    icon: {
                        "image": interfacePath + "/icons/page_regular.png"
                    }
                },
                "folder" : {
                    clickable	: true,
                    renameable	: false,
                    deletable	: true,
                    creatable	: true,
                    draggable	: true,
                    max_children	: -1,
                    max_depth	: -1,
                    valid_children	: "all",
                    icon: {
                        image: interfacePath + "/icons/folder_regular.png"
                    }
                },
                "folder_excluded": {
                    clickable	: true,
                    renameable	: false,
                    deletable	: true,
                    creatable	: true,
                    draggable	: true,
                    max_children	: -1,
                    max_depth	: -1,
                    valid_children	: "all",
                    icon: {
                        image: interfacePath + "/icons/folder_hidden.png"
                    }
                },
                "folder_restricted": {
                    clickable	: true,
                    renameable	: false,
                    deletable	: true,
                    creatable	: true,
                    draggable	: true,
                    max_children	: -1,
                    max_depth	: -1,
                    valid_children	: "all",
                    icon: {
                        image: interfacePath + "/icons/folder_lock.png"
                    }
                },
                "folder_excluded_restricted": {
                    clickable	: true,
                    renameable	: false,
                    deletable	: true,
                    creatable	: true,
                    draggable	: true,
                    max_children	: -1,
                    max_depth	: -1,
                    valid_children	: "all",
                    icon: {
                        image: interfacePath + "/icons/folder_hidden_restricted.png"
                    }
                },
                "page": {
                    clickable	: true,
                    renameable	: false,
                    deletable	: true,
                    creatable	: true,
                    draggable	: true,
                    max_children	: -1,
                    max_depth	: -1,
                    valid_children	: "all",
                    icon: {
                        image: interfacePath + "/icons/page_regular.png"
                    }
                },
                "page_excluded": {
                    clickable	: true,
                    renameable	: false,
                    deletable	: true,
                    creatable	: true,
                    draggable	: true,
                    max_children	: -1,
                    max_depth	: -1,
                    valid_children	: "all",
                    icon: {
                        image: interfacePath + "/icons/page_hidden.png"
                    }
                },
                "page_restricted": {
                    clickable	: true,
                    renameable	: false,
                    deletable	: true,
                    creatable	: true,
                    draggable	: true,
                    max_children	: -1,
                    max_depth	: -1,
                    valid_children	: "all",
                    icon: {
                        image: interfacePath + "/icons/page_restricted.png"
                    }
                },
                "page_excluded_restricted": {
                    clickable	: true,
                    renameable	: false,
                    deletable	: true,
                    creatable	: true,
                    draggable	: true,
                    max_children	: -1,
                    max_depth	: -1,
                    valid_children	: "all",
                    icon: {
                        image: interfacePath + "/icons/page_hidden_restricted.png"
                    }
                },
                "link": {
                    icon: {
                        image: interfacePath + "/icons/page_white_link.png"
                    },
                    max_children: 0
                },
                "link_restricted": {
                    icon: {
                        image: interfacePath + "/icons/page_white_link.png"
                    },
                    max_children: 0
                }
            }
		},
		"json_data": {
            "data" : pages,
            "ajax" : {
                url : rpcURL,
                type : "POST",
                data : function (n) {
                    return {id : n.attr ? n.attr("id") : 0}
                }
            },
            "progressive_render" : true
        },
        contextmenu : {
                items : function() {
                    return {
                        "edit" : {
                            "label" : "Edit",
                            "action" : editNode,
                            "icon" : "remove"
                        },
                        "remove" : {
                            "label" : "Remove",
                            "action" : function (obj) {if(this.is_selected(obj)) {this.remove();} else {this.remove(obj);}}
                        }
                    }
                }
        }
	};
    $navman = jQuery("#navman_container");

    $navman.bind("select_node.jstree", function() {
        jQuery("input[name='bu_navman_delete']").removeAttr("disabled");
        jQuery("input[name='bu_navman_edit']").removeAttr("disabled");
    });

    $navman.bind("deselect_node.jstree", function() {
        jQuery("input[name='bu_navman_delete']").attr("disabled", "disabled");
        jQuery("input[name='bu_navman_edit']").attr("disabled", "disabled");
        hidePageDetail();
    });

	$navman.bind("remove.jstree", function(n, prev) {
		var node = jQuery(prev.rslt.obj);

		var re = /^p(\d+)/;
		var id = re.exec(node.attr('id'))[1];

		if (id) {
			navman_delete.push(id);
			navman_dirty = true;
		}
    		console.log(navman_delete);
	});

    $navman.jstree(options);

	/* expand/collapse */
	jQuery("#navman_expand_all").click(function(e) {
		$navman.jstree("open_all");
		e.preventDefault();
		e.stopImmediatePropagation();
	});

	jQuery("#navman_collapse_all").click(function(e) {
		$navman.jstree("close_all");
		e.preventDefault();
		e.stopImmediatePropagation();
	});

	jQuery("#bu_navman_save").click(function(e) {
		navman_dirty = false;
	});

	jQuery("#navman_expand_all_b").click(function(e) {
		$navman.jstree("open_all");
		e.preventDefault();
		e.stopImmediatePropagation();
	});

	jQuery("#navman_collapse_all_b").click(function(e) {
		navman.jstree("close_all");
		e.preventDefault();
		e.stopImmediatePropagation();
	});

	jQuery("#bu_navman_save_b").click(function(e) {
		navman_dirty = false;
	});

	/* handler for adding link */
	jQuery("#addlink_add").click(function (e) {
		if (jQuery("#navman_addlink_form").valid())
		{
			var address = jQuery("#addlink_address").attr("value").replace(/^\s+|\s+$/g,"");
			var label = jQuery("#addlink_label").attr("value").replace(/^\s+|\s+$/g,"");
			var target = jQuery("input[name='addlink_target']:checked").attr("value");

			jQuery("#addlink_address").attr("value", "");
			jQuery("#addlink_label").attr("value", "");

			var className = 'newlink_' + navman_links.length;

			var data = {
				"attr": {"rel": "link", "class": className},
				"data": {"title": label}
			};

			var link = {"address": address, "label": label, "target": target};
			navman_links.push(link);

			$navman.jstree("create", null, "after", data, null, true);
			navman_dirty = true;
		}
	});

	/* delete/rename buttons */
	jQuery("input[name='bu_navman_delete']").attr("disabled", "disabled");
	jQuery("input[name='bu_navman_edit']").attr("disabled", "disabled");

	jQuery("input[name='bu_navman_delete']").click(function (e) {
		$navman.jstree("remove");
	});


	jQuery("input[name='bu_navman_edit']").click(function (e) {
		var n = $navman.jstree("get_selected");
		editNode(n);
	});

	jQuery("#navman_form").submit(function (e) {
		var data = $navman.jstree("get_json", -1);

		jQuery("#navman_data").attr("value", JSON.stringify(data));
		jQuery("#navman_links").attr("value", JSON.stringify(navman_links));
		jQuery("#navman_delete").attr("value", JSON.stringify(navman_delete));
		jQuery("#navman_edits").attr("value", JSON.stringify(navman_edits));
	});
});

window.onbeforeunload = function()
{
	if (navman_dirty)
	{
		return 'You have made changes to your navigation that have not yet been saved.';
	}
	else
	{
		return;
	}
};
