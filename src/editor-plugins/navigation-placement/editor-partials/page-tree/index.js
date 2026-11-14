// External dependencies
import {
	draggable,
	dropTargetForElements,
} from '@atlaskit/pragmatic-drag-and-drop/element/adapter';
import {
	attachInstruction,
	extractInstruction,
} from '@atlaskit/pragmatic-drag-and-drop-hitbox/list-item';

// WordPress dependencies
import { __, _x, sprintf } from '@wordpress/i18n';
import { Button, Notice, Spinner, VisuallyHidden } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';

// Identifies drags of the post being moved, so that the tree ignores other drags.
const DRAG_TYPE = 'bu-navigation-placement-post';

// How long the post is held over a closed page before its child pages are shown, in milliseconds.
const EXPAND_DELAY = 500;

/**
 * Page Tree Item Component
 *
 * One page in the tree, with its child pages. The post being moved can be dragged; the other
 * pages are drop targets that place it before, after or inside them.
 *
 * @param {Object}      props
 * @param {Object}      props.page              A page from the usePageTree hook.
 * @param {boolean}     props.isCurrentPost     Whether the page is the post being moved.
 * @param {boolean}     props.canExpand         Whether the page's child pages can be shown.
 * @param {boolean}     props.isExpanded        Whether the page's child pages are shown.
 * @param {boolean}     props.isSelected        Whether the page is the selected parent.
 * @param {string}      props.disabledReason    Why the page cannot be chosen, or ''.
 * @param {string}      props.statusBadge       The badge for the page's status, or ''.
 * @param {Function}    props.onToggle          Called with a page ID to show or hide its child pages.
 * @param {Function}    props.onSelect          Called with a page ID when it is chosen.
 * @param {Function}    props.getDropOperations Called with a page and whether it is expanded; returns the drop operations.
 * @param {Function}    props.onDropPost        Called with a page and an operation when the post is dropped on it.
 * @param {Function}    props.onDragExpand      Called with a page ID to show its child pages during a drag.
 * @param {JSX.Element} props.children          The page's child pages.
 * @return {JSX.Element} The page.
 */
const PageTreeItem = ( {
	page,
	isCurrentPost,
	canExpand,
	isExpanded,
	isSelected,
	disabledReason,
	statusBadge,
	onToggle,
	onSelect,
	getDropOperations,
	onDropPost,
	onDragExpand,
	children,
} ) => {
	const rowRef = useRef();
	const [ isDragging, setIsDragging ] = useState( false );
	const [ dropInstruction, setDropInstruction ] = useState( null );

	// The drag and drop handlers are attached once and read the current props from here.
	const latest = useRef();
	latest.current = {
		page,
		canExpand,
		isExpanded,
		getDropOperations,
		onDropPost,
		onDragExpand,
	};

	useEffect( () => {
		const element = rowRef.current;

		if ( isCurrentPost ) {
			return draggable( {
				element,
				getInitialData: () => ( { type: DRAG_TYPE } ),
				onDragStart: () => setIsDragging( true ),
				onDrop: () => setIsDragging( false ),
			} );
		}

		let expandTimer = null;
		const clearExpandTimer = () => {
			clearTimeout( expandTimer );
			expandTimer = null;
		};

		const cleanup = dropTargetForElements( {
			element,
			canDrop: ( { source } ) => DRAG_TYPE === source.data.type,
			getData: ( { input } ) =>
				attachInstruction(
					{},
					{
						element,
						input,
						operations: latest.current.getDropOperations(
							latest.current.page,
							latest.current.isExpanded
						),
					}
				),
			onDrag: ( { self } ) => {
				const instruction = extractInstruction( self.data );
				setDropInstruction( ( previous ) =>
					previous &&
					instruction &&
					previous.operation === instruction.operation &&
					previous.blocked === instruction.blocked
						? previous
						: instruction
				);

				// Holding the post over a closed page shows its child pages.
				const opensPage =
					instruction &&
					'combine' === instruction.operation &&
					! instruction.blocked &&
					latest.current.canExpand &&
					! latest.current.isExpanded;
				if ( ! opensPage ) {
					clearExpandTimer();
				} else if ( ! expandTimer ) {
					expandTimer = setTimeout(
						() =>
							latest.current.onDragExpand(
								latest.current.page.id
							),
						EXPAND_DELAY
					);
				}
			},
			onDragLeave: () => {
				clearExpandTimer();
				setDropInstruction( null );
			},
			onDrop: ( { self } ) => {
				clearExpandTimer();
				setDropInstruction( null );

				const instruction = extractInstruction( self.data );
				if ( instruction && ! instruction.blocked ) {
					latest.current.onDropPost(
						latest.current.page,
						instruction.operation
					);
				}
			},
		} );

		return () => {
			clearExpandTimer();
			cleanup();
		};
	}, [ isCurrentPost ] );

	const inputId = `bu-navigation-placement-parent-${ page.id }`;

	const rowClassName = [
		'bu-navigation-placement-tree-item',
		isCurrentPost && 'is-current-post',
		// Not is-dragging, which the WordPress 5.8 block editor hides with display: none.
		isDragging && 'is-dragged',
		dropInstruction && `is-drop-${ dropInstruction.operation }`,
		dropInstruction && dropInstruction.blocked && 'is-drop-blocked',
	]
		.filter( Boolean )
		.join( ' ' );

	let toggle = (
		<span className="bu-navigation-placement-tree-toggle-spacer" />
	);
	if ( isCurrentPost ) {
		toggle = (
			<span
				className="bu-navigation-placement-tree-drag-handle dashicons dashicons-move"
				aria-hidden="true"
			/>
		);
	} else if ( canExpand ) {
		const toggleLabel = isExpanded
			? sprintf(
					/* translators: %s: page label. */
					__( 'Hide pages in %s', 'bu-navigation' ),
					page.label
			  )
			: sprintf(
					/* translators: %s: page label. */
					__( 'Show pages in %s', 'bu-navigation' ),
					page.label
			  );
		toggle = (
			<Button
				className="bu-navigation-placement-tree-toggle"
				icon={ isExpanded ? 'arrow-down-alt2' : 'arrow-right-alt2' }
				label={ toggleLabel }
				aria-expanded={ isExpanded }
				onClick={ () => onToggle( page.id ) }
				isSmall
			/>
		);
	}

	return (
		<li>
			<div ref={ rowRef } className={ rowClassName }>
				{ toggle }
				<input
					type="radio"
					id={ inputId }
					name="bu-navigation-placement-parent"
					value={ page.id }
					checked={ isSelected }
					disabled={ !! disabledReason }
					onChange={ () => onSelect( page.id ) }
				/>
				<label htmlFor={ inputId }>
					{ page.label }
					<span className="bu-navigation-placement-badges">
						{ page.excluded && (
							<span className="bu-navigation-placement-badge is-excluded">
								{ _x(
									'not in nav',
									'page tree badge: hidden from navigation lists',
									'bu-navigation'
								) }
							</span>
						) }
						{ statusBadge && (
							<span
								className={ `bu-navigation-placement-badge is-status-${ page.status }` }
							>
								{ statusBadge }
							</span>
						) }
						{ disabledReason && ! isCurrentPost && (
							<span className="bu-navigation-placement-badge is-disabled-reason">
								{ disabledReason }
							</span>
						) }
					</span>
					{ /* The post's row is marked by its color; its reason is for screen readers. */ }
					{ disabledReason && isCurrentPost && (
						<VisuallyHidden
							as="span"
							className="is-disabled-reason"
						>
							{ disabledReason }
						</VisuallyHidden>
					) }
				</label>
			</div>
			{ children }
		</li>
	);
};

