import { existsSync, rmSync } from "node:fs";
import { join } from "node:path";

export const nonPublicArtifactPaths = Object.freeze([
  "design-reference",
  "images/start_seite.jpeg",
  "start_seite.jpeg",
  "images/Kukes1.jpg",
  "images/Grafik Elemente/Blaues_Element_Wolke.png",
  "images/Grafik Elemente/Linie_Blau_Klein.png",
  "images/Grafik Elemente/Linine_Blau_Gross.png",
  "images/Grafik Elemente/Rosa_Blau_Linie.png",
]);

export function removeNonPublicArtifacts(root) {
  for (const relativePath of nonPublicArtifactPaths) {
    rmSync(join(root, relativePath), { recursive: true, force: true });
  }
}

export function presentNonPublicArtifacts(root) {
  return nonPublicArtifactPaths.filter((relativePath) => existsSync(join(root, relativePath)));
}
