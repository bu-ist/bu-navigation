// WordPress Dependencies
import apiFetch from '@wordpress/api-fetch';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';

/**
 * A custom hook to load the page tree of a post type one level at a time.
 *
 * Each level is requested once from the `bu-navigation/v1/tree` REST route.
 * A level that failed to load is requested again the next time it is loaded.
 *
 * @param {string} postType The post type of the tree.
 * @param {number} postId   The post being placed. Its unpublished ancestors are added to the levels they belong to.
 * @return {Object} An object containing `levels`, keyed by parent ID, and `loadLevel( parentId )`.
 */
const usePageTree = ( postType, postId = 0 ) => {
	// Each level is an object with `pages`, `isLoading` and `error`.
	const [ levels, setLevels ] = useState( {} );

	// Parent IDs that have been requested, so each level is fetched once.
	const requestedLevels = useRef( new Set() );

	// Responses that arrive after the component unmounts are ignored.
	const isMounted = useRef( true );
	useEffect( () => {
		isMounted.current = true;
		return () => {
			isMounted.current = false;
		};
	}, [] );

	const setLevel = useCallback( ( parentId, level ) => {
		if ( isMounted.current ) {
			setLevels( ( previousLevels ) => ( {
				...previousLevels,
				[ parentId ]: level,
			} ) );
		}
	}, [] );

	const loadLevel = useCallback(
		( parentId ) => {
			if ( ! postType || requestedLevels.current.has( parentId ) ) {
				return;
			}
			requestedLevels.current.add( parentId );

			setLevel( parentId, { pages: [], isLoading: true, error: null } );

			apiFetch( {
				path: `/bu-navigation/v1/tree?post_type=${ encodeURIComponent(
					postType
				) }&child_of=${ parentId }&post_id=${ postId }`,
			} )
				.then( ( pages ) => {
					setLevel( parentId, {
						pages,
						isLoading: false,
						error: null,
					} );
				} )
				.catch( ( error ) => {
					requestedLevels.current.delete( parentId );
					setLevel( parentId, {
						pages: [],
						isLoading: false,
						error,
					} );
				} );
		},
		[ postType, postId, setLevel ]
	);

	return { levels, loadLevel };
};

export default usePageTree;
