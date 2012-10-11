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

	// Parse initial data string (pages to load) to object for json_data.data (due to WP deficiency)
	if( Nav.settings.initialTreeData && typeof Nav.settings.initialTreeData === 'string' )
		Nav.settings.initialTreeData = JSON.parse( Nav.settings.initialTreeData );

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
			
			var listeners = {};
			var events = ['selectPost','movePost'];

			// Simple pub/sub pattern

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

			// jsTree Settings object
			d.treeConfig = {
				"themes" : {
					"theme" : "bu", 
					"url"	: s.themesPath + "/bu-jstree/style.css"
				},
				"core" : {
					"animation" : 0,
					"html_titles": true
				},
				"plugins" : ["themes", "json_data", "ui", "types", "dnd", "crrm"],
				"types" : {
					"types" : {
						"default" : {
							"clickable"			: true,
							"renameable"		: true,
							"deletable"			: true,
							"creatable"			: true,
							"draggable"			: true,
							"max_children"		: -1,
							"max_depth"			: -1,
							"valid_children"	: "all",
							"icon": {
								"image": s.interfacePath + "/icons/page_regular.png"
							}
						},
						"page": {
							"clickable"			: true,
							"renameable"		: false,
							"deletable"			: true,
							"creatable"			: true,
							"draggable"			: true,
							"max_children"		: -1,
							"max_depth"			: -1,
							"valid_children"	: "all",
							"icon": {
								"image": s.interfacePath + "/icons/page_regular.png"
							}
						},
						"page_excluded": {
							"clickable"			: true,
							"renameable"		: false,
							"deletable"			: true,
							"creatable"			: true,
							"draggable"			: true,
							"max_children"		: -1,
							"max_depth"			: -1,
							"valid_children"	: "all",
							"icon": {
								"image": s.interfacePath + "/icons/page_hidden.png"
							}
						},
						"page_restricted": {
							"clickable"			: true,
							"renameable"		: false,
							"deletable"			: true,
							"creatable"			: true,
							"draggable"			: true,
							"max_children"		: -1,
							"max_depth"			: -1,
							"valid_children"	: "all",
							"icon": {
								"image": s.interfacePath + "/icons/page_restricted.png"
							}
						},
						"page_denied": {
							"clickable"			: false,
							"renameable"		: false,
							"deletable"			: false,
							"creatable"			: false,
							"draggable"			: false,
							"max_children"		: -1,
							"max_depth"			: -1,
							"valid_children"		: "all",
							"icon": {
								"image": s.interfacePath + "/icons/page_regular.png"
							}
						},
						"page_excluded_denied": {
							"clickable"			: false,
							"renameable"		: false,
							"deletable"			: false,
							"creatable"			: false,
							"draggable"			: false,
							"max_children"		: -1,
							"max_depth"			: -1,
							"valid_children"	: "all",
							"icon": {
								"image": s.interfacePath + "/icons/page_hidden.png"
							}
						},
						"page_restricted_denied": {
							"clickable"			: false,
							"renameable"		: false,
							"deletable"			: false,
							"creatable"			: false,
							"draggable"			: false,
							"max_children"		: -1,
							"max_depth"			: -1,
							"valid_children"	: "all",
							"icon": {
								"image": s.interfacePath + "/icons/page_restricted.png"
							}
						},
						"page_excluded_restricted": {
							"clickable"			: true,
							"renameable"		: false,
							"deletable"			: true,
							"creatable"			: true,
							"draggable"			: true,
							"max_children"		: -1,
							"max_depth"			: -1,
							"valid_children"	: "all",
							"icon": {
								"image": s.interfacePath + "/icons/page_hidden_restricted.png"
							}
						},
						"page_excluded_restricted_denied": {
							"clickable"			: false,
							"renameable"		: false,
							"deletable"			: false,
							"creatable"			: false,
							"draggable"			: false,
							"max_children"		: -1,
							"max_depth"			: -1,
							"valid_children"	: "all",
							"icon": {
								"image": s.interfacePath + "/icons/page_hidden_restricted.png"
							}
						},
						"link": {
							"icon": {
								"image": s.interfacePath + "/icons/page_white_link.png"
							},
							"max_children": 0
						},
						"link_restricted": {
							"icon": {
								"image": s.interfacePath + "/icons/page_white_link.png"
							},
							"max_children": 0
						},
						"link_excluded": {
							"icon": {
								"image": s.interfacePath + "/icons/page_white_link.png"
							},
							"max_children": 0
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
				}
			};

			// ======= Public ======= //

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

			that.getAncestors = function( postID ) {
				var $node = my.getNodeForPost( postID );

				var $ancestors = $node.parentsUntil( $tree, 'li' );
				var ancestorPosts = [];

				$ancestors.each(function(){
					ancestorPosts.push(my.nodeToPost($(this)));
				});

				return ancestorPosts;
			};

			that.save = function() {

				// Update rollback object
				d.rollback = $tree.jstree( 'get_rollback' );

			};

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

			$tree.bind( 'create_node.jstree', function( event, data ) {
				var $node = data.rslt.obj;
				var post = my.nodeToPost( $node );

				that.broadcast( 'postCreated', [ post ] );
			});

			$tree.bind( "select_node.jstree", function( event, data ) {
				var post = my.nodeToPost(data.rslt.obj);
				that.broadcast( 'selectPost', [ post ]);
			});

			$tree.bind( "deselect_node.jstree", function( event, data ) {
				var post = my.nodeToPost( data.rslt.obj );
				that.broadcast( 'deselectPost', [ post ]);
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
				"crrm": {
					"move": {
						"check_move": that.checkMove
					}
				}
			};

			// @todo push ui plugin if it isn't already there
			// extraTreeConfig["plugins"].push("ui");

			// Build initial open and selection arrays from current post / ancestors
			var toSelect = [], toOpen = [], i;

			if( s.currentPost ) {

				toSelect.push( '#' + currentNodeId );

				if( s.ancestors && s.ancestors.length ) {
					// We want old -> young, which is not how they're passed
					var ancestors = s.ancestors.reverse();
					for(i = 0; i < ancestors.length; i++ ) {
						toOpen.push( '#' + c.nodePrefix + s.ancestors[i] );
					}
				}

			}

			if( toSelect.length ) {
				extraTreeConfig['ui'] = {
					"initially_select": toSelect
				};
			}

			if( toOpen.length ) {
				extraTreeConfig['core'] = {
					"initially_open": toOpen
				};
			}

			// Merge base tree config with extras
			$.extend( true, d.treeConfig, extraTreeConfig );

			// jsTree Event Handlers

			// Run before ALL events -- allows us to interrupt selection, hover and drag behaviors
			$tree.bind('before.jstree', function( event, data ) {
				// Override default behavior for specific functions
				switch(data.func) {
					case "select_node":

						// The argument that contains the node being selected is inconsistent.
						// The following values occur:
						// 1. String of the HTML ID attribute of the list item -- "#<post-id>" (happens with initially_select)
						// 2. HTMLElement of the anchor (happens on manual selection)
						// 3. jQuery object of the selected list item (happens on node closing)
						if(typeof data.args[0] == 'string') {
							if('#' + currentNodeId != data.args[0]) {
								// console.log( 'Selection prohibited 1!' );
								// console.log('Current post: #' + currentNodeId );
								// console.log('Args[0]: ' + data.args[0] );
								return false;
							}
						}

						if(data.args[0] instanceof HTMLElement) {
							// console.log('Checking before selection:');
							// console.log('Current node ID: ' + currentNodeId );
							// console.log('Parent LI ID: ' + $(data.args[0]).parent('li').attr('id'));
							if(currentNodeId != $(data.args[0]).parent('li').attr('id')) {
								// console.log('Selection prohibited 2!');
								return false;
							}
						}

						if(data.args[0] instanceof jQuery) {
							if(currentNodeId != data.args[0].attr('id')) {
								// console.log('Selection prohibited 3!');
								return false;
							}
						}
						break;

						// Don't allow de-selection of post being edited
					case "deselect_node":
						if( currentNodeId == $(data.args[0]).parent('li').attr('id')) {
							// console.log('Preventing deselection!');
							return false;
						}
						break;

					case "hover_node":
					case "start_drag":
						var $node = $(data.args[0]);

						// Can only hover on current node
						if(currentNodeId != $node.parent('li').attr('id')) {
							// console.log('Preventing hover or drag!');
							return false;
						}
						break;
				}
			});

			$tree.bind('move_node.jstree', function( event, data ) {

				var $parent = data.rslt.np;
				var menu_order = data.rslt.o.index() + 1;
				var parent_id;

				// Set new parent ID
				if( $tree.attr('id') == $parent.attr('id')) {
					parent_id = 0;
				} else {
					parent_id = parseInt(my.stripNodePrefix( $parent.attr('id') ),10);
				}

				var post = my.nodeToPost( data.rslt.o );

				// Extra post parameters that may be helpful to consumers
				post['parent'] = parent_id;
				post['menu_order'] = menu_order;

				that.updatePost(post);
				that.broadcast( 'postMoved', [post, parent_id, menu_order]);

			});

			// @todo make sure there are no conflicts with buse implementation
			that.checkMove = function( m ) {
				var attempted_parent_id = m.np.attr('id');

				// Can ONLY move current post being edited
				if( s.currentPost != m.o.attr('id')) {
					// console.log('No moving allowed -- illegal selection!');
					return false;
				}

				// Don't allow top level posts if global option prohibits it
				if(m.cr === -1 && !Nav.settings.allowTop ) {
					// console.log('Move denied, top level posts cannot be created!');
					// @todo pop up a friendlier notice explaining this
					return false;
				}

				// @todo needs to be extendable

				return true;
			};

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