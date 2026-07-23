<?php
/** WordPress runtime for request-local GenerateBlocks content projection. */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$fail = static function ( string $message ): void {
	throw new RuntimeException( $message );
};
$assert = static function ( bool $condition, string $message ) use ( $fail ): void {
	if ( ! $condition ) {
		$fail( $message );
	}
};

$suffix = strtolower( wp_generate_password( 10, false, false ) );
$staged_id = 'request-content-' . $suffix;
$staged_content = '<!-- wp:generateblocks/container {"uniqueId":"' . $staged_id . '","isDynamic":true,"blockVersion":4,"sizing":{"maxWidth":"1140px"},"spacing":{"paddingLeft":"24px","paddingRight":"24px"}} -->'
	. '<!-- wp:paragraph --><p>Request-local full rebuild.</p><!-- /wp:paragraph -->'
	. '<!-- /wp:generateblocks/container -->';
$registry_before = get_option( 'generateblocks_dynamic_css_posts', null );
$time_before = get_option( 'generateblocks_dynamic_css_time', null );

$supply = static function ( $content ) use ( $staged_content ) {
	return null === $content ? $staged_content : $content;
};
add_filter( 'mcp_abilities_generatepress_generateblocks_request_content', $supply );

try {
	$assert(
		'inline' === MCP_Abilities_GeneratePress_GenerateBlocks_Grid_Projection::force_authoritative_request_content_inline_mode( 'file' ),
		'Authoritative request content did not select request-local inline CSS.'
	);
	$assert(
		$staged_content === MCP_Abilities_GeneratePress_GenerateBlocks_Grid_Projection::project_authoritative_request_content( '<!-- canonical plain content -->' ),
		'Canonical GenerateBlocks parser input was not replaced by request-local authority.'
	);

	$enqueue = class_exists( 'GenerateBlocks_Enqueue_CSS' ) ? GenerateBlocks_Enqueue_CSS::get_instance() : null;
	$assert( is_object( $enqueue ), 'The upstream GenerateBlocks CSS Adapter is unavailable.' );
	$enqueue->print_inline_css();
	$styles = wp_styles();
	$after = isset( $styles->registered['generateblocks']->extra['after'] ) ? (array) $styles->registered['generateblocks']->extra['after'] : array();
	$css = implode( "\n", $after );
	$assert( false !== strpos( $css, $staged_id ), 'A full rebuild from canonical plain content did not generate staged native CSS.' );
	$assert( $registry_before === get_option( 'generateblocks_dynamic_css_posts', null ), 'Request-local CSS mutated the GenerateBlocks post registry.' );
	$assert( $time_before === get_option( 'generateblocks_dynamic_css_time', null ), 'Request-local CSS mutated the GenerateBlocks generation clock.' );
} finally {
	remove_filter( 'mcp_abilities_generatepress_generateblocks_request_content', $supply );
}

echo "GenerateBlocks request-content projection runtime passed.\n";
