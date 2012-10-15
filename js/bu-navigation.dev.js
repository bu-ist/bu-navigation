/**
 * ========================================================================
 * BU Navigation plugin - main script
 * ========================================================================
 */
var bu = bu || {};
	bu.plugins = bu.plugins || {};
	bu.plugins.navigation = {};

(function($){

	// Simple pub/sub pattern
	bu.signals = (function() {
		var listeners = {};
		var that = this;

		return {
			listenFor: function( event, callback ) {

				if( typeof listeners[event] === 'undefined')
					listeners[event] = [];

				listeners[event].push( callback );
				return that;

			},
			broadcast: function( event, data ) {

				if( listeners[event] ) {
					for( var i = 0; i < listeners[event].length; i++) {
						listeners[event][i].apply( this, data || [] );
					}
				}
				return that;
			}
		};
	})();

	// Simple filter mechanism, modeled after Plugins API
	// @todo partially implemented
	bu.hooks = (function(){
		var filters = {};
		var that = this;

		return {
			addFilter: function( name, func ) {
				if( typeof filters[name] === 'undefined' )
					filters[name] = [];

				filters[name].push(func);
				return that;

			},
			applyFilters: function( name, obj ) {
				if( typeof filters[name] === 'undefined' )
					return obj;

				var args = Array.prototype.slice.apply(arguments);
				extra = args.slice(1);

				var i = 0;
				for( i = 0; i < filters[name].length; i++ ) {
					obj = filters[name][i].apply( this, extra );
				}

				return obj;
			}
		};
	})();

})(jQuery);

