import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const repositoryRoot = resolve(dirname(fileURLToPath(import.meta.url)), "../..");
const workflow = readFileSync(
  resolve(repositoryRoot, ".github/workflows/madlen-production-publisher.yml"),
  "utf8",
);

const stepMarker = (name) => `      - name: ${name}`;
const stepPosition = (name) => {
  const position = workflow.indexOf(stepMarker(name));
  assert.notEqual(position, -1, `Workflow step is missing: ${name}`);

  return position;
};

assert.match(workflow, /MADLEN_PRODUCTION_WORKFLOW_ENABLED/);
assert.match(workflow, /cancel-in-progress: false/);
assert.match(workflow, /persist-credentials: false/);
assert.match(workflow, /MADLEN_EXPECTED_PUBLICATION_ID/);
assert.match(workflow, /MADLEN_EXPECTED_SEQUENCE/);
assert.match(workflow, /MADLEN_EXPECTED_SOURCE_REVISION/);
assert.match(workflow, /madlen:production:activate/);
assert.match(workflow, /madlen-production-incoming-v1/);
assert.match(workflow, /StrictHostKeyChecking=yes/);
assert.match(workflow, /\.part-\$GITHUB_RUN_ID-\$GITHUB_RUN_ATTEMPT/);
assert.equal(workflow.includes("artisan config:cache"), false);

const configureSsh = stepPosition("Configure strict SSH verification");
for (const prerequisite of [
  "Require the explicit production gate",
  "Install locked frontend dependencies",
  "Build and validate the DE and EN static release",
  "Create immutable static release archive",
  "Mark publication as uploading",
]) {
  assert.ok(stepPosition(prerequisite) < configureSsh, `${prerequisite} must run before SSH setup.`);
}

const inspectDestination = stepPosition("Inspect private incoming destination");
assert.ok(configureSsh < inspectDestination, "SSH setup must immediately precede the SSH/SFTP phase.");

const beforeSsh = workflow.slice(0, configureSsh);
assert.equal(beforeSsh.includes("NETCUP_PRODUCTION_SSH_PRIVATE_KEY"), false);
assert.equal(beforeSsh.includes("madlen-production-ssh"), false);

const cleanup = stepPosition("Remove private runner material");
assert.ok(cleanup > inspectDestination);
assert.match(workflow.slice(cleanup), /if: always\(\)/);
assert.match(workflow.slice(cleanup), /rm -rf "\$RUNNER_TEMP\/madlen-production-ssh"/);

assert.equal(workflow.match(/secrets\.NETCUP_PRODUCTION_SSH_PRIVATE_KEY/g)?.length, 1);
console.log("PASS: production SSH material is configured only after the verified external build and is always removed.");
