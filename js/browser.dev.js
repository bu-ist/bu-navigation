jQuery(function($) {
	if (typeof page_summary === 'undefined') {
		return;
	}
	
	var container = $('#bu-page-parent');
	
	container.bind('nodeSelected', function(e) {
		var id = $(e.target).data('scrollingTree').getSelection(),
			d;
		$('[name="parent_id"]').val(id);
		$('#bu-page-parent-current span').html((id == 0) ? 'Top level page' : $(e.target).data('scrollingTree').getSelectionName());
		
		d = new Date();
		d.setTime(d.getTime() + (28800000)); // 8 hours
		document.cookie = 'bpp_parent=' + id + '; expires=' + d.toGMTString() + '; path=/';
	});
	
	container.bind('beforeDraw', function(e) {
		var st = $(e.target).data('scrollingTree'),
			sel = st.getSelection(),
			name = ((sel === 0) ? 'Top level page' : st.getSelectionName()) || 'none';
		
		$('#bu-page-parent-current span').html(name);
	});
	
	container.bind('afterDraw', function(e) {
		var st = $(e.target).data('scrollingTree'),
			sel = st.getSelection();
		if (!st.current_view) {
			st.the_list.prepend($('<li>')
				.append($('<input type="radio" value="0" id="top_level_page" name="st-input" />').bind('change', {'st': st}, st.itemSelected))
				.append($('<label for="top_level_page">MAKE TOP LEVEL PAGE</label>'))
				.data('st-value', 0)
			);
			
			if ((sel === 0) || (sel === "0")) {
				$('#top_level_page').attr('checked', 'checked');
			}
		}
	});
	
	container.scrollingTree(page_summary, {
		nodeMarkup: function(node) {
			var name = node.post_title || '(no title - #' + node.ID + ')';
			return name.replace('<', '&lt;').replace('>', '&gt;');
			},
		getNodeName: function(node) {
			var name = node.post_title || '(no title - #' + node.ID + ')';
			return name.replace('<', '&lt;').replace('>', '&gt;').replace(' ', '&nbsp;');
			},
		getNodeId: function(node) {return node.ID;},
		getParentId: function(node) {
			return node.post_parent;
			},
		getNumChildren: function(node) {
			var children = $(this.getChildren(node)).filter(this.filterNode);
			return children.length > 0;
		},
		startNode: current_parent,
		drillMarkup: '&nbsp;',
		filterNode: function() {
			return (this.post_type == 'page') && (this.ID != post_id) && (this.ID != page_on_front);
		}
	});
	
	$('#bu-page-parent-help').qtip({
		content: $('#bu-page-parent-help-content').html(),
		position: {
			corner: {target: 'leftMiddle', tooltip: 'rightMiddle'},
			adjust: {screen: true}
		},
		style: {width: {min: '600px'}}
	});
});
