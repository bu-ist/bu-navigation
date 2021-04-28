import { __ } from '@wordpress/i18n';
import { Placeholder, RadioControl } from '@wordpress/components';

import './editor.scss';

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
		<div className="wp-block-bu-navigation-navigation-block">
			<Placeholder label={ __( 'Navigation Block', 'bu-navigation' ) }>
				<RadioControl
					label={ __( 'Mode', 'bu-navigation' ) }
					help={ __( 'Which type of navigation?', 'bu-navigation' ) }
					selected={ attributes.navMode }
					options={ [
						{ label: 'Site', value: 'site' },
						{ label: 'Section', value: 'section' },
						{ label: 'Adaptive', value: 'adaptive' },
					] }
					onChange={ ( option ) =>
						setAttributes( { navMode: option } )
					}
				/>
			</Placeholder>
		</div>
	);
}
