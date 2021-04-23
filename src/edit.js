/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/packages/packages-block-editor/#useBlockProps
 */
import { useBlockProps } from '@wordpress/block-editor';

import { TextControl } from '@wordpress/components';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/block-edit-save/#edit
 *
 * @return {WPElement} Element to render.
 */
export default function Edit( { attributes, isSelected, setAttributes } ) {
	return (
		<div { ...useBlockProps() }>
			{ ! isSelected ? (
				<div>Navigation Mode: { attributes.navMode }</div>
			) : (
				<div>
					<TextControl
						label={ __( 'Message', 'navigation-block' ) }
						value={ attributes.navMode }
						onChange={ ( val ) =>
							setAttributes( { navMode: val } )
						}
					/>
				</div>
			) }
		</div>
	);
}
