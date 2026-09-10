import { existsSync, readFileSync } from "node:fs";
import { resolve } from "node:path";

const packageRoot = resolve(process.argv[2] || ".");
const baseline = JSON.parse(readFileSync(resolve(packageRoot, "content/baseline.json"), "utf8"));
const publicReferences = new Set();

function collect(value) {
  if (typeof value === "string" && value.startsWith("/images/")) {
    publicReferences.add(value);
    return;
  }
  if (Array.isArray(value)) {
    value.forEach(collect);
    return;
  }
  if (value && typeof value === "object") {
    Object.values(value).forEach(collect);
  }
}

collect(baseline);
const missing = [...publicReferences].filter((path) => !existsSync(resolve(packageRoot, "public", path.slice(1))));
if (missing.length) {
  console.error(`Missing ${missing.length} baseline public media file(s).`);
  process.exit(1);
}

console.log(`BASELINE_MEDIA_REFERENCES_OK=${publicReferences.size}`);
