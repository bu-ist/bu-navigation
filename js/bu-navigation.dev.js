/**
 * ========================================================================
 * BU Navigation plugin - main script
 * ========================================================================
 */

/*jslint browser: true, todo: true */
/*global bu: true, jQuery: false, console: false, window: false, document: false */

var bu = bu || {};

bu.plugins = bu.plugins || {};
bu.plugins.navigation = {};

(function ($) {
	'use strict';

	// Simple pub/sub pattern
	bu.signals = (function () {
		var api = {};

		// Attach a callback function to respond for the given event
		api.listenFor = function (event, callback) {
			var listeners = this._listeners;
			if (listeners[event] === undefined) {
				listeners[event] = [];
			}

			listeners[event].push(callback);
		};

		// Broadcast a specific event, optionally providing context data
		api.broadcast = function (event, data) {
			var i, listeners = this._listeners;
			if (listeners[event]) {
				for (i = 0; i < listeners[event].length; i = i + 1) {
					listeners[event][i].apply(this, data || []);
				}
			}
		};

		// Objects that wish to broadcast signals must register themselves first
		return {
			register: function (obj) {
				obj._listeners = {};
				$.extend(true, obj, api);
			}
		};

	}());

	// Simple filter mechanism, modeled after Plugins API
	// @todo partially implemented
	bu.hooks = (function () {
		var filters = {};

		return {
			addFilter: function (name, func) {
				if (filters[name] === undefined) {
					filters[name] = [];
				}

				filters[name].push(func);
				return this;

			},
			applyFilters: function (name, obj) {
				if (filters[name] === undefined) {
					return obj;
				}

				var args = Array.prototype.slice.apply(arguments),
					extra = args.slice(1),
					rslt = obj,
					i;

				for (i = 0; i < filters[name].length; i = i + 1) {
					rslt = filters[name][i].apply(this, extra);
				}

				return rslt;
			}
		};
	}());
}(jQuery));

