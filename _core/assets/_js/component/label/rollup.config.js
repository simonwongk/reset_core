import { DEFAULT_EXTENSIONS } from '@babel/core';
import { terser } from 'rollup-plugin-terser';
import babel from '@rollup/plugin-babel';
import pkg from './package.json';
import { nodeResolve } from '@rollup/plugin-node-resolve';
import commonjs from '@rollup/plugin-commonjs';
import replace from '@rollup/plugin-replace';
// import external from 'rollup-plugin-peer-deps-external';

export default {
  input: 'src.js',
  output: [
    {
      file: 'index.js',
      format: 'umd',
      name: pkg.name,
      globals: {
        react: 'React',
      },
      // sourcemap: true,
    },
  ],
  external: ['react', 'react-dom'],
  plugins: [
    replace( {
    	preventAssignment: true,
    	values: { 'process.env.NODE_ENV': JSON.stringify('production') }
    	// values: { 'process.env.NODE_ENV': JSON.stringify('development') }
    } ),
    babel({
      extensions: [...DEFAULT_EXTENSIONS],
      babelHelpers: 'runtime',
      exclude: /node_modules/,
      presets: [
      	'@babel/preset-env',
      	"@babel/preset-react",
      ],
      plugins: [
      	'@babel/plugin-transform-runtime',
      	[
          'babel-plugin-transform-react-remove-prop-types',
          {
            removeImport: true,
          },
        ],
       ],
    }),
    // external({
    //   includeDependencies: true,
    // }),
	commonjs(),
    nodeResolve(),
    terser(),
  ],
};
