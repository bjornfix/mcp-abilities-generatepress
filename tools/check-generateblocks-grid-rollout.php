<?php
/** Pure contract for versioned GenerateBlocks Grid Projection rollout. */

define( 'ABSPATH', __DIR__ );

$test_options = array();
$test_enabled = true;
$test_failed_write = '';

function apply_filters( string $name, $value ) {
	global $test_enabled;
	return 'mcp_abilities_generatepress_enable_grid_layout_projection' === $name ? $test_enabled : $value;
}

function get_option( string $name, $default = false ) {
	global $test_options;
	return $test_options[ $name ] ?? $default;
}

function update_option( string $name, $value ): bool {
	global $test_failed_write, $test_options;
	if ( $name === $test_failed_write ) {
		return false;
	}
	$test_options[ $name ] = $value;
	return true;
}

require_once dirname( __DIR__ ) . '/includes/class-generateblocks-grid-projection.php';

$failures = array();
$assert   = static function ( bool $condition, string $message ) use ( &$failures ): void {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$test_options = array(
	'generateblocks_dynamic_css_posts'                 => array( 5 => true, 7 => true ),
	'mcp_generatepress_grid_projection_contract_version' => 'old-contract',
);
MCP_Abilities_GeneratePress_GenerateBlocks_Grid_Projection::rollout_generated_css_contract();
$assert( array() === $test_options['generateblocks_dynamic_css_posts'], 'A changed projection contract did not invalidate the regenerable CSS registry.' );
$assert( 0 === $test_options['generateblocks_dynamic_css_time'], 'A changed projection contract did not open the upstream regeneration window.' );
$assert(
	MCP_Abilities_GeneratePress_GenerateBlocks_Grid_Projection::CONTRACT_VERSION === $test_options['mcp_generatepress_grid_projection_contract_version'],
	'The installed projection contract version was not recorded.'
);

$test_options['generateblocks_dynamic_css_posts'] = array( 11 => true );
MCP_Abilities_GeneratePress_GenerateBlocks_Grid_Projection::rollout_generated_css_contract();
$assert( array( 11 => true ) === $test_options['generateblocks_dynamic_css_posts'], 'An unchanged projection contract invalidated CSS again.' );

$test_enabled = false;
$test_options = array(
	'generateblocks_dynamic_css_posts'                 => array( 13 => true ),
	'mcp_generatepress_grid_projection_contract_version' => 'disabled-old-contract',
);
MCP_Abilities_GeneratePress_GenerateBlocks_Grid_Projection::rollout_generated_css_contract();
$assert( array( 13 => true ) === $test_options['generateblocks_dynamic_css_posts'], 'A disabled projection policy mutated the CSS registry.' );
$assert( 'disabled-old-contract' === $test_options['mcp_generatepress_grid_projection_contract_version'], 'A disabled projection policy advanced its contract version.' );

$test_enabled      = true;
$test_failed_write = 'generateblocks_dynamic_css_time';
$test_options      = array(
	'generateblocks_dynamic_css_posts'                   => array( 17 => true ),
	'generateblocks_dynamic_css_time'                    => 123,
	'mcp_generatepress_grid_projection_contract_version' => 'retry-required',
);
MCP_Abilities_GeneratePress_GenerateBlocks_Grid_Projection::rollout_generated_css_contract();
$assert( array( 17 => true ) === $test_options['generateblocks_dynamic_css_posts'], 'A partial rollout did not restore the prior registry.' );
$assert( 123 === $test_options['generateblocks_dynamic_css_time'], 'A partial rollout did not restore the prior regeneration time.' );
$assert( 'retry-required' === $test_options['mcp_generatepress_grid_projection_contract_version'], 'A partial rollout suppressed its required retry.' );

$test_failed_write = 'mcp_generatepress_grid_projection_contract_version';
$test_options      = array(
	'generateblocks_dynamic_css_posts'                   => array( 19 => true ),
	'generateblocks_dynamic_css_time'                    => 456,
	'mcp_generatepress_grid_projection_contract_version' => 'marker-retry-required',
);
MCP_Abilities_GeneratePress_GenerateBlocks_Grid_Projection::rollout_generated_css_contract();
$assert( array( 19 => true ) === $test_options['generateblocks_dynamic_css_posts'], 'A failed contract-version write did not restore the prior registry.' );
$assert( 456 === $test_options['generateblocks_dynamic_css_time'], 'A failed contract-version write did not restore the prior regeneration time.' );
$assert( 'marker-retry-required' === $test_options['mcp_generatepress_grid_projection_contract_version'], 'A failed contract-version write suppressed its required retry.' );

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "GenerateBlocks Grid Projection rollout: OK\n";