// =============================================//
// BU Navigation plugin settings & tree objects //
// =============================================//
(function($){

	// Plugin alias
	var Nav = bu.plugins.navigation;

	// Global plugin settings
	Nav.settings = buNavSettings || {};
	
	// Tree constructor
	Nav.tree = function( type, config ) {
		if( typeof type === 'undefined')
			type = 'base';

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

			// "Implement" the signals interface
			$.extend( true, that, bu.signals );

			// Configuration defaults
			var default_config = {
				el : '#navman_container',
				nodePrefix : 'p'
			};

			that.config = $.extend({}, default_config, config || {} );

			// Public data
			that.data = {
				treeConfig: {},
				rollback: undefined
			};

			// Aliases
			var s = Nav.settings;
			var c = that.config;
			var d = that.data;

			// Need valid tree element to continue
			var $tree = that.$el = $(c.el);

			if( $tree.length === 0 )
				throw new TypeError('Invalid DOM selector, can\'t create BU Navigation Tree');

			// jsTree Settings object
			d.treeConfig = {
				"plugins" : ["themes", "types", "json_data", "ui", "dnd", "crrm"],
				"core" : {
					"animation" : 0,
					"html_titles": true
				},
				"themes" : {
					"theme" : "bu",
					"url"	: s.themePath + "/style.css"
				},
				"types" : {
					"types" : {
						"default" : {
							"max_children"		: -1,
							"max_depth"			: -1,
							"valid_children"	: "all"
						},
						"page": {
							"max_children"		: -1,
							"max_depth"			: -1,
							"valid_children"	: "all"
						},
						"section": {
							"max_children"		: -1,
							"max_depth"			: -1,
							"valid_children"	: "all"
						},
						"link": {
							"max_children"		: 0,
							"max_depth"			: 0,
							"valid_children"	: "none"
						}
					}
				},
				"json_data": {
					"data" : s.initialTreeData,
					"ajax" : {
						"url" : s.rpcUrl,
						"type" : "POST",
						"data" : function (n) {
							return { id : n.attr ? n.attr("id") : 0 };
						}
					},
					"progressive_render" : true
				},
				"crrm": {
					"move": {
						"check_move": checkMove
					}
				}
			};

			if( s.showCounts ) {
				// counting needs a fully loaded DOM
				d.treeConfig['json_data']['progressive_render'] = false;
			}

			// ======= Public API ======= //

			that.initialize = function() {
				$tree.jstree( d.treeConfig );
				return that;
			};

			that.selectPost = function( post ) {
				var node = my.getNodeForPost( post );
				$tree.jstree( 'select_node', node );
			};

			that.getSelected = function() {
				var node = $tree.jstree('get_selected');
				return my.nodeToPost( node );
			};

			that.getPosts = function() {
				return $tree.jstree( 'get_json', -1 );
			};
			
			that.showAll = function() {
				$tree.jstree('open_all');
			};

			that.hideAll = function() {
				$tree.jstree('close_all');
			};

			that.insertPost = function( post, args ) {
				var defaults = {
					position: 'after',
					which: null,
					skip_rename: true,
					callback: null
				};

				var a = $.extend( defaults, args );
				var node = my.postToNode( post );

				$tree.jstree( 'create', a.which, a.position, node, a.callback, a.skip_rename );

				that.broadcast('insertPost', [post]);

				return node['attr']['id'];
			};

			that.updatePost = function( post ) {
				var $node = my.getNodeForPost( post );

				if( $node ) {

					// Merge original values with updates
					origPost = my.nodeToPost( $node );
					updated = $.extend(true, {}, origPost, post);

					// Set node text with navigation label
					$tree.jstree('set_text', $node, updated.title );

					// Update metadata stored with node
					// @todo do this dynamically by looping through post props
					$node.data('post_content', updated.content);
					$node.data('post_title', updated.title);
					$node.data('post_status', updated.status);
					$node.data('post_type', updated.type);
					$node.data('post_parent', updated.parent);
					$node.data('menu_order', updated.menu_order);
					$node.data('post_meta', updated.meta);

				}

				that.broadcast('updatePost', [ updated ]);
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

				// @todo protect against empty node

				$tree.jstree('remove', node );

				that.broadcast('removePost', [post]);
			};

			// Get post ancestors (by title)
			that.getAncestors = function( postID ) {
				var $node = my.getNodeForPost( postID );
				return $tree.jstree('get_path', $node);
			};

			// Save tree state
			that.save = function() {
				d.rollback = $tree.jstree( 'get_rollback' );
			};

			// Restore tree state
			that.restore = function() {
				if( typeof d.rollback === 'undefined' )
					return;

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
				d.rollback.d.ui.selected = $([]);

				// Run rollback
				$.jstree.rollback(d.rollback);

			};

			// ======= Protected ======= //

			my.nodeToPost = function( node ) {
				if( typeof node === 'undefined' )
					throw new TypeError('Invalid node!');

				var id = node.attr('id');

				if( id.indexOf('post-new') === -1 )
					id = my.stripNodePrefix( id );

				var post = {
					ID: id,
					title: $tree.jstree('get_text', node ),
					content: node.data('post_content'),
					status: node.data('post_status'),
					type: node.data('post_type'),
					parent: node.data('post_parent'),
					menu_order: node.data('menu_order'),
					meta: node.data('post_meta') || {}
				};
				return bu.hooks.applyFilters('nodeToPost',post);

			};

			my.postToNode = function( post, args ) {
				if( typeof post === 'undefined' )
					throw new TypeError('Invalid post!');

				var default_args = {
					'hasChildren': false,
					'parentId': 0
				};
				var a = $.extend({}, default_args, args || {});

				var default_post = {
					ID: my.getNextPostID(),
					title: 'Untitled Post',
					content: '',
					status: 'new',
					type: 'page',
					parent: 0,
					menu_order: 0,
					meta: {}
				};

				var p = $.extend({}, default_post, post);

				// @todo real rel logic:
				// - has children = section
				// - post type is link = link
				// - anything else = page
				var rel = p.type;

				var data = {
					"attr": {
						"id": (p.ID) ? c.nodePrefix + p.ID : 'post-new-' + p.ID,
						"rel" : rel
					},
					"data": {
						"title": p.title
					},
					"metadata": {
						"post_status": p.status,
						"post_type": p.type,
						"post_content": p.content,
						'post_meta': p.meta
					}
				};

				return bu.hooks.applyFilters('postToNode', data );

			};

			my.getNodeForPost = function( post ) {
				if( typeof post === 'undefined' )
					throw new TypeError('Invalid post!');

				var node_id;

				// @todo clean up type coercion
				if( post && typeof post === 'object' ) {
					node_id = post.ID;
					if( node_id.indexOf('post-new') === -1 ) {
						node_id = c.nodePrefix + node_id;
					}
				} else {
					node_id = post;
					if( node_id.indexOf('post-new') === -1 ) {
						node_id = c.nodePrefix + node_id;
					}
				}

				var $node = $.jstree._reference($tree)._get_node( '#' + node_id );
				if( $node.length )
					return $node;

				return false;
			};

			my.getNextPostID = function() {
				var newPosts = $('[id*="post-new-"]');
				return newPosts.length;

			};
			
			my.stripNodePrefix = function( str ) {
				return str.replace( c.nodePrefix, '');
			};

			// ======= Private ======= //

			var lazyLoad = function() {

				// Lazy loading causes huge performance issues in IE < 8
				if( $.browser.msie === true &&  parseInt($.browser.version, 10) < 8 )
					return;

				// Start lazy loading once tree is fully loaded
				$tree.find('ul > .jstree-closed').each( function(){
					var $node = $(this);
					// Load using API -- they require callback functions, but we're
					// handling actions in the load_node.jstree even handler below
					// so we just pass empty functions
					$tree.jstree('load_node', $node, function(){}, function(){} );
				});
			};

			var calculateCounts = function($node, includeDescendents) {
				if( typeof includeDescendents === 'undefined' )
					includeDescendents = true;

				var count = $node.find('li').length;
				var $a = $node.children('a');

				if(count) {

					var $count = $a.children('.count');

					if($count.length === 0) {

						var $options = $a.children('.edit-options');
						$count = $(' <span class="count">');

						// Count should appear before statuses
						if( $options.length ) {
							$options.before($count);
						} else {
							$a.append($count);
						}

					}

					$count.text('(' + count + ')');

				} else {

					// Remove count if empty
					$a.children('.count').remove();

				}

				// Recurse to all descendents
				if(includeDescendents) {
					$node.find('> ul > li').each(function(){
						calculateCounts($(this));
					});
				}
			};

			var appendPostStatus = function( $node ) {
				var post_status = $node.data('post_status') || 'publish';
				var $a = $node.children('a');

				if( $a.children('post-statuses').length === 0 ) {
					$a.append(' <span class="post-statuses">');
				}

				if(post_status != 'publish') $a.children('.post-statuses').append(' <span class="post_status ' + post_status + '">' + post_status + '</span>');
			};

			var checkMove = function( m ) {
				var attempted_parent_id = m.np.attr('id');
				var allowed = true;

				// Don't allow top level posts if global option prohibits it
				if(m.cr === -1 && ! Nav.settings.allowTop ) {
					// console.log('Move denied, top level posts cannot be created!');
					// @todo pop up a friendlier notice explaining this
					allowed = false;
				}

				return bu.hooks.applyFilters( 'moveAllowed', allowed, m );
			};

			// ======= jsTree Event Handlers ======= //

			// Tree instance is loaded (before initial opens/selections are made)
			$tree.bind('loaded.jstree', function( event, data ) {
				that.broadcast( 'postsLoaded' );
			});

			// Post initial opens/selections are made
			$tree.bind('reselect.jstree', function( event, data ) {
				if(s.lazyLoad) {
					lazyLoad();
				}
				that.broadcast( 'postsSelected' );
			});

			// After node is loaded from server using json_data
			$tree.bind('load_node.jstree', function( event, data ) {
				if( data.rslt.obj !== -1 ) {
					var $node = data.rslt.obj;

					if( s.showCounts ) {
						calculateCounts( $node );
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

						// Append post statuses inside node anchor
						if( $node.find('> a > .post-statuses').length === 0 ) {
							appendPostStatus($node);
						}
					});
				}
			});

			$tree.bind('create_node.jstree', function(event, data ) {
				var $node = data.rslt.obj;
				var post = my.nodeToPost( $node );
				that.broadcast( 'postCreated', [ post ] );
			});

			$tree.bind('select_node.jstree', function(event, data ) {
				var post = my.nodeToPost(data.rslt.obj);
				that.broadcast( 'selectPost', [ post, that ]);
			});

			$tree.bind('deselect_node.jstree', function(event, data ) {
				var post = my.nodeToPost( data.rslt.obj );
				that.broadcast( 'deselectPost', [ post, that ]);
			});

			$tree.bind('move_node.jstree', function(event, data ) {
				var $parent = data.rslt.np,
					$oldparent = data.rslt.op,
					menu_order = data.rslt.o.index() + 1,
					parent_id;

				// Set new parent ID
				if( $tree.attr('id') == $parent.attr('id')) {
					parent_id = 0;
				} else {
					parent_id = parseInt(my.stripNodePrefix($parent.attr('id') ),10);
				}

				// Recalculate counts
				$newsection = $parent.parentsUntil($tree,'li');
				$oldsection = $oldparent.parentsUntil($tree,'li');
				$newsection = $newsection.length ? $newsection.last() : $parent;
				$oldsection = $oldsection.length ? $oldsection.last() : $oldparent;

				if( $oldsection.is($newsection) ) {
					calculateCounts($newsection);
				} else {
					calculateCounts($oldsection);
					calculateCounts($newsection);
				}

				// Extra post parameters that may be helpful to consumers
				var post = my.nodeToPost( data.rslt.o );
				post['parent'] = parent_id;
				post['menu_order'] = menu_order;

				that.updatePost(post);
				that.broadcast( 'postMoved', [post, parent_id, menu_order]);
			});

			$(document).bind('drag_start.vakata', function(event, data) {
				var $node = data.data.obj;
				$node.addClass('bu-dnd-placeholder');
			});

			$(document).bind('drag_stop.vakata', function(event, data) {
				var $node = data.data.obj;
				$node.removeClass('bu-dnd-placeholder');
			});

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

			// Adds context menu plugin and edit/remove post events
			d.treeConfig["plugins"].push("contextmenu");

			d.treeConfig["contextmenu"] = {
				'show_at_node': false,
				"items": function() {
					return {
						"edit" : {
							"label" : "Edit",
							"action" : editPost,
							"icon" : "remove"
						},
						"remove" : {
							"label" : "Remove",
							"action" : removePost
						}
					};
				}
			};

			var editPost = function( node ) {
				var post = my.nodeToPost( node );
				that.broadcast( 'editPost', [ post ]);
			};

			var removePost = function( node ) {
				var post = my.nodeToPost( node );
				that.removePost( post );
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

						var $button = $('<button class="edit-options"><ins class="jstree-icon">&#160;</ins>options</button>');
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

			$tree.delegate(".edit-options", "click", function(e){
				e.preventDefault();
				e.stopPropagation();

				$tree.jstree('deselect_all');

				var pos = $(this).offset();
				var yOffset = $(this).height() + 5;
				var obj = $(this).parent('a').parent('li');

				$(this).addClass('clicked');
				$tree.jstree('select_node', obj );
				$tree.jstree('show_contextmenu', obj, pos.left, pos.top + yOffset );
			});

			$tree.bind('deselect_all.jstree', function(e, data){
				var $node = data.rslt.obj;

				if( $node.attr('id') !== $tree.attr('id') ) {
					$node.find('> a > .edit-options').removeClass('clicked');
				}
			});

			// If the event doesn't contain the current selection, deselect all
			$(document).bind("mousedown", function (e) {
				var $selected = $tree.jstree('get_selected', $tree);
				var $match = $selected.filter( e.target );
				
				if($match.length === 0) {
					$tree.jstree('deselect_all');
				}
			});

			return that;
		},

		// ----------------------------
		// Edit post tree
		// @todo
		//	- prevent deseleciton of current post (lost it when we triggered deselect_all on document.body click)
		// ----------------------------
		edit_post: function( config, my ) {
			my = my || {};

			// Functional inheritance
			var that = Nav.trees.base( config, my );

			// Aliases
			var d = that.data;
			var c = $.extend(that.config, config || {});	// instance configuration
			var s = Nav.settings;	// global plugin settings

			var $tree = that.$el;
			var currentPost = s.currentPost;
			var currentNodeId = c.nodePrefix + currentPost;

			// Extra configuration
			var extraTreeConfig = {
				"types": {
					"types": {}
				}
			};

			// Build initial open and selection arrays from current post / ancestors
			var toSelect = [],
				toOpen = [],
				i;
			if ( currentPost ) {
				toSelect.push( '#' + currentNodeId );
				if ( s.ancestors && s.ancestors.length ) {
					// We want old -> young, which is not how they're passed
					var ancestors = s.ancestors.reverse();
					for (i = 0; i < ancestors.length; i++ ) {
						toOpen.push( '#' + c.nodePrefix + s.ancestors[i] );
					}
				}
			}
			if ( toSelect.length ) {
				extraTreeConfig['ui'] = {
					"initially_select": toSelect
				};
			}
			if ( toOpen.length ) {
				extraTreeConfig['core'] = {
					"initially_open": toOpen
				};
			}

			// Extend config object to restrict selection, hover and dragging to current post
			var assertCurrentPost = function( node ) {
				var post = my.nodeToPost(node);
				return post.ID == currentPost;
			};

			var typeChecks = {
				"select_node"		: assertCurrentPost,
				"hover_node"		: assertCurrentPost,
				"start_drag"		: assertCurrentPost
			};

			$.each( d.treeConfig['types']['types'], function( type, typeConfig ){
				extraTreeConfig['types']['types'][type] = $.extend(typeConfig,typeChecks);
			});

			// Merge base tree config with extras
			$.extend( true, d.treeConfig, extraTreeConfig );

			// Public
			that.getCurrentPost = function() {
				if( currentPost === null ||
					typeof currentPost === 'undefined' )
					return false;

				var $node = my.getNodeForPost( currentPost );
				var post = my.nodeToPost( $node );
				return post;
			};

			that.setCurrentPost = function( post ) {
				var $node = my.getNodeForPost( post );

				// Update all state vars relevant to current post
				currentPost = post.ID;
				currentNodeId = $node.attr('id');

				// Select and update tree state
				that.selectPost( post );
			};

			return that;
		}
	};
})(jQuery);