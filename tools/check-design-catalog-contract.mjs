#!/usr/bin/env node

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const root = dirname(fileURLToPath(import.meta.url));
const plugin = readFileSync(join(root, "..", "mcp-abilities-generatepress.php"), "utf8");
assert.match(plugin, /GeneratePress_Site_Library_Rest::get_instance\(\)->get_sites/, "the site catalog must use the native GeneratePress Site Library controller");
assert.match(plugin, /mcp_abilities_generatepress_catalog_text[\s\S]*page_builder[\s\S]*mcp_abilities_generatepress_catalog_text[\s\S]*category/, "native list-valued Site Library labels must be normalized without PHP array-to-string warnings");
assert.match(plugin, /GenerateBlocks_Libraries::get_instance\(\)->get_all\( false \)/, "the pattern catalog must use the same native library registry as the editor");
assert.match(plugin, /array_merge\( array\( '' \), array_keys\( \$categories \) \)/, "the full catalog must read uncategorized patterns and every native category");
assert.match(plugin, /empty\( \$library\['isEnabled'\] \)[\s\S]*empty\( \$library\['isLocal'\] \)/, "the full catalog must include native local custom collections even when they are not remote-enabled");
assert.match(plugin, /mcp_abilities_generatepress_get_full_pattern_catalog/, "the design catalog must call the full pattern catalog Adapter");
assert.match(plugin, /generatepress\/list-design-catalog/, "the full design catalog must be exposed as a public WordPress Ability");
assert.match(plugin, /no site or pattern is preselected/, "the catalog Ability must leave the contextual design choice to the agent");
assert.match(plugin, /include_pattern_markup[\s\S]*selected pattern/, "pattern markup must be opt-in after the agent has selected a pattern");
assert.match(plugin, /catalog_revision[\s\S]*hash\( 'sha256'/, "the catalog must expose a content-bound revision for stale selection rejection");
assert.match(plugin, /custom_pattern_sources[\s\S]*mcp_abilities_generatepress_generatecloud_status[\s\S]*'custom_pattern_sources'\s*=>\s*\$custom_pattern_sources/, "the catalog must expose safe native GenerateCloud availability without exposing its license key");
assert.match(plugin, /catalog_source[\s\S]*native-local-collection[\s\S]*custom_design_source/, "local custom collections must remain identifiable as selectable native design sources");
assert.match(plugin, /is_local[\s\S]*HTTP_HOST[\s\S]*Host/, "local custom collections must preserve the native same-host permission check through WP-CLI and HTTP transports");
assert.match(plugin, /'' !== \(string\) \$value \|\| 'host' === strtolower\( \(string\) \$key \)/, "the adapter must preserve an explicit empty Host header for the native CLI same-host check");
assert.match(plugin, /\$success = ! empty\( \$response\['success'\] \)[\s\S]*'items'\s*=>\s*\$success \? \$data : array\(\)/, "failed native pattern requests must not be exposed as selectable catalog items");

console.log("Full native design-catalog contract passed.");
