function splitSuffix(value) {
  const suffixIndex = value.search(/[?#]/);

  if (suffixIndex === -1) {
    return { pathname: value, suffix: "" };
  }

  return {
    pathname: value.slice(0, suffixIndex),
    suffix: value.slice(suffixIndex),
  };
}

function isExternalReference(value) {
  return /^(?:[a-z][a-z\d+.-]*:|\/\/|#|\?)/i.test(value);
}

function normalizeRoutePath(pathname) {
  const value = String(pathname || "/").trim();

  if (!value) {
    return "/";
  }

  return value.startsWith("/") ? value : `/${value}`;
}

export function normalizeBasePath(basePath = "/") {
  const value = String(basePath || "/").trim();
  const normalized = `/${value.replace(/^\/+|\/+$/g, "")}`;

  return normalized === "/" ? "/" : normalized;
}

export function stripBasePath(path, basePath = "/") {
  const value = String(path || "/").trim() || "/";

  if (isExternalReference(value)) {
    return value;
  }

  const { pathname, suffix } = splitSuffix(normalizeRoutePath(value));
  const base = normalizeBasePath(basePath);

  if (base === "/") {
    return `${pathname}${suffix}`;
  }

  if (pathname === base || pathname === `${base}/`) {
    return `/${suffix}`;
  }

  if (pathname.startsWith(`${base}/`)) {
    return `${pathname.slice(base.length)}${suffix}`;
  }

  return `${pathname}${suffix}`;
}

export function addBasePath(path, basePath = "/") {
  const value = String(path || "/").trim() || "/";

  if (isExternalReference(value)) {
    return value;
  }

  const { pathname, suffix } = splitSuffix(normalizeRoutePath(value));
  const base = normalizeBasePath(basePath);

  if (base === "/") {
    return `${pathname}${suffix}`;
  }

  if (pathname === base || pathname.startsWith(`${base}/`)) {
    return `${pathname}${suffix}`;
  }

  if (pathname === "/") {
    return `${base}/${suffix}`;
  }

  return `${base}${pathname}${suffix}`;
}

export function localizeRoutePath(path, language) {
  const value = String(path || "/").trim() || "/";

  if (isExternalReference(value)) {
    return value;
  }

  const { pathname, suffix } = splitSuffix(normalizeRoutePath(value));
  let localizedPath;

  if (language === "de") {
    localizedPath = pathname === "/de"
      ? "/"
      : pathname.replace(/^\/en(?=\/|$)/, "") || "/";
  } else if (pathname === "/") {
    localizedPath = "/en";
  } else {
    localizedPath = /^\/en(?:\/|$)/.test(pathname)
      ? pathname
      : `/en${pathname}`;
  }

  return `${localizedPath}${suffix}`;
}