// =============================================//
// BU Navigation plugin settings & tree objects //
// =============================================//
(function ($) {

	// Plugin alias
	var Nav = bu.plugins.navigation;

	// Default global settings
	Nav.settings = {
		'lazyLoad': true,
		'showCounts': true,
		'showStatuses': true,
		'deselectOnDocumentClick': true
	};

	// DOM ready -- browser classes
	$(document).ready(function () {
		if( $.browser.msie === true && parseInt($.browser.version, 10) == 7 )
			$(document.body).addClass('ie7');
		if( $.browser.msie === true && parseInt($.browser.version, 10) == 8 )
			$(document.body).addClass('ie8');
		if( $.browser.msie === true && parseInt($.browser.version, 10) == 9 )
			$(document.body).addClass('ie9');
	});

	// Tree constructor
	Nav.tree = function( type, config ) {
		if (typeof type === 'undefined') {
			type = 'base';
		}

		return Nav.trees[type](config).initialize();
	};

	// Tree instances
	Nav.trees = {

		// ---------------------------------------//
		// Base navigation tree type - extend me! //
		// ---------------------------------------//
		base: function( config, my ) {
			var that = {};
			my = my || {};

			// Implement the signals interface
			bu.signals.register(that);

			// Instance settings
			that.config = $.extend({}, Nav.settings, config || {} );

			// Public data
			that.data = {
				treeConfig: {},
				rollback: undefined
			};

			// Aliases
			var c = that.config;
			var d = that.data;

			// Need valid tree element to continue
			var $tree = that.$el = $(c.el);

			// Prefetch tree assets
			if (c.themePath && document.images) {
				var themeSprite = new Image();
				var themeLoader = new Image();
				themeSprite.src = c.themePath + "/sprite.png";
				themeLoader.src = c.themePath + "/throbber.gif";
			}

			// Allow clients to stop certain actions and UI interactions via filters
			var checkMove = function( m ) {
				var post, parent, isTopLevelMove, isVisible, wasTop;

				post = my.nodeToPost(m.o);
				isTopLevelMove = m.cr === -1;
				isVisible = post.post_meta['excluded'] === false || post.post_type === c.linksPostType;

				allowed = true;

				// Don't allow top level posts if global option prohibits it
				if (isTopLevelMove && isVisible && !wasTop && !c.allowTop) {
					// console.log('Move denied, top level posts cannot be created!');
					// @todo pop up a friendlier notice explaining this
					allowed = false;
				}

				return bu.hooks.applyFilters( 'moveAllowed', allowed, m, that );
			};

			var canSelectNode = function( node ) {
				return bu.hooks.applyFilters( 'canSelectNode', node, that );
			};

			var canHoverNode = function( node ) {
				return bu.hooks.applyFilters( 'canHoverNode', node, that );
			};

			var canDragNode = function( node ) {
				return bu.hooks.applyFilters( 'canDragNode', node, that );
			};

			// jsTree Settings object
			d.treeConfig = {
				"plugins" : ["themes", "types", "json_data", "ui", "dnd", "crrm", "bu"],
				"core" : {
					"animation" : 0,
					"html_titles": true
				},
				"ui" : {
					"selected_parent_close": false
				},
				"themes" : {
					"theme": "bu",
					"load_css": false
				},
				"dnd" : {
					"drag_container": document
				},
				"types" : {
					"types" : {
						"default" : {
							"max_children"		: -1,
							"max_depth"			: -1,
							"valid_children"	: "all",
							"select_node"		: canSelectNode,
							"hover_node"		: canHoverNode,
							"start_drag"		: canDragNode
						},
						"page": {
							"max_children"		: -1,
							"max_depth"			: -1,
							"valid_children"	: "all",
							"select_node"		: canSelectNode,
							"hover_node"		: canHoverNode,
							"start_drag"		: canDragNode
						},
						"section": {
							"max_children"		: -1,
							"max_depth"			: -1,
							"valid_children"	: "all",
							"select_node"		: canSelectNode,
							"hover_node"		: canHoverNode,
							"start_drag"		: canDragNode
						},
						"link": {
							"max_children"		: 0,
							"max_depth"			: 0,
							"valid_children"	: "none",
							"select_node"		: canSelectNode,
							"hover_node"		: canHoverNode,
							"start_drag"		: canDragNode
						}
					}
				},
				"json_data": {
					"ajax" : {
						"url" : c.rpcUrl,
						"type" : "POST",
						"data" : function (n) {
							var post;

							if(n === -1) {
								post = {ID: 0};
							} else {
								post = my.nodeToPost(n);
							}

							return {
								child_of : post.ID,
								post_types : c.postTypes,
								post_statuses : c.postStatuses,
								instance : c.instance,
								prefix : c.nodePrefix,
								include_links: c.includeLinks
							};
						}
					},
					"progressive_render" : true
				},
				"crrm": {
					"move": {
						"default_position" : "first",
						"check_move": checkMove
					}
				},
				"bu": {
					"lazy_load": c.lazyLoad
				}
			};

			if( c.showCounts ) {
				// counting needs a fully loaded DOM
				d.treeConfig['json_data']['progressive_render'] = false;
			}

			if( c.initialTreeData ) {
				d.treeConfig['json_data']['data'] = c.initialTreeData;
			}

			// For meddlers
			d.treeConfig = bu.hooks.applyFilters( 'buNavTreeSettings', d.treeConfig, $tree );

			// ======= Public API ======= //

			that.initialize = function() {
				$tree.jstree( d.treeConfig );
				return that;
			};

			that.openPost = function (post, callback) {
				var $node = my.getNodeForPost(post);
				callback = callback || $.noop;

				if ($node) {
					$tree.jstree('open_node', $node, callback, true);
				} else {
					return false;
				}
			}

			that.selectPost = function( post, deselect_all ) {
				deselect_all = deselect_all || true;
				var $node = my.getNodeForPost(post);

				if (deselect_all) {
					$tree.jstree('deselect_all');
				}

				$tree.jstree('select_node', $node);
			};

			that.getSelectedPost = function() {
				var $node = $tree.jstree('get_selected');
				if ($node.length) {
					return my.nodeToPost($node);
				}
				return false;
			};

			that.deselectAll = function () {
				$tree.jstree('deselect_all');
			};

			that.getPost = function( id ) {
				var $node = my.getNodeForPost(id);
				if ($node) {
					return my.nodeToPost($node);
				}
				return false;
			};

			// Custom version of jstree.get_json, optimized for our needs
			that.getPosts = function( child_of ) {
				var result = [], current_post = {}, parent, post_id, post_type;

				if (child_of) {
					parent = $.jstree._reference($tree)._get_node('#' + child_of);
				} else {
					parent = $tree;
				}

				// Iterate over children of current node
				parent.find('> ul > li').each(function (i, child) {
					child = $(child);

					current_post = my.nodeToPost(child);

					// Recurse through children if this post has any
					if( child.find('> ul > li').length ) {
						current_post.children = that.getPosts(child.attr('id'));
					}

					// Store post + descendents
					result.push(current_post);
				});

				// Result = post tree starting with child_of
				return result;
			};

			that.showAll = function() {
				$tree.jstree('open_all');
			};

			that.hideAll = function() {
				$tree.jstree('close_all');
			};

			that.getPostLabel = function( post ) {

				var $node = my.getNodeForPost( post );
				return $tree.jstree('get_text', $node );

			};

			that.setPostLabel = function( post, label ) {

				var $node = my.getNodeForPost( post );
				$tree.jstree('set_text', $node, label );

			};

			that.insertPost = function( post, after_insert ) {
				if (typeof post === 'undefined') {
					throw new TypeError('Post argument for insertPost must be defined!');
				}

				var $inserted, $parent, $which, parent, orderIndex, args, node, pos;

				// Assert parent and menu order values exist and are valid
				post.post_parent = post.post_parent || 0;
				post.menu_order = post.menu_order || 1;

				// Translate post parent field to node
				if (post.post_parent) {
					$parent = my.getNodeForPost( post.post_parent );
					parent = that.getPost( post.post_parent );
				} else {
					$parent = $tree;
				}

				// Post will be first
				if (1 == post.menu_order) {
					$which = $parent.find('> ul > li').get(0);
					pos = 'before';
				} else {
					// Translate menu order to list item index of sibling to insert post after
					orderIndex = post.menu_order - 2;
					if (orderIndex >= 0) {
						$which = $parent.find('> ul > li').get(orderIndex);
						pos = 'after';
					}
				}

				// No siblings in destination
				if (!$which) {
					$which = $parent;
					pos = 'inside';
				}

				// Setup create args based on values translated from parent/menu_order
				args = {
					which: $which,
					position: pos,
					callback: after_insert || function($node) { $tree.jstree('deselect_all'); $tree.jstree('select_node', $node); }
				};

				post = bu.hooks.applyFilters('preInsertPost', post, parent );

				// Translate post object to node format for jstree consumption
				node = my.postToNode( post );

				// Create tree node and update with insertion ID if post ID was not previously set
				$inserted = $tree.jstree('create_node', args.which, args.position, node, args.callback);
				if (!post.ID) {
					post.ID = $inserted.attr('id');
				}

				return post;
			};

			that.updatePost = function( post ) {
				var $node = my.getNodeForPost( post ),
					original, updated;

				if ($node) {

					// Merge original values with updates
					original = my.nodeToPost($node);
					updated = $.extend(true, {}, original, post);

					// Set node text with navigation label
					$tree.jstree('set_text', $node, updated.post_title);

					// Type coercion
					updated.post_parent = parseInt(updated.post_parent, 10);
					updated.menu_order = parseInt(updated.menu_order, 10);

					// Update DOM data attribute
					$node.data('post', updated);

					// Refresh post status badges (recursively)
					// @todo move to callback
					if (c.showStatuses) {
						$node.find('li').andSelf().each(function (){
							setStatusBadges($(this));
						});
					}

					that.broadcast('postUpdated', [updated]) ;

					return updated;

				}

				return false;
			};

			// Remove post
			that.removePost = function( post ) {
				var node;

				if ( post && typeof post === 'undefined' ) {
					node = $tree.jstree('get_selected');
					post = my.nodeToPost(node);
				} else {
					node = my.getNodeForPost( post );
				}

				$tree.jstree('remove', node );

			};

			// Get post ancestors (by title)
			that.getAncestors = function( postID ) {
				var $node = my.getNodeForPost( postID );
				return $tree.jstree('get_path', $node);
			};

			// Save tree state
			that.save = function() {

				// Cache current rollback object
				d.rollback = $tree.jstree( 'get_rollback' );

			};

			// Restore tree state
			that.restore = function() {
				if (typeof d.rollback === 'undefined')
					return;

				// HACK: Don't restore previous selections by removing them before rolling back
				// jstree has some buggy behavior with the ui/dnd plugins and selections
				// These bugs can be worked around by not attempting to restore selections
				// on rollbacks.

				// @todo fix the buggy behavior rather then hacking it here
				// @todo look at 1.0 release of jstree to see if it has been fixed
				d.rollback.d.ui.selected = $([]);

				// Run rollback
				$.jstree.rollback(d.rollback);

				// Reset cached rollback
				d.rollback = $tree.jstree('get_rollback');

			};

			that.lock = function() {
				$tree.jstree('lock');
			};

			that.unlock = function() {
				$tree.jstree('unlock');
			};

			// ======= Protected ======= //

			my.nodeToPost = function( node ) {
				if (typeof node === 'undefined')
					throw new TypeError('Invalid node!');

				var id, post;

				id = node.attr('id');
				post = $.extend({}, true, node.data('post'));

				// ID processing
				if (id.indexOf('post-new') === -1) {
					id = parseInt(my.stripNodePrefix(id),10);
				}

				// Populate dynamic fields with tree state
				post.ID = id;
				post.post_title = $tree.jstree('get_text', node);
				post.menu_order = node.index() + 1;

				// Type coercion
				post.post_parent = parseInt(post.post_parent, 10);
				post.originalParent = parseInt(post.originalParent, 10);
				post.originalOrder = parseInt(post.originalOrder, 10);

				post.post_meta = post.post_meta || {};

				return bu.hooks.applyFilters('nodeToPost', post, node);
			};

			my.postToNode = function( post, hasChildren ) {
				if (typeof post === 'undefined')
					throw new TypeError('Invalid post!');

				var default_post, p, node, post_id,
					hasChildren = hasChildren || false;

				// @todo refactor to getDefaultPost method
				default_post = {
					post_title: '(no title)',
					post_content: '',
					post_status: 'new',
					post_type: 'page',
					post_parent: 0,
					menu_order: 1,
					post_meta: {},
					url: ''
				};

				p = $.extend({}, default_post, post);

				// Generate post ID if none previously existed
				post_id = p.ID ? c.nodePrefix + p.ID : 'post-new-' + my.getNextPostID();

				node = {
					"attr": {
						"id": post_id,
						"rel" : my.getRelAttrForPost(post, hasChildren)
					},
					"data": {
						"title": p.post_title
					},
					"metadata": {
						"post": p
					}
				};

				return bu.hooks.applyFilters('postToNode', node, p);
			};

			my.getNodeForPost = function( post ) {
				if (typeof post === 'undefined')
					return false;

				var node_id, $node;

				// Allow post object or ID
				if (post && typeof post === 'object') {
					node_id = post.ID.toString();
					if (node_id.indexOf('post-new') === -1) {
						node_id = c.nodePrefix + node_id;
					}
				} else {
					node_id = post.toString();
					if (node_id.indexOf('post-new') === -1) {
						node_id = c.nodePrefix + node_id;
					}
				}

				$node = $.jstree._reference($tree)._get_node('#' + node_id);

				if ($node.length) {
					return $node;
				}

				return false;
			};

			my.getNextPostID = function() {
				var newPosts = $('[id*="post-new-"]');
				return newPosts.length;

			};

			my.stripNodePrefix = function( str ) {
				return str.replace( c.nodePrefix, '');
			};

			my.getRelAttrForPost = function(post, hasChildren) {
				var rel;
				
				if (hasChildren) {
					rel = 'section';
				} else {
					rel = post.post_type == c.linksPostType ? 'link' : 'page';
				}
				return rel;
			}

			// ======= Private ======= //

			var calculateCounts = function($node, includeDescendents) {
				var count;

				// Use DOM to calculate descendent count
				count = $node.find('li').length;

				// Update markup
				setCount($node, count);

				if (includeDescendents) {
					// Recurse to children
					$node.find('li').each(function (){
						calculateCounts($(this));
					});
				}
			};

			var setCount = function ($node, count) {
				var $a = $node.children('a'), $count;
				if ($a.children('.title-count').children('.count').length === 0) {
					$a.children('.title-count').append('<span class="count"></span>');
				}

				$count = $a.find('> .title-count > .count').empty();

				if (count) {
					// Set current count
					$count.text('(' + count + ')');
				} else {
					// Remove count if empty
					$count.text('');
				}
			};

			// List of status badges
			var getStatusBadges = function (inherited) {
				var defaults, _builtins, badges, status, results;

				inherited = inherited || false;
				_builtins = {
					'excluded': { 'class': 'excluded', 'label': c.statusBadgeExcluded, 'inherited': false },
					'protected': { 'class': 'protected', 'label': c.statusBadgeProtected, 'inherited': false }
				};

				badges = bu.hooks.applyFilters( 'navStatusBadges', _builtins );
				results = badges;

				if (inherited) {
					results = {};
					for (status in badges) {
						if (badges[status].hasOwnProperty('inherited') && badges[status].inherited)
							results[status] = badges[status];
					}
				}
				return results;
			}

			// Update post meta that may change depending on ancestors
			var calculateInheritedStatuses = function ($node) {
				var post, badges, status, inheriting_status;

				post = my.nodeToPost($node);
				badges = getStatusBadges({'inherited': true});

				for (status in badges) {
					inheriting_status = $node.parentsUntil('#'+$tree.attr('id'), 'li').filter(function () {
						return $(this).data('post')['post_meta'][status] || $(this).data('inherited_'+status);
					}).length;

					// Cache inherited statuses on DOM node
					if (inheriting_status) {
						$node.data('inherited_'+status, true);
					} else {
						$node.removeData('inherited_'+status);
					}
				}
			};

			// Convert post meta data in to status badges
			var setStatusBadges = function ($node) {
				var $a, post, $statuses, statuses, badges, status, val, i;

				// Prep the DOM
				$a = $node.children('a');
				if ($a.children('.post-statuses').length === 0) {
					$a.append('<span class="post-statuses"></span>');
				}
				$statuses = $a.children('.post-statuses').empty();

				post = my.nodeToPost( $node );
				statuses = [];

				// Calculate statuses that can be inherited from ancestors
				calculateInheritedStatuses($node);

				// Push actual post statuses first
				if (post.post_status != 'publish')
					statuses.push({ "class": post.post_status, "label": post.post_status });

				// Push any additional status badges
				badges = getStatusBadges();
				for (status in badges) {
					val = post.post_meta[status] || $node.data('inherited_'+status);
					if (val)
						statuses.push({ "class": badges[status]['class'], "label": badges[status]['label'] });
				}

				// Append markup
				for (i = 0; i < statuses.length; i = i + 1) {
					$statuses.append('<span class="post_status ' + statuses[i]['class'] + '">' + statuses[i]['label'] + '</span>');
				}

			};

			var updateBranch = function ( $post ) {
				var $section;

				// Maybe update rel attribute
				if ($post.children('ul').length === 0) {
					$post.attr('rel', 'page');
				} else {
					$post.attr('rel', 'section');
				}

				// Recalculate counts
				if (c.showCounts) {

					// Start from root
					if ($post.parent('ul').parent('div').attr('id') != $tree.attr('id')) {
						$section = $post.parents('li:last');
					} else {
						$section = $post;
					}

					calculateCounts($section, true);
				}
			};

			// ======= jsTree Event Handlers ======= //

			// Tree instance is loaded (before initial opens/selections are made)
			$tree.bind('loaded.jstree', function( event, data ) {

				// jstree breaks spectacularly if the stylesheet hasn't set an li height
				// when the tree is created -- this is what they call a hack...
				var $li = $tree.find("> ul > li:first-child");
				var nodeHeight = $li.height() >= 18 ? $li.height() : 32;
				$tree.jstree('data').data.core.li_height = nodeHeight;

				that.broadcast('postsLoaded');
			});

			// Run after initial node openings and selections have completed
			$tree.bind('reselect.jstree', function( event, data ) {

				that.broadcast('postsSelected');

			});

			// Run after lazy load operation has completed
			$tree.bind('lazy_loaded.jstree', function (event, data) {

				that.broadcast('lazyLoadComplete');

			});

			// After node is loaded from server using json_data
			$tree.bind('load_node.jstree', function( event, data ) {
				if( data.rslt.obj !== -1 ) {
					var $node = data.rslt.obj;

					if (c.showCounts) {
						calculateCounts($node, true);
					}
				}
			});

			// Append extra markup to each tree node
			$tree.bind('clean_node.jstree', function( event, data ) {
				var $nodes = data.rslt.obj;

				// skip root node
				if ($nodes && $nodes !== -1) {
					$nodes.each(function(i, node) {
						var $node = $(node);

						// Only add once
						if ($node.data('buNavExtrasAdded')) return;

						// Status badges
						if (c.showStatuses) {

							// Append post statuses inside node anchor
							setStatusBadges($node);

						}

						$node.data('buNavExtrasAdded', true);

					});
				}
			});

			$tree.bind('before.jstree', function (event, data) {
				var $node;

				switch (data.func) {
					case 'select_node':
					case 'hover_node':
					case 'start_drag':
						// Restrict select, hover and drag operations for denied posts
						$node = data.inst._get_node(data.args[0]);
						if ($node && $node.hasClass('denied')) {
							return false;
						}
						break;
				}

			});

			$tree.bind('create_node.jstree', function(event, data ) {
				var $node = data.rslt.obj;
				var post = my.nodeToPost( $node );
				that.broadcast( 'postCreated', [ post ] );
			});

			$tree.bind('select_node.jstree', function(event, data ) {
				var post = my.nodeToPost(data.rslt.obj);
				that.broadcast('postSelected', [post]);
			});

			$tree.bind('create.jstree', function (event, data) {
				var	$node = data.rslt.obj,
					$parent = data.rslt.parent,
					position = data.rslt.position,
					post = my.nodeToPost($node),
					postParent = null;

				// Notify ancestors of our existence
				if( $parent !== -1 ) {
					postParent = my.nodeToPost($parent);
					updateBranch($parent);
				}

				// Set parent and menu order
				post['post_parent'] = postParent ? postParent.ID : 0;
				post['menu_order'] = position + 1;

				that.broadcast('postInserted', [post]);
			});

			$tree.bind('remove.jstree', function (event, data) {
				var $node = data.rslt.obj,
					post = my.nodeToPost($node),
					$oldParent = data.rslt.parent,
					child;

				// Notify former ancestors of our removal
				if( $oldParent !== -1 ) {
					updateBranch($oldParent);
				}

				that.broadcast('postRemoved', [post]);

				// Notify of descendent removals as well
				$node.find('li').each(function () {
					child = my.nodeToPost($(this));
					if (child) {
						that.broadcast('postRemoved', [child]);
					}
				});
			});

			$tree.bind('deselect_node.jstree', function(event, data ) {
				var post = my.nodeToPost( data.rslt.obj );
				that.broadcast('postDeselected', [post]);
			});

			$tree.bind('deselect_all.jstree', function (event, data) {
				that.broadcast('postsDeselected');
			});

			$tree.bind('move_node.jstree', function (event, data ) {
				var $moved = data.rslt.o;

				// Repeat move behavior for each moved node (handles multi-select)
				$moved.each(function (i, node) {
					var $node = $(node),
						post = my.nodeToPost( $node ),
						$newParent = data.rslt.np,
						$oldParent = data.rslt.op,
						menu_order = $node.index() + 1,
						parent_id = 0, oldParent, oldParentID = 0, oldOrder = 1;

					// Set new parent ID
					if( $tree.attr('id') !== $newParent.attr('id')) {
						// Notify new ancestors of changes
						updateBranch($newParent);
						parent_id = parseInt(my.stripNodePrefix($newParent.attr('id')),10);
					}

					// If we've changed sections, notify former ancestors as well
					if ($tree.attr('id') !== $oldParent.attr('id') &&
						!$newParent.is('#' + $oldParent.attr('id')) ) {
						updateBranch($oldParent);
						oldParent = my.nodeToPost( $oldParent );
						oldParentID = oldParent.ID;
					}

					oldOrder = post['menu_order'];

					// Extra post parameters that may be helpful to consumers
					post['post_parent'] = parent_id;
					post['menu_order'] = menu_order;

					that.updatePost(post);

					that.broadcast( 'postMoved', [post, oldParentID, oldOrder]);
				});
			});

			// Deselect all nodes on document clicks outside of a tree element or
			// context menu item
			var deselectOnDocumentClick = function (e) {
				if (typeof $tree[0] === 'undefined') {
					return;
				}

				var clickedTree = $.contains( $tree[0], e.target );
				var clickedMenuItem = $.contains( $('#vakata-contextmenu')[0], e.target );

				if (!clickedTree && !clickedMenuItem) {
					$tree.jstree('deselect_all');
				}
			};

			if (c.deselectOnDocumentClick) {
				$(document).bind( "click", deselectOnDocumentClick );
			}

			return that;
		},

		// ----------------------------
		// Edit order (Navigation manager) tree
		// ----------------------------
		navman: function( config, my ) {
			var that = {};
			my = my || {};

			that = Nav.trees.base( config, my );

			var $tree = that.$el;
			var d = that.data;
			var c = that.config;

			var showOptionsMenu = function (node) {
				var post = my.nodeToPost(node);

				var options = {
					"edit" : {
						"label" : c.optionsEditLabel,
						"action" : editPost
					},
					"view" : {
						"label" : c.optionsViewLabel,
						"action" : viewPost
					},
					"remove" : {
						"label" : c.optionsTrashLabel,
						"action" : removePost
					}
				};

				// Can't view an item with no URL
				if (!post.url) {
					delete options['view'];
				}

				// Special behavior for links
				if (post.post_type === c.linksPostType) {
					// Links are permanently deleted -- "Move To Trash" is misleading
					options['remove']['label'] = c.optionsDeleteLabel;
				}

				return bu.hooks.applyFilters('navmanOptionsMenuItems', options, node);
			};

			var editPost = function( node ) {
				var post = my.nodeToPost(node);
				that.broadcast('editPost', [post]);
			};

			var viewPost = function (node) {
				var post = my.nodeToPost(node);
				if (post.url) {
					window.open(post.url);
				}
			};

			var removePost = function( node ) {
				var post = my.nodeToPost(node);
				that.removePost(post);
			};

			// Add context menu plugin
			d.treeConfig["plugins"].push("contextmenu");

			d.treeConfig["contextmenu"] = {
				'show_at_node': false,
				"items": showOptionsMenu
			};

			// Prevent default right click behavior
			$tree.bind('loaded.jstree', function(e,data) {

				$tree.undelegate('a', 'contextmenu.jstree');

			});

			// Append options menu to each node
			$tree.bind('clean_node.jstree', function( event, data ) {
				var $nodes = data.rslt.obj;
				// skip root node
				if ($nodes && $nodes != -1) {
					$nodes.each(function(i, node) {
						var $node = $(node);
						var $a = $node.children('a');

						if( $a.children('.edit-options').length ) return;

						var $button = $('<button class="edit-options"><ins class="jstree-icon">&#160;</ins>' + c.optionsLabel + '</button>');
						var $statuses = $a.children('.post-statuses');

						// Button should appear before statuses
						if( $statuses.length ) {
							$statuses.before($button);
						} else {
							$a.append($button);
						}

					});
				}
			});

			// @todo move all of this custom contextmenu behavior to our fork of the
			// jstree contextmenu plugin
			var currentMenuTarget = null;

			$tree.delegate(".edit-options", "click", function (e) {
				e.preventDefault();
				e.stopPropagation();

				var pos, width, height, top, left, obj;

				// Calculate location
				pos = $(this).offset();
				width = $(this).outerWidth();
				height = $(this).outerHeight();
				top = pos.top;
				left = pos.left;
				top = top + height;
				left = (left + width) - 180;

				obj = $(this).closest('li');

				$tree.jstree('deselect_all');
				$tree.jstree('select_node', obj );
				$tree.jstree('show_contextmenu', obj, left, top);

				$(this).addClass('clicked');

				if (currentMenuTarget && currentMenuTarget.attr('id') != obj.attr('id')) {
					removeMenu(currentMenuTarget);
				}
				currentMenuTarget = obj;
			});

			// Remove active state on edit options button when the menu is removed
			$(document).bind('context_hide.vakata', function(e, data){
				removeMenu(currentMenuTarget);
			});

			var removeMenu = function ( target ) {
				if (target) {
					target.find('> a > .edit-options').removeClass('clicked');
				}
			};

			$tree.addClass('bu-navman');

			return that;
		},

		// ----------------------------
		// Edit post tree
		// ----------------------------
		edit_post: function( config, my ) {
			my = my || {};

			// Functional inheritance
			var that = Nav.trees.base( config, my );

			// Aliases
			var d = that.data;
			var c = $.extend(that.config, config || {});	// instance configuration

			var $tree = that.$el;
			var currentPost = c.currentPost;

			// Extra configuration
			var extraTreeConfig = {};

			// Build initial open and selection arrays from current post / ancestors
			// Replaced by the loaded.jstreee callback below to handle cases where
			// ancestors and current page are not published.

//			var toOpen = [], i;
//
//			if (c.ancestors && c.ancestors.length) {
//				// We want old -> young, which is not how they're passed
//				var ancestors = c.ancestors.reverse();
//				for (i = 0; i < ancestors.length; i = i + 1) {
//					toOpen.push( '#' + c.nodePrefix + c.ancestors[i]['ID'] );
//				}
//			}
//			if (toOpen.length) {
//				extraTreeConfig['core'] = {
//					"initially_open": toOpen
//				};
//			}

			extraTreeConfig['dnd'] = {
				"drag_container": c.treeDragContainer
			};

			// Merge base tree config with extras
			$.extend( true, d.treeConfig, extraTreeConfig );

			// Assert current post for select, hover and drag operations
			var assertCurrentPost = function( node, inst ) {
				if (inst.$el.is(that.$el.selector)) {
					var postId = my.stripNodePrefix(node.attr('id'));
					return postId == currentPost.ID;
				}
			};

			bu.hooks.addFilter( 'canSelectNode', assertCurrentPost );
			bu.hooks.addFilter( 'canHoverNode', assertCurrentPost );
			bu.hooks.addFilter( 'canDragNode', assertCurrentPost );

			// The following logic will be simplified once we don't have
			// to handled unpublished content as special cases.
			// For right now, they are excluded from the AJAX calls to
			// list posts, which means we have to create any unpublished
			// ancestors as well as the current post (if it is new or unpublished)
			// client side to make sure they are represented in the tree.

			$tree.bind('loaded.jstree', function (e, data) {
				var ancestors, i;

				// Need to load and open ancestors before we can select current post
				if (c.ancestors && c.ancestors.length) {

					// We want old -> young, which is not how they're passed
					ancestors = c.ancestors.reverse();

					// Handles opening (and possibly inserting) post ancestors one by one
					openNextChild(0, ancestors);

				} else {

						// Current post is top level -- select or insert
						selectCurrentPost();

				}

			});

			/**
			 * Recursively load ancestors, opening and possibly inserting along the way.
			 *
			 * For now, unpublished content will not be represented in the tree passed to us
			 * from the server, so we need to enter this recursive callback waterfall to make
			 * sure all ancestors exist and are open before selecting the current post.
			 */
			var openNextChild = function (current, all) {
				var post = all[current];

				if (post) {
					if (that.openPost(post, function() { openNextChild( current + 1, all) }) === false ) {
						that.insertPost(post, function($node) { openNextChild(current + 1, all); });
					}
				} else {
					// No more ancestors ... we're safe to select the current post now
					selectCurrentPost();
				}
			}

			/**
			 * Select the current post, inserting if it does not already exist in the tree (i.e. new post, or unpublished post)
			 */
			var selectCurrentPost = function () {

				// Insert post if it isn't already represented in the tree (new, draft, or pending posts)
				var $current = my.getNodeForPost(currentPost);

				if (!$current) {
					// Insert and select self, then save tree state
					that.insertPost(currentPost, function($node) {
						that.selectPost(currentPost);
						that.save();
					});
				} else {
					that.selectPost(currentPost);
					that.save();
				}

			};

			// Public
			that.getCurrentPost = function() {
				var $node, post;

				$node = my.getNodeForPost(currentPost);

				if ($node) {
					post = my.nodeToPost( $node );
					return post;
				}

				return false;
			};

			that.setCurrentPost = function( post ) {
				currentPost = post;
			};

			$tree.addClass('bu-edit-post');

			return that;
		}
	};
})(jQuery);
