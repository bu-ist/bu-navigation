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
		el: '#bunavattrsdiv',

		// UI Elements
		ui: {
			treeContainer: '#edit-post-tree',
			moveBtn: '#move-post-button',
			breadcrumbs: '#bu-post-breadcrumbs'
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
			this.settings = nav_metabox_settings;
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
				post_title: navLabel,
				post_status: currentStatus == 'auto-draft' ? 'new' : currentStatus,
				post_parent: currentParent,
				menu_order: currentOrder,
				post_meta: { excluded: !navDisplay },
				originalParent: currentParent,
				originalExclude: !navDisplay
			};

			// Tree container needs to know about current post status
			$(this.ui.treeContainer).addClass('current-post-status-' + currentStatus );

			// References to key elements
			this.$el = $(this.el);

			// Load navigation tree
			this.loadNavTree();

			// Bind event handlers
			this.attachHandlers();

			return this;

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
			this.settings.currentPost.post_title = label;

			// Label updates should be reflected in tree view
			Navtree.updatePost( this.settings.currentPost );
			Navtree.save();

			this.updateBreadcrumbs( this.settings.currentPost );

		},

		onToggleVisibility: function(e) {
			var visible = $(e.target).attr('checked');
			var msg = nav_metabox_settings.topLevelDisabled + "\n\n" + nav_metabox_settings.topLevelNotice;

			if (visible && !this.isAllowedInNavigationLists(this.settings.currentPost)) {

				e.preventDefault();
				this.notify(msg);

			} else {

				this.settings.currentPost.post_meta['excluded'] = ! visible;

				// Nav visibility updates should be reflected in tree view
				Navtree.updatePost( this.settings.currentPost );
				Navtree.save();

			}

		},

		onLocationUpdated: function( post ) {

			// Set form field values
			$(this.inputs.parent).val(post.post_parent);
			$(this.inputs.order).val(post.menu_order);

			this.updateBreadcrumbs( post );

			this.settings.currentPost = post;
		},

		// Methods
		updateBreadcrumbs: function( post ) {
			var ancestors, crumbs, $crumbs, $crumb;
			ancestors = Navtree.getAncestors( post.ID );
			$crumbs = $(this.ui.breadcrumbs).clone().empty();

			$.each(ancestors, function(index, label) {
				$crumb = $('<li></li>').html(label);

				if( index < ( ancestors.length - 1 ) ) {
					$crumb.append('<ul></ul>');
				} else {
					$crumb.addClass('current');
				}
				if( index === 0 ) {
					$crumbs.append($crumb);
				} else {
					$crumbs.find('ul').last().append($crumb);
				}
			});

			// Update breadcrumbs
			if ($crumbs.find('li').length > 1) {
				$(this.ui.breadcrumbs).replaceWith($crumbs);
			} else {
				$(this.ui.breadcrumbs).html('<li class="current">' + nav_metabox_settings.topLevelLabel + '</li>');
			}
		},

		isAllowedInNavigationLists: function( post ) {
			var alreadyInNav = post.originalExclude === false && post.originalParent === 0;

			if (!alreadyInNav && post.post_parent === 0) {
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
			treeContainer: '#edit-post-tree',
			// toolbar
			toolbarContainer: '.post-placement-toolbar',
			// save button
			navSaveBtn: '#bu-post-placement-save',
			// cancel button
			navCancelBtn: '#bu-post-placement-cancel',
			// drag container
			treeDragContainer: '#TB_ajaxContent'
		};

		c = $.extend(c, config);

		// Implement the signals interface
		bu.signals.register(that);

		// Alias
		var $toolbar;

		/**
		 * Build a modal navigation tree object
		 */
		var initialize = function(config) {

			// Create post navigation tree,
			Navtree = that.tree = bu.plugins.navigation.tree( 'edit_post', c );

			$toolbar = $(c.toolbarContainer);

			// Modal toolbar actions
			$toolbar.delegate(c.navSaveBtn, 'click', that.onUpdateLocation);
			$toolbar.delegate(c.navCancelBtn, 'click', that.onCancelMove);

			// Store initial tree state, either after lazy load is complete or inital selection is made
			if (c.lazyLoad) {
				Navtree.listenFor('lazyLoadComplete', Navtree.save );
			} else {
				Navtree.listenFor('postsSelected', Navtree.save );
			}

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

			that.scrollToSelection();

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

		that.scrollToSelection = function() {
			var $tree, $node, $container, containerHeight, nodeOffset;
			$tree = $(c.treeContainer),
			$node = $tree.jstree('get_selected');

			if ($node.length) {
				$container = $(c.treeDragContainer);
				containerHeight = $container.innerHeight();
				nodeOffset = $node.position().top + ($node.height() / 2) - (containerHeight / 2);

				if (nodeOffset > 0) {
					$container.scrollTop(nodeOffset);
				}
			}

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

/**
 * Thickbox monkey patching, lifted from media-upload.js
 *
 * This code does the following on window resize
 *  1. Recalculates width & height query params for a.thickbox
 *	2. Adjusts the size of the thickbox window + content area
 *
 * The code in media-upload.js only handles TB_iframeContent -- we need TB_ajaxContent as well.
 * This is fragile, and should be checked against each new version of WP.
 *
 * @todo stop using thickbox...
 */
var tb_position;
(function($) {
	tb_position = function() {
		var tbWindow = $('#TB_window'), width = $(window).width(), H = $(window).height(), W = ( 720 < width ) ? 720 : width, adminbar_height = 0;

		if ( $('body.admin-bar').length )
			adminbar_height = 28;

		if ( tbWindow.size() ) {
			tbWindow.width( W - 50 ).height( H - 45 - adminbar_height );
			$('#TB_iframeContent').width( W - 50 ).height( H - 75 - adminbar_height );
			$('#TB_ajaxContent').width( W - 80 ).height( H - 92 - adminbar_height );
			tbWindow.css({'margin-left': '-' + parseInt((( W - 50 ) / 2),10) + 'px'});
			if ( typeof document.body.style.maxWidth != 'undefined' )
				tbWindow.css({'top': 20 + adminbar_height + 'px','margin-top':'0'});
		};

		return $('a.thickbox').each( function() {
			var href = $(this).attr('href');
			if ( ! href ) return;
			href = href.replace(/&width=[0-9]+/g, '');
			href = href.replace(/&height=[0-9]+/g, '');
			$(this).attr( 'href', href + '&width=' + ( W - 80 ) + '&height=' + ( H - 85 - adminbar_height ) );
		});
	};

	$(window).resize(function(){ tb_position(); });

})(jQuery);

// Start the show
jQuery(document).ready( function(){
	// Store metabox to globally accessible location
	bu.plugins.navigation.metabox = bu.plugins.navigation.views.Metabox.initialize();
});