/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/#registering-a-block
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import Edit from './edit';

/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/#registering-a-block
 */
registerBlockType( 'bu-navigation/navigation-block', {
	title: 'BU Navigation Block',
	icon: 'admin-site-alt3',
	category: 'widgets',
	attributes: {
		navMode: {
			type: 'string',
			default: 'section',
		},
		rootPostID: {
			type: 'integer',
			default: 0,
		},
	},
	/**
	 * @see ./edit.js
	 */
	edit: Edit,

	/**
	 * @see ./save.js
	 */
	save: () => null,
} );
