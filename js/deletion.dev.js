(function($) {
	$(function() {
		
		// ajax request to get info on the page (but only if it is relevant), otherwise we get ignore: true in response
		function ajaxCheck(id) {
			
			var resp = { ignore: true };	// default to ignore
			var data = {
				action: 'check_hidden_page',
				post_id: id
			};
			
			// check the post through an non-asynchronous ajax request 
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: data,
				async: false,
				success: function(response) {
					var obj = JSON.parse(response);
					resp = obj;
				}
			});
	
			return resp;
		}
		
		
		function processResp( resp, multiple_warnings ) {
			
			// if we can't ignore
			if (!resp.ignore) {
				
				// show warning
				var warning = resp.msg;
				warning += "\n\nAre you sure you want to delete " + (multiple_warnings ? "these pages" : "this page") + "?";
				
				if ( window.confirm(warning) ) {
					return true;
				} else {
					return false;
				}
				
			}
			
			return true;
		}
		
		
		/*
		 * displays a warning confirmation box when a post is hidden and has children
		 * @returns bool true/false (where true means delete the post, false means don't delete)
		 */
		$('a.submitdelete').live('click', function(){
			
			var id = ( typeof(inlineEditPost) != "undefined" ) ? inlineEditPost.getId(this) : post_id;
			// check post by ajax and do action accordingly
			var resp = ajaxCheck(id);
			return processResp(resp);
		});
		
		/**
		 * case: bulk deleting posts
		 */
		$('#posts-filter').submit(function() {
			var resp = null;
			var warnings = [];
			var id = 0;
			
			// when the person has selected "delete" from the drop down
			if ( $(this).find('select[name="action"],select[name="action2"]').filter('[value="trash"]') ) {
				
				// get posts
				var checked_posts = $(this).find('input[name="post[]"]:checked');
				
				// go through found posts
				for (var i=0; i < checked_posts.length; i++) {
					
					// check each posts' status
					id = $(checked_posts[i]).val();
					resp = ajaxCheck( id );
					
					// if it isn't something to ignore, add to warnings
					if (!resp.ignore)
						warnings.push(resp.msg);
				}
				
				// if we have warnings
				if (warnings.length) {
					var multiple_warnings = warnings.length > 1 ? true : false;
					// now warn them
					return processResp({ ignore: false, msg:  warnings.join('\n\n') }, multiple_warnings);
				}
			}
			
			return true;
		});
		
	});
	
})(jQuery);
