<?php
/**
 * Reject GenerateBlocks content that the block editor cannot safely own.
 *
 * @package MCP_Abilities_GeneratePress
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns the shared pre-save validation for GenerateBlocks page content.
 *
 * The MCP content ability and Gutenberg REST endpoint are two Interfaces for
 * the same page write. This Adapter keeps their guard identical, so invalid
 * block structures cannot enter WordPress through either path.
 */
final class MCP_Abilities_GeneratePress_GenerateBlocks_Content_Save_Guard {
	/** Register both page-write Interfaces. */
	public static function register(): void {
		add_filter( 'mcp_content_write_preflight', array( __CLASS__, 'validate_mcp_write' ), 999, 2 );
		add_filter( 'rest_pre_insert_page', array( __CLASS__, 'validate_rest_write' ), 999, 2 );
	}

	/**
	 * Validate the neutral MCP content-write preflight.
	 *
	 * @param mixed               $result Existing policy result.
	 * @param array<string,mixed> $context Write context.
	 * @return mixed
	 */
	public static function validate_mcp_write( $result, array $context ) {
		if ( true !== $result ) {
			return $result;
		}
		if ( 'page' !== strtolower( (string) ( $context['post_type'] ?? '' ) ) ) {
			return $result;
		}

		return self::validate_content(
			(string) ( $context['content'] ?? '' ),
			'publish' === strtolower( (string) ( $context['target_status'] ?? '' ) )
		);
	}

	/**
	 * Validate the content Gutenberg is about to persist through REST.
	 *
	 * @param mixed $prepared_post Prepared post object or WP_Error.
	 * @param mixed $request        REST request.
	 * @return mixed
	 */
	public static function validate_rest_write( $prepared_post, $request ) {
		if ( is_wp_error( $prepared_post ) || ! is_object( $prepared_post ) ) {
			return $prepared_post;
		}

		$target_status = strtolower( (string) ( $prepared_post->post_status ?? '' ) );
		if ( '' === $target_status && is_object( $request ) && method_exists( $request, 'get_param' ) ) {
			$post_id = (int) $request->get_param( 'id' );
			$post    = $post_id > 0 ? get_post( $post_id ) : null;
			if ( is_object( $post ) ) {
				$target_status = strtolower( (string) ( $post->post_status ?? '' ) );
			}
		}

		$validation = self::validate_content(
			(string) ( $prepared_post->post_content ?? '' ),
			'publish' === $target_status
		);
		return true === $validation ? $prepared_post : $validation;
	}

	/**
	 * Validate one proposed page body.
	 *
	 * Plain classic content is outside this Module. Once a body declares block
	 * markup, every named block must still be registered and its block comments
	 * must be balanced. A featured image may not be nested inside a GenerateBlocks
	 * anchor element: that is the editor-invalid structure found on Plugins.
	 *
	 * @param string $content Proposed post content.
	 * @return true|WP_Error
	 */
	public static function validate_content( string $content, bool $require_published_styles = false ) {
		if ( ! self::contains_block_markup( $content ) ) {
			return true;
		}

		if ( ! function_exists( 'parse_blocks' ) ) {
			return self::error(
				'generateblocks_content_guard_unavailable',
				'Page save blocked because WordPress cannot validate its block structure.'
			);
		}

		$comment_issues = self::block_comment_issues( $content );
		if ( $comment_issues ) {
			return self::error(
				'generateblocks_block_comments_invalid',
				'Page save blocked because the block markup is incomplete or mismatched.',
				$comment_issues
			);
		}

		$issues = array();
		$global_classes = array();
		self::walk_blocks( parse_blocks( $content ), array(), $issues, array(), $global_classes );
		if ( $issues ) {
			return self::error(
				'generateblocks_invalid_editor_content',
				'Page save blocked because Gutenberg contains a block structure it cannot edit safely.',
				$issues
			);
		}

		$missing_global_styles = self::missing_global_styles( $global_classes, $require_published_styles );
		if ( $missing_global_styles ) {
			return self::error(
				'generateblocks_global_styles_missing',
				'Page save blocked because one or more GenerateBlocks Global Styles are missing or unusable.',
				$missing_global_styles
			);
		}

		return true;
	}

	private static function contains_block_markup( string $content ): bool {
		return false !== strpos( $content, '<!-- wp:' ) || false !== strpos( $content, 'generateblocks/' );
	}

