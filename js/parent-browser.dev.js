jQuery(document).ready( function($) {

	if (typeof pageTree === 'undefined') {
		return;
	}

	var bu_navtree_rollback = null;

	var section = [];

	if( ancestors ) {
		var i = ancestors.length - 1;

		for(; i >= 0; i-- ) {
			section.push( 'p' + ancestors[i] );
		}
	}

	var selection = [ 'p' + currentPage ];

	// Configure jsTree
	var options = {
		themes : {
			theme : "classic"
		},
		core : {
			animation : 0,
			html_titles: true,
			initially_open: section
		},
		ui : {
			initially_select: selection
		},
		plugins : ["themes", "json_data", "ui", "types", "dnd", "crrm"],
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
				"folder_excluded_denied": {
					clickable	: false,
					renameable	: false,
					deletable	: false,
					creatable	: false,
					draggable	: false,
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
				"folder_denied": {
					clickable	: false,
					renameable	: false,
					deletable	: false,
					creatable	: false,
					draggable	: false,
					max_children	: -1,
					max_depth	: -1,
					valid_children	: "all",
					icon: {
						image: interfacePath + "/icons/folder_regular.png"
					}
				},
				"folder_restricted_denied": {
					clickable	: false,
					renameable	: false,
					deletable	: false,
					creatable	: false,
					draggable	: false,
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
				"folder_excluded_restricted_denied": {
					clickable	: false,
					renameable	: false,
					deletable	: false,
					creatable	: false,
					draggable	: false,
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
				"page_denied": {
					clickable	: false,
					renameable	: false,
					deletable	: false,
					creatable	: false,
					draggable	: false,
					max_children	: -1,
					max_depth	: -1,
					valid_children	: "all",
					icon: {
						image: interfacePath + "/icons/page_regular.png"
					}
				},
				"page_excluded_denied": {
					clickable	: false,
					renameable	: false,
					deletable	: false,
					creatable	: false,
					draggable	: false,
					max_children	: -1,
					max_depth	: -1,
					valid_children	: "all",
					icon: {
						image: interfacePath + "/icons/page_hidden.png"
					}
				},
				"page_restricted_denied": {
					clickable	: false,
					renameable	: false,
					deletable	: false,
					creatable	: false,
					draggable	: false,
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
				"page_excluded_restricted_denied": {
					clickable	: false,
					renameable	: false,
					deletable	: false,
					creatable	: false,
					draggable	: false,
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
			"data" : pageTree,
			"ajax" : {
				url : rpcURL,
				type : "POST",
				data : function (n) {
					return {id : n.attr ? n.attr("id") : 0}
				}
			},
			"progressive_render" : true
		},
		"crrm" : {
			   "move" : {
				"check_move" : function (m) {
					
					var current_node_id = 'p' + currentPage;
					var attempted_parent_id = m.np.attr('id');

					// Can ONLY move current post being edited
					if( current_node_id != m.o.attr( 'id' ) ) {
						console.log('No moving allowed -- illegal selection!');
						return false;
					}

                    // Don't allow top level pages for section editors or of global option prohibits it
                    if( m.cr == -1 && ( isSectionEditor || ! allowTop ) ) {
                        console.log('Move denied, top level pages cannot be created!');
                        return false;
                    }

                    // Section editor specific
                    if( isSectionEditor ) {

                        // Can't move a denied post
                        if( m.o.hasClass( 'denied' ) ) {
                            console.log('Cannot move a denied post!');
                            return false;
                        }

                        // Can't move inside denied post
                        if( m.np.hasClass( 'denied' ) ) {
                            console.log('Move denied, destination parent invalid');
                            return false;
                        }

                    }

                    return true;
				},
			}
		}
	};

	$('#edit_page_tree')
		.jstree( options )
		.bind( "before.jstree", function( event, data ) {

			var current_node_id = '#p' + currentPage;
			var current_node = $(current_node_id);

			console.log(data.func);

			// Override default behavior for specific functions
			switch( data.func ) {

				case "reopen":
					console.log(data);
					
					if( bu_navtree_rollback == null ) {
						bu_navtree_rollback = data.inst.get_rollback();
						data.inst.select_node( '#p' + currentPage );
					}
					break;

				case "select_node":

					// The argument that contains the node being selected is inconsistent.
					// The following values occur:
						// 1. String of the HTML ID attribute of the list item -- "#<post-id>" (happens with initially_select)
						// 2. HTMLElement of the anchor (happens on manual selection)
						// 3. jQuery object of the selected list item (happens on node closing)

					if( typeof data.args[0] == 'string' ) {
						if( current_node_id != data.args[0] ) {
							return false;
						}
					}

					if( data.args[0] instanceof HTMLElement ) {
						if( current_node.attr('id') != $(data.args[0]).parent('li').attr('id') ) {
							return false;
						}
					}

					if( data.args[0] instanceof jQuery ) {
						if( current_node.attr('id') != data.args[0].attr('id') ) {
							return false;
						}
					}

					// Allow selection otherwise
					break;

				case "deselect_node":
					// Don't allow de-selection of post being edited
					if( current_node.attr('id') == $(data.args[0]).parent('li').attr('id') ) {
						return false;
					}

					// Allow deselection otherwise
					break;

				case "hover_node":
				case "start_drag":
					var $node = $(data.args[0]);

					// Can only hover on current node
					if( current_node.attr('id') != $node.parent('li').attr('id') )
						return false;

					break;

			}

		})	// Run on successful movde
		.bind('move_node.jstree', function( event, data ){

			var $new_parent = data.rslt.np;
			var menu_order = data.rslt.o.index() + 1;

			var parent_id = $new_parent.attr('id').substr(1);
			parent_id = ( parent_id > 0 ) ? parent_id : 0;

			var parent_label = data.inst.get_text( $new_parent );

			// @todo Correct parent icons for old and new parent if necessary
			// (manage.dev.js does not do this...)

			// Update placeholder values
            $('[name="tmp_parent_id"]').val( parent_id );
        	$('[name="tmp_menu_order"]').val( menu_order );
        	$('[name="tmp_parent_label"]').val( parent_label );

		});

	// @todo add a cancel and save button, handle actions accordingly
	$('#bu_page_parent_save').click( function(e) {
		e.preventDefault();

        $('[name="parent_id"]').val( $('[name="tmp_parent_id"]').val() );
    	$('[name="menu_order"]').val( $('[name="tmp_menu_order"]').val() );
		$('#bu_page_parent_current_label span').text($('[name="tmp_parent_label"]').val());

		// Update rollback object
		bu_navtree_rollback = $.jstree._reference('#edit_page_tree').get_rollback();

		tb_remove();

	});

	$('#bu_page_parent_cancel').click( function(e) {
		e.preventDefault();

        $('[name="tmp_parent_id"]').val( '' );
    	$('[name="tmp_menu_order"]').val( '' );
    	$('[name="tmp_parent_label"]').val( '' );

    	// Rollback to last stored state
    	$.jstree.rollback(bu_navtree_rollback);

    	tb_remove();

	});

	// @todo Implement this properly
	// need to prohibit displaying of navigation label for top-level pages
	$("#bu-page-navigation-display").click( function(e){

		// if this page was already in the nav and also top level, we don't need the validation (let user do whatever, ignore the following)
		// ...
		// otherwise we need to validate input
		// if ( !already_in_nav || current_parent != 0 ) {
			
		// 	// if we don't allow top levels, and the user has selected top level, then this is clearly a problem
		// 	if (!allowTop && current_parent == 0 && check.is(":checked")) {
		// 		alert("Displaying top-level pages in the navigation is disabled. To change this behavior, go to Site Design > Primary Navigation and enable \"Allow Top-Level Pages.\"");
		// 		check.removeAttr("checked");
		// 	}
		// }

	});

	/* Tooltip for page parent */
	$('#bu-page-parent-help').qtip({
		content: $('#bu-page-parent-help-content').html(),
		position: {
			corner: {target: 'leftMiddle', tooltip: 'rightMiddle'},
			adjust: {screen: true}
		},
		style: {width: {min: '600px'}}
	});

});