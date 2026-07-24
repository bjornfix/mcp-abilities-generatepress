<?php
/** Run with: wp eval-file tools/check-generateblocks-cache-atomic-wordpress-runtime.php */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$missing             = new stdClass();
$registry_before     = get_option( 'generateblocks_dynamic_css_posts', $missing );
$css_version_before  = get_option( 'generateblocks_css_version', $missing );
$css_time_before     = get_option( 'generateblocks_dynamic_css_time', $missing );
$current_user_before = get_current_user_id();
$fixture_id          = 0;
$absent_fixture_id   = 0;
$css_path            = '';
$absent_css_path     = '';
$css_before          = null;
$failures            = array();

$administrators = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( ! $administrators ) {
	fwrite( STDERR, "No administrator is available for the atomic cache runtime test.\n" );
	exit( 1 );
}
wp_set_current_user( (int) $administrators[0] );

try {
	$fixture_id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'draft',
			'post_title'   => 'Temporary atomic GenerateBlocks cache fixture',
			'post_content' => '<!-- wp:generateblocks/container {"uniqueId":"mcp-cache-atomic-runtime","isDynamic":true,"blockVersion":4} --><!-- wp:paragraph --><p>Fixture</p><!-- /wp:paragraph --><!-- /wp:generateblocks/container -->',
		)
	);
	if ( is_wp_error( $fixture_id ) || ! $fixture_id ) {
		throw new RuntimeException( 'Could not create the atomic cache fixture.' );
	}
	$fixture_id = (int) $fixture_id;
	$absent_fixture_id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'draft',
			'post_title'   => 'Temporary absent GenerateBlocks cache fixture',
			'post_content' => '<!-- wp:generateblocks/container {"uniqueId":"mcp-cache-atomic-absent","isDynamic":true,"blockVersion":4} --><!-- wp:paragraph --><p>Fixture</p><!-- /wp:paragraph --><!-- /wp:generateblocks/container -->',
		)
	);
	if ( is_wp_error( $absent_fixture_id ) || ! $absent_fixture_id ) {
		throw new RuntimeException( 'Could not create the absent cache fixture.' );
	}
	$absent_fixture_id = (int) $absent_fixture_id;
	$css_path   = mcp_abilities_generatepress_generateblocks_css_path( $fixture_id );
	$absent_css_path = mcp_abilities_generatepress_generateblocks_css_path( $absent_fixture_id );
	$filesystem = generateblocks_get_wp_filesystem();
	if ( ! $filesystem ) {
		throw new RuntimeException( 'WordPress filesystem is unavailable.' );
	}
	$css_before = file_exists( $css_path ) ? $filesystem->get_contents( $css_path ) : null;
	if ( ! is_dir( dirname( $css_path ) ) && ! wp_mkdir_p( dirname( $css_path ) ) ) {
		throw new RuntimeException( 'Could not create the GenerateBlocks CSS fixture directory.' );
	}
	$fixture_css = '.mcp-cache-atomic-runtime{display:block;}';
	if ( ! $filesystem->put_contents( $css_path, $fixture_css, 0644 ) ) {
		throw new RuntimeException( 'Could not create the prior CSS fixture.' );
	}
	update_post_meta( $fixture_id, '_generateblocks_dynamic_css_version', GENERATEBLOCKS_VERSION );
	update_post_meta( $absent_fixture_id, '_generateblocks_dynamic_css_version', GENERATEBLOCKS_VERSION );
	if ( file_exists( $absent_css_path ) ) {
		wp_delete_file( $absent_css_path );
	}
	update_option( 'generateblocks_dynamic_css_posts', array( $fixture_id => true, $absent_fixture_id => true ) );

	$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'generateblocks/clear-cache' ) : null;
	if ( ! $ability ) {
		throw new RuntimeException( 'GenerateBlocks cache ability is not registered.' );
	}
	$result = $ability->execute(
		array(
			'confirm'      => true,
			'delete_files' => true,
			'warm'         => true,
			'post_ids'     => array( $fixture_id, $absent_fixture_id ),
			'limit'        => 2,
		)
	);

	if ( ! empty( $result['success'] ) ) {
		$failures[] = 'A skipped destructive warm did not fail closed.';
	}
	if ( empty( $result['rolled_back'] ) ) {
		$failures[] = 'A skipped destructive warm did not report rollback.';
	}
	if ( ! file_exists( $css_path ) || $fixture_css !== $filesystem->get_contents( $css_path ) ) {
		$failures[] = 'A skipped destructive warm did not restore the exact prior CSS file.';
	}
	if ( file_exists( $absent_css_path ) ) {
		$failures[] = 'A skipped destructive warm did not restore a previously absent CSS file to absence.';
	}
	$registry_after = get_option( 'generateblocks_dynamic_css_posts', array() );
	if ( empty( $registry_after[ $fixture_id ] ) ) {
		$failures[] = 'A skipped destructive warm did not restore the prior registry entry.';
	}
	if ( GENERATEBLOCKS_VERSION !== get_post_meta( $fixture_id, '_generateblocks_dynamic_css_version', true ) ) {
		$failures[] = 'A skipped destructive warm did not restore the prior regeneration marker.';
	}
	if ( GENERATEBLOCKS_VERSION !== get_post_meta( $absent_fixture_id, '_generateblocks_dynamic_css_version', true ) ) {
		$failures[] = 'A skipped destructive warm did not restore the absent-file target regeneration marker.';
	}
	if ( $css_version_before !== get_option( 'generateblocks_css_version', $missing ) ) {
		$failures[] = 'A skipped destructive warm did not restore the prior global CSS version.';
	}
	if ( $css_time_before !== get_option( 'generateblocks_dynamic_css_time', $missing ) ) {
		$failures[] = 'A skipped destructive warm did not restore the prior CSS generation time.';
	}

	$state_before_global_rejection = get_option( 'generateblocks_dynamic_css_posts', array() );
	$file_before_global_rejection  = $filesystem->get_contents( $css_path );
	$global_result = $ability->execute( array( 'confirm' => true, 'delete_files' => true, 'warm' => true ) );
	if ( ! empty( $global_result['success'] ) || empty( $global_result['failed']['scope'] ) ) {
		$failures[] = 'An unbounded destructive global refresh was not rejected before mutation.';
	}
	if ( $state_before_global_rejection !== get_option( 'generateblocks_dynamic_css_posts', array() ) || $file_before_global_rejection !== $filesystem->get_contents( $css_path ) ) {
		$failures[] = 'A rejected destructive global refresh mutated cache state.';
	}
} finally {
	if ( $fixture_id ) {
		wp_delete_post( $fixture_id, true );
		if ( get_post( $fixture_id ) ) {
			$failures[] = 'Atomic cache test cleanup did not delete its temporary post.';
		}
	}
	if ( $absent_fixture_id ) {
		wp_delete_post( $absent_fixture_id, true );
		if ( get_post( $absent_fixture_id ) ) {
			$failures[] = 'Atomic cache test cleanup did not delete its absent-file post.';
		}
	}
	if ( $absent_css_path && file_exists( $absent_css_path ) ) {
		wp_delete_file( $absent_css_path );
		if ( file_exists( $absent_css_path ) ) {
			$failures[] = 'Atomic cache test cleanup left an absent-file CSS fixture behind.';
		}
	}
	if ( $css_path ) {
		$filesystem = generateblocks_get_wp_filesystem();
		if ( null === $css_before ) {
			if ( file_exists( $css_path ) ) {
				wp_delete_file( $css_path );
			}
			if ( file_exists( $css_path ) ) {
				$failures[] = 'Atomic cache test cleanup left a generated CSS fixture behind.';
			}
		} elseif ( $filesystem ) {
			if ( ! $filesystem->put_contents( $css_path, $css_before, 0644 ) || $css_before !== $filesystem->get_contents( $css_path ) ) {
				$failures[] = 'Atomic cache test cleanup did not restore the prior CSS bytes.';
			}
		}
	}
	if ( $registry_before === $missing ) {
		delete_option( 'generateblocks_dynamic_css_posts' );
	} else {
		update_option( 'generateblocks_dynamic_css_posts', $registry_before );
	}
	if ( $registry_before !== get_option( 'generateblocks_dynamic_css_posts', $missing ) ) {
		$failures[] = 'Atomic cache test cleanup did not restore the registry.';
	}
	if ( $css_version_before === $missing ) {
		delete_option( 'generateblocks_css_version' );
	} else {
		update_option( 'generateblocks_css_version', $css_version_before );
	}
	if ( $css_version_before !== get_option( 'generateblocks_css_version', $missing ) ) {
		$failures[] = 'Atomic cache test cleanup did not restore the global CSS version.';
	}
	if ( $css_time_before === $missing ) {
		delete_option( 'generateblocks_dynamic_css_time' );
	} else {
		update_option( 'generateblocks_dynamic_css_time', $css_time_before );
	}
	if ( $css_time_before !== get_option( 'generateblocks_dynamic_css_time', $missing ) ) {
		$failures[] = 'Atomic cache test cleanup did not restore the CSS generation time.';
	}
	wp_set_current_user( $current_user_before );
}

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "GenerateBlocks atomic cache refresh runtime: OK\n";
