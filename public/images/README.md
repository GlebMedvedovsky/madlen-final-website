# /public/images/

Place your images here in the following structure:

## Folder structure

```
/public/images/
├── portfolio/
│   ├── portraits/
│   │   ├── portrait-001.webp
│   │   ├── portrait-002.webp
│   │   └── ...
│   ├── paare-familien/
│   ├── hochzeiten/
│   ├── events/
│   └── editorial/
├── hero.jpg              ← Main hero image (large B&W photo, min 1920×1200px)
└── madlen-portrait.jpg   ← Photo of Madlen for the About page (~800×1000px)
```

## Recommended formats and sizes

| Use case          | Format | Width   | Notes                          |
|-------------------|--------|---------|--------------------------------|
| Hero image        | JPG    | 1920px+ | Will be grayscale via CSS      |
| Portrait shots    | WebP   | 800px   | Portrait orientation preferred |
| Landscape shots   | WebP   | 1200px  | Landscape orientation          |
| About photo       | WebP   | 800px   | Portrait orientation           |

## How to reference in code

In `src/pages/portfolio.astro`, replace the Unsplash URLs:
```
src: '/images/portfolio/portraits/portrait-001.webp'
```

In `src/components/Hero.astro`, update the `backgroundImage` prop in `src/pages/index.astro`:
```
<Hero backgroundImage="/images/hero.jpg" />
```

In `src/pages/ueber-mich.astro`, replace the placeholder with:
```
<img src="/images/madlen-portrait.webp" alt="Madlen Medvedovskyy, Fotografin" class="..." />
```

## Performance tips

- Convert images to WebP format for ~30% smaller file sizes
- Aim for < 200 KB per portfolio image
- Use the `loading="lazy"` attribute (already set in the code)
- Consider using `sharp` or Squoosh to batch optimize images before upload
