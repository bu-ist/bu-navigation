
# Building

All Javascript is compiled using [Grunt](http://gruntjs.com/). Assuming that both [Node & Node Package Manager](http://nodejs.org/) are installed, run the following commands using Terminal in this directory:

```bash
# Install Grunt CLI globally
$ npm install -g grunt-cli

# Install this project's dependencies
$ npm install

# Run Grunt
$ grunt
$ grunt watch
```

# Testing

Unit tests for this plugin live in the `tests/phpunit` directory. To run:

1. Install [Composer](https://getcomposer.org/)
2. Install [PHPUnit](https://phpunit.de/) (`composer install`)
3. Install the core [WP Unit Test library](https://develop.svn.wordpress.org/trunk/tests/phpunit/includes/) and configure a test install (`bin/install-wp-tests`)
4. Run the tests (`./vendor/bin/phpunit`)
