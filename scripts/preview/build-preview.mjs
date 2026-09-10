import { createHash } from "node:crypto";
import { spawnSync } from "node:child_process";
import {
  cpSync,
  existsSync,
  lstatSync,
  readFileSync,
  readdirSync,
  realpathSync,
  rmSync,
  statSync,
  writeFileSync,
} from "node:fs";
import { dirname, isAbsolute, join, relative, resolve, sep } from "node:path";
import { fileURLToPath } from "node:url";

const [packageArgument, outputArgument] = process.argv.slice(2);
if (!packageArgument || !outputArgument) {
  fail("Aufruf: npm run build:preview -- <entpacktes-vorschau-paket> <ausgabeverzeichnis>");
}

const packageRoot = realpathSync(resolve(packageArgument));
const outputRoot = resolve(outputArgument);
const repositoryRoot = resolve(dirname(fileURLToPath(import.meta.url)), "../..");
if (existsSync(outputRoot)) fail("Das eindeutige Vorschau-Ziel existiert bereits.");
if (outputRoot === repositoryRoot || !isAbsolute(outputRoot)) fail("Unsicheres Vorschau-Ziel.");

assertSafeTree(packageRoot, ["preview.json", "content-manifest.json", "media"]);
const metadataPath = join(packageRoot, "preview.json");
const manifestPath = join(packageRoot, "content-manifest.json");
if (!existsSync(metadataPath) || !existsSync(manifestPath)) fail("Vorschau-Metadaten oder Inhaltsmanifest fehlen.");

const metadata = JSON.parse(readFileSync(metadataPath, "utf8"));
const manifestBytes = readFileSync(manifestPath);
const manifest = JSON.parse(manifestBytes.toString("utf8"));
if (metadata.schemaVersion !== 1 || manifest.schemaVersion !== 1) fail("Nicht unterstützte Vorschau-Paketversion.");
if (!/^[a-zA-Z0-9-]{8,64}$/.test(metadata.previewId)) fail("Ungültige Vorschau-Kennung.");
if (!/^[a-zA-Z0-9]{48}$/.test(metadata.previewToken)) fail("Ungültiges Vorschau-Token.");
if (!/^[0-9a-f]{40}$/i.test(metadata.sourceRevision)) fail("Ungültiger fester Quellcode-Stand.");
if (metadata.contentManifest !== "content-manifest.json") fail("Unerwartetes Inhaltsmanifest.");
if (metadata.basePath !== `/admin/preview/${metadata.previewToken}`) fail("Ungültiger geschützter Vorschau-Basispfad.");
if (!Array.isArray(metadata.locales) || metadata.locales.join(",") !== "de,en") fail("Die DE/EN-Sprachen fehlen im Vorschau-Paket.");
const expiresAt = Date.parse(metadata.expiresAt);
if (!Number.isFinite(expiresAt) || expiresAt <= Date.now()) fail("Der Vorschau-Auftrag ist abgelaufen.");

const contentChecksum = createHash("sha256").update(manifestBytes).digest("hex");
if (contentChecksum !== metadata.contentChecksum) fail("Die Prüfsumme des Vorschau-Inhalts stimmt nicht.");
for (const [environmentName, actual, label] of [
  ["MADLEN_EXPECTED_PREVIEW_ID", metadata.previewId, "Vorschau-Kennung"],
  ["MADLEN_EXPECTED_SOURCE_REVISION", metadata.sourceRevision, "Quellcode-Stand"],
  ["MADLEN_EXPECTED_EXPIRES_AT", metadata.expiresAt, "Ablaufzeit"],
]) {
  if (process.env[environmentName] && process.env[environmentName] !== actual) {
    fail(`Falsche ${label} im Vorschau-Paket.`);
  }
}

const npm = process.platform === "win32" ? "npm.cmd" : "npm";
const build = spawnSync(npm, ["run", "build"], {
  cwd: repositoryRoot,
  env: {
    ...process.env,
    ASTRO_TELEMETRY_DISABLED: "1",
    MADLEN_CONTENT_RELEASE: manifestPath,
    MADLEN_OUT_DIR: outputRoot,
    MADLEN_BASE_PATH: metadata.basePath,
  },
  encoding: "utf8",
  stdio: "inherit",
});
if (build.error || build.status !== 0) fail(`Astro-Vorschau-Build fehlgeschlagen${build.error ? `: ${build.error.message}` : "."}`);

removeNonPublicArtifacts(outputRoot);
const mediaSource = join(packageRoot, "media");
if (existsSync(mediaSource)) {
  cpSync(mediaSource, join(outputRoot, "media"), { recursive: true, errorOnExist: true, force: false });
}
scopeUrls(outputRoot, metadata.basePath);

writeFileSync(
  join(outputRoot, ".madlen-preview.json"),
  `${JSON.stringify({
    schemaVersion: 1,
    previewId: metadata.previewId,
    previewToken: metadata.previewToken,
    sourceRevision: metadata.sourceRevision,
    contentChecksum,
    expiresAt: metadata.expiresAt,
    builtAt: new Date().toISOString(),
  }, null, 2)}\n`,
);

validatePreview(outputRoot, manifest, metadata);
console.log(`Geschützte DE/EN-Vorschau ${metadata.previewId} wurde extern gebaut und geprüft.`);

