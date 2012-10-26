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
			label: '[name="nav_label"]',
			visible: '[name="nav_display"]',
			postID: '[name="post_ID"]',
			originalStatus: '[name="original_post_status"]',
			parent: '[name="parent_id"]',
			order: '[name="menu_order"]',
			autoDraft: '[name="auto_draft"]'
		},

		// Metabox instance data
		data: {
			modalTree: undefined,
			breadcrumbs: '',
			label: ''
		},

		initialize: function() {
			var currentStatus, currentParent, currentOrder, navLabel, navDisplay;
			
			// Create post navigation tree from server-provided instance settings object
			this.settings = bu_nav_settings_nav_metabox;
			this.settings.el = this.ui.treeContainer;

			// Populate current post object with initial form input data 
			this.settings.isNewPost = $(this.inputs['autoDraft']).val() == 1 ? true : false;
			currentStatus = $(this.inputs['originalStatus']).val();
			currentParent = parseInt($(this.inputs['parent']).val(),10);
			currentOrder = parseInt($(this.inputs['order']).val(),10);
			navLabel = $(this.inputs['label']).val() || '(no title)';
			navDisplay = $(this.inputs['visible']).attr('checked') || false;

			// Create current post object
			this.settings.currentPost = {
				ID: parseInt($(this.inputs['postID']).val(),10),
				title: navLabel,
				meta: { excluded: !navDisplay },
				parent: currentParent,
				menu_order: currentOrder,
				status: currentStatus == 'auto-draft' ? 'new' : currentStatus
			};
			
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
				this.data.modalTree = ModalPostTree(this.settings);

				// Subscribe to relevant signals to trigger UI updates
				this.data.modalTree.listenFor( 'locationUpdated', $.proxy(this.onLocationUpdated,this) );

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
			this.settings.currentPost.title = label;

			// Label updates should be reflected in tree view
			Navtree.updatePost( this.settings.currentPost );
			Navtree.save();

			this.updateBreadcrumbs( this.settings.currentPost );

		},

		onToggleVisibility: function(e) {
			var visible = $(e.target).attr('checked');
				
			if (visible && !this.isAllowedInNavigationLists(this.settings.currentPost)) {
				e.preventDefault();
				this.notify("Displaying top-level pages in the navigation is disabled. To change this behavior, go to Site Design > Primary Navigation and enable \"Allow Top-Level Pages.\"");
			}
			
			this.settings.currentPost.meta['excluded'] = ! visible;
			
			// Nav visibility updates should be reflected in tree view
			Navtree.updatePost( this.settings.currentPost );
			Navtree.save();
		},

		onLocationUpdated: function( post ) {
			
			// Set form field values
			$(this.inputs.parent).val(post.parent);
			$(this.inputs.order).val(post.menu_order);

			this.updateBreadcrumbs( post );

			this.settings.currentPost = post;
		},

		//Methods
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
			navCancelBtn: '#bu_page_parent_cancel'
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

			// Create post navigation tree,
			Navtree = bu.plugins.navigation.tree( 'edit_post', c );

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
			$('#TB_window').bind('unload tb_unload', function(e){

				if(!that.saving) {
					Navtree.restore();
				} else {
					that.saving = false;
				}
			});

			return false;

		};

		// Modal toolbar actions

		that.onUpdateLocation = function(e) {

			e.preventDefault();

			that.broadcast( 'locationUpdated', [ Navtree.getCurrentPost() ]);

			// Update rollback object
			Navtree.save();

			that.saving = true;

			tb_remove();

		};

		that.onCancelMove = function(e) {

			e.preventDefault();

			tb_remove();

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