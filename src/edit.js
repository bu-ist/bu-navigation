import { __ } from '@wordpress/i18n';
import { RadioControl } from '@wordpress/components';
import Select from '@material-ui/core/Select';
import MenuItem from '@material-ui/core/MenuItem';
import { InputLabel } from '@material-ui/core';

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
	// Loaded from wp_add_inline_scripts.
	const parents = buNavigationBlockParents;

	return (
		<div className="wp-block-bu-navigation-navigation-block">
			<p>Navigation Block</p>
			<div className="bu-navigation-parent-picker ">
				<InputLabel>Display from parent post:</InputLabel>
				<Select
					value={ attributes.rootPostID }
					onChange={ ( { target: value } ) =>
						setAttributes( { rootPostID: value.value } )
					}
				>
					{ parents.map( ( parentID ) => (
						<MenuItem
							name={ parentID }
							key={ parentID }
							value={ parentID }
						>
							{ parentID === 0 ? 'Current Parent' : `${parentID}` }
						</MenuItem>
					) ) }
				</Select>
			</div>
			<hr />
			<RadioControl
				label={ __( 'Display Mode', 'bu-navigation' ) }
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
