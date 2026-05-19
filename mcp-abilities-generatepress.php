<?php
/**
 * Plugin Name: MCP Abilities - GeneratePress
 * Plugin URI: https://github.com/bjornfix/mcp-abilities-generatepress
 * Description: GeneratePress and GenerateBlocks abilities for MCP. Manage theme settings, elements, global styles, page meta, and caches.
 * Version: 1.1.10
 * Author: Devenia
 * Author URI: https://devenia.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires at least: 6.9
 * Requires PHP: 8.0
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
 * Check if GeneratePress theme is active.
 */
function mcp_abilities_generatepress_is_active(): bool {
	$theme_info = mcp_abilities_generatepress_get_theme_info();
	return ! empty( $theme_info['is_generatepress'] );
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
 * Clear GeneratePress dynamic CSS cache.
 */
function mcp_abilities_generatepress_clear_dynamic_css_cache(): void {
	delete_option( 'generate_dynamic_css_output' );
	delete_option( 'generate_dynamic_css_cached_version' );

	if ( function_exists( 'generate_update_dynamic_css_cache' ) ) {
		generate_update_dynamic_css_cache();
	}
}

/**
 * Get the expected GenerateBlocks generated CSS file path for a post.
 */
function mcp_abilities_generatepress_generateblocks_css_path( int $post_id ): string {
	global $blog_id;

	$upload_dir  = wp_get_upload_dir();
	$css_blog_id = is_multisite() && isset( $blog_id ) && $blog_id > 1 ? '_blog-' . (int) $blog_id : '';

	return trailingslashit( $upload_dir['basedir'] ) . 'generateblocks/style' . $css_blog_id . '-' . $post_id . '.css';
}

/**
 * Warm GenerateBlocks generated CSS files by requesting pages that use dynamic CSS.
 *
 * @param array<int>|null $post_ids Optional list of post IDs to warm. Null warms all known GenerateBlocks posts.
 * @param int             $limit    Maximum number of posts to warm.
 * @return array{warmed: array<int>, failed: array<int, string>, skipped: int}
 */
function mcp_abilities_generatepress_warm_generateblocks_css( ?array $post_ids = null, int $limit = 100 ): array {
	$known_posts = get_option( 'generateblocks_dynamic_css_posts', array() );
	if ( null === $post_ids ) {
		$post_ids = array_map( 'intval', array_keys( is_array( $known_posts ) ? $known_posts : array() ) );
	} else {
		$post_ids = array_map( 'intval', $post_ids );
	}

	$post_ids = array_values( array_unique( array_filter( $post_ids ) ) );
	sort( $post_ids );

	$warmed  = array();
	$failed  = array();
	$skipped = 0;
	$count   = 0;

	foreach ( $post_ids as $post_id ) {
		if ( $count >= $limit ) {
			$skipped++;
			continue;
		}

		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			$skipped++;
			continue;
		}

		$permalink = get_permalink( $post );
		if ( ! $permalink ) {
			$skipped++;
			continue;
		}

		$count++;
		$url      = add_query_arg( 'mcp_gb_css_warm', (string) time(), $permalink );
		$css_file = mcp_abilities_generatepress_generateblocks_css_path( $post_id );

		update_option( 'generateblocks_dynamic_css_time', 0, false );

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 15,
				'redirection' => 3,
				'user-agent'  => 'MCP GenerateBlocks CSS warmer',
				'headers'     => array(
					'Cache-Control' => 'no-cache',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$failed[ $post_id ] = $response->get_error_message();
			continue;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status >= 200 && $status < 400 && file_exists( $css_file ) ) {
			$warmed[] = $post_id;
		} elseif ( $status >= 200 && $status < 400 ) {
			$failed[ $post_id ] = 'CSS file was not generated.';
		} else {
			$failed[ $post_id ] = 'HTTP ' . $status;
		}
	}

	return array(
		'warmed'  => $warmed,
		'failed'  => $failed,
		'skipped' => $skipped,
	);
}

/**
 * Map page meta labels to GeneratePress meta keys.
 */
function mcp_abilities_generatepress_page_meta_map(): array {
	return array(
		'disable_headline'       => '_generate-disable-headline',
		'disable_nav'            => '_generate-disable-nav',
		'disable_footer'         => '_generate-disable-footer',
		'disable_footer_widgets' => '_generate-disable-footer-widgets',
		'sidebar_layout'         => '_generate-sidebar-layout-meta',
		'content_area'           => '_generate-full-width-content',
		'transparent_header'     => '_generate-transparent-header',
		'sticky_header'          => '_generate-sticky-navigation-meta',
	);
}

/**
 * Map module slugs to GeneratePress settings options.
 */
function mcp_abilities_generatepress_module_settings_map(): array {
	return array(
		'blog'          => 'generate_blog_settings',
		'spacing'       => 'generate_spacing_settings',
		'menu_plus'     => 'generate_menu_plus_settings',
		'secondary_nav' => 'generate_secondary_nav_settings',
		'woocommerce'   => 'generate_woocommerce_settings',
	);
}

/**
 * Convert a font family name into the GeneratePress dynamic typography value.
 */
