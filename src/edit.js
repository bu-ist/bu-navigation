import { __ } from '@wordpress/i18n';
import { RadioControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from '@wordpress/element';

import { InputLabel } from '@material-ui/core';
import TextField from '@material-ui/core/TextField';
import Autocomplete from '@material-ui/lab/Autocomplete';

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
	const [ parents, setParents ] = useState( [
		{ postid: 0, title: 'Current Parent', type: '' },
	] );

	useEffect( () => {
		getParents();
	}, [] );

	const getParents = async () => {
		const fetchedParents = await apiFetch( {
			path: 'bu-navigation/v1/parents',
		} );
		// set the zero item to 'Parent Post'
		fetchedParents[ 0 ].title = 'Current Parent';

		setParents( fetchedParents );
	};

	const [ currentValue ] = parents.filter(
		( { postid } ) => postid === attributes.rootPostID
	);
		
	
	const currentInputValue =
		! currentValue || attributes.rootPostID === 0
			? 'Current Parent'
			: currentValue.title;
	
	console.log(currentInputValue);
 
	return (
		<div className="wp-block-bu-navigation-navigation-block">
			<p>Navigation Block</p>
			<InputLabel>Display from parent post:</InputLabel>
			<Autocomplete
				className="bu-navigation-parent-picker"
				options={ parents }
				getOptionLabel={ ( { title } ) => title }
				renderInput={ ( params ) => (
					<TextField { ...params } label="Parent Posts" />
				) }
				//inputValue={ currentInputValue }
				onChange={ ( event, { postid } ) =>
					setAttributes( { rootPostID: postid } )
				}
			/>
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
