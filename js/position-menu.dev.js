jQuery(function($) {
	"use strict";

	var posmap = null;
	var posdiv = $("#bu-page-position");
	var menu = posdiv.find("select");
	var check = $("#bu-page-navigation-display");

	var getPosition = function(default_position) {
		if (posmap === null || posmap[current_parent] === undefined) {
			return default_position;
		} else {
			return posmap[current_parent];
		}
	};

	var setPosition = function(child_id) {
		if (posmap === null)
			posmap = {};

		posmap[current_parent] = child_id;
	};

	// disable the menu to start
	//	menu.attr("disabled", "disabled");

	// add the onselect event handler
	menu.change(function(e) {
		setPosition(menu.val());
	});

	// populates the menu with dynamic options
	var populateMenu = function(siblings) {

		// remove the dynamic options; the first option remains in place
		menu.find("option:gt(0)").remove();

		// add the new options
		for (var i = 0; i < siblings.length; i++) {
			var sib = siblings[i];
			var id = sib.ID;

			if (id == post_id && posmap === null) {
			setPosition(menu.find('option:last').val());
			} else {
				var title = sib.post_title || '(no title - #' + sib.ID + ')';
				
				menu.append('<option value="' + (parseInt(sib.menu_order) + 1) + '">Place after &ldquo;' + title + '&rdquo;</option>');
			}
		}

		// select the correct menu option
		menu.val(getPosition(siblings.length + 1));

		// enable the menu
		menu.removeAttr("disabled");
	};

	var handleSelection = function(e) {
		var container = $(e.target);
		var st = container.data("scrollingTree");
		current_parent = st.getSelection();

		if (current_parent !== null) {
			var sibs = st.getChildren(current_parent);
			populateMenu(sibs);
			
			handleCheck();	// validate the checkbox input
		}
	};
	
	var handleCheck = function () {
		
		// if this page was already in the nav and also top level, we don't need the validation (let user do whatever, ignore the following)
		// ...
		// otherwise we need to validate input
		if ( !already_in_nav || current_parent != 0 ) {
			
			// if we don't allow top levels, and the user has selected top level, then this is clearly a problem
			if (!allow_top && current_parent == 0 && check.is(":checked")) {
				alert("Displaying top-level pages in the navigation is disabled. To change this behavior, go to Site Design > Primary Navigation and enable \"Allow Top-Level Pages.\"");
				check.removeAttr("checked");
			}
		}
	}

	// handle the nodeSelected event of the page parent widget
	$("#bu-page-parent").bind("nodeSelected", handleSelection);

	// handle an initial selection for an existing page
	handleSelection({target: "#bu-page-parent"});

	// validate the checkbox input
	check.click(function(e) {
		handleCheck();
	});

	$('#bu-page-position-help').qtip({
		content: $('#bu-page-position-help-content').html(),
		position: {
			corner: {target: 'leftMiddle', tooltip: 'rightMiddle'},
			adjust: {screen: true}
		},
		style: {width: {min: '600px'}}
	});
	$('#bu-navigation-help').qtip({
		content: $('#bu-navigation-help-content').html(),
		position: {
			corner: {target: 'leftMiddle', tooltip: 'rightMiddle'},
			adjust: {screen: true}
		},
		style: {width: {min: '600px'}}
	});
});
