const defaultConfig = require("@wordpress/scripts/config/webpack.config");

const navConfig = {
	...defaultConfig,
	entry: {
		block: './src/block.js'
	},
};

module.exports = [
	navConfig
];
