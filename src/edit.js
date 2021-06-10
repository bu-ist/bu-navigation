import { __ } from '@wordpress/i18n';
import { RadioControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from '@wordpress/element';

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
	const [ parents, setParents ] = useState( [
		{ postid: 0, title: '', type: '' },
	] );

	const [ preview, setPreview ] = useState( '' );

	// Load all parent posts for the block's rootPostID attribute.
	useEffect( () => {
		getParents();
	}, [] );

	const getParents = async () => {
		const fetchedParents = await apiFetch( {
			path: 'bu-navigation/v1/parents',
		} );
		setParents( fetchedParents );
	};

	// Refresh the block preview markup whenever the attributes change.
	useEffect( () => {
		getPreview();
	}, [ attributes ] );

	const getPreview = async () => {
		const fetchedPreview = await apiFetch( {
			path: `bu-navigation/v1/markup/${ attributes.rootPostID }`,
		} );
		setPreview( fetchedPreview );
	};

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
					{ parents.map( ( { postid, title, type } ) => (
						<MenuItem
							name={ postid }
							key={ postid }
							value={ postid }
						>
							{ postid === 0 ? 'Current Parent' : title }
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
			<div dangerouslySetInnerHTML={ { __html: preview } } />
		</div>
	);
}
