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

console.log("GeneratePress global Block Element contract passed.");
