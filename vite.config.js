import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import babel from '@rollup/plugin-babel';

const rootDir = fileURLToPath(new URL('.', import.meta.url));

const inputs = {
  'admin-image-actions': resolve(rootDir, 'src/js/admin-image-actions.js'),
  'admin-media': resolve(rootDir, 'src/js/admin-media.js'),
  'admin-settings': resolve(rootDir, 'src/js/admin-settings.js'),
  'admin-upload': resolve(rootDir, 'src/js/admin-upload.js'),
  'no-right-click': resolve(rootDir, 'src/js/no-right-click.js'),
  'admin-classic-editor': resolve(rootDir, 'src/js/admin-classic-editor.js'),
  'image-watermark': resolve(rootDir, 'src/scss/image-watermark.scss')
};

export default defineConfig({
  plugins: [
    babel({
      babelHelpers: 'bundled',
      extensions: ['.js'],
      presets: [
        [
          '@babel/preset-env',
          {
            targets: { ie: '11' },
            loose: true
          }
        ]
      ],
      exclude: 'node_modules/**'
    })
  ],
  publicDir: false,
  build: {
    target: 'es2017',
    outDir: '.',
    emptyOutDir: false,
    assetsDir: '',
    sourcemap: false,
    manifest: false,
    minify: 'esbuild',
    cssMinify: true,
    cssCodeSplit: false,
    esbuild: {
      target: 'es2017'
    },
    rollupOptions: {
      input: inputs,
      external: [],
      output: {
        entryFileNames: 'js/[name].js',
        chunkFileNames: 'js/[name].js',
        assetFileNames: assetInfo => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'css/image-watermark.css';
          }

          return 'assets/[name][extname]';
        }
      }
    }
  }
});
