import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const repositoryRoot = resolve(dirname(fileURLToPath(import.meta.url)), "../..");
const workflow = readFileSync(
  resolve(repositoryRoot, ".github/workflows/madlen-external-preview.yml"),
  "utf8",
);

const stepMarker = (name) => `      - name: ${name}`;
const stepPosition = (name) => {
  const position = workflow.indexOf(stepMarker(name));
  assert.notEqual(position, -1, `Workflow step is missing: ${name}`);

  return position;
};

const configureSsh = stepPosition("Configure strict SSH verification");
for (const prerequisite of [
  "Require the explicit preview gate",
  "Install locked frontend dependencies",
  "Build and validate DE and EN preview",
  "Create immutable result archive",
  "Check expiry immediately before transfer",
]) {
  assert.ok(stepPosition(prerequisite) < configureSsh, `${prerequisite} must run before SSH setup.`);
}

const inspectDestination = stepPosition("Inspect private incoming destination");
assert.ok(configureSsh < inspectDestination, "SSH setup must immediately precede the SSH/SFTP phase.");

const beforeSsh = workflow.slice(0, configureSsh);
assert.equal(beforeSsh.includes("NETCUP_PREVIEW_SSH_PRIVATE_KEY"), false);
assert.equal(beforeSsh.includes("$RUNNER_TEMP/madlen-ssh"), false);

const sshSetup = workflow.slice(configureSsh, inspectDestination);
assert.match(sshSetup, /NETCUP_PREVIEW_SSH_PRIVATE_KEY/);
assert.match(sshSetup, /printf '%s\\n' "\$SSH_PRIVATE_KEY" > "\$RUNNER_TEMP\/madlen-ssh\/id_ed25519"/);

const cleanup = stepPosition("Remove private runner material");
assert.ok(cleanup > inspectDestination);
assert.match(workflow.slice(cleanup), /if: always\(\)/);
assert.match(workflow.slice(cleanup), /rm -rf "\$RUNNER_TEMP\/madlen-ssh"/);

assert.equal(workflow.match(/secrets\.NETCUP_PREVIEW_SSH_PRIVATE_KEY/g)?.length, 1);
console.log("PASS: SSH material is created only after build/result checks and always cleaned after SSH use.");
