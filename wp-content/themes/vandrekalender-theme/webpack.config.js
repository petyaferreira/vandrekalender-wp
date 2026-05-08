const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		screen: path.resolve( __dirname, 'resources/screen.js' ),
		editor: path.resolve( __dirname, 'resources/editor.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'public' ),
	},
};
