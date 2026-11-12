// WordPress Dependencies
import { useSelect } from '@wordpress/data';

/**
 * A custom hook to get the current post ID, post type and post type object.
 *
 * Returns a loading state while the post type object is being fetched.
 *
 * @return {Object} An object containing postId, postType, postTypeObject and isLoading.
 */
const usePost = () => {
	return useSelect( ( select ) => {
		const { getCurrentPostId, getCurrentPostType } = select(
			'core/editor'
		);
		const { getPostType } = select( 'core' );

		const postId = getCurrentPostId(); // e.g. 123
		const postType = getCurrentPostType(); // e.g. 'page'
		const postTypeObject = postType ? getPostType( postType ) : undefined; // Labels, supports, etc.

		return {
			postId,
			postType,
			postTypeObject,
			isLoading: ! postId || ! postType || ! postTypeObject,
		};
	}, [] );
};

export default usePost;
