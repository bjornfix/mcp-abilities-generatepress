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
$assert( 'calc(66% + 18.36px)' === $ltr_items[0]['attrs']['sizing']['width'], 'First desktop wrapper did not combine its proportional visible share with the gutter it owns.' );
$assert( 'calc(34% - 18.36px)' === $ltr_items[1]['attrs']['sizing']['width'], 'Second desktop width did not absorb its proportional share of the row gutter.' );
$assert( 'calc(50% + 15px)' === $ltr_items[0]['attrs']['sizing']['widthTablet'], 'First tablet wrapper did not combine its equal visible share with the gutter it owns.' );
$assert( 'calc(50% - 15px)' === $ltr_items[1]['attrs']['sizing']['widthTablet'], 'Row-ending tablet peer did not retain the matching visible width.' );
$assert( '100%' === $ltr_items[0]['attrs']['sizing']['widthMobile'], 'A one-item mobile row must retain its full native width.' );
$assert( '54px' === $ltr_items[0]['attrs']['spacing']['marginRight'], 'LTR desktop gutter is not on the first item end side.' );
$assert( '30px' === $ltr_items[0]['attrs']['spacing']['marginRightTablet'], 'LTR tablet gutter did not preserve the responsive source gap.' );
$assert( '0px' === $ltr_items[0]['attrs']['spacing']['marginRightMobile'], 'Stacked mobile items must not retain a horizontal gutter.' );
$assert( '0px' === $ltr_items[1]['attrs']['spacing']['marginRight'], 'LTR row-ending item unexpectedly owns a gutter.' );

$thirds = array(
	array(
		'blockName' => 'generateblocks/grid',
		'attrs' => array( 'blockVersion' => 3, 'horizontalGap' => 24 ),
		'innerBlocks' => array(
			$container( '33.33%', '50%', '100%' ),
			$container( '33.33%', '50%', '100%' ),
			$container( '33.33%', '50%', '100%' ),
		),
	),
);
$third_items = MCP_Abilities_GeneratePress_GenerateBlocks_Grid_Projection::project( $thirds, $thirds, false )[0]['innerBlocks'];
$assert( 'calc(33.33% + 8px)' === $third_items[0]['attrs']['sizing']['width'], 'First equal peer wrapper did not include its owned gutter.' );
$assert( 'calc(33.33% + 8px)' === $third_items[1]['attrs']['sizing']['width'], 'Middle equal peer wrapper did not include its owned gutter.' );
$assert( 'calc(33.33% - 16px)' === $third_items[2]['attrs']['sizing']['width'], 'The row-ending peer remained wider than the gutter-owning peers.' );
$assert( 'calc(50% + 12px)' === $third_items[0]['attrs']['sizing']['widthTablet'], 'First tablet peer did not retain an equal visible width around the wrapped-row gutter.' );
$assert( 'calc(50% - 12px)' === $third_items[1]['attrs']['sizing']['widthTablet'], 'Second tablet peer did not retain an equal visible width around the wrapped-row gutter.' );
$assert( '50%' === $third_items[2]['attrs']['sizing']['widthTablet'], 'A lone final tablet peer must retain its authored half-row width.' );

$resolve_width = static function ( string $value, float $container ): float {
	if ( preg_match( '/^([0-9.]+)%$/', $value, $match ) ) {
		return $container * (float) $match[1] / 100;
	}
	if ( preg_match( '/^calc\(([0-9.]+)% ([+-]) ([0-9.]+)px\)$/', $value, $match ) ) {
		$base = $container * (float) $match[1] / 100;
		return '+' === $match[2] ? $base + (float) $match[3] : $base - (float) $match[3];
	}
	return NAN;
};
$desktop_container = 1092.0;
$visible_thirds = array_map(
	static function ( array $item, int $index ) use ( $resolve_width, $desktop_container ): float {
		$wrapper = $resolve_width( (string) $item['attrs']['sizing']['width'], $desktop_container );
		return $wrapper - ( 2 > $index ? 24.0 : 0.0 );
	},
	$third_items,
	array_keys( $third_items )
);
$assert( max( $visible_thirds ) - min( $visible_thirds ) < 0.01, 'Equal source peers did not produce equal visible desktop widths after their owned margins.' );
$assert( abs( array_sum( $visible_thirds ) + 48.0 - $desktop_container ) < 0.2, 'Visible peer widths and two gutters did not fill the desktop row.' );

$rtl       = MCP_Abilities_GeneratePress_GenerateBlocks_Grid_Projection::project( $source, $source, true );
$rtl_items = $rtl[0]['innerBlocks'];
$assert( '54px' === $rtl_items[0]['attrs']['spacing']['marginLeft'], 'RTL desktop gutter is not on the logical end side.' );
$assert( '30px' === $rtl_items[0]['attrs']['spacing']['marginLeftTablet'], 'RTL tablet gutter is not on the logical end side.' );
$assert( '0px' === $rtl_items[0]['attrs']['spacing']['marginLeftMobile'], 'RTL stacked mobile item unexpectedly owns a gutter.' );

$collision                         = $source;
$collision[0]['innerBlocks'][0]    = $container( '66%', '50%', '100%', array( 'marginRight' => '12px' ) );
$collision_result                  = MCP_Abilities_GeneratePress_GenerateBlocks_Grid_Projection::project( $collision, $collision, false );
$assert( 54 === $collision_result[0]['attrs']['horizontalGap'], 'Projection did not fail closed on intentional native spacing.' );

$opposite_collision                      = $source;
$opposite_collision[0]['innerBlocks'][0] = $container( '66%', '50%', '100%', array( 'marginLeft' => '12px' ) );
$opposite_result                         = MCP_Abilities_GeneratePress_GenerateBlocks_Grid_Projection::project( $opposite_collision, $opposite_collision, false );
$assert( 54 === $opposite_result[0]['attrs']['horizontalGap'], 'LTR projection did not fail closed on an intentional opposite-side margin.' );

$responsive_collision                      = $source;
$responsive_collision[0]['innerBlocks'][0] = $container( '66%', '50%', '100%', array( 'marginRightTablet' => '12px' ) );
$responsive_result                         = MCP_Abilities_GeneratePress_GenerateBlocks_Grid_Projection::project( $responsive_collision, $responsive_collision, false );
$assert( 54 === $responsive_result[0]['attrs']['horizontalGap'], 'Projection did not fail closed on an intentional responsive horizontal margin.' );

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "GenerateBlocks Grid Projection: OK\n";
