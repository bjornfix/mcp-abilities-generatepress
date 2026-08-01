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

const clearStart = php.indexOf("'generateblocks/clear-cache'");
const clearEnd = php.indexOf("\n\t);\n}", clearStart);
const clearImplementation = php.slice(clearStart, clearEnd);
for (const invariant of [
  'mcp_abilities_generatepress_discover_generateblocks_post_ids()',
  "unset( $known_posts[ $post_id ], $known_posts[ (string) $post_id ] )",
  "update_option( 'generateblocks_dynamic_css_posts', $known_posts )",
  "null === $post_ids ? $global_ids : $post_ids",
]) {
  if (!clearImplementation.includes(invariant)) {
    throw new Error(`Missing scoped/global cache ownership invariant: ${invariant}`);
  }
}

const targetedScope = clearImplementation.indexOf('if ( null === $post_ids )');
const globalDiscovery = clearImplementation.indexOf('mcp_abilities_generatepress_discover_generateblocks_post_ids()', targetedScope);
const globalScopeEnd = clearImplementation.indexOf('\n\t\t\t\t}', globalDiscovery);
if (targetedScope < 0 || globalDiscovery < targetedScope || globalScopeEnd < globalDiscovery) {
  throw new Error('Authoritative full-content discovery is not scoped exclusively to global invalidation.');
}
const beforeGlobalScope = clearImplementation.slice(0, targetedScope);
if (beforeGlobalScope.includes('mcp_abilities_generatepress_discover_generateblocks_post_ids()')) {
  throw new Error('Targeted invalidation still performs an unbounded published-content scan.');
}

if (!php.includes("false !== strpos( (string) get_post_field( 'post_content', $post_id, 'raw' ), '<!-- wp:generateblocks/' )")) {
  throw new Error('Global cache warming does not recover its authoritative post set from published WordPress content.');
}

const snapshotRead = clearImplementation.indexOf('$prior_content = $filesystem->get_contents( $file )');
const unreadableStop = clearImplementation.indexOf('false === $prior_content');
const destructiveDelete = clearImplementation.indexOf('wp_delete_file( $file )');
if (snapshotRead < 0 || unreadableStop < snapshotRead || destructiveDelete < unreadableStop) {
  throw new Error('Destructive refresh can delete before every existing CSS file has a readable rollback snapshot.');
}
if (!clearImplementation.includes("$prior_content === $filesystem->get_contents( $restore_file )")) {
  throw new Error('Delete-failure rollback does not verify exact restored CSS bytes.');
}
if (!clearImplementation.includes('if ( $exact_restore )')) {
  throw new Error('Delete-failure rollback counts a file before exact restoration is verified.');
}
if (!clearImplementation.includes("$prior_content !== $filesystem->get_contents( $file )")) {
  throw new Error('Warm-failure rollback does not verify exact restored CSS bytes.');
}
if (!clearImplementation.includes("! file_exists( $file ) && $rollback_ok")) {
  throw new Error('Warm-failure rollback does not verify restoration of prior file absence.');
}
if (!clearImplementation.includes('Destructive CSS refresh requires explicit post_ids.')) {
  throw new Error('Unbounded destructive global refresh is not rejected before mutation.');
}

console.log('GenerateBlocks multi-post CSS warm contract passed.');
