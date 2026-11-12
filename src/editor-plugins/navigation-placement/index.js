/**
 * Navigation Placement Block Editor Plugin Component
 *
 * A Block Editor Plugin rendered in a Slot Fill in the Document Settings
 * Sidebar. This component replaces the "Placement in Navigation" metabox
 * on Block Editor screens. The Classic Editor keeps using the metabox.
 *
 * Features:
 * - Uses a custom hook to get current post data.
 * - Shows the post's location in the page hierarchy as breadcrumbs.
 * - Provides a Move dialog that sets the post's parent and position (menu order).
 * - Uses a custom hook to get and set the navigation label post meta.
 * - Provides a "Hide from navigation" checkbox for the navigation visibility post meta.
 *
 * The core Page Attributes panel (parent and order) is removed by js/remove_attributes_panel.js.
 *
 * @since 1.4.0
 */

// WordPress dependencies
import { __, sprintf } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel as EditPostPluginDocumentSettingPanel } from '@wordpress/edit-post';
import * as editor from '@wordpress/editor';
import { BaseControl, Button, TextControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';

// Internal Dependencies
import usePost from '../../hooks/usePost';
import usePostMeta from '../../hooks/usePostMeta';
import LocationBreadcrumbs from './editor-partials/location-breadcrumbs';
import MovePageModal from './editor-partials/move-page-modal';
import VisibilityControl from './editor-partials/visibility-control';

import './editor.scss';

// Settings from navigation-placement.php. wp_localize_script() passes top level values
// as strings, so booleans arrive as '1' or ''.
const settings = window.buNavigationPlacementSettings || {};
const allowTop = Boolean( settings.allowTop );
const excludeDefault = Boolean( settings.excludeDefault );
const isSectionEditor = Boolean( settings.isSectionEditor );
const linkPostType = settings.linkPostType;
const metaKeys = settings.metaKeys || {};

// WordPress 6.6 moved PluginDocumentSettingPanel from wp.editPost to wp.editor.
const PluginDocumentSettingPanel =
	editor.PluginDocumentSettingPanel || EditPostPluginDocumentSettingPanel;

/**
 * Navigation Placement Panel Content Component
 *
 * The panel for a post whose data has loaded.
 *
 * @param {Object} props
 * @param {number} props.postId         The ID of the post being edited.
 * @param {string} props.postType       The post type of the post.
 * @param {Object} props.postTypeObject The post type object.
 * @return {JSX.Element} The panel content.
 */
const NavigationPlacementPanelContent = ( {
	postId,
	postType,
	postTypeObject,
} ) => {
	// The edited parent and position, and the post's last saved values.
	const {
		parent,
		menuOrder,
		title,
		savedParent,
		savedMenuOrder,
		savedStatus,
		savedExclude,
	} = useSelect( ( select ) => {
		const { getEditedPostAttribute, getCurrentPostAttribute } = select(
			'core/editor'
		);
		const savedMeta = getCurrentPostAttribute( 'meta' );

		return {
			parent: getEditedPostAttribute( 'parent' ) || 0,
			menuOrder: getEditedPostAttribute( 'menu_order' ) || 0,
			title: getEditedPostAttribute( 'title' ),
			savedParent: getCurrentPostAttribute( 'parent' ) || 0,
			savedMenuOrder: getCurrentPostAttribute( 'menu_order' ) || 0,
			savedStatus: getCurrentPostAttribute( 'status' ),
			savedExclude: savedMeta ? savedMeta[ metaKeys.exclude ] : null,
		};
	}, [] );
	const { editPost } = useDispatch( 'core/editor' );

	// Get and set the navigation meta for the current post.
	const [ label, setLabel ] = usePostMeta( metaKeys.label, postType, postId );
	const [ exclude, setExclude ] = usePostMeta(
		metaKeys.exclude,
		postType,
		postId
	);

	// The ancestors of each parent the post has had in this editor, for the breadcrumbs: the saved
	// parent's from PHP, and each parent chosen in the Move dialog. Undo and Redo only return to these
	// parents, so the breadcrumbs follow the edited parent. The core Page Attributes panel, the other
	// way to set a parent, is removed.
	const [ ancestorsByParent, setAncestorsByParent ] = useState( () => ( {
		[ savedParent ]: settings.ancestors || [],
	} ) );
	const ancestors = ( 0 !== parent && ancestorsByParent[ parent ] ) || [];
	const [ isMoveModalOpen, setIsMoveModalOpen ] = useState( false );

	const isNewPost = 'auto-draft' === savedStatus;

	/**
	 * Whether a navigation visibility value hides the post. An empty value uses the site default,
	 * except for a new post, which shows in navigation lists unless it is hidden.
	 *
	 * @param {string|null} value The navigation visibility meta value.
	 * @return {boolean} Whether the value hides the post.
	 */
	const isExcluded = ( value ) => {
		if ( null === value || undefined === value || '' === value ) {
			return isNewPost ? false : excludeDefault;
		}
		return '0' !== String( value );
	};

	// When top level posts are not allowed in navigation lists, a top level post stays hidden,
	// unless it was saved at the top level and showing in navigation lists.
	const canStayAtTopLevel =
		! isNewPost && 0 === savedParent && ! isExcluded( savedExclude );
	const isLocked = 0 === parent && ! allowTop && ! canStayAtTopLevel;
	const isHidden = isLocked || isExcluded( exclude );

	const singularName = postTypeObject.labels.singular_name;
	const postTypeLabels = {
		singularName,
		singular: singularName.toLowerCase(),
		plural: postTypeObject.labels.name.toLowerCase(),
	};

	const lockedHelp = [
		sprintf(
			/* translators: %s: post type plural name, e.g. "pages". */
			__(
				'Displaying top-level %s in navigation lists is currently disabled.',
				'bu-navigation'
			),
			postTypeLabels.plural
		),
		sprintf(
			/* translators: %s: settings screen, "Appearance > Primary Navigation". */
			__(
				'To change this behavior, visit %s and enable the "Allow Top-Level Pages" setting.',
				'bu-navigation'
			),
			__( 'Appearance > Primary Navigation', 'bu-navigation' )
		),
	].join( ' ' );

	/**
	 * Set the new location chosen in the Move dialog. It is saved with the post.
	 *
	 * @param {Object} location
	 * @param {number} location.parent    The new parent ID.
	 * @param {number} location.menuOrder The new position among the parent's child pages.
	 * @param {Array}  location.ancestors The ancestors of the new location, for the breadcrumbs.
	 */
	const onConfirmMove = ( location ) => {
		editPost( { parent: location.parent, menu_order: location.menuOrder } );
		setAncestorsByParent( ( previous ) => ( {
			...previous,
			[ location.parent ]: location.ancestors,
		} ) );
		setIsMoveModalOpen( false );
	};

	return (
		<>
			<div className="bu-navigation-placement-location">
				<BaseControl.VisualLabel className="bu-navigation-placement-section-label">
					<span id="bu-navigation-placement-location-label">
						{ __( 'Location', 'bu-navigation' ) }
					</span>
				</BaseControl.VisualLabel>
				<LocationBreadcrumbs
					ancestors={ ancestors }
					label={
						label || title || __( '(no title)', 'bu-navigation' )
					}
					singularName={ singularName }
					labelledBy="bu-navigation-placement-location-label"
				/>
				<Button
					isSecondary
					onClick={ () => setIsMoveModalOpen( true ) }
				>
					{ sprintf(
						/* translators: %s: post type singular name, e.g. "page". */
						__( 'Move %s', 'bu-navigation' ),
						postTypeLabels.singular
					) }
				</Button>
			</div>

			<TextControl
				className="bu-navigation-placement-label"
				label={ __( 'Label', 'bu-navigation' ) }
				value={ label || '' }
				placeholder={ title }
				onChange={ setLabel }
			/>

			<VisibilityControl
				isHidden={ isHidden }
				isLocked={ isLocked }
				lockedHelp={ lockedHelp }
				onChange={ ( checked ) => setExclude( checked ? '1' : '0' ) }
			/>

			{ isMoveModalOpen && (
				<MovePageModal
					postId={ postId }
					postType={ postType }
					labels={ postTypeLabels }
					currentParent={ parent }
					currentMenuOrder={ menuOrder }
					isPositionSaved={
						parent === savedParent && menuOrder === savedMenuOrder
					}
					ancestors={ ancestors }
					isHidden={ isHidden }
					canStayAtTopLevel={ canStayAtTopLevel }
					allowTop={ allowTop }
					isSectionEditor={ isSectionEditor }
					isPublished={ 'publish' === savedStatus }
					linkPostType={ linkPostType }
					onConfirm={ onConfirmMove }
					onClose={ () => setIsMoveModalOpen( false ) }
				/>
			) }
		</>
	);
};

/**
 * Navigation Placement Panel Component
 *
 * Creates a Document Setting Panel in the Block Editor sidebar for placing
 * the current post in the page hierarchy and in navigation lists.
 *
 * @return {JSX.Element|null} The panel, or null while the post data loads.
 */
const NavigationPlacementPanel = () => {
	// Get current post data using custom usePost hook.
	const { postId, postType, postTypeObject, isLoading } = usePost();

	if ( isLoading ) {
		return null;
	}

	return (
		<PluginDocumentSettingPanel
			name="navigation-placement-post-panel"
			title={ __( 'Placement in Navigation', 'bu-navigation' ) }
			className="bu-navigation-navigation-placement-post-panel"
			icon="networking"
		>
			<NavigationPlacementPanelContent
				postId={ postId }
				postType={ postType }
				postTypeObject={ postTypeObject }
			/>
		</PluginDocumentSettingPanel>
	);
};

// Register the Editor Plugin.
domReady( () => {
	registerPlugin( 'bu-navigation-navigation-placement', {
		render: NavigationPlacementPanel,
	} );
} );