function mcp_abilities_generatepress_font_family_value( string $font_family ): string {
	$font_family = trim( $font_family );
	if ( '' === $font_family || str_starts_with( $font_family, 'var(' ) ) {
		return $font_family;
	}

	$slug = strtolower( $font_family );
	$slug = preg_replace( '/[^a-z0-9]+/', '-', $slug );
	$slug = trim( (string) $slug, '-' );

	return '' === $slug ? $font_family : 'var(--gp-font--' . $slug . ')';
}

/**
 * Find an existing GeneratePress typography rule by selector or append a new one.
 */
function mcp_abilities_generatepress_upsert_typography_rule( array &$rules, string $selector, array $updates, string $group = 'content' ): void {
	$index = null;
	foreach ( $rules as $rule_index => $rule ) {
		if ( is_array( $rule ) && isset( $rule['selector'] ) && $selector === $rule['selector'] ) {
			$index = $rule_index;
			break;
		}
	}

	if ( null === $index ) {
		$rules[] = array(
			'selector' => $selector,
			'module'   => 'core',
			'group'    => $group,
		);
		$index = array_key_last( $rules );
	}

	foreach ( $updates as $key => $value ) {
		if ( null === $value ) {
			unset( $rules[ $index ][ $key ] );
			continue;
		}
		$rules[ $index ][ $key ] = $value;
	}
}

/**
 * Apply a typography group to GeneratePress legacy and dynamic typography settings.
 */
function mcp_abilities_generatepress_apply_typography_group( array &$settings, array &$rules, string $group, array $values ): array {
	$changed = array();

	$map = array(
		'body'       => array(
			'selector' => 'body',
			'rule_group' => 'base',
			'keys'     => array(
				'fontFamily'    => 'font_body',
				'fontWeight'    => 'body_font_weight',
				'fontSize'      => 'body_font_size',
				'lineHeight'    => 'body_line_height',
				'textTransform' => 'body_font_transform',
			),
		),
		'navigation' => array(
			'selector' => 'primary-menu-items',
			'rule_group' => 'primaryNavigation',
			'keys'     => array(
				'fontFamily'    => 'font_navigation',
				'fontWeight'    => 'navigation_font_weight',
				'fontSize'      => 'navigation_font_size',
				'textTransform' => 'navigation_font_transform',
			),
		),
		'buttons'    => array(
			'selector' => 'buttons',
			'rule_group' => 'content',
			'keys'     => array(
				'fontFamily'    => 'font_buttons',
				'fontWeight'    => 'buttons_font_weight',
				'fontSize'      => 'buttons_font_size',
				'textTransform' => 'buttons_font_transform',
			),
		),
	);

	for ( $level = 1; $level <= 6; $level++ ) {
		$map[ 'h' . $level ] = array(
			'selector' => 'h' . $level,
			'rule_group' => 'content',
			'keys'     => array(
				'fontFamily'     => 'font_heading_' . $level,
				'fontWeight'     => 'heading_' . $level . '_weight',
				'fontSize'       => 'heading_' . $level . '_font_size',
				'fontSizeMobile' => 'mobile_heading_' . $level . '_font_size',
				'lineHeight'     => 'heading_' . $level . '_line_height',
				'textTransform'  => 'heading_' . $level . '_transform',
			),
		);
	}

	if ( empty( $map[ $group ] ) ) {
		return $changed;
	}

	$config       = $map[ $group ];
	$rule_updates = array();

	foreach ( $config['keys'] as $input_key => $setting_key ) {
		if ( ! array_key_exists( $input_key, $values ) ) {
			continue;
		}

		$value = is_string( $values[ $input_key ] ) ? trim( $values[ $input_key ] ) : $values[ $input_key ];
		if ( '' === $value || null === $value ) {
			unset( $settings[ $setting_key ] );
			$rule_updates[ $input_key ] = null;
			$changed[] = $setting_key;
			continue;
		}

		$settings[ $setting_key ] = $value;
		$changed[] = $setting_key;

		if ( 'fontFamily' === $input_key ) {
			$rule_updates['fontFamily'] = mcp_abilities_generatepress_font_family_value( (string) $value );
			continue;
		}

		if ( 'fontSize' === $input_key || 'fontSizeMobile' === $input_key ) {
			$rule_updates[ $input_key ] = (string) $value;
			if ( 'fontSize' === $input_key && ! isset( $values['fontSizeUnit'] ) ) {
				$rule_updates['fontSizeUnit'] = 'px';
			}
			continue;
		}

		$rule_updates[ $input_key ] = (string) $value;
	}

	if ( array_key_exists( 'fontSizeUnit', $values ) ) {
		$rule_updates['fontSizeUnit'] = (string) $values['fontSizeUnit'];
	}

	if ( ! empty( $rule_updates ) ) {
		mcp_abilities_generatepress_upsert_typography_rule( $rules, $config['selector'], $rule_updates, $config['rule_group'] );
	}

	return array_values( array_unique( $changed ) );
}

/**
 * Check if a GeneratePress setting key is a global design setting.
 */
