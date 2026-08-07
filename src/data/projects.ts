export type ProjectCategory = "editorial" | "events" | "weddings" | "landscape";
export type GallerySide = "left" | "right";

export interface LocalizedText {
  de: string;
  en: string;
}

export interface ProjectImage {
  src: string;
  altDe: string;
  altEn: string;
  order: number;
  side: GallerySide;
}

export interface Project {
  slug: string;
  title: LocalizedText;
  category: ProjectCategory;
  cover: string;
  description: LocalizedText;
  images: ProjectImage[];
}

export const projects: Project[] = [
  {
    "slug": "rainbow",
    "title": {
      "de": "Rainbow",
      "en": "Rainbow"
    },
    "category": "editorial",
    "cover": "/images/projects/rainbow/0-titelfoto-rainbow-orange.webp",
    "description": {
      "de": "Editoriale Fotografie mit einem filmischen Blick für Farbe, Form und Persönlichkeit.",
      "en": "Editorial photography with a cinematic eye for colour, form and personality."
    },
    "images": [
      {
        "src": "/images/projects/rainbow/1-rainbow-violett.webp",
        "altDe": "Rainbow – Fotografie von Madlen Medvedovskyy",
        "altEn": "Rainbow — photography by Madlen Medvedovskyy",
        "order": 100,
        "side": "left"
      },
      {
        "src": "/images/projects/rainbow/2-rainbow-rosa.webp",
        "altDe": "Rainbow – Fotografie von Madlen Medvedovskyy",
        "altEn": "Rainbow — photography by Madlen Medvedovskyy",
        "order": 200,
        "side": "right"
      },
      {
        "src": "/images/projects/rainbow/3-rainbow-violett.webp",
        "altDe": "Rainbow – Fotografie von Madlen Medvedovskyy",
        "altEn": "Rainbow — photography by Madlen Medvedovskyy",
        "order": 300,
        "side": "left"
      },
      {
        "src": "/images/projects/rainbow/4-rainbow-rosa.webp",
        "altDe": "Rainbow – Fotografie von Madlen Medvedovskyy",
        "altEn": "Rainbow — photography by Madlen Medvedovskyy",
        "order": 400,
        "side": "right"
      },
      {
        "src": "/images/projects/rainbow/5-rainbow-violett.webp",
        "altDe": "Rainbow – Fotografie von Madlen Medvedovskyy",
        "altEn": "Rainbow — photography by Madlen Medvedovskyy",
        "order": 500,
        "side": "left"
      },
      {
        "src": "/images/projects/rainbow/6-rainbow-rosa.webp",
        "altDe": "Rainbow – Fotografie von Madlen Medvedovskyy",
        "altEn": "Rainbow — photography by Madlen Medvedovskyy",
        "order": 600,
        "side": "right"
      },
      {
        "src": "/images/projects/rainbow/7-rainbow-rosa.webp",
        "altDe": "Rainbow – Fotografie von Madlen Medvedovskyy",
        "altEn": "Rainbow — photography by Madlen Medvedovskyy",
        "order": 700,
        "side": "left"
      },
      {
        "src": "/images/projects/rainbow/8-rainbow-rosa.webp",
        "altDe": "Rainbow – Fotografie von Madlen Medvedovskyy",
        "altEn": "Rainbow — photography by Madlen Medvedovskyy",
        "order": 800,
        "side": "right"
      },
      {
        "src": "/images/projects/rainbow/9-rainbow-blau.webp",
        "altDe": "Rainbow – Fotografie von Madlen Medvedovskyy",
        "altEn": "Rainbow — photography by Madlen Medvedovskyy",
        "order": 900,
        "side": "left"
      },
      {
        "src": "/images/projects/rainbow/10-rainbow-blau.webp",
        "altDe": "Rainbow – Fotografie von Madlen Medvedovskyy",
        "altEn": "Rainbow — photography by Madlen Medvedovskyy",
        "order": 1000,
        "side": "right"
      },
      {
        "src": "/images/projects/rainbow/11-rainbow-grun.webp",
        "altDe": "Rainbow – Fotografie von Madlen Medvedovskyy",
        "altEn": "Rainbow — photography by Madlen Medvedovskyy",
        "order": 1100,
        "side": "left"
      },
      {
        "src": "/images/projects/rainbow/12-rainbow-grun.webp",
        "altDe": "Rainbow – Fotografie von Madlen Medvedovskyy",
        "altEn": "Rainbow — photography by Madlen Medvedovskyy",
        "order": 1200,
        "side": "right"
      },
      {
        "src": "/images/projects/rainbow/13-rainbow-grun.webp",
        "altDe": "Rainbow – Fotografie von Madlen Medvedovskyy",
        "altEn": "Rainbow — photography by Madlen Medvedovskyy",
        "order": 1300,
        "side": "left"
      },
      {
        "src": "/images/projects/rainbow/14-rainbow-grun.webp",
        "altDe": "Rainbow – Fotografie von Madlen Medvedovskyy",
        "altEn": "Rainbow — photography by Madlen Medvedovskyy",
        "order": 1400,
        "side": "right"
      },
      {
        "src": "/images/projects/rainbow/14-rainbow-orange.webp",
        "altDe": "Rainbow – Fotografie von Madlen Medvedovskyy",
        "altEn": "Rainbow — photography by Madlen Medvedovskyy",
        "order": 1400,
        "side": "right"
      },
      {
        "src": "/images/projects/rainbow/15-rainbow-orange.webp",
        "altDe": "Rainbow – Fotografie von Madlen Medvedovskyy",
        "altEn": "Rainbow — photography by Madlen Medvedovskyy",
        "order": 1500,
        "side": "left"
      },
      {
        "src": "/images/projects/rainbow/16-rainbow-orange.webp",
        "altDe": "Rainbow – Fotografie von Madlen Medvedovskyy",
        "altEn": "Rainbow — photography by Madlen Medvedovskyy",
        "order": 1600,
        "side": "right"
      },
      {
        "src": "/images/projects/rainbow/17-rainbow-orange.webp",
        "altDe": "Rainbow – Fotografie von Madlen Medvedovskyy",
        "altEn": "Rainbow — photography by Madlen Medvedovskyy",
        "order": 1700,
        "side": "left"
      }
    ]
  },
  {
    "slug": "renaissance",
    "title": {
      "de": "Renaissance",
      "en": "Renaissance"
    },
    "category": "editorial",
    "cover": "/images/projects/renaissance/0-renaissance.webp",
    "description": {
      "de": "Editoriale Fotografie mit einem filmischen Blick für Farbe, Form und Persönlichkeit.",
      "en": "Editorial photography with a cinematic eye for colour, form and personality."
    },
    "images": [
      {
        "src": "/images/projects/renaissance/1-renaissance.webp",
        "altDe": "Renaissance – Fotografie von Madlen Medvedovskyy",
        "altEn": "Renaissance — photography by Madlen Medvedovskyy",
        "order": 100,
        "side": "left"
      },
      {
        "src": "/images/projects/renaissance/2-renaissance.webp",
        "altDe": "Renaissance – Fotografie von Madlen Medvedovskyy",
        "altEn": "Renaissance — photography by Madlen Medvedovskyy",
        "order": 200,
        "side": "right"
      },
      {
        "src": "/images/projects/renaissance/3-renaissance.webp",
        "altDe": "Renaissance – Fotografie von Madlen Medvedovskyy",
        "altEn": "Renaissance — photography by Madlen Medvedovskyy",
        "order": 300,
        "side": "left"
      },
      {
        "src": "/images/projects/renaissance/4-renaissance.webp",
        "altDe": "Renaissance – Fotografie von Madlen Medvedovskyy",
        "altEn": "Renaissance — photography by Madlen Medvedovskyy",
        "order": 400,
        "side": "right"
      },
      {
        "src": "/images/projects/renaissance/5-renaissance.webp",
        "altDe": "Renaissance – Fotografie von Madlen Medvedovskyy",
        "altEn": "Renaissance — photography by Madlen Medvedovskyy",
        "order": 500,
        "side": "left"
      },
      {
        "src": "/images/projects/renaissance/6-renaissance.webp",
        "altDe": "Renaissance – Fotografie von Madlen Medvedovskyy",
        "altEn": "Renaissance — photography by Madlen Medvedovskyy",
        "order": 600,
        "side": "right"
      },
      {
        "src": "/images/projects/renaissance/7-renaissance.webp",
        "altDe": "Renaissance – Fotografie von Madlen Medvedovskyy",
        "altEn": "Renaissance — photography by Madlen Medvedovskyy",
        "order": 700,
        "side": "left"
      },
      {
        "src": "/images/projects/renaissance/8-renaissance.webp",
        "altDe": "Renaissance – Fotografie von Madlen Medvedovskyy",
        "altEn": "Renaissance — photography by Madlen Medvedovskyy",
        "order": 800,
        "side": "right"
      },
      {
        "src": "/images/projects/renaissance/9-renaissance.webp",
        "altDe": "Renaissance – Fotografie von Madlen Medvedovskyy",
        "altEn": "Renaissance — photography by Madlen Medvedovskyy",
        "order": 900,
        "side": "left"
      },
      {
        "src": "/images/projects/renaissance/10-renaissance.webp",
        "altDe": "Renaissance – Fotografie von Madlen Medvedovskyy",
        "altEn": "Renaissance — photography by Madlen Medvedovskyy",
        "order": 1000,
        "side": "right"
      },
      {
        "src": "/images/projects/renaissance/11-renaissance.webp",
        "altDe": "Renaissance – Fotografie von Madlen Medvedovskyy",
        "altEn": "Renaissance — photography by Madlen Medvedovskyy",
        "order": 1100,
        "side": "left"
      },
      {
        "src": "/images/projects/renaissance/12-renaissance.webp",
        "altDe": "Renaissance – Fotografie von Madlen Medvedovskyy",
        "altEn": "Renaissance — photography by Madlen Medvedovskyy",
        "order": 1200,
        "side": "right"
      },
      {
        "src": "/images/projects/renaissance/13-renaissance.webp",
        "altDe": "Renaissance – Fotografie von Madlen Medvedovskyy",
        "altEn": "Renaissance — photography by Madlen Medvedovskyy",
        "order": 1300,
        "side": "left"
      },
      {
        "src": "/images/projects/renaissance/14-renaissance.webp",
        "altDe": "Renaissance – Fotografie von Madlen Medvedovskyy",
        "altEn": "Renaissance — photography by Madlen Medvedovskyy",
        "order": 1400,
        "side": "right"
      },
      {
        "src": "/images/projects/renaissance/15-renaissance.webp",
        "altDe": "Renaissance – Fotografie von Madlen Medvedovskyy",
        "altEn": "Renaissance — photography by Madlen Medvedovskyy",
        "order": 1500,
        "side": "left"
      },
      {
        "src": "/images/projects/renaissance/17-renaissance.webp",
        "altDe": "Renaissance – Fotografie von Madlen Medvedovskyy",
        "altEn": "Renaissance — photography by Madlen Medvedovskyy",
        "order": 1700,
        "side": "left"
      }
    ]
  },
  {
    "slug": "many-facets",
    "title": {
      "de": "Many Facets",
      "en": "Many Facets"
    },
    "category": "editorial",
    "cover": "/images/projects/many-facets/0-manyfacets.webp",
    "description": {
      "de": "Editoriale Fotografie mit einem filmischen Blick für Farbe, Form und Persönlichkeit.",
      "en": "Editorial photography with a cinematic eye for colour, form and personality."
    },
    "images": [
      {
        "src": "/images/projects/many-facets/1-manyfacets.webp",
        "altDe": "Many Facets – Fotografie von Madlen Medvedovskyy",
        "altEn": "Many Facets — photography by Madlen Medvedovskyy",
        "order": 100,
        "side": "left"
      },
      {
        "src": "/images/projects/many-facets/2-manyfacets.webp",
        "altDe": "Many Facets – Fotografie von Madlen Medvedovskyy",
        "altEn": "Many Facets — photography by Madlen Medvedovskyy",
        "order": 200,
        "side": "right"
      },
      {
        "src": "/images/projects/many-facets/3-manyfacets.webp",
        "altDe": "Many Facets – Fotografie von Madlen Medvedovskyy",
        "altEn": "Many Facets — photography by Madlen Medvedovskyy",
        "order": 300,
        "side": "left"
      },
      {
        "src": "/images/projects/many-facets/4-manyfacets.webp",
        "altDe": "Many Facets – Fotografie von Madlen Medvedovskyy",
        "altEn": "Many Facets — photography by Madlen Medvedovskyy",
        "order": 400,
        "side": "right"
      },
      {
        "src": "/images/projects/many-facets/5-manyfacets.webp",
        "altDe": "Many Facets – Fotografie von Madlen Medvedovskyy",
        "altEn": "Many Facets — photography by Madlen Medvedovskyy",
        "order": 500,
        "side": "left"
      },
      {
        "src": "/images/projects/many-facets/6-manyfacets.webp",
        "altDe": "Many Facets – Fotografie von Madlen Medvedovskyy",
        "altEn": "Many Facets — photography by Madlen Medvedovskyy",
        "order": 600,
        "side": "right"
      },
      {
        "src": "/images/projects/many-facets/7-manyfacets.webp",
        "altDe": "Many Facets – Fotografie von Madlen Medvedovskyy",
        "altEn": "Many Facets — photography by Madlen Medvedovskyy",
        "order": 700,
        "side": "left"
      },
      {
        "src": "/images/projects/many-facets/8-manyfacets.webp",
        "altDe": "Many Facets – Fotografie von Madlen Medvedovskyy",
        "altEn": "Many Facets — photography by Madlen Medvedovskyy",
        "order": 800,
        "side": "right"
      },
      {
        "src": "/images/projects/many-facets/9-manyfacets.webp",
        "altDe": "Many Facets – Fotografie von Madlen Medvedovskyy",
        "altEn": "Many Facets — photography by Madlen Medvedovskyy",
        "order": 900,
        "side": "left"
      },
      {
        "src": "/images/projects/many-facets/10-manyfacets.webp",
        "altDe": "Many Facets – Fotografie von Madlen Medvedovskyy",
        "altEn": "Many Facets — photography by Madlen Medvedovskyy",
        "order": 1000,
        "side": "right"
      },
      {
        "src": "/images/projects/many-facets/11-manyfacets.webp",
        "altDe": "Many Facets – Fotografie von Madlen Medvedovskyy",
        "altEn": "Many Facets — photography by Madlen Medvedovskyy",
        "order": 1100,
        "side": "left"
      },
      {
        "src": "/images/projects/many-facets/12-manyfacets.webp",
        "altDe": "Many Facets – Fotografie von Madlen Medvedovskyy",
        "altEn": "Many Facets — photography by Madlen Medvedovskyy",
        "order": 1200,
        "side": "right"
      }
    ]
  },
  {
    "slug": "easy-morning",
    "title": {
      "de": "Easy Morning",
      "en": "Easy Morning"
    },
    "category": "editorial",
    "cover": "/images/projects/easy-morning/0-easymorning.webp",
    "description": {
      "de": "Editoriale Fotografie mit einem filmischen Blick für Farbe, Form und Persönlichkeit.",
      "en": "Editorial photography with a cinematic eye for colour, form and personality."
    },
    "images": [
      {
        "src": "/images/projects/easy-morning/1-easymorning.webp",
        "altDe": "Easy Morning – Fotografie von Madlen Medvedovskyy",
        "altEn": "Easy Morning — photography by Madlen Medvedovskyy",
        "order": 100,
        "side": "left"
      },
      {
        "src": "/images/projects/easy-morning/2-easymorning.webp",
        "altDe": "Easy Morning – Fotografie von Madlen Medvedovskyy",
        "altEn": "Easy Morning — photography by Madlen Medvedovskyy",
        "order": 200,
        "side": "right"
      },
      {
        "src": "/images/projects/easy-morning/3-easymorning.webp",
        "altDe": "Easy Morning – Fotografie von Madlen Medvedovskyy",
        "altEn": "Easy Morning — photography by Madlen Medvedovskyy",
        "order": 300,
        "side": "left"
      },
      {
        "src": "/images/projects/easy-morning/4-easymorning.webp",
        "altDe": "Easy Morning – Fotografie von Madlen Medvedovskyy",
        "altEn": "Easy Morning — photography by Madlen Medvedovskyy",
        "order": 400,
        "side": "right"
      },
      {
        "src": "/images/projects/easy-morning/5-easymorning.webp",
        "altDe": "Easy Morning – Fotografie von Madlen Medvedovskyy",
        "altEn": "Easy Morning — photography by Madlen Medvedovskyy",
        "order": 500,
        "side": "left"
      },
      {
        "src": "/images/projects/easy-morning/6-easymorning.webp",
        "altDe": "Easy Morning – Fotografie von Madlen Medvedovskyy",
        "altEn": "Easy Morning — photography by Madlen Medvedovskyy",
        "order": 600,
        "side": "right"
      },
      {
        "src": "/images/projects/easy-morning/7-easymorning.webp",
        "altDe": "Easy Morning – Fotografie von Madlen Medvedovskyy",
        "altEn": "Easy Morning — photography by Madlen Medvedovskyy",
        "order": 700,
        "side": "left"
      },
      {
        "src": "/images/projects/easy-morning/8-easymorning.webp",
        "altDe": "Easy Morning – Fotografie von Madlen Medvedovskyy",
        "altEn": "Easy Morning — photography by Madlen Medvedovskyy",
        "order": 800,
        "side": "right"
      },
      {
        "src": "/images/projects/easy-morning/9-easymorning.webp",
        "altDe": "Easy Morning – Fotografie von Madlen Medvedovskyy",
        "altEn": "Easy Morning — photography by Madlen Medvedovskyy",
        "order": 900,
        "side": "left"
      },
      {
        "src": "/images/projects/easy-morning/10-easymorning.webp",
        "altDe": "Easy Morning – Fotografie von Madlen Medvedovskyy",
        "altEn": "Easy Morning — photography by Madlen Medvedovskyy",
        "order": 1000,
        "side": "right"
      },
      {
        "src": "/images/projects/easy-morning/11-easymorning.webp",
        "altDe": "Easy Morning – Fotografie von Madlen Medvedovskyy",
        "altEn": "Easy Morning — photography by Madlen Medvedovskyy",
        "order": 1100,
        "side": "left"
      },
      {
        "src": "/images/projects/easy-morning/12-easymorning.webp",
        "altDe": "Easy Morning – Fotografie von Madlen Medvedovskyy",
        "altEn": "Easy Morning — photography by Madlen Medvedovskyy",
        "order": 1200,
        "side": "right"
      },
      {
        "src": "/images/projects/easy-morning/13-easymorning.webp",
        "altDe": "Easy Morning – Fotografie von Madlen Medvedovskyy",
        "altEn": "Easy Morning — photography by Madlen Medvedovskyy",
        "order": 1300,
        "side": "left"
      },
      {
        "src": "/images/projects/easy-morning/14-easymorning.webp",
        "altDe": "Easy Morning – Fotografie von Madlen Medvedovskyy",
        "altEn": "Easy Morning — photography by Madlen Medvedovskyy",
        "order": 1400,
        "side": "right"
      }
    ]
  },
  {
    "slug": "she-blooms-in-blue",
    "title": {
      "de": "She Blooms in Blue",
      "en": "She Blooms in Blue"
    },
    "category": "editorial",
    "cover": "/images/projects/she-blooms-in-blue/0-shebloomsinblue.webp",
    "description": {
      "de": "Editoriale Fotografie mit einem filmischen Blick für Farbe, Form und Persönlichkeit.",
      "en": "Editorial photography with a cinematic eye for colour, form and personality."
    },
    "images": [
      {
        "src": "/images/projects/she-blooms-in-blue/1-shebloomsinblue.webp",
        "altDe": "She Blooms in Blue – Fotografie von Madlen Medvedovskyy",
        "altEn": "She Blooms in Blue — photography by Madlen Medvedovskyy",
        "order": 100,
        "side": "left"
      },
      {
        "src": "/images/projects/she-blooms-in-blue/2-shebloomsinblue.webp",
        "altDe": "She Blooms in Blue – Fotografie von Madlen Medvedovskyy",
        "altEn": "She Blooms in Blue — photography by Madlen Medvedovskyy",
        "order": 200,
        "side": "right"
      },
      {
        "src": "/images/projects/she-blooms-in-blue/3-shebloomsinblue.webp",
        "altDe": "She Blooms in Blue – Fotografie von Madlen Medvedovskyy",
        "altEn": "She Blooms in Blue — photography by Madlen Medvedovskyy",
        "order": 300,
        "side": "left"
      },
      {
        "src": "/images/projects/she-blooms-in-blue/4-shebloomsinblue.webp",
        "altDe": "She Blooms in Blue – Fotografie von Madlen Medvedovskyy",
        "altEn": "She Blooms in Blue — photography by Madlen Medvedovskyy",
        "order": 400,
        "side": "right"
      },
      {
        "src": "/images/projects/she-blooms-in-blue/5-shebloomsinblue.webp",
        "altDe": "She Blooms in Blue – Fotografie von Madlen Medvedovskyy",
        "altEn": "She Blooms in Blue — photography by Madlen Medvedovskyy",
        "order": 500,
        "side": "left"
      }
    ]
  },
  {
    "slug": "about-pop",
    "title": {
      "de": "ABOUT POP",
      "en": "ABOUT POP"
    },
    "category": "events",
    "cover": "/images/projects/about-pop/0-aboutpop.webp",
    "description": {
      "de": "Eine atmosphärische Eventreportage mit Blick für Menschen, Details und echte Momente.",
      "en": "An atmospheric event story focused on people, details and genuine moments."
    },
    "images": [
      {
        "src": "/images/projects/about-pop/1-aboutpop.webp",
        "altDe": "ABOUT POP – Fotografie von Madlen Medvedovskyy",
        "altEn": "ABOUT POP — photography by Madlen Medvedovskyy",
        "order": 100,
        "side": "left"
      },
      {
        "src": "/images/projects/about-pop/2-aboutpop.webp",
        "altDe": "ABOUT POP – Fotografie von Madlen Medvedovskyy",
        "altEn": "ABOUT POP — photography by Madlen Medvedovskyy",
        "order": 200,
        "side": "right"
      },
      {
        "src": "/images/projects/about-pop/3-aboutpop.webp",
        "altDe": "ABOUT POP – Fotografie von Madlen Medvedovskyy",
        "altEn": "ABOUT POP — photography by Madlen Medvedovskyy",
        "order": 300,
        "side": "left"
      },
      {
        "src": "/images/projects/about-pop/4-1-aboutpop.webp",
        "altDe": "ABOUT POP – Fotografie von Madlen Medvedovskyy",
        "altEn": "ABOUT POP — photography by Madlen Medvedovskyy",
        "order": 401,
        "side": "right"
      },
      {
        "src": "/images/projects/about-pop/4-2-aboutpop.webp",
        "altDe": "ABOUT POP – Fotografie von Madlen Medvedovskyy",
        "altEn": "ABOUT POP — photography by Madlen Medvedovskyy",
        "order": 402,
        "side": "right"
      },
      {
        "src": "/images/projects/about-pop/5-1-aboutpop.webp",
        "altDe": "ABOUT POP – Fotografie von Madlen Medvedovskyy",
        "altEn": "ABOUT POP — photography by Madlen Medvedovskyy",
        "order": 501,
        "side": "left"
      },
      {
        "src": "/images/projects/about-pop/5-2-aboutpop.webp",
        "altDe": "ABOUT POP – Fotografie von Madlen Medvedovskyy",
        "altEn": "ABOUT POP — photography by Madlen Medvedovskyy",
        "order": 502,
        "side": "left"
      },
      {
        "src": "/images/projects/about-pop/6-aboutpop.webp",
        "altDe": "ABOUT POP – Fotografie von Madlen Medvedovskyy",
        "altEn": "ABOUT POP — photography by Madlen Medvedovskyy",
        "order": 600,
        "side": "right"
      },
      {
        "src": "/images/projects/about-pop/7-aboutpop.webp",
        "altDe": "ABOUT POP – Fotografie von Madlen Medvedovskyy",
        "altEn": "ABOUT POP — photography by Madlen Medvedovskyy",
        "order": 700,
        "side": "left"
      },
      {
        "src": "/images/projects/about-pop/8-aboutpop.webp",
        "altDe": "ABOUT POP – Fotografie von Madlen Medvedovskyy",
        "altEn": "ABOUT POP — photography by Madlen Medvedovskyy",
        "order": 800,
        "side": "right"
      },
      {
        "src": "/images/projects/about-pop/9-aboutpop.webp",
        "altDe": "ABOUT POP – Fotografie von Madlen Medvedovskyy",
        "altEn": "ABOUT POP — photography by Madlen Medvedovskyy",
        "order": 900,
        "side": "left"
      },
      {
        "src": "/images/projects/about-pop/10-aboutpop.webp",
        "altDe": "ABOUT POP – Fotografie von Madlen Medvedovskyy",
        "altEn": "ABOUT POP — photography by Madlen Medvedovskyy",
        "order": 1000,
        "side": "right"
      },
      {
        "src": "/images/projects/about-pop/11-aboutpop.webp",
        "altDe": "ABOUT POP – Fotografie von Madlen Medvedovskyy",
        "altEn": "ABOUT POP — photography by Madlen Medvedovskyy",
        "order": 1100,
        "side": "left"
      },
      {
        "src": "/images/projects/about-pop/12-aboutpop.webp",
        "altDe": "ABOUT POP – Fotografie von Madlen Medvedovskyy",
        "altEn": "ABOUT POP — photography by Madlen Medvedovskyy",
        "order": 1200,
        "side": "right"
      },
      {
        "src": "/images/projects/about-pop/13-aboutpop.webp",
        "altDe": "ABOUT POP – Fotografie von Madlen Medvedovskyy",
        "altEn": "ABOUT POP — photography by Madlen Medvedovskyy",
        "order": 1300,
        "side": "left"
      },
      {
        "src": "/images/projects/about-pop/14-aboutpop.webp",
        "altDe": "ABOUT POP – Fotografie von Madlen Medvedovskyy",
        "altEn": "ABOUT POP — photography by Madlen Medvedovskyy",
        "order": 1400,
        "side": "right"
      },
      {
        "src": "/images/projects/about-pop/15-aboutpop.webp",
        "altDe": "ABOUT POP – Fotografie von Madlen Medvedovskyy",
        "altEn": "ABOUT POP — photography by Madlen Medvedovskyy",
        "order": 1500,
        "side": "left"
      },
      {
        "src": "/images/projects/about-pop/16-aboutpop.webp",
        "altDe": "ABOUT POP – Fotografie von Madlen Medvedovskyy",
        "altEn": "ABOUT POP — photography by Madlen Medvedovskyy",
        "order": 1600,
        "side": "right"
      },
      {
        "src": "/images/projects/about-pop/17-aboutpop.webp",
        "altDe": "ABOUT POP – Fotografie von Madlen Medvedovskyy",
        "altEn": "ABOUT POP — photography by Madlen Medvedovskyy",
        "order": 1700,
        "side": "left"
      },
      {
        "src": "/images/projects/about-pop/18-aboutpop.webp",
        "altDe": "ABOUT POP – Fotografie von Madlen Medvedovskyy",
        "altEn": "ABOUT POP — photography by Madlen Medvedovskyy",
        "order": 1800,
        "side": "right"
      },
      {
        "src": "/images/projects/about-pop/19-aboutpop.webp",
        "altDe": "ABOUT POP – Fotografie von Madlen Medvedovskyy",
        "altEn": "ABOUT POP — photography by Madlen Medvedovskyy",
        "order": 1900,
        "side": "left"
      },
      {
        "src": "/images/projects/about-pop/20-aboutpop.webp",
        "altDe": "ABOUT POP – Fotografie von Madlen Medvedovskyy",
        "altEn": "ABOUT POP — photography by Madlen Medvedovskyy",
        "order": 2000,
        "side": "right"
      },
      {
        "src": "/images/projects/about-pop/21-aboutpop.webp",
        "altDe": "ABOUT POP – Fotografie von Madlen Medvedovskyy",
        "altEn": "ABOUT POP — photography by Madlen Medvedovskyy",
        "order": 2100,
        "side": "left"
      }
    ]
  },
  {
    "slug": "personio-2",
    "title": {
      "de": "Personio 2.0",
      "en": "Personio 2.0"
    },
    "category": "events",
    "cover": "/images/projects/personio-2/0-personio.webp",
    "description": {
      "de": "Eine atmosphärische Eventreportage mit Blick für Menschen, Details und echte Momente.",
      "en": "An atmospheric event story focused on people, details and genuine moments."
    },
    "images": [
      {
        "src": "/images/projects/personio-2/1-personio.webp",
        "altDe": "Personio 2.0 – Fotografie von Madlen Medvedovskyy",
        "altEn": "Personio 2.0 — photography by Madlen Medvedovskyy",
        "order": 100,
        "side": "left"
      },
      {
        "src": "/images/projects/personio-2/2-personio.webp",
        "altDe": "Personio 2.0 – Fotografie von Madlen Medvedovskyy",
        "altEn": "Personio 2.0 — photography by Madlen Medvedovskyy",
        "order": 200,
        "side": "right"
      },
      {
        "src": "/images/projects/personio-2/3-personio.webp",
        "altDe": "Personio 2.0 – Fotografie von Madlen Medvedovskyy",
        "altEn": "Personio 2.0 — photography by Madlen Medvedovskyy",
        "order": 300,
        "side": "left"
      },
      {
        "src": "/images/projects/personio-2/4-personio.webp",
        "altDe": "Personio 2.0 – Fotografie von Madlen Medvedovskyy",
        "altEn": "Personio 2.0 — photography by Madlen Medvedovskyy",
        "order": 400,
        "side": "right"
      },
      {
        "src": "/images/projects/personio-2/5-personio.webp",
        "altDe": "Personio 2.0 – Fotografie von Madlen Medvedovskyy",
        "altEn": "Personio 2.0 — photography by Madlen Medvedovskyy",
        "order": 500,
        "side": "left"
      },
      {
        "src": "/images/projects/personio-2/6-personio.webp",
        "altDe": "Personio 2.0 – Fotografie von Madlen Medvedovskyy",
        "altEn": "Personio 2.0 — photography by Madlen Medvedovskyy",
        "order": 600,
        "side": "right"
      }
    ]
  },
  {
    "slug": "partscloud",
    "title": {
      "de": "PartsCloud",
      "en": "PartsCloud"
    },
    "category": "events",
    "cover": "/images/projects/partscloud/0-sps.webp",
    "description": {
      "de": "Eine atmosphärische Eventreportage mit Blick für Menschen, Details und echte Momente.",
      "en": "An atmospheric event story focused on people, details and genuine moments."
    },
    "images": [
      {
        "src": "/images/projects/partscloud/1-sps.webp",
        "altDe": "PartsCloud – Fotografie von Madlen Medvedovskyy",
        "altEn": "PartsCloud — photography by Madlen Medvedovskyy",
        "order": 100,
        "side": "left"
      },
      {
        "src": "/images/projects/partscloud/2-sps.webp",
        "altDe": "PartsCloud – Fotografie von Madlen Medvedovskyy",
        "altEn": "PartsCloud — photography by Madlen Medvedovskyy",
        "order": 200,
        "side": "right"
      },
      {
        "src": "/images/projects/partscloud/3-sps.webp",
        "altDe": "PartsCloud – Fotografie von Madlen Medvedovskyy",
        "altEn": "PartsCloud — photography by Madlen Medvedovskyy",
        "order": 300,
        "side": "left"
      },
      {
        "src": "/images/projects/partscloud/4-sps.webp",
        "altDe": "PartsCloud – Fotografie von Madlen Medvedovskyy",
        "altEn": "PartsCloud — photography by Madlen Medvedovskyy",
        "order": 400,
        "side": "right"
      },
      {
        "src": "/images/projects/partscloud/5-sps.webp",
        "altDe": "PartsCloud – Fotografie von Madlen Medvedovskyy",
        "altEn": "PartsCloud — photography by Madlen Medvedovskyy",
        "order": 500,
        "side": "left"
      },
      {
        "src": "/images/projects/partscloud/6-sps.webp",
        "altDe": "PartsCloud – Fotografie von Madlen Medvedovskyy",
        "altEn": "PartsCloud — photography by Madlen Medvedovskyy",
        "order": 600,
        "side": "right"
      },
      {
        "src": "/images/projects/partscloud/7-sps.webp",
        "altDe": "PartsCloud – Fotografie von Madlen Medvedovskyy",
        "altEn": "PartsCloud — photography by Madlen Medvedovskyy",
        "order": 700,
        "side": "left"
      },
      {
        "src": "/images/projects/partscloud/8-sps.webp",
        "altDe": "PartsCloud – Fotografie von Madlen Medvedovskyy",
        "altEn": "PartsCloud — photography by Madlen Medvedovskyy",
        "order": 800,
        "side": "right"
      },
      {
        "src": "/images/projects/partscloud/9-sps.webp",
        "altDe": "PartsCloud – Fotografie von Madlen Medvedovskyy",
        "altEn": "PartsCloud — photography by Madlen Medvedovskyy",
        "order": 900,
        "side": "left"
      },
      {
        "src": "/images/projects/partscloud/10-sps.webp",
        "altDe": "PartsCloud – Fotografie von Madlen Medvedovskyy",
        "altEn": "PartsCloud — photography by Madlen Medvedovskyy",
        "order": 1000,
        "side": "right"
      }
    ]
  },
  {
    "slug": "porsche-postcards",
    "title": {
      "de": "Porsche Postcards",
      "en": "Porsche Postcards"
    },
    "category": "events",
    "cover": "/images/projects/porsche-postcards/0-porschepostcards.webp",
    "description": {
      "de": "Eine atmosphärische Eventreportage mit Blick für Menschen, Details und echte Momente.",
      "en": "An atmospheric event story focused on people, details and genuine moments."
    },
    "images": [
      {
        "src": "/images/projects/porsche-postcards/1-porschepostcards.webp",
        "altDe": "Porsche Postcards – Fotografie von Madlen Medvedovskyy",
        "altEn": "Porsche Postcards — photography by Madlen Medvedovskyy",
        "order": 100,
        "side": "left"
      },
      {
        "src": "/images/projects/porsche-postcards/2-porschepostcards.webp",
        "altDe": "Porsche Postcards – Fotografie von Madlen Medvedovskyy",
        "altEn": "Porsche Postcards — photography by Madlen Medvedovskyy",
        "order": 200,
        "side": "right"
      },
      {
        "src": "/images/projects/porsche-postcards/3-porschepostcards.webp",
        "altDe": "Porsche Postcards – Fotografie von Madlen Medvedovskyy",
        "altEn": "Porsche Postcards — photography by Madlen Medvedovskyy",
        "order": 300,
        "side": "left"
      },
      {
        "src": "/images/projects/porsche-postcards/4-porschepostcards.webp",
        "altDe": "Porsche Postcards – Fotografie von Madlen Medvedovskyy",
        "altEn": "Porsche Postcards — photography by Madlen Medvedovskyy",
        "order": 400,
        "side": "right"
      },
      {
        "src": "/images/projects/porsche-postcards/5-porschepostcards.webp",
        "altDe": "Porsche Postcards – Fotografie von Madlen Medvedovskyy",
        "altEn": "Porsche Postcards — photography by Madlen Medvedovskyy",
        "order": 500,
        "side": "left"
      },
      {
        "src": "/images/projects/porsche-postcards/6-porschepostcards.webp",
        "altDe": "Porsche Postcards – Fotografie von Madlen Medvedovskyy",
        "altEn": "Porsche Postcards — photography by Madlen Medvedovskyy",
        "order": 600,
        "side": "right"
      },
      {
        "src": "/images/projects/porsche-postcards/7-porschepostcards.webp",
        "altDe": "Porsche Postcards – Fotografie von Madlen Medvedovskyy",
        "altEn": "Porsche Postcards — photography by Madlen Medvedovskyy",
        "order": 700,
        "side": "left"
      },
      {
        "src": "/images/projects/porsche-postcards/8-porschepostcards.webp",
        "altDe": "Porsche Postcards – Fotografie von Madlen Medvedovskyy",
        "altEn": "Porsche Postcards — photography by Madlen Medvedovskyy",
        "order": 800,
        "side": "right"
      }
    ]
  },
  {
    "slug": "sicherheitspolitischer-dialog",
    "title": {
      "de": "Sicherheitspolitischer Dialog",
      "en": "Security Policy Dialogue"
    },
    "category": "events",
    "cover": "/images/projects/sicherheitspolitischer-dialog/0-sicherheitspolitischer-dialog-pressestatement.webp",
    "description": {
      "de": "Eine atmosphärische Eventreportage mit Blick für Menschen, Details und echte Momente.",
      "en": "An atmospheric event story focused on people, details and genuine moments."
    },
    "images": [
      {
        "src": "/images/projects/sicherheitspolitischer-dialog/1-sicherheitspolitischer-dialog-pressestatement.webp",
        "altDe": "Sicherheitspolitischer Dialog – Fotografie von Madlen Medvedovskyy",
        "altEn": "Security Policy Dialogue — photography by Madlen Medvedovskyy",
        "order": 100,
        "side": "left"
      },
      {
        "src": "/images/projects/sicherheitspolitischer-dialog/2-sicherheitspolitischer-dialog-pressestatement.webp",
        "altDe": "Sicherheitspolitischer Dialog – Fotografie von Madlen Medvedovskyy",
        "altEn": "Security Policy Dialogue — photography by Madlen Medvedovskyy",
        "order": 200,
        "side": "right"
      },
      {
        "src": "/images/projects/sicherheitspolitischer-dialog/3-sicherheitspolitischer-dialog-pressestatement.webp",
        "altDe": "Sicherheitspolitischer Dialog – Fotografie von Madlen Medvedovskyy",
        "altEn": "Security Policy Dialogue — photography by Madlen Medvedovskyy",
        "order": 300,
        "side": "left"
      }
    ]
  },
  {
    "slug": "laura-micha",
    "title": {
      "de": "Laura & Micha",
      "en": "Laura & Micha"
    },
    "category": "weddings",
    "cover": "/images/projects/laura-micha/0-laura-micha.webp",
    "description": {
      "de": "Eine persönliche Hochzeitsreportage, ruhig beobachtet und emotional erzählt.",
      "en": "A personal wedding story, quietly observed and emotionally told."
    },
    "images": [
      {
        "src": "/images/projects/laura-micha/2-laura-micha.webp",
        "altDe": "Laura & Micha – Fotografie von Madlen Medvedovskyy",
        "altEn": "Laura & Micha — photography by Madlen Medvedovskyy",
        "order": 200,
        "side": "right"
      },
      {
        "src": "/images/projects/laura-micha/2-laura-micha-1.webp",
        "altDe": "Laura & Micha – Fotografie von Madlen Medvedovskyy",
        "altEn": "Laura & Micha — photography by Madlen Medvedovskyy",
        "order": 200,
        "side": "right"
      },
      {
        "src": "/images/projects/laura-micha/3-laura-micha.webp",
        "altDe": "Laura & Micha – Fotografie von Madlen Medvedovskyy",
        "altEn": "Laura & Micha — photography by Madlen Medvedovskyy",
        "order": 300,
        "side": "left"
      },
      {
        "src": "/images/projects/laura-micha/4-laura-micha.webp",
        "altDe": "Laura & Micha – Fotografie von Madlen Medvedovskyy",
        "altEn": "Laura & Micha — photography by Madlen Medvedovskyy",
        "order": 400,
        "side": "right"
      },
      {
        "src": "/images/projects/laura-micha/5-laura-micha.webp",
        "altDe": "Laura & Micha – Fotografie von Madlen Medvedovskyy",
        "altEn": "Laura & Micha — photography by Madlen Medvedovskyy",
        "order": 500,
        "side": "left"
      },
      {
        "src": "/images/projects/laura-micha/6-laura-micha.webp",
        "altDe": "Laura & Micha – Fotografie von Madlen Medvedovskyy",
        "altEn": "Laura & Micha — photography by Madlen Medvedovskyy",
        "order": 600,
        "side": "right"
      },
      {
        "src": "/images/projects/laura-micha/7-laura-micha.webp",
        "altDe": "Laura & Micha – Fotografie von Madlen Medvedovskyy",
        "altEn": "Laura & Micha — photography by Madlen Medvedovskyy",
        "order": 700,
        "side": "left"
      },
      {
        "src": "/images/projects/laura-micha/8-laura-micha.webp",
        "altDe": "Laura & Micha – Fotografie von Madlen Medvedovskyy",
        "altEn": "Laura & Micha — photography by Madlen Medvedovskyy",
        "order": 800,
        "side": "right"
      },
      {
        "src": "/images/projects/laura-micha/9-laura-micha.webp",
        "altDe": "Laura & Micha – Fotografie von Madlen Medvedovskyy",
        "altEn": "Laura & Micha — photography by Madlen Medvedovskyy",
        "order": 900,
        "side": "left"
      },
      {
        "src": "/images/projects/laura-micha/10-laura-micha.webp",
        "altDe": "Laura & Micha – Fotografie von Madlen Medvedovskyy",
        "altEn": "Laura & Micha — photography by Madlen Medvedovskyy",
        "order": 1000,
        "side": "right"
      },
      {
        "src": "/images/projects/laura-micha/11-laura-micha.webp",
        "altDe": "Laura & Micha – Fotografie von Madlen Medvedovskyy",
        "altEn": "Laura & Micha — photography by Madlen Medvedovskyy",
        "order": 1100,
        "side": "left"
      },
      {
        "src": "/images/projects/laura-micha/12-laura-micha.webp",
        "altDe": "Laura & Micha – Fotografie von Madlen Medvedovskyy",
        "altEn": "Laura & Micha — photography by Madlen Medvedovskyy",
        "order": 1200,
        "side": "right"
      },
      {
        "src": "/images/projects/laura-micha/13-laura-micha.webp",
        "altDe": "Laura & Micha – Fotografie von Madlen Medvedovskyy",
        "altEn": "Laura & Micha — photography by Madlen Medvedovskyy",
        "order": 1300,
        "side": "left"
      },
      {
        "src": "/images/projects/laura-micha/14-laura-micha.webp",
        "altDe": "Laura & Micha – Fotografie von Madlen Medvedovskyy",
        "altEn": "Laura & Micha — photography by Madlen Medvedovskyy",
        "order": 1400,
        "side": "right"
      }
    ]
  },
  {
    "slug": "simon-lena",
    "title": {
      "de": "Simon & Lena",
      "en": "Simon & Lena"
    },
    "category": "weddings",
    "cover": "/images/projects/simon-lena/0-simonlena.webp",
    "description": {
      "de": "Eine persönliche Hochzeitsreportage, ruhig beobachtet und emotional erzählt.",
      "en": "A personal wedding story, quietly observed and emotionally told."
    },
    "images": [
      {
        "src": "/images/projects/simon-lena/1-simonlena.webp",
        "altDe": "Simon & Lena – Fotografie von Madlen Medvedovskyy",
        "altEn": "Simon & Lena — photography by Madlen Medvedovskyy",
        "order": 100,
        "side": "left"
      },
      {
        "src": "/images/projects/simon-lena/2-simonlena.webp",
        "altDe": "Simon & Lena – Fotografie von Madlen Medvedovskyy",
        "altEn": "Simon & Lena — photography by Madlen Medvedovskyy",
        "order": 200,
        "side": "right"
      },
      {
        "src": "/images/projects/simon-lena/3-simonlena.webp",
        "altDe": "Simon & Lena – Fotografie von Madlen Medvedovskyy",
        "altEn": "Simon & Lena — photography by Madlen Medvedovskyy",
        "order": 300,
        "side": "left"
      },
      {
        "src": "/images/projects/simon-lena/4-simonlena.webp",
        "altDe": "Simon & Lena – Fotografie von Madlen Medvedovskyy",
        "altEn": "Simon & Lena — photography by Madlen Medvedovskyy",
        "order": 400,
        "side": "right"
      },
      {
        "src": "/images/projects/simon-lena/5-simonlena.webp",
        "altDe": "Simon & Lena – Fotografie von Madlen Medvedovskyy",
        "altEn": "Simon & Lena — photography by Madlen Medvedovskyy",
        "order": 500,
        "side": "left"
      },
      {
        "src": "/images/projects/simon-lena/6-simonlena.webp",
        "altDe": "Simon & Lena – Fotografie von Madlen Medvedovskyy",
        "altEn": "Simon & Lena — photography by Madlen Medvedovskyy",
        "order": 600,
        "side": "right"
      }
    ]
  },
  {
    "slug": "joko-erik",
    "title": {
      "de": "Joko & Erik",
      "en": "Joko & Erik"
    },
    "category": "weddings",
    "cover": "/images/projects/joko-erik/0-johannaerik.webp",
    "description": {
      "de": "Eine persönliche Hochzeitsreportage, ruhig beobachtet und emotional erzählt.",
      "en": "A personal wedding story, quietly observed and emotionally told."
    },
    "images": [
      {
        "src": "/images/projects/joko-erik/1-johannaerik.webp",
        "altDe": "Joko & Erik – Fotografie von Madlen Medvedovskyy",
        "altEn": "Joko & Erik — photography by Madlen Medvedovskyy",
        "order": 100,
        "side": "left"
      },
      {
        "src": "/images/projects/joko-erik/2-johannaerik.webp",
        "altDe": "Joko & Erik – Fotografie von Madlen Medvedovskyy",
        "altEn": "Joko & Erik — photography by Madlen Medvedovskyy",
        "order": 200,
        "side": "right"
      },
      {
        "src": "/images/projects/joko-erik/3-johannaerik.webp",
        "altDe": "Joko & Erik – Fotografie von Madlen Medvedovskyy",
        "altEn": "Joko & Erik — photography by Madlen Medvedovskyy",
        "order": 300,
        "side": "left"
      },
      {
        "src": "/images/projects/joko-erik/4-johannaerik.webp",
        "altDe": "Joko & Erik – Fotografie von Madlen Medvedovskyy",
        "altEn": "Joko & Erik — photography by Madlen Medvedovskyy",
        "order": 400,
        "side": "right"
      },
      {
        "src": "/images/projects/joko-erik/5-johannaerik.webp",
        "altDe": "Joko & Erik – Fotografie von Madlen Medvedovskyy",
        "altEn": "Joko & Erik — photography by Madlen Medvedovskyy",
        "order": 500,
        "side": "left"
      },
      {
        "src": "/images/projects/joko-erik/6-johannaerik.webp",
        "altDe": "Joko & Erik – Fotografie von Madlen Medvedovskyy",
        "altEn": "Joko & Erik — photography by Madlen Medvedovskyy",
        "order": 600,
        "side": "right"
      },
      {
        "src": "/images/projects/joko-erik/7-johannaerik.webp",
        "altDe": "Joko & Erik – Fotografie von Madlen Medvedovskyy",
        "altEn": "Joko & Erik — photography by Madlen Medvedovskyy",
        "order": 700,
        "side": "left"
      },
      {
        "src": "/images/projects/joko-erik/8-johannaerik.webp",
        "altDe": "Joko & Erik – Fotografie von Madlen Medvedovskyy",
        "altEn": "Joko & Erik — photography by Madlen Medvedovskyy",
        "order": 800,
        "side": "right"
      }
    ]
  },
  {
    "slug": "johanna-karle",
    "title": {
      "de": "Johanna & Karle",
      "en": "Johanna & Karle"
    },
    "category": "weddings",
    "cover": "/images/projects/johanna-karle/0-johannakarle.webp",
    "description": {
      "de": "Eine persönliche Hochzeitsreportage, ruhig beobachtet und emotional erzählt.",
      "en": "A personal wedding story, quietly observed and emotionally told."
    },
    "images": [
      {
        "src": "/images/projects/johanna-karle/1-johannakarle.webp",
        "altDe": "Johanna & Karle – Fotografie von Madlen Medvedovskyy",
        "altEn": "Johanna & Karle — photography by Madlen Medvedovskyy",
        "order": 100,
        "side": "left"
      },
      {
        "src": "/images/projects/johanna-karle/2-johannakarle.webp",
        "altDe": "Johanna & Karle – Fotografie von Madlen Medvedovskyy",
        "altEn": "Johanna & Karle — photography by Madlen Medvedovskyy",
        "order": 200,
        "side": "right"
      },
      {
        "src": "/images/projects/johanna-karle/3-johannakarle.webp",
        "altDe": "Johanna & Karle – Fotografie von Madlen Medvedovskyy",
        "altEn": "Johanna & Karle — photography by Madlen Medvedovskyy",
        "order": 300,
        "side": "left"
      },
      {
        "src": "/images/projects/johanna-karle/4-johannakarle.webp",
        "altDe": "Johanna & Karle – Fotografie von Madlen Medvedovskyy",
        "altEn": "Johanna & Karle — photography by Madlen Medvedovskyy",
        "order": 400,
        "side": "right"
      },
      {
        "src": "/images/projects/johanna-karle/5-johannakarle.webp",
        "altDe": "Johanna & Karle – Fotografie von Madlen Medvedovskyy",
        "altEn": "Johanna & Karle — photography by Madlen Medvedovskyy",
        "order": 500,
        "side": "left"
      },
      {
        "src": "/images/projects/johanna-karle/6-johannakarle.webp",
        "altDe": "Johanna & Karle – Fotografie von Madlen Medvedovskyy",
        "altEn": "Johanna & Karle — photography by Madlen Medvedovskyy",
        "order": 600,
        "side": "right"
      },
      {
        "src": "/images/projects/johanna-karle/7-johannakarle.webp",
        "altDe": "Johanna & Karle – Fotografie von Madlen Medvedovskyy",
        "altEn": "Johanna & Karle — photography by Madlen Medvedovskyy",
        "order": 700,
        "side": "left"
      },
      {
        "src": "/images/projects/johanna-karle/8-johannakarle.webp",
        "altDe": "Johanna & Karle – Fotografie von Madlen Medvedovskyy",
        "altEn": "Johanna & Karle — photography by Madlen Medvedovskyy",
        "order": 800,
        "side": "right"
      },
      {
        "src": "/images/projects/johanna-karle/9-johannakarle.webp",
        "altDe": "Johanna & Karle – Fotografie von Madlen Medvedovskyy",
        "altEn": "Johanna & Karle — photography by Madlen Medvedovskyy",
        "order": 900,
        "side": "left"
      },
      {
        "src": "/images/projects/johanna-karle/10-johannakarle.webp",
        "altDe": "Johanna & Karle – Fotografie von Madlen Medvedovskyy",
        "altEn": "Johanna & Karle — photography by Madlen Medvedovskyy",
        "order": 1000,
        "side": "right"
      }
    ]
  },
  {
    "slug": "eibsee",
    "title": {
      "de": "Eibsee",
      "en": "Eibsee"
    },
    "category": "landscape",
    "cover": "/images/projects/eibsee/eibsee-1.webp",
    "description": {
      "de": "Landschaftsfotografie zwischen Ruhe, Weite und natürlichem Licht.",
      "en": "Landscape photography shaped by stillness, space and natural light."
    },
    "images": [
      {
        "src": "/images/projects/eibsee/eibsee-2.webp",
        "altDe": "Eibsee – Fotografie von Madlen Medvedovskyy",
        "altEn": "Eibsee — photography by Madlen Medvedovskyy",
        "order": 200,
        "side": "right"
      },
      {
        "src": "/images/projects/eibsee/eibsee-3.webp",
        "altDe": "Eibsee – Fotografie von Madlen Medvedovskyy",
        "altEn": "Eibsee — photography by Madlen Medvedovskyy",
        "order": 300,
        "side": "left"
      },
      {
        "src": "/images/projects/eibsee/eibsee-4.webp",
        "altDe": "Eibsee – Fotografie von Madlen Medvedovskyy",
        "altEn": "Eibsee — photography by Madlen Medvedovskyy",
        "order": 400,
        "side": "right"
      },
      {
        "src": "/images/projects/eibsee/eibsee-5.webp",
        "altDe": "Eibsee – Fotografie von Madlen Medvedovskyy",
        "altEn": "Eibsee — photography by Madlen Medvedovskyy",
        "order": 500,
        "side": "left"
      },
      {
        "src": "/images/projects/eibsee/eibsee-7.webp",
        "altDe": "Eibsee – Fotografie von Madlen Medvedovskyy",
        "altEn": "Eibsee — photography by Madlen Medvedovskyy",
        "order": 700,
        "side": "left"
      }
    ]
  },
  {
    "slug": "norway-sweden",
    "title": {
      "de": "Norwegen & Schweden",
      "en": "Norway & Sweden"
    },
    "category": "landscape",
    "cover": "/images/projects/norway-sweden/0-schweden-norwegen.webp",
    "description": {
      "de": "Landschaftsfotografie zwischen Ruhe, Weite und natürlichem Licht.",
      "en": "Landscape photography shaped by stillness, space and natural light."
    },
    "images": [
      {
        "src": "/images/projects/norway-sweden/1-schweden-norwegen.webp",
        "altDe": "Norwegen & Schweden – Fotografie von Madlen Medvedovskyy",
        "altEn": "Norway & Sweden — photography by Madlen Medvedovskyy",
        "order": 100,
        "side": "left"
      },
      {
        "src": "/images/projects/norway-sweden/2-schweden-norwegen.webp",
        "altDe": "Norwegen & Schweden – Fotografie von Madlen Medvedovskyy",
        "altEn": "Norway & Sweden — photography by Madlen Medvedovskyy",
        "order": 200,
        "side": "right"
      },
      {
        "src": "/images/projects/norway-sweden/3-schweden-norwegen.webp",
        "altDe": "Norwegen & Schweden – Fotografie von Madlen Medvedovskyy",
        "altEn": "Norway & Sweden — photography by Madlen Medvedovskyy",
        "order": 300,
        "side": "left"
      },
      {
        "src": "/images/projects/norway-sweden/4-schweden-norwegen.webp",
        "altDe": "Norwegen & Schweden – Fotografie von Madlen Medvedovskyy",
        "altEn": "Norway & Sweden — photography by Madlen Medvedovskyy",
        "order": 400,
        "side": "right"
      },
      {
        "src": "/images/projects/norway-sweden/5-schweden-norwegen.webp",
        "altDe": "Norwegen & Schweden – Fotografie von Madlen Medvedovskyy",
        "altEn": "Norway & Sweden — photography by Madlen Medvedovskyy",
        "order": 500,
        "side": "left"
      },
      {
        "src": "/images/projects/norway-sweden/6-schweden-norwegen.webp",
        "altDe": "Norwegen & Schweden – Fotografie von Madlen Medvedovskyy",
        "altEn": "Norway & Sweden — photography by Madlen Medvedovskyy",
        "order": 600,
        "side": "right"
      },
      {
        "src": "/images/projects/norway-sweden/7-schweden-norwegen.webp",
        "altDe": "Norwegen & Schweden – Fotografie von Madlen Medvedovskyy",
        "altEn": "Norway & Sweden — photography by Madlen Medvedovskyy",
        "order": 700,
        "side": "left"
      },
      {
        "src": "/images/projects/norway-sweden/8-schweden-norwegen.webp",
        "altDe": "Norwegen & Schweden – Fotografie von Madlen Medvedovskyy",
        "altEn": "Norway & Sweden — photography by Madlen Medvedovskyy",
        "order": 800,
        "side": "right"
      }
    ]
  }
];

export function getProjectBySlug(slug: string) {
  return projects.find((project) => project.slug === slug);
}

export function getAdjacentProjects(slug: string) {
  const index = projects.findIndex((project) => project.slug === slug);
  if (index < 0) return { previous: undefined, next: undefined };

  return {
    previous: projects[(index - 1 + projects.length) % projects.length],
    next: projects[(index + 1) % projects.length],
  };
}
