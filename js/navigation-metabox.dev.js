// Bail if globals we depend on are undefined
if((typeof bu === 'undefined' ) ||
	(typeof bu.plugins === 'undefined' ) ||
	(typeof bu.plugins.navigation === 'undefined' ) )
		throw new TypeError('BU Navigation Metabox dependencies have not been met!');

(function($) {

	// Append to views namespace for global access
	bu.plugins.navigation.views = bu.plugins.navigation.views || {};

	// Aliases
	var Metabox, MovePostModal, Navtree;

	/**
	 * Navigation attributes metabox
	 */
	Metabox = bu.plugins.navigation.views.Metabox = {

		// Metabox container
		el: '#bupageparentdiv',

		// UI Elements
		ui: {
			treeContainer: '#edit_page_tree',
			moveBtn: '#select-parent',
			breadcrumbs: '#bu_nav_attributes_location_breadcrumbs'
		},

		// Form fields
		inputs: {
			label: '#bu-page-navigation-label',
			visible: '#bu-page-navigation-display',
			parent: '[name="parent_id"]',
			order: '[name="menu_order"]'
		},

		// Metabox instance data
		data: {
			modalTree: undefined,
			breadcrumbs: '',
			label: ''
		},

		initialize: function(config) {

			// Merge config argument with global plugin settings object
			config = config || {};
			this.settings = $.extend({}, bu.plugins.navigation.settings, config );

			if( typeof this.settings.isNewPost === 'undefined' )
				this.settings.isNewPost = $('#auto_draft').val() == 1 ? true : false;

			if( this.settings.isNewPost )
				this.settings.currentPost = $('#post_ID').val();

			// References to key elements
			this.$el = $(this.el);

			// Load navigation tree
			this.loadNavTree();

			// Bind event handlers
			this.attachHandlers();

		},

		loadNavTree: function(e) {

			if( typeof this.data.modalTree === 'undefined' ) {

				// Instantiate navtree object
				this.data.modalTree = ModalPostTree({
					treeContainer: this.ui.treeContainer,
					currentPost: this.settings.currentPost,
					ancestors: this.settings.ancestors,
					isNewPost: this.settings.isNewPost
				});

				// Subscribe to relevant signals to trigger UI updates
				this.data.modalTree.listenFor( 'update', $.proxy(this.updateLocation,this) );

			}

		},

		attachHandlers: function() {

			// Modal creation on click
			this.$el.delegate(this.ui.moveBtn, 'click', this.data.modalTree.open );

			// Metabox actions
			this.$el.delegate(this.inputs.label, 'blur', $.proxy(this.onLabelChange,this));
			this.$el.delegate(this.inputs.visible, 'click', $.proxy(this.onToggleVisibility,this));
	
		},

		// Event handlers

		onLabelChange: function(e) {
			var label = $(this.inputs.label).attr('value');
			var post = { ID: this.settings.currentPost, title: label };

			// Label updates should be reflected in tree view
			Navtree.updatePost( post );
			Navtree.save();

			this.updateBreadcrumbs( post );

		},

		onToggleVisibility: function(e) {
			var visible = $(e.target).attr('checked');
			var post = Navtree.getPost( this.settings.currentPost );
			
			if ( visible && ! this.isAllowedInNavigationLists( post ) ) {
				e.preventDefault();
				this.notify("Displaying top-level pages in the navigation is disabled. To change this behavior, go to Site Design > Primary Navigation and enable \"Allow Top-Level Pages.\"");
			}
			
			post.meta['excluded'] = ! visible;
			
			// Nav visibility updates should be reflected in tree view
			Navtree.updatePost( post );
			Navtree.save();
		},

		// Methods

		updateLocation: function( post ) {

			this.updateBreadcrumbs( post );

			// Set form field values
			$(this.inputs.parent).val(post.parent);
			$(this.inputs.order).val(post.menu_order);

		},

		updateBreadcrumbs: function( post ) {

			var ancestors = Navtree.getAncestors( post.ID );
			var breadcrumbs = ancestors.join("&nbsp;&raquo;&nbsp;");

			// Update breadcrumbs
			if (ancestors.length > 1) {
				$(this.ui.breadcrumbs).html('<p>' + breadcrumbs + '</p>');
			} else {
				$(this.ui.breadcrumbs).html('<p>Top level page</p>');
			}
		},

		isAllowedInNavigationLists: function( post ) {

			if( post.parent === 0  ) {
				return this.settings.allowTop;
			}
			
			return true;
		},

		notify: function( msg ) {
			alert(msg);
		}

	};

	/**
	 * Modal post tree interface
	 *
	 * This object encapsulates the modal navigation tree interface that is presented
	 * to a user attempting to move a post while editing it.
	 *
	 * @todo refactor for consistency with Metabox (object literal)
	 */
	ModalPostTree = bu.plugins.navigation.views.ModalPostTree = function( config ) {

		var that = {}; // Instance object

		// Default configuration object
		var c = that.conf = {
			// jstree instance selector
			treeContainer: '#edit_page_tree',
			// toolbar
			toolbarContainer: '.page_location_toolbar',
			// save button
			navSaveBtn: '#bu_page_parent_save',
			// cancel button
			navCancelBtn: '#bu_page_parent_cancel',
			// Current post ID
			currentPost: undefined,
			// Flag to trigger new post behaviors
			isNewPost: false
		};

		c = $.extend(c, config);

		// Signals
		$.extend( true, that, bu.signals );

		// Alias
		var $toolbar;

		/**
		 * Build a modal navigation tree object
		 */
		var initialize = function(config) {

			// Create post navigation tree, pass in initial posts from server
			Navtree = bu.plugins.navigation.tree('edit_post', { el: c.treeContainer });

			// Subscribe to relevant navtree signals
			Navtree.listenFor('postsSelected', that.onPostsSelected);

			$toolbar = $(c.toolbarContainer);

			// Modal toolbar actions
			$toolbar.delegate(c.navSaveBtn, 'click', that.onUpdateLocation);
			$toolbar.delegate(c.navCancelBtn, 'click', that.onCancelMove);

			return that;

		};

		that.open = function(e) {

			// See media-upload.dev.js
			// This code was adapted from the above code that modifies the size of the thickbox.
			var width = $(window).width(), H = $(window).height(), W = ( 720 < width ) ? 720 : width;

			var title = e.target.title || e.target.name || null;
			var href = e.target.href || e.target.alt;
			var g = e.target.rel || false;

			href = href.replace(/&width=[0-9]+/g, '');
			href = href.replace(/&height=[0-9]+/g, '');
			href = href + '&width=' + ( W - 80 ) + '&height=' + ( H - 85 );

			tb_show(title,href,g);

			Navtree.scrollToSelection();

			// Restore navtree state on close (cancel)
			$('#TB_window').bind('tb_unload', function(e){
				Navtree.restore();
			});

			return false;

		};

		// Modal toolbar actions

		that.onUpdateLocation = function(e) {

			e.preventDefault();

			that.broadcast( 'update', [ Navtree.getCurrentPost() ]);

			// Update rollback object
			Navtree.save();

			tb_remove();

		};

		that.onCancelMove = function(e) {

			e.preventDefault();

			tb_remove();

		};

		// Navtree Actions

		that.onPostsSelected = function() {

			// Current node will be undefined if we are editing a new post
			if( c.isNewPost && ! Navtree.getCurrentPost() ) {

				// Setup attributes for new page
				var post = {
					ID: c.currentPost,
					title: $('input[name="nav_label"]').val() || 'Untitled post',
					parent: 0,
					menu_order: 0
				};

				// Insert new post placeholder and designate it as current post
				Navtree.insertPost( post, { position: 'before' } );
				Navtree.setCurrentPost( post );

			}

			// Store tree state after all posts are loaded/opened/selected
			Navtree.save();

		};

		return initialize(config);

	};

})(jQuery);

