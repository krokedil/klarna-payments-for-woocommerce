// Generated using webpack-cli https://github.com/webpack/webpack-cli
const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const DependencyExtractionWebpackPlugin = require('@woocommerce/dependency-extraction-webpack-plugin');

const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

const isProduction = process.env.NODE_ENV == 'production';


const stylesHandler = MiniCssExtractPlugin.loader;



const config = {
    ...defaultConfig,
    entry: { 'klarna-payments-block': `./Blocks/Payment/src/js/klarna-payments-block.js` },
    output: {
        path: path.resolve(__dirname, './Blocks/Payment/build/'),
        filename: `[name].js`,
    },
    plugins: [
        new MiniCssExtractPlugin(),

        // Add your plugins here
        // Learn more about plugins from https://webpack.js.org/configuration/plugins/
    ],
    module: {
        ...defaultConfig.module,
        rules: [
            {
                test: /\.(js|jsx)$/i,
                loader: 'babel-loader',
            },
            {
                test: /\.css$/i,
                use: [stylesHandler,'css-loader'],
            },
            {
                test: /\.s[ac]ss$/i,
                use: [stylesHandler, 'css-loader', 'sass-loader'],
            },
            {
                test: /\.(eot|svg|ttf|woff|woff2|png|jpg|gif)$/i,
                type: 'asset',
            },

            // Add your rules for custom modules here
            // Learn more about loaders from https://webpack.js.org/loaders/
        ],
    },
    resolve: {
        extensions: ['.js', '.jsx', '.css', '.scss', ...(defaultConfig.resolve ? defaultConfig.resolve.extensions || ['.js', '.jsx'] : [])],
    },
    plugins: [
        ...defaultConfig.plugins.filter(
            (plugin) =>
                plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
        ),
        new DependencyExtractionWebpackPlugin()
    ],
};

module.exports = () => {
    if (isProduction) {
        config.mode = 'production';
         
    } else {
        config.mode = 'development';
    }
    return config;
};
