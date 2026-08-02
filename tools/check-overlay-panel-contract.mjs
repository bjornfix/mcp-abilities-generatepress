#!/usr/bin/env node

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const source = fs.readFileSync(path.join(root, 'mcp-abilities-generatepress.php'), 'utf8');

for (const ability of [
  'generatepress/list-overlay-panels',
  'generatepress/upsert-overlay-panel',
  'generatepress/attach-menu-item-mega-menu',
]) {
  assert.match(source, new RegExp(`['"]${ability.replace('/', '\\/')}['"]`), `${ability} must remain registered`);
}

assert.match(source, /post_type_exists\( 'gblocks_overlay' \)/, 'Overlay support must discover the installed native post type');
assert.match(source, /'_gb_overlay_type'\s*=>\s*\$type/, 'Overlay type must use native GenerateBlocks Pro metadata');
assert.match(source, /'_gb_mega_menu'/, 'Menu attachment must use the native GenerateBlocks Pro association');
assert.match(source, /function mcp_abilities_generatepress_upsert_overlay_panel[\s\S]*'unchanged'/, 'Overlay upsert must expose a write-free exact repeat');
assert.doesNotMatch(source, /Building a Simple Mega Menu|mega-menu\.css|wp_add_inline_style\([^)]*mega/i, 'The adapter must not recreate the retired CSS mega-menu recipe');

console.log('GenerateBlocks Pro Overlay Panel contract passed.');
