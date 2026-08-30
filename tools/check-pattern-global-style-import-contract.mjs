import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const plugin = fs.readFileSync(path.join(here, '..', 'mcp-abilities-generatepress.php'), 'utf8');

assert.match(plugin, /generateblocks\/import-pattern-global-styles/);
assert.match(plugin, /mcp_abilities_generatepress_import_pattern_global_styles/);
assert.match(plugin, /\/generateblocks-pro\/v1\/pattern-library\/get-global-style-data/);
assert.match(plugin, /\/generateblocks-pro\/v1\/pattern-library\/import-styles/);
assert.match(plugin, /globalStyleSelectors/);
assert.match(plugin, /missing_pattern_ids/);
assert.match(plugin, /missing_selectors/);
assert.match(plugin, /\.gbp-section__inner/);
assert.match(plugin, /current_user_can\( 'manage_options' \)/);
assert.match(plugin, /'idempotent'\s*=>\s*true/);
assert.doesNotMatch(
  plugin.match(/generateblocks\/import-pattern-global-styles[\s\S]*?GENERATEPRESS - Get Page Meta/)?.[0] ?? '',
  /'styles'\s*=>\s*array\(\s*'type'\s*=>\s*'array'/,
  'The public ability must resolve trusted library styles instead of accepting caller-supplied style payloads.',
);

console.log('GenerateBlocks Pattern Library Global Style import contract passed.');
