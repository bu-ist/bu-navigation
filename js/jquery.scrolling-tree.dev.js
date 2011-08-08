/**
 * This plugin turns an array, structured as a tree, into a control for navigating said selecting an item in it.
 * Tree navigation occurs horizontally, showing single sets of siblings in a vertical list.  The user may select
 * items here, or drill down into their children.  Static navigation items at the top allow the user to move
 * up one, or back to the top.  Breadcrumbs below the list allow the user to navigate anywhere above their
 * current position.
 *
 * I think this was written by BU -- gcorne (6/14/2011)
 *   
 * TODO:
 * 	- better data sourcing, add asynch loading of tree on demand.
 *    - should be flexible enough for client code to define custom loading mechanisms for complete control over
 *      asynch request patterns.
 */

if (typeof jQuery !== 'function') {throw 'ScrollingTree loaded before jQuery; be sure to load jQuery first.';}
(function($) {

	// Static plugin members and methods.
	$.scrollingTree = {
		defaults: {
			// Markup stuff
			selectedClass:      'st-selected', // class to highlight selected LIs
			btnTopClass:        'st-back-to-top', // class given to the "back to top" link
			btnBackClass:       'st-back-one', // class given to the "back one" link
			listClass:          'st-the-list', // class given to the <ul>
			btnDisabledClass:   'st-disabled', // class given to disabled links
			listContainerClass: 'st-list-container',
			breadcrumbsClass:   'st-breadcrumbs',
			drillClass:         'st-drill',
			bcLinkClass:        'st-breadcrumb-link',
			inputName:          'st-input',
			disabledNodeClass:  'st-disabled-node',
			
			// Positional stuff
			startNode:          0, // what node to have selected initially
			startView:          0, // what parent node to show children of initially
			
			// Behavioral stuff
			disabledNodeIds:    [],
			filterNode:         function(node) {return true;},
			
			// Callbacks
			getNodeId:          function(node) {return node.id;},
			getNodeName:        function(node) {return node.name;},
			getParentId:        function(node) {return node.parent_id;},
			nodeMarkup:         function(node) {return node.name;},
			getNumChildren:     function(node) {return node.num_children;}
		},
		instances: []
	};
	
	// jQuery hookup.
	$.fn.scrollingTree = function(tree, opts)
	{
		return this.each(function() {
			$.scrollingTree.instances.push(new scrollingTree($(this), tree, opts));
		});
	};
	
	
	var scrollingTree = function($c, tree, opts) {
		// Change view to show children of node_or_id.  Pass 'left' or 'right' into the third argument
		// to scroll in that direction.
		this.view = function(node_or_id, direction)
		{
			var node_id = (typeof node_or_id == 'object') ? this.getNodeId(node_or_id) : node_or_id;
			this.current_view = node_id;
			
			if (direction == 'left') {this.list_container.scrollLeft('fast');}
			if (direction == 'right') {this.list_container.scrollRight('fast');}
			this.draw();
		};
		
		
		// Call this to redraw using current state.
		this.draw = function()
		{
			this.container.trigger('beforeDraw');
			
			var bc, i, nodes, filtered = [];
			
			// Disable nav controls.
			this.link_top.addClass(this.opts.btnDisabledClass);
			this.link_back.addClass(this.opts.btnDisabledClass);
			
			// Build up new list.
			this.the_list = $('<ul class="' + this.opts.listClass + '"></ul>');
			nodes = $(this.getChildren(this.current_view || 0)).filter(this.filterNode);
			
			for (i = 0; i < nodes.length; i++) {
				var node = nodes[i],
					id = this.getNodeId(node),
					radio = $('<input type="radio"/>')
						.attr({'value': id, 'id': this.opts.inputName + '_' + id})
						.bind('click', {st: this}, this.itemSelected),
					li = $('<li>')
						.append(radio),
					drill, label;
					children = this.getChildren(node);
				
				label = $('<span>').append(this.nodeMarkup(node));
				if (this.hasChildren(node)) {
					label.addClass(this.opts.drillClass)
						.bind('click', {st: this, target: li}, this.drillDown);
				}
				li.append(label);

				if (this.opts.inputName) {radio.attr('name', this.opts.inputName);}
				if (id == this.getSelection()) {li.addClass(this.opts.selectedClass);}
				
				li.data('st-value', id);
				this.the_list.append(li);
			}
			
			this.list_container.html(this.the_list);
			jQuery('#' + this.opts.inputName + '_' + this.current_selection).attr('checked', 'checked');
			
			bc = this.makeBreadcrumbs();
			this.bc.html('');
			for (i = 0; i < bc.length; i++) {
				this.bc.append(bc[i]);
			}
			
			// Enable appropriate nav controls.
			if (this.current_view) {
				this.link_back.removeClass(this.opts.btnDisabledClass);
				this.link_top.removeClass(this.opts.btnDisabledClass);
			}
			
			this.container.trigger('afterDraw');
		};
		
		
		this.makeBreadcrumbs = function()
		{
			var crumbs = [],
				parent = this.current_view,
				clicky = function(e) {
					e.preventDefault();
					e.data[0].view(e.data[1]);
				},
				parent_node = null,
				link = null,
				first = true;
			
			while (parent) {
				parent_node = this.getNodeById(parent);
				if (!parent_node) {
					break;
				}
				
				link = $('<span>').append(this.getNodeName(parent_node).replace('<', '&lt;').replace('>', '&gt;'));
				
				if (!first) {
					link.addClass(this.opts.bcLinkClass)
						.bind('click', [this, parent], clicky);
				}
				crumbs.push(link);
				crumbs.push($('<span> &gt; </span>'));
				
				parent = this.getParentId(parent_node);
				
				first = false;
			}
			
			if (this.current_view) {
				crumbs.push($('<span>Top</span>').addClass(this.opts.bcLinkClass).bind('click', [this, 0], clicky));
			} else {
				crumbs.push($('<span>Top</span>'));
			}
			crumbs.reverse();
			
			return crumbs;
		};
		
		
		this.getNodeById = function(id)
		{
			for (var index = 0; index < this.tree.length; index++) {
				if (this.getNodeId(this.tree[index]) == id) {
					return this.tree[index];
				}
			}
			
			return null;
		};
		
		// Returns all of the siblings of a given node ID, as well as node_id itself.
		this.getSiblings = function(node_or_id)
		{
			// Find the node, its parent, then build a list of all nodes with the same parent.
			var node = (typeof node_or_id == 'object') ? node_or_id : this.getNodeById(node_or_id);
			return node ? this.getChildren(this.getParentId(node)) : null;
		};
		
		// Returns true if node should not be selectable.
		this.isDisabledNode = function(node_or_id)
		{
			var node_id = (typeof node_or_id == 'object') ? this.getNodeId(node_or_id) : node_or_id,
				bads = (typeof this.opts.disabledNodeIds.length === 'undefined') ? [0 + this.opts.disabledNodeIds] : this.opts.disabledNodeIds,
				i = 0;
			
			for (; i < bads.length; i++) {
				if (bads[i] == node_id) {
					return true;
				}
			}
			return false;
		};
		
		
		this.getChildren = function(node_or_id)
		{
			var children = [],
				node_id = (typeof node_or_id == 'object') ? this.getNodeId(node_or_id) : node_or_id,
				i;
			if (node_id !== null) {
				for (i = 0; i < this.tree.length; i++) {
					if (this.getParentId(this.tree[i]) == node_id) {
						children.push(this.tree[i]);
					}
				}
			}
			
			return children;
		};
		
		
		this.hasChildren = function(node_or_id)
		{
			var node = (typeof node_or_id == 'object') ? node_or_id : this.getNodeById(node_or_id);
			return (node !== null) ? (this.getNumChildren(node) > 0) : false;
		};
		
		
		this.hasParent = function(node_or_id)
		{
			var node_id = (typeof node_or_id == 'object') ? this.getNodeId(node_or_id) : node_or_id;
			return (node_id !== null) ? (this.getParentId(node) ? true : false) : false;
		};
		
		
		this.navigateBack = function(e)
		{
			e.preventDefault();
			var current_view = e.data.st.current_view || 0,
				parent = e.data.st.getParent(current_view) || 0;
			e.data.st.view(parent, 'left');
		};
		
		
		this.navigateTop = function(e)
		{
			e.preventDefault();
			e.data.st.view(0);
		};
		
		
		this.getParent = function(node_or_id)
		{
			var node = ((typeof node_or_id == 'object') ? node_or_id : this.getNodeById(node_or_id));
			return (node === null) ? null : this.getNodeById(this.getParentId(node));
		};

		
		// Call this to wipe the tree clean and recreate skeletal stuff.  Chase it with a shot of draw() for best results.
		this.skeleton = function()
		{
			this.link_top = $('<div class="' + this.opts.btnTopClass + '">Back to Top</div>')
				.bind('click', {st: this}, this.navigateTop);
			this.link_back = $('<div class="' + this.opts.btnBackClass + '">Back One Level</div>')
				.bind('click', {st: this}, this.navigateBack);
			this.list_container = $('<div class="' + this.opts.listContainerClass + '"></div>');
			this.bc = $('<div class="' + this.opts.breadcrumbsClass + '"></div>');
			this.container.html('').append(this.link_top).append(this.link_back).append(this.list_container).after(this.bc);
		};
		
		this.itemSelected = function(e)
		{
			var st = e.data.st,
				$target = $(e.target),
				value = $target.val(),
				selected = (e.data.st.getSelection() == value),
				$li = $target.parents('li:first');
			
			// IE7 hack - radio buttons don't select properly for some reason.
			$target.parents('ul:first').find('input:radio').attr('checked', false);
			$target.attr('checked', 'checked');
			
			$li.siblings().removeClass(e.data.st.opts.selectedClass);
			$li.addClass(e.data.st.opts.selectedClass);
			e.data.st.selectNode(value);
		};
		
		this.drillDown = function(e)
		{
			e.preventDefault();
			
			var value = e.data.target.data('st-value');
			e.data.st.navigateDown(value);
		};
		
		// Scrolls down to children of node_or_id.
		this.navigateDown = function(node_or_id)
		{
			this.view(node_or_id);
		};

		// Call this to supplement the existing tree data.
		this.addData = function(tree, redraw)
		{
			if (!tree.length) {throw 'Non-array passed into scrollingTree.addData()';}
			for (var i = 0; i < data.length; i++) {
				this.tree.push(data[i]);
			}
			
			if (redraw) {this.draw();}
		};
		
		
		// Call this to replace the existing tree data wholesale.
		this.replaceTree = function(tree, redraw)
		{
			if (!tree.length) {throw 'Non-array passed into scrollingTree.replaceTree()';}
			this.tree = tree;
			
			if (redraw) {this.draw();}
		};
		
		
		this.getSelectionName = function(node_or_id)
		{
			var node = this.getNodeById(this.getSelection());
			return node ? this.getNodeName(node) : '';
		};
		
		// Call this to select an item by ID.
		this.selectNode = function(id, redraw)
		{
			this.current_selection = id;
			this.container.trigger('nodeSelected');
			if (redraw) {this.draw();}
		};
		
		
		this.getSelection = function()
		{
			return this.current_selection;
		};
		
		
		// Elements 
		this.container = $c; // container (jQuery)
		this.output = null; // hidden input that holds current selection
		this.link_top = this.link_back = null; // "Back to Top" and "Back One Level" links
		this.list_container = null; // container div for the list
		this.bc = null; // container div for breadcrumbs
		
		// State
		this.tree = tree; // driving data
		this.opts = $.extend({}, $.scrollingTree.defaults, opts); // compiled options
		this.current_selection = null; // currently selected value
		
		$c.data('scrollingTree', this);
		
		var callbacks = ['getNodeId', 'getParentId', 'nodeMarkup', 'getNumChildren', 'getNodeName', 'filterNode'];
		for (var index = 0; index < callbacks.length; index++) {
			this[callbacks[index]] = this.opts[callbacks[index]];
		}
		
		this.selectNode(opts.startNode); // current selection
		this.current_view = this.current_selection ? this.getParent(this.current_selection) : opts.startView;
		
		this.skeleton();		
		this.draw();
	};
})(jQuery);
