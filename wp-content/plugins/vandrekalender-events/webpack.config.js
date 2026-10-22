/**
 * Extends the @wordpress/scripts webpack config.
 *
 * Leaflet's stylesheet refers to its images as `url(images/layers.png)`, and
 * postcss-import inlines it without rebasing those URLs, so the build cannot
 * find them. Adding Leaflet's dist folder as a module directory resolves them.
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

module.exports = Array.isArray(defaultConfig)
  ? defaultConfig.map(withLeaflet)
  : withLeaflet(defaultConfig);