function mcp_abilities_generatepress_is_global_design_setting_key( string $key ): bool {
	$exact_keys = array(
		'global_colors',
		'hide_title',
		'hide_tagline',
		'logo',
		'retina_logo',
		'logo_width',
		'inline_logo_site_branding',
		'custom_logo',
		'back_to_top',
	);

	if ( in_array( $key, $exact_keys, true ) ) {
		return true;
	}

	$prefixes = array(
		'font_',
		'body_',
		'heading_',
		'mobile_heading_',
		'buttons_',
		'navigation_',
		'subnavigation_',
		'container_',
		'content_',
		'layout_',
		'blog_layout_',
		'single_layout_',
		'header_',
		'nav_',
		'footer_',
		'top_bar_',
		'sidebar_',
		'form_',
		'entry_meta_',
		'site_title_',
		'site_tagline_',
	);

	foreach ( $prefixes as $prefix ) {
		if ( str_starts_with( $key, $prefix ) ) {
			return true;
		}
	}

	return str_ends_with( $key, '_color' ) || str_contains( $key, '_background_color' );
}

/**
 * Normalize module status values.
 */
function mcp_abilities_generatepress_normalize_module_status( $value ): ?string {
	if ( is_bool( $value ) ) {
		return $value ? 'activated' : 'deactivated';
	}
	if ( ! is_string( $value ) ) {
		return null;
	}

	$value = strtolower( trim( $value ) );
	if ( in_array( $value, array( 'activated', 'active', 'enable', 'enabled', 'on', 'true', '1' ), true ) ) {
		return 'activated';
	}
	if ( in_array( $value, array( 'deactivated', 'inactive', 'disable', 'disabled', 'off', 'false', '0' ), true ) ) {
		return 'deactivated';
	}

	return null;
}

/**
 * Register GeneratePress and GenerateBlocks abilities.
 */
