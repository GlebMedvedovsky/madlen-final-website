# Foto & Video Madlen — Portfolio Website

A production-ready static portfolio website for a freelance photographer and videographer.

**Public site:** Astro · TypeScript · plain CSS<br>
**Local CMS feature branch:** Laravel 12 · Filament 5 · MySQL 8.4 · isolated Docker Compose

The accepted static frontend remains intact. The new CMS, private preview, release pipeline, backup/restore procedures and exact local commands are documented in [ADMIN_CMS.md](ADMIN_CMS.md). Production deployment remains unconfigured.

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
madebymadlen/
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

## Contact Form

The DE/EN form posts to the Laravel endpoint at
`https://admin.madebymadlen.de/api/contact`. Laravel validates the fields,
applies bot and rate-limit checks, and sends through the configured SMTP
mailer with server-controlled From/To addresses and the visitor as Reply-To.

The handler and real SMTP transport are disabled until the Netcup admin
hostname, HTTPS and protected environment configuration have been rehearsed.
Never put the mailbox password or another mail credential into Astro or a
frontend environment variable. Follow `docs/NETCUP_INSTALLATION_RU.md` for the
staged installation and activation procedure.

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

The canonical public origin is configured in `astro.config.mjs`:
```js
export default defineConfig({
  site: 'https://madebymadlen.de',
});
```

---

## Deployment

Production uses the repository's immutable CMS publication architecture: an
external Node runner builds the pinned source and only the validated static
release is transferred to Netcup. Do not replace this with a generic direct
Pages deployment or make the hosting account build the frontend. See
`ADMIN_CMS.md` for the architecture and `docs/NETCUP_INSTALLATION_RU.md` for
the staged Netcup installation procedure.

### Environment variables

The static build has no SMTP secret. `MADLEN_CONTACT_ENDPOINT` may override the
public Laravel contact URL at build time; SMTP credentials belong only in the
protected Laravel environment described in `docs/NETCUP_INSTALLATION_RU.md`.

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
