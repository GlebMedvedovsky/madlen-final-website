import fs from "node:fs";
import { projects as sourceProjects, type Project } from "../data/projects";
import { de as sourceDe } from "../i18n/de";
import { en as sourceEn } from "../i18n/en";
import baseline from "../../content/baseline.json";

export type { Project } from "../data/projects";

type Translation = typeof sourceDe | typeof sourceEn;
type ServiceContent = {
  key: string;
  order: number;
  titleDe: string;
  titleEn: string;
  descriptionDe: string;
  descriptionEn: string;
  icon?: string;
  highlighted?: boolean;
};

type ContentManifest = {
  schemaVersion: number;
  projects: Project[];
  translations: { de: Translation; en: Translation };
  services: ServiceContent[];
  legalPages?: LegalPageContent[];
  settings?: { mediaSlots?: Record<string, string> };
};

type LegalPageContent = {
  key: string;
  type: string;
  valueDe: string;
  valueEn: string;
};

function readRelease(): ContentManifest | undefined {
  const releasePath = process.env.MADLEN_CONTENT_RELEASE;
  if (!releasePath) return undefined;

  try {
    const candidate = JSON.parse(fs.readFileSync(releasePath, "utf8")) as ContentManifest;
    if (candidate.schemaVersion !== 1 || !Array.isArray(candidate.projects)) {
      throw new Error("unsupported content manifest");
    }
    return candidate;
  } catch (error) {
    throw new Error(`Could not read MADLEN_CONTENT_RELEASE at ${releasePath}: ${String(error)}`);
  }
}

const release = readRelease();

export const projects = release?.projects ?? sourceProjects;
export const translations = release?.translations ?? { de: sourceDe, en: sourceEn };
export const services = release?.services ?? (baseline.services as ServiceContent[]);
export const legalPages = release?.legalPages ?? (baseline.legalPages as LegalPageContent[]);
const defaultMediaSlots = {
  home_video: "/images/hero/hero-video.mp4",
  home_video_poster: "/images/hero/hero-video-poster.webp",
  memories_photo: "/images/hero/memories-photo.webp",
};
export const mediaSlots = { ...defaultMediaSlots, ...(release?.settings?.mediaSlots ?? {}) };

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

export function getServices(lang: "de" | "en") {
  return services
    .filter((service) => (lang === "de" ? service.titleDe : service.titleEn))
    .sort((a, b) => a.order - b.order)
    .map((service) => ({
      title: lang === "de" ? service.titleDe : service.titleEn,
      description: lang === "de" ? service.descriptionDe : service.descriptionEn,
      icon: service.icon ?? "",
      highlighted: Boolean(service.highlighted),
    }));
}

export function getLegalPage(key: string, lang: "de" | "en") {
  const page = legalPages.find((entry) => entry.key === key);
  return page ? (lang === "de" ? page.valueDe : page.valueEn) : "";
}
