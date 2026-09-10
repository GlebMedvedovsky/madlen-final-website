import { defineConfig } from 'astro/config';

export default defineConfig({
  site: 'https://madebymadlen.de',
  base: process.env.MADLEN_BASE_PATH || '/',
  outDir: process.env.MADLEN_OUT_DIR || './dist',
  compressHTML: true,
  build: {
    assets: '_assets',
  },
});
