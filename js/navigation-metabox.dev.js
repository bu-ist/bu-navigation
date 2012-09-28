/*
@todo
	- look at the new media upload interface (currently in beta) for a better looking modal implementation
	- redo with Backbone
*/

/*
Taken from link-lists.dev.js, and in turn media-upload.dev.js
Handles resizing of thickbox viewport, both on load and as window resizes
Ugly...
*/
var tb_position;

(function($) {
	tb_position = function() {

		var tbWindow = $('#TB_window'), width = $(window).width(), H = $(window).height(), W = ( 720 < width ) ? 720 : width;

		if ( tbWindow.size() ) {
			tbWindow.width( W - 50 ).height( H - 45 );
			$('#TB_inline').width( W - 80 ).height( H - 90 );
			tbWindow.css({'margin-left': '-' + parseInt((( W - 50 ) / 2),10) + 'px'});
			if ( typeof document.body.style.maxWidth != 'undefined' )
				tbWindow.css({'top':'20px','margin-top':'0'});
		}

		return $('a.thickbox').each( function() {
			var href = $(this).attr('href');

			if ( ! href ) return;
			href = href.replace(/&width=[0-9]+/g, '');
			href = href.replace(/&height=[0-9]+/g, '');
			$(this).attr( 'href', href + '&width=' + ( W - 80 ) + '&height=' + ( H - 85 ) );
		});
	};

	$(window).resize(function(){ tb_position(); });

})(jQuery);

jQuery(document).ready( function($) {

	// Bail if dependent globals are undefined
	if( typeof bu === 'undefined' )
		return;

	if( typeof BUPP === 'undefined' )
		return;

	tb_position();

	var bu_navtree_rollback = null;

	// Current page attributes
	var current_node_id = 'p' + BUPP.currentPage;
	var current_node = null;
	var current_selection = [ '#' + current_node_id ];
	var current_section = [];

	if( BUPP.ancestors ) {
		var i = BUPP.ancestors.length - 1;

		for(; i >= 0; i-- ) {
			current_section.push( '#p' + BUPP.ancestors[i] );
		}
	}

	// Customizations on top of base BU jstree configuration
	var extra_conf = {
		"core" : {
			"initially_open": current_section
		},
		"ui" : {
			"initially_select": current_selection
		},
		"crrm" : {
			"move" : {
				"check_move" : function (m) {
					
					var attempted_parent_id = m.np.attr('id');

					// Can ONLY move current post being edited
					if( current_node_id != m.o.attr( 'id' ) ) {
						console.log('No moving allowed -- illegal selection!');
						return false;
					}

					// Don't allow top level pages for section editors or of global option prohibits it
					if( m.cr == -1 && ( BUPP.isSectionEditor || ! BUPP.allowTop ) ) {
						console.log('Move denied, top level pages cannot be created!');
						return false;
					}

					// Section editor specific
					if( BUPP.isSectionEditor ) {

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

					console.log('Check move has passed!');
					return true;

				}
			}
		}
	};

	// Extend base BU jstree configuration
	var jstree_settings = bu.navigation.default_tree_config( { 'extra': extra_conf, 'initialTree': BUPP.tree } );

	// Instantiate jstree and attach event handlers
	$('#edit_page_tree')

		// Construction
		.jstree( jstree_settings )

		// Run after initially_open and initially_select have run
		.bind('reselect.jstree', function( event, data ) {


			if( bu_navtree_rollback === null ) {

				bu_navtree_rollback = {};

				// Clone rollback
				$.extend( true, bu_navtree_rollback, data.inst.get_rollback() );

			}

			current_node = $( '#' + current_node_id );

		})

		// Run before ALL events -- allows us to interrupt selection, hover and drag behaviors
		.bind( "before.jstree", function( event, data ) {

			// Override default behavior for specific functions
			switch( data.func ) {

				case "select_node":

					// The argument that contains the node being selected is inconsistent.
					// The following values occur:
						// 1. String of the HTML ID attribute of the list item -- "#<post-id>" (happens with initially_select)
						// 2. HTMLElement of the anchor (happens on manual selection)
						// 3. jQuery object of the selected list item (happens on node closing)

					if( typeof data.args[0] == 'string' ) {
						if( '#' + current_node_id != data.args[0] ) {
							console.log( 'Selection prohibited 1!' );
							return false;
						}
					}

					if( data.args[0] instanceof HTMLElement ) {
						if( current_node.attr('id') != $(data.args[0]).parent('li').attr('id') ) {
							console.log('Selection prohibited 2!');
							return false;
						}
					}

					if( data.args[0] instanceof jQuery ) {
						if( current_node.attr('id') != data.args[0].attr('id') ) {
							console.log('Selection prohibited 3!');
							return false;
						}
					}

					break;

				// Don't allow de-selection of post being edited
				case "deselect_node":

					if( current_node.attr('id') == $(data.args[0]).parent('li').attr('id') ) {
						return false;
					}

					break;

				case "hover_node":
				case "start_drag":

					var $node = $(data.args[0]);

					// Can only hover on current node
					if( current_node.attr('id') != $node.parent('li').attr('id') ) {
						return false;
					}

					break;

			}

		})

		// Run after a node has been successful moved
		.bind('move_node.jstree', function( event, data ){

			var $new_parent = data.rslt.np;
			var menu_order = data.rslt.o.index() + 1;

			var parent_id = $new_parent.attr('id').substr(1);
			parent_id = ( parent_id > 0 ) ? parent_id : 0;

			var parent_label = data.inst.get_text( $new_parent );

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
			
		// if we don't allow top levels, and the user has selected top level, then this is clearly a problem
		//	if (!allowTop && current_parent == 0 && check.is(":checked")) {
		//		alert("Displaying top-level pages in the navigation is disabled. To change this behavior, go to Site Design > Primary Navigation and enable \"Allow Top-Level Pages.\"");
		//		check.removeAttr("checked");
		//	}
		// }

	});

});