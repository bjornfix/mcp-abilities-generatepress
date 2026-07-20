<?php
/** Run with: wp eval-file tools/check-generateblocks-cache-wordpress-runtime.php */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$missing               = new stdClass();
$registry_before       = get_option( 'generateblocks_dynamic_css_posts', $missing );
$css_version_before    = get_option( 'generateblocks_css_version', $missing );
$current_user_before   = get_current_user_id();
$fixture_ids           = array();
$failures              = array();

$administrators = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( ! $administrators ) {
	fwrite( STDERR, "No administrator is available for the cache ability runtime test.\n" );
	exit( 1 );
}
wp_set_current_user( (int) $administrators[0] );

try {
	foreach ( array( 'A', 'B' ) as $suffix ) {
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Temporary GenerateBlocks cache fixture ' . $suffix,
				'post_content' => '<!-- wp:generateblocks/container {"uniqueId":"mcp-cache-runtime-' . strtolower( $suffix ) . '","isDynamic":true,"blockVersion":4} --><!-- wp:paragraph --><p>Fixture</p><!-- /wp:paragraph --><!-- /wp:generateblocks/container -->',
			)
		);
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			throw new RuntimeException( 'Could not create the temporary cache fixture.' );
		}
		$fixture_ids[] = (int) $post_id;
	}

	update_option(
		'generateblocks_dynamic_css_posts',
		array(
			$fixture_ids[0] => 100,
			$fixture_ids[1] => 200,
		)
	);

	$discovered = mcp_abilities_generatepress_discover_generateblocks_post_ids();
	foreach ( $fixture_ids as $post_id ) {
		if ( ! in_array( $post_id, $discovered, true ) ) {
			$failures[] = "Published GenerateBlocks fixture {$post_id} was not discovered from authoritative content.";
		}
	}

	$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'generateblocks/clear-cache' ) : null;
	if ( ! $ability ) {
		$failures[] = 'GenerateBlocks cache ability is not registered.';
	} else {
		$targeted = $ability->execute(
			array(
				'confirm'      => true,
				'delete_files' => false,
				'warm'         => false,
				'post_ids'     => array( $fixture_ids[0] ),
			)
		);
		$after_targeted = get_option( 'generateblocks_dynamic_css_posts', array() );
		if ( empty( $targeted['success'] ) || isset( $after_targeted[ $fixture_ids[0] ] ) || ! isset( $after_targeted[ $fixture_ids[1] ] ) ) {
			$failures[] = 'Targeted invalidation did not preserve the unrelated GenerateBlocks registry entry.';
		}

		$global = $ability->execute( array( 'confirm' => true, 'delete_files' => false, 'warm' => false ) );
		if ( empty( $global['success'] ) || array() !== get_option( 'generateblocks_dynamic_css_posts', array() ) ) {
			$failures[] = 'Global invalidation did not reset the regenerable GenerateBlocks registry.';
		}
	}
} finally {
	foreach ( $fixture_ids as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	if ( $registry_before === $missing ) {
		delete_option( 'generateblocks_dynamic_css_posts' );
	} else {
		update_option( 'generateblocks_dynamic_css_posts', $registry_before );
	}
	if ( $css_version_before === $missing ) {
		delete_option( 'generateblocks_css_version' );
	} else {
		update_option( 'generateblocks_css_version', $css_version_before );
	}
	wp_set_current_user( $current_user_before );
}

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "GenerateBlocks cache WordPress runtime: OK\n";
