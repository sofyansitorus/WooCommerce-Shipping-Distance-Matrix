/* eslint-disable @typescript-eslint/no-require-imports */
const path = require("path");
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const { CleanWebpackPlugin } = require("clean-webpack-plugin");

module.exports = function () {
  const config = {
    entry: {
      "wcsdm-backend": ["./src/ts/backend.ts", "./src/scss/backend.scss"],
    },
    output: {
      path: path.resolve(__dirname, "assets"),
      filename: "js/[name].js",
    },
    resolve: {
      extensions: [".ts", ".js"],
      alias: {
        "@": path.resolve(__dirname, "src"),
      },
    },
    module: {
      rules: [
        {
          test: /\.(ts|js)$/,
          exclude: /node_modules/,
          use: {
            loader: "babel-loader",
            options: {
              presets: ["@babel/preset-env", "@babel/preset-typescript"],
            },
          },
        },
        {
          test: /\.scss$/,
          use: [
            MiniCssExtractPlugin.loader,
            "css-loader",
            {
              loader: "postcss-loader",
              options: {
                postcssOptions: {
                  plugins: [require("autoprefixer")],
                },
              },
            },
            "sass-loader",
          ],
        },
      ],
    },
    plugins: [
      new MiniCssExtractPlugin({
        filename: "css/[name].css",
      }),
      new CleanWebpackPlugin(),
    ],
    externals: {
      jquery: "jQuery",
      wp: "wp",
    },
  };

  return config;
};
