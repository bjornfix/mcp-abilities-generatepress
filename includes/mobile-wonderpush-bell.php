<?php
/**
 * Keep the native WonderPush bell in the document flow on mobile.
 *
 * @package MCP_Abilities_GeneratePress
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( ! function_exists( 'generate_get_media_query' ) ) {
			return;
		}

		$stylesheet = 'mobile-wonderpush-bell.css';
		wp_enqueue_style(
			'mcp-abilities-generatepress-mobile-wonderpush-bell',
			plugins_url( $stylesheet, __FILE__ ),
			array(),
			(string) filemtime( __DIR__ . '/' . $stylesheet ),
			generate_get_media_query( 'mobile' )
		);
	}
);

add_shortcode(
	'generatepress_mobile_wonderpush_bell',
	static function (): string {
		return '<span hidden data-generatepress-inline-wonderpush-bell></span>';
	}
);
