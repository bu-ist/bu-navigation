// WordPress Dependencies
import { useEntityProp } from '@wordpress/core-data';
import { useCallback } from '@wordpress/element';

/**
 * A custom hook to get and set one post meta value of a post.
 *
 * The meta key has to be registered with `show_in_rest` (see `register_post_meta()`).
 *
 * @param {string} metaKey  The meta key to get and set.
 * @param {string} postType The post type of the post.
 * @param {number} postId   The ID of the post.
 * @return {Array} The meta value, or null when it is not available, and a function to set it.
 */
const usePostMeta = ( metaKey, postType, postId ) => {
	// Get and set the meta object of the post.
	const [ meta, setMeta ] = useEntityProp(
		'postType',
		postType,
		'meta',
		postId
	);

	// Set one meta key and keep the other keys of the meta object.
	const setValue = useCallback(
		( newValue ) => {
			setMeta( { ...meta, [ metaKey ]: newValue } );
		},
		[ meta, metaKey, setMeta ]
	);

	const value =
		meta && undefined !== meta[ metaKey ] ? meta[ metaKey ] : null;

	return [ value, setValue ];
};

export default usePostMeta;
