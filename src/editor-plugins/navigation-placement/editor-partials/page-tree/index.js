// WordPress dependencies
import { __, sprintf } from '@wordpress/i18n';
import { Button, Notice, Spinner } from '@wordpress/components';

/**
 * Page Tree Component
 *
 * Lists one level of the page tree as radio buttons for choosing a parent page. A page with
 * child pages has a button that shows or hides them; each child level is a nested Page Tree.
 * The post being moved is listed but cannot be chosen, and its child pages are not shown.
 *
 * @param {Object}   props
 * @param {number}   props.parentId          The parent ID of the level to list (0 for the top level).
 * @param {Object}   props.levels            Loaded levels from the usePageTree hook.
 * @param {Array}    props.expanded          IDs of the pages whose child pages are shown.
 * @param {Function} props.onToggle          Called with a page ID to show or hide its child pages.
 * @param {number}   props.selected          The selected parent ID.
 * @param {Function} props.onSelect          Called with a page ID when it is chosen.
 * @param {Function} props.getDisabledReason Called with a page; returns why it cannot be chosen, or ''.
 * @param {number}   props.currentPostId     The ID of the post being moved.
 * @param {Function} props.onRetry           Called with a parent ID to load a level that failed.
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
	} = props;

	const level = levels[ parentId ];

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
				const canExpand = page.hasChildren && page.id !== currentPostId;
				const isExpanded = canExpand && expanded.includes( page.id );
				const disabledReason = getDisabledReason( page );
				const inputId = `bu-navigation-placement-parent-${ page.id }`;
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

				return (
					<li key={ page.id }>
						<div className="bu-navigation-placement-tree-item">
							{ canExpand ? (
								<Button
									className="bu-navigation-placement-tree-toggle"
									icon={
										isExpanded
											? 'arrow-down-alt2'
											: 'arrow-right-alt2'
									}
									label={ toggleLabel }
									aria-expanded={ isExpanded }
									onClick={ () => onToggle( page.id ) }
									isSmall
								/>
							) : (
								<span className="bu-navigation-placement-tree-toggle-spacer" />
							) }
							<input
								type="radio"
								id={ inputId }
								name="bu-navigation-placement-parent"
								value={ page.id }
								checked={ selected === page.id }
								disabled={ !! disabledReason }
								onChange={ () => onSelect( page.id ) }
							/>
							<label htmlFor={ inputId }>
								{ page.label }
								{ page.excluded && (
									<span className="bu-navigation-placement-badge">
										{ __( 'not in nav', 'bu-navigation' ) }
									</span>
								) }
								{ 'private' === page.status && (
									<span className="bu-navigation-placement-badge">
										{ __( 'private', 'bu-navigation' ) }
									</span>
								) }
								{ disabledReason && (
									<span className="bu-navigation-placement-badge is-disabled-reason">
										{ disabledReason }
									</span>
								) }
							</label>
						</div>
						{ isExpanded && (
							<PageTree { ...props } parentId={ page.id } />
						) }
					</li>
				);
			} ) }
		</ul>
	);
};

export default PageTree;
