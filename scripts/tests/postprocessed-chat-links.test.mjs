import assert from "node:assert/strict";
import { readFileSync, readdirSync, statSync } from "node:fs";
import { dirname, isAbsolute, join, relative, resolve } from "node:path";
import vm from "node:vm";

const [buildArgument, baseArgument] = process.argv.slice(2);
if (!buildArgument || !baseArgument) {
  throw new Error("Usage: node scripts/tests/postprocessed-chat-links.test.mjs <preview-build-root> <preview-base-path>");
}

const buildRoot = resolve(buildArgument);
const basePath = baseArgument.replace(/\/+$/, "");
assert.match(basePath, /^\/admin\/preview\/[a-zA-Z0-9-]{8,64}$/);
assertAllClientRootUrlsAreScoped();

for (const scenario of [
  {
    page: "portfolio/renaissance/index.html",
    language: "de",
    contactQuery: "Kontakt",
    projectHref: `${basePath}/portfolio/renaissance`,
    contactHref: `${basePath}/kontakt`,
  },
  {
    page: "en/portfolio/renaissance/index.html",
    language: "en",
    contactQuery: "Contact",
    projectHref: `${basePath}/en/portfolio/renaissance`,
    contactHref: `${basePath}/en/contact`,
  },
]) {
  const pagePath = join(buildRoot, scenario.page);
  const html = readFileSync(pagePath, "utf8");
  const chatTag = html.match(/<div\b[^>]*\bdata-chatbot\b[^>]*>/i)?.[0];
  assert.ok(chatTag, `ChatBot root is missing from ${scenario.page}`);

  const language = readAttribute(chatTag, "data-language");
  const routes = readAttribute(chatTag, "data-routes");
  const projects = readAttribute(chatTag, "data-projects");
  assert.equal(language, scenario.language);
  for (const href of Object.values(JSON.parse(routes))) assertHasSinglePreviewBase(href);
  for (const project of JSON.parse(projects)) {
    assertHasSinglePreviewBase(project.href.de);
    assertHasSinglePreviewBase(project.href.en);
  }

  const scriptSources = [...html.matchAll(/<script\b[^>]*\bsrc="([^"]+\.js(?:[?#][^"]*)?)"[^>]*>/gi)]
    .map(match => decodeHtml(match[1]).replace(/[?#].*$/, ""));
  assert.ok(scriptSources.length > 0, `No client scripts found in ${scenario.page}`);

  const chatModule = findReachableChatModule(pagePath, scriptSources);
  assert.ok(chatModule, `The referenced ChatBot client module was not found for ${scenario.page}`);
  const source = readFileSync(chatModule, "utf8");
  assert.doesNotMatch(source, /\b(?:from\s*|import\s*(?:\(\s*)?)["']/,
    `ChatBot test module unexpectedly retains an import: ${relative(buildRoot, chatModule)}`);

  const harness = createChatHarness({ language, routes, projects });
  vm.runInNewContext(source, harness.context, {
    filename: relative(buildRoot, chatModule),
    timeout: 5_000,
  });

  const projectLinks = harness.submit("Renaissance");
  assert.deepEqual(projectLinks, [scenario.projectHref]);

  const contactLinks = harness.submit(scenario.contactQuery);
  assert.ok(contactLinks.includes(scenario.contactHref), `Missing ${scenario.contactHref}`);
  assert.ok(contactLinks.includes("mailto:contact@madlenmedvedovskyy.de"));

  for (const href of [...projectLinks, ...contactLinks]) {
    if (!href.startsWith("/")) continue;
    assert.ok(
      href === basePath || href.startsWith(`${basePath}/`),
      `ChatBot link leaves preview: ${href}`,
    );
    assertHasSinglePreviewBase(href);
  }
}

console.log("Postprocessed DE/EN ChatBot project and contact links stay inside the preview base path.");

function findReachableChatModule(pagePath, initialSources) {
  const queue = initialSources.map(source => resolveClientPath(pagePath, source));
  const visited = new Set();

  while (queue.length > 0) {
    const modulePath = queue.shift();
    if (visited.has(modulePath)) continue;
    visited.add(modulePath);
    const source = readFileSync(modulePath, "utf8");
    if (source.includes("[data-chatbot]")) return modulePath;

    for (const match of source.matchAll(/(?:\bfrom\s*|\bimport\s*(?:\(\s*)?)(["'])([^"']+\.js)\1/g)) {
      queue.push(resolveClientPath(modulePath, match[2]));
    }
  }

  return null;
}

function resolveClientPath(parentPath, source) {
  let candidate;
  if (source === basePath || source.startsWith(`${basePath}/`)) {
    candidate = join(buildRoot, source.slice(basePath.length).replace(/^\/+/, ""));
  } else if (source.startsWith("/")) {
    candidate = join(buildRoot, source.replace(/^\/+/, ""));
  } else {
    candidate = resolve(dirname(parentPath), source);
  }

  const relativePath = relative(buildRoot, candidate);
  assert.ok(relativePath && !relativePath.startsWith("..") && !isAbsolute(relativePath),
    `Client module escapes preview build root: ${source}`);
  return candidate;
}

function assertAllClientRootUrlsAreScoped() {
  const escapedBase = escapeRegExp(basePath.slice(1));
  const quotedRootUrl = new RegExp("([\"'`])/(?!/|" + escapedBase + "(?:/|[\"'`]))");

  walk(buildRoot, path => {
    if (!path.endsWith(".js")) return;
    const source = readFileSync(path, "utf8");
    assert.doesNotMatch(
      source,
      quotedRootUrl,
      `Final preview JavaScript retains an unscoped root URL: ${relative(buildRoot, path)}`,
    );
  });
}

function assertHasSinglePreviewBase(href) {
  assert.ok(href === basePath || href.startsWith(`${basePath}/`), `Link leaves preview: ${href}`);
  assert.equal(
    href.indexOf(basePath, basePath.length),
    -1,
    `Preview base path is duplicated in link: ${href}`,
  );
}

function walk(root, callback) {
  for (const name of readdirSync(root)) {
    const path = join(root, name);
    if (statSync(path).isDirectory()) walk(path, callback);
    else callback(path);
  }
}

function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

function readAttribute(tag, name) {
  const value = tag.match(new RegExp(`\\b${name}="([^"]*)"`, "i"))?.[1];
  assert.notEqual(value, undefined, `${name} is missing from ChatBot root`);
  return decodeHtml(value);
}

function decodeHtml(value) {
  return value
    .replace(/&#x([0-9a-f]+);/gi, (_, code) => String.fromCodePoint(Number.parseInt(code, 16)))
    .replace(/&#([0-9]+);/g, (_, code) => String.fromCodePoint(Number.parseInt(code, 10)))
    .replace(/&quot;/g, '"')
    .replace(/&apos;/g, "'")
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">")
    .replace(/&amp;/g, "&");
}

function createChatHarness({ language, routes, projects }) {
  class FakeClassList {
    add() {}
    remove() {}
    toggle() {}
  }

  class FakeElement {
    constructor(tagName = "div") {
      this.tagName = tagName.toUpperCase();
      this.children = [];
      this.listeners = new Map();
      this.attributes = new Map();
      this.classList = new FakeClassList();
      this.className = "";
      this.hidden = false;
      this.href = "";
      this.textContent = "";
      this.value = "";
      this.scrollHeight = 0;
      this.scrollTop = 0;
      this.tabIndex = 0;
    }

    addEventListener(type, listener) {
      const listeners = this.listeners.get(type) ?? [];
      listeners.push(listener);
      this.listeners.set(type, listeners);
    }

    appendChild(child) {
      this.children.push(child);
      this.scrollHeight = this.children.length;
      return child;
    }

    contains() {
      return false;
    }

    focus() {}

    getAttribute(name) {
      return this.attributes.get(name) ?? null;
    }

    setAttribute(name, value) {
      this.attributes.set(name, String(value));
    }
  }

  const toggle = new FakeElement("button");
  const close = new FakeElement("button");
  const windowElement = new FakeElement("section");
  const body = new FakeElement("div");
  const form = new FakeElement("form");
  const input = new FakeElement("input");
  const elements = new Map([
    ["[data-chat-toggle]", toggle],
    ["[data-chat-close]", close],
    ["[data-chat-window]", windowElement],
    ["[data-chat-body]", body],
    ["[data-chat-form]", form],
    ["[data-chat-input]", input],
  ]);
  const root = new FakeElement("div");
  root.dataset = { language, routes, projects };
  root.querySelector = selector => elements.get(selector) ?? null;
  root.querySelectorAll = () => [];

  const document = {
    activeElement: null,
    documentElement: { classList: new FakeClassList() },
    addEventListener() {},
    createElement: tagName => new FakeElement(tagName),
    getElementById: () => null,
    querySelector: selector => selector === "[data-chatbot]" ? root : null,
  };
  const browserWindow = {
    addEventListener() {},
    dispatchEvent() {},
    scrollTo() {},
    scrollY: 0,
  };
  const context = {
    CustomEvent: class CustomEvent {},
    console,
    document,
    window: browserWindow,
  };

  return {
    context,
    submit(query) {
      const submitListener = form.listeners.get("submit")?.[0];
      assert.ok(submitListener, "ChatBot submit listener was not registered");
      input.value = query;
      submitListener({ preventDefault() {} });
      const botMessage = body.children.at(-1);
      assert.ok(botMessage, `ChatBot produced no response for ${query}`);
      return collectLinks(botMessage);
    },
  };
}

function collectLinks(element) {
  const links = element.tagName === "A" ? [element.href] : [];
  for (const child of element.children) links.push(...collectLinks(child));
  return links;
}
