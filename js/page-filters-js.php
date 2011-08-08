<script type="text/javascript">
//<![CDATA[
jQuery(document).ready( function($) 
{
	function getQuerystringParams()
	{
	  var vars = []; 
	  var hash;
	  
	  if (window.location.href.indexOf('?') != -1)
	  {
	    var hashes = window.location.href.slice(window.location.href.indexOf('?') + 1).split('&');
	    
	    for(var i = 0; i < hashes.length; i++)
	      {
		hash = hashes[i].split('=');
		vars[hash[0]] = hash[1];
	      }
	  }
	  
	  return vars;
	}
	
	jQuery('#<?php echo BU_FILTER_PAGES_ID; ?>').change(function ()
	{
		var parent = jQuery('#<?php echo BU_FILTER_PAGES_ID; ?> option:selected').attr('value');
		
		var params = getQuerystringParams();

		var url = window.location.href;

		if (url.indexOf('?') != -1)
		{
		  url = url.slice(0, url.indexOf('?'));
		}

		var query = '';

		for (p in params)
		{
		  if (p == 'post_parent') continue;
		  query += p + "=" + params[p] + "&";
		}

		if (parent) query += 'post_parent=' + parent;
		
		if (query) 
		{
		  url += '?' + query;
		}

		url = url.replace(/&$/, '');

		window.location = url;
	});
});
//]]>
</script>
