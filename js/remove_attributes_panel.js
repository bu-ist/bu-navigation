// WordPress 6.5 moved removeEditorPanel() from the core/edit-post store to core/editor.
( function( data ) {
	var editor = data.dispatch( 'core/editor' );
	( editor.removeEditorPanel ? editor : data.dispatch( 'core/edit-post' ) ).removeEditorPanel( 'page-attributes' );
} )( wp.data );
