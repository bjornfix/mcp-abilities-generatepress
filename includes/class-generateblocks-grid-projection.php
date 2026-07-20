<?php
/**
 * Direction-aware native GenerateBlocks grid projection.
 *
 * @package MCP_Abilities_GeneratePress
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns one reusable GenerateBlocks layout and generated-CSS lifecycle Interface.
 */
final class MCP_Abilities_GeneratePress_GenerateBlocks_Grid_Projection {
	/** Register the optional frontend, Workflow, and cache Adapters. */
	public static function register(): void {
		add_filter( 'generateblocks_do_content', array( __CLASS__, 'project_frontend_content' ), 30 );
		add_filter( 'render_block_data', array( __CLASS__, 'project_rendered_block' ), 10, 3 );
		add_filter( 'devenia_workflow_project_block_layout', array( __CLASS__, 'project_workflow_layout' ), 10, 4 );
		add_action( 'devenia_workflow_source_design_reprojected', array( __CLASS__, 'invalidate_reprojected_post_css' ) );
		add_action( 'save_post', array( __CLASS__, 'mark_saved_post_for_css_regeneration' ), 100, 1 );
	}

	/** Whether the installed site presentation owner activates this global policy. */
	private static function enabled(): bool {
		return (bool) apply_filters( 'mcp_abilities_generatepress_enable_grid_layout_projection', false );
	}

	/**
	 * Deep Interface: project one target tree from one authoritative source tree.
	 *
	 * @param array<int,array<string,mixed>> $blocks Target blocks.
	 * @param array<int,array<string,mixed>> $source_blocks Source blocks.
	 * @return array<int,array<string,mixed>>
	 */
	public static function project( array $blocks, array $source_blocks, bool $is_rtl ): array {
		foreach ( $blocks as $index => $block ) {
			$source_block = $source_blocks[ $index ] ?? null;
			if ( ! is_array( $block ) || ! is_array( $source_block ) ) {
				continue;
			}

			$block = self::project_grid( $block, $source_block, $is_rtl );
			if ( isset( $block['innerBlocks'], $source_block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) && is_array( $source_block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::project( $block['innerBlocks'], $source_block['innerBlocks'], $is_rtl );
			}
			$blocks[ $index ] = $block;
		}

		return $blocks;
	}

	/** Project content before GenerateBlocks parses it for dynamic CSS. */
	public static function project_frontend_content( string $content ): string {
		if ( is_admin() || ! self::enabled() || false === strpos( $content, '<!-- wp:generateblocks/grid' ) ) {
			return $content;
		}

		$blocks = parse_blocks( $content );
		return serialize_blocks( self::project( $blocks, $blocks, function_exists( 'is_rtl' ) && is_rtl() ) );
	}

	/** Apply the identical projection to WordPress' rendered block tree. */
	public static function project_rendered_block( array $parsed_block, array $source_block, $parent_block = null ): array {
		unset( $parent_block );
		if ( is_admin() || ! self::enabled() || 'generateblocks/grid' !== (string) ( $parsed_block['blockName'] ?? '' ) ) {
			return $parsed_block;
		}

		$projected = self::project(
			array( $parsed_block ),
			array( $source_block ),
			function_exists( 'is_rtl' ) && is_rtl()
		);
		return $projected[0] ?? $parsed_block;
	}

	/** Supply the same Interface to Workflow's source-to-target publication seam. */
	public static function project_workflow_layout( array $blocks, array $source_blocks, string $language, bool $is_rtl ): array {
		unset( $language );
		return self::enabled() ? self::project( $blocks, $source_blocks, $is_rtl ) : $blocks;
	}

	/** Invalidate one post's generated CSS after Workflow changes native design attrs. */
	public static function invalidate_reprojected_post_css( int $post_id ): void {
		if ( ! defined( 'GENERATEBLOCKS_VERSION' ) ) {
			return;
		}

		$css_file = mcp_abilities_generatepress_generateblocks_css_path( $post_id );
		if ( file_exists( $css_file ) ) {
			wp_delete_file( $css_file );
		}
		self::mark_post_for_regeneration( $post_id );
	}

	/** Preserve GenerateBlocks' cache contract for capability-less server-side saves. */
	public static function mark_saved_post_for_css_regeneration( int $post_id ): void {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! defined( 'GENERATEBLOCKS_VERSION' ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || false === strpos( (string) $post->post_content, 'wp:generateblocks' ) ) {
			return;
		}
		self::mark_post_for_regeneration( $post_id );
	}

	/** @param array<string,mixed> $source_attrs */
	private static function project_grid( array $block, array $source_block, bool $is_rtl ): array {
		if ( 'generateblocks/grid' !== (string) ( $block['blockName'] ?? '' ) || 'generateblocks/grid' !== (string) ( $source_block['blockName'] ?? '' ) ) {
			return $block;
		}

		$source_attrs = is_array( $source_block['attrs'] ?? null ) ? $source_block['attrs'] : array();
		$source_items = is_array( $source_block['innerBlocks'] ?? null ) ? $source_block['innerBlocks'] : array();
		$target_items = is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : array();
		if ( ! $source_items || count( $source_items ) !== count( $target_items ) ) {
			return $block;
		}

		$breakpoints = array(
			'desktop' => array( 'gap' => 'horizontalGap', 'width' => 'width', 'margin_suffix' => '' ),
			'tablet'  => array( 'gap' => 'horizontalGapTablet', 'width' => 'widthTablet', 'margin_suffix' => 'Tablet' ),
			'mobile'  => array( 'gap' => 'horizontalGapMobile', 'width' => 'widthMobile', 'margin_suffix' => 'Mobile' ),
		);
		$gaps        = self::responsive_gaps( $source_attrs );
		if ( ! array_filter( $gaps, static fn( float $gap ): bool => $gap > 0 ) ) {
			return $block;
		}

		$widths       = array();
		$spacing_side = $is_rtl ? 'marginLeft' : 'marginRight';
		foreach ( $source_items as $item_index => $source_item ) {
			$target_item = $target_items[ $item_index ] ?? null;
			if (
				! is_array( $source_item )
				|| ! is_array( $target_item )
				|| 'generateblocks/container' !== (string) ( $source_item['blockName'] ?? '' )
				|| 'generateblocks/container' !== (string) ( $target_item['blockName'] ?? '' )
				|| (int) ( $source_item['attrs']['blockVersion'] ?? 0 ) < 3
			) {
				return $block;
			}

			$spacing = is_array( $source_item['attrs']['spacing'] ?? null ) ? $source_item['attrs']['spacing'] : array();
			foreach ( $breakpoints as $breakpoint ) {
				if ( ! self::spacing_is_zero( $spacing[ $spacing_side . $breakpoint['margin_suffix'] ] ?? '' ) ) {
					return $block;
				}
			}

			$sizing      = is_array( $source_item['attrs']['sizing'] ?? null ) ? $source_item['attrs']['sizing'] : array();
			$item_widths = self::responsive_widths( $sizing );
			if ( null === $item_widths ) {
				return $block;
			}
			$widths[ $item_index ] = $item_widths;
		}

		$block['attrs'] = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		foreach ( $breakpoints as $breakpoint_name => $breakpoint ) {
			$block['attrs'][ $breakpoint['gap'] ] = 0;
			$breakpoint_widths                    = array_column( $widths, $breakpoint_name );
			$row_ends                             = self::row_ends( $breakpoint_widths );
			$margin_key                           = $spacing_side . $breakpoint['margin_suffix'];
			$gap_value                            = self::pixel_value( $gaps[ $breakpoint_name ] );

			foreach ( $block['innerBlocks'] as $item_index => $target_item ) {
				$target_item['attrs']            = is_array( $target_item['attrs'] ?? null ) ? $target_item['attrs'] : array();
				$target_item['attrs']['spacing'] = is_array( $target_item['attrs']['spacing'] ?? null ) ? $target_item['attrs']['spacing'] : array();
				$owns_gutter                    = $gaps[ $breakpoint_name ] > 0 && ! isset( $row_ends[ $item_index ] );
				$target_item['attrs']['spacing'][ $margin_key ] = $owns_gutter ? $gap_value : '0px';
				$block['innerBlocks'][ $item_index ]            = $target_item;
			}
		}

		return $block;
	}

	/** @return array{desktop:float,tablet:float,mobile:float} */
	private static function responsive_gaps( array $attrs ): array {
		$desktop = self::numeric_gap( $attrs['horizontalGap'] ?? null, 0.0 );
		$tablet  = self::numeric_gap( $attrs['horizontalGapTablet'] ?? null, $desktop );
		$mobile  = self::numeric_gap( $attrs['horizontalGapMobile'] ?? null, $tablet );
		return array( 'desktop' => $desktop, 'tablet' => $tablet, 'mobile' => $mobile );
	}

	private static function numeric_gap( $value, float $fallback ): float {
		return is_numeric( $value ) ? max( 0.0, (float) $value ) : $fallback;
	}

	/** @return array{desktop:string,tablet:string,mobile:string}|null */
	private static function responsive_widths( array $sizing ): ?array {
		$desktop = self::percentage_width( $sizing['width'] ?? '' );
		$tablet  = self::percentage_width( $sizing['widthTablet'] ?? '', $desktop );
		$mobile  = self::percentage_width( $sizing['widthMobile'] ?? '', $tablet );
		return null === $desktop || null === $tablet || null === $mobile
			? null
			: array( 'desktop' => $desktop, 'tablet' => $tablet, 'mobile' => $mobile );
	}

	private static function percentage_width( $value, ?string $fallback = null ): ?string {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return $fallback;
		}
		return preg_match( '/^(?:100|[0-9]{1,2}(?:\.[0-9]+)?)%$/', $value ) ? $value : null;
	}

