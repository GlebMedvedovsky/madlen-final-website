import { createHash } from "node:crypto";
import { spawnSync } from "node:child_process";
import {
  cpSync,
  existsSync,
  lstatSync,
  readFileSync,
  readdirSync,
  realpathSync,
  statSync,
  writeFileSync,
} from "node:fs";
import { dirname, isAbsolute, join, relative, resolve, sep } from "node:path";
import { fileURLToPath } from "node:url";

const [packageArgument, outputArgument] = process.argv.slice(2);
if (!packageArgument || !outputArgument) {
  fail("Aufruf: npm run build:publication -- <entpacktes-paket> <ausgabeverzeichnis>");
}

const packageRoot = realpathSync(resolve(packageArgument));
const outputRoot = resolve(outputArgument);
const repositoryRoot = resolve(dirname(fileURLToPath(import.meta.url)), "../..");

if (existsSync(outputRoot)) fail("Das eindeutige Build-Ziel existiert bereits.");
if (outputRoot === repositoryRoot || !isAbsolute(outputRoot)) fail("Unsicheres Build-Ziel.");

assertSafeTree(packageRoot, ["publication.json", "content-manifest.json", "media"]);
const metadataPath = join(packageRoot, "publication.json");
const manifestPath = join(packageRoot, "content-manifest.json");
if (!existsSync(metadataPath) || !existsSync(manifestPath)) fail("Paket-Metadaten oder Inhaltsmanifest fehlen.");

const metadataBytes = readFileSync(metadataPath);
const manifestBytes = readFileSync(manifestPath);
const metadata = JSON.parse(metadataBytes.toString("utf8"));
const manifest = JSON.parse(manifestBytes.toString("utf8"));
if (metadata.schemaVersion !== 1 || manifest.schemaVersion !== 1) fail("Nicht unterstützte Paketversion.");
if (metadata.contentManifest !== "content-manifest.json") fail("Das Paket verweist auf ein unerwartetes Inhaltsmanifest.");
if (!/^[a-zA-Z0-9-]{8,64}$/.test(metadata.publicationId)) fail("Ungültige Veröffentlichungskennung.");
if (!Number.isInteger(metadata.sequence) || metadata.sequence < 1) fail("Ungültige Auftragsnummer.");
if (!/^[0-9a-f]{40}$/i.test(metadata.sourceRevision)) fail("Ungültiger fester Quellcode-Stand.");

const manifestChecksum = createHash("sha256").update(manifestBytes).digest("hex");
if (manifestChecksum !== metadata.contentChecksum) fail("Die Prüfsumme des Inhaltsmanifests stimmt nicht.");
if (process.env.MADLEN_EXPECTED_PUBLICATION_ID && process.env.MADLEN_EXPECTED_PUBLICATION_ID !== metadata.publicationId) {
  fail("Das Paket gehört zu einem anderen Veröffentlichungsauftrag.");
}
if (process.env.MADLEN_EXPECTED_SEQUENCE && Number(process.env.MADLEN_EXPECTED_SEQUENCE) !== metadata.sequence) {
  fail("Das Paket besitzt eine andere Auftragsnummer.");
}
if (process.env.MADLEN_EXPECTED_SOURCE_REVISION && process.env.MADLEN_EXPECTED_SOURCE_REVISION !== metadata.sourceRevision) {
  fail("Das Paket gehört zu einem anderen Quellcode-Stand.");
}

const npm = process.platform === "win32" ? "npm.cmd" : "npm";
const build = spawnSync(npm, ["run", "build"], {
  cwd: repositoryRoot,
  env: {
    ...process.env,
    ASTRO_TELEMETRY_DISABLED: "1",
    MADLEN_CONTENT_RELEASE: manifestPath,
    MADLEN_OUT_DIR: outputRoot,
    MADLEN_BASE_PATH: "/",
  },
  encoding: "utf8",
  stdio: "inherit",
});
if (build.error || build.status !== 0) fail(`Astro-Build fehlgeschlagen${build.error ? `: ${build.error.message}` : "."}`);

