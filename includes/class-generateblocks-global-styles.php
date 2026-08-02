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
				return self::invalid( $index, 'selector must be one valid GenerateBlocks CSS selector' );
			}

			if ( isset( $normalized[ $selector ] ) ) {
				return self::invalid( $index, 'selector is duplicated' );
			}

			$style_data = $style['styles'] ?? array();
			if ( ! is_array( $style_data ) ) {
				return self::invalid( $index, 'styles must be an object' );
			}

			$css = self::compile_css( $selector, $style_data );
			if ( is_wp_error( $css ) ) {
				return self::invalid( $index, $css->get_error_message() );
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
				return self::invalid( $index, 'delete selector must be one valid GenerateBlocks CSS selector' );
			}

			if ( isset( $normalized[ $selector ] ) ) {
				return self::invalid( $index, 'selector cannot be updated and deleted together' );
			}

			$delete[ $selector ] = true;
		}

		$created = array();
		$updated = array();
		$unchanged = array();
		$deleted = array();
		$existing_by_selector = self::index_by_selector();

		foreach ( $normalized as $selector => $style ) {
			$existing = $existing_by_selector[ $selector ] ?? null;
			if ( $existing instanceof WP_Post && self::matches( $existing, $style ) ) {
				$unchanged[] = $selector;
				continue;
			}

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

			$stored = get_post( $post_id );
			if ( $stored instanceof WP_Post ) {
				$existing_by_selector[ $selector ] = $stored;
			}
		}

		foreach ( array_keys( $delete ) as $selector ) {
			$existing = $existing_by_selector[ $selector ] ?? null;
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
			'unchanged' => $unchanged,
			'deleted' => $deleted,
			'styles'  => self::get_all(),
			'message' => 'GenerateBlocks current Global Styles synchronized successfully',
		);
	}

	/**
	 * Index the current native Global Style inventory by selector in one read.
	 *
	 * @return array<string, WP_Post>
	 */
	private static function index_by_selector(): array {
		$query = new WP_Query(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => array( 'publish', 'draft', 'private' ),
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		$indexed = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$selector = (string) get_post_meta( $post->ID, 'gb_style_selector', true );
			if ( '' !== self::normalize_selector( $selector ) ) {
				$indexed[ $selector ] = $post;
			}
		}

		return $indexed;
	}

	/**
	 * Compile the native Global Styles object into GenerateBlocks' required CSS cache.
	 *
	 * @param array<string, mixed> $styles Native style data.
	 * @return string|WP_Error
	 */
	private static function compile_css( string $selector, array $styles ) {
		$declarations = '';
		$nested = '';

		foreach ( $styles as $key => $value ) {
			$key = (string) $key;
			if ( is_array( $value ) ) {
				if ( 0 === strpos( $key, '@media ' ) ) {
					if ( 1 !== preg_match( '/^@media \\(\s*(?:min|max)-width:\s*[0-9.]+(?:px|em|rem)\s*\\)$/', $key ) ) {
						return new WP_Error( 'global_style_media_invalid', 'contains an invalid media query' );
					}

					$compiled = self::compile_css( $selector, $value );
					if ( is_wp_error( $compiled ) ) {
						return $compiled;
					}

					$nested .= $key . '{' . $compiled . '}';
					continue;
				}

				if ( false !== strpos( $key, '{' ) || false !== strpos( $key, '}' ) || false !== strpos( $key, ';' ) || false !== strpos( $key, '@' ) ) {
					return new WP_Error( 'global_style_nested_selector_invalid', 'contains an invalid nested selector' );
				}

				$nested_selector = false !== strpos( $key, '&' )
					? str_replace( '&', $selector, $key )
					: $selector . ' ' . $key;
				$compiled = self::compile_css( trim( $nested_selector ), $value );
				if ( is_wp_error( $compiled ) ) {
					return $compiled;
				}

				$nested .= $compiled;
				continue;
			}

			$property = self::css_property_name( $key );
			if ( '' === $property ) {
				return new WP_Error( 'global_style_property_invalid', 'contains an invalid style property' );
			}

			$css_value = trim( wp_strip_all_tags( (string) $value ) );
			if ( '' === $css_value || false !== strpos( $css_value, '{' ) || false !== strpos( $css_value, '}' ) || false !== strpos( $css_value, ';' ) ) {
				return new WP_Error( 'global_style_value_invalid', 'contains an invalid style value' );
			}

			$declarations .= $property . ':' . $css_value . ';';
		}

		$css = '' !== $declarations ? $selector . '{' . $declarations . '}' : '';
		return $css . $nested;
	}

	/** Convert a native camelCase style key to a CSS property name. */
	private static function css_property_name( string $key ): string {
		if ( 0 === strpos( $key, '--' ) ) {
			return 1 === preg_match( '/^--[A-Za-z0-9_-]+$/', $key ) ? $key : '';
		}

		if ( 1 !== preg_match( '/^[A-Za-z][A-Za-z0-9]*$/', $key ) ) {
			return '';
		}

		return strtolower( (string) preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $key ) );
	}

	/**
	 * Determine whether a native Global Style already matches exactly.
	 *
	 * @param WP_Post              $post  Existing style post.
	 * @param array<string, mixed> $style Normalized desired style.
	 */
	private static function matches( WP_Post $post, array $style ): bool {
		return (string) $post->post_title === (string) $style['selector']
			&& (string) $post->post_status === (string) $style['status']
			&& (int) $post->menu_order === (int) $style['menu_order']
			&& (array) get_post_meta( $post->ID, 'gb_style_data', true ) === (array) $style['styles']
			&& (string) get_post_meta( $post->ID, 'gb_style_css', true ) === (string) $style['css'];
	}

	/**
	 * Normalize one current Global Style selector, including documented compound selectors.
	 */
	private static function normalize_selector( string $selector ): string {
		$selector = trim( $selector );
		if (
			'' === $selector
			|| strlen( $selector ) > 512
			|| '.' !== $selector[0]
			|| 1 === preg_match( '/[\x00-\x1F\x7F{};@]/', $selector )
			|| 1 !== preg_match( '/^[A-Za-z0-9_.*#:\-\[\]()="\x27,\s>+~|^$]+$/', $selector )
		) {
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
