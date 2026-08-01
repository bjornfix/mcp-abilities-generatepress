<?php
/** Run with: wp eval-file tools/check-generateblocks-targeted-cache-scope-wordpress-runtime.php */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$missing             = new stdClass();
$registry_before     = get_option( 'generateblocks_dynamic_css_posts', $missing );
$current_user_before = get_current_user_id();
$scan_attempts       = 0;
$failures            = array();
$published_ids       = get_posts(
	array(
		'post_type'      => array_values( array_diff( get_post_types( array( 'public' => true ), 'names' ), array( 'attachment' ) ) ),
		'post_status'    => 'publish',
		'posts_per_page' => 2,
		'fields'         => 'ids',
		'orderby'        => 'ID',
		'order'          => 'DESC',
	)
);

if ( count( $published_ids ) < 2 ) {
	fwrite( STDERR, "Two published posts are required for the targeted cache scope test.\n" );
	exit( 1 );
}

$target_id    = absint( $published_ids[0] );
$unrelated_id = absint( $published_ids[1] );
$scan_guard   = static function ( $posts, WP_Query $query ) use ( &$scan_attempts ) {
	if (
		-1 === (int) $query->get( 'posts_per_page' )
		&& 'ids' === $query->get( 'fields' )
		&& 'publish' === $query->get( 'post_status' )
	) {
		++$scan_attempts;
		throw new RuntimeException( 'targeted_cache_started_global_scan' );
	}

	return $posts;
};

$administrators = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( ! $administrators ) {
	fwrite( STDERR, "No administrator is available for the targeted cache scope test.\n" );
	exit( 1 );
}

wp_set_current_user( (int) $administrators[0] );
update_option(
	'generateblocks_dynamic_css_posts',
	array(
		$target_id    => 100,
		$unrelated_id => 200,
	)
);
add_filter( 'posts_pre_query', $scan_guard, 1, 2 );

try {
	$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'generateblocks/clear-cache' ) : null;
	if ( ! $ability ) {
		throw new RuntimeException( 'GenerateBlocks cache ability is not registered.' );
	}

	$result = $ability->execute(
		array(
			'confirm'      => true,
			'delete_files' => false,
			'warm'         => false,
			'post_ids'     => array( $target_id ),
		)
	);
	$after = get_option( 'generateblocks_dynamic_css_posts', array() );
	if ( empty( $result['success'] ) ) {
		$failures[] = 'Targeted cache invalidation failed.';
	}
	if ( isset( $after[ $target_id ] ) || ! isset( $after[ $unrelated_id ] ) ) {
		$failures[] = 'Targeted cache invalidation changed the wrong registry scope.';
	}
	if ( 0 !== $scan_attempts ) {
		$failures[] = 'Targeted cache invalidation performed a global published-content scan.';
	}
} finally {
	remove_filter( 'posts_pre_query', $scan_guard, 1 );
	if ( $registry_before === $missing ) {
		delete_option( 'generateblocks_dynamic_css_posts' );
	} else {
		update_option( 'generateblocks_dynamic_css_posts', $registry_before );
	}
	wp_set_current_user( $current_user_before );
}

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "GenerateBlocks targeted cache scope runtime: OK\n";
