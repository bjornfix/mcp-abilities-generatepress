<?php
/** Pure runtime contract for the GenerateBlocks Grid Projection Module. */

define( 'ABSPATH', __DIR__ );
require_once dirname( __DIR__ ) . '/includes/class-generateblocks-grid-projection.php';

$container = static function ( string $width, string $tablet = '', string $mobile = '', array $spacing = array() ): array {
	return array(
		'blockName' => 'generateblocks/container',
		'attrs'     => array(
			'blockVersion' => 4,
			'sizing'       => array_filter(
				array( 'width' => $width, 'widthTablet' => $tablet, 'widthMobile' => $mobile ),
				static fn( string $value ): bool => '' !== $value
			),
			'spacing'      => $spacing,
		),
		'innerBlocks' => array(),
	);
};

$source = array(
	array(
		'blockName'   => 'generateblocks/grid',
		'attrs'       => array(
			'blockVersion'        => 3,
			'horizontalGap'       => 54,
			'horizontalGapTablet' => 30,
		),
		'innerBlocks' => array(
			$container( '66%', '50%', '100%' ),
			$container( '34%', '50%', '100%' ),
		),
	),
);

$failures = array();
$assert   = static function ( bool $condition, string $message ) use ( &$failures ): void {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$ltr       = MCP_Abilities_GeneratePress_GenerateBlocks_Grid_Projection::project( $source, $source, false );
$ltr_grid  = $ltr[0];
$ltr_items = $ltr_grid['innerBlocks'];
$assert( 0 === $ltr_grid['attrs']['horizontalGap'], 'Desktop negative-wrapper gap was not removed.' );
$assert( 0 === $ltr_grid['attrs']['horizontalGapTablet'], 'Tablet negative-wrapper gap was not removed.' );
$assert( 0 === $ltr_grid['attrs']['horizontalGapMobile'], 'Mobile negative-wrapper gap was not removed.' );
$assert( '66%' === $ltr_items[0]['attrs']['sizing']['width'] && '34%' === $ltr_items[1]['attrs']['sizing']['width'], 'Native column widths changed.' );
$assert( '54px' === $ltr_items[0]['attrs']['spacing']['marginRight'], 'LTR desktop gutter is not on the first item end side.' );
$assert( '30px' === $ltr_items[0]['attrs']['spacing']['marginRightTablet'], 'LTR tablet gutter did not preserve the responsive source gap.' );
$assert( '0px' === $ltr_items[0]['attrs']['spacing']['marginRightMobile'], 'Stacked mobile items must not retain a horizontal gutter.' );
$assert( '0px' === $ltr_items[1]['attrs']['spacing']['marginRight'], 'LTR row-ending item unexpectedly owns a gutter.' );

$rtl       = MCP_Abilities_GeneratePress_GenerateBlocks_Grid_Projection::project( $source, $source, true );
$rtl_items = $rtl[0]['innerBlocks'];
$assert( '54px' === $rtl_items[0]['attrs']['spacing']['marginLeft'], 'RTL desktop gutter is not on the logical end side.' );
$assert( '30px' === $rtl_items[0]['attrs']['spacing']['marginLeftTablet'], 'RTL tablet gutter is not on the logical end side.' );
$assert( '0px' === $rtl_items[0]['attrs']['spacing']['marginLeftMobile'], 'RTL stacked mobile item unexpectedly owns a gutter.' );

$collision                         = $source;
$collision[0]['innerBlocks'][0]    = $container( '66%', '50%', '100%', array( 'marginRight' => '12px' ) );
$collision_result                  = MCP_Abilities_GeneratePress_GenerateBlocks_Grid_Projection::project( $collision, $collision, false );
$assert( 54 === $collision_result[0]['attrs']['horizontalGap'], 'Projection did not fail closed on intentional native spacing.' );

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "GenerateBlocks Grid Projection: OK\n";