const mediaSource = join(packageRoot, "media");
if (existsSync(mediaSource)) {
  cpSync(mediaSource, join(outputRoot, "media"), { recursive: true, errorOnExist: true, force: false });
}

writeFileSync(
  join(outputRoot, ".madlen-release.json"),
  `${JSON.stringify({
    schemaVersion: 1,
    publicationId: metadata.publicationId,
    sequence: metadata.sequence,
    sourceRevision: metadata.sourceRevision,
    contentChecksum: metadata.contentChecksum,
    builtAt: new Date().toISOString(),
  }, null, 2)}\n`,
);

writeSitemap(outputRoot);
validateRelease(outputRoot, manifest);
console.log(`Produktiv-Kandidat ${metadata.sequence}-${metadata.publicationId} wurde lokal gebaut und vollständig geprüft.`);

function validateRelease(root, content) {
  for (const required of ["index.html", "en/index.html", "sitemap.xml"]) {
    assertFile(join(root, required), `Release-Prüfung fehlgeschlagen: ${required} fehlt.`);
  }
  if (!Array.isArray(content.projects)) fail("Das Inhaltsmanifest enthält keine Projektliste.");
  for (const project of content.projects) {
    if (!project?.slug || !/^[a-zA-Z0-9-]+$/.test(project.slug)) fail("Ungültiger Projekt-Slug im Inhaltsmanifest.");
    assertFile(join(root, "portfolio", project.slug, "index.html"), `Deutsche Projektseite ${project.slug} fehlt.`);
    assertFile(join(root, "en", "portfolio", project.slug, "index.html"), `Englische Projektseite ${project.slug} fehlt.`);
    for (const mediaPath of [project.cover, ...(project.images ?? []).map((image) => image.src)]) {
      if (typeof mediaPath === "string" && mediaPath.startsWith("/media/")) {
        assertFile(join(root, mediaPath.slice(1)), `Referenziertes Medium ${mediaPath} fehlt.`);
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
    if (item.isSymbolicLink()) fail("Der statische Release darf keine symbolischen Links enthalten.");
    if (!item.isFile()) return;
    const rel = relative(root, path).split(sep).join("/");
    if (/(^|\/)(\.env(?:\.|$)|backend|storage|vendor|node_modules|private)(\/|$)/i.test(rel)) {
      fail(`Nicht öffentlicher Inhalt im Release: ${rel}`);
    }
    if (!rel.endsWith(".html")) return;
    const html = readFileSync(path, "utf8");
    for (const match of html.matchAll(/\/media\/[a-zA-Z0-9._/-]+/g)) {
      assertFile(join(root, match[0].slice(1)), `HTML referenziert ein fehlendes Medium: ${match[0]}`);
    }
  });
}

function writeSitemap(root) {
  const origin = (process.env.MADLEN_PUBLIC_SITE_URL || "https://foto-video-madlen.de").replace(/\/$/, "");
  const urls = [];
  walk(root, (path) => {
    if (!lstatSync(path).isFile() || !path.endsWith(`${sep}index.html`)) return;
    const rel = relative(root, path).split(sep).join("/").replace(/index\.html$/, "");
    const route = `/${rel}`.replace(/\/+/g, "/");
    urls.push(`  <url><loc>${escapeXml(origin + route)}</loc></url>`);
  });
  urls.sort();
  writeFileSync(
    join(root, "sitemap.xml"),
    `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls.join("\n")}\n</urlset>\n`,
  );
}

function escapeXml(value) {
  return value.replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;").replaceAll('"', "&quot;");
}

function assertSafeTree(root, allowedTopLevel) {
  walk(root, (path) => {
    const item = lstatSync(path);
    if (item.isSymbolicLink()) fail("Das Veröffentlichungspaket darf keine symbolischen Links enthalten.");
    const rel = relative(root, path).split(sep).join("/");
    const top = rel.split("/")[0];
    if (rel && !allowedTopLevel.includes(top)) fail(`Unerwarteter Inhalt im Veröffentlichungspaket: ${rel}`);
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

function fail(message) {
  console.error(message);
  process.exit(1);
}