function scopeUrls(root, base) {
  const escapedBase = escapeRegExp(base.slice(1));
  const quotedRootUrl = new RegExp("([\"'`])/(?!/|" + escapedBase + "(?:/|[\"'`]))", "g");
  const cssRootUrl = new RegExp("url\\(/(?!/|" + escapedBase + "(?:/|\\)))", "g");
  walk(root, (path) => {
    if (!lstatSync(path).isFile() || !/\.(html|css|js|json|xml)$/i.test(path)) return;
    const content = readFileSync(path, "utf8");
    const scoped = content.replace(quotedRootUrl, `$1${base}/`).replace(cssRootUrl, `url(${base}/`);
    writeFileSync(path, scoped);
  });
}

function removeNonPublicArtifacts(root) {
  for (const path of [
    "design-reference",
    "start_seite.jpeg",
    "images/Kukes1.jpg",
    "images/Grafik Elemente/Blaues_Element_Wolke.png",
    "images/Grafik Elemente/Linie_Blau_Klein.png",
    "images/Grafik Elemente/Linine_Blau_Gross.png",
    "images/Grafik Elemente/Rosa_Blau_Linie.png",
  ]) {
    rmSync(join(root, path), { recursive: true, force: true });
  }
}

function validatePreview(root, content, preview) {
  for (const required of ["index.html", "en/index.html", ".madlen-preview.json"]) {
    assertFile(join(root, required), `Vorschau-Prüfung fehlgeschlagen: ${required} fehlt.`);
  }
  for (const excluded of [
    "design-reference",
    "start_seite.jpeg",
    "images/Kukes1.jpg",
    "images/Grafik Elemente/Blaues_Element_Wolke.png",
    "images/Grafik Elemente/Linie_Blau_Klein.png",
    "images/Grafik Elemente/Linine_Blau_Gross.png",
    "images/Grafik Elemente/Rosa_Blau_Linie.png",
  ]) {
    if (existsSync(join(root, excluded))) fail(`Lokale Referenz wurde in die Vorschau kopiert: ${excluded}`);
  }
  if (!Array.isArray(content.projects)) fail("Das Vorschau-Manifest enthält keine Projektliste.");
  for (const project of content.projects) {
    if (!project?.slug || !/^[a-zA-Z0-9-]+$/.test(project.slug)) fail("Ungültiger Projekt-Slug im Vorschau-Manifest.");
    assertFile(join(root, "portfolio", project.slug, "index.html"), `Deutsche Vorschauseite ${project.slug} fehlt.`);
    assertFile(join(root, "en", "portfolio", project.slug, "index.html"), `Englische Vorschauseite ${project.slug} fehlt.`);
    for (const mediaPath of [project.cover, ...(project.images ?? []).map((image) => image.src)]) {
      if (typeof mediaPath === "string" && mediaPath.startsWith("/media/")) {
        assertFile(join(root, mediaPath.slice(1)), `Referenziertes Vorschau-Medium ${mediaPath} fehlt.`);
      }
    }
  }
  for (const mediaPath of Object.values(content.settings?.mediaSlots ?? {})) {
    if (typeof mediaPath === "string" && mediaPath.startsWith("/media/")) {
      assertFile(join(root, mediaPath.slice(1)), `Referenziertes Startseiten-Medium ${mediaPath} fehlt.`);
    }
  }

  walk(root, (path) => {
    const item = lstatSync(path);
    if (item.isSymbolicLink()) fail("Das Vorschau-Ergebnis darf keine symbolischen Links enthalten.");
    if (!item.isFile()) return;
    const rel = relative(root, path).split(sep).join("/");
    if (/(^|\/)(\.env(?:\.|$)|backend|storage|vendor|node_modules|private)(\/|$)/i.test(rel)) {
      fail(`Nicht öffentlicher Inhalt im Vorschau-Ergebnis: ${rel}`);
    }
    if (!rel.endsWith(".html")) return;
    const html = readFileSync(path, "utf8");
    if (/(^|[\"'=(])\/media\//m.test(html)) fail(`Ungeschützter Medienpfad im Vorschau-HTML: ${rel}`);
    for (const match of html.matchAll(new RegExp(`${escapeRegExp(preview.basePath)}/media/([a-zA-Z0-9._/-]+)`, "g"))) {
      assertFile(join(root, "media", match[1]), `HTML referenziert ein fehlendes Vorschau-Medium: ${match[1]}`);
    }
  });
}

function assertSafeTree(root, allowedTopLevel) {
  walk(root, (path) => {
    const item = lstatSync(path);
    if (item.isSymbolicLink()) fail("Das Vorschau-Paket darf keine symbolischen Links enthalten.");
    const rel = relative(root, path).split(sep).join("/");
    const top = rel.split("/")[0];
    if (rel && !allowedTopLevel.includes(top)) fail(`Unerwarteter Inhalt im Vorschau-Paket: ${rel}`);
  });
}

function walk(root, callback) {
  for (const name of readdirSync(root)) {
    const path = join(root, name);
    callback(path);
    if (statSync(path).isDirectory()) walk(path, callback);
  }
}

function assertFile(path, message) {
  if (!existsSync(path) || !statSync(path).isFile()) fail(message);
}

function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

function fail(message) {
  console.error(message);
  process.exit(1);
}
