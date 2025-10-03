// webpack.config.js
const Encore = require('@symfony/webpack-encore');

if (!Encore.isRuntimeEnvironmentConfigured()) {
  Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
  .setOutputPath('public/build/')
  .setPublicPath('/build')
  .addEntry('home', './assets/home/index.jsx') // React homepage entry
  // keep your other entries (e.g., app) here with additional .addEntry(...)

  .enableSingleRuntimeChunk()
  .cleanupOutputBeforeBuild()
  .enableBuildNotifications()
  .enableSourceMaps(!Encore.isProduction())
  .enableVersioning(Encore.isProduction())

  // Transpile modern JS + JSX
  .configureBabel((config) => {
    config.plugins = config.plugins || [];
  }, {
    presets: [
      ['@babel/preset-env', { useBuiltIns: 'usage', corejs: 3 }],
      ['@babel/preset-react', { runtime: 'automatic' }],
    ],
  })

  // React preset helper (safe to keep even with custom babel config)
  .enableReactPreset()

  // CSS if you import any (optional)
  .enablePostCssLoader((options) => {
    // you can add postcss plugins later if needed
  })
  .addRule({
    test: /\.css$/i,
    use: [
      { loader: require('mini-css-extract-plugin').loader },
      'css-loader',
    ],
  })
;

module.exports = Encore.getWebpackConfig();
