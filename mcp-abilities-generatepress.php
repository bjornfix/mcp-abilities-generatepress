<?php
/**
 * Plugin Name: MCP Abilities - GeneratePress
 * Plugin URI: https://github.com/bjornfix/mcp-abilities-generatepress
 * Description: GeneratePress and GenerateBlocks abilities for MCP. Manage theme settings, elements, global styles, page meta, and caches.
 * Version: 1.1.0
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
function mcp_abilities_generatepress_check_dependencies(): bool {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p><strong>MCP Abilities - GeneratePress</strong> requires the <a href="https://github.com/WordPress/abilities-api">Abilities API</a> plugin to be installed and activated.</p></div>';
		} );
		return false;
	}
	return true;
}

/**
 * Get GeneratePress theme details.
 */
function mcp_abilities_generatepress_get_theme_info(): array {
	$theme     = wp_get_theme();
	$parent    = $theme->parent();
	$template  = $theme->get_template();
	$stylesheet = $theme->get_stylesheet();

	$is_generatepress = ( 'generatepress' === $template || 'generatepress' === $stylesheet );
	if ( $parent && 'generatepress' === $parent->get_template() ) {
		$is_generatepress = true;
	}

	return array(
		'name'             => $theme->get( 'Name' ),
		'version'          => $theme->get( 'Version' ),
		'template'         => $template,
		'stylesheet'       => $stylesheet,
		'is_child'         => (bool) $parent,
		'parent_name'      => $parent ? $parent->get( 'Name' ) : '',
		'parent_version'   => $parent ? $parent->get( 'Version' ) : '',
		'is_generatepress' => $is_generatepress,
	);
}

/**
 * Allowed option prefixes for GeneratePress/GenerateBlocks.
 */
function mcp_abilities_generatepress_allowed_option_prefixes(): array {
	return array(
		'generate_',
		'gp_',
		'generatepress_',
		'generateblocks_',
	);
}

/**
 * Allowed explicit option names for theme mods.
 */
function mcp_abilities_generatepress_allowed_option_names(): array {
	$names      = array();
	$theme_info = mcp_abilities_generatepress_get_theme_info();

	if ( ! empty( $theme_info['stylesheet'] ) ) {
		$names[] = 'theme_mods_' . $theme_info['stylesheet'];
	}
	if ( ! empty( $theme_info['template'] ) && $theme_info['template'] !== $theme_info['stylesheet'] ) {
		$names[] = 'theme_mods_' . $theme_info['template'];
	}

	return $names;
}

/**
 * Check if option name is allowed for GeneratePress abilities.
 */
