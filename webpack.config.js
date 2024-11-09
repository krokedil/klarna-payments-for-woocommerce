import * as url from "url";
import { resolve as _resolve } from "path";
import DependencyExtractionWebpackPlugin from "@woocommerce/dependency-extraction-webpack-plugin";
import * as glob from "glob";
import path from "path";
const __dirname = url.fileURLToPath(new URL(".", import.meta.url));
const isProduction = process.env.NODE_ENV == "production";

// Common configuration settings
const common = {
  mode: isProduction ? "production" : "development",
  module: {
    rules: [
      {
        test: /\.(ts|tsx)$/i,
        use: "ts-loader",
        exclude: ["/node_modules/", "/tests/", "/vendor/"],
      },
      {
        test: /\.s[ac]ss$/i,
        use: ["style-loader", "css-loader", "sass-loader"],
      },
    ],
  },
  resolve: {
    extensions: [".ts", ".tsx", ".scss", ".sass", ".css"],
  },
  plugins: [
    new DependencyExtractionWebpackPlugin({
      injectPolyfill: true,
    }),
  ],
};
// Blocks configuration
const blocksConfig = {
  ...common,
  entry: glob
    .sync("./blocks/src/**/!(shared)/**/index.tsx", {
      ignore: ["./blocks/src/shared/**/index.tsx"],
    })
    .reduce((entries, file) => {
      const entryName = path.basename(path.dirname(file));
      entries[entryName] = `./${file}`;
      return entries;
    }, {}),
  output: {
    path: path.resolve(__dirname, "./blocks/build/"),
    filename: `[name].js`,
  },
};

export default [blocksConfig];
