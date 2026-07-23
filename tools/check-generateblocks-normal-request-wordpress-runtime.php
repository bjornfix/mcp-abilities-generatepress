<?php
/** WordPress runtime proving normal requests retain upstream file mode. */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$mode = MCP_Abilities_GeneratePress_GenerateBlocks_Grid_Projection::force_authoritative_request_content_inline_mode( 'file' );
if ( 'file' !== $mode ) {
	throw new RuntimeException( 'A normal request without authoritative request-local content did not retain GenerateBlocks file mode.' );
}

echo "GenerateBlocks normal-request mode runtime passed.\n";
