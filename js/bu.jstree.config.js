if ( typeof bu === 'undefined' )
	var bu = {};

(function($){

	if( typeof bu.navigation === 'undefined' )
		bu.navigation = {};

	/**
	 * Base configuration object for BU navigation jstree instances
	 *
	 * The settings object accepts the following values
	 *		interfacePath = path to type icons
	 *		rpcUrl = JSON formatted page tree
	 *		initialTree = JSON object containing initial tree state
	 */
	bu.navigation.get_default_tree_settings = function( config ) {

		if( typeof( config ) != 'object' )
			config = {};

		// Plugin defaults -- default variables brought to you by wp_localize_script
		if( typeof( config.rpcUrl ) === 'undefined' )
			config.rpcUrl = buNavTree.rpcUrl;
			
		if( typeof( config.interfacePath ) === 'undefined' )
			config.interfacePath = buNavTree.interfacePath;

		// Build default BU jstree settings object
		var settings = {
			"themes" : {
				"theme" : "classic"
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
							"image": config.interfacePath + "/icons/page_regular.png"
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
							"image": config.interfacePath + "/icons/page_regular.png"
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
							"image": config.interfacePath + "/icons/page_hidden.png"
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
							"image": config.interfacePath + "/icons/page_restricted.png"
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
							"image": config.interfacePath + "/icons/page_regular.png"
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
							"image": config.interfacePath + "/icons/page_hidden.png"
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
							"image": config.interfacePath + "/icons/page_restricted.png"
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
							"image": config.interfacePath + "/icons/page_hidden_restricted.png"
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
							"image": config.interfacePath + "/icons/page_hidden_restricted.png"
						}
					},
					"link": {
						"icon": {
							"image": config.interfacePath + "/icons/page_white_link.png"
						},
						"max_children": 0
					},
					"link_restricted": {
						"icon": {
							"image": config.interfacePath + "/icons/page_white_link.png"
						},
						"max_children": 0
					},
					"link_excluded": {
						"icon": {
							"image": config.interfacePath + "/icons/page_white_link.png"
						},
						"max_children": 0
					}
				}
			},
			"json_data": {
				"ajax" : {
					"url" : config.rpcUrl,
					"type" : "POST",
					"data" : function (n) {
						return { id : n.attr ? n.attr("id") : 0 };
					}
				},
				"progressive_render" : true
			}
		};

		if( config.initialTree )
			settings.json_data.data = config.initialTree;

		return settings;

	};

})(jQuery);