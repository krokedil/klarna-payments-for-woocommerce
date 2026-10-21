/**
 * Build config for the plugin, run with `npm run build` (or `npm run start` to watch).
 *
 * It exports two webpack configs:
 * - blocks: bundles each block in blocks/src into blocks/build.
 * - assets: minifies the plain scripts and styles in assets/ to *.min.js and *.min.css next to the source.
 */
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { globSync } = require( 'glob' );
const WooCommerceDependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );
const wpScriptsConfig = require( '@wordpress/scripts/config/webpack.config.js' );

// Start from the wp-scripts defaults (loaders, minimizer, CSS extraction) and adjust them below.
const defaultConfig = Array.isArray( wpScriptsConfig ) ? wpScriptsConfig[ 0 ] : wpScriptsConfig;
const MiniCSSExtractPlugin = defaultConfig.plugins.find( ( plugin ) => plugin.constructor.name === 'MiniCssExtractPlugin' ).constructor;

// Always compile with the WordPress Babel preset, so a stray .babelrc can't change the output.
const babelPreset = require.resolve( '@wordpress/babel-preset-default', {
	paths: [ path.dirname( require.resolve( '@wordpress/scripts/package.json' ) ) ],
} );

// Classic JSX runtime for the blocks, so the dependencies in <name>.asset.php stay the same as before
// the move to wp-scripts. The automatic runtime would add react-jsx-runtime.
const classicJsx = [
	require.resolve( '@babel/plugin-transform-react-jsx', { paths: [ path.dirname( babelPreset ) ] } ),
	{ runtime: 'classic' },
];

// Helpers to change the options of one loader inside the wp-scripts rules.
const isLoader = ( loader, name ) => new RegExp( `[\\\\/]${ name }[\\\\/]` ).test( String( loader.loader ?? loader ) );
const mapLoaders = ( rules, map ) =>
	rules.map( ( rule ) => ( { ...rule, use: Array.isArray( rule.use ) ? rule.use.map( map ) : rule.use } ) );
const withBabelPreset = ( loader, extra = {} ) =>
	isLoader( loader, 'babel-loader' )
		? { ...loader, options: { ...loader.options, babelrc: false, configFile: false, presets: [ babelPreset ], ...extra } }
		: loader;

// Which files get built. ASSET_GLOBS comes from asset_globs in .krokedil-baseline.json.
const ASSET_GLOBS = [];
const BLOCK_GLOB = 'blocks/src/**/index.{js,jsx,ts,tsx}';

// Blocks: one bundle per blocks/src/<name>/index.*, named after the folder.
// blocks/src/shared holds code imported by several blocks and is not a block itself.
const blockEntries = Object.fromEntries(
	globSync( BLOCK_GLOB, { ignore: 'blocks/src/shared/**', posix: true } ).map( ( file ) => [
		path.basename( path.dirname( file ) ),
		'./' + file,
	] )
);

const blocksConfig = {
	...defaultConfig,
	entry: blockEntries,
	// Output: blocks/build/<name>.js, <name>.asset.php and style-<name>.css when the block imports CSS (enqueue that file).
	output: { ...defaultConfig.output, path: path.resolve( __dirname, 'blocks/build' ) },
	module: { ...defaultConfig.module, rules: mapLoaders( defaultConfig.module.rules, ( loader ) => withBabelPreset( loader, { plugins: [ classicJsx ] } ) ) },
	plugins: [
		// The WooCommerce version also treats @woocommerce/* packages as external, so they are loaded
		// from WooCommerce at runtime instead of bundled, and listed in <name>.asset.php.
		...defaultConfig.plugins.filter( ( plugin ) => plugin.constructor.name !== 'DependencyExtractionWebpackPlugin' ),
		new WooCommerceDependencyExtractionWebpackPlugin( { injectPolyfill: true } ),
	],
};

// Assets: every matching .js and .css file becomes <file>.min.js or <file>.min.css in the same folder,
// so the plugin enqueues the .min file from the same path as the source.
// Files that use import/export are ES modules and need bundling, so they are left out with a warning.
const isModule = ( file ) => file.endsWith( '.js' ) && /^\s*(import|export)\b|\bimport\(/m.test( fs.readFileSync( file, 'utf8' ) );
const assetFiles = globSync( ASSET_GLOBS, { ignore: '**/*.min.*', posix: true } );
const skipped = assetFiles.filter( isModule );
if ( skipped.length ) {
	console.warn( `Not minified, these use import/export:\n  ${ skipped.join( '\n  ' ) }` );
}
const assetEntries = Object.fromEntries(
	assetFiles
		.filter( ( file ) => ! skipped.includes( file ) )
		.map( ( file ) => [ file.replace( /\.(js|css)$/, '.min' ), './' + file ] )
);

// Keep url() paths in CSS as written. They are relative to the stylesheet, which stays in the same folder.
const withoutUrlRewrite = ( loader ) =>
	isLoader( loader, 'css-loader' ) ? { ...loader, options: { ...loader.options, url: false, modules: false } } : loader;

// webpack always emits a .js file per entry. For a CSS entry that file is empty, so delete it.
class RemoveCssEntryScripts {
	apply( compiler ) {
		compiler.hooks.thisCompilation.tap( 'RemoveCssEntryScripts', ( compilation ) => {
			compilation.hooks.processAssets.tap(
				{ name: 'RemoveCssEntryScripts', stage: compiler.webpack.Compilation.PROCESS_ASSETS_STAGE_SUMMARIZE },
				() => {
					for ( const [ name, entry ] of Object.entries( assetEntries ) ) {
						if ( entry.endsWith( '.css' ) ) {
							compilation.deleteAsset( name + '.js' );
							compilation.deleteAsset( name + '.js.map' );
						}
					}
				}
			);
		} );
	}
}

const assetsConfig = {
	mode: defaultConfig.mode,
	target: defaultConfig.target,
	devtool: false,
	entry: assetEntries,
	// Entry names already hold the folder, so output goes to the plugin root.
	// clean must stay false: the output path is the plugin root.
	// iife false: top-level functions and variables stay global, as other scripts and inline code may use them.
	output: { path: __dirname, filename: '[name].js', clean: false, iife: false },
	// Each file is minified on its own, nothing is split out or merged.
	optimization: { minimizer: defaultConfig.optimization.minimizer, splitChunks: false, concatenateModules: false },
	// javascript/auto: with "type": "module" in package.json webpack would otherwise treat every .js file as strict ES module code.
	module: { rules: mapLoaders( defaultConfig.module.rules.map( ( rule ) => ( rule.test?.test?.( 'x.js' ) ? { ...rule, type: 'javascript/auto' } : rule ) ), ( loader ) =>
		// sourceType script: these are plain browser scripts, don't add "use strict" or module semantics.
		withoutUrlRewrite( withBabelPreset( loader, { sourceType: 'script' } ) ) ) },
	plugins: [ new MiniCSSExtractPlugin( { filename: '[name].css' } ), new RemoveCssEntryScripts() ],
	stats: defaultConfig.stats,
};

// Only run a config that has something to build.
module.exports = [
	Object.keys( blockEntries ).length && blocksConfig,
	Object.keys( assetEntries ).length && assetsConfig,
].filter( Boolean );
