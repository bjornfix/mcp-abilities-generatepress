<?php
/** Contract for direction-aware native GeneratePress sidebars. */
declare( strict_types=1 );

$source = file_get_contents( dirname( __DIR__ ) . '/mcp-abilities-generatepress.php' );
$start = strpos( $source, 'function mcp_abilities_generatepress_mirror_sidebars_enabled(' );
$end = strpos( $source, 'function mcp_abilities_generatepress_page_meta_map(', $start ?: 0 );
if ( false !== $start ) { eval( substr( $source, $start, $end - $start ) ); }

function add_filter( $hook, $callback, $priority = 10, $accepted = 1 ): void { $GLOBALS['filters'][ $hook ] = $callback; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function is_admin(): bool { return $GLOBALS['admin'] ?? false; }
function is_rtl(): bool { return $GLOBALS['rtl'] ?? false; }
function mcp_abilities_generatepress_is_active(): bool { return $GLOBALS['active'] ?? true; }
function check_sidebar( bool $condition, string $message ): void { if ( ! $condition ) { throw new RuntimeException( $message ); } }

check_sidebar( isset( $GLOBALS['filters']['generate_sidebar_layout'], $GLOBALS['filters']['sidebars_widgets'] ), 'The native layout and widget Interfaces must both be registered.' );
$layout = $GLOBALS['filters']['generate_sidebar_layout'];
$widgets = $GLOBALS['filters']['sidebars_widgets'];
$stored = array( 'sidebar-1' => array( 'block-19' ), 'sidebar-2' => array( 'block-20' ), 'footer-1' => array( 'block-21' ), 'wp_inactive_widgets' => array( 'block-22' ) );
$GLOBALS['rtl'] = true;
check_sidebar( 'right-sidebar' === $layout( 'right-sidebar' ) && $stored === $widgets( $stored ), 'Sites must opt in before their native physical sidebar choices change.' );
$GLOBALS['options']['mcp_generatepress_mirror_rtl_sidebars'] = true;
foreach ( array( 'right-sidebar' => 'left-sidebar', 'left-sidebar' => 'right-sidebar', 'both-right' => 'both-left', 'both-left' => 'both-right', 'both-sidebars' => 'both-sidebars', 'no-sidebar' => 'no-sidebar', 'custom-layout' => 'custom-layout' ) as $before => $after ) {
	check_sidebar( $after === $layout( $before ), 'Native sidebar placement must mirror both directions: ' . $before );
}
$projected = $widgets( $stored );
check_sidebar( $projected['sidebar-2'] === $stored['sidebar-1'] && $projected['sidebar-1'] === $stored['sidebar-2'], 'The visible widgets must follow their sidebar, without copying saved widgets.' );
check_sidebar( $projected['footer-1'] === $stored['footer-1'] && $projected['wp_inactive_widgets'] === $stored['wp_inactive_widgets'], 'Unrelated widget areas must not change.' );
check_sidebar( $widgets( array( 'sidebar-1' => array( 'block-19' ) ) ) === array( 'sidebar-1' => array(), 'sidebar-2' => array( 'block-19' ) ), 'A one-sided sidebar must move without duplicating its widgets.' );
foreach ( array( 'rtl' => false, 'admin' => true, 'active' => false ) as $flag => $value ) {
	$GLOBALS[ $flag ] = $value;
	check_sidebar( 'right-sidebar' === $layout( 'right-sidebar' ) && $stored === $widgets( $stored ), 'Do not project outside opted-in RTL frontend requests: ' . $flag );
	$GLOBALS[ $flag ] = 'rtl' === $flag || 'active' === $flag;
}
echo "Native sidebar projection: RTL placement, widget identity, LTR/admin/theme isolation passed.\n";
