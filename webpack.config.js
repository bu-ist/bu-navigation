const defaultConfig = require("@wordpress/scripts/config/webpack.config");

const navConfig = {
	...defaultConfig,
	entry: {
		block: './src/block.js',
		'editor-plugins/navigation-placement': './src/editor-plugins/navigation-placement/index.js',
	},
};

module.exports = [
	navConfig
];
