<?php
/** Contract test for the current Global Styles compiler. */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ );

final class WP_Error {
	public function __construct( public string $code, public string $message ) {}

	public function get_error_message(): string {
		return $this->message;
	}
}

function is_wp_error( $value ): bool {
	return $value instanceof WP_Error;
}

function wp_strip_all_tags( string $value ): string {
	return strip_tags( $value );
}

require_once dirname( __DIR__ ) . '/includes/class-generateblocks-global-styles.php';

$method = new ReflectionMethod( MCP_Abilities_GeneratePress_GenerateBlocks_Global_Styles::class, 'compile_css' );
$styles = array(
	'display' => 'grid',
	'gridTemplateColumns' => 'minmax(0,1fr)',
	'&:is(:hover, :focus)' => array( 'backgroundColor' => 'var(--accent)' ),
	'@media (max-width:1024px)' => array( 'paddingTop' => '24px' ),
);
$actual = $method->invoke( null, '.example', $styles );
$expected = '.example{display:grid;grid-template-columns:minmax(0,1fr);}.example:is(:hover, :focus){background-color:var(--accent);}@media (max-width:1024px){.example{padding-top:24px;}}';

if ( $expected !== $actual ) {
	fwrite( STDERR, "Global Styles compiler output mismatch.\n" );
	exit( 1 );
}

$invalid = $method->invoke( null, '.example', array( 'color' => 'red;}.injected{display:block' ) );
if ( ! is_wp_error( $invalid ) ) {
	fwrite( STDERR, "Global Styles compiler accepted an injected value.\n" );
	exit( 1 );
}

echo "GenerateBlocks Global Styles compiler contract passed.\n";
