# usePostMeta

A custom hook that gets and sets one post meta value of a post through the editor's entity state.
Changes are saved with the post, the same as any other edit in the Block Editor.

## Usage

```javascript
import usePost from '../../hooks/usePost';
import usePostMeta from '../../hooks/usePostMeta';

const MyComponent = () => {
	const { postId, postType } = usePost();
	const [ label, setLabel ] = usePostMeta( '_bu_cms_navigation_page_label', postType, postId );

	return <input value={ label || '' } onChange={ ( event ) => setLabel( event.target.value ) } />;
};
```

## Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `metaKey` | `string` | The meta key to get and set. |
| `postType` | `string` | The post type of the post. |
| `postId` | `number` | The ID of the post. |

## Returns

```javascript
[ value, setValue ]
```

- `value`: the meta value, or `null` when the post or the key is not available.
- `setValue( newValue )`: sets the meta key. The other keys of the post's meta object are kept.

## Notes

- The meta key has to be registered for the post type with `show_in_rest`, and the post type has to support `custom-fields`. `src/editor-plugins/navigation-placement/navigation-placement.php` does both for the navigation meta keys.
- Uses `useEntityProp( 'postType', postType, 'meta', postId )` from `@wordpress/core-data`.
