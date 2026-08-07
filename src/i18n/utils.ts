import { de } from "./de";
import { en } from "./en";

export const languages = {
  de,
  en,
};

export type Language = keyof typeof languages;

export function getLanguageFromUrl(url: URL): Language {
  const [, lang] = url.pathname.split("/");

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
  const cleanPath = path.startsWith("/") ? path : `/${path}`;

  if (lang === "de") {
    return cleanPath === "/de" ? "/" : cleanPath.replace(/^\/en/, "");
  }

  if (cleanPath === "/") {
    return "/en";
  }

  return cleanPath.startsWith("/en") ? cleanPath : `/en${cleanPath}`;
}