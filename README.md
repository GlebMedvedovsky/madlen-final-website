# Foto & Video Madlen — Portfolio Website

A production-ready static portfolio website for a freelance photographer and videographer.

**Tech Stack:** Astro · TypeScript · plain CSS · no backend · no database

---

## Quick Start

```bash
# 1. Install dependencies
npm install

# 2. Start development server
npm run dev
# → opens at http://localhost:4321
```

---

## Project Structure

```
foto-video-madlen/
├── public/
│   ├── images/
│   │   └── README.md          ← How to add your photos
│   ├── favicon.svg
│   ├── robots.txt
│   └── _redirects             ← Cloudflare Pages config
│
├── src/
│   ├── components/
│   │   ├── Header.astro       ← Site header with responsive mobile nav
│   │   ├── Footer.astro       ← Footer with legal links
│   │   ├── Hero.astro         ← Full-screen hero section
│   │   ├── PortfolioGrid.astro ← Masonry grid with category filter
│   │   ├── ServiceCard.astro  ← Individual service block
│   │   └── ContactSection.astro ← Contact form + channel links
│   │
│   ├── layouts/
│   │   └── Layout.astro       ← Base HTML layout with SEO meta tags
│   │
│   ├── pages/
│   │   ├── index.astro        ← Home page
│   │   ├── portfolio.astro    ← Portfolio page with filter
│   │   ├── leistungen.astro   ← Services page
│   │   ├── ueber-mich.astro   ← About page
│   │   ├── kontakt.astro      ← Contact page
│   │   ├── impressum.astro    ← Legal: Impressum (placeholder)
│   │   ├── datenschutz.astro  ← Legal: Datenschutz (placeholder)
│   │   ├── agb.astro          ← Legal: AGB (placeholder)
│   │   └── 404.astro          ← 404 error page
│   │
│   └── styles/
│       └── global.css         ← Design tokens, reset, utilities
│
├── astro.config.mjs
├── tsconfig.json
└── package.json
```

---

## Available Scripts

| Command           | Description                          |
|-------------------|--------------------------------------|
| `npm run dev`     | Start dev server at localhost:4321   |
| `npm run build`   | Build production site to `./dist`    |
| `npm run preview` | Preview the production build locally |

---

## Adding Your Photos

### 1. Portfolio images

Place your photos in `/public/images/portfolio/`.

Open `src/pages/portfolio.astro` and replace the Unsplash URLs with your file paths:

```ts
const portfolioImages: PortfolioImage[] = [
  {
    src: '/images/portfolio/portrait-001.webp',
    alt: 'Stimmungsvolles Portrait einer jungen Frau im natürlichen Licht',
    category: 'Portraits',  // must match filter labels exactly
  },
  // ...
];
```

**Valid category values:**
- `Portraits`
- `Paare & Familien`
- `Hochzeiten`
- `Events`
- `Editorial & Commercial`
- `Video`

### 2. Hero image

In `src/pages/index.astro`, update the Hero component:

```astro
<Hero backgroundImage="/images/hero.jpg" />
```

Place your hero photo at `/public/images/hero.jpg`. Minimum size: 1920 × 1200 px.

### 3. About page photo

In `src/pages/ueber-mich.astro`, replace the placeholder div with:

```astro
<img
  src="/images/madlen-portrait.webp"
  alt="Madlen Medvedovskyy, Fotografin"
  class="about-img"
  loading="eager"
/>
```

---

## Connecting the Contact Form

The form currently shows a demo confirmation message. To receive real messages:

### Option A: Formspree (easiest)

1. Create a free account at [formspree.io](https://formspree.io)
2. Create a new form and copy your form ID
3. In `src/components/ContactSection.astro`, update the `<form>` tag:
   ```html
   <form action="https://formspree.io/f/YOUR_FORM_ID" method="POST" ...>
   ```
4. Remove the JavaScript `submit` handler at the bottom of the component

### Option B: Netlify Forms

1. Add `data-netlify="true"` to the `<form>` tag
2. Remove the JavaScript `submit` handler
3. Deploy to Netlify — forms are automatically detected

### Option C: Cloudflare Worker

1. Create a Worker that sends emails via [Resend](https://resend.com) or [Mailchannels](https://mailchannels.com)
2. In the form's submit handler, replace the demo code with a `fetch()` to your Worker endpoint

---

## Customizing Content

### Brand & Contact Info

Search for `TODO` comments throughout the codebase — these mark all placeholders:
- Contact email and phone in `ContactSection.astro`
- WhatsApp link in `ContactSection.astro`
- Instagram handle in `ContactSection.astro`

### Legal Pages

All three legal pages are placeholders:
- `src/pages/impressum.astro` — fill with your real business information
- `src/pages/datenschutz.astro` — generate at [datenschutz-generator.de](https://datenschutz-generator.de)
- `src/pages/agb.astro` — consult a lawyer or use [it-recht-kanzlei.de](https://www.it-recht-kanzlei.de)

### Domain in Astro Config

Update `astro.config.mjs`:
```js
export default defineConfig({
  site: 'https://www.ihre-domain.de',
});
```

---

## Deploying to Cloudflare Pages

### First-time setup

1. Push your project to GitHub or GitLab
2. Log in to [dash.cloudflare.com](https://dash.cloudflare.com)
3. Go to **Workers & Pages → Create application → Pages → Connect to Git**
4. Select your repository
5. Configure the build:
   - **Build command:** `npm run build`
   - **Build output directory:** `dist`
   - **Node.js version:** 18 or 20

### Custom domain

1. In Cloudflare Pages → your project → **Custom domains**
2. Add `foto-video-madlen.de` and `www.foto-video-madlen.de`
3. Follow the DNS configuration instructions

### Environment variables

No environment variables are required for the static site.
If you add a Cloudflare Worker for form handling, set any API keys there.

---

## Design Tokens (Color Palette)

| Token             | Value     | Usage                       |
|-------------------|-----------|-----------------------------|
| `--color-white`   | `#FFFFFF` | Backgrounds, cards          |
| `--color-cream`   | `#F9F7F4` | Section backgrounds         |
| `--color-beige`   | `#EDE7E0` | Borders, subtle backgrounds |
| `--color-sand`    | `#D4CCC2` | Dividers, muted borders     |
| `--color-gray-dark` | `#2C2C2C` | Primary text, headings    |
| `--color-bronze`  | `#8C5A2B` | Accent, CTAs, labels        |
| `--color-text-muted` | `#6B6660` | Body text, captions      |

---

## Typography

| Role      | Font                 | Google Fonts import |
|-----------|----------------------|---------------------|
| Headings  | Cormorant Garamond   | Weight 300, 400, 500, 600 |
| Body      | Inter                | Weight 300, 400, 500, 600 |

Fonts are loaded from Google Fonts via `@import` in `src/styles/global.css`.

---

## Performance Notes

- All portfolio images use `loading="lazy"` and `decoding="async"`
- Hero image uses `loading="eager"` to avoid LCP penalty
- No JavaScript frameworks — vanilla JS only for menu toggle and filter
- CSS is scoped to each component via Astro's built-in scoping
- No external JS libraries beyond Google Fonts

---

## Browser Support

Modern browsers (Chrome, Firefox, Safari, Edge). No IE11 support.
CSS custom properties and CSS Grid are used throughout.
