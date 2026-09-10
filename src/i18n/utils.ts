import { translations } from "../content/cms";
import {
  addBasePath,
  localizeRoutePath,
  normalizeBasePath,
  stripBasePath,
} from "./routing.js";

export const languages = {
  de: translations.de,
  en: translations.en,
};

export type Language = keyof typeof languages;

const configuredBasePath = import.meta.env.BASE_URL;

export function getBasePath(): string {
  return normalizeBasePath(configuredBasePath);
}

export function getSitePathname(url: URL): string {
  return stripBasePath(url.pathname, configuredBasePath);
}

export function withBasePath(path: string): string {
  return addBasePath(path, configuredBasePath);
}

export function getLanguageFromUrl(url: URL): Language {
  const [, lang] = getSitePathname(url).split("/");

  if (lang === "en") {
    return "en";
  }

  return "de";
}

export function useTranslations(lang: Language) {
  return languages[lang];
}

export function getAlternateLanguage(lang: Language): Language {
  return lang === "de" ? "en" : "de";
}

export function getLocalizedPath(path: string, lang: Language): string {
  const sitePath = stripBasePath(path, configuredBasePath);
  return addBasePath(localizeRoutePath(sitePath, lang), configuredBasePath);
}
