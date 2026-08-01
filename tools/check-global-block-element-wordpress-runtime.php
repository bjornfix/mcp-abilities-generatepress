<?php
/** WordPress runtime for the reusable GeneratePress Block Element Interface. */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$current_user_before = get_current_user_id();
$element_id          = 0;
$failures            = array();
$administrators      = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( ! $administrators ) {
	fwrite( STDERR, "No administrator is available for the Block Element runtime test.\n" );
	exit( 1 );
}
wp_set_current_user( (int) $administrators[0] );

try {
	$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'generatepress/upsert-block-element' ) : null;
	if ( ! $ability ) {
		throw new RuntimeException( 'The global Block Element ability is not registered.' );
	}
	if ( function_exists( 'wp_get_ability' ) && wp_get_ability( 'generatepress/upsert-archive-hook-element' ) ) {
		throw new RuntimeException( 'The removed archive-specific Element ability is still registered.' );
	}

	$slug    = 'mcp-runtime-block-element-' . sanitize_key( wp_generate_uuid4() );
	$content = '<!-- wp:generateblocks/container {"uniqueId":"mcpglobalruntime","isDynamic":true,"blockVersion":4} --><!-- wp:paragraph --><p>Global fixture</p><!-- /wp:paragraph --><!-- /wp:generateblocks/container -->';
	$input   = array(
		'title'              => 'Temporary global Block Element',
		'slug'               => $slug,
		'content'            => $content,
		'hook'               => 'generate_after_header',
		'priority'           => 10,
		'status'             => 'draft',
		'display_conditions' => array( array( 'rule' => 'general:site', 'object' => '0' ) ),
	);
	$created = $ability->execute( $input );
	if ( empty( $created['success'] ) || empty( $created['id'] ) || 'created' !== (string) ( $created['action'] ?? '' ) ) {
		throw new RuntimeException( 'The public Interface did not create the native Block Element.' );
	}
	$element_id = (int) $created['id'];
	$input['title'] = 'Updated global Block Element fixture';
	$updated = $ability->execute( $input );
	if ( empty( $updated['success'] ) || $element_id !== (int) ( $updated['id'] ?? 0 ) || 'updated' !== (string) ( $updated['action'] ?? '' ) ) {
		$failures[] = 'A repeated stable-slug call did not update the same Element.';
	}

	$expected_meta = array(
		'_generate_element_type'             => 'block',
		'_generate_block_type'               => 'hook',
		'_generate_hook_type'                => 'hook',
		'_generate_hook'                     => 'generate_after_header',
		'_generate_element_display_conditions' => array( array( 'rule' => 'general:site', 'object' => '0' ) ),
	);
	foreach ( $expected_meta as $key => $expected ) {
		if ( $expected !== get_post_meta( $element_id, $key, true ) ) {
			$failures[] = 'The native Element meta contract failed for ' . $key . '.';
		}
	}
} catch ( Throwable $error ) {
	$failures[] = $error->getMessage();
} finally {
	if ( $element_id > 0 ) {
		wp_delete_post( $element_id, true );
	}
	wp_set_current_user( $current_user_before );
}

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

fwrite( STDOUT, "GeneratePress global Block Element WordPress runtime passed.\n" );
