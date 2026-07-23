#!/usr/bin/env node
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

const root = resolve(import.meta.dirname, "..");
const workspace = resolve(root, "..");
const moduleSource = readFileSync(resolve(root, "includes/class-generateblocks-grid-projection.php"), "utf8");
const mainSource = readFileSync(resolve(root, "mcp-abilities-generatepress.php"), "utf8");
const presentationSource = readFileSync(resolve(workspace, "devenia-site-presentation/devenia-site-presentation.php"), "utf8");
const workflowAdapter = readFileSync(resolve(workspace, "devenia-workflow/addons/generateblocks.php"), "utf8");
const workflowMain = readFileSync(resolve(workspace, "devenia-workflow/devenia-workflow.php"), "utf8");
const workflowPreview = readFileSync(resolve(workspace, "devenia-workflow/includes/trait-staged-preview-capability.php"), "utf8");

assert.match(moduleSource, /public static function project\(/, "GP-MCP must own the reusable projection Interface");
assert.match(moduleSource, /generateblocks_do_content/, "GP-MCP must own the GenerateBlocks CSS-input Adapter");
assert.match(moduleSource, /generateblocks_css_print_method/, "GP-MCP must own request-local GenerateBlocks CSS output mode");
assert.match(moduleSource, /mcp_abilities_generatepress_generateblocks_request_content/, "GP-MCP must expose one generic request-content Interface");
assert.doesNotMatch(moduleSource, /generateblocks_get_dynamic_css|generateblocks_get_parsed_content/, "GP-MCP must let the one upstream GenerateBlocks implementation parse and generate request-local CSS");
assert.match(moduleSource, /render_block_data/, "GP-MCP must own the frontend render Adapter");
assert.match(moduleSource, /devenia_workflow_project_block_layout/, "Workflow must consume the same projection Interface");
assert.match(moduleSource, /horizontalGapTablet[\s\S]*horizontalGapMobile/, "the Module must cover every responsive gap breakpoint");
assert.match(moduleSource, /marginLeft[\s\S]*marginRight/, "gutter direction must derive from document direction");
assert.doesNotMatch(moduleSource, /page_id|locale|translated text|className|customCss|additionalCss/i, "projection must not infer policy from page, language, text, or CSS");
assert.match(mainSource, /generateblocks_dynamic_css_posts'[\s\S]*array\(\)/, "GP-MCP cache clear must own GenerateBlocks regeneration metadata");
assert.match(presentationSource, /mcp_abilities_generatepress_enable_grid_layout_projection'[\s\S]*__return_true/, "Site Presentation must explicitly activate the Devenia-wide policy");
assert.doesNotMatch(workflowAdapter, /generateblocks_do_content|render_block_data|generateblocks_dynamic_css_posts|_generateblocks_dynamic_css_version|normalize_grid_gap|project_frontend_grid/, "Workflow must not own canonical frontend or GenerateBlocks cache behavior");
assert.match(workflowMain, /mcp_abilities_generatepress_generateblocks_request_content[\s\S]*filter_staged_preview_generateblocks_request_content/, "Workflow must register staged-preview authority at the reusable GP-MCP request-content seam");
assert.match(workflowPreview, /filter_staged_preview_generateblocks_request_content[\s\S]*source_rewrite_preview_authority[\s\S]*translation_job_preview_authority/, "Workflow must adapt both authorized preview callers without owning CSS generation");

console.log("GenerateBlocks grid ownership: OK");
