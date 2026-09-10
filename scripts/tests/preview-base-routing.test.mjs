import assert from "node:assert/strict";
import test from "node:test";

import {
  addBasePath,
  localizeRoutePath,
  normalizeBasePath,
  stripBasePath,
} from "../../src/i18n/routing.js";

const previewBase = "/admin/preview/preview-token-7f31";

test("normalizes root and preview base paths", () => {
  assert.equal(normalizeBasePath("/"), "/");
  assert.equal(normalizeBasePath(`${previewBase}/`), previewBase);
});

test("strips only the configured preview base", () => {
  assert.equal(stripBasePath(`${previewBase}/`, previewBase), "/");
  assert.equal(stripBasePath(`${previewBase}/en/portfolio/renaissance`, previewBase), "/en/portfolio/renaissance");
  assert.equal(stripBasePath("/en/portfolio/renaissance", previewBase), "/en/portfolio/renaissance");
  assert.equal(stripBasePath("/admin/preview/another-token/en", previewBase), "/admin/preview/another-token/en");
});

test("adds the preview base exactly once and preserves suffixes", () => {
  const projectPath = `${previewBase}/en/portfolio/renaissance`;

  assert.equal(addBasePath("/", previewBase), `${previewBase}/`);
  assert.equal(addBasePath("/en/portfolio/renaissance", previewBase), projectPath);
  assert.equal(addBasePath(projectPath, previewBase), projectPath);
  assert.equal(addBasePath("/portfolio?category=editorial#work", previewBase), `${previewBase}/portfolio?category=editorial#work`);
  assert.equal(addBasePath("mailto:test@example.com", previewBase), "mailto:test@example.com");
  assert.equal(addBasePath("https://example.com/path", previewBase), "https://example.com/path");
});

test("keeps Renaissance while switching DE to EN and back", () => {
  const germanRoute = "/portfolio/renaissance";
  const englishRoute = localizeRoutePath(germanRoute, "en");
  const germanAgain = localizeRoutePath(englishRoute, "de");

  assert.equal(englishRoute, "/en/portfolio/renaissance");
  assert.equal(germanAgain, germanRoute);
  assert.equal(addBasePath(englishRoute, previewBase), `${previewBase}/en/portfolio/renaissance`);
  assert.equal(addBasePath(germanAgain, previewBase), `${previewBase}/portfolio/renaissance`);
});

test("normal site routes remain unchanged", () => {
  assert.equal(stripBasePath("/en/about", "/"), "/en/about");
  assert.equal(addBasePath("/leistungen", "/"), "/leistungen");
  assert.equal(localizeRoutePath("/", "en"), "/en");
  assert.equal(localizeRoutePath("/en", "de"), "/");
});
