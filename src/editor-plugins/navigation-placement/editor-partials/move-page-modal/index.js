// WordPress dependencies
import { __, sprintf } from '@wordpress/i18n';
import {
	BaseControl,
	Button,
	Modal,
	SelectControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';

// Internal Dependencies
import usePageTree from '../../../../hooks/usePageTree';
import PageTree from '../page-tree';

/**
 * Move Page Modal Component
 *
 * A dialog for choosing a new location for the post: the page to place it in (or the top level),
 * then its position among that page's child pages. Levels of the page tree load as they are shown.
 *
 * Follows the rules of the Classic Editor metabox:
 * - A post cannot be placed in itself or in a link.
 * - When top level posts are not allowed in navigation lists, a post that shows in navigation lists
 *   can only be placed at the top level if it already showed there.
 * - A section editor moving a published post cannot place it at the top level or in a page they cannot edit.
 *
 * @param {Object}   props
 * @param {number}   props.postId               The ID of the post being moved.
 * @param {string}   props.postType             The post type of the post.
 * @param {Object}   props.labels               Post type names: `singularName` as registered, and `singular` and `plural` in lower case.
 * @param {number}   props.currentParent        The post's parent ID (0 for the top level).
 * @param {number}   props.currentMenuOrder     The post's position among its siblings.
 * @param {Array}    props.ancestors            The post's ancestors from the top level down, as `{ id, label }` objects.
 * @param {boolean}  props.isHidden             Whether the post is hidden from navigation lists.
 * @param {boolean}  props.canStayAtTopLevel    Whether the post was saved at the top level and showing in navigation lists.
 * @param {boolean}  props.allowTop             Whether top level posts are allowed in navigation lists.
 * @param {boolean}  props.isSectionEditor      Whether the user is a section editor.
 * @param {boolean}  props.isPublished          Whether the post is published.
 * @param {string}   props.linkPostType         The post type of navigation links.
 * @param {Function} props.onConfirm            Called with `{ parent, menuOrder, ancestors }` for a new location.
 * @param {Function} props.onClose              Called when the dialog closes.
 * @return {JSX.Element} The Move dialog.
 */
const MovePageModal = ( {
	postId,
	postType,
	labels,
	currentParent,
	currentMenuOrder,
	ancestors,
	isHidden,
	canStayAtTopLevel,
	allowTop,
	isSectionEditor,
	isPublished,
	linkPostType,
	onConfirm,
	onClose,
} ) => {
	const { levels, loadLevel } = usePageTree( postType );

	const [ selectedParent, setSelectedParent ] = useState( currentParent );

	// The chosen position (1 is first); null until one is chosen, which uses the default position.
	const [ selectedPosition, setSelectedPosition ] = useState( null );

	// Open the tree at the post's location.
	const [ expanded, setExpanded ] = useState( () =>
		ancestors.map( ( ancestor ) => ancestor.id )
	);

	// Load the top level and each level down to the post.
	useEffect( () => {
		loadLevel( 0 );
		ancestors.forEach( ( ancestor ) => loadLevel( ancestor.id ) );
	}, [ ancestors, loadLevel ] );

	// Section editing restrictions only affect published content.
	const sectionRulesApply = isSectionEditor && isPublished;

	// Why the post cannot be placed at the top level, if it cannot. Staying at the top level is always allowed.
	let topLevelDisabledReason = '';
	if ( 0 !== currentParent ) {
		if ( sectionRulesApply ) {
			topLevelDisabledReason = sprintf(
				/* translators: %s: post type plural name, e.g. "pages". */
				__(
					'Section editors cannot move published %s to the top level.',
					'bu-navigation'
				),
				labels.plural
			);
		} else if ( ! allowTop && ! isHidden && ! canStayAtTopLevel ) {
			topLevelDisabledReason = sprintf(
				/* translators: %s: post type plural name, e.g. "pages". */
				__(
					'Displaying top-level %s in navigation lists is currently disabled.',
					'bu-navigation'
				),
				labels.plural
			);
		}
	}

	/**
	 * Why a page cannot be chosen as the parent, or an empty string if it can.
	 *
	 * @param {Object} page A page from the usePageTree hook.
	 * @return {string} The reason, or an empty string.
	 */
	const getDisabledReason = ( page ) => {
		if ( page.id === postId ) {
			return __( 'this page', 'bu-navigation' );
		}
		if ( page.type === linkPostType ) {
			return __( 'link', 'bu-navigation' );
		}
		// Staying in the current section is always allowed.
		if (
			sectionRulesApply &&
			! page.canEdit &&
			page.id !== currentParent
		) {
			return __( 'denied', 'bu-navigation' );
		}
		return '';
	};

	const onToggle = ( pageId ) => {
		if ( expanded.includes( pageId ) ) {
			setExpanded( expanded.filter( ( id ) => id !== pageId ) );
		} else {
			setExpanded( [ ...expanded, pageId ] );
			loadLevel( pageId );
		}
	};

	const onSelect = ( pageId ) => {
		setSelectedParent( pageId );
		setSelectedPosition( null );
		// The child pages of the new parent are the positions to choose from.
		loadLevel( pageId );
	};

	// The pages the post can be placed among: the child pages of the selected parent, without the post.
	const siblingsLevel = levels[ selectedParent ];
	const isLoadingSiblings = ! siblingsLevel || siblingsLevel.isLoading;
	const siblings = siblingsLevel
		? siblingsLevel.pages.filter( ( page ) => page.id !== postId )
		: [];

	// The default position: where the post is now when it stays in its section, otherwise the end.
	let defaultPosition = siblings.length + 1;
	if ( selectedParent === currentParent && siblingsLevel ) {
		const currentIndex = siblingsLevel.pages.findIndex(
			( page ) => page.id === postId
		);
		defaultPosition =
			currentIndex > -1
				? currentIndex + 1
				: Math.min(
						Math.max( currentMenuOrder, 1 ),
						siblings.length + 1
				  );
	}
	const position =
		null !== selectedPosition ? selectedPosition : defaultPosition;

	const positionOptions = [
		{ value: 1, label: __( 'First', 'bu-navigation' ) },
		...siblings.map( ( sibling, index ) => ( {
			value: index + 2,
			/* translators: %s: page label. */
			label: sprintf( __( 'After %s', 'bu-navigation' ), sibling.label ),
		} ) ),
	];

	/**
	 * The ancestors of the selected parent for the breadcrumbs, from the top level down, ending with the parent.
	 *
	 * @return {Array} `{ id, label }` objects.
	 */
	const getSelectedAncestors = () => {
		if ( selectedParent === currentParent ) {
			return ancestors;
		}

		const pagesById = {};
		Object.values( levels ).forEach( ( level ) => {
			level.pages.forEach( ( page ) => {
				pagesById[ page.id ] = page;
			} );
		} );

		const path = [];
		let pageId = selectedParent;
		while (
			pageId &&
			pagesById[ pageId ] &&
			! path.some( ( item ) => item.id === pageId )
		) {
			path.unshift( { id: pageId, label: pagesById[ pageId ].label } );
			pageId = pagesById[ pageId ].parent;
		}
		return path;
	};

	const onUpdateLocation = () => {
		// Nothing to change when the post stays where it is.
		if (
			selectedParent === currentParent &&
			position === defaultPosition
		) {
			onClose();
			return;
		}

		onConfirm( {
			parent: selectedParent,
			menuOrder: position,
			ancestors: getSelectedAncestors(),
		} );
	};

	return (
		<Modal
			title={ sprintf(
				/* translators: %s: post type singular name, e.g. "Page". */
				__( '%s Location', 'bu-navigation' ),
				labels.singularName
			) }
			onRequestClose={ onClose }
			className="bu-navigation-placement-modal"
		>
			<p className="bu-navigation-placement-modal-hint">
				{ sprintf(
					/* translators: %s: post type singular name, e.g. "page". */
					__(
						'Choose where to place this %s, then its position.',
						'bu-navigation'
					),
					labels.singular
				) }
			</p>

			<fieldset className="bu-navigation-placement-tree">
				<legend>
					<BaseControl.VisualLabel className="bu-navigation-placement-section-label">
						{ __( 'Place in', 'bu-navigation' ) }
					</BaseControl.VisualLabel>
				</legend>
				<ul className="bu-navigation-placement-tree-level is-root">
					<li>
						<div className="bu-navigation-placement-tree-item">
							<span className="bu-navigation-placement-tree-toggle-spacer" />
							<input
								type="radio"
								id="bu-navigation-placement-parent-0"
								name="bu-navigation-placement-parent"
								value={ 0 }
								checked={ 0 === selectedParent }
								disabled={ !! topLevelDisabledReason }
								aria-describedby={
									topLevelDisabledReason
										? 'bu-navigation-placement-top-level-reason'
										: undefined
								}
								onChange={ () => onSelect( 0 ) }
							/>
							<label htmlFor="bu-navigation-placement-parent-0">
								{ __( 'Top level', 'bu-navigation' ) }
							</label>
						</div>
						{ topLevelDisabledReason && (
							<p
								id="bu-navigation-placement-top-level-reason"
								className="bu-navigation-placement-tree-reason"
							>
								{ topLevelDisabledReason }
							</p>
						) }
						<PageTree
							parentId={ 0 }
							levels={ levels }
							expanded={ expanded }
							onToggle={ onToggle }
							selected={ selectedParent }
							onSelect={ onSelect }
							getDisabledReason={ getDisabledReason }
							currentPostId={ postId }
							onRetry={ loadLevel }
						/>
					</li>
				</ul>
			</fieldset>

			<SelectControl
				className="bu-navigation-placement-position"
				label={ __( 'Position', 'bu-navigation' ) }
				value={ position }
				options={ positionOptions }
				onChange={ ( value ) =>
					setSelectedPosition( parseInt( value, 10 ) )
				}
				disabled={ isLoadingSiblings }
			/>

			<div className="bu-navigation-placement-modal-actions">
				<Button isSecondary onClick={ onClose }>
					{ __( 'Cancel', 'bu-navigation' ) }
				</Button>
				<Button
					isPrimary
					onClick={ onUpdateLocation }
					disabled={ isLoadingSiblings || !! siblingsLevel.error }
				>
					{ __( 'Update Location', 'bu-navigation' ) }
				</Button>
			</div>
		</Modal>
	);
};

export default MovePageModal;
