#!/usr/bin/env node

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const root = resolve(import.meta.dirname, '..');
const main = readFileSync(resolve(root, 'mcp-abilities-generatepress.php'), 'utf8');
const adapter = readFileSync(resolve(root, 'includes/class-generateblocks-global-styles.php'), 'utf8');

const getStart = main.indexOf("'generateblocks/get-global-styles'");
const updateStart = main.indexOf("'generateblocks/update-global-styles'");
const optionsStart = main.indexOf("'generateblocks/list-options'", updateStart);

assert.ok(getStart > 0 && updateStart > getStart && optionsStart > updateStart, 'Global Styles abilities must exist in order');

const abilities = main.slice(getStart, optionsStart);
assert.match(abilities, /MCP_Abilities_GeneratePress_GenerateBlocks_Global_Styles::get_all\(\)/, 'read ability must use the current Global Styles Adapter');
assert.match(abilities, /MCP_Abilities_GeneratePress_GenerateBlocks_Global_Styles::synchronize\(/, 'write ability must use the current Global Styles Adapter');
assert.doesNotMatch(abilities, /generateblocks_global_styles|generateblocks_global_style_attrs/, 'abilities must not use the legacy option-backed Global Styles model');
assert.doesNotMatch(abilities, /generateblocks_defaults|update_option\(\s*'generateblocks'/, 'Global Styles must not multiplex unrelated settings/options');

assert.match(adapter, /private const POST_TYPE = 'gblocks_styles'/, 'Adapter must own the current native Global Styles post type');
assert.match(adapter, /gb_style_selector/, 'Adapter must persist the native selector field');
assert.match(adapter, /gb_style_data/, 'Adapter must persist the native style-data field');
assert.match(adapter, /gb_style_css/, 'Adapter must persist the native generated-CSS field');
assert.match(adapter, /GenerateBlocks_Pro_Enqueue_Styles::get_instance\(\)->build_css\(\)/, 'Adapter must ask GenerateBlocks Pro to rebuild its external global stylesheet');
assert.doesNotMatch(adapter, /generateblocks_global_styles|gblocks_global_style['"]/, 'Adapter must not retain the deprecated Global Styles model');
assert.doesNotMatch(adapter, /meta_query/, 'Global Style selector lookup must not issue a slow metadata query');
assert.match(adapter, /self::matches\( \$existing, \$style \)/, 'exact Global Styles must perform no write');

console.log('GenerateBlocks current Global Styles contract passed.');
