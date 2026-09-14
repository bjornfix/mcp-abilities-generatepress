<?php
/** Contract for the native Polylang page-layout copy filter. */
declare( strict_types=1 );

$source = file_get_contents( dirname( __DIR__ ) . '/mcp-abilities-generatepress.php' );
$start = strpos( $source, 'function mcp_abilities_generatepress_page_meta_map()' );
$end = strpos( $source, 'function mcp_abilities_generatepress_expected_page_meta_value(', $start );
eval( substr( $source, $start, $end - $start ) );

function add_filter( $hook, $callback, $priority = 10, $accepted = 1 ): void {
	$GLOBALS['copy_filter'] = compact( 'hook', 'callback', 'priority', 'accepted' );
}
function mcp_abilities_generatepress_is_active(): bool { return $GLOBALS['theme_active'] ?? true; }
function check_copy( bool $condition, string $message ): void {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}

$filter = $GLOBALS['copy_filter'];
check_copy( 'pll_copy_post_metas' === $filter['hook'] && 2 === $filter['accepted'], 'The native Polylang filter must be registered.' );
$keys = $filter['callback']( array( '_wp_page_template', 'other_provider' ), false );
foreach ( mcp_abilities_generatepress_page_meta_map() as $key ) {
	check_copy( in_array( $key, $keys, true ), 'A native GeneratePress page-layout key was omitted: ' . $key );
}
check_copy( in_array( 'other_provider', $keys, true ), 'Other providers must retain ownership of their metadata.' );
check_copy( $keys === $filter['callback']( $keys, false ), 'Repeated filtering must not duplicate keys.' );
check_copy( array( 'other_provider' ) === $filter['callback']( array( 'other_provider' ), true ), 'Do not force two-way layout sync from an empty target.' );
$GLOBALS['theme_active'] = false;
check_copy( array( 'other_provider' ) === $filter['callback']( array( 'other_provider' ), false ), 'Do not apply GeneratePress layout policy to another theme.' );
echo "Polylang page layout copy: native keys, ownership and copy-only contract passed.\n";
