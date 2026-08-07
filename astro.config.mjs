import { defineConfig } from 'astro/config';

export default defineConfig({
  site: 'https://foto-video-madlen.de',
  compressHTML: true,
  build: {
    assets: '_assets',
  },
});
