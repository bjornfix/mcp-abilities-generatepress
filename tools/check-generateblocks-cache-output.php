<?php
/** Exercise the cache warmer with file and native inline response fixtures. */
$source = file_get_contents( dirname( __DIR__ ) . '/mcp-abilities-generatepress.php' );
$start = strpos( $source, 'function mcp_abilities_generatepress_warm_generateblocks_css' );
$end = false === $start ? false : strpos( $source, "\n}\n", $start );
if ( false === $start || false === $end ) {
	throw new RuntimeException( 'Native cache warmer function could not be located.' );
}
eval( substr( $source, $start, $end + 3 - $start ) );
function get_option( $name, $default = null ) { return $default; }
function delete_option( $name ) {}
function add_option( ...$args ) {}
function wp_cache_delete( ...$args ) {}
function get_post( $id ) { return (object) array( 'ID' => $id, 'post_status' => 'publish' ); }
function get_permalink( $post ) { return 'https://example.com/' . $post->ID; }
function add_query_arg( $key, $value, $url ) { return $url; }
function is_wp_error( $response ) { return false; }
function wp_remote_get( ...$args ) { return $GLOBALS['fixture']; }
function wp_remote_retrieve_response_code( $response ) { return $response['status']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function mcp_abilities_generatepress_generateblocks_css_path( $id ) { return $GLOBALS['css_path']; }
$css_path = tempnam( sys_get_temp_dir(), 'gb-cache-test-' );
$missing_path = $css_path . '-absent';
$existing_path = $css_path;
$cases = array(
	array( 'file', 200, '', true, true ),
	array( 'inline', 200, '<style id="generateblocks-inline-css">.gb-text{color:red}</style>', false, true ),
	array( 'inline single quotes', 200, "<style media='all' id='generateblocks-inline-css'>.gb-text{color:red}</style>", false, true ),
	array( 'missing', 200, '', false, false ),
	array( 'empty', 200, '<style id="generateblocks-inline-css"> </style>', false, false ),
	array( 'comments only', 200, '<style id="generateblocks-inline-css">/* sourceURL */</style>', false, false ),
	array( 'other style', 200, '<style id="other">.gb-text{color:red}</style>', false, false ),
	array( 'HTTP error', 500, '<style id="generateblocks-inline-css">.gb-text{color:red}</style>', false, false ),
);
try {
	foreach ( $cases as $case ) {
		list( $name, $status, $body, $file, $expected ) = $case;
		$fixture = compact( 'status', 'body' );
		$css_path = $file ? $existing_path : $missing_path;
		$result = mcp_abilities_generatepress_warm_generateblocks_css( array( 123 ), 1 );
		if ( $expected !== in_array( 123, $result['warmed'], true ) || ( ! $expected && empty( $result['failed'][123] ) ) ) {
			throw new RuntimeException( 'Failed case: ' . $name );
		}
	}
} finally {
	unlink( $existing_path );
}
echo "GenerateBlocks cache output: 8 cases passed.\n";
