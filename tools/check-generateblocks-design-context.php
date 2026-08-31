<?php
/** Runtime contract for the GenerateBlocks design-context Adapter. */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

function add_filter( string $name, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	$GLOBALS['design_context_filters'][ $name ] = array( $callback, $priority, $accepted_args );
	return true;
}

function absint( $value ): int {
	return abs( (int) $value );
}

final class MCP_Abilities_GeneratePress_GenerateBlocks_Global_Styles {
	public static function get_all(): array {
		return $GLOBALS['native_global_styles'] ?? array();
	}
}

require_once dirname( __DIR__ ) . '/includes/class-generateblocks-design-context.php';

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

MCP_Abilities_GeneratePress_GenerateBlocks_Design_Context::register();
$assert( isset( $GLOBALS['design_context_filters']['mcp_block_editor_design_context'] ), 'The Adapter did not register the public design-context seam.' );
$assert( 2 === $GLOBALS['design_context_filters']['mcp_block_editor_design_context'][2], 'The Adapter did not request the complete design-context Interface.' );

$GLOBALS['native_global_styles'] = array(
	array(
		'id'     => 41,
		'status' => 'publish',
		'css'    => '.fixture-surface{background:#fff;border:1px solid #ddd}',
	),
	array(
		'id'     => 42,
		'status' => 'draft',
		'css'    => '.draft-surface{background:#111}',
	),
	array(
		'id'     => 43,
		'status' => 'publish',
		'css'    => '',
	),
);

$callback = $GLOBALS['design_context_filters']['mcp_block_editor_design_context'][0];
$context  = call_user_func(
	$callback,
	array(
		'stylesheets' => array(
			array(
				'source' => 'existing-provider',
				'css'    => '.existing{padding:1rem}',
			),
		),
	),
	array( 'content' => '<!-- fixture -->', 'blocks' => array() )
);

$assert( 2 === count( $context['stylesheets'] ), 'The Adapter did not preserve existing context and append one usable native stylesheet.' );
$assert( 'generateblocks-global-style-41' === $context['stylesheets'][1]['source'], 'The Adapter did not identify the native stylesheet source.' );
$assert( false === strpos( implode( "\n", array_column( $context['stylesheets'], 'css' ) ), 'draft-surface' ), 'The Adapter exposed draft CSS as rendered design context.' );

$GLOBALS['native_global_styles'][] = array(
	'id'     => 44,
	'status' => 'publish',
	'css'    => '.existing{padding:1rem}',
);
$deduplicated = call_user_func( $callback, $context, array() );
$assert( 2 === count( $deduplicated['stylesheets'] ), 'The Adapter duplicated CSS already supplied through the seam.' );

echo "GenerateBlocks design-context Adapter checks passed.\n";
