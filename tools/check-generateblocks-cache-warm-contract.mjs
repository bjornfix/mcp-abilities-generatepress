#!/usr/bin/env node
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = dirname(dirname(fileURLToPath(import.meta.url)));
const php = readFileSync(join(root, 'mcp-abilities-generatepress.php'), 'utf8');
const start = php.indexOf('function mcp_abilities_generatepress_warm_generateblocks_css');
const end = php.indexOf('\nfunction mcp_abilities_generatepress_page_meta_map', start);

if (start < 0 || end < 0) {
  throw new Error('GenerateBlocks cache warmer implementation was not found.');
}

const implementation = php.slice(start, end);
const remoteRequest = implementation.indexOf('$response = wp_remote_get');
const requiredBeforeRequest = [
  "delete_option( 'generateblocks_dynamic_css_time' )",
  "add_option( 'generateblocks_dynamic_css_time', 0, '', false )",
  "wp_cache_delete( 'generateblocks_dynamic_css_time', 'options' )",
];

for (const invariant of requiredBeforeRequest) {
  const position = implementation.indexOf(invariant);
  if (position < 0 || position > remoteRequest) {
    throw new Error(`Missing ordered multi-post cache reset invariant: ${invariant}`);
  }
}

if (implementation.includes("update_option( 'generateblocks_dynamic_css_time'")) {
  throw new Error('The cache warmer still uses the request-stale update_option reset.');
}

console.log('GenerateBlocks multi-post CSS warm contract passed.');
