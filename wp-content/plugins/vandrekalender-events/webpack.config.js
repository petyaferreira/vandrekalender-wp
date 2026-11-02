/**
 * Extends the @wordpress/scripts webpack config.
 *
 * Leaflet's stylesheet refers to its images as `url(images/layers.png)`, and
 * postcss-import inlines it without rebasing those URLs, so the build cannot
 * find them. Adding Leaflet's dist folder as a module directory resolves them.
 *
 * With --experimental-modules the config is two builds into the same folder:
 * classic scripts and script modules. Both lazy-load Leaflet, and in
 * development mode both name that chunk after its path, so one overwrote the
 * other and the map loading the wrong format broke (#205). Module chunks get
 * their own `.module.js` names.
 */
const path = require('path');
const defaultConfig = require('@wordpress/scripts/config/webpack.config');

const withLeaflet = config => ({
  ...config,
  resolve: {
    ...config.resolve,
    modules: [
      ...(config.resolve?.modules || ['node_modules']),
      path.resolve(__dirname, 'node_modules/leaflet/dist'),
    ],
  },
});

const withOwnModuleChunks = config =>
  config.output?.module
    ? {
        ...config,
        output: {
          ...config.output,
          chunkFilename: '[name].module.js?ver=[chunkhash]',
        },
      }
    : config;

module.exports = Array.isArray(defaultConfig)
  ? defaultConfig.map(withLeaflet).map(withOwnModuleChunks)
  : withLeaflet(defaultConfig);
