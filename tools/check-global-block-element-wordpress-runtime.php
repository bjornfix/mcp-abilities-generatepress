<?php
/** WordPress runtime for the reusable GeneratePress Block Element Interface. */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$current_user_before = get_current_user_id();
$element_ids         = array();
$failures            = array();
$missing             = new stdClass();
$cache_options       = array(
	'generate_dynamic_css_output'         => get_option( 'generate_dynamic_css_output', $missing ),
	'generate_dynamic_css_cached_version' => get_option( 'generate_dynamic_css_cached_version', $missing ),
	'generateblocks_dynamic_css_time'      => get_option( 'generateblocks_dynamic_css_time', $missing ),
);
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
	if ( function_exists( 'wp_has_ability' ) && wp_has_ability( 'generatepress/upsert-archive-hook-element' ) ) {
		throw new RuntimeException( 'The removed archive-specific Element ability is still registered.' );
	}

	$slug    = 'mcp-runtime-block-element-' . sanitize_key( wp_generate_uuid4() );
	$content = '<!-- wp:generateblocks/container {"uniqueId":"mcpglobalruntime","isDynamic":true,"blockVersion":4} --><!-- wp:paragraph --><p>Global fixture</p><!-- /wp:paragraph --><!-- /wp:generateblocks/container -->';
	$input   = array(
		'title'              => 'Temporary global Block Element',
		'slug'               => $slug,
		'content'            => $content,
		'block_type'         => 'hook',
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
	$element_ids[] = $element_id;
	update_option( 'generate_dynamic_css_output', 'mcp-runtime-stale-output', false );
	update_option( 'generate_dynamic_css_cached_version', 'mcp-runtime-stale-version', false );
	update_option( 'generateblocks_dynamic_css_time', 731, false );
	$input['title'] = 'Updated global Block Element fixture';
	$updated = $ability->execute( $input );
	if ( empty( $updated['success'] ) || $element_id !== (int) ( $updated['id'] ?? 0 ) || 'updated' !== (string) ( $updated['action'] ?? '' ) ) {
		$failures[] = 'A repeated stable-slug call did not update the same Element.';
	}
	if (
		false !== get_option( 'generate_dynamic_css_output', false )
		|| false !== get_option( 'generate_dynamic_css_cached_version', false )
		|| 0 !== (int) get_option( 'generateblocks_dynamic_css_time', -1 )
	) {
		$failures[] = 'A changed Element did not invalidate both dynamic CSS caches.';
	}

	update_option( 'generate_dynamic_css_output', 'mcp-runtime-current-output', false );
	update_option( 'generate_dynamic_css_cached_version', 'mcp-runtime-current-version', false );
	update_option( 'generateblocks_dynamic_css_time', 947, false );
	$post_writes = 0;
	$meta_writes = 0;
	$post_write_guard = static function ( array $data ) use ( &$post_writes ): array {
		++$post_writes;
		return $data;
	};
	$meta_update_guard = static function ( $check ) use ( &$meta_writes ) {
		++$meta_writes;
		return $check;
	};
	$meta_delete_guard = static function ( $check ) use ( &$meta_writes ) {
		++$meta_writes;
		return $check;
	};
	add_filter( 'wp_insert_post_data', $post_write_guard, 1, 1 );
	add_filter( 'update_post_metadata', $meta_update_guard, 1, 1 );
	add_filter( 'delete_post_metadata', $meta_delete_guard, 1, 1 );
	try {
		$unchanged = $ability->execute( $input );
	} finally {
		remove_filter( 'wp_insert_post_data', $post_write_guard, 1 );
		remove_filter( 'update_post_metadata', $meta_update_guard, 1 );
		remove_filter( 'delete_post_metadata', $meta_delete_guard, 1 );
	}
	if ( empty( $unchanged['success'] ) || 'unchanged' !== (string) ( $unchanged['action'] ?? '' ) || 0 !== $post_writes || 0 !== $meta_writes ) {
		$failures[] = 'An exact repeated Element contract was not a write-free no-op.';
	}
	if (
		'mcp-runtime-current-output' !== get_option( 'generate_dynamic_css_output', '' )
		|| 'mcp-runtime-current-version' !== get_option( 'generate_dynamic_css_cached_version', '' )
		|| 947 !== (int) get_option( 'generateblocks_dynamic_css_time', -1 )
	) {
		$failures[] = 'An unchanged Element invalidated dynamic CSS.';
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

	$template_input = array(
		'title' => 'Temporary universal Content Template',
		'slug' => 'mcp-runtime-content-template-' . sanitize_key( wp_generate_uuid4() ),
		'content' => '<!-- wp:generateblocks/container {"uniqueId":"mcpcontentruntime","isDynamic":true,"blockVersion":4} --><!-- wp:post-content /--><!-- /wp:generateblocks/container -->',
		'block_type' => 'content-template',
		'status' => 'draft',
		'display_conditions' => array( array( 'rule' => 'post:page', 'object' => '0' ), array( 'rule' => 'general:front_page', 'object' => '0' ) ),
		'use_theme_post_container' => false,
		'post_loop_item_tagname' => 'main',
	);
	$template = $ability->execute( $template_input );
	if ( empty( $template['success'] ) || 'content-template' !== (string) ( $template['block_type'] ?? '' ) ) {
		throw new RuntimeException( 'The public Interface did not create a native Content Template.' );
	}
	$template_id = (int) $template['id'];
	$element_ids[] = $template_id;
	if (
		'content-template' !== get_post_meta( $template_id, '_generate_block_type', true )
		|| '' !== get_post_meta( $template_id, '_generate_hook', true )
		|| 'main' !== get_post_meta( $template_id, '_generate_post_loop_item_tagname', true )
	) {
		$failures[] = 'The native Content Template metadata contract failed.';
	}
} catch ( Throwable $error ) {
	$failures[] = $error->getMessage();
} finally {
	foreach ( $element_ids as $element_id ) {
		wp_delete_post( (int) $element_id, true );
	}
	foreach ( $cache_options as $option_name => $option_value ) {
		if ( $missing === $option_value ) {
			delete_option( $option_name );
		} else {
			update_option( $option_name, $option_value );
		}
	}
	wp_set_current_user( $current_user_before );
}

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

fwrite( STDOUT, "GeneratePress global Block Element WordPress runtime passed.\n" );
