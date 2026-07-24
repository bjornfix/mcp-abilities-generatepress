<?php
/** Run with: wp eval-file tools/check-generateblocks-cache-success-wordpress-runtime.php */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$missing             = new stdClass();
$post_id             = 0;
$candidate_ids       = get_posts(
	array(
		'post_type'      => array_values( array_diff( get_post_types( array( 'public' => true ), 'names' ), array( 'attachment' ) ) ),
		'post_status'    => 'publish',
		'posts_per_page' => 100,
		'fields'         => 'ids',
		'orderby'        => 'ID',
		'order'          => 'DESC',
	)
);
foreach ( $candidate_ids as $candidate_id ) {
	$candidate = get_post( $candidate_id );
	$candidate_path = mcp_abilities_generatepress_generateblocks_css_path( (int) $candidate_id );
	if ( $candidate && false !== strpos( (string) $candidate->post_content, '<!-- wp:generateblocks/' ) && file_exists( $candidate_path ) ) {
		$post_id = (int) $candidate_id;
		break;
	}
}
$registry_before     = get_option( 'generateblocks_dynamic_css_posts', $missing );
$css_version_before  = get_option( 'generateblocks_css_version', $missing );
$css_time_before     = get_option( 'generateblocks_dynamic_css_time', $missing );
$current_user_before = get_current_user_id();
$meta_exists_before  = $post_id > 0 && metadata_exists( 'post', $post_id, '_generateblocks_dynamic_css_version' );
$meta_before         = $post_id > 0 ? get_post_meta( $post_id, '_generateblocks_dynamic_css_version', true ) : '';
$css_path            = $post_id > 0 ? mcp_abilities_generatepress_generateblocks_css_path( $post_id ) : '';
$filesystem          = generateblocks_get_wp_filesystem();
$css_before          = $filesystem && $css_path && file_exists( $css_path ) ? $filesystem->get_contents( $css_path ) : null;
$failures            = array();

$post = $post_id > 0 ? get_post( $post_id ) : null;
if ( ! $post || 'publish' !== $post->post_status || false === strpos( (string) $post->post_content, '<!-- wp:generateblocks/' ) ) {
	fwrite( STDERR, "No published GenerateBlocks refresh fixture is available.\n" );
	exit( 1 );
}
if ( ! $filesystem || ! is_string( $css_before ) ) {
	fwrite( STDERR, "The published GenerateBlocks fixture has no readable prior CSS file.\n" );
	exit( 1 );
}

$administrators = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( ! $administrators ) {
	fwrite( STDERR, "No administrator is available for the successful cache runtime test.\n" );
	exit( 1 );
}
wp_set_current_user( (int) $administrators[0] );

try {
	$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'generateblocks/clear-cache' ) : null;
	if ( ! $ability ) {
		throw new RuntimeException( 'GenerateBlocks cache ability is not registered.' );
	}
	$result = $ability->execute(
		array(
			'confirm'      => true,
			'delete_files' => true,
			'warm'         => true,
			'post_ids'     => array( $post_id ),
			'limit'        => 1,
		)
	);

	if ( empty( $result['success'] ) || ! in_array( $post_id, $result['warmed'] ?? array(), true ) ) {
		$failures[] = 'A published GenerateBlocks page did not complete a successful destructive refresh.';
	}
	if ( ! file_exists( $css_path ) || ! is_string( $filesystem->get_contents( $css_path ) ) ) {
		$failures[] = 'A successful destructive refresh did not leave a readable CSS file.';
	}
	if ( GENERATEBLOCKS_VERSION !== get_post_meta( $post_id, '_generateblocks_dynamic_css_version', true ) ) {
		$failures[] = 'A successful destructive refresh did not preserve the upstream regeneration marker.';
	}
} finally {
	if ( ! $filesystem->put_contents( $css_path, $css_before, defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644 ) || $css_before !== $filesystem->get_contents( $css_path ) ) {
		$failures[] = 'Successful cache test cleanup did not restore the prior CSS bytes.';
	}
	if ( $registry_before === $missing ) {
		delete_option( 'generateblocks_dynamic_css_posts' );
	} else {
		update_option( 'generateblocks_dynamic_css_posts', $registry_before );
	}
	if ( $registry_before !== get_option( 'generateblocks_dynamic_css_posts', $missing ) ) {
		$failures[] = 'Successful cache test cleanup did not restore the registry.';
	}
	if ( $css_version_before === $missing ) {
		delete_option( 'generateblocks_css_version' );
	} else {
		update_option( 'generateblocks_css_version', $css_version_before );
	}
	if ( $css_version_before !== get_option( 'generateblocks_css_version', $missing ) ) {
		$failures[] = 'Successful cache test cleanup did not restore the global CSS version.';
	}
	if ( $css_time_before === $missing ) {
		delete_option( 'generateblocks_dynamic_css_time' );
	} else {
		update_option( 'generateblocks_dynamic_css_time', $css_time_before );
	}
	if ( $css_time_before !== get_option( 'generateblocks_dynamic_css_time', $missing ) ) {
		$failures[] = 'Successful cache test cleanup did not restore the CSS generation time.';
	}
	if ( $meta_exists_before ) {
		update_post_meta( $post_id, '_generateblocks_dynamic_css_version', $meta_before );
	} else {
		delete_post_meta( $post_id, '_generateblocks_dynamic_css_version' );
	}
	if ( $meta_exists_before !== metadata_exists( 'post', $post_id, '_generateblocks_dynamic_css_version' ) || ( $meta_exists_before && $meta_before !== get_post_meta( $post_id, '_generateblocks_dynamic_css_version', true ) ) ) {
		$failures[] = 'Successful cache test cleanup did not restore the regeneration marker.';
	}
	wp_set_current_user( $current_user_before );
}

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "GenerateBlocks successful cache refresh runtime: OK\n";
