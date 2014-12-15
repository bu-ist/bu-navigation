/**
 * BU Navigation "Edit Order" admin page
 *
 * Presents users with a full view of hierarchical content for supported post types
 * Provides a drag and drop tree interfcace for moving posts, as well as shortcuts
 * for editing and trashing posts.
 *
 * Pages also add custom "Link" behavior -- the ability to add and edit external
 * links and place them in the navigation hierarchy.
 */

/*jslint browser: true, todo: true */
/*global bu: true, bu_navman_settings: false, jQuery: false, console: false, window: false, document: false */


// @todo remove Link Manager if links are disabled (?)

// Check prerequisites
if ((typeof bu === 'undefined') ||
		(typeof bu.plugins.navigation === 'undefined') ||
		(typeof bu.plugins.navigation.tree === 'undefined')) {
	throw new TypeError('BU Navigation Manager script dependencies have not been met!');
}

(function ($) {
	'use strict';

	// If we are the first view object, set up our namespace
	bu.plugins.navigation.views = bu.plugins.navigation.views || {};

	var Navman, Linkman, Navtree;

	/* =====================================================================
	 * Navigation manager interface
	 * ===================================================================== */
	Navman = bu.plugins.navigation.views.Navman = {

		el: '#nav-tree-container',

		ui: {
			form: '#navman_form',
			noticesContainer: '#navman-notices',
			movesField: '#navman-moves',
			insertsField: '#navman-inserts',
			updatesField: '#navman-updates',
			deletionsField: '#navman-deletions',
			expandAllBtn: '#navman_expand_all',
			collapseAllBtn: '#navman_collapse_all',
			saveBtn: '#bu_navman_save'
		},

		data: {
			dirty: false,
			deletions: [],
			insertions: {},
			updates: {},
			moves: {}
		},

		initialize: function (config) {
			// Create post navigation tree from server-provided instance settings object
			var settings = this.settings = bu_navman_settings;
			settings.el = this.el;

			Navtree = bu.plugins.navigation.tree('navman', settings);

			// Initialize link manager
			Linkman.initialize({allowTop: !!settings.allowTop, isSectionEditor: !!settings.isSectionEditor});

			// Subscribe to relevant tree signals
			Navtree.listenFor('editPost', $.proxy(this.editPost, this));

			Navtree.listenFor('postRemoved', $.proxy(this.postRemoved, this));
			Navtree.listenFor('postMoved', $.proxy(this.postMoved, this));
			Linkman.listenFor('linkInserted', $.proxy(this.linkInserted, this));
			Linkman.listenFor('linkUpdated', $.proxy(this.linkUpdated, this));

			// Form submission
			$(this.ui.form).bind('submit', $.proxy(this.save, this));
			$(this.ui.expandAllBtn).bind('click', this.expandAll);
			$(this.ui.collapseAllBtn).bind('click', this.collapseAll);

		},

		expandAll: function (e) {
			e.preventDefault();
			e.stopImmediatePropagation();
			Navtree.showAll();
		},

		collapseAll: function (e) {
			e.preventDefault();
			e.stopImmediatePropagation();
			Navtree.hideAll();
		},

		editPost: function (post) {
			if (bu_navman_settings.linksPostType === post.post_type) {
				Linkman.edit(post);
			} else {
				var url = "post.php?action=edit&post=" + post.ID;
				window.location = url;
			}
		},

		linkInserted: function (link) {
			this.data.insertions[link.ID] = link;
			this.data.dirty = true;
		},

		linkUpdated: function (link) {
			if ('new' === link.post_status) {
				// Update to new link (not yet commited to DB)
				this.data.insertions[link.ID] = link;
			} else {
				// Update to previously existing link
				this.data.updates[link.ID] = link;
			}
			this.data.dirty = true;
		},

		postRemoved: function (post) {
			var id = post.ID;

			if (id) {

				if (typeof this.data.insertions[id] !== 'undefined') {

					// Newly inserted posts aren't yet commited to DB, so just
					// remove it from the insertions cache and move on
					delete this.data.insertions[id];

				} else if (typeof this.data.updates[id] !== 'undefined') {

					// Post was marked to be updated -- remove from updates cache
					// and push to deletions
					delete this.data.updates[id];
					this.data.deletions.push(id);
					this.data.dirty = true;

				} else if (typeof this.data.moves[id] !== 'undefined') {

					// Post was marked to be moved -- remove from moves cache
					// and push to deletions
					delete this.data.moves[id];
					this.data.deletions.push(id);
					this.data.dirty = true;

				} else {

					// Deletion was not previously in any category, just add to deletions cache
					// and mark page as dirty
					this.data.deletions.push(id);
					this.data.dirty = true;

				}
			}
		},

		postMoved : function (post) {

			// New post moves are tracked via the insertions cache
			if ('new' === post.post_status) {
				return;
			}

			this.data.moves[post.ID] = post;
			this.data.dirty = true;

		},

		save: function (e) {
			var deletions = this.data.deletions, moves = {}, updates = {}, insertions = {}, current;

			// Process insertions
			$.each(this.data.insertions, function (postID, post) {
				current = Navtree.getPost(postID);
				if (current) {
					insertions[current.ID] = current;
				}
			});

			// Process updates
			$.each(this.data.updates, function (postID, post) {
				current = Navtree.getPost(postID);
				if (current) {
					updates[current.ID] = current;
				}
			});

			// Process moves
			$.each(this.data.moves, function (postID, post) {
				current = Navtree.getPost(postID);
				if (current) {
					// Construct object for submission with just the fields we need
					moves[current.ID] = {
						ID: current.ID,
						post_status: current.post_status,
						post_type: current.post_type,
						post_parent: current.post_parent,
						menu_order: current.menu_order
					};
				}
			});

			// Push pending deletions, insertions, updates and moves to hidden inputs for POST'ing
			$(this.ui.deletionsField).attr("value", JSON.stringify(deletions));
			$(this.ui.insertsField).attr("value", JSON.stringify(insertions));
			$(this.ui.updatesField).attr("value", JSON.stringify(updates));
			$(this.ui.movesField).attr("value", JSON.stringify(moves));

			// Notify user that save is in progress
			var $msg = $('<span>' + bu_navman_settings.saveNotice + '</span>')
			$(this.ui.saveBtn).prev('img').css('visibility', 'visible');
			this.notice( $msg.html(), 'message');

			// Lock tree interface while saving
			Navtree.lock();

			// Let us through the window.unload check now that all pending moves are ready to go
			this.data.dirty = false;

		},

		notice: function (message, type, replace_existing) {
			replace_existing = replace_existing || true;

			var $container = $(this.ui.noticesContainer), classes = '';

			if (replace_existing) {
				$container.empty();
			}

			classes = ('message' === type) ? 'updated fade' : 'error';

			$container.append('<div class="' + classes + ' below-h2"><p>' + message + '</p></div>');

		}

	};

	/* =====================================================================
	 * Link manager interface
	 * ===================================================================== */
	Linkman = bu.plugins.navigation.views.Linkman = {

		el: '#navman-link-editor',

		ui: {
			form: '#navman_editlink_form',
			addBtn: '#navman_add_link',
			urlField: '#editlink_address',
			labelField: '#editlink_label',
			targetNewField: '#editlink_target_new',
			targetSameField: '#editlink_target_same'
		},

		data: {
			currentLink: null,
			allowTop: true,
			isSectionEditor: false
		},

		initialize: function (config) {
			config = config || {};
			$.extend(true, this.data, config);

			// Implement the signals interface
			bu.signals.register(this);

			this.$el = $(this.el);

			this.$form = $(this.ui.form);

			var buttons = {};
			buttons[bu_navman_settings.confirmLinkBtn] = $.proxy(this.save, this);
			buttons[bu_navman_settings.cancelLinkBtn] = $.proxy(this.cancel, this);

			// Edit link dialog
			this.$el.dialog({
				autoOpen: false,
				buttons: buttons,
				minWidth: 400,
				width: 500,
				modal: true,
				resizable: false
			});

			// Prevent clicks in dialog/overlay from removing tree selections
			$(document.body).delegate('.ui-widget-overlay, .ui-widget', 'click', this.stopPropagation);

			// Add link event
			$(this.ui.addBtn).bind('click', $.proxy(this.add, this));

			// Enable/disable add link button with selection if allow top is false
			Navtree.listenFor('postSelected', $.proxy(this.onPostSelected, this));
			Navtree.listenFor('postDeselected', $.proxy(this.onPostDeselected, this));
			Navtree.listenFor('postsDeselected', $.proxy(this.onPostDeselected, this));

		},

		add: function (e) {
			e.preventDefault();
			e.stopPropagation();
			var msg = '';
			var selected;

			if ($(e.currentTarget).parent('li').hasClass('disabled')) {
				selected = Navtree.getSelectedPost();
				msg = bu_navman_settings.noLinksNotice;

				// User is attempting to add a link below a link
				if (selected && bu_navman_settings.linksPostType === selected.post_type ) {
					msg = bu_navman_settings.noChildLinkNotice + "\n\n" + bu_navman_settings.createLinkNotice;

				} else {
					// User is a section editor attempting to add a top level link
					if (Navman.settings.isSectionEditor) {
						msg = bu_navman_settings.noTopLevelNotice + "\n\n" + bu_navman_settings.createLinkNotice;
					} else {
						// User is not a section editor, but not allowed to add top level pages due to allow top setting
						if (!Navman.settings.allowTop) {
							msg = bu_navman_settings.noTopLevelNotice + "\n\n" + bu_navman_settings.createLinkNotice + "\n\n" + bu_navman_settings.allowTopNotice;
						}
					}
				}

				alert(msg);

			} else {
				// Setup new link
				this.data.currentLink = { "post_status": "new", "post_type": bu_navman_settings.linksPostType, "post_meta": {} };
				this.$el.dialog('option', 'title', bu_navman_settings.addLinkDialogTitle).dialog('open');
			}

		},

		edit: function (link) {

			$(this.ui.urlField).attr("value", link.post_content);
			$(this.ui.labelField).attr("value", link.post_title);

			if ('new' === link.post_meta.bu_link_target) {
				$(this.ui.targetNewField).attr("checked", "checked");
			} else {
				$(this.ui.targetSameField).attr("checked", "checked");
			}

			this.data.currentLink = link;

			this.$el.dialog('option', 'title', bu_navman_settings.editLinkDialogTitle).dialog('open');
		},

		save: function (e) {
			e.preventDefault();
			e.stopPropagation();

			if (this.$form.valid()) {

				// Global link being edited
				var link = this.data.currentLink, saved, selected;

				// Extract updates from form
				link.post_content = $(this.ui.urlField).attr("value");
				link.post_title = $(this.ui.labelField).attr("value");
				link.url = link.post_content;
				link.post_meta.bu_link_target = $("input[name='editlink_target']:checked").attr("value");

				selected = Navtree.getSelectedPost();

				if (selected) {
					link.post_parent = selected.ID;
					link.menu_order = 1;
				} else {
					link.post_parent = 0;
					link.menu_order = 1;
				}

				// Insert or update link
				if ('new' === link.post_status && !link.ID) {

					saved = Navtree.insertPost(link);
					this.broadcast('linkInserted', [saved]);

				} else {

					saved = Navtree.updatePost(link);
					this.broadcast('linkUpdated', [saved]);

				}

				this.clear();

				this.$el.dialog('close');

			}

		},

		cancel: function (e) {
			e.preventDefault();
			e.stopPropagation();

			this.$el.dialog('close');

			this.clear();
		},

		clear: function () {

			// Clear dialog
			$(this.ui.urlField).attr("value", "");
			$(this.ui.labelField).attr("value", "");
			$(this.ui.targetSameField).attr("checked", "checked");
			$(this.ui.targetNewField).removeAttr("checked");

			this.data.currentLink = null;

		},

		onPostSelected: function (post) {
			var canAdd = true;

			if (post.post_type == bu_navman_settings.linksPostType) {
				canAdd = false;
			}

			canAdd = bu.hooks.applyFilters('navmanCanAddLink', canAdd, post, Navtree);

			if (canAdd) {
				$(this.ui.addBtn).parent('li').removeClass('disabled');
			} else {
				$(this.ui.addBtn).parent('li').addClass('disabled');
			}
		},

		onPostDeselected: function () {
			var canAdd = this.data.allowTop;

			canAdd = bu.hooks.applyFilters('navmanCanAddLink', canAdd);

			if (!canAdd) {
				$(this.ui.addBtn).parent('li').addClass('disabled');
			} else {
				$(this.ui.addBtn).parent('li').removeClass('disabled');
			}
		},

		stopPropagation: function (e) {
			e.stopPropagation();
		}

	};

	window.onbeforeunload = function () {
		if (Navman.data.dirty) {
			return bu_navman_settings.unloadWarning;
		}

		return;
	};

}(jQuery));

jQuery(document).ready(function ($) {
	'use strict';
	bu.plugins.navigation.views.Navman.initialize();
});
