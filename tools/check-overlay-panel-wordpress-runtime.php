<?php
/** WordPress runtime for native GenerateBlocks Pro Overlay Panel abilities. */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$current_user_before = get_current_user_id();
$administrators = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( ! $administrators ) {
	fwrite( STDERR, "No administrator is available for the Overlay Panel runtime test.\n" );
	exit( 1 );
}
wp_set_current_user( (int) $administrators[0] );

$overlay_id = 0;
$menu_id = 0;
$menu_item_id = 0;
$failures = array();

try {
	$upsert = wp_get_ability( 'generatepress/upsert-overlay-panel' );
	$attach = wp_get_ability( 'generatepress/attach-menu-item-mega-menu' );
	$list = wp_get_ability( 'generatepress/list-overlay-panels' );
	if ( ! $upsert || ! $attach || ! $list ) {
		throw new RuntimeException( 'One or more Overlay Panel abilities are not registered.' );
	}
	$slug = 'mcp-runtime-overlay-' . sanitize_key( wp_generate_uuid4() );
	$content = '<!-- wp:generateblocks/container {"uniqueId":"mcpoverlayruntime","isDynamic":true,"blockVersion":4} --><!-- wp:paragraph --><p>Overlay fixture</p><!-- /wp:paragraph --><!-- /wp:generateblocks/container -->';
	$input = array(
		'title' => 'Temporary native mega menu',
		'slug' => $slug,
		'content' => $content,
		'type' => 'mega-menu',
		'status' => 'publish',
		'placement' => 'bottom',
		'position_to_parent' => '.gb-navigation',
		'hover_buffer' => 24,
		'width_mode' => 'full',
	);
	$created = $upsert->execute( $input );
	if ( empty( $created['success'] ) || 'created' !== (string) ( $created['action'] ?? '' ) ) {
		throw new RuntimeException( 'The native Overlay Panel was not created.' );
	}
	$overlay_id = (int) $created['id'];
	$unchanged = $upsert->execute( $input );
	if ( 'unchanged' !== (string) ( $unchanged['action'] ?? '' ) || $overlay_id !== (int) ( $unchanged['id'] ?? 0 ) ) {
		$failures[] = 'An exact Overlay Panel repeat was not a write-free no-op.';
	}
	if (
		'mega-menu' !== get_post_meta( $overlay_id, '_gb_overlay_type', true )
		|| 'bottom' !== get_post_meta( $overlay_id, '_gb_overlay_placement', true )
		|| '.gb-navigation' !== get_post_meta( $overlay_id, '_gb_overlay_position_to_parent', true )
		|| 'full' !== get_post_meta( $overlay_id, '_gb_overlay_width_mode', true )
	) {
		$failures[] = 'Native Overlay Panel metadata did not match the public contract.';
	}

	$menu_id = (int) wp_create_nav_menu( 'Temporary overlay runtime menu ' . wp_generate_password( 6, false, false ) );
	$menu_item_id = (int) wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => 'Languages', 'menu-item-url' => home_url( '/' ), 'menu-item-status' => 'publish' ) );
	$attached = $attach->execute( array( 'menu_item_id' => $menu_item_id, 'overlay_id' => $overlay_id ) );
	$attached_again = $attach->execute( array( 'menu_item_id' => $menu_item_id, 'overlay_id' => $overlay_id ) );
	if ( 'updated' !== (string) ( $attached['action'] ?? '' ) || 'unchanged' !== (string) ( $attached_again['action'] ?? '' ) || (string) $overlay_id !== (string) get_post_meta( $menu_item_id, '_gb_mega_menu', true ) ) {
		$failures[] = 'Native menu-item mega-menu attachment was not idempotent.';
	}
	$inventory = $list->execute( array() );
	$ids = array_map( static function ( array $row ): int { return (int) ( $row['id'] ?? 0 ); }, (array) ( $inventory['overlays'] ?? array() ) );
	if ( empty( $inventory['success'] ) || ! in_array( $overlay_id, $ids, true ) ) {
		$failures[] = 'Overlay inventory did not expose the created native panel.';
	}
} catch ( Throwable $error ) {
	$failures[] = $error->getMessage();
} finally {
	if ( $menu_id > 0 ) {
		wp_delete_nav_menu( $menu_id );
	}
	if ( $overlay_id > 0 ) {
		wp_delete_post( $overlay_id, true );
	}
	wp_set_current_user( $current_user_before );
}

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

fwrite( STDOUT, "GenerateBlocks Pro Overlay Panel WordPress runtime passed.\n" );
