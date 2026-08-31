<?php
/**
 * GenerateBlocks design-context Adapter.
 *
 * @package MCP_Abilities_GeneratePress
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Supply native GenerateBlocks Global Style CSS to static block design analysis. */
final class MCP_Abilities_GeneratePress_GenerateBlocks_Design_Context {
	/** Register the provider-neutral design-context seam. */
	public static function register(): void {
		add_filter( 'mcp_block_editor_design_context', array( __CLASS__, 'supply' ), 10, 2 );
	}

	/**
	 * Append published native Global Style CSS without interpreting page design.
	 *
	 * @param mixed               $context Existing design context.
	 * @param array<string,mixed> $request Block Editor design request.
	 * @return array<string,mixed>
	 */
	public static function supply( $context, array $request ): array {
		unset( $request );
		$context = is_array( $context ) ? $context : array();
		$stylesheets = is_array( $context['stylesheets'] ?? null ) ? $context['stylesheets'] : array();

		if ( ! class_exists( 'MCP_Abilities_GeneratePress_GenerateBlocks_Global_Styles' ) ) {
			$context['stylesheets'] = $stylesheets;
			return $context;
		}

		$known_css = array();
		foreach ( $stylesheets as $stylesheet ) {
			if ( is_array( $stylesheet ) && '' !== trim( (string) ( $stylesheet['css'] ?? '' ) ) ) {
				$known_css[ hash( 'sha256', (string) $stylesheet['css'] ) ] = true;
			}
		}

		foreach ( MCP_Abilities_GeneratePress_GenerateBlocks_Global_Styles::get_all() as $style ) {
			if ( ! is_array( $style ) || 'publish' !== (string) ( $style['status'] ?? '' ) ) {
				continue;
			}

			$css = trim( (string) ( $style['css'] ?? '' ) );
			if ( '' === $css || isset( $known_css[ hash( 'sha256', $css ) ] ) ) {
				continue;
			}

			$style_id = absint( $style['id'] ?? 0 );
			$stylesheets[] = array(
				'source' => $style_id > 0 ? 'generateblocks-global-style-' . $style_id : 'generateblocks-global-style',
				'css'    => $css,
			);
			$known_css[ hash( 'sha256', $css ) ] = true;
		}

		$context['stylesheets'] = $stylesheets;
		return $context;
	}
}
