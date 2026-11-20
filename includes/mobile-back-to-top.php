<?php
/**
 * Render an in-flow mobile link from a GeneratePress footer Element.
 *
 * @package MCP_Abilities_GeneratePress
 */

defined( 'ABSPATH' ) || exit;

function mcp_abilities_generatepress_hide_floating_back_to_top_on_mobile( string $markup ): string {
	$element = new WP_HTML_Tag_Processor( $markup );
	if ( $element->next_tag( array( 'class_name' => 'generate-back-to-top' ) ) ) {
		$element->add_class( 'hide-on-mobile' );
	}
	return $element->get_updated_html();
}

function mcp_abilities_generatepress_mobile_back_to_top_shortcode(): string {
	if ( ! function_exists( 'generate_back_to_top' ) ) {
		return '';
	}

	ob_start();
	generate_back_to_top();
	$element = new WP_HTML_Tag_Processor( (string) ob_get_clean() );
	if ( ! $element->next_tag( array( 'class_name' => 'generate-back-to-top' ) ) ) {
		return '';
	}

	$label = $element->get_attribute( 'aria-label' );
	$href  = $element->get_attribute( 'href' );
	$href  = is_string( $href ) ? esc_url( $href ) : '';
	if ( ! is_string( $label ) || '' === trim( $label ) || '' === $href ) {
		return '';
	}

	add_filter( 'generate_back_to_top_output', 'mcp_abilities_generatepress_hide_floating_back_to_top_on_mobile' );
	return '<a class="hide-on-tablet hide-on-desktop" href="' . $href . '">' . esc_html( $label ) . '</a>';
}

add_shortcode( 'generatepress_mobile_back_to_top', 'mcp_abilities_generatepress_mobile_back_to_top_shortcode' );