function mcp_abilities_generatepress_register_abilities(): void {
	if ( ! mcp_abilities_generatepress_check_dependencies() ) {
		return;
	}
	if ( ! mcp_abilities_generatepress_is_active() ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p><strong>MCP Abilities - GeneratePress</strong> requires the GeneratePress theme to be installed and active.</p></div>';
		} );
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
					'force'   => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'Alias for confirm; accepted for client compatibility.',
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
				$confirm = true;
				if ( isset( $input['confirm'] ) ) {
					$confirm = (bool) $input['confirm'];
				} elseif ( isset( $input['force'] ) ) {
					$confirm = (bool) $input['force'];
				}
				if ( ! $confirm ) {
					return array( 'success' => false, 'message' => 'Confirmation required to clear cache.' );
				}

				mcp_abilities_generatepress_clear_dynamic_css_cache();

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
					'font_body', 'body_font_weight', 'body_font_transform', 'body_font_size', 'body_line_height',
					'font_heading_1', 'heading_1_weight', 'heading_1_transform', 'heading_1_font_size', 'mobile_heading_1_font_size', 'heading_1_line_height',
					'font_heading_2', 'heading_2_weight', 'heading_2_transform', 'heading_2_font_size', 'mobile_heading_2_font_size', 'heading_2_line_height',
					'font_heading_3', 'heading_3_weight', 'heading_3_transform', 'heading_3_font_size', 'mobile_heading_3_font_size', 'heading_3_line_height',
					'font_heading_4', 'heading_4_weight', 'heading_4_transform', 'heading_4_font_size', 'mobile_heading_4_font_size', 'heading_4_line_height',
					'font_heading_5', 'heading_5_weight', 'heading_5_transform', 'heading_5_font_size', 'mobile_heading_5_font_size', 'heading_5_line_height',
					'font_heading_6', 'heading_6_weight', 'heading_6_transform', 'heading_6_font_size', 'mobile_heading_6_font_size', 'heading_6_line_height',
					'font_navigation', 'navigation_font_weight', 'navigation_font_transform', 'navigation_font_size',
					'font_buttons', 'buttons_font_weight', 'buttons_font_transform', 'buttons_font_size',
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

				// Site identity keys.
				$site_identity_keys = array(
					'font_site_title', 'font_site_tagline',
					'site_title_font_size', 'site_tagline_font_size',
					'site_title_font_weight', 'site_title_font_transform',
					'site_title_color', 'site_tagline_color',
					'logo_width', 'retina_logo',
					'mobile_site_title_font_size', 'tablet_site_title_font_size',
					'mobile_navigation_site_title_font_size', 'header_alignment_setting',
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
					if ( isset( $settings['font_manager'] ) ) {
						$result['typography']['font_manager'] = $settings['font_manager'];
					}
					if ( isset( $settings['typography'] ) ) {
						$result['typography']['typography'] = $settings['typography'];
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

				if ( 'all' === $section || 'site_identity' === $section ) {
					foreach ( $site_identity_keys as $key ) {
						if ( isset( $settings[ $key ] ) ) {
							$result['site_identity'][ $key ] = $settings[ $key ];
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
					foreach ( array( 'colors', 'layout', 'buttons', 'site_identity' ) as $section_key ) {
						if ( isset( $input['settings'][ $section_key ] ) && is_array( $input['settings'][ $section_key ] ) ) {
							return array(
								'success' => false,
								'message' => 'Refusing nested global design section "' . $section_key . '". Use generatepress/update-global-design-settings.',
							);
						}
					}
					if ( isset( $input['settings']['typography'] ) && ! is_array( $input['settings']['typography'] ) ) {
						return array(
							'success' => false,
							'message' => 'Invalid typography setting. Use generatepress/update-global-design-settings for global font settings.',
						);
					}
					if ( isset( $input['settings']['typography'] ) && is_array( $input['settings']['typography'] ) && array_keys( $input['settings']['typography'] ) !== range( 0, count( $input['settings']['typography'] ) - 1 ) ) {
						return array(
							'success' => false,
							'message' => 'Refusing nested typography object inside generate_settings. Use generatepress/update-global-design-settings.',
						);
					}
					foreach ( array_keys( $input['settings'] ) as $setting_key ) {
						if ( is_string( $setting_key ) && mcp_abilities_generatepress_is_global_design_setting_key( $setting_key ) ) {
							return array(
								'success' => false,
								'message' => 'Refusing global design setting "' . $setting_key . '" through generic update-settings. Use generatepress/update-global-design-settings.',
							);
						}
					}

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
				mcp_abilities_generatepress_clear_dynamic_css_cache();

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
						'destructive' => true,
						'idempotent'  => true,
					),
				),
		)
	);

	// =========================================================================
	// GENERATEPRESS - Update Global Design Settings
	// =========================================================================
	wp_register_ability(
		'generatepress/update-global-design-settings',
		array(
			'label'               => 'Update GeneratePress Global Design Settings',
			'description'         => 'Updates global GeneratePress design settings for typography, colors, layout, buttons, and site identity. Use this instead of page/block-level styling for site-wide design decisions.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'typography'   => array( 'type' => 'object' ),
					'colors'       => array( 'type' => 'object' ),
					'layout'       => array( 'type' => 'object' ),
					'buttons'      => array( 'type' => 'object' ),
					'site_identity' => array( 'type' => 'object' ),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'               => array( 'type' => 'boolean' ),
					'updated_sections'      => array( 'type' => 'array' ),
					'updated_keys'          => array( 'type' => 'array' ),
					'typography_rule_count' => array( 'type' => 'integer' ),
					'message'               => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$input = is_array( $input ) ? $input : array();

				$settings = get_option( 'generate_settings', array() );
				if ( ! is_array( $settings ) ) {
					$settings = array();
				}

				$rules = isset( $settings['typography'] ) && is_array( $settings['typography'] ) ? $settings['typography'] : array();
				if ( array_keys( $rules ) !== range( 0, count( $rules ) - 1 ) ) {
					$rules = array();
				}

				$allowed = array(
					'colors'        => array(
						'global_colors', 'background_color', 'text_color', 'link_color', 'link_color_hover', 'link_color_visited',
						'header_background_color', 'header_text_color', 'header_link_color',
						'navigation_background_color', 'navigation_text_color', 'navigation_background_hover_color', 'navigation_text_hover_color', 'navigation_background_current_color', 'navigation_text_current_color',
						'subnavigation_background_color', 'subnavigation_text_color', 'subnavigation_background_hover_color', 'subnavigation_text_hover_color', 'subnavigation_background_current_color', 'subnavigation_text_current_color',
						'content_background_color', 'content_text_color', 'content_link_color', 'content_link_hover_color',
						'sidebar_widget_background_color', 'sidebar_widget_title_color', 'sidebar_widget_text_color',
						'footer_widget_background_color', 'footer_widget_title_color', 'footer_widget_text_color', 'footer_widget_link_color',
						'footer_background_color', 'footer_text_color', 'footer_link_color', 'footer_link_hover_color',
						'entry_meta_text_color', 'entry_meta_link_color', 'entry_meta_link_color_hover',
						'form_background_color', 'form_text_color', 'form_background_color_focus', 'form_text_color_focus', 'form_border_color', 'form_border_color_focus',
						'top_bar_background_color', 'navigation_search_background_color', 'navigation_search_text_color',
					),
					'layout'        => array(
						'container_width', 'container_alignment', 'content_layout_setting', 'content_width', 'layout_setting', 'blog_layout_setting', 'single_layout_setting',
						'header_layout_setting', 'header_inner_width', 'header_alignment_setting',
						'nav_layout_setting', 'nav_inner_width', 'nav_alignment_setting', 'nav_position_setting', 'nav_drop_point', 'nav_dropdown_type', 'nav_dropdown_direction', 'nav_search',
						'footer_layout_setting', 'footer_inner_width', 'footer_widget_setting', 'footer_bar_alignment',
						'top_bar_width', 'top_bar_inner_width', 'top_bar_alignment', 'back_to_top',
					),
					'buttons'       => array(
						'font_buttons', 'buttons_font_weight', 'buttons_font_size', 'buttons_font_transform',
						'form_button_background_color', 'form_button_background_color_hover', 'form_button_text_color', 'form_button_text_color_hover', 'form_button_border_radius',
					),
					'site_identity' => array(
						'hide_title', 'hide_tagline', 'logo', 'retina_logo', 'logo_width', 'inline_logo_site_branding', 'custom_logo',
						'font_site_title', 'site_title_font_size', 'mobile_site_title_font_size', 'site_title_font_weight', 'site_title_font_transform', 'site_title_color',
						'font_site_tagline', 'site_tagline_font_size', 'site_tagline_color',
					),
				);

				$updated_sections = array();
				$updated_keys     = array();

				if ( isset( $input['typography'] ) && is_array( $input['typography'] ) ) {
					foreach ( array( 'body', 'navigation', 'buttons', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $group ) {
						if ( isset( $input['typography'][ $group ] ) && is_array( $input['typography'][ $group ] ) ) {
							$changed = mcp_abilities_generatepress_apply_typography_group( $settings, $rules, $group, $input['typography'][ $group ] );
							if ( ! empty( $changed ) ) {
								$updated_sections[] = 'typography.' . $group;
								$updated_keys       = array_merge( $updated_keys, $changed );
							}
						}
					}
				}

				foreach ( $allowed as $section => $keys ) {
					if ( empty( $input[ $section ] ) || ! is_array( $input[ $section ] ) ) {
						continue;
					}
					foreach ( $input[ $section ] as $key => $value ) {
						if ( ! is_string( $key ) || ! in_array( $key, $keys, true ) ) {
							continue;
						}
						if ( null === $value || '' === $value ) {
							unset( $settings[ $key ] );
						} else {
							$settings[ $key ] = $value;
						}
						$updated_sections[] = $section;
						$updated_keys[]     = $key;
					}
				}

				if ( empty( $updated_keys ) ) {
					return array(
						'success' => false,
						'message' => 'No allowed global GeneratePress design settings provided.',
					);
				}

				$settings['typography'] = array_values( $rules );
				update_option( 'generate_settings', $settings );
				mcp_abilities_generatepress_clear_dynamic_css_cache();

				return array(
					'success'               => true,
					'updated_sections'      => array_values( array_unique( $updated_sections ) ),
					'updated_keys'          => array_values( array_unique( $updated_keys ) ),
					'typography_rule_count' => count( $settings['typography'] ),
					'message'               => 'GeneratePress global design settings updated successfully.',
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
	// GENERATEPRESS - List Modules
	// =========================================================================
	wp_register_ability(
		'generatepress/list-modules',
		array(
			'label'               => 'List GeneratePress Modules',
			'description'         => 'Lists GeneratePress Premium module statuses (generate_package_* options).',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'modules' => array( 'type' => 'array' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function (): array {
				global $wpdb;

				$prefix = 'generate_package_';
				$like   = $wpdb->esc_like( $prefix ) . '%';

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Module discovery uses prepared LIKE.
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT option_name, option_value FROM ' . $wpdb->options . ' WHERE option_name LIKE %s ORDER BY option_name ASC',
						$like
					),
					ARRAY_A
				);

				$modules = array();
				foreach ( $rows as $row ) {
					$option_name = $row['option_name'];
					$slug        = substr( $option_name, strlen( $prefix ) );
					$value       = maybe_unserialize( $row['option_value'] );
					$status      = is_string( $value ) ? $value : '';

					$modules[] = array(
						'slug'        => $slug,
						'option_name' => $option_name,
						'status'      => $status,
						'active'      => ( 'activated' === $status ),
					);
				}

				return array(
					'success' => true,
					'modules' => $modules,
					'message' => 'Modules retrieved successfully',
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
	// GENERATEPRESS - Update Modules
	// =========================================================================
	wp_register_ability(
		'generatepress/update-modules',
		array(
			'label'               => 'Update GeneratePress Modules',
			'description'         => 'Activates or deactivates GeneratePress Premium modules (generate_package_* options).',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'modules' ),
				'properties'           => array(
					'modules' => array(
						'type'        => 'object',
						'description' => 'Map of module slugs to status (activated/deactivated or boolean).',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'updated'  => array( 'type' => 'array' ),
					'missing'  => array( 'type' => 'array' ),
					'rejected' => array( 'type' => 'array' ),
					'message'  => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$modules = isset( $input['modules'] ) && is_array( $input['modules'] ) ? $input['modules'] : array();
				if ( empty( $modules ) ) {
					return array( 'success' => false, 'message' => 'No modules provided.' );
				}

				$updated  = array();
				$missing  = array();
				$rejected = array();
				$marker   = 'mcp_missing_' . wp_generate_password( 12, false );

				foreach ( $modules as $module => $status_input ) {
					if ( ! is_string( $module ) || '' === $module ) {
						continue;
					}

					$module = sanitize_key( $module );
					$module = str_replace( '-', '_', $module );
					if ( '' === $module ) {
						continue;
					}

					$status = mcp_abilities_generatepress_normalize_module_status( $status_input );
					if ( null === $status ) {
						$rejected[] = $module;
						continue;
					}

					$option_name = 'generate_package_' . $module;
					$current     = get_option( $option_name, $marker );
					if ( $current === $marker ) {
						$missing[] = $module;
						continue;
					}

					update_option( $option_name, $status );
					$updated[] = $module;
				}

				return array(
					'success'  => true,
					'updated'  => $updated,
					'missing'  => $missing,
					'rejected' => $rejected,
					'message'  => 'Module statuses updated successfully',
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
	// GENERATEPRESS - Get Module Settings
	// =========================================================================
	wp_register_ability(
		'generatepress/get-module-settings',
		array(
			'label'               => 'Get GeneratePress Module Settings',
			'description'         => 'Gets settings for a GeneratePress Premium module (blog, spacing, menu_plus, secondary_nav, woocommerce).',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'module' ),
				'properties'           => array(
					'module' => array(
						'type'        => 'string',
						'enum'        => array_keys( mcp_abilities_generatepress_module_settings_map() ),
						'description' => 'Module slug to retrieve.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'     => array( 'type' => 'boolean' ),
					'module'      => array( 'type' => 'string' ),
					'option_name' => array( 'type' => 'string' ),
					'settings'    => array( 'type' => 'object' ),
					'message'     => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$module = isset( $input['module'] ) ? sanitize_key( $input['module'] ) : '';
				$module = str_replace( '-', '_', $module );
				$map    = mcp_abilities_generatepress_module_settings_map();

				if ( '' === $module || ! isset( $map[ $module ] ) ) {
					return array( 'success' => false, 'message' => 'Unknown module.' );
				}

				$option_name = $map[ $module ];
				$settings    = get_option( $option_name, array() );

				if ( ! is_array( $settings ) ) {
					$settings = array();
				}

				return array(
					'success'     => true,
					'module'      => $module,
					'option_name' => $option_name,
					'settings'    => $settings,
					'message'     => 'Module settings retrieved successfully',
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
	// GENERATEPRESS - Update Module Settings
	// =========================================================================
	wp_register_ability(
		'generatepress/update-module-settings',
		array(
			'label'               => 'Update GeneratePress Module Settings',
			'description'         => 'Updates settings for a GeneratePress Premium module (blog, spacing, menu_plus, secondary_nav, woocommerce).',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'module', 'settings' ),
				'properties'           => array(
					'module' => array(
						'type'        => 'string',
						'enum'        => array_keys( mcp_abilities_generatepress_module_settings_map() ),
						'description' => 'Module slug to update.',
					),
					'settings' => array(
						'type'        => 'object',
						'description' => 'Settings to update (merged with existing unless replace is true).',
					),
					'replace' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Replace settings entirely instead of merging.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'     => array( 'type' => 'boolean' ),
					'module'      => array( 'type' => 'string' ),
					'option_name' => array( 'type' => 'string' ),
					'message'     => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$module  = isset( $input['module'] ) ? sanitize_key( $input['module'] ) : '';
				$module  = str_replace( '-', '_', $module );
				$map     = mcp_abilities_generatepress_module_settings_map();
				$settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();

				if ( '' === $module || ! isset( $map[ $module ] ) ) {
					return array( 'success' => false, 'message' => 'Unknown module.' );
				}

				if ( empty( $settings ) ) {
					return array( 'success' => false, 'message' => 'No settings provided.' );
				}

				$option_name = $map[ $module ];
				$replace     = ! empty( $input['replace'] );

				if ( $replace ) {
					$updated = $settings;
				} else {
					$current = get_option( $option_name, array() );
					if ( ! is_array( $current ) ) {
						$current = array();
					}
					$updated = array_merge( $current, $settings );
				}

				update_option( $option_name, $updated );
				mcp_abilities_generatepress_clear_dynamic_css_cache();

				return array(
					'success'     => true,
					'module'      => $module,
					'option_name' => $option_name,
					'message'     => 'Module settings updated successfully',
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
	// GENERATEPRESS - Get Typography
	// =========================================================================
	wp_register_ability(
		'generatepress/get-typography',
		array(
			'label'               => 'Get GeneratePress Typography',
			'description'         => 'Retrieves GeneratePress typography rules and font manager entries (Local Font Library).',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'      => array( 'type' => 'boolean' ),
					'font_manager' => array( 'type' => 'array' ),
					'typography'   => array( 'type' => 'array' ),
					'message'      => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function (): array {
				$settings     = get_option( 'generate_settings', array() );
				$font_manager = isset( $settings['font_manager'] ) && is_array( $settings['font_manager'] ) ? $settings['font_manager'] : array();
				$typography   = isset( $settings['typography'] ) && is_array( $settings['typography'] ) ? $settings['typography'] : array();

				return array(
					'success'      => true,
					'font_manager' => $font_manager,
					'typography'   => $typography,
					'message'      => 'Typography retrieved successfully',
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
	// GENERATEPRESS - Update Typography
	// =========================================================================
	wp_register_ability(
		'generatepress/update-typography',
		array(
			'label'               => 'Update GeneratePress Typography',
			'description'         => 'Updates GeneratePress typography rules and/or font manager entries (Local Font Library).',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'font_manager' => array(
						'type'        => 'array',
						'description' => 'Font manager entries to save (replaces existing).',
					),
					'typography' => array(
						'type'        => 'array',
						'description' => 'Typography rules to save (replaces existing).',
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
			'execute_callback'    => function ( array $input = array() ): array {
				$input = is_array( $input ) ? $input : array();

				$has_font_manager = array_key_exists( 'font_manager', $input );
				$has_typography   = array_key_exists( 'typography', $input );

				if ( ! $has_font_manager && ! $has_typography ) {
					return array( 'success' => false, 'message' => 'No typography data provided.' );
				}

				$current = get_option( 'generate_settings', array() );
				if ( ! is_array( $current ) ) {
					$current = array();
				}

				if ( $has_font_manager ) {
					$current['font_manager'] = is_array( $input['font_manager'] ) ? $input['font_manager'] : array();
				}
				if ( $has_typography ) {
					$current['typography'] = is_array( $input['typography'] ) ? $input['typography'] : array();
				}

				update_option( 'generate_settings', $current );
				mcp_abilities_generatepress_clear_dynamic_css_cache();

				return array(
					'success' => true,
					'message' => 'Typography updated successfully',
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
	// GENERATEPRESS - Get Site Library Cache
	// =========================================================================
	wp_register_ability(
		'generatepress/get-site-library-cache',
		array(
			'label'               => 'Get GeneratePress Site Library Cache',
			'description'         => 'Returns cached Starter Site library metadata without dumping the full dataset.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'     => array( 'type' => 'boolean' ),
					'cached'      => array( 'type' => 'boolean' ),
					'item_count'  => array( 'type' => 'integer' ),
					'expires_at'  => array( 'type' => 'string' ),
					'expires_gmt' => array( 'type' => 'string' ),
					'message'     => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function (): array {
				$library = get_option( 'generatepress_sites', array() );
				$expires = get_option( 'generatepress_sites_expiration', '' );

				$item_count = is_array( $library ) ? count( $library ) : 0;
				$cached     = ( $item_count > 0 );
				$expires_gmt = '';

				if ( is_numeric( $expires ) ) {
					$expires_gmt = gmdate( 'Y-m-d H:i:s', (int) $expires );
				}

				return array(
					'success'     => true,
					'cached'      => $cached,
					'item_count'  => $item_count,
					'expires_at'  => (string) $expires,
					'expires_gmt' => $expires_gmt,
					'message'     => 'Site library cache inspected successfully',
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
	// GENERATEPRESS - Clear Site Library Cache
	// =========================================================================
	wp_register_ability(
		'generatepress/clear-site-library-cache',
		array(
			'label'               => 'Clear GeneratePress Site Library Cache',
			'description'         => 'Clears the cached Starter Site library to force a refresh.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'confirm' => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'Confirm cache clear operation.',
					),
					'force'   => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'Alias for confirm; accepted for client compatibility.',
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

				delete_option( 'generatepress_sites' );
				delete_option( 'generatepress_sites_expiration' );

				return array(
					'success' => true,
					'message' => 'Site library cache cleared successfully',
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
						'destructive' => true,
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

				$meta_map = mcp_abilities_generatepress_page_meta_map();

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
				$meta_map = mcp_abilities_generatepress_page_meta_map();

				foreach ( $meta_map as $input_key => $meta_key ) {
					if ( isset( $input[ $input_key ] ) ) {
						$value = $input[ $input_key ];

						if ( 'content_area' === $input_key ) {
							if ( '' === $value ) {
								delete_post_meta( $post_id, $meta_key );
								$updated[] = "{$input_key} = '' (removed)";
							} elseif ( 'full-width' === $value || 'full-width-content' === $value ) {
								update_post_meta( $post_id, $meta_key, 'true' );
								$updated[] = "{$input_key} = full-width";
							} elseif ( 'contained' === $value ) {
								update_post_meta( $post_id, $meta_key, 'contained' );
								$updated[] = "{$input_key} = contained";
							}
							continue;
						}

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
					'status' => array(
						'type'        => 'string',
						'enum'        => array( 'publish', 'draft', 'pending', 'private', 'trash', 'any' ),
						'default'     => 'publish',
						'description' => 'Filter by post status.',
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

				$query    = new WP_Query( $args );
				$elements = array();

				// Filter by element type in PHP to avoid slow meta_query
				$filter_type = ! empty( $input['element_type'] ) ? sanitize_text_field( $input['element_type'] ) : '';

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
					// Filter by element type in PHP to avoid slow meta_query
					$element_type = get_post_meta( $post->ID, '_generate_element_type', true );
					if ( $filter_type !== '' && $element_type !== $filter_type ) {
						continue;
					}

					$item = array(
						'id'          => $post->ID,
						'title'       => $post->post_title,
						'status'      => $post->post_status,
						'slug'        => $post->post_name,
						'modified_gmt' => $post->post_modified_gmt,
					);

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

				// Update _generate_element_content meta - always set it when content is provided
				if ( isset( $input['content'] ) && $input['content'] !== '' ) {
					update_post_meta( $post_id, '_generate_element_content', $input['content'] );
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

				// Update _generate_element_content meta - always set it when content is provided
				if ( array_key_exists( 'content', $input ) && $input['content'] !== '' ) {
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
			'description'         => 'Moves a GeneratePress Element (gp_elements) to trash. Restore from WordPress admin.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'id' ),
				'properties'           => array(
					'id' => array(
						'type'        => 'integer',
						'description' => 'Element ID to delete.',
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

				$deleted = wp_delete_post( $post_id, false );
				if ( ! $deleted ) {
					return array( 'success' => false, 'message' => 'Failed to move element to trash.' );
				}

				return array(
					'success' => true,
					'id'      => $post_id,
					'message' => 'GeneratePress element moved to trash. Restore from WordPress admin.',
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
	// GENERATEPRESS - Restore Element from Trash
	// =========================================================================
	wp_register_ability(
		'generatepress/restore-element',
		array(
			'label'               => 'Restore GeneratePress Element',
			'description'         => 'Restores a trashed GeneratePress Element (gp_elements) by ID.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'id' ),
				'properties'           => array(
					'id' => array(
						'type'        => 'integer',
						'description' => 'Element ID to restore.',
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

				if ( 'trash' !== $post->post_status ) {
					return array( 'success' => false, 'message' => 'Element is not in trash.' );
				}

				$restored = wp_untrash_post( $post_id );
				if ( ! $restored ) {
					return array( 'success' => false, 'message' => 'Failed to restore element from trash.' );
				}

				return array(
					'success' => true,
					'id'      => $post_id,
					'message' => 'GeneratePress element restored from trash.',
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
	// GENERATEBLOCKS - Clear CSS Cache
	// =========================================================================
	wp_register_ability(
		'generateblocks/clear-cache',
		array(
			'label'               => 'Clear GenerateBlocks Cache',
			'description'         => 'Clears GenerateBlocks CSS cache metadata while preserving generated files by default.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'confirm' => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'Confirm cache clear operation.',
					),
					'force'   => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'Alias for confirm; accepted for client compatibility.',
					),
					'warm'    => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Warm regenerated GenerateBlocks CSS files. Only needed after delete_files or for targeted post_ids.',
					),
					'delete_files' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Delete generated CSS files. Defaults to false to avoid frontend pages loading without their per-page CSS.',
					),
					'post_ids' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'integer' ),
						'description' => 'Optional post IDs to warm. Defaults to all known GenerateBlocks dynamic CSS posts.',
					),
					'limit'   => array(
						'type'        => 'integer',
						'default'     => 100,
						'minimum'     => 1,
						'maximum'     => 500,
						'description' => 'Maximum number of posts to warm.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'deleted' => array( 'type' => 'integer' ),
					'delete_files' => array( 'type' => 'boolean' ),
					'warmed'  => array(
						'type'  => 'array',
						'items' => array( 'type' => 'integer' ),
					),
					'failed'  => array( 'type' => 'object' ),
					'skipped' => array( 'type' => 'integer' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$confirm = true;
				if ( isset( $input['confirm'] ) ) {
					$confirm = (bool) $input['confirm'];
				} elseif ( isset( $input['force'] ) ) {
					$confirm = (bool) $input['force'];
				}
				if ( ! $confirm ) {
					return array(
						'success' => false,
						'deleted' => 0,
						'message' => 'Confirmation required to clear cache.',
					);
				}

				$upload_dir   = wp_upload_dir();
				$css_dir      = $upload_dir['basedir'] . '/generateblocks/';
				$deleted      = 0;
				$delete_files = isset( $input['delete_files'] ) && (bool) $input['delete_files'];
				$has_post_ids = isset( $input['post_ids'] ) && is_array( $input['post_ids'] ) && ! empty( $input['post_ids'] );
				$post_ids     = $has_post_ids ? array_values( array_unique( array_filter( array_map( 'intval', $input['post_ids'] ) ) ) ) : null;

				if ( $delete_files && is_dir( $css_dir ) ) {
					$files = array();
					if ( null !== $post_ids ) {
						foreach ( $post_ids as $post_id ) {
							$files[] = mcp_abilities_generatepress_generateblocks_css_path( $post_id );
						}
					} else {
						$files = glob( $css_dir . '*.css' );
					}

					if ( $files ) {
						foreach ( $files as $file ) {
							if ( is_string( $file ) && file_exists( $file ) && wp_delete_file( $file ) ) {
								$deleted++;
							}
						}
					}
				}

				// Clear metadata without deleting generated CSS files by default.
				delete_option( 'generateblocks_css_version' );

				$warm_result = array(
					'warmed'  => array(),
					'failed'  => array(),
					'skipped' => 0,
				);
				$warm         = isset( $input['warm'] ) ? (bool) $input['warm'] : ( $delete_files || $has_post_ids );

				if ( $warm ) {
					$limit       = isset( $input['limit'] ) ? max( 1, min( 500, (int) $input['limit'] ) ) : 100;
					$warm_result = mcp_abilities_generatepress_warm_generateblocks_css( $post_ids, $limit );
				}

				return array(
					'success' => true,
					'deleted' => $deleted,
					'delete_files' => $delete_files,
					'warmed'  => $warm_result['warmed'],
					'failed'  => $warm_result['failed'],
					'skipped' => $warm_result['skipped'],
					'message' => $warm
						? sprintf(
							'Cleared GenerateBlocks CSS metadata; %1$d file(s) deleted; warmed %2$d post(s); %3$d failed; %4$d skipped.',
							$deleted,
							count( $warm_result['warmed'] ),
							count( $warm_result['failed'] ),
							$warm_result['skipped']
						)
						: ( $delete_files
							? "Cleared GenerateBlocks CSS metadata and deleted {$deleted} CSS file(s)."
							: 'Cleared GenerateBlocks CSS metadata; existing CSS files were preserved.' ),
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
