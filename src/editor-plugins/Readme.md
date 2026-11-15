# Editor Plugins

WordPress Block Editor plugins that extend the editor interface for BU Navigation.

## Overview

Editor plugins in this directory use the WordPress `@wordpress/plugins` API to add panels and controls to the Block Editor. Each plugin has its own folder with its registration (`index.js`), a PHP file that registers what the plugin needs on the server, and a Readme.

## Available Plugins

### navigation-placement

Adds a "Placement in Navigation" panel to the document sidebar that allows editors to:
- See where the post is in the page hierarchy, and move it to a new parent and position
- Set the navigation label
- Hide the post from navigation lists

It replaces the "Placement in Navigation" metabox on Block Editor screens.

**Location:** `src/editor-plugins/navigation-placement/`

**Documentation:** See [navigation-placement/Readme.md](./navigation-placement/Readme.md)

## Adding a Plugin

1. **Create a plugin folder** with an `index.js` that registers the plugin, a PHP file that enqueues it, and a `Readme.md`. Components used only by the plugin go in `editor-partials/`.
2. **Add a webpack entry** in `webpack.config.js`:
   ```javascript
   entry: {
   	'editor-plugins/my-plugin': './src/editor-plugins/my-plugin/index.js',
   }
   ```
3. **Load the PHP file** from `bu-navigation.php` with `require_once`, and enqueue `build/editor-plugins/my-plugin.js` on `enqueue_block_editor_assets` with the dependencies and version from `build/editor-plugins/my-plugin.asset.php`.

## Related

- **Hooks:** `src/hooks/` - Custom React hooks shared by the editor plugins
- **Block:** `src/block.js` - The Navigation block
