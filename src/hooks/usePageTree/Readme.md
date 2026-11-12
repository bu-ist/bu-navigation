# usePageTree

A custom hook that loads the page tree of a post type one level at a time from the `bu-navigation/v1/tree` REST route.
Loading one level at a time keeps the Move dialog fast on sites with thousands of pages.

## Usage

```javascript
import { useEffect } from '@wordpress/element';
import usePageTree from '../../hooks/usePageTree';

const MyComponent = ( { postType } ) => {
	const { levels, loadLevel } = usePageTree( postType );

	// Load the top level pages.
	useEffect( () => {
		loadLevel( 0 );
	}, [ loadLevel ] );

	const topLevel = levels[ 0 ];

	if ( ! topLevel || topLevel.isLoading ) {
		return null;
	}

	return (
		<ul>
			{ topLevel.pages.map( ( page ) => <li key={ page.id }>{ page.label }</li> ) }
		</ul>
	);
};
```

## Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `postType` | `string` | The post type of the tree. |

## Returns

| Property | Type | Description |
|----------|------|-------------|
| `levels` | `object` | Loaded levels, keyed by parent ID (`0` is the top level). Each level is `{ pages, isLoading, error }`. |
| `loadLevel` | `function` | `loadLevel( parentId )` requests the children of a page, or the top level pages for `0`. A level is requested once; a level that failed is requested again. |

## Page Object

Each page in `levels[ parentId ].pages` comes from the REST route, in navigation order:

| Property | Type | Description |
|----------|------|-------------|
| `id` | `number` | The post ID. |
| `label` | `string` | The navigation label, or the title when no label is set. |
| `type` | `string` | The post type. Links added in "Edit Order" use the link post type. |
| `status` | `string` | `publish` or `private`. |
| `parent` | `number` | The parent post ID. |
| `menuOrder` | `number` | The position among its siblings. |
| `excluded` | `boolean` | Whether the page is hidden from navigation lists. |
| `hasChildren` | `boolean` | Whether the page has child pages to load. |
| `canEdit` | `boolean` | `false` for pages a section editor cannot edit in BU Section Editing. |

## Notes

- The route requires the `edit_posts` capability of the post type.
- The tree is built by `BU_Navigation_Tree_View` with the same instance name as the Classic Editor metabox (`nav_metabox`), so plugins that filter that tree apply to both.
- BU Section Editing adds its tree filters on admin requests only. On this REST request, `canEdit` for a section editor comes from its permission check, `BU_Group_Permissions::can_edit_section()`, which reads the same section group data.
