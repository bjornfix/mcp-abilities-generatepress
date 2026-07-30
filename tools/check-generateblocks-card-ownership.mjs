import { readFileSync } from "node:fs";
import { resolve } from "node:path";

const root = resolve(import.meta.dirname, "..");
const workspace = resolve(root, "..");
const moduleSource = readFileSync(resolve(root, "includes/class-generateblocks-card-projection.php"), "utf8");
const pluginSource = readFileSync(resolve(root, "mcp-abilities-generatepress.php"), "utf8");
const workflowProjection = readFileSync(resolve(workspace, "devenia-workflow/includes/trait-source-design-inheritance.php"), "utf8");
const workflowJob = readFileSync(resolve(workspace, "devenia-workflow/includes/trait-translation-job.php"), "utf8");
const workflowSourceRewrite = readFileSync(resolve(workspace, "devenia-workflow/includes/trait-source-rewrite-quality-authority.php"), "utf8");
const sitePresentation = readFileSync(resolve(workspace, "devenia-site-presentation/devenia-site-presentation.php"), "utf8");

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

assert.match = (value, pattern, message) => assert(pattern.test(value), message);
assert.notMatch = (value, pattern, message) => assert(!pattern.test(value), message);

assert.match(pluginSource, /class-generateblocks-card-projection\.php/, "GP-MCP must load the card projection Module");
assert.match(moduleSource, /generateblocks_dynamic_tag_replacement/, "GP-MCP must own the explicit-summary runtime Adapter");
assert.match(moduleSource, /devenia_workflow_translatable_block_html_fragments/, "GP-MCP must expose token-safe static Query copy to Workflow");
assert.match(moduleSource, /devenia_workflow_structured_text_attr_fragments/, "GP-MCP must expose accessible Query copy to Workflow");
assert.match(workflowProjection, /devenia_workflow_project_translatable_block_html_fragment/, "Workflow must consume the generic Adapter projection Interface");
assert.match(workflowJob, /translation_job_dynamic_inventory_policy/, "Workflow must enforce declared dynamic inventory before staging");
assert.match(workflowJob, /devenia_workflow_validate_dynamic_inventory/, "Workflow must call the owning Adapter's inventory validator");
assert.match(
  moduleSource,
  /add_filter\(\s*'devenia_workflow_source_rewrite_artifact_policy',\s*array\(\s*__CLASS__,\s*'validate_source_rewrite_artifact'\s*\),\s*10,\s*4\s*\)/,
  "GP-MCP must register the exact Source Rewrite artifact policy contract",
);
assert.match(
  workflowSourceRewrite,
  /apply_filters\(\s*'devenia_workflow_source_rewrite_artifact_policy',\s*array\(\s*'success'\s*=>\s*true\s*\),\s*\$source,\s*\$proposed,\s*\$job\s*\)/,
  "Workflow must call the Source Rewrite artifact policy with result, source, proposed artifact, and job",
);
assert.notMatch(workflowProjection, /data-devenia-card-(?:summary|action)/, "Workflow must not own GenerateBlocks card-role implementation");
assert.notMatch(sitePresentation, /data-devenia-card-(?:summary|action)|generateblocks_dynamic_tag_replacement/, "Site Presentation must not own Query card data projection");
assert.notMatch(moduleSource, /page_id|post_parent\s*===|locale|customCss|additionalCss/i, "Card projection must not infer policy from page IDs, locale, or CSS");

console.log("GenerateBlocks card ownership: OK");
