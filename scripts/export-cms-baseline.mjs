import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { execFileSync } from "node:child_process";
import { projects } from "../src/data/projects.ts";
import { de } from "../src/i18n/de.ts";
import { en } from "../src/i18n/en.ts";

const repositoryRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const frontendCheckpoint = "f44a51b07cdd89b04702e19b6454a079105ed4ad";

function extractArray(source, marker) {
  const markerIndex = source.indexOf(marker);
  if (markerIndex < 0) throw new Error(`Marker not found: ${marker}`);
  const start = source.indexOf("[", markerIndex);
  let depth = 0;
  let quote = null;
  let escaped = false;

  for (let index = start; index < source.length; index += 1) {
    const character = source[index];
    if (quote) {
      if (escaped) escaped = false;
      else if (character === "\\") escaped = true;
      else if (character === quote) quote = null;
      continue;
    }
    if (["'", '"', "`"].includes(character)) {
      quote = character;
      continue;
    }
    if (character === "[") depth += 1;
    if (character === "]") depth -= 1;
    if (depth === 0) return source.slice(start, index + 1);
  }

  throw new Error(`Unclosed array after ${marker}`);
}

function germanServices() {
  const source = fs.readFileSync(path.join(repositoryRoot, "src/pages/leistungen.astro"), "utf8");
  const literal = extractArray(source, "const sourceServices =");
  return Function(`"use strict"; return (${literal});`)().map((service, index) => ({
    key: `service-${index + 1}`,
    order: index + 1,
    ...service,
  }));
}

function englishServices() {
  // Approved authored copy from frontend checkpoint f44a51b. Keep this explicit:
  // the current Astro page renders CMS data and therefore cannot be parsed as literals.
  return [
    {
      title: "Portrait Photography",
      description: "Natural and expressive portraits shaped by light, personality and atmosphere.",
    },
    {
      title: "Couples & Families",
      description: "Authentic photographs of shared moments without forced poses.",
    },
    {
      title: "Wedding Photography",
      description: "Emotional and timeless images of the moments that make your wedding unique.",
    },
    {
      title: "Events",
      description: "Professional photography for private events, cultural occasions and celebrations.",
    },
    {
      title: "Videography & Editing",
      description: "Cinematic wedding films, event videos and carefully edited visual stories.",
    },
    {
      title: "Editorial & Commercial",
      description: "Photography for magazines, brands, shops, campaigns and creative projects.",
    },
  ];
}

function visibleText(relativePath, checkpoint = false) {
  const source = checkpoint
    ? execFileSync("git", ["show", `${frontendCheckpoint}:${relativePath}`], {
        cwd: repositoryRoot,
        encoding: "utf8",
      })
    : fs.readFileSync(path.join(repositoryRoot, relativePath), "utf8");
  return source
    .replace(/^---[\s\S]*?---/, "")
    .replace(/<style>[\s\S]*?<\/style>/gi, "")
    .replace(/<!--([\s\S]*?)-->/g, "")
    .replace(/<script[\s\S]*?<\/script>/gi, "")
    .replace(/<[^>]+>/g, "\n")
    .replace(/&amp;/g, "&")
    .replace(/&nbsp;/g, " ")
    .replace(/&#39;/g, "'")
    .replace(/&quot;/g, '"')
    .split("\n")
    .map((line) => line.trim())
    .filter(Boolean)
    .join("\n");
}

const deServices = germanServices();
const enServices = englishServices();
// The existing DE page has separate videography/editing cards while EN combines them.
// Keep that authored structure rather than inventing a translation or dropping a card.
const servicePairs = [
  [0, 0, "portraits"],
  [1, 1, "couples-families"],
  [2, 2, "weddings"],
  [3, 3, "events"],
  [4, 4, "videography"],
  [5, null, "editing"],
  [6, 5, "editorial-commercial"],
];
const services = servicePairs.map(([deIndex, enIndex, key], index) => ({
  key,
  order: index + 1,
  titleDe: deServices[deIndex]?.title ?? "",
  titleEn: enIndex === null ? "" : (enServices[enIndex]?.title ?? ""),
  descriptionDe: deServices[deIndex]?.description ?? "",
  descriptionEn: enIndex === null ? "" : (enServices[enIndex]?.description ?? ""),
  icon: deServices[deIndex]?.icon ?? "",
  highlighted: Boolean(deServices[deIndex]?.highlighted),
}));

const legalPages = [
  { key: "legal-notice", de: "src/pages/impressum.astro", en: "src/pages/en/legal-notice.astro" },
  { key: "privacy", de: "src/pages/datenschutz.astro", en: "src/pages/en/privacy.astro" },
  { key: "terms", de: "src/pages/agb.astro", en: "src/pages/en/terms.astro" },
].map((entry) => ({
  key: entry.key,
  type: "legal_document",
  valueDe: visibleText(entry.de, true),
  valueEn: visibleText(entry.en, true),
}));

const manifest = {
  schemaVersion: 1,
  source: "tracked Astro content",
  projects,
  translations: { de, en },
  services,
  legalPages,
  settings: {
    primaryLocale: "de",
    contactEmail: "contact@madlenmedvedovskyy.de",
    siteUrl: "https://madebymadlen.de",
  },
};

const outputPath = path.join(repositoryRoot, "content/baseline.json");
fs.mkdirSync(path.dirname(outputPath), { recursive: true });
fs.writeFileSync(outputPath, `${JSON.stringify(manifest, null, 2)}\n`);
console.log(`Wrote ${outputPath}`);
console.log(`${projects.length} projects, ${projects.reduce((sum, project) => sum + project.images.length, 0)} gallery images, ${services.length} services`);
