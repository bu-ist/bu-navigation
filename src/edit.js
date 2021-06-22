import { __ } from '@wordpress/i18n';
import { PanelBody, ButtonGroup, Button } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from '@wordpress/element';
import { select } from '@wordpress/data';
import { InspectorControls } from '@wordpress/block-editor';

import Select from '@material-ui/core/Select';
import MenuItem from '@material-ui/core/MenuItem';

import './editor.scss';

const modes = [
	[ 'Site', 'site' ],
	[ 'Section', 'section' ],
	[ 'Adaptive', 'adaptive' ],
];

/**
 * Displays the edit side of the block
 *
 * @param {Object} props
 * @param {Array} props.attributes
 * @param {Function} props.setAttributes
 * @return {WPElement} Element to render.
 */
export default function Edit( { attributes, setAttributes } ) {
	// Get current post id.
	const { getCurrentPostId } = select( 'core/editor' );
	const currentPostId = getCurrentPostId();

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
			path: `bu-navigation/v1/markup?id=${ attributes.rootPostID }&navMode=${ attributes.navMode }`,
		} );
		setPreview( fetchedPreview );
	};

	return (
		<div className="wp-block-bu-navigation-navigation-block">
			<InspectorControls>
				<PanelBody title="Display Settings" initialOpen>
					<fieldset style={ { marginBottom: '2em' } }>
						<legend className="blocks-base-control__label">
							{ __(
								'Parent post for navigation tree',
								'bu-navigation'
							) }
						</legend>
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
					</fieldset>
					<fieldset style={ { marginBottom: '2em' } }>
						<legend className="blocks-base-control__label">
							{ __( 'Display Mode', 'bu-navigation' ) }
						</legend>
						<ButtonGroup>
							{ modes.map( ( [ label, value ] ) => (
								<Button
									key={ value }
									isPrimary={ attributes.navMode === value }
									isSecondary={ attributes.navMode !== value }
									onClick={ () =>
										setAttributes( { navMode: value } )
									}
								>
									{ label }
								</Button>
							) ) }
						</ButtonGroup>
					</fieldset>
				</PanelBody>
			</InspectorControls>
			<div dangerouslySetInnerHTML={ { __html: preview } } />
		</div>
	);
}
