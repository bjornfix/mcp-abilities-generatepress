<?php
/**
 * Project native overlay placement for right-to-left pages.
 *
 * @package MCP_Abilities_GeneratePress
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keep GenerateBlocks anchored overlays on the inward side of the trigger
 * when WordPress renders a right-to-left page.
 *
 * GenerateBlocks Pro stores one physical placement value and its client-side
 * positioning code treats start/end physically. This Adapter keeps the
 * overlay native while letting each overlay opt into a right-to-left value.
 */
final class MCP_Abilities_GeneratePress_GenerateBlocks_Overlay_Projection {
	/**
	 * Meta key used by native GenerateBlocks overlay posts.
	 */
	private const RTL_PLACEMENT_META_KEY = '_devenia_rtl_overlay_placement';

	/**
	 * Register the projection.
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );
		add_filter( 'get_post_metadata', array( __CLASS__, 'project_rtl_placement' ), 10, 5 );
	}

	/**
	 * Register the opt-in metadata on the native overlay post type.
	 */
	public static function register_meta(): void {
		register_post_meta(
			'gblocks_overlay',
			self::RTL_PLACEMENT_META_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_key',
				'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', (int) $post_id );
				},
			)
		);
	}

	/**
	 * Return the opt-in placement when an anchored overlay is rendered in RTL.
	 *
	 * @param mixed  $value     Short-circuit value.
	 * @param int    $object_id Post ID.
	 * @param string $meta_key Requested meta key.
	 * @param bool   $single    Whether a single value is requested.
	 * @param string $meta_type Metadata type.
	 * @return mixed
	 */
	public static function project_rtl_placement( $value, $object_id, $meta_key, $single, $meta_type ) {
		if (
			! is_string( $meta_key )
			|| '_gb_overlay_placement' !== $meta_key
			|| 'post' !== $meta_type
			|| is_admin()
			|| ! function_exists( 'is_rtl' )
			|| ! is_rtl()
			|| ! is_numeric( $object_id )
			|| 'gblocks_overlay' !== get_post_type( (int) $object_id )
		) {
			return $value;
		}

		$rtl_placement = get_post_meta( (int) $object_id, self::RTL_PLACEMENT_META_KEY, true );
		$allowed       = array( 'bottom-start', 'bottom-end', 'top-start', 'top-end', 'left', 'right' );

		if ( ! is_string( $rtl_placement ) || ! in_array( $rtl_placement, $allowed, true ) ) {
			return $value;
		}

		return $single ? $rtl_placement : array( $rtl_placement );
	}
}
