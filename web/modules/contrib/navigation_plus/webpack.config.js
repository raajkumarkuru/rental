const path = require('path');
const webpack = require('webpack');

module.exports = (env, argv) => {
  const isDev = argv.mode === 'development';

  return {
    // Source maps: 'eval-source-map' for dev (fast, readable), 'source-map' for prod (separate file).
    devtool: isDev ? 'eval-source-map' : 'source-map',
    entry: './js/edit_mode/edit-mode-app.js',
    output: {
      path: path.resolve(__dirname, 'js/edit_mode/dist'),
      filename: 'edit-mode-app.bundle.js',
    },
    module: {
      rules: [
        {
          test: /\.js$/,
          exclude: /node_modules/,
          use: {
            loader: 'babel-loader',
            options: {
              presets: [
                '@babel/preset-env',
                ['@babel/preset-react', { pragma: 'h' }],
              ],
            },
          },
        },
      ],
    },
    resolve: {
      alias: {
        'react': 'preact/compat',
        'react-dom': 'preact/compat',
      },
    },
    plugins: [
      // Define a custom variable to toggle development mode.
      new webpack.DefinePlugin({
        'process.env.DEV_MODE': JSON.stringify(isDev),
      }),
    ],
  };
};