/**
 * Page Tree Component
 *
 * Lists one level of the page tree as radio buttons for choosing a parent page. A page with
 * child pages has a button that shows or hides them; each child level is a nested Page Tree.
 * The post being moved is listed but cannot be chosen, and its child pages are not shown.
 *
 * @param {Object}   props
 * @param {number}   props.parentId          The parent ID of the level to list (0 for the top level).
 * @param {Object}   props.levels            Loaded levels, as from the usePageTree hook.
 * @param {Array}    props.expanded          IDs of the pages whose child pages are shown.
 * @param {Function} props.onToggle          Called with a page ID to show or hide its child pages.
 * @param {number}   props.selected          The selected parent ID.
 * @param {Function} props.onSelect          Called with a page ID when it is chosen.
 * @param {Function} props.getDisabledReason Called with a page; returns why it cannot be chosen, or ''.
 * @param {number}   props.currentPostId     The ID of the post being moved.
 * @param {Function} props.onRetry           Called with a parent ID to load a level that failed.
 * @param {Function} props.getDropOperations Called with a page and whether it is expanded; returns the drop operations.
 * @param {Function} props.onDropPost        Called with a page and an operation when the post is dropped on it.
 * @param {Function} props.onDragExpand      Called with a page ID to show its child pages during a drag.
 * @return {JSX.Element|null} The list of pages, or null when the level has no pages.
 */
const PageTree = ( props ) => {
	const {
		parentId,
		levels,
		expanded,
		onToggle,
		selected,
		onSelect,
		getDisabledReason,
		currentPostId,
		onRetry,
		getDropOperations,
		onDropPost,
		onDragExpand,
	} = props;

	const level = levels[ parentId ];

	// Badges for the statuses the tree lists besides published: private pages, and the
	// unpublished ancestors of the post being moved.
	const statusBadges = {
		private: _x(
			'private',
			'page tree badge: post status',
			'bu-navigation'
		),
		draft: _x( 'draft', 'page tree badge: post status', 'bu-navigation' ),
		pending: _x(
			'pending',
			'page tree badge: post status',
			'bu-navigation'
		),
		future: _x(
			'scheduled',
			'page tree badge: post status',
			'bu-navigation'
		),
	};

	if ( ! level || level.isLoading ) {
		return <Spinner />;
	}

	if ( level.error ) {
		return (
			<Notice
				status="error"
				isDismissible={ false }
				actions={ [
					{
						label: __( 'Try again', 'bu-navigation' ),
						onClick: () => onRetry( parentId ),
					},
				] }
			>
				{ __( 'The pages could not be loaded.', 'bu-navigation' ) }
			</Notice>
		);
	}

	if ( ! level.pages.length ) {
		return null;
	}

	return (
		<ul className="bu-navigation-placement-tree-level">
			{ level.pages.map( ( page ) => {
				const isCurrentPost = page.id === currentPostId;
				const canExpand = page.hasChildren && ! isCurrentPost;
				const isExpanded = canExpand && expanded.includes( page.id );

				return (
					<PageTreeItem
						key={ page.id }
						page={ page }
						isCurrentPost={ isCurrentPost }
						canExpand={ canExpand }
						isExpanded={ isExpanded }
						isSelected={ selected === page.id }
						disabledReason={ getDisabledReason( page ) }
						statusBadge={ statusBadges[ page.status ] || '' }
						onToggle={ onToggle }
						onSelect={ onSelect }
						getDropOperations={ getDropOperations }
						onDropPost={ onDropPost }
						onDragExpand={ onDragExpand }
					>
						{ isExpanded && (
							<PageTree { ...props } parentId={ page.id } />
						) }
					</PageTreeItem>
				);
			} ) }
		</ul>
	);
};

export default PageTree;
