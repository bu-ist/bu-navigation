# BU Navigation Core Widget

Contains the core data code for [BU Navigation](https://github.com/bu-ist/bu-navigation), along with the code for the navigation widget.  This package exists so that the widget can be shared with other plugins, providing a navigation widget where BU Navigation may not be installed.

## Installation

To install this package, add this github repo to your `composer.json` to let know composer where it should download the package from:

``` json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/bu-ist/bu-navigation-core-widget.git"
    }
],
```

Then add the package to the composer require section:

``` json
"require": {
    "bu-ist/bu-navigation-core-widget": "1.0.*"
}
```

Additionally a custom install path can be defined:

``` json
"extra": {
    "installer-paths": {
        "inc/{$name}": ["bu-ist/bu-navigation-core-widget"]
    }
}
```

Once setup, the package can be installed with a `composer install` or `composer update` command.

## Initialization

There is not an autoloader provided at present, so each file should be required at the location where composer has installed them.

Setup also requires some additional steps to operate outside of BU Navigation:

- registering the widget
- providing a `supported_posts_type` function
- setting a link post type constant

The following example checks to see if the main BU Navigation plugin is loaded, and initializes the local composer installed library if not:

``` php
global $bu_navigation_plugin;

 // Include the BU Navigation core and widget, if BU Navigation isn't already available.
if ( ! $bu_navigation_plugin ) {
    require __DIR__ . '/inc/bu-navigation-core-widget/src/class-navigation-widget.php';
    require __DIR__ . '/inc/bu-navigation-core-widget/src/data-active-section.php';
    require __DIR__ . '/inc/bu-navigation-core-widget/src/data-format.php';
    require __DIR__ . '/inc/bu-navigation-core-widget/src/data-get-urls.php';
    require __DIR__ . '/inc/bu-navigation-core-widget/src/data-model.php';
    require __DIR__ . '/inc/bu-navigation-core-widget/src/data-nav-labels.php';
    require __DIR__ . '/inc/bu-navigation-core-widget/src/data-widget.php';
    require __DIR__ . '/inc/bu-navigation-core-widget/src/filters.php';

    define( 'BU_NAVIGATION_LINK_POST_TYPE', 'link' );

    add_action( 'widgets_init', function() {
        register_widget( 'BU\Plugins\Navigation\Navigation_Widget' );
    });

    /**
     * If BU Navigation isn't loaded, declare a bu_navigation_supported_post_types function
     * that returns an array of post types that the navigation should display.
     *
     * @return array Array of supported custom post types
     */
    function bu_navigation_supported_post_types() {
        return [ 'page' ];
    }
}
```
