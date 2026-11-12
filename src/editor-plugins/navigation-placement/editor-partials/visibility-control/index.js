// WordPress dependencies
import { __ } from '@wordpress/i18n';
import { BaseControl, CheckboxControl } from '@wordpress/components';

/**
 * Visibility Control Component
 *
 * A "Hide from navigation" checkbox: checked hides the post from navigation lists such as
 * the Content Navigation widget. When the post cannot show in navigation lists (a top level
 * post while top level posts are not allowed), the checkbox is checked and disabled, and the
 * help text says why.
 *
 * @param {Object}   props
 * @param {boolean}  props.isHidden   Whether the post is hidden from navigation lists.
 * @param {boolean}  props.isLocked   Whether the post has to stay hidden.
 * @param {string}   props.lockedHelp Why the post has to stay hidden.
 * @param {Function} props.onChange   Called with true to hide the post, or false to show it.
 * @return {JSX.Element} The checkbox.
 */
const VisibilityControl = ( { isHidden, isLocked, lockedHelp, onChange } ) => (
	<div className="bu-navigation-placement-visibility">
		<BaseControl.VisualLabel className="bu-navigation-placement-section-label">
			{ __( 'Visibility', 'bu-navigation' ) }
		</BaseControl.VisualLabel>
		<CheckboxControl
			label={ __( 'Hide from navigation', 'bu-navigation' ) }
			help={ isLocked ? lockedHelp : undefined }
			checked={ isHidden }
			disabled={ isLocked }
			onChange={ onChange }
		/>
	</div>
);

export default VisibilityControl;
