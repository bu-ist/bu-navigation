// Bail if globals we depend on are undefined
if((typeof bu === 'undefined' ) ||
	(typeof bu.navigation === 'undefined' ) )
		throw new TypeError('BU Navigation Metabox dependencies have not been met!');

(function($) {

	// Append to views namespace for global access
	bu.navigation.views = bu.navigation.views || {};

	// Aliases
	var Metabox, MovePostModal, Navtree;

	/**
	 * Navigation attributes metabox
	 */
	Metabox = bu.navigation.views.Metabox = {

		// Metabox container
		el: '#bupageparentdiv',

		// UI Elements
		ui: {
			treeContainer: '#edit_page_tree',
			moveBtn: '#select-parent',
			breadcrumbs: '#bu_nav_attributes_location_breadcrumbs'
		},

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

			// Configuration
			config = config || {};
			this.settings = $.extend({}, bu.navigation.settings, config );

			// References to key elements
			this.$el = $(this.el);

			// Build navigation tree in modal
			this.loadNavTree();

			// Bind event handlers
			this.attachHandlers();

		},

		loadNavTree: function() {

			// Tree config that varies based on new vs. existing post edit
			var isNew = $('#auto_draft').val() == '1' ? true : false;
			var postID = isNew ? $('#post_ID').val() : this.settings.currentPost;

			// Instantiate navtree object
			this.data.modalTree = ModalPostTree({
				treeContainer: this.ui.treeContainer,
				currentPost: postID,
				ancestors: this.settings.ancestors,
				isNewPost: isNew
			});

		},

		attachHandlers: function() {

			// Metabox actions
			this.$el.delegate(this.inputs.label, 'blur', this.onLabelChange);
			this.$el.delegate(this.inputs.visible, 'click', this.onToggleVisibility);

			// Global actions
			$(document.body).bind('navigation:post-moved', this.updateLocation );
	
		},

		// Event handlers

		onLabelChange: function(e) {
			//@todo implement
		},

		onToggleVisibility: function(e) {
			//@todo implement
		},

		// Methods

		updateLocation: function( e, data) {
			var post = data.post;

			Metabox.updateBreadcrumbs( post );

			// Set form field values
			$(Metabox.inputs.parent).val(post.parent);
			$(Metabox.inputs.order).val(post.menu_order);

		},

		updateBreadcrumbs: function( post ) {
			var ancestors = Navtree.getAncestors( post.ID );
			var ancestorTitles = $.map( ancestors.reverse(), function(post){ return post.title; });
			var breadcrumbs = ancestorTitles.join(' > ');

			// Update breadcrumbs
			if (breadcrumbs) {
				$(Metabox.ui.breadcrumbs).html('<p>' + breadcrumbs + '</p>');
			} else {
				$(Metabox.ui.breadcrumbs).html('<p>Top Level Page</p>');
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
	ModalPostTree = bu.navigation.views.ModalPostTree = function( config ) {

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

		// Alias
		var $toolbar;

		/**
		 * Build a modal navigation tree object
		 */
		var initialize = function(config) {

			// Create post navigation tree, pass in initial posts from server
			Navtree = bu.navigation.tree('edit_post', { el: c.treeContainer});
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

			$(document.body).trigger('navigation:post-moved', { post: Navtree.getCurrentPost() });

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
jQuery(document).ready( function(){ bu.navigation.views.Metabox.initialize(); });