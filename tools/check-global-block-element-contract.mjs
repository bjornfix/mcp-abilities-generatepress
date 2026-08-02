#!/usr/bin/env node

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const root = dirname(dirname(fileURLToPath(import.meta.url)));
const plugin = readFileSync(join(root, "mcp-abilities-generatepress.php"), "utf8");

assert.match(
	plugin,
	/function mcp_abilities_generatepress_upsert_block_element\( array \$input \): array[\s\S]*block_types[\s\S]*content-template[\s\S]*_generate_element_type'[\s\S]*'block'[\s\S]*_generate_block_type'[\s\S]*\$block_type/,
	"one reusable GeneratePress Block Element Module must own idempotent native Element persistence",
);
assert.match(
	plugin,
	/'generatepress\/upsert-block-element'[\s\S]*'block_type'[\s\S]*'display_conditions'[\s\S]*mcp_abilities_generatepress_upsert_block_element/,
	"the public Interface must accept native Block Element subtype and display conditions",
);
assert.doesNotMatch(
	plugin,
	/upsert-archive-hook-element|archive_element_guidance|context_display_condition/,
	"archive-specific Element ownership must not survive the clean cut",
);
assert.match(
	plugin,
	/function mcp_abilities_generatepress_invalidate_dynamic_css_cache\(\): void[\s\S]*delete_option\( 'generate_dynamic_css_output' \)[\s\S]*update_option\( 'generateblocks_dynamic_css_time', 0, false \)/,
	"the Adapter must expose invalidation without synchronous global regeneration",
);
const upsertStart = plugin.indexOf("function mcp_abilities_generatepress_upsert_block_element");
const upsertEnd = plugin.indexOf("\nfunction mcp_abilities_generatepress_current_display_rules", upsertStart);
const upsert = plugin.slice(upsertStart, upsertEnd);
assert.match(upsert, /'action'\s*=>\s*'unchanged'/, "an exact Element contract must return without rewriting it");
assert.match(upsert, /mcp_abilities_generatepress_invalidate_dynamic_css_cache\(\)/, "a changed Element must invalidate CSS asynchronously");
assert.doesNotMatch(upsert, /mcp_abilities_generatepress_clear_dynamic_css_cache\(\)/, "Element upsert must not synchronously regenerate global CSS");

console.log("GeneratePress global Block Element contract passed.");
