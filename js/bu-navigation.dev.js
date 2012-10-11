/**
 * ========================================================================
 * BU Navigation plugin - main script
 * ========================================================================
 */
var bu = bu || {};
	bu.navigation = {};

// -----------------------------
// BU Navigation global settings
// -----------------------------
(function($){

	// Plugin lias
	var Nav = bu.navigation;

	// Global plugin settings
	Nav.settings = buNavSettings || {};

})(jQuery);

// ----------------------------
// BU Navigation tree instances
// ----------------------------
(function($){

	// Plugin alias
	var Nav = bu.navigation;
	
	// Tree constructor
	Nav.tree = function( type, config ) {
		if( typeof type === 'undefined')
			throw new TypeError('Invalid navigation tree type!');

		return Nav.trees[type](config).initialize();
	};

	// ====== BU Navigation Tree Types ====== //
	Nav.trees = {

		// ----------------------------
		// Base navigation tree type - extend me!
		// ----------------------------
		base: function( config, my ) {
			var that = {};
			my = my || {};

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
			

			// Simple pub/sub pattern - belongs elsewhere
			var listeners = {};
			var events = ['selectPost','movePost'];

			that.listenFor = function( event, callback ) {

				// @todo verify event string from events var
				// @todo verify callback is callable

				if( typeof listeners[event] === 'undefined')
					listeners[event] = [];

				listeners[event].push( callback );

				return that;
			};

			that.broadcast = function( event, data ) {
				var i;

				if( listeners[event] ) {
					for( i = 0; i < listeners[event].length; i++) {
						listeners[event][i].apply( that, data );
					}
				}
			};

			// Need valid tree element to continue
			var $tree = that.$el = $(c.el);

			if( $tree.length === 0 )
				return false;

			// Need to implement this in a filterable way
			var checkMove = function(m) {
				var attempted_parent_id = m.np.attr('id');

				// Don't allow top level posts if global option prohibits it
				if(m.cr === -1 && ! Nav.settings.allowTop ) {
					// console.log('Move denied, top level posts cannot be created!');
					// @todo pop up a friendlier notice explaining this
					return false;
				}

				// @todo needs to be extendable
				// maybe register for the checkMove filter, and if any one of those
				// returns false then return false

				return true;
			};

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
							"valid_children"	: "all",
							"icon": {
								"image": s.themePath + "/icons/page_regular.png"
								// "position": "0 0"	// page section offset
							}
						},
						"page": {
							"max_children"		: -1,
							"max_depth"			: -1,
							"valid_children"	: "all",
							"icon": {
								"image": s.themePath + "/icons/page_regular.png"
								// "position": "0 0"	// page section offset
							}
						},
						"section": {
							"max_children"		: -1,
							"max_depth"			: -1,
							"valid_children"	: "all",
							"icon": {
								"image": s.themePath + "/icons/page_regular.png"
								// "position": "5px 5px"	// section icon offset
							}
						},
						"link": {
							"max_children"		: 0,
							"max_depth"			: 0,
							"valid_children"	: "none",
							"icon": {
								"image": s.themePath + "/icons/page_link.png"
								// "position": "5px 5px"	// link icon offset
							}
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

					// Set node text with navigation label
					$tree.jstree('set_text', $node, post.title );

					// Update metadata stored with node
					// @todo do this dynamically by looping through post props
					$node.data('post_content', post.content);
					$node.data('post_title', post.title);
					$node.data('post_status', post.status);
					$node.data('post_type', post.type);
					$node.data('post_parent', post.parent);
					$node.data('menu_order', post.menu_order);
					$node.data('post_meta', post.meta);

				}

				that.broadcast('updatePost', [ post ]);
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
					throw new TypeError('Invalid node argument!');

				var id = node.attr('id');

				if( id.indexOf('post-new') === -1 )
					id = my.stripNodePrefix( id );

				// @todo make the values returned from this
				// method extendable

				return {
					ID: id,
					title: $tree.jstree('get_text', node ),
					content: node.data('post_content'),
					status: node.data('post_status'),
					type: node.data('post_type'),
					parent: node.data('post_parent'),
					menu_order: node.data('menu_order'),
					meta: node.data('post_meta') || {}
				};
			};

			my.postToNode = function( post, args ) {
				if( typeof post === 'undefined' )
					throw new TypeError('Invalid post argument!');

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

				// @todo provide a flexible mechanism for external js
				// to filter this default data array

				return data;

			};

			my.getNodeForPost = function( post ) {
				if( typeof post === 'undefined' )
					throw new TypeError('Invalid post argument!');

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

			my.appendPostStatus = function( $node ) {
				var post_status = $node.data('post_status') || 'publish';
				if(post_status != 'publish') $node.children('a').after('<span class="post_status ' + post_status + '">' + post_status + '</span>');
			};
			
			my.stripNodePrefix = function( str ) {
				return str.replace( c.nodePrefix, '');
			};

			/**
			 * jsTree event handlers
			 */

			$tree.bind('loaded.jstree', function( event, data ) {
				that.broadcast( 'postsLoaded' );
			});

			$tree.bind('reselect.jstree', function( event, data ) {
				that.broadcast( 'postsSelected' );
			});

			// Used to append post status spans to each tree element
			$tree.bind('clean_node.jstree', function( event, data ) {
				var nodes = data.rslt.obj;
				if (nodes && nodes != -1) {
					nodes.each(function(i, li) {
						var $li = $(li);

						if($li.data('meta-loaded')) return;

						// Add post status span after link
						my.appendPostStatus($li);

						// Prevent duplicate spans from being added on moves
						$li.data('meta-loaded', true);
					});
				}
			});

			$tree.bind('create_node.jstree', function( event, data ) {
				var $node = data.rslt.obj;
				var post = my.nodeToPost( $node );
				that.broadcast( 'postCreated', [ post ] );
			});

			$tree.bind('select_node.jstree', function( event, data ) {
				var post = my.nodeToPost(data.rslt.obj);
				that.broadcast( 'selectPost', [ post ]);
			});

			$tree.bind('deselect_node.jstree', function( event, data ) {
				var post = my.nodeToPost( data.rslt.obj );
				that.broadcast( 'deselectPost', [ post ]);
			});

			$tree.bind('move_node.jstree', function( event, data ) {
				var $parent = data.rslt.np,
					menu_order = data.rslt.o.index() + 1,
					parent_id;

				// Set new parent ID
				if( $tree.attr('id') == $parent.attr('id')) {
					parent_id = 0;
				} else {
					parent_id = parseInt(my.stripNodePrefix($parent.attr('id') ),10);
				}

				// Extra post parameters that may be helpful to consumers
				var post = my.nodeToPost( data.rslt.o );
				post['parent'] = parent_id;
				post['menu_order'] = menu_order;

				that.updatePost(post);
				that.broadcast( 'postMoved', [post, parent_id, menu_order]);
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

			// Context menu event translators

			var editPost = function( node ) {
				var post = my.nodeToPost( node );
				that.broadcast( 'editPost', [ post ]);
			};

			var removePost = function( node ) {
				var post = my.nodeToPost( node );
				that.removePost( post );
			};

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
			var s = Nav.settings;	// global plugin settings

			var $tree = that.$el;
			var currentNodeId = c.nodePrefix + s.currentPost;

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
			if ( s.currentPost ) {
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
			var checkCurrentPost = function( node ) {
				var post = my.nodeToPost(node);
				return post.ID == s.currentPost;
			};
			var typeChecks = {
				"select_node"		: checkCurrentPost,
				"hover_node"		: checkCurrentPost,
				"start_drag"		: checkCurrentPost
			};
			$.each( d.treeConfig['types']['types'], function( type, typeConfig ){
				extraTreeConfig['types']['types'][type] = $.extend(typeConfig,typeChecks);
			});

			// Merge base tree config with extras
			$.extend( true, d.treeConfig, extraTreeConfig );

			// Public
			that.getCurrentPost = function() {
				if( s.currentPost === null ||
					typeof s.currentPost === 'undefined' )
					return false;

				var $node = my.getNodeForPost( s.currentPost );
				var post = my.nodeToPost( $node );
				return post;
			};

			that.setCurrentPost = function( post ) {
				var $node = my.getNodeForPost( post );

				// Update all state vars relevant to current post
				s.currentPost = post.ID;
				currentNodeId = $node.attr('id');

				// Select and update tree state
				that.selectPost( post );
			};

			return that;
		}
	};
})(jQuery);