	/**
	 * @param array<int,array<string,mixed>> $blocks
	 * @param array<int,int>                 $path
	 * @param array<int,array<string,mixed>> $issues
	 * @param array<int,array{name:string,attrs:array<string,mixed>}> $ancestors
	 * @param string[]                       $global_classes
	 */
	private static function walk_blocks( array $blocks, array $path, array &$issues, array $ancestors, array &$global_classes ): void {
		foreach ( $blocks as $index => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name = (string) ( $block['blockName'] ?? '' );
			if ( '' !== $name && ! self::is_registered_block( $name ) ) {
				$issues[] = array(
					'code'  => 'unregistered_block',
					'block' => $name,
					'path'  => array_merge( $path, array( (int) $index ) ),
				);
			}

			if ( self::is_featured_image_inside_anchor( $block, $ancestors ) ) {
				$issues[] = array(
					'code'  => 'featured_image_nested_in_anchor',
					'block' => 'core/post-featured-image',
					'path'  => array_merge( $path, array( (int) $index ) ),
				);
			}

			if ( 0 === strpos( $name, 'generateblocks/' ) ) {
				foreach ( (array) ( $block['attrs']['globalClasses'] ?? array() ) as $global_class ) {
					if ( is_scalar( $global_class ) ) {
						$global_class = trim( (string) $global_class );
						if ( '' !== $global_class ) {
							$global_classes[] = ltrim( $global_class, '.' );
						}
					}
				}
			}

			$children = is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : array();
			self::walk_blocks(
				$children,
				array_merge( $path, array( (int) $index ) ),
				$issues,
				array_merge(
					$ancestors,
					array(
						array(
							'name'  => $name,
							'attrs' => is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array(),
						),
					)
				),
				$global_classes
			);
		}
	}

	/**
	 * Return referenced GenerateBlocks class names that have no native style.
	 *
	 * @param string[] $global_classes Class names from GenerateBlocks blocks.
	 * @return string[]
	 */
	private static function missing_global_styles( array $global_classes, bool $require_published_styles ): array {
		$global_classes = array_values( array_unique( array_filter( $global_classes ) ) );
		if ( empty( $global_classes ) ) {
			return array();
		}

		if ( ! class_exists( 'MCP_Abilities_GeneratePress_GenerateBlocks_Global_Styles' ) ) {
			return $global_classes;
		}

		$existing = array();
		foreach ( MCP_Abilities_GeneratePress_GenerateBlocks_Global_Styles::get_all() as $style ) {
			$selector = is_array( $style ) ? (string) ( $style['selector'] ?? '' ) : '';
			$status   = is_array( $style ) ? (string) ( $style['status'] ?? '' ) : '';
			$css      = is_array( $style ) ? trim( (string) ( $style['css'] ?? '' ) ) : '';
			if ( '' === $css || ( $require_published_styles && 'publish' !== $status ) ) {
				continue;
			}

			if ( preg_match_all( '/\.([A-Za-z_][A-Za-z0-9_-]*)/', $selector, $matches ) ) {
				foreach ( $matches[1] as $class_name ) {
					$existing[] = (string) $class_name;
				}
			}
		}

		return array_values( array_diff( $global_classes, array_unique( $existing ) ) );
	}

	/** @param array<string,mixed> $block @param array<int,array{name:string,attrs:array<string,mixed>}> $ancestors */
	private static function is_featured_image_inside_anchor( array $block, array $ancestors ): bool {
		if ( 'core/post-featured-image' !== (string) ( $block['blockName'] ?? '' ) ) {
			return false;
		}

		foreach ( $ancestors as $ancestor ) {
			if (
				'generateblocks/element' === (string) ( $ancestor['name'] ?? '' )
				&& 'a' === strtolower( (string) ( $ancestor['attrs']['tagName'] ?? '' ) )
			) {
				return true;
			}
		}

		return false;
	}

	/** @return true */
	private static function is_registered_block( string $name ): bool {
		if ( class_exists( 'WP_Block_Type_Registry' ) ) {
			$registry = WP_Block_Type_Registry::get_instance();
			return $registry->is_registered( $name );
		}
		if ( function_exists( 'get_block_type' ) ) {
			return null !== get_block_type( $name );
		}

		return false;
	}

	/** @return array<int,string> */
	private static function block_comment_issues( string $content ): array {
		$issues = array();
		$stack  = array();
		preg_match_all(
			'/<!--\s*(\/?)wp:([a-z0-9_-]+\/[a-z0-9_-]+)\b[^>]*-->/i',
			$content,
			$matches,
			PREG_SET_ORDER
		);

		foreach ( $matches as $match ) {
			$is_closing = '/' === (string) ( $match[1] ?? '' );
			$name       = (string) ( $match[2] ?? '' );
			$token      = (string) ( $match[0] ?? '' );
			if ( $is_closing ) {
				$open = array_pop( $stack );
				if ( $name !== $open ) {
					$issues[] = 'mismatched_block_comment:' . $name;
				}
				continue;
			}
			if ( ! preg_match( '/\/\s*-->$/', $token ) ) {
				$stack[] = $name;
			}
		}

		foreach ( array_reverse( $stack ) as $name ) {
			$issues[] = 'unclosed_block_comment:' . $name;
		}

		return array_values( array_unique( $issues ) );
	}

	/** @param array<int,string> $issues */
	private static function error( string $code, string $message, array $issues = array() ) {
		return new WP_Error( $code, $message, array( 'issues' => $issues ) );
	}
}
