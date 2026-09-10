import assert from "node:assert/strict";
import { existsSync, mkdtempSync, mkdirSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { dirname, join } from "node:path";
import {
  nonPublicArtifactPaths,
  presentNonPublicArtifacts,
  removeNonPublicArtifacts,
} from "../lib/non-public-artifacts.mjs";

const root = mkdtempSync(join(tmpdir(), "madlen-build-filter-"));

try {
  for (const relativePath of nonPublicArtifactPaths) {
    const fixture = relativePath === "design-reference"
      ? join(root, relativePath, "layout.jpeg")
      : join(root, relativePath);
    mkdirSync(dirname(fixture), { recursive: true });
    writeFileSync(fixture, `private fixture: ${relativePath}\n`);
  }
  mkdirSync(join(root, "images"), { recursive: true });
  writeFileSync(join(root, "images", "keep-me.jpg"), "public fixture\n");

  assert.deepEqual(presentNonPublicArtifacts(root), nonPublicArtifactPaths);
  removeNonPublicArtifacts(root);
  assert.deepEqual(presentNonPublicArtifacts(root), []);
  assert.equal(existsSync(join(root, "images", "keep-me.jpg")), true);

  console.log("PASS: nested and legacy non-public artifacts are removed without touching public siblings.");
} finally {
  rmSync(root, { recursive: true, force: true });
}
