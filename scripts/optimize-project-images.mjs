import fs from "node:fs/promises";
import path from "node:path";
import sharp from "sharp";

const projectsDirectory = path.resolve("public/images/projects");
const dataFile = path.resolve("src/data/projects.ts");
const supported = new Set([".jpg", ".jpeg", ".png", ".tif", ".tiff"]);

async function walk(directory) {
  const entries = await fs.readdir(directory, { withFileTypes: true });
  const files = [];

  for (const entry of entries) {
    const fullPath = path.join(directory, entry.name);
    if (entry.isDirectory()) files.push(...(await walk(fullPath)));
    else files.push(fullPath);
  }

  return files;
}

const files = await walk(projectsDirectory);
let converted = 0;

for (const sourcePath of files) {
  const extension = path.extname(sourcePath).toLowerCase();
  if (!supported.has(extension)) continue;

  const outputPath = sourcePath.slice(0, -extension.length) + ".webp";
  await sharp(sourcePath)
    .rotate()
    .resize({
      width: 2400,
      height: 2400,
      fit: "inside",
      withoutEnlargement: true,
    })
    .webp({ quality: 84, effort: 5 })
    .toFile(outputPath);

  await fs.unlink(sourcePath);
  converted += 1;
}

let projectData = await fs.readFile(dataFile, "utf8");
projectData = projectData.replace(
  /(\/images\/projects\/[^"]+?)\.(?:jpe?g|png|tiff?)(?=")/gi,
  "$1.webp",
);
await fs.writeFile(dataFile, projectData, "utf8");

console.log(`Optimized ${converted} portfolio images.`);
