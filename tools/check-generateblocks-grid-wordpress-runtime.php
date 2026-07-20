<?php
/** Run with: wp eval-file tools/check-generateblocks-grid-wordpress-runtime.php */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$failures = array();
$assert   = static function ( bool $condition, string $message ) use ( &$failures ): void {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$assert( class_exists( 'MCP_Abilities_GeneratePress_GenerateBlocks_Grid_Projection' ), 'The owning Grid Projection Module is not active.' );
$assert( (bool) apply_filters( 'mcp_abilities_generatepress_enable_grid_layout_projection', false ), 'Site Presentation did not activate the global grid policy.' );
$assert( function_exists( 'generateblocks_get_parsed_content' ), 'GenerateBlocks parsed-content Interface is unavailable.' );
$assert( function_exists( 'generateblocks_get_dynamic_css' ), 'GenerateBlocks dynamic-CSS Interface is unavailable.' );

$source = '<!-- wp:generateblocks/grid {"uniqueId":"mcp-grid-runtime","blockVersion":3,"horizontalGap":54} -->'
	. '<!-- wp:generateblocks/container {"uniqueId":"mcp-grid-runtime-a","isGrid":true,"gridId":"mcp-grid-runtime","isDynamic":true,"blockVersion":4,"sizing":{"width":"66%","widthMobile":"100%"}} --><p>One</p><!-- /wp:generateblocks/container -->'
	. '<!-- wp:generateblocks/container {"uniqueId":"mcp-grid-runtime-b","isGrid":true,"gridId":"mcp-grid-runtime","isDynamic":true,"blockVersion":4,"sizing":{"width":"34%","widthMobile":"100%"}} --><p>Two</p><!-- /wp:generateblocks/container -->'
	. '<!-- /wp:generateblocks/grid -->';

$parsed = generateblocks_get_parsed_content( $source );
$grid   = is_array( $parsed ) ? ( $parsed[0] ?? array() ) : array();
$items  = $grid['innerBlocks'] ?? array();
$assert( 0 === (int) ( $grid['attrs']['horizontalGap'] ?? -1 ), 'GenerateBlocks parsed the unprojected wrapper gap.' );
$assert( '54px' === (string) ( $items[0]['attrs']['spacing']['marginRight'] ?? '' ), 'Projected LTR native gutter is missing.' );
$assert( '0px' === (string) ( $items[0]['attrs']['spacing']['marginRightMobile'] ?? '' ), 'Projected stacked-mobile gutter is not zero.' );
$assert( '66%' === (string) ( $items[0]['attrs']['sizing']['width'] ?? '' ), 'Projection changed the first native width.' );
$assert( '34%' === (string) ( $items[1]['attrs']['sizing']['width'] ?? '' ), 'Projection changed the second native width.' );

if ( is_array( $parsed ) ) {
	generateblocks_get_dynamic_css( $parsed );
}
$css = function_exists( 'generateblocks_get_frontend_block_css' ) ? (string) generateblocks_get_frontend_block_css() : '';
$assert( '' !== $css, 'GenerateBlocks emitted no CSS for the projected fixture.' );
$assert( false === strpos( $css, 'margin-left:-54px' ) && false === strpos( $css, 'margin-right:-54px' ), 'Generated CSS retained a negative wrapper margin.' );
$assert( false === strpos( $css, 'padding-left:54px' ) && false === strpos( $css, 'padding-right:54px' ), 'Generated CSS retained wrapper-compensating column padding.' );
$assert( false !== strpos( $css, '.gb-container-mcp-grid-runtime-a' ) && false !== strpos( $css, 'margin-right:54px' ), 'Generated CSS omitted the first item native gutter.' );
$assert( false !== strpos( $css, '.gb-grid-wrapper > .gb-grid-column-mcp-grid-runtime-a' ) && false !== strpos( $css, 'width:66%' ), 'Generated CSS omitted the first native column width.' );

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "GenerateBlocks Grid WordPress runtime: OK\n";