/*
Taken from link-lists.dev.js, and in turn media-upload.dev.js
Handles resizing of thickbox viewport, both on load and as window resizes
Ugly...
*/
var tb_position;
(function($) {
	tb_position = function() {

		var tbWindow = $('#TB_window'),
			width = $(window).width(),
			H = $(window).height(),
			W = (720 < width) ? 720 : width;

		if(tbWindow.size()) {
			tbWindow.width(W - 50).height(H - 45);
			$('#TB_inline').width(W - 80).height(H - 90);
			tbWindow.css({
				'margin-left': '-' + parseInt(((W - 50) / 2), 10) + 'px'
			});
			if(typeof document.body.style.maxWidth != 'undefined') tbWindow.css({
				'top': '20px',
				'margin-top': '0'
			});
		}

		return $('a.thickbox').each(function() {
			var href = $(this).attr('href');

			if(!href) return;
			href = href.replace(/&width=[0-9]+/g, '');
			href = href.replace(/&height=[0-9]+/g, '');
			$(this).attr('href', href + '&width=' + (W - 80) + '&height=' + (H - 85));
		});
	};

	$(window).resize(function() {
		tb_position();
	});

})(jQuery);

// Start the show
jQuery(document).ready( function(){ bu.plugins.navigation.views.Metabox.initialize(); });