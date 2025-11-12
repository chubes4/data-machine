const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		'pipelines-react':
			'./inc/Core/Admin/Pages/Pipelines/assets/react/index.jsx',
	},
	output: {
		...defaultConfig.output,
		path: path.resolve(
			__dirname,
			'inc/Core/Admin/Pages/Pipelines/assets/build'
		),
	},
};
