/*
@todo
	- restructure / rethink global bu js namespace, structure of navigation related settings/views/objects
	- post status buckets to filter tree view
	- look at the new media upload interface (currently in beta) for a better looking modal implementation
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

	// Bail if globals we depend on are undefined
	if(
		( typeof bu === 'undefined' ) ||
		( typeof bu.navigation === 'undefined' ) ||
		( typeof bu.navigation.settings === 'undefined' )
		)
		return;

	// If we are the first view object, set up our namespace
	if( typeof bu.navigation.views === 'undefined' )
		bu.navigation.views = {};

	/**
	 * Reposition post interface
	 *
	 * This object encapsulates the modal navigation tree interface that is presented
	 * to a user attempting to move a post while editing it.
	 *
	 * @todo this doesn't need to be in DOM ready, relocate
	 */
	bu.navigation.views.reposition_post = function( config ) {
		
		var that = {};	// Instance object

		// Default configuration object
		that.conf = {
			tree_id: '#bu_nav_tree',	// jstree instance selector
			post_id: 0,					// current post ID
			ancestors: [],				// post ID's of all current post ancestors
			is_section_editor: false,	// whether or not the current user is a section editor
			allow_top_level: false,		// whether or not top level nav visible pages are allowed
			initial_data: null			// initial jstree json object
		};

		// Data object for this instance
		that.data = {
			settings: null,				// instance settings
			post_id: null,				// current post ID
			post: null,					// current post object
			id: null,					// jstree instance selector
			inst: null,					// jstree instance
			rollback: null,				// jstree rollback object
			tree_conf: null,			// jstree confiugration object
			is_new_post: false			// whether or not this is a new post
		};

		/**
		 * Build a modal navigation tree object
		 */
		var _construct = function( config ) {

			// Merge default config with object supplied on instantiation
			$.extend( that.conf, config );

			// Store jstree instance data
			that.data.id = that.conf.tree_id;
			that.data.inst = $(that.conf.tree_id);

			var to_open, to_select;

			// Setup data on current section for jsTree if we are editing an existing post
			if( that.conf.post_id ) {

				// Use current post ID to pass as initially_selected to ui plugin
				that.data.post_id = that.post_id_to_node_id( that.conf.post_id );

				to_select = [ '#' + that.data.post_id ];

				// Build section with current post ancesors to pass as initially_open
				to_open = [];

				if( that.conf.ancestors ) {
					var i = that.conf.ancestors.length - 1;

					for(; i >= 0; i-- ) {
						to_open.push( '#' + that.post_id_to_node_id( that.conf.ancestors[i] ) );
					}
				}

			} else {

				// Fetch autodraft ID and current post title to build placeholder page for navtree
				that.data.post_id = $('input[name="post_ID"]').val() || '0';
				that.data.post_id = that.post_id_to_node_id( that.data.post_id );
				that.data.is_new_post = true;

				// New post hasn't been saved so it won't have a jstree node yet
				// We'll create one and select it ourselves once the tree has been loaded
				to_open = [];
				to_select = [];

			}

			// Start with BU jstree settings object
			var default_settings = bu.navigation.get_default_tree_settings( { 'initialTree': that.conf.initial_data } );

			// Customizations on top of base BU jstree configuration
			var settings = {
				"crrm" : {
					"move" : {
						"check_move" : that.check_move
					}
				}
			};

			// Tell jsTree to open all ancestors for the current page if we have one
			if( to_open.length ) {
				settings["core"] = { "initially_open": to_open };
			}

			// Tell jsTree to select the current post on load if we have one
			if( to_select.length ) {
				settings["ui"] = { "initially_select" : to_select };
			}

			// Extend base BU jstree configuration
			that.data.settings = $.extend( true, settings, default_settings );

			// Plant the tree
			that.data.inst.jstree( that.data.settings );

			// Attach event handlers
			attach_handlers();

			// Return this instance
			return that;

		};

		// Private methods

		/**
		 * Bind jstree event handlers
		 */
		var attach_handlers = function() {

			that.data.inst.bind( 'before.jstree', that.before );
			that.data.inst.bind( 'loaded.jstree', that.on_load_posts );
			that.data.inst.bind( 'reselect.jstree', that.on_reselect );
			that.data.inst.bind( 'clean_node.jstree', that.on_clean_post );
			that.data.inst.bind( 'move_node.jstree', that.on_move_post );
			that.data.inst.bind( 'create_node.jstree', that.on_create_post );

		};

		/**
		 * Append a span with post status information to the given jstree post node
		 */
		var append_post_status = function( $post ) {

			var post_status = $post.data('post_status') || 'publish';

			if( post_status != 'publish' )
				$post.children('a').after('<span class="post_status ' + post_status + '">' + post_status + '</span>' );

		};

		/* Public methods */

		/**
		 * Save the current state of the jstree instance
		 */
		that.save = function() {
			// console.log('Saving tree state!');

			// Update rollback object
			that.data.rollback = $.jstree._reference( that.data.id ).get_rollback();

		};

		/**
		 * Restore the jstree instance to the last rollback state
		 */
		that.restore = function() {
			// console.log('Restoring tree!');

			/*
			HUGE hack alert...
			Using the rollback object to store a snapshot and restore is not
			exactly the intended use case.  One thing that was broken was that
			passing the config for initially_select was resulting in the selected
			page being created twice.  As part of the rollback method, it selects whatever
			was passed to initially_select, then it selects any objects that were selected
			when the rollback was stored -- including the one it just selected.  This is a
			bug that I work around by clearing the selected object (which is an empty array
			in a jQuery object) and allowing it to rely on the argument passed to
			initially_select instead.
			*/
			that.data.rollback.d.ui.selected = $([]);

			// Run rollback
			$.jstree.rollback( that.data.rollback );

			// Reset rollback object
			that.data.rollback = $.jstree._reference( that.data.id ).get_rollback();

		};

		/**
		 * Helper method for setting the label for a jstree post node
		 */
		that.set_post_label = function( post_id, label ) {
			
			$.jstree._reference( that.data.id ).set_text( post_id_to_node_id( post_id ), label );

		};

		/**
		 * Helper method for converting a raw post ID to a jstree node li ID attribute
		 */
		that.post_id_to_node_id = function( post_id ) {
			return 'p' + post_id;
		};

		/**
		 * Helper method for converting a jstree node li ID attribute to a raw post ID
		 */
		that.node_id_to_post_id = function( node_id ) {
			return node_id.substr(1);
		};

		/* Event handlers */

		// Run before ALL events -- allows us to interrupt selection, hover and drag behaviors
		that.before = function( event, data ) {

			// Override default behavior for specific functions
			switch( data.func ) {

				case "select_node":

					// The argument that contains the node being selected is inconsistent.
					// The following values occur:
						// 1. String of the HTML ID attribute of the list item -- "#<post-id>" (happens with initially_select)
						// 2. HTMLElement of the anchor (happens on manual selection)
						// 3. jQuery object of the selected list item (happens on node closing)

					if( typeof data.args[0] == 'string' ) {
						if( '#' + that.data.post_id != data.args[0] ) {
							// console.log( 'Selection prohibited 1!' );
							return false;
						}
					}

					if( data.args[0] instanceof HTMLElement ) {
						if( that.data.post.attr('id') != $(data.args[0]).parent('li').attr('id') ) {
							// console.log('Selection prohibited 2!');
							return false;
						}
					}

					if( data.args[0] instanceof jQuery ) {
						if( that.data.post.attr('id') != data.args[0].attr('id') ) {
							// console.log('Selection prohibited 3!');
							return false;
						}
					}

					break;

				// Don't allow de-selection of post being edited
				case "deselect_node":

					if( that.data.post.attr('id') == $(data.args[0]).parent('li').attr('id') ) {
						return false;
					}

					break;

				case "hover_node":
				case "start_drag":

					var $node = $(data.args[0]);

					// Can only hover on current node
					if( that.data.post.attr('id') != $node.parent('li').attr('id') ) {
						return false;
					}

					break;

			}
		};

		// Run as soon as the tree is loaded, before initial open or selection has started
		that.on_load_posts = function( event, data ) {
			// console.log('On load posts run!');

			// Current node will be undefined if we are editing a new post
			if( that.data.is_new_post ) {

				// Setup attributes for new page
				var postTitle = $('input[name="nav_label"]').val() || 'Untitled post';
				var newPageAttributes = { 'attr': { 'id' : that.data.post_id }, 'data': postTitle };
				var firstPage = data.inst._get_node('ul > li:first');

				// Create a new leaf for this page, placing it at the top
				data.inst.create_node( firstPage, 'before', newPageAttributes );
				
			}

		};

		// Run after initially_open and initially_select have run
		that.on_reselect = function( event, data ) {
			// console.log('On reselect posts run!');

			// If current node is still undefined, set it now using that.data.post_id
			if( that.data.post === null )
				that.data.post = $( '#' + that.data.post_id );

			// Store initial rollback state
			that.save();

		};

		// Used internally to set node classes when loading, used here
		// to append post status spans to each post
		that.on_clean_post = function( event, data ) {
			// console.log('On clean posts run!');

			var nodes = data.rslt.obj;

			if( nodes && nodes != -1 ) {

				nodes.each( function(i,li) {
					var $li = $(li);

					if( $li.data('meta-loaded') )
						return;

					// Add post status span after link
					append_post_status( $li );

					// Prevent duplicate spans from being added on moves
					$li.data('meta-loaded',true);

				});

			}

		};

		that.check_move = function(m) {

			var attempted_parent_id = m.np.attr('id');

			// Can ONLY move current post being edited
			if( that.data.post_id != m.o.attr( 'id' ) ) {
				// console.log('No moving allowed -- illegal selection!');
				return false;
			}

			// Don't allow top level posts for section editors or of global option prohibits it
			if( m.cr == -1 && ( that.conf.is_section_editor || ! that.conf.allow_top_level ) ) {
				console.log('Move denied, top level posts cannot be created!');
				// @todo pop up a friendlier notice explaining this
				return false;
			}

			// Section editor specific
			if( that.conf.is_section_editor ) {

				// Can't move a denied post
				if( m.o.hasClass( 'denied' ) ) {
					// console.log('Cannot move a denied post!');
					return false;
				}

				// Can't move inside denied post
				if( m.np.hasClass( 'denied' ) ) {
					// console.log('Move denied, destination parent invalid');
					return false;
				}

			}

			return true;
		};

		// Run after a tree node has been successful moved
		that.on_move_post = function( event, data ) {
			// console.log('On move post run!');

			var $new_parent = data.rslt.np;
			var menu_order = data.rslt.o.index() + 1;
			var parent_id;

			// Set new parent ID
			if( that.data.id == $new_parent.attr('id') ) {
				parent_id = 0;
			} else {
				parent_id = $new_parent.attr('id').substr(1);
			}

			// If we have a valid parent, fetch label from it's text
			var parent_label = parent_id > 0 ? data.inst.get_text( $new_parent ) : '';

			// Update placeholder values
			// @todo use data instead of input elements
			$('[name="tmp_parent_id"]').val( parent_id );
			$('[name="tmp_menu_order"]').val( menu_order );
			$('[name="tmp_parent_label"]').val( parent_label );

		};

		that.on_create_post = function( event, data ) {

			// Is the current post a new one?
			if( data.rslt.obj.attr('id') == that.data.post_id ) {

				// Fetch new page node from DOM and give it a post_status
				that.data.post = $('#' + that.data.post_id );
				that.data.post.data('post_status', 'new');

				// Add a post status of new
				append_post_status( that.data.post );

				// Select current post
				data.inst.select_node( that.data.post );

				// Update tree state to incorporate new post
				that.save();

			}

		};

		return _construct( config );

	};

	// ----------------------------------------------------------- //
	// On Dom Ready
	// ------------//

	tb_position();


	// Reference to modal navtree instance
	var navtree;

	// Instantiate navtree object
	navtree = bu.navigation.views.reposition_post({
		tree_id: '#edit_page_tree',
		post_id: bu.navigation.settings.currentPage,
		ancestors: bu.navigation.settings.ancestors,
		is_section_editor: bu.navigation.settings.isSectionEditor,
		allow_top_level: bu.navigation.settings.allowTop,
		initial_data: bu.navigation.settings.tree
	});

	/**
	 * @todo should we update current post title in tree interface when the navigation label
	 * or post title changes?
	 *
	 * If so we'll need to account for that update after rollbacks are run as they wipe them out
	 */
	$('input[name="nav_label"]').blur( function(e) {

		// Update current node title
		if( $(this).val() ) {

			// @todo sanitization
			// navtree.setCurrentPostLabel( $(this).val() );

		}

	});

	// Save navigation changes
	$('#bu_page_parent_save').click( function(e) {
		e.preventDefault();

		// Commit values for parent ID and menu order to actual inputs
		$('[name="parent_id"]').val( $('[name="tmp_parent_id"]').val() );
		$('[name="menu_order"]').val( $('[name="tmp_menu_order"]').val() );

		// Update meta box breadcrumb label
		setCurrentParentLabel( $('[name="tmp_parent_label"]').val() );

		// Update rollback object
		navtree.save();

		tb_remove();

	});

	// Canel navigation changes
	$('#bu_page_parent_cancel').click( function(e) {
		e.preventDefault();

		// Clear any unsaved moves
		$('[name="tmp_parent_id"]').val( '' );
		$('[name="tmp_menu_order"]').val( '' );
		$('[name="tmp_parent_label"]').val( '' );

		tb_remove();

	});

	/**
	 * Helper to set navigation attributes location label
	 */
	function setCurrentParentLabel( text ) {

		if( text ) {
			$('#bu_nav_attributes_location_breadcrumbs').html('<p>Current Parent: <span>' + text + '</span></p>' );
		} else {
			$('#bu_nav_attributes_location_breadcrumbs').html('<p>Current Parent: <span>None (top-level page)</span></p>');
		}

	}

	// More thickbox monkey patching -- force a jstree rollback on thickbox remove
	var original_tb_remove = window.tb_remove;
	window.tb_remove = function() {

		original_tb_remove();

		navtree.restore();

	};

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