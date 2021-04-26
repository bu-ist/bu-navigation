import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { RadioControl } from '@wordpress/components';

import { TextControl } from '@wordpress/components';

/**
 * Displays the edit side of the block
 *
 * @param {Object} props
 * @param {Array} props.attributes
 * @param {Function} props.setAttributes
 * @return {WPElement} Element to render.
 */
export default function Edit( { attributes, setAttributes } ) {
	return (
		<div { ...useBlockProps() }>
			<RadioControl
				label={ __( 'Navigation Block Mode', 'bu-navigation' ) }
				help={ __( 'Which type of navigation?', 'bu-navigation' ) }
				selected={ attributes.navMode }
				options={ [
					{ label: 'Site', value: 'site' },
					{ label: 'Section', value: 'section' },
					{ label: 'Adaptive', value: 'adaptive' },
				] }
				onChange={ ( option ) => setAttributes( { navMode: option } ) }
			/>
		</div>
	);
}
