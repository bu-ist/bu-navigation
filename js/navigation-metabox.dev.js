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

			if( typeof this.settings.isNewPost === 'undefined' );
				this.settings.isNewPost = $('#auto_draft').val() == 1 ? true : false;

			if( this.settings.isNewPost )
				this.settings.currentPost = $('#post_ID').val();

			// References to key elements
			this.$el = $(this.el);

			// Load navigation tree
			// @todo we should consider only loading nav tree on "Move page" button click
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

			// should we load tree only when "Move page" button is clicked?
			// this.$el.delegate(this.ui.moveBtn, 'click', $.proxy( this.loadNavTree, this ) );

			// Metabox actions
			this.$el.delegate(this.inputs.label, 'blur', $.proxy(this.onLabelChange,this));
			this.$el.delegate(this.inputs.visible, 'click', $.proxy(this.onToggleVisibility,this));
	
		},

		// Event handlers

		onLabelChange: function(e) {
			var label = $(this.inputs.label).attr('value');
			var post = { ID: this.settings.currentPost, title: label };

			// Label update should be reflected in tree view
			Navtree.updatePost( post );
			Navtree.save();

			this.updateBreadcrumbs( post );

		},

		onToggleVisibility: function(e) {
			//@todo implement
			//@todo check top-level settings, prevent toggle if needed
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
			var breadcrumbs = ancestors.join(' > ');

			// Update breadcrumbs
			if (ancestors.length > 1) {
				$(this.ui.breadcrumbs).html('<p>' + breadcrumbs + '</p>');
			} else {
				$(this.ui.breadcrumbs).html('<p>Top Level Page</p>');
			}
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
			Navtree = bu.plugins.navigation.tree('edit_post', { el: c.treeContainer});

			// Subscribe to relevant navtree signals
			Navtree.listenFor('postsSelected', that.onPostsSelected);

			$toolbar = $(c.toolbarContainer);

			// Modal toolbar actions
			$toolbar.delegate(c.navSaveBtn, 'click', that.onUpdateLocation);
			$toolbar.delegate(c.navCancelBtn, 'click', that.onCancelMove);

			// Thickbox monkey patching
			setupThickbox();

			return that;

		};

		var setupThickbox = function() {

			tb_position();

			var original_tb_remove = window.tb_remove;

			window.tb_remove = function() {

				original_tb_remove();

				Navtree.restore();

			};

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