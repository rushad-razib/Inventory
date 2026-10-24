import { execSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const spec = path.resolve(root, "../../openapi/openapi.v1.yaml");
const out = path.resolve(root, "src/lib/api-types.ts");

execSync(`npx openapi-typescript "${spec}" -o "${out}"`, {
  stdio: "inherit",
  cwd: root,
  shell: true,
});

console.log(`Generated ${out}`);
