<?php
/** Run with: wp eval-file tools/check-generateblocks-card-wordpress-runtime.php */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$summary = array(
	'blockName'    => 'generateblocks/text',
	'attrs'        => array(
		'htmlAttributes' => array(
			'data-devenia-card-summary'     => 'explicit',
			'data-devenia-card-summary-max' => '120',
		),
	),
	'innerBlocks'  => array(),
	'innerHTML'    => '<p>{{post_excerpt}}</p>',
	'innerContent' => array( '<p>{{post_excerpt}}</p>' ),
);
$action = array(
	'blockName'    => 'generateblocks/text',
	'attrs'        => array(
		'htmlAttributes' => array(
			'href'                     => '{{post_permalink}}',
			'aria-label'               => 'View {{post_title}} details',
			'data-devenia-card-action' => 'details',
		),
	),
	'innerBlocks'  => array(),
	'innerHTML'    => '<a href="{{post_permalink}}">View details</a>',
	'innerContent' => array( '<a href="{{post_permalink}}">View details</a>' ),
);
$content = array(
	'blockName'    => 'generateblocks/element',
	'attrs'        => array( 'tagName' => 'div' ),
	'innerBlocks'  => array( $summary, $action ),
	'innerHTML'    => '<div></div>',
	'innerContent' => array( '<div>', null, null, '</div>' ),
);
$card = array(
	'blockName'    => 'generateblocks/loop-item',
	'attrs'        => array(),
	'innerBlocks'  => array( $content ),
	'innerHTML'    => '<div></div>',
	'innerContent' => array( '<div>', null, '</div>' ),
);

$query = array(
	'blockName'    => 'generateblocks/query',
	'attrs'        => array(),
	'innerBlocks'  => array(
		array(
			'blockName'    => 'generateblocks/looper',
			'attrs'        => array(),
			'innerBlocks'  => array( $card ),
			'innerHTML'    => '<div></div>',
			'innerContent' => array( '<div>', null, '</div>' ),
		),
	),
	'innerHTML'    => '<section></section>',
	'innerContent' => array( '<section>', null, '</section>' ),
);

$projected = apply_filters( 'render_block_data', $query, $query, null );
$media     = $projected['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0] ?? array();
if (
	! class_exists( 'MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection' )
	|| 'core/post-featured-image' !== (string) ( $media['blockName'] ?? '' )
	|| true !== (bool) ( $media['attrs']['isLink'] ?? false )
	|| 'devenia-query-card-featured-image' !== (string) ( $media['attrs']['className'] ?? '' )
	|| '160px' !== (string) ( $media['attrs']['width'] ?? '' )
	|| 'left' !== (string) ( $media['attrs']['align'] ?? '' )
	|| isset( $media['attrs']['aspectRatio'] )
	|| isset( $media['attrs']['scale'] )
) {
	fwrite( STDERR, "GenerateBlocks card runtime projection failed.\n" );
	exit( 1 );
}

echo "GenerateBlocks card WordPress runtime: OK\n";
