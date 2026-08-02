<?php
/**
 * Current GenerateBlocks Pro Global Styles adapter.
 *
 * @package MCP_Abilities_GeneratePress
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read and synchronize the current GenerateBlocks Pro Global Styles model.
 */
final class MCP_Abilities_GeneratePress_GenerateBlocks_Global_Styles {
	private const POST_TYPE = 'gblocks_styles';

	/**
	 * Return current Global Styles in their native stored representation.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_all(): array {
		if ( ! post_type_exists( self::POST_TYPE ) ) {
			return array();
		}

		$query = new WP_Query(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => array( 'publish', 'draft', 'private' ),
				'posts_per_page'         => -1,
				'orderby'                => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		$styles = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$styles[] = array(
				'id'         => (int) $post->ID,
				'selector'   => (string) get_post_meta( $post->ID, 'gb_style_selector', true ),
				'status'     => (string) $post->post_status,
				'menu_order' => (int) $post->menu_order,
				'styles'     => (array) get_post_meta( $post->ID, 'gb_style_data', true ),
				'css'        => (string) get_post_meta( $post->ID, 'gb_style_css', true ),
			);
		}

		return $styles;
	}

	/**
	 * Upsert supplied Global Styles and explicitly delete named selectors.
	 *
	 * @param array<int, array<string, mixed>> $styles Global Style definitions.
	 * @param string[]                        $delete_selectors Selectors to delete.
	 * @return array<string, mixed>
	 */
	public static function synchronize( array $styles, array $delete_selectors = array() ): array {
		if ( ! post_type_exists( self::POST_TYPE ) ) {
			return array(
				'success' => false,
				'message' => 'GenerateBlocks Pro current Global Styles are unavailable',
			);
		}

		$normalized = array();
		foreach ( $styles as $index => $style ) {
			if ( ! is_array( $style ) ) {
				return self::invalid( $index, 'must be an object' );
			}

			$selector = self::normalize_selector( (string) ( $style['selector'] ?? '' ) );
			if ( '' === $selector ) {
				return self::invalid( $index, 'selector must be one valid CSS class' );
			}

			if ( isset( $normalized[ $selector ] ) ) {
				return self::invalid( $index, 'selector is duplicated' );
			}

			$style_data = $style['styles'] ?? array();
			if ( ! is_array( $style_data ) ) {
				return self::invalid( $index, 'styles must be an object' );
			}

			$css = trim( wp_strip_all_tags( (string) ( $style['css'] ?? '' ) ) );
			if ( '' === $css || false === strpos( $css, $selector ) ) {
				return self::invalid( $index, 'css must contain its selector' );
			}

			$status = (string) ( $style['status'] ?? 'publish' );
			if ( ! in_array( $status, array( 'publish', 'draft', 'private' ), true ) ) {
				return self::invalid( $index, 'status is invalid' );
			}

			$normalized[ $selector ] = array(
				'selector'   => $selector,
				'status'     => $status,
				'menu_order' => max( 0, (int) ( $style['menu_order'] ?? 0 ) ),
				'styles'     => $style_data,
				'css'        => $css,
			);
		}

		$delete = array();
		foreach ( $delete_selectors as $index => $selector ) {
			$selector = self::normalize_selector( (string) $selector );
			if ( '' === $selector ) {
				return self::invalid( $index, 'delete selector must be one valid CSS class' );
			}

			if ( isset( $normalized[ $selector ] ) ) {
				return self::invalid( $index, 'selector cannot be updated and deleted together' );
			}

			$delete[ $selector ] = true;
		}

		$created = array();
		$updated = array();
		$deleted = array();

		foreach ( $normalized as $selector => $style ) {
			$existing = self::get_by_selector( $selector );
			$postarr  = array(
				'post_type'   => self::POST_TYPE,
				'post_title'  => $selector,
				'post_status' => $style['status'],
				'menu_order'  => $style['menu_order'],
				'meta_input'  => array(
					'gb_style_selector' => $selector,
					'gb_style_data'     => $style['styles'],
					'gb_style_css'      => $style['css'],
				),
			);

			if ( $existing instanceof WP_Post ) {
				$postarr['ID'] = (int) $existing->ID;
			}

			$post_id = wp_insert_post( wp_slash( $postarr ), true );
			if ( is_wp_error( $post_id ) ) {
				return array(
					'success' => false,
					'message' => $post_id->get_error_message(),
				);
			}

			if ( $existing instanceof WP_Post ) {
				$updated[] = $selector;
			} else {
				$created[] = $selector;
			}
		}

		foreach ( array_keys( $delete ) as $selector ) {
			$existing = self::get_by_selector( $selector );
			if ( ! $existing instanceof WP_Post ) {
				continue;
			}

			if ( false === wp_delete_post( $existing->ID, true ) ) {
				return array(
					'success' => false,
					'message' => 'Failed to delete GenerateBlocks Global Style ' . $selector,
				);
			}

			$deleted[] = $selector;
		}

		if ( class_exists( 'GenerateBlocks_Pro_Enqueue_Styles' ) ) {
			GenerateBlocks_Pro_Enqueue_Styles::get_instance()->build_css();
		}

		return array(
			'success' => true,
			'created' => $created,
			'updated' => $updated,
			'deleted' => $deleted,
			'styles'  => self::get_all(),
			'message' => 'GenerateBlocks current Global Styles synchronized successfully',
		);
	}

	/**
	 * Find one current Global Style by its exact selector.
	 */
	private static function get_by_selector( string $selector ): ?WP_Post {
		$query = new WP_Query(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => array( 'publish', 'draft', 'private' ),
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'     => 'gb_style_selector',
						'value'   => $selector,
						'compare' => '=',
					),
				),
			)
		);

		$post = $query->posts[0] ?? null;
		return $post instanceof WP_Post ? $post : null;
	}

	/**
	 * Normalize one native Global Style class selector.
	 */
	private static function normalize_selector( string $selector ): string {
		$selector = trim( $selector );
		if ( 1 !== preg_match( '/^\.[A-Za-z_][A-Za-z0-9_-]*$/', $selector ) ) {
			return '';
		}

		return $selector;
	}

	/**
	 * Return a consistent validation error.
	 *
	 * @return array<string, mixed>
	 */
	private static function invalid( int $index, string $reason ): array {
		return array(
			'success' => false,
			'message' => sprintf( 'Global Style at index %d %s', $index, $reason ),
		);
	}
}