	/** @return array<int,bool> */
	private static function row_ends( array $widths ): array {
		$row_ends = array();
		$row_sum  = 0.0;
		foreach ( $widths as $index => $width ) {
			$value = (float) rtrim( $width, '%' );
			if ( $row_sum > 0 && $row_sum + $value > 100.0001 ) {
				$row_ends[ $index - 1 ] = true;
				$row_sum                = 0.0;
			}
			$row_sum += $value;
			if ( $row_sum >= 99.9999 ) {
				$row_ends[ $index ] = true;
				$row_sum            = 0.0;
			}
		}
		if ( $widths ) {
			$row_ends[ array_key_last( $widths ) ] = true;
		}
		return $row_ends;
	}

	private static function spacing_is_zero( $value ): bool {
		$value = strtolower( trim( (string) $value ) );
		return '' === $value || in_array( $value, array( '0', '0px', '0em', '0rem', '0%' ), true );
	}

	private static function pixel_value( float $value ): string {
		return rtrim( rtrim( number_format( $value, 4, '.', '' ), '0' ), '.' ) . 'px';
	}

	private static function mark_post_for_regeneration( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post || false === strpos( (string) $post->post_content, 'wp:generateblocks' ) ) {
			return;
		}

		update_post_meta( $post_id, '_generateblocks_dynamic_css_version', sanitize_text_field( GENERATEBLOCKS_VERSION ) );
		$known_posts = get_option( 'generateblocks_dynamic_css_posts', array() );
		$known_posts = is_array( $known_posts ) ? $known_posts : array();
		unset( $known_posts[ $post_id ] );
		update_option( 'generateblocks_dynamic_css_posts', $known_posts );
	}
}

