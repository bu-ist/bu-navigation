# Navigation Placement Editor Plugin

A WordPress Block Editor plugin that adds a "Placement in Navigation" panel to the document sidebar. It replaces the "Placement in Navigation" metabox on Block Editor screens; the Classic Editor keeps using the metabox (`admin/post.php`).

## Overview

The panel is rendered with `PluginDocumentSettingPanel`, which places it directly below the Status & Visibility (Summary) panel. It provides:
- The post's location in the page hierarchy, as breadcrumbs.
- A **Move** dialog to choose the post's parent page and its position among that page's child pages.
- A **Label** field for the navigation label.
- A **Hide from navigation** checkbox. New pages show in navigation lists unless this is checked.

The plugin loads for the post types supported by BU Navigation that use the Block Editor (hierarchical post types with `show_in_rest`), when the navigation manager feature is on.

## Component Structure

```
navigation-placement/
├── index.js                    # Plugin registration and the panel
├── navigation-placement.php    # Post meta, REST route, REST save handling and script enqueue
├── editor.scss                 # Panel and Move dialog styles
├── editor-partials/
│   ├── location-breadcrumbs/   # LocationBreadcrumbs component
│   ├── move-page-modal/        # MovePageModal component
│   ├── page-tree/              # PageTree component, used by MovePageModal
│   └── visibility-control/     # VisibilityControl component
└── Readme.md                   # This file
```

### NavigationPlacementPanel

**Location:** `index.js`

Renders the panel once the post data has loaded, and keeps the breadcrumbs in step with the Move dialog. Moving the post calls `editPost( { parent, menu_order } )`, so the new location is saved with the post.

### LocationBreadcrumbs

**Location:** `editor-partials/location-breadcrumbs/index.js`

The post's ancestors from the top level down, followed by the post. A top level post shows "Top level page" instead. The ancestors come from PHP when the editor loads, then from the Move dialog.

### MovePageModal

**Location:** `editor-partials/move-page-modal/index.js`

A dialog with the page tree as a set of radio buttons, then a **Position** select ("First" or "After …"). The tree opens at the post's location, and each level loads from the REST API when it is shown (see the `usePageTree` hook), so large sites do not load every page at once. Pages that are not published carry a badge with their status ("private", "draft", "pending" or "scheduled").

It follows the rules of the Classic Editor metabox:
- The post cannot be placed in itself or in a link, and its own child pages are not listed.
- When top level posts are not allowed in navigation lists (the "Allow Top-Level Pages" setting is off), a post that shows in navigation lists can only be placed at the top level if it already showed there.
- When BU Section Editing is active, a section editor moving a published post cannot place it at the top level or in a page they cannot edit ("denied"). Drafts can be placed anywhere.

The position is the 1-based `menu_order` that `BU_Navigation_Reorder_Tracker` expects.

### VisibilityControl

**Location:** `editor-partials/visibility-control/index.js`

The "Hide from navigation" checkbox. Checked stores `'1'` and unchecked stores `'0'` in the navigation visibility meta. When the post is at the top level and top level posts are not allowed in navigation lists, the checkbox is checked and disabled, with help text that says how to change the setting.

## Post Meta

| Meta Key | Values | Description |
|----------|--------|-------------|
| `_bu_cms_navigation_page_label` | string | The navigation label. Empty uses the post title. |
| `_bu_cms_navigation_exclude` | `'1'`, `'0'` or empty | `'1'` hides the post from navigation lists, `'0'` shows it. Empty uses the `BU_NAVIGATION_POST_EXCLUDE_DEFAULT` constant. |

Both keys are registered with `show_in_rest` and no `default`, so posts without a value keep falling back to the title and to `BU_NAVIGATION_POST_EXCLUDE_DEFAULT`. Only users who can edit the post can change them.

New posts show in navigation lists: on a new post's first save, `navigation-placement.php` stores `'0'` when no value was chosen, or `'1'` for a top level post when top level posts are not allowed in navigation lists.

## PHP

`navigation-placement.php`:
- Registers the two meta keys for the REST API, and adds `custom-fields` and `page-attributes` support to the supported post types that lack it (the REST API needs them for post meta and `menu_order`). Core's Custom Fields metabox is removed from the edit screen of the post types it adds `custom-fields` to.
- Registers `GET /bu-navigation/v1/tree?post_type=page&child_of=0&post_id=123`, which returns one level of the page tree. It requires the post type's `edit_posts` capability. The tree lists published pages, and private pages for users who can read them; with `post_id`, the unpublished ancestors of that post are added to their levels, so a post in a draft section can stay where it is.
- Deletes an empty navigation visibility value after a REST API save. The block editor sends every registered meta key, and an empty value stored for a post that had none would read as "show" in the navigation lists instead of falling back to `BU_NAVIGATION_POST_EXCLUDE_DEFAULT`.
- Renumbers the siblings after a post moves in a REST API save, as `BU_Navigation_Admin_Post::save()` does for the Classic Editor.
- Enqueues the built script and passes `buNavigationPlacementSettings` to it.

## Settings

`window.buNavigationPlacementSettings`, from `wp_localize_script()`:

| Key | Description |
|-----|-------------|
| `allowTop` | Whether top level posts are allowed in navigation lists. |
| `excludeDefault` | The value of `BU_NAVIGATION_POST_EXCLUDE_DEFAULT`. |
| `linkPostType` | The post type of navigation links. |
| `isSectionEditor` | Whether BU Section Editing treats the user as a section editor. |
| `ancestors` | The post's ancestors from the top level down, as `{ id, label }` objects. |
| `metaKeys` | The `label` and `exclude` meta keys. |

`wp_localize_script()` passes top level values as strings, so booleans arrive as `'1'` or `''`.

## Build

Webpack entry (in `webpack.config.js`):

```javascript
entry: {
	'editor-plugins/navigation-placement': './src/editor-plugins/navigation-placement/index.js',
}
```

`npm run build` writes `build/editor-plugins/navigation-placement.js`, `.asset.php` and `.css`.

## Dependencies

### WordPress Packages
- `@wordpress/plugins` - Plugin registration
- `@wordpress/editor` - `PluginDocumentSettingPanel` component (WordPress 6.6 and later)
- `@wordpress/edit-post` - `PluginDocumentSettingPanel` component (earlier versions)
- `@wordpress/components` - `BaseControl`, `Button`, `CheckboxControl`, `Modal`, `Notice`, `SelectControl`, `Spinner`, `TextControl`
- `@wordpress/data` - The `core/editor` store
- `@wordpress/core-data` - `useEntityProp`
- `@wordpress/api-fetch` - Requests to the tree route
- `@wordpress/element` - React and hooks
- `@wordpress/i18n` - Internationalization
- `@wordpress/dom-ready` - DOM ready utility

### Internal Dependencies
- `usePost` - Gets current post data
- `usePostMeta` - Gets and sets post meta values
- `usePageTree` - Loads the page tree one level at a time

## Related

- **Hooks:** [src/hooks/Readme.md](../../hooks/Readme.md)
- **Classic Editor metabox:** `admin/post.php`, `templates/metabox-navigation-attributes.php`
