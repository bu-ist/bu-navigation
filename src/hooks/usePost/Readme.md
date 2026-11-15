# usePost

A custom hook that returns basic information about the post open in the Block Editor.

## Usage

```javascript
import usePost from '../../hooks/usePost';

const MyComponent = () => {
	const { postId, postType, postTypeObject, isLoading } = usePost();

	if ( isLoading ) {
		return null;
	}

	return <p>{ postTypeObject.labels.singular_name } #{ postId }</p>;
};
```

## Parameters

This hook takes no parameters.

## Returns

| Property | Type | Description |
|----------|------|-------------|
| `postId` | `number` | The ID of the post being edited. |
| `postType` | `string` | The post type slug, for example `page`. |
| `postTypeObject` | `object` | The post type object from the REST API: labels, supports, etc. `undefined` until it has loaded. |
| `isLoading` | `boolean` | Whether any of the values above is not available yet. |

## Notes

- Reads `getCurrentPostId()` and `getCurrentPostType()` from the `core/editor` store, and `getPostType()` from the `core` store.
- The values are the editor's current post. To read edited values such as the parent or the menu order, use `getEditedPostAttribute()` from the `core/editor` store.
