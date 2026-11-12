// WordPress dependencies
import { __, sprintf } from '@wordpress/i18n';

/**
 * Location Breadcrumbs Component
 *
 * Shows where the post is in the page hierarchy: its ancestors from the top level down,
 * followed by the post itself. A top level post shows a "Top level" label instead.
 *
 * @param {Object} props
 * @param {Array}  props.ancestors    Ancestors from the top level down, as `{ id, label }` objects.
 * @param {string} props.label        The navigation label or title of the post.
 * @param {string} props.singularName The singular name of the post type, e.g. "Page".
 * @param {string} props.labelledBy   ID of the element that labels the breadcrumbs.
 * @return {JSX.Element} The breadcrumbs.
 */
const LocationBreadcrumbs = ( {
	ancestors,
	label,
	singularName,
	labelledBy,
} ) => {
	if ( ! ancestors.length ) {
		return (
			<p
				className="bu-navigation-placement-breadcrumbs is-top-level"
				aria-labelledby={ labelledBy }
			>
				{ sprintf(
					/* translators: %s: post type singular name, e.g. "page". */
					__( 'Top level %s', 'bu-navigation' ),
					singularName.toLowerCase()
				) }
			</p>
		);
	}

	return (
		<ol
			className="bu-navigation-placement-breadcrumbs"
			aria-labelledby={ labelledBy }
		>
			{ ancestors.map( ( ancestor ) => (
				<li key={ ancestor.id }>{ ancestor.label }</li>
			) ) }
			<li className="is-current" aria-current="location">
				{ label }
			</li>
		</ol>
	);
};

export default LocationBreadcrumbs;
