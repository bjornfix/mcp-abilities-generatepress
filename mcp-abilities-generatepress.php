<?php
/**
 * Plugin Name: MCP Abilities - GeneratePress
 * Plugin URI: https://github.com/bjornfix/mcp-abilities-generatepress
 * Description: GeneratePress and GenerateBlocks abilities for MCP. Manage theme settings, global colors, typography, and block styles.
 * Version: 1.0.2
 * Author: Devenia
 * Author URI: https://devenia.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires at least: 6.9
 * Requires PHP: 8.0
 * Requires Plugins: abilities-api
 *
 * @package MCP_Abilities_GeneratePress
 */

declare( strict_types=1 );

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if Abilities API is available.
 */
function mcp_generatepress_check_dependencies(): bool {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p><strong>MCP Abilities - GeneratePress</strong> requires the <a href="https://github.com/WordPress/abilities-api">Abilities API</a> plugin to be installed and activated.</p></div>';
		} );
		return false;
	}
	return true;
}

/**
 * Register GeneratePress and GenerateBlocks abilities.
 */
function mcp_register_generatepress_abilities(): void {
	if ( ! mcp_generatepress_check_dependencies() ) {
		return;
	}

	// =========================================================================
	// GENERATEPRESS - Get Settings
	// =========================================================================
	wp_register_ability(
		'generatepress/get-settings',
		array(
			'label'               => 'Get GeneratePress Settings',
			'description'         => 'Retrieves GeneratePress theme settings including colors, typography, layout, and global styles.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'section' => array(
						'type'        => 'string',
						'enum'        => array( 'all', 'colors', 'typography', 'layout', 'buttons', 'site_identity' ),
						'default'     => 'all',
						'description' => 'Which settings section to retrieve.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'settings' => array( 'type' => 'object' ),
					'message'  => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$input = is_array( $input ) ? $input : array();

				$settings       = get_option( 'generate_settings', array() );
				$global_colors  = get_option( 'generate_global_colors', array() );

				if ( empty( $settings ) && empty( $global_colors ) ) {
					return array(
						'success' => false,
						'message' => 'GeneratePress settings not found - is the theme active?',
					);
				}

				$section = $input['section'] ?? 'all';

				// Color-related settings keys.
				$color_keys = array(
					'global_colors', 'background_color', 'text_color', 'link_color', 'link_color_hover',
					'header_background_color', 'header_text_color', 'header_link_color',
					'navigation_background_color', 'navigation_text_color', 'navigation_background_hover',
					'sidebar_widget_title_color', 'sidebar_widget_text_color',
					'footer_background_color', 'footer_text_color', 'footer_link_color',
					'entry_meta_link_color', 'entry_meta_link_color_hover',
				);

				// Typography keys.
				$typo_keys = array(
					'font_body', 'body_font_weight', 'body_font_size', 'body_line_height',
					'font_heading_1', 'heading_1_weight', 'heading_1_font_size',
					'font_heading_2', 'heading_2_weight', 'heading_2_font_size',
					'font_heading_3', 'heading_3_weight', 'heading_3_font_size',
					'font_buttons', 'buttons_font_weight', 'buttons_font_size',
				);

				// Layout keys.
				$layout_keys = array(
					'container_width', 'content_layout_setting', 'content_width',
					'sidebar_width', 'sidebar_layout', 'header_layout_setting',
					'footer_widget_setting', 'back_to_top',
				);

				// Button keys.
				$button_keys = array(
					'form_button_background_color', 'form_button_background_color_hover',
					'form_button_text_color', 'form_button_text_color_hover',
					'form_button_border_radius',
				);

				$result = array();

				if ( 'all' === $section || 'colors' === $section ) {
					$result['global_colors'] = $global_colors;
					foreach ( $color_keys as $key ) {
						if ( isset( $settings[ $key ] ) ) {
							$result['colors'][ $key ] = $settings[ $key ];
						}
					}
				}

				if ( 'all' === $section || 'typography' === $section ) {
					foreach ( $typo_keys as $key ) {
						if ( isset( $settings[ $key ] ) ) {
							$result['typography'][ $key ] = $settings[ $key ];
						}
					}
				}

				if ( 'all' === $section || 'layout' === $section ) {
					foreach ( $layout_keys as $key ) {
						if ( isset( $settings[ $key ] ) ) {
							$result['layout'][ $key ] = $settings[ $key ];
						}
					}
				}

				if ( 'all' === $section || 'buttons' === $section ) {
					foreach ( $button_keys as $key ) {
						if ( isset( $settings[ $key ] ) ) {
							$result['buttons'][ $key ] = $settings[ $key ];
						}
					}
				}

				if ( 'all' === $section ) {
					$result['all_settings'] = $settings;
				}

				return array(
					'success'  => true,
					'settings' => $result,
					'message'  => 'GeneratePress settings retrieved successfully',
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_theme_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	// =========================================================================
	// GENERATEPRESS - Update Settings
	// =========================================================================
	wp_register_ability(
		'generatepress/update-settings',
		array(
			'label'               => 'Update GeneratePress Settings',
			'description'         => 'Updates GeneratePress theme settings. Merges with existing settings - only provided keys are updated.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'settings' ),
				'properties'           => array(
					'settings' => array(
						'type'        => 'object',
						'description' => 'Settings to update (merged with existing).',
					),
					'global_colors' => array(
						'type'        => 'array',
						'description' => 'Global colors array to update.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$input = is_array( $input ) ? $input : array();

				if ( empty( $input['settings'] ) && empty( $input['global_colors'] ) ) {
					return array( 'success' => false, 'message' => 'No settings provided to update' );
				}

				if ( ! empty( $input['settings'] ) ) {
					$current = get_option( 'generate_settings', array() );
					$updated = array_merge( $current, $input['settings'] );
					update_option( 'generate_settings', $updated );
				}

				if ( ! empty( $input['global_colors'] ) ) {
					update_option( 'generate_global_colors', $input['global_colors'] );
				}

				return array(
					'success' => true,
					'message' => 'GeneratePress settings updated successfully',
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_theme_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	// =========================================================================
	// GENERATEBLOCKS - Get Global Styles
	// =========================================================================
	wp_register_ability(
		'generateblocks/get-global-styles',
		array(
			'label'               => 'Get GenerateBlocks Global Styles',
			'description'         => 'Retrieves GenerateBlocks global styles and default settings.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'include_defaults' => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'Include default settings in response.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'       => array( 'type' => 'boolean' ),
					'global_styles' => array( 'type' => 'array' ),
					'defaults'      => array( 'type' => 'object' ),
					'message'       => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$global_styles = get_option( 'generateblocks_global_styles', array() );
				$defaults      = get_option( 'generateblocks_defaults', array() );
				$settings      = get_option( 'generateblocks', array() );

				return array(
					'success'       => true,
					'global_styles' => $global_styles,
					'defaults'      => $defaults,
					'settings'      => $settings,
					'message'       => 'GenerateBlocks settings retrieved successfully',
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_theme_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	// =========================================================================
	// GENERATEBLOCKS - Update Global Styles
	// =========================================================================
	wp_register_ability(
		'generateblocks/update-global-styles',
		array(
			'label'               => 'Update GenerateBlocks Global Styles',
			'description'         => 'Updates GenerateBlocks global styles. Replaces entire global styles array.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'global_styles' => array(
						'type'        => 'array',
						'description' => 'Complete global styles array to save.',
					),
					'defaults' => array(
						'type'        => 'object',
						'description' => 'Default settings object to save.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$input = is_array( $input ) ? $input : array();

				if ( empty( $input['global_styles'] ) && empty( $input['defaults'] ) ) {
					return array( 'success' => false, 'message' => 'No styles or defaults provided to update' );
				}

				if ( isset( $input['global_styles'] ) ) {
					update_option( 'generateblocks_global_styles', $input['global_styles'] );
				}

				if ( isset( $input['defaults'] ) ) {
					update_option( 'generateblocks_defaults', $input['defaults'] );
				}

				return array(
					'success' => true,
					'message' => 'GenerateBlocks settings updated successfully',
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_theme_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	// =========================================================================
	// GENERATEPRESS - Update Page Meta
	// =========================================================================
	wp_register_ability(
		'generatepress/update-page-meta',
		array(
			'label'               => 'Update GeneratePress Page Meta',
			'description'         => 'Updates GeneratePress page-specific settings like disabling title, sidebar layout, content width, navigation, and footer.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'id' ),
				'properties'           => array(
					'id' => array(
						'type'        => 'integer',
						'description' => 'Post or page ID to update.',
					),
					'disable_headline' => array(
						'type'        => 'boolean',
						'description' => 'Disable the page/post title.',
					),
					'disable_nav' => array(
						'type'        => 'boolean',
						'description' => 'Disable primary navigation.',
					),
					'disable_footer' => array(
						'type'        => 'boolean',
						'description' => 'Disable site footer.',
					),
					'disable_footer_widgets' => array(
						'type'        => 'boolean',
						'description' => 'Disable footer widgets.',
					),
					'sidebar_layout' => array(
						'type'        => 'string',
						'enum'        => array( '', 'right-sidebar', 'left-sidebar', 'no-sidebar', 'both-sidebars', 'both-left', 'both-right' ),
						'description' => 'Sidebar layout for this page.',
					),
					'content_area' => array(
						'type'        => 'string',
						'enum'        => array( '', 'full-width', 'contained', 'full-width-content' ),
						'description' => 'Content area style.',
					),
					'transparent_header' => array(
						'type'        => 'boolean',
						'description' => 'Use transparent header on this page.',
					),
					'sticky_header' => array(
						'type'        => 'boolean',
						'description' => 'Use sticky header on this page.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'updated' => array( 'type' => 'array' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$input = is_array( $input ) ? $input : array();

				if ( empty( $input['id'] ) ) {
					return array( 'success' => false, 'message' => 'Post ID is required' );
				}

				$post_id = intval( $input['id'] );
				$post    = get_post( $post_id );

				if ( ! $post ) {
					return array( 'success' => false, 'message' => "Post {$post_id} not found" );
				}

				$updated = array();

				// Meta key mappings.
				$meta_map = array(
					'disable_headline'       => '_generate-disable-headline',
					'disable_nav'            => '_generate-disable-nav',
					'disable_footer'         => '_generate-disable-footer',
					'disable_footer_widgets' => '_generate-disable-footer-widgets',
					'sidebar_layout'         => '_generate-sidebar-layout-meta',
					'content_area'           => '_generate-content-area-meta',
					'transparent_header'     => '_generate-transparent-header',
					'sticky_header'          => '_generate-sticky-navigation-meta',
				);

				foreach ( $meta_map as $input_key => $meta_key ) {
					if ( isset( $input[ $input_key ] ) ) {
						$value = $input[ $input_key ];

						// Boolean fields: store 'true' string or delete.
						if ( is_bool( $value ) ) {
							if ( $value ) {
								update_post_meta( $post_id, $meta_key, 'true' );
								$updated[] = "{$input_key} = true";
							} else {
								delete_post_meta( $post_id, $meta_key );
								$updated[] = "{$input_key} = false (removed)";
							}
						} else {
							// String fields.
							if ( '' === $value ) {
								delete_post_meta( $post_id, $meta_key );
								$updated[] = "{$input_key} = '' (removed)";
							} else {
								update_post_meta( $post_id, $meta_key, sanitize_text_field( $value ) );
								$updated[] = "{$input_key} = {$value}";
							}
						}
					}
				}

				if ( empty( $updated ) ) {
					return array( 'success' => false, 'message' => 'No valid settings provided to update' );
				}

				return array(
					'success' => true,
					'updated' => $updated,
					'message' => 'GeneratePress page meta updated for post ' . $post_id,
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_theme_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	// =========================================================================
	// GENERATEBLOCKS - Clear CSS Cache
	// =========================================================================
	wp_register_ability(
		'generateblocks/clear-cache',
		array(
			'label'               => 'Clear GenerateBlocks Cache',
			'description'         => 'Clears GenerateBlocks CSS cache by deleting generated CSS files.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'confirm' => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'Confirm cache clear operation.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'deleted' => array( 'type' => 'integer' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$upload_dir = wp_upload_dir();
				$css_dir    = $upload_dir['basedir'] . '/generateblocks/';
				$deleted    = 0;

				if ( is_dir( $css_dir ) ) {
					$files = glob( $css_dir . '*.css' );
					if ( $files ) {
						foreach ( $files as $file ) {
							if ( wp_delete_file( $file ) ) {
								$deleted++;
							}
						}
					}
				}

				// Also delete the CSS version option to force regeneration.
				delete_option( 'generateblocks_css_version' );

				return array(
					'success' => true,
					'deleted' => $deleted,
					'message' => "Cleared {$deleted} GenerateBlocks CSS file(s)",
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_theme_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);
}
add_action( 'wp_abilities_api_init', 'mcp_register_generatepress_abilities' );