function mcp_abilities_generatepress_is_allowed_option_name( string $name ): bool {
	if ( in_array( $name, mcp_abilities_generatepress_allowed_option_names(), true ) ) {
		return true;
	}

	foreach ( mcp_abilities_generatepress_allowed_option_prefixes() as $prefix ) {
		if ( str_starts_with( $name, $prefix ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Check if a meta key is allowed for GeneratePress elements.
 */
function mcp_abilities_generatepress_is_allowed_meta_key( string $key ): bool {
	return str_starts_with( $key, '_generate_' );
}

/**
 * Default meta keys for GeneratePress elements.
 */
function mcp_abilities_generatepress_default_element_meta_keys(): array {
	return array(
		'_generate_element_type',
		'_generate_element_content',
		'_generate_hook_type',
		'_generate_hook',
		'_generate_custom_hook',
		'_generate_hook_priority',
		'_generate_hook_execute_php',
		'_generate_element_display_conditions',
		'_generate_element_exclude_conditions',
		'_generate_element_user_conditions',
	);
}

/**
 * Register GeneratePress and GenerateBlocks abilities.
 */
function mcp_abilities_generatepress_register_abilities(): void {
	if ( ! mcp_abilities_generatepress_check_dependencies() ) {
		return;
	}

	// =========================================================================
	// GENERATEPRESS - Get Theme Info
	// =========================================================================
	wp_register_ability(
		'generatepress/get-info',
		array(
			'label'               => 'Get GeneratePress Theme Info',
			'description'         => 'Get active theme information and GeneratePress Premium status.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'         => array( 'type' => 'boolean' ),
					'theme'           => array( 'type' => 'object' ),
					'premium_active'  => array( 'type' => 'boolean' ),
					'premium_version' => array( 'type' => 'string' ),
					'message'         => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function (): array {
				$theme_info     = mcp_abilities_generatepress_get_theme_info();
				$premium_active = class_exists( 'GP_Premium' ) || defined( 'GP_PREMIUM_VERSION' );
				$premium_version = defined( 'GP_PREMIUM_VERSION' ) ? GP_PREMIUM_VERSION : '';

				return array(
					'success'         => true,
					'theme'           => $theme_info,
					'premium_active'  => $premium_active,
					'premium_version' => $premium_version,
					'message'         => 'Theme info retrieved successfully',
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
	// GENERATEPRESS - Clear Cache
	// =========================================================================
	wp_register_ability(
		'generatepress/clear-cache',
		array(
			'label'               => 'Clear GeneratePress Cache',
			'description'         => 'Clears GeneratePress dynamic CSS cache to force regeneration.',
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
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$confirm = isset( $input['confirm'] ) ? (bool) $input['confirm'] : true;
				if ( ! $confirm ) {
					return array( 'success' => false, 'message' => 'Confirmation required to clear cache.' );
				}

				delete_option( 'generate_dynamic_css_output' );
				delete_option( 'generate_dynamic_css_cached_version' );

				if ( function_exists( 'generate_update_dynamic_css_cache' ) ) {
					generate_update_dynamic_css_cache();
				}

				return array(
					'success' => true,
					'message' => 'GeneratePress cache cleared successfully',
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_theme_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
			),
		)
	);

	// =========================================================================
	// GENERATEPRESS - List Options
	// =========================================================================
	wp_register_ability(
		'generatepress/list-options',
		array(
			'label'               => 'List GeneratePress Options',
			'description'         => 'List GeneratePress/GenerateBlocks options available in wp_options.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'prefixes' => array(
						'type'        => 'array',
						'description' => 'Optional list of prefixes to filter (defaults to GeneratePress/GenerateBlocks prefixes).',
						'items'       => array( 'type' => 'string' ),
					),
					'limit' => array(
						'type'        => 'integer',
						'default'     => 200,
						'minimum'     => 1,
						'maximum'     => 500,
						'description' => 'Maximum options to return.',
					),
					'offset' => array(
						'type'        => 'integer',
						'default'     => 0,
						'minimum'     => 0,
						'description' => 'Offset for pagination.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'       => array( 'type' => 'boolean' ),
					'options'       => array( 'type' => 'array' ),
					'used_prefixes' => array( 'type' => 'array' ),
					'message'       => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				global $wpdb;

				$prefixes = isset( $input['prefixes'] ) && is_array( $input['prefixes'] )
					? array_values( array_filter( $input['prefixes'], 'is_string' ) )
					: mcp_abilities_generatepress_allowed_option_prefixes();

				$allowed_prefixes = mcp_abilities_generatepress_allowed_option_prefixes();
				$prefixes         = array_values( array_intersect( $prefixes, $allowed_prefixes ) );
				if ( empty( $prefixes ) ) {
					$prefixes = $allowed_prefixes;
				}

				$limit  = isset( $input['limit'] ) ? max( 1, min( 500, (int) $input['limit'] ) ) : 200;
				$offset = isset( $input['offset'] ) ? max( 0, (int) $input['offset'] ) : 0;

				$conditions = array();

				foreach ( $prefixes as $prefix ) {
					$conditions[] = $wpdb->prepare(
						'option_name LIKE %s',
						$wpdb->esc_like( $prefix ) . '%'
					);
				}

				$allowed_names = mcp_abilities_generatepress_allowed_option_names();
				if ( ! empty( $allowed_names ) ) {
					$escaped_names = array_map( 'esc_sql', $allowed_names );
					$conditions[]  = 'option_name IN (\'' . implode( '\',\'', $escaped_names ) . '\')';
				}

				if ( empty( $conditions ) ) {
					return array(
						'success' => false,
						'message' => 'No allowed prefixes available to query.',
					);
				}

				$limit  = (int) $limit;
				$offset = (int) $offset;

				$query = 'SELECT option_name, autoload FROM ' . $wpdb->options . ' WHERE ' . implode( ' OR ', $conditions ) . ' ORDER BY option_name ASC LIMIT ' . $limit . ' OFFSET ' . $offset;

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Option discovery with prepared conditions.
				$rows = $wpdb->get_results( $query, ARRAY_A );

				return array(
					'success'       => true,
					'options'       => $rows,
					'used_prefixes' => $prefixes,
					'message'       => 'Options listed successfully',
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'manage_options' );
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
	// GENERATEPRESS - Get Options
	// =========================================================================
	wp_register_ability(
		'generatepress/get-options',
		array(
			'label'               => 'Get GeneratePress Options',
			'description'         => 'Get specific GeneratePress/GenerateBlocks options by name.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'options' ),
				'properties'           => array(
					'options' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'Option names to retrieve.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'options' => array( 'type' => 'object' ),
					'missing' => array( 'type' => 'array' ),
					'rejected' => array( 'type' => 'array' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$names = isset( $input['options'] ) && is_array( $input['options'] ) ? $input['options'] : array();
				if ( empty( $names ) ) {
					return array( 'success' => false, 'message' => 'No option names provided.' );
				}

				$marker  = 'mcp_missing_' . wp_generate_password( 12, false );
				$results = array();
				$missing = array();
				$rejected = array();

				foreach ( $names as $name ) {
					if ( ! is_string( $name ) || '' === $name ) {
						continue;
					}
					if ( ! mcp_abilities_generatepress_is_allowed_option_name( $name ) ) {
						$rejected[] = $name;
						continue;
					}

					$value = get_option( $name, $marker );
					if ( $value === $marker ) {
						$missing[] = $name;
						continue;
					}

					$results[ $name ] = $value;
				}

				return array(
					'success' => true,
					'options' => $results,
					'missing' => $missing,
					'rejected' => $rejected,
					'message' => 'Options retrieved successfully',
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'manage_options' );
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
	// GENERATEPRESS - Update Options
	// =========================================================================
	wp_register_ability(
		'generatepress/update-options',
		array(
			'label'               => 'Update GeneratePress Options',
			'description'         => 'Update or delete GeneratePress/GenerateBlocks options by name.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'updates' => array(
						'type'        => 'object',
						'description' => 'Map of option names to values. Use null to delete.',
					),
					'deletes' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'Option names to delete.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'updated'  => array( 'type' => 'array' ),
					'deleted'  => array( 'type' => 'array' ),
					'rejected' => array( 'type' => 'array' ),
					'message'  => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$updates = isset( $input['updates'] ) && is_array( $input['updates'] ) ? $input['updates'] : array();
				$deletes = isset( $input['deletes'] ) && is_array( $input['deletes'] ) ? $input['deletes'] : array();

				if ( empty( $updates ) && empty( $deletes ) ) {
					return array( 'success' => false, 'message' => 'No updates or deletes provided.' );
				}

				$updated  = array();
				$deleted  = array();
				$rejected = array();

				foreach ( $updates as $name => $value ) {
					if ( ! is_string( $name ) || '' === $name ) {
						continue;
					}
					if ( ! mcp_abilities_generatepress_is_allowed_option_name( $name ) ) {
						$rejected[] = $name;
						continue;
					}

					if ( null === $value ) {
						delete_option( $name );
						$deleted[] = $name;
						continue;
					}

					update_option( $name, $value );
					$updated[] = $name;
				}

				foreach ( $deletes as $name ) {
					if ( ! is_string( $name ) || '' === $name ) {
						continue;
					}
					if ( ! mcp_abilities_generatepress_is_allowed_option_name( $name ) ) {
						$rejected[] = $name;
						continue;
					}
					delete_option( $name );
					$deleted[] = $name;
				}

				return array(
					'success'  => true,
					'updated'  => $updated,
					'deleted'  => $deleted,
					'rejected' => $rejected,
					'message'  => 'Options updated successfully',
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'manage_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
			),
		)
	);

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
				$global_colors  = isset( $settings['global_colors'] ) ? $settings['global_colors'] : array();

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
					// Global colors must be stored inside generate_settings, not as a separate option.
					// See: https://generatepress.com/forums/topic/changing-global-colors-programatically/
					$current = get_option( 'generate_settings', array() );
					$current['global_colors'] = $input['global_colors'];
					update_option( 'generate_settings', $current );
				}

				// Clear GP's CSS cache to force regeneration from new settings.
				delete_option( 'generate_dynamic_css_output' );
				delete_option( 'generate_dynamic_css_cached_version' );

				// Trigger GP's cache rebuild if the function exists.
				if ( function_exists( 'generate_update_dynamic_css_cache' ) ) {
					generate_update_dynamic_css_cache();
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
			'description'         => 'Retrieves GenerateBlocks global styles, defaults, and settings.',
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
					'settings'      => array( 'type' => 'object' ),
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
			'description'         => 'Updates GenerateBlocks global styles, defaults, and settings. Global styles are replaced entirely.',
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
					'settings' => array(
						'type'        => 'object',
						'description' => 'GenerateBlocks settings object to save.',
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

				if ( empty( $input['global_styles'] ) && empty( $input['defaults'] ) && empty( $input['settings'] ) ) {
					return array( 'success' => false, 'message' => 'No styles, defaults, or settings provided to update' );
				}

				if ( isset( $input['global_styles'] ) ) {
					update_option( 'generateblocks_global_styles', $input['global_styles'] );
				}

				if ( isset( $input['defaults'] ) ) {
					update_option( 'generateblocks_defaults', $input['defaults'] );
				}

				if ( isset( $input['settings'] ) ) {
					update_option( 'generateblocks', $input['settings'] );
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
	// GENERATEPRESS - Get Page Meta
	// =========================================================================
	wp_register_ability(
		'generatepress/get-page-meta',
		array(
			'label'               => 'Get GeneratePress Page Meta',
			'description'         => 'Retrieves GeneratePress page-specific meta values for a post or page.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'id' ),
				'properties'           => array(
					'id' => array(
						'type'        => 'integer',
						'description' => 'Post or page ID to retrieve.',
					),
					'meta_keys' => array(
						'type'        => 'array',
						'description' => 'Additional GeneratePress meta keys to include.',
						'items'       => array( 'type' => 'string' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'   => array( 'type' => 'boolean' ),
					'id'        => array( 'type' => 'integer' ),
					'meta'      => array( 'type' => 'object' ),
					'raw_meta'  => array( 'type' => 'object' ),
					'rejected'  => array( 'type' => 'array' ),
					'message'   => array( 'type' => 'string' ),
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

				$meta = array();
				foreach ( $meta_map as $label => $meta_key ) {
					$meta[ $label ] = get_post_meta( $post_id, $meta_key, true );
				}

				$raw_meta = array();
				$rejected = array();
				if ( ! empty( $input['meta_keys'] ) && is_array( $input['meta_keys'] ) ) {
					foreach ( $input['meta_keys'] as $meta_key ) {
						if ( ! is_string( $meta_key ) || '' === $meta_key ) {
							continue;
						}
						if ( ! mcp_abilities_generatepress_is_allowed_meta_key( $meta_key ) ) {
							$rejected[] = $meta_key;
							continue;
						}
						$raw_meta[ $meta_key ] = get_post_meta( $post_id, $meta_key, true );
					}
				}

				return array(
					'success'  => true,
					'id'       => $post_id,
					'meta'     => $meta,
					'raw_meta' => $raw_meta,
					'rejected' => $rejected,
					'message'  => 'GeneratePress page meta retrieved',
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
					'custom_meta' => array(
						'type'        => 'object',
						'description' => 'Additional GeneratePress meta keys to update. Use null to delete.',
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

				if ( ! empty( $input['custom_meta'] ) && is_array( $input['custom_meta'] ) ) {
					foreach ( $input['custom_meta'] as $meta_key => $value ) {
						if ( ! is_string( $meta_key ) || '' === $meta_key ) {
							continue;
						}
						if ( ! mcp_abilities_generatepress_is_allowed_meta_key( $meta_key ) ) {
							continue;
						}

						if ( null === $value ) {
							delete_post_meta( $post_id, $meta_key );
							$updated[] = "{$meta_key} = null (removed)";
							continue;
						}

						update_post_meta( $post_id, $meta_key, $value );
						$updated[] = "{$meta_key} updated";
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
	// GENERATEPRESS - List Elements
	// =========================================================================
	wp_register_ability(
		'generatepress/list-elements',
		array(
			'label'               => 'List GeneratePress Elements',
			'description'         => 'Lists GeneratePress Elements (gp_elements) with optional filters.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'status' => array(
						'type'        => 'string',
						'default'     => 'any',
						'description' => 'Post status filter (publish, draft, any).',
					),
					'element_type' => array(
						'type'        => 'string',
						'description' => 'Filter by element type (hook, block, header, layout, etc).',
					),
					'search' => array(
						'type'        => 'string',
						'description' => 'Search term for element titles.',
					),
					'per_page' => array(
						'type'        => 'integer',
						'default'     => 50,
						'minimum'     => 1,
						'maximum'     => 200,
						'description' => 'Elements per page.',
					),
					'page' => array(
						'type'        => 'integer',
						'default'     => 1,
						'minimum'     => 1,
						'description' => 'Page number.',
					),
					'orderby' => array(
						'type'        => 'string',
						'enum'        => array( 'date', 'modified', 'title', 'menu_order', 'ID' ),
						'default'     => 'modified',
						'description' => 'Order by field.',
					),
					'order' => array(
						'type'        => 'string',
						'enum'        => array( 'ASC', 'DESC' ),
						'default'     => 'DESC',
						'description' => 'Sort direction.',
					),
					'include_meta' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Include element meta fields in response.',
					),
					'include_content' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Include element content in response.',
					),
					'meta_keys' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'Additional meta keys to include when include_meta is true.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'elements' => array( 'type' => 'array' ),
					'total'    => array( 'type' => 'integer' ),
					'pages'    => array( 'type' => 'integer' ),
					'message'  => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				if ( ! post_type_exists( 'gp_elements' ) ) {
					return array(
						'success' => false,
						'message' => 'GeneratePress Elements are not available (gp_elements post type missing).',
					);
				}

				$per_page = isset( $input['per_page'] ) ? max( 1, min( 200, (int) $input['per_page'] ) ) : 50;
				$page     = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
				$status   = isset( $input['status'] ) ? sanitize_text_field( $input['status'] ) : 'any';
				$orderby  = isset( $input['orderby'] ) ? sanitize_text_field( $input['orderby'] ) : 'modified';
				$order    = isset( $input['order'] ) ? sanitize_text_field( $input['order'] ) : 'DESC';
				$search   = isset( $input['search'] ) ? sanitize_text_field( $input['search'] ) : '';

				$args = array(
					'post_type'      => 'gp_elements',
					'post_status'    => $status,
					'posts_per_page' => $per_page,
					'paged'          => $page,
					'orderby'        => $orderby,
					'order'          => $order,
					's'              => $search,
				);

				if ( ! empty( $input['element_type'] ) ) {
					$args['meta_query'] = array(
						array(
							'key'     => '_generate_element_type',
							'value'   => sanitize_text_field( $input['element_type'] ),
							'compare' => '=',
						),
					);
				}

				$query    = new WP_Query( $args );
				$elements = array();

				$include_meta    = ! empty( $input['include_meta'] );
				$include_content = ! empty( $input['include_content'] );
				$meta_keys       = mcp_abilities_generatepress_default_element_meta_keys();

				if ( $include_meta && ! empty( $input['meta_keys'] ) && is_array( $input['meta_keys'] ) ) {
					foreach ( $input['meta_keys'] as $meta_key ) {
						if ( is_string( $meta_key ) && mcp_abilities_generatepress_is_allowed_meta_key( $meta_key ) ) {
							$meta_keys[] = $meta_key;
						}
					}
					$meta_keys = array_values( array_unique( $meta_keys ) );
				}

				foreach ( $query->posts as $post ) {
					$item = array(
						'id'          => $post->ID,
						'title'       => $post->post_title,
						'status'      => $post->post_status,
						'slug'        => $post->post_name,
						'modified_gmt' => $post->post_modified_gmt,
					);

					$element_type = get_post_meta( $post->ID, '_generate_element_type', true );
					if ( '' !== $element_type ) {
						$item['element_type'] = $element_type;
					}

					if ( $include_meta ) {
						$meta = array();
						foreach ( $meta_keys as $meta_key ) {
							if ( ! $include_content && '_generate_element_content' === $meta_key ) {
								continue;
							}
							$meta[ $meta_key ] = get_post_meta( $post->ID, $meta_key, true );
						}
						$item['meta'] = $meta;
					}

					if ( $include_content ) {
						$content = get_post_meta( $post->ID, '_generate_element_content', true );
						if ( '' === $content ) {
							$content = $post->post_content;
						}
						$item['content'] = $content;
					}

					$elements[] = $item;
				}

				return array(
					'success'  => true,
					'elements' => $elements,
					'total'    => (int) $query->found_posts,
					'pages'    => (int) $query->max_num_pages,
					'message'  => 'GeneratePress elements retrieved successfully',
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
	// GENERATEPRESS - Get Element
	// =========================================================================
	wp_register_ability(
		'generatepress/get-element',
		array(
			'label'               => 'Get GeneratePress Element',
			'description'         => 'Retrieves a GeneratePress Element (gp_elements) by ID.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'id' ),
				'properties'           => array(
					'id' => array(
						'type'        => 'integer',
						'description' => 'Element ID.',
					),
					'include_meta' => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'Include element meta fields.',
					),
					'include_content' => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'Include element content.',
					),
					'meta_keys' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'Additional meta keys to include.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'      => array( 'type' => 'boolean' ),
					'id'           => array( 'type' => 'integer' ),
					'title'        => array( 'type' => 'string' ),
					'status'       => array( 'type' => 'string' ),
					'slug'         => array( 'type' => 'string' ),
					'element_type' => array( 'type' => 'string' ),
					'content'      => array( 'type' => 'string' ),
					'post_content' => array( 'type' => 'string' ),
					'meta'         => array( 'type' => 'object' ),
					'message'      => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				if ( empty( $input['id'] ) ) {
					return array( 'success' => false, 'message' => 'Element ID is required.' );
				}

				if ( ! post_type_exists( 'gp_elements' ) ) {
					return array(
						'success' => false,
						'message' => 'GeneratePress Elements are not available (gp_elements post type missing).',
					);
				}

				$post = get_post( (int) $input['id'] );
				if ( ! $post || 'gp_elements' !== $post->post_type ) {
					return array( 'success' => false, 'message' => 'Element not found.' );
				}

				$include_meta    = ! empty( $input['include_meta'] );
				$include_content = ! empty( $input['include_content'] );
				$meta_keys       = mcp_abilities_generatepress_default_element_meta_keys();

				if ( $include_meta && ! empty( $input['meta_keys'] ) && is_array( $input['meta_keys'] ) ) {
					foreach ( $input['meta_keys'] as $meta_key ) {
						if ( is_string( $meta_key ) && mcp_abilities_generatepress_is_allowed_meta_key( $meta_key ) ) {
							$meta_keys[] = $meta_key;
						}
					}
					$meta_keys = array_values( array_unique( $meta_keys ) );
				}

				$content = '';
				if ( $include_content ) {
					$content = get_post_meta( $post->ID, '_generate_element_content', true );
					if ( '' === $content ) {
						$content = $post->post_content;
					}
				}

				$meta = array();
				if ( $include_meta ) {
					foreach ( $meta_keys as $meta_key ) {
						if ( ! $include_content && '_generate_element_content' === $meta_key ) {
							continue;
						}
						$meta[ $meta_key ] = get_post_meta( $post->ID, $meta_key, true );
					}
				}

				return array(
					'success'      => true,
					'id'           => $post->ID,
					'title'        => $post->post_title,
					'status'       => $post->post_status,
					'slug'         => $post->post_name,
					'element_type' => get_post_meta( $post->ID, '_generate_element_type', true ),
					'content'      => $content,
					'post_content' => $post->post_content,
					'meta'         => $meta,
					'message'      => 'GeneratePress element retrieved successfully',
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
	// GENERATEPRESS - Create Element
	// =========================================================================
	wp_register_ability(
		'generatepress/create-element',
		array(
			'label'               => 'Create GeneratePress Element',
			'description'         => 'Creates a new GeneratePress Element (gp_elements) with meta and content.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'title' ),
				'properties'           => array(
					'title' => array(
						'type'        => 'string',
						'description' => 'Element title.',
					),
					'status' => array(
						'type'        => 'string',
						'default'     => 'publish',
						'description' => 'Post status (publish, draft).',
					),
					'slug' => array(
						'type'        => 'string',
						'description' => 'Optional slug for the element.',
					),
					'element_type' => array(
						'type'        => 'string',
						'description' => 'Element type (hook, block, header, layout, etc).',
					),
					'content' => array(
						'type'        => 'string',
						'description' => 'Element content (stored in _generate_element_content).',
					),
					'hook' => array(
						'type'        => 'string',
						'description' => 'Hook name for hook elements.',
					),
					'custom_hook' => array(
						'type'        => 'string',
						'description' => 'Custom hook name when hook type is custom.',
					),
					'hook_type' => array(
						'type'        => 'string',
						'description' => 'Hook type (hook or custom).',
					),
					'priority' => array(
						'type'        => 'integer',
						'description' => 'Hook priority.',
					),
					'execute_php' => array(
						'type'        => 'boolean',
						'description' => 'Enable execute PHP for hook elements.',
					),
					'display_conditions' => array(
						'type'        => 'array',
						'description' => 'Display conditions array for elements.',
					),
					'exclude_conditions' => array(
						'type'        => 'array',
						'description' => 'Exclude conditions array for elements.',
					),
					'user_conditions' => array(
						'type'        => 'array',
						'description' => 'User conditions array for elements.',
					),
					'meta' => array(
						'type'        => 'object',
						'description' => 'Additional element meta to set (keys must start with _generate_).',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'id'      => array( 'type' => 'integer' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				if ( ! post_type_exists( 'gp_elements' ) ) {
					return array(
						'success' => false,
						'message' => 'GeneratePress Elements are not available (gp_elements post type missing).',
					);
				}

				$title = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
				if ( '' === $title ) {
					return array( 'success' => false, 'message' => 'Element title is required.' );
				}

				$status = isset( $input['status'] ) ? sanitize_text_field( $input['status'] ) : 'publish';
				$slug   = isset( $input['slug'] ) ? sanitize_title( $input['slug'] ) : '';
				$content = isset( $input['content'] ) ? $input['content'] : '';

				$post_id = wp_insert_post(
					array(
						'post_type'    => 'gp_elements',
						'post_status'  => $status,
						'post_title'   => $title,
						'post_name'    => $slug,
						'post_content' => $content,
					),
					true
				);

				if ( is_wp_error( $post_id ) ) {
					return array( 'success' => false, 'message' => $post_id->get_error_message() );
				}

				if ( '' !== $content ) {
					update_post_meta( $post_id, '_generate_element_content', $content );
				}

				if ( isset( $input['element_type'] ) ) {
					update_post_meta( $post_id, '_generate_element_type', sanitize_text_field( $input['element_type'] ) );
				}
				if ( isset( $input['hook'] ) ) {
					update_post_meta( $post_id, '_generate_hook', sanitize_text_field( $input['hook'] ) );
				}
				if ( isset( $input['custom_hook'] ) ) {
					update_post_meta( $post_id, '_generate_custom_hook', sanitize_text_field( $input['custom_hook'] ) );
				}
				if ( isset( $input['hook_type'] ) ) {
					update_post_meta( $post_id, '_generate_hook_type', sanitize_text_field( $input['hook_type'] ) );
				}
				if ( isset( $input['priority'] ) ) {
					update_post_meta( $post_id, '_generate_hook_priority', (int) $input['priority'] );
				}
				if ( array_key_exists( 'execute_php', $input ) ) {
					if ( $input['execute_php'] ) {
						update_post_meta( $post_id, '_generate_hook_execute_php', 'true' );
					} else {
						delete_post_meta( $post_id, '_generate_hook_execute_php' );
					}
				}
				if ( isset( $input['display_conditions'] ) && is_array( $input['display_conditions'] ) ) {
					update_post_meta( $post_id, '_generate_element_display_conditions', $input['display_conditions'] );
				}
				if ( isset( $input['exclude_conditions'] ) && is_array( $input['exclude_conditions'] ) ) {
					update_post_meta( $post_id, '_generate_element_exclude_conditions', $input['exclude_conditions'] );
				}
				if ( isset( $input['user_conditions'] ) && is_array( $input['user_conditions'] ) ) {
					update_post_meta( $post_id, '_generate_element_user_conditions', $input['user_conditions'] );
				}

				if ( ! empty( $input['meta'] ) && is_array( $input['meta'] ) ) {
					foreach ( $input['meta'] as $meta_key => $value ) {
						if ( ! is_string( $meta_key ) || '' === $meta_key ) {
							continue;
						}
						if ( ! mcp_abilities_generatepress_is_allowed_meta_key( $meta_key ) ) {
							continue;
						}
						update_post_meta( $post_id, $meta_key, $value );
					}
				}

				return array(
					'success' => true,
					'id'      => (int) $post_id,
					'message' => 'GeneratePress element created successfully',
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_theme_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
			),
		)
	);

	// =========================================================================
	// GENERATEPRESS - Update Element
	// =========================================================================
	wp_register_ability(
		'generatepress/update-element',
		array(
			'label'               => 'Update GeneratePress Element',
			'description'         => 'Updates an existing GeneratePress Element (gp_elements).',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'id' ),
				'properties'           => array(
					'id' => array(
						'type'        => 'integer',
						'description' => 'Element ID to update.',
					),
					'title' => array(
						'type'        => 'string',
						'description' => 'Element title.',
					),
					'status' => array(
						'type'        => 'string',
						'description' => 'Post status (publish, draft).',
					),
					'slug' => array(
						'type'        => 'string',
						'description' => 'Optional slug for the element.',
					),
					'element_type' => array(
						'type'        => 'string',
						'description' => 'Element type (hook, block, header, layout, etc).',
					),
					'content' => array(
						'type'        => 'string',
						'description' => 'Element content (stored in _generate_element_content).',
					),
					'hook' => array(
						'type'        => 'string',
						'description' => 'Hook name for hook elements.',
					),
					'custom_hook' => array(
						'type'        => 'string',
						'description' => 'Custom hook name when hook type is custom.',
					),
					'hook_type' => array(
						'type'        => 'string',
						'description' => 'Hook type (hook or custom).',
					),
					'priority' => array(
						'type'        => 'integer',
						'description' => 'Hook priority.',
					),
					'execute_php' => array(
						'type'        => 'boolean',
						'description' => 'Enable execute PHP for hook elements.',
					),
					'display_conditions' => array(
						'type'        => 'array',
						'description' => 'Display conditions array for elements.',
					),
					'exclude_conditions' => array(
						'type'        => 'array',
						'description' => 'Exclude conditions array for elements.',
					),
					'user_conditions' => array(
						'type'        => 'array',
						'description' => 'User conditions array for elements.',
					),
					'meta' => array(
						'type'        => 'object',
						'description' => 'Additional element meta to set (keys must start with _generate_). Use null to delete.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'id'      => array( 'type' => 'integer' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				if ( empty( $input['id'] ) ) {
					return array( 'success' => false, 'message' => 'Element ID is required.' );
				}

				if ( ! post_type_exists( 'gp_elements' ) ) {
					return array(
						'success' => false,
						'message' => 'GeneratePress Elements are not available (gp_elements post type missing).',
					);
				}

				$post = get_post( (int) $input['id'] );
				if ( ! $post || 'gp_elements' !== $post->post_type ) {
					return array( 'success' => false, 'message' => 'Element not found.' );
				}

				$post_update = array( 'ID' => $post->ID );
				if ( isset( $input['title'] ) ) {
					$post_update['post_title'] = sanitize_text_field( $input['title'] );
				}
				if ( isset( $input['status'] ) ) {
					$post_update['post_status'] = sanitize_text_field( $input['status'] );
				}
				if ( isset( $input['slug'] ) ) {
					$post_update['post_name'] = sanitize_title( $input['slug'] );
				}
				if ( array_key_exists( 'content', $input ) ) {
					$post_update['post_content'] = $input['content'];
				}

				if ( count( $post_update ) > 1 ) {
					$updated_post = wp_update_post( $post_update, true );
					if ( is_wp_error( $updated_post ) ) {
						return array( 'success' => false, 'message' => $updated_post->get_error_message() );
					}
				}

				if ( array_key_exists( 'content', $input ) ) {
					update_post_meta( $post->ID, '_generate_element_content', $input['content'] );
				}

				if ( isset( $input['element_type'] ) ) {
					update_post_meta( $post->ID, '_generate_element_type', sanitize_text_field( $input['element_type'] ) );
				}
				if ( isset( $input['hook'] ) ) {
					update_post_meta( $post->ID, '_generate_hook', sanitize_text_field( $input['hook'] ) );
				}
				if ( isset( $input['custom_hook'] ) ) {
					update_post_meta( $post->ID, '_generate_custom_hook', sanitize_text_field( $input['custom_hook'] ) );
				}
				if ( isset( $input['hook_type'] ) ) {
					update_post_meta( $post->ID, '_generate_hook_type', sanitize_text_field( $input['hook_type'] ) );
				}
				if ( isset( $input['priority'] ) ) {
					update_post_meta( $post->ID, '_generate_hook_priority', (int) $input['priority'] );
				}
				if ( array_key_exists( 'execute_php', $input ) ) {
					if ( $input['execute_php'] ) {
						update_post_meta( $post->ID, '_generate_hook_execute_php', 'true' );
					} else {
						delete_post_meta( $post->ID, '_generate_hook_execute_php' );
					}
				}
				if ( isset( $input['display_conditions'] ) && is_array( $input['display_conditions'] ) ) {
					update_post_meta( $post->ID, '_generate_element_display_conditions', $input['display_conditions'] );
				}
				if ( isset( $input['exclude_conditions'] ) && is_array( $input['exclude_conditions'] ) ) {
					update_post_meta( $post->ID, '_generate_element_exclude_conditions', $input['exclude_conditions'] );
				}
				if ( isset( $input['user_conditions'] ) && is_array( $input['user_conditions'] ) ) {
					update_post_meta( $post->ID, '_generate_element_user_conditions', $input['user_conditions'] );
				}

				if ( ! empty( $input['meta'] ) && is_array( $input['meta'] ) ) {
					foreach ( $input['meta'] as $meta_key => $value ) {
						if ( ! is_string( $meta_key ) || '' === $meta_key ) {
							continue;
						}
						if ( ! mcp_abilities_generatepress_is_allowed_meta_key( $meta_key ) ) {
							continue;
						}
						if ( null === $value ) {
							delete_post_meta( $post->ID, $meta_key );
						} else {
							update_post_meta( $post->ID, $meta_key, $value );
						}
					}
				}

				return array(
					'success' => true,
					'id'      => $post->ID,
					'message' => 'GeneratePress element updated successfully',
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_theme_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
			),
		)
	);

	// =========================================================================
	// GENERATEPRESS - Delete Element
	// =========================================================================
	wp_register_ability(
		'generatepress/delete-element',
		array(
			'label'               => 'Delete GeneratePress Element',
			'description'         => 'Deletes a GeneratePress Element (gp_elements) by ID.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'id' ),
				'properties'           => array(
					'id' => array(
						'type'        => 'integer',
						'description' => 'Element ID to delete.',
					),
					'force' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Force delete (bypass trash).',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'id'      => array( 'type' => 'integer' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				if ( empty( $input['id'] ) ) {
					return array( 'success' => false, 'message' => 'Element ID is required.' );
				}

				if ( ! post_type_exists( 'gp_elements' ) ) {
					return array(
						'success' => false,
						'message' => 'GeneratePress Elements are not available (gp_elements post type missing).',
					);
				}

				$post_id = (int) $input['id'];
				$post    = get_post( $post_id );
				if ( ! $post || 'gp_elements' !== $post->post_type ) {
					return array( 'success' => false, 'message' => 'Element not found.' );
				}

				$force = ! empty( $input['force'] );
				$deleted = wp_delete_post( $post_id, $force );
				if ( ! $deleted ) {
					return array( 'success' => false, 'message' => 'Failed to delete element.' );
				}

				return array(
					'success' => true,
					'id'      => $post_id,
					'message' => 'GeneratePress element deleted successfully',
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_theme_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
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
add_action( 'wp_abilities_api_init', 'mcp_abilities_generatepress_register_abilities' );
