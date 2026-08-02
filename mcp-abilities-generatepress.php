<?php
/**
 * Plugin Name: MCP Abilities - GeneratePress
 * Plugin URI: https://github.com/bjornfix/mcp-abilities-generatepress
 * Description: GeneratePress and GenerateBlocks abilities for MCP. Manage theme settings, elements, global styles, page meta, and caches.
 * Version: 1.1.56
 * Author: basicus
 * Author URI: https://profiles.wordpress.org/basicus/
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

require_once __DIR__ . '/includes/class-generateblocks-grid-projection.php';
MCP_Abilities_GeneratePress_GenerateBlocks_Grid_Projection::register();
require_once __DIR__ . '/includes/class-generateblocks-card-projection.php';
MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::register();
require_once __DIR__ . '/includes/class-generateblocks-global-styles.php';

/**
 * Supply GenerateBlocks design markers to the vendor-neutral content gate.
 *
 * @param string[] $markers Existing markers supplied by other adapters.
 * @return string[]
 */
function mcp_abilities_generatepress_content_design_markers( array $markers, string $content ): array {
	if ( false !== strpos( $content, '<!-- wp:generateblocks/' ) || preg_match( '/\bgb-(?:container|grid-wrapper|headline|button)-[a-z0-9_-]+\b/i', $content ) ) {
		$markers[] = 'generateblocks';
	}

	return array_values( array_unique( array_filter( array_map( 'sanitize_key', $markers ) ) ) );
}
add_filter( 'mcp_content_design_markup_markers', 'mcp_abilities_generatepress_content_design_markers', 10, 2 );

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
		'generate_blocks_',
		'gb_',
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
 * Check if option name belongs to GenerateBlocks or GenerateBlocks Pro.
 */
function mcp_abilities_generatepress_is_allowed_generateblocks_option_name( string $name ): bool {
	return str_starts_with( $name, 'generateblocks' )
		|| str_starts_with( $name, 'generate_blocks' )
		|| str_starts_with( $name, 'gb_' );
}

/**
 * Check if a meta key is allowed for GeneratePress elements.
 */
function mcp_abilities_generatepress_is_allowed_meta_key( string $key ): bool {
	return str_starts_with( $key, '_generate_' ) || str_starts_with( $key, '_generate-' );
}

/**
 * Default meta keys for GeneratePress elements.
 */
function mcp_abilities_generatepress_default_element_meta_keys(): array {
	return array(
		'_generate_element_type',
		'_generate_element_content',
		'_generate_block_type',
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
 * Idempotently persist one native GeneratePress Block Element.
 *
 * This is the owning PHP Interface used by the public ability and by local
 * Devenia presentation Adapters. Callers provide native GeneratePress display
 * conditions; this Module owns Element identity, storage, and cache invalidation.
 *
 * @param array<string,mixed> $input Element specification.
 * @return array<string,mixed>
 */
function mcp_abilities_generatepress_upsert_block_element( array $input ): array {
	$title              = sanitize_text_field( (string) ( $input['title'] ?? '' ) );
	$slug               = sanitize_title( (string) ( $input['slug'] ?? '' ) );
	$content            = (string) ( $input['content'] ?? '' );
	$block_type         = sanitize_key( (string) ( $input['block_type'] ?? '' ) );
	$hook               = sanitize_text_field( (string) ( $input['hook'] ?? '' ) );
	$status             = sanitize_key( (string) ( $input['status'] ?? 'publish' ) );
	$priority           = (int) ( $input['priority'] ?? 10 );
	$display_conditions = is_array( $input['display_conditions'] ?? null ) ? array_values( $input['display_conditions'] ) : array();
	$exclude_conditions = is_array( $input['exclude_conditions'] ?? null ) ? array_values( $input['exclude_conditions'] ) : array();
	$user_conditions    = is_array( $input['user_conditions'] ?? null ) ? array_values( $input['user_conditions'] ) : array();

	$block_types = array( 'hook', 'content-template', 'loop-template', 'post-meta-template', 'post-navigation-template', 'archive-navigation-template', 'site-header', 'site-footer', 'right-sidebar', 'left-sidebar', 'search-modal' );
	if ( '' === $title || '' === $slug || '' === trim( $content ) || ! in_array( $block_type, $block_types, true ) || empty( $display_conditions ) ) {
		return array( 'success' => false, 'message' => 'title, slug, content, a supported block_type, and display_conditions are required.' );
	}
	if ( 'hook' === $block_type && '' === $hook ) {
		return array( 'success' => false, 'message' => 'hook is required when block_type is hook.' );
	}
	if ( ! in_array( $status, array( 'publish', 'draft' ), true ) ) {
		return array( 'success' => false, 'message' => 'status must be publish or draft.' );
	}
	if ( false === strpos( $content, 'wp:generateblocks/' ) ) {
		return array( 'success' => false, 'message' => 'Block Elements must use native GenerateBlocks markup.' );
	}

	$sanitize_conditions = static function ( array $conditions ): array {
		$sanitized = array();
		foreach ( $conditions as $condition ) {
			if ( ! is_array( $condition ) ) {
				continue;
			}
			$rule = sanitize_text_field( (string) ( $condition['rule'] ?? '' ) );
			if ( '' === $rule ) {
				continue;
			}
			$sanitized[] = array(
				'rule'   => $rule,
				'object' => sanitize_text_field( (string) ( $condition['object'] ?? '' ) ),
			);
		}
		return $sanitized;
	};
	$display_conditions = $sanitize_conditions( $display_conditions );
	$exclude_conditions = $sanitize_conditions( $exclude_conditions );
	$user_conditions    = $sanitize_conditions( $user_conditions );
	if ( empty( $display_conditions ) ) {
		return array( 'success' => false, 'message' => 'At least one valid native GeneratePress display condition is required.' );
	}

	$existing  = get_page_by_path( $slug, OBJECT, 'gp_elements' );
	$post_data = array(
		'post_type'    => 'gp_elements',
		'post_status'  => $status,
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_content' => $content,
	);
	$meta_values = array(
		'_generate_element_type'               => 'block',
		'_generate_element_content'            => $content,
		'_generate_block_type'                  => $block_type,
		'_generate_element_display_conditions' => $display_conditions,
		'_generate_element_exclude_conditions' => $exclude_conditions,
		'_generate_element_user_conditions'    => $user_conditions,
	);
	$absent_meta = array( '_generate_hook_execute_php' );
	if ( 'hook' === $block_type ) {
		$meta_values['_generate_hook_type']     = 'hook';
		$meta_values['_generate_hook']          = $hook;
		$meta_values['_generate_hook_priority'] = (string) $priority;
	} else {
		$absent_meta[] = '_generate_hook_type';
		$absent_meta[] = '_generate_hook';
		$absent_meta[] = '_generate_hook_priority';
	}
	if ( 'content-template' === $block_type ) {
		$tag_name = sanitize_key( (string) ( $input['post_loop_item_tagname'] ?? 'article' ) );
		if ( ! in_array( $tag_name, array( 'article', 'main', 'section', 'div' ), true ) ) {
			$tag_name = 'article';
		}
		$meta_values['_generate_use_theme_post_container'] = ! empty( $input['use_theme_post_container'] ) ? '1' : '';
		$meta_values['_generate_post_loop_item_tagname']   = $tag_name;
	} else {
		$absent_meta[] = '_generate_use_theme_post_container';
		$absent_meta[] = '_generate_post_loop_item_tagname';
	}

	if ( $existing instanceof WP_Post ) {
		$matches =
			$status === $existing->post_status
			&& $title === $existing->post_title
			&& $slug === $existing->post_name
			&& $content === $existing->post_content;
		foreach ( $meta_values as $meta_key => $meta_value ) {
			if ( ! metadata_exists( 'post', $existing->ID, $meta_key ) || $meta_value !== get_post_meta( $existing->ID, $meta_key, true ) ) {
				$matches = false;
				break;
			}
		}
		if ( $matches ) {
			foreach ( $absent_meta as $meta_key ) {
				if ( metadata_exists( 'post', $existing->ID, $meta_key ) ) {
					$matches = false;
					break;
				}
			}
		}
		if ( $matches ) {
			return array(
				'success'            => true,
				'id'                 => (int) $existing->ID,
				'action'             => 'unchanged',
				'block_type'         => $block_type,
				'display_conditions' => $display_conditions,
				'message'            => 'GeneratePress Block Element is already current.',
			);
		}
	}

	$action = 'created';
	if ( $existing instanceof WP_Post ) {
		$post_data['ID'] = $existing->ID;
		$action          = 'updated';
	}
	$post_id = wp_insert_post( $post_data, true );
	if ( is_wp_error( $post_id ) ) {
		return array( 'success' => false, 'message' => $post_id->get_error_message() );
	}

	foreach ( $meta_values as $meta_key => $meta_value ) {
		update_post_meta( (int) $post_id, $meta_key, $meta_value );
	}
	foreach ( $absent_meta as $meta_key ) {
		delete_post_meta( (int) $post_id, $meta_key );
	}
	mcp_abilities_generatepress_invalidate_dynamic_css_cache();

	return array(
		'success'            => true,
		'id'                 => (int) $post_id,
		'action'             => $action,
		'block_type'         => $block_type,
		'display_conditions' => $display_conditions,
		'message'            => 'GeneratePress Block Element ' . $action . ' successfully.',
	);
}

/**
 * Invalidate GeneratePress and GenerateBlocks dynamic CSS without a synchronous site-wide rebuild.
 */
function mcp_abilities_generatepress_invalidate_dynamic_css_cache(): void {
	delete_option( 'generate_dynamic_css_output' );
	delete_option( 'generate_dynamic_css_cached_version' );
	update_option( 'generateblocks_dynamic_css_time', 0, false );
}

/**
 * Get context rules matching the current frontend request.
 */
function mcp_abilities_generatepress_current_display_rules(): array {
	$rules = array( 'general:site' );

	if ( is_front_page() ) {
		$rules[] = 'general:front_page';
	}
	if ( is_home() ) {
		$rules[] = 'general:blog';
	}
	if ( is_archive() ) {
		$rules[] = 'general:archive';
	}
	if ( is_search() ) {
		$rules[] = 'general:search';
	}
	if ( is_404() ) {
		$rules[] = 'general:404';
	}

	return array_values( array_unique( $rules ) );
}

/**
 * Check if a GeneratePress Element condition set matches the current request.
 */
function mcp_abilities_generatepress_conditions_match_current_request( $conditions ): bool {
	if ( ! is_array( $conditions ) || empty( $conditions ) ) {
		return false;
	}

	$current_rules = mcp_abilities_generatepress_current_display_rules();

	foreach ( $conditions as $condition ) {
		if ( ! is_array( $condition ) || empty( $condition['rule'] ) ) {
			continue;
		}

		if ( in_array( (string) $condition['rule'], $current_rules, true ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Include matching GeneratePress Block Element content when GenerateBlocks builds CSS for archive pages.
 */
function mcp_abilities_generatepress_append_matching_element_content_for_generateblocks( string $content ): string {
	if ( is_admin() || ! function_exists( 'generateblocks_get_dynamic_css' ) || ! post_type_exists( 'gp_elements' ) ) {
		return $content;
	}

	$element_ids = get_posts(
		array(
			'post_type'              => 'gp_elements',
			'post_status'            => 'publish',
			'fields'                 => 'ids',
			'posts_per_page'         => 50,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	foreach ( $element_ids as $element_id ) {
		$element_id = (int) $element_id;
		$element    = get_post( $element_id );

		if (
			! $element instanceof WP_Post
			|| 'block' !== get_post_meta( $element_id, '_generate_element_type', true )
			|| false === strpos( $element->post_content, 'wp:generateblocks/' )
		) {
			continue;
		}

		$display_conditions = get_post_meta( $element_id, '_generate_element_display_conditions', true );
		$exclude_conditions = get_post_meta( $element_id, '_generate_element_exclude_conditions', true );

		if (
			! mcp_abilities_generatepress_conditions_match_current_request( $display_conditions )
			|| mcp_abilities_generatepress_conditions_match_current_request( $exclude_conditions )
		) {
			continue;
		}

		$content .= "\n" . $element->post_content;
	}

	return $content;
}
add_filter( 'generateblocks_do_content', 'mcp_abilities_generatepress_append_matching_element_content_for_generateblocks', 20 );

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
 * Discover every published public post that currently owns GenerateBlocks content.
 *
 * The GenerateBlocks registry is a regenerable cache, not an authoritative content
 * inventory. A cache-clear operation therefore must not depend on that registry
 * being complete, especially after a prior targeted invalidation.
 *
 * @return array<int>
 */
function mcp_abilities_generatepress_discover_generateblocks_post_ids(): array {
	$post_types = array_values( get_post_types( array( 'public' => true ), 'names' ) );
	$post_types = array_values( array_diff( $post_types, array( 'attachment' ) ) );
	if ( ! $post_types ) {
		return array();
	}

	$query = new WP_Query(
		array(
			'post_type'              => $post_types,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'cache_results'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	$ids = array();
	foreach ( $query->posts as $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id > 0 && false !== strpos( (string) get_post_field( 'post_content', $post_id, 'raw' ), '<!-- wp:generateblocks/' ) ) {
			$ids[] = $post_id;
		}
	}

	return $ids;
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

		// The frontend request updates this option in a separate PHP process.
		// Recreate it for every post so this request cannot reuse its stale
		// option-cache value and skip the database reset after the first warm.
		delete_option( 'generateblocks_dynamic_css_time' );
		add_option( 'generateblocks_dynamic_css_time', 0, '', false );
		wp_cache_delete( 'generateblocks_dynamic_css_time', 'options' );

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
 * Normalize a GeneratePress page meta input value to the value stored by the theme.
 *
 * @param string $input_key Ability input key.
 * @param mixed  $value     Input value.
 * @return mixed
 */
function mcp_abilities_generatepress_expected_page_meta_value( string $input_key, $value ) {
	if ( 'content_area' === $input_key ) {
		if ( 'full-width' === $value || 'full-width-content' === $value ) {
			return 'true';
		}
		if ( 'contained' === $value ) {
			return 'contained';
		}
		if ( '' === $value ) {
			return '';
		}
	}

	if ( is_bool( $value ) ) {
		return $value ? 'true' : '';
	}

	if ( is_string( $value ) ) {
		return '' === $value ? '' : sanitize_text_field( $value );
	}

	return $value;
}

/**
 * Build expected frontend layout checks from requested GeneratePress page meta.
 *
 * @param array<string,mixed> $input Ability input.
 * @return array{expected_body_classes:array<int,string>,forbidden_body_classes:array<int,string>,forbid_entry_title:bool}
 */
function mcp_abilities_generatepress_frontend_layout_expectations( array $input ): array {
	$expected_body_classes  = array();
	$forbidden_body_classes = array();
	$forbid_entry_title     = false;

	if ( isset( $input['sidebar_layout'] ) && is_string( $input['sidebar_layout'] ) ) {
		$sidebar_layout = $input['sidebar_layout'];
		if ( '' !== $sidebar_layout ) {
			$expected_body_classes[] = $sidebar_layout;
		}
		if ( 'no-sidebar' === $sidebar_layout ) {
			$forbidden_body_classes = array_merge(
				$forbidden_body_classes,
				array( 'right-sidebar', 'left-sidebar', 'both-sidebars', 'both-left', 'both-right' )
			);
		}
	}

	if ( isset( $input['content_area'] ) && is_string( $input['content_area'] ) ) {
		$content_area = $input['content_area'];
		if ( 'full-width' === $content_area || 'full-width-content' === $content_area ) {
			$expected_body_classes[]  = 'full-width-content';
			$forbidden_body_classes[] = 'contained-content';
		} elseif ( 'contained' === $content_area ) {
			$expected_body_classes[]  = 'contained-content';
			$forbidden_body_classes[] = 'full-width-content';
		}
	}

	if ( isset( $input['disable_headline'] ) && true === $input['disable_headline'] ) {
		$forbid_entry_title = true;
	}

	return array(
		'expected_body_classes'  => array_values( array_unique( $expected_body_classes ) ),
		'forbidden_body_classes' => array_values( array_unique( $forbidden_body_classes ) ),
		'forbid_entry_title'     => $forbid_entry_title,
	);
}

/**
 * Build expected GeneratePress page-meta values from audit input.
 *
 * @param array<string,mixed> $input Ability input.
 * @return array<string,string>
 */
function mcp_abilities_generatepress_expected_layout_meta_from_input( array $input ): array {
	$expected = array();

	$defaults = array(
		'disable_headline' => true,
		'sidebar_layout'   => 'no-sidebar',
		'content_area'     => 'full-width-content',
	);

	foreach ( $defaults as $input_key => $default_value ) {
		$value = array_key_exists( $input_key, $input ) ? $input[ $input_key ] : $default_value;
		if ( null === $value ) {
			continue;
		}
		$expected[ $input_key ] = (string) mcp_abilities_generatepress_expected_page_meta_value( $input_key, $value );
	}

	return $expected;
}

/**
 * Collect posts for a GeneratePress layout meta audit.
 *
 * @param array<string,mixed> $input Ability input.
 * @return array{posts:array<int,WP_Post>,message:string}
 */
function mcp_abilities_generatepress_collect_layout_audit_posts( array $input ): array {
	$post_type = isset( $input['post_type'] ) ? sanitize_key( (string) $input['post_type'] ) : 'page';
	$status    = isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : 'publish';
	$limit     = isset( $input['limit'] ) ? max( 1, min( 500, (int) $input['limit'] ) ) : 100;

	$args = array(
		'post_type'              => $post_type,
		'post_status'            => $status,
		'posts_per_page'         => $limit,
		'orderby'                => 'modified',
		'order'                  => 'DESC',
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	);

	if ( ! empty( $input['ids'] ) && is_array( $input['ids'] ) ) {
		$ids = array_values(
			array_filter(
				array_map( 'absint', $input['ids'] )
			)
		);

		$args['post__in']       = array_slice( $ids, 0, $limit );
		$args['orderby']        = 'post__in';
		$args['posts_per_page'] = count( $args['post__in'] );
	} elseif ( array_key_exists( 'parent', $input ) ) {
		$parent              = absint( $input['parent'] );
		$include_descendants = ! empty( $input['include_descendants'] );

		if ( $include_descendants ) {
			$descendants = get_pages(
				array(
					'post_type'   => $post_type,
					'post_status' => $status,
					'child_of'    => $parent,
					'sort_column' => 'post_modified',
					'sort_order'  => 'DESC',
					'number'      => $limit,
				)
			);

			return array(
				'posts'   => array_values(
					array_filter(
						$descendants,
						static function ( $post ): bool {
							return $post instanceof WP_Post;
						}
					)
				),
				'message' => 'Collected descendant pages with get_pages().',
			);
		}

		$args['post_parent'] = $parent;
	}

	$query = new WP_Query( $args );

	return array(
		'posts'   => array_values(
			array_filter(
				$query->posts,
				static function ( $post ): bool {
					return $post instanceof WP_Post;
				}
			)
		),
		'message' => 'Collected posts with WP_Query.',
	);
}

/**
 * Return missing required content patterns for a post.
 *
 * @param WP_Post           $post     Post object.
 * @param array<int,string> $patterns Required literal content patterns.
 * @return array<int,string>
 */
function mcp_abilities_generatepress_missing_content_patterns( WP_Post $post, array $patterns ): array {
	$missing = array();
	$content = (string) $post->post_content;

	foreach ( $patterns as $pattern ) {
		$pattern = (string) $pattern;
		if ( '' === $pattern ) {
			continue;
		}
		if ( ! str_contains( $content, $pattern ) ) {
			$missing[] = $pattern;
		}
	}

	return $missing;
}

/**
 * Extract body classes from frontend HTML.
 *
 * @param string $html Frontend HTML.
 * @return array<int,string>
 */
function mcp_abilities_generatepress_extract_body_classes( string $html ): array {
	if ( ! preg_match( '/<body[^>]*class=["\']([^"\']*)["\']/i', $html, $matches ) ) {
		return array();
	}

	return array_values( array_filter( preg_split( '/\s+/', trim( html_entity_decode( $matches[1], ENT_QUOTES ) ) ) ) );
}

/**
 * Verify the public frontend layout after GeneratePress page meta changes.
 *
 * @param int                 $post_id      Post ID.
 * @param string              $frontend_url Frontend URL to verify.
 * @param array<string,mixed> $expectations Layout expectations.
 * @return array<string,mixed>
 */
function mcp_abilities_generatepress_verify_frontend_page_layout( int $post_id, string $frontend_url, array $expectations ): array {
	$frontend_url = esc_url_raw( $frontend_url );
	if ( '' === $frontend_url ) {
		return array(
			'success'  => false,
			'url'      => '',
			'errors'   => array( 'frontend_url_empty' ),
			'message'  => 'No frontend URL available for layout verification.',
		);
	}

	$response = wp_remote_get(
		$frontend_url,
		array(
			'timeout'     => 15,
			'redirection' => 3,
			'user-agent'  => 'MCP Abilities GeneratePress layout verifier; post=' . $post_id,
		)
	);

	if ( is_wp_error( $response ) ) {
		return array(
			'success' => false,
			'url'     => $frontend_url,
			'errors'  => array( $response->get_error_code() ),
			'message' => $response->get_error_message(),
		);
	}

	$status = (int) wp_remote_retrieve_response_code( $response );
	$html   = (string) wp_remote_retrieve_body( $response );
	if ( $status < 200 || $status >= 300 ) {
		return array(
			'success' => false,
			'url'     => $frontend_url,
			'status'  => $status,
			'errors'  => array( 'http_' . $status ),
			'message' => 'Frontend layout verification failed because the URL did not return HTTP 2xx.',
		);
	}

	$body_classes = mcp_abilities_generatepress_extract_body_classes( $html );
	$errors       = array();

	foreach ( $expectations['expected_body_classes'] as $class_name ) {
		if ( ! in_array( $class_name, $body_classes, true ) ) {
			$errors[] = 'missing_body_class:' . $class_name;
		}
	}

	foreach ( $expectations['forbidden_body_classes'] as $class_name ) {
		if ( in_array( $class_name, $body_classes, true ) ) {
			$errors[] = 'forbidden_body_class:' . $class_name;
		}
	}

	$entry_title_count = substr_count( $html, 'class="entry-title' ) + substr_count( $html, "class='entry-title" );
	if ( ! empty( $expectations['forbid_entry_title'] ) && $entry_title_count > 0 ) {
		$errors[] = 'entry_title_still_visible';
	}

	return array(
		'success'                   => empty( $errors ),
		'url'                       => $frontend_url,
		'status'                    => $status,
		'body_classes'              => $body_classes,
		'expected_body_classes'     => $expectations['expected_body_classes'],
		'forbidden_body_classes'    => $expectations['forbidden_body_classes'],
		'entry_title_count'         => $entry_title_count,
		'errors'                    => $errors,
		'message'                   => empty( $errors ) ? 'Frontend layout verified.' : 'Frontend layout did not match requested GeneratePress meta. Purge cache and retry verification before declaring the page fixed.',
	);
}

/**
 * Check whether block content contains a visible top-level heading.
 *
 * @param array<int,array<string,mixed>> $blocks Parsed Gutenberg blocks.
 */
function mcp_abilities_generatepress_blocks_have_h1( array $blocks ): bool {
	foreach ( $blocks as $block ) {
		$block_name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
		$attrs      = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

		if ( 'core/heading' === $block_name ) {
			$level = isset( $attrs['level'] ) ? (int) $attrs['level'] : 2;
			if ( 1 === $level ) {
				return true;
			}
		}

		if ( 'generateblocks/headline' === $block_name && isset( $attrs['element'] ) && 'h1' === strtolower( (string) $attrs['element'] ) ) {
			return true;
		}

		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) && mcp_abilities_generatepress_blocks_have_h1( $block['innerBlocks'] ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Check whether post content contains a Gutenberg H1 block.
 */
function mcp_abilities_generatepress_content_has_h1( string $content ): bool {
	if ( '' === trim( $content ) ) {
		return false;
	}

	return mcp_abilities_generatepress_blocks_have_h1( parse_blocks( $content ) );
}

/**
 * Keep GeneratePress from rendering a duplicate title when page content owns the H1.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @param bool    $update  Whether this is an existing post being updated.
 */
function mcp_abilities_generatepress_sync_page_headline_visibility( int $post_id, WP_Post $post, bool $update ): void {
	unset( $update );

	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( 'page' !== $post->post_type || 'auto-draft' === $post->post_status ) {
		return;
	}

	$content = (string) $post->post_content;

	if ( mcp_abilities_generatepress_content_has_h1( $content ) ) {
		update_post_meta( $post_id, '_generate-disable-headline', 'true' );
	}
}
add_action( 'save_post_page', 'mcp_abilities_generatepress_sync_page_headline_visibility', 20, 3 );

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
 * Discover GeneratePress module-like settings options currently stored.
 */
function mcp_abilities_generatepress_discover_module_setting_options(): array {
	global $wpdb;

	$known = mcp_abilities_generatepress_module_settings_map();
	$items = array();

	foreach ( $known as $module => $option_name ) {
		$value = get_option( $option_name, null );
		$items[ $option_name ] = array(
			'module'      => $module,
			'option_name' => $option_name,
			'present'     => null !== $value,
			'type'        => gettype( $value ),
			'keys'        => is_array( $value ) ? array_values( array_map( 'strval', array_keys( $value ) ) ) : array(),
		);
	}

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Option discovery uses a prepared LIKE pattern.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT option_name FROM ' . $wpdb->options . ' WHERE option_name LIKE %s ORDER BY option_name ASC',
			$wpdb->esc_like( 'generate_' ) . '%_settings'
		),
		ARRAY_A
	);

	foreach ( is_array( $rows ) ? $rows : array() as $row ) {
		$option_name = isset( $row['option_name'] ) ? (string) $row['option_name'] : '';
		if ( '' === $option_name || isset( $items[ $option_name ] ) ) {
			continue;
		}

		$value  = get_option( $option_name, array() );
		$module = preg_replace( '/^generate_|_settings$/', '', $option_name );
		$module = is_string( $module ) ? $module : $option_name;

		$items[ $option_name ] = array(
			'module'      => $module,
			'option_name' => $option_name,
			'present'     => true,
			'type'        => gettype( $value ),
			'keys'        => is_array( $value ) ? array_values( array_map( 'strval', array_keys( $value ) ) ) : array(),
		);
	}

	return array_values( $items );
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
				'letterSpacing' => 'body_letter_spacing',
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
				'letterSpacing' => 'navigation_letter_spacing',
				'textTransform' => 'navigation_font_transform',
			),
		),
		'subnavigation' => array(
			'selector' => 'primary-sub-menu-items',
			'rule_group' => 'primaryNavigation',
			'keys'     => array(
				'fontFamily'    => 'font_subnavigation',
				'fontWeight'    => 'subnavigation_font_weight',
				'fontSize'      => 'subnavigation_font_size',
				'letterSpacing' => 'subnavigation_letter_spacing',
				'textTransform' => 'subnavigation_font_transform',
			),
		),
		'buttons'    => array(
			'selector' => 'buttons',
			'rule_group' => 'content',
			'keys'     => array(
				'fontFamily'    => 'font_buttons',
				'fontWeight'    => 'buttons_font_weight',
				'fontSize'      => 'buttons_font_size',
				'letterSpacing' => 'buttons_letter_spacing',
				'textTransform' => 'buttons_font_transform',
			),
		),
		'html' => array(
			'selector' => 'html',
			'rule_group' => 'base',
			'keys'     => array(
				'fontFamily'    => null,
				'fontWeight'    => null,
				'fontSize'      => null,
				'fontSizeTablet' => null,
				'fontSizeMobile' => null,
				'lineHeight'    => null,
				'lineHeightTablet' => null,
				'lineHeightMobile' => null,
				'letterSpacing' => null,
				'letterSpacingTablet' => null,
				'letterSpacingMobile' => null,
				'textTransform' => null,
				'textDecoration' => null,
				'fontStyle'     => null,
				'marginBottom'  => null,
				'marginBottomTablet' => null,
				'marginBottomMobile' => null,
				'marginBottomUnit' => null,
			),
		),
		'site_title' => array(
			'selector' => 'site-title',
			'rule_group' => 'header',
			'keys'     => array(
				'fontFamily'    => 'font_site_title',
				'fontWeight'    => 'site_title_font_weight',
				'fontSize'      => 'site_title_font_size',
				'fontSizeTablet' => 'tablet_site_title_font_size',
				'fontSizeMobile' => 'mobile_site_title_font_size',
				'textTransform' => 'site_title_font_transform',
			),
		),
		'mobile_navigation_site_title' => array(
			'selector' => 'mobile-navigation-site-title',
			'rule_group' => 'header',
			'keys'     => array(
				'fontSize' => 'mobile_navigation_site_title_font_size',
			),
		),
		'site_tagline' => array(
			'selector' => 'site-description',
			'rule_group' => 'header',
			'keys'     => array(
				'fontFamily'    => 'font_site_tagline',
				'fontWeight'    => 'site_tagline_font_weight',
				'fontSize'      => 'site_tagline_font_size',
				'textTransform' => 'site_tagline_font_transform',
			),
		),
		'entry_meta' => array(
			'selector' => '.entry-meta, .entry-meta a, .posted-on, .posted-on a, .cat-links, .cat-links a',
			'rule_group' => 'content',
			'keys'     => array(
				'fontFamily'    => null,
				'fontWeight'    => null,
				'fontSize'      => null,
				'lineHeight'    => null,
				'letterSpacing' => null,
				'textTransform' => null,
			),
		),
		'sidebar_widget_title' => array(
			'selector' => '.sidebar .widget .widget-title',
			'rule_group' => 'sidebar',
			'keys'     => array(
				'fontFamily'    => null,
				'fontWeight'    => null,
				'fontSize'      => null,
				'lineHeight'    => null,
				'letterSpacing' => null,
				'textTransform' => null,
			),
		),
		'sidebar_widget_text' => array(
			'selector' => '.sidebar .widget',
			'rule_group' => 'sidebar',
			'keys'     => array(
				'fontFamily'    => null,
				'fontWeight'    => null,
				'fontSize'      => null,
				'lineHeight'    => null,
				'letterSpacing' => null,
				'textTransform' => null,
			),
		),
		'footer_widget_title' => array(
			'selector' => '.footer-widgets .widget-title',
			'rule_group' => 'footer',
			'keys'     => array(
				'fontFamily'    => null,
				'fontWeight'    => null,
				'fontSize'      => null,
				'lineHeight'    => null,
				'letterSpacing' => null,
				'textTransform' => null,
			),
		),
		'footer_widget_text' => array(
			'selector' => '.footer-widgets',
			'rule_group' => 'footer',
			'keys'     => array(
				'fontFamily'    => null,
				'fontWeight'    => null,
				'fontSize'      => null,
				'lineHeight'    => null,
				'letterSpacing' => null,
				'textTransform' => null,
			),
		),
		'footer_bar_text' => array(
			'selector' => '.site-info',
			'rule_group' => 'footer',
			'keys'     => array(
				'fontFamily'    => null,
				'fontWeight'    => null,
				'fontSize'      => null,
				'lineHeight'    => null,
				'letterSpacing' => null,
				'textTransform' => null,
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
				'letterSpacing'  => 'heading_' . $level . '_letter_spacing',
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
			if ( is_string( $setting_key ) && '' !== $setting_key ) {
				unset( $settings[ $setting_key ] );
			}
			$rule_updates[ $input_key ] = null;
			$changed[] = is_string( $setting_key ) && '' !== $setting_key ? $setting_key : 'typography.' . $group . '.' . $input_key;
			continue;
		}

		if ( is_string( $setting_key ) && '' !== $setting_key ) {
			$settings[ $setting_key ] = $value;
			$changed[] = $setting_key;
		} else {
			$changed[] = 'typography.' . $group . '.' . $input_key;
		}

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
 * Check if a value can be safely stored as a flat GeneratePress setting.
 */
function mcp_abilities_generatepress_is_flat_setting_value( $value ): bool {
	if ( null === $value || is_scalar( $value ) ) {
		return true;
	}

	if ( ! is_array( $value ) ) {
		return false;
	}

	foreach ( $value as $item ) {
		if ( is_array( $item ) ) {
			foreach ( $item as $nested_value ) {
				if ( is_array( $nested_value ) || is_object( $nested_value ) ) {
					return false;
				}
			}
			continue;
		}

		if ( is_object( $item ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Known GeneratePress setting groups used for discovery, auditing, and safer updates.
 *
 * GeneratePress stores the authoritative theme design state in one option:
 * generate_settings. The exact key set can vary by GP/GP Premium version, so MCP
 * abilities must discover the live keys and classify them instead of pretending a
 * static schema is complete forever.
 */
function mcp_abilities_generatepress_setting_groups(): array {
	return array(
		'colors'        => array(
			'global_colors',
			'background_color',
			'text_color',
			'link_color',
			'link_color_hover',
			'link_color_visited',
			'header_background_color',
			'header_text_color',
			'header_link_color',
			'header_link_hover_color',
			'navigation_background_color',
			'navigation_text_color',
			'navigation_background_hover_color',
			'navigation_text_hover_color',
			'navigation_background_current_color',
			'navigation_text_current_color',
			'subnavigation_background_color',
			'subnavigation_text_color',
			'subnavigation_background_hover_color',
			'subnavigation_text_hover_color',
			'subnavigation_background_current_color',
			'subnavigation_text_current_color',
			'content_background_color',
			'content_text_color',
			'content_link_color',
			'content_link_hover_color',
			'sidebar_widget_background_color',
			'sidebar_widget_title_color',
			'sidebar_widget_text_color',
			'footer_widget_background_color',
			'footer_widget_title_color',
			'footer_widget_text_color',
			'footer_widget_link_color',
			'footer_widget_link_hover_color',
			'footer_background_color',
			'footer_text_color',
			'footer_link_color',
			'footer_link_hover_color',
			'entry_meta_text_color',
			'entry_meta_link_color',
			'entry_meta_link_color_hover',
			'form_background_color',
			'form_text_color',
			'form_background_color_focus',
			'form_text_color_focus',
			'form_border_color',
			'form_border_color_focus',
			'form_button_background_color',
			'form_button_background_color_hover',
			'form_button_text_color',
			'form_button_text_color_hover',
			'top_bar_background_color',
			'top_bar_text_color',
			'top_bar_link_color',
			'top_bar_link_color_hover',
			'navigation_search_background_color',
			'navigation_search_text_color',
		),
		'typography'    => array(
			'font_manager',
			'typography',
			'font_body',
			'body_font_weight',
			'body_font_transform',
			'body_font_size',
			'body_line_height',
			'body_letter_spacing',
			'font_site_title',
			'site_title_font_size',
			'tablet_site_title_font_size',
			'mobile_site_title_font_size',
			'mobile_navigation_site_title_font_size',
			'site_title_font_weight',
			'site_title_font_transform',
			'font_site_tagline',
			'site_tagline_font_size',
			'site_tagline_font_weight',
			'site_tagline_font_transform',
			'font_navigation',
			'navigation_font_weight',
			'navigation_font_transform',
			'navigation_font_size',
			'navigation_letter_spacing',
			'font_subnavigation',
			'subnavigation_font_weight',
			'subnavigation_font_transform',
			'subnavigation_font_size',
			'subnavigation_letter_spacing',
			'font_buttons',
			'buttons_font_weight',
			'buttons_font_transform',
			'buttons_font_size',
			'buttons_letter_spacing',
			'buttons_line_height',
		),
		'layout'        => array(
			'container_width',
			'container_alignment',
			'content_layout_setting',
			'content_width',
			'layout_setting',
			'blog_layout_setting',
			'single_layout_setting',
			'sidebar_width',
			'left_sidebar_width',
			'right_sidebar_width',
			'header_layout_setting',
			'header_inner_width',
			'header_alignment_setting',
			'nav_layout_setting',
			'nav_inner_width',
			'nav_alignment_setting',
			'nav_position_setting',
			'nav_drop_point',
			'nav_dropdown_type',
			'nav_dropdown_direction',
			'nav_search',
			'nav_search_modal',
			'mobile_header',
			'mobile_header_sticky',
			'footer_layout_setting',
			'footer_inner_width',
			'footer_widget_setting',
			'footer_bar_alignment',
			'top_bar_width',
			'top_bar_inner_width',
			'top_bar_alignment',
			'back_to_top',
		),
		'spacing'       => array(
			'top_bar_top',
			'top_bar_right',
			'top_bar_bottom',
			'top_bar_left',
			'header_top',
			'header_right',
			'header_bottom',
			'header_left',
			'menu_item',
			'menu_item_height',
			'sub_menu_item_height',
			'content_top',
			'content_right',
			'content_bottom',
			'content_left',
			'separator',
			'widget_top',
			'widget_right',
			'widget_bottom',
			'widget_left',
			'footer_widget_container_top',
			'footer_widget_container_right',
			'footer_widget_container_bottom',
			'footer_widget_container_left',
			'footer_top',
			'footer_right',
			'footer_bottom',
			'footer_left',
		),
		'buttons'       => array(
			'form_button_background_color',
			'form_button_background_color_hover',
			'form_button_text_color',
			'form_button_text_color_hover',
			'form_button_border_color',
			'form_button_border_color_hover',
			'form_button_border_radius',
			'button_padding_top',
			'button_padding_right',
			'button_padding_bottom',
			'button_padding_left',
		),
		'site_identity' => array(
			'hide_title',
			'hide_tagline',
			'logo',
			'retina_logo',
			'logo_width',
			'inline_logo_site_branding',
			'custom_logo',
			'site_title_color',
			'site_tagline_color',
		),
	);
}

/**
 * Classify a live generate_settings key into a broad GeneratePress control group.
 */
function mcp_abilities_generatepress_classify_setting_key( string $key ): string {
	foreach ( mcp_abilities_generatepress_setting_groups() as $group => $keys ) {
		if ( in_array( $key, $keys, true ) ) {
			return $group;
		}
	}

	if ( preg_match( '/^(font_|body_|heading_|mobile_heading_|site_title_|site_tagline_|navigation_|subnavigation_|buttons_).*(font|weight|size|line_height|letter_spacing|transform)|^(font_heading_)/', $key ) ) {
		return 'typography';
	}
	if ( str_ends_with( $key, '_color' ) || str_contains( $key, '_background_color' ) || str_contains( $key, '_text_color' ) || str_contains( $key, '_link_color' ) ) {
		return 'colors';
	}
	if ( preg_match( '/^(container_|content_|layout_|blog_layout_|single_layout_|header_|nav_|footer_|top_bar_|sidebar_|mobile_header|back_to_top)/', $key ) ) {
		return 'layout';
	}
	if ( preg_match( '/(padding|spacing|margin|separator|_top$|_right$|_bottom$|_left$|_height$)/', $key ) ) {
		return 'spacing';
	}
	if ( str_starts_with( $key, 'form_button_' ) || str_starts_with( $key, 'button_' ) ) {
		return 'buttons';
	}
	if ( in_array( $key, array( 'hide_title', 'hide_tagline', 'logo', 'retina_logo', 'custom_logo', 'logo_width' ), true ) ) {
		return 'site_identity';
	}

	return 'other';
}

/**
 * Allowed theme mods that affect GeneratePress/customizer-owned rendering.
 */
function mcp_abilities_generatepress_allowed_theme_mod_key( string $key ): bool {
	if ( in_array( $key, array( 'custom_logo', 'custom_css_post_id', 'nav_menu_locations' ), true ) ) {
		return true;
	}

	return str_starts_with( $key, 'generate_' ) || str_starts_with( $key, 'gp_' );
}

/**
 * Get registered image sizes with dimensions.
 */
function mcp_abilities_generatepress_image_sizes(): array {
	$sizes  = array();
	$names  = get_intermediate_image_sizes();
	$names[] = 'full';
	$names  = array_values( array_unique( $names ) );

	foreach ( $names as $name ) {
		if ( 'full' === $name ) {
			$sizes[ $name ] = array(
				'width'  => 0,
				'height' => 0,
				'crop'   => false,
			);
			continue;
		}
		$sizes[ $name ] = array(
			'width'  => (int) get_option( "{$name}_size_w", 0 ),
			'height' => (int) get_option( "{$name}_size_h", 0 ),
			'crop'   => (bool) get_option( "{$name}_crop", false ),
		);
	}

	return $sizes;
}

/**
 * Audit one attachment for generated image sizes.
 */
function mcp_abilities_generatepress_audit_attachment_image_sizes( int $attachment_id, array $size_names ): array {
	$metadata = wp_get_attachment_metadata( $attachment_id );
	$metadata = is_array( $metadata ) ? $metadata : array();
	$files    = isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ? $metadata['sizes'] : array();
	$result   = array();

	foreach ( $size_names as $size_name ) {
		if ( 'full' === $size_name ) {
			$src = wp_get_attachment_image_src( $attachment_id, 'full' );
			$result[ $size_name ] = array(
				'exists' => (bool) $src,
				'url'    => $src ? $src[0] : '',
				'width'  => $src ? (int) $src[1] : 0,
				'height' => $src ? (int) $src[2] : 0,
			);
			continue;
		}

		$src = wp_get_attachment_image_src( $attachment_id, $size_name );
		$result[ $size_name ] = array(
			'exists'   => isset( $files[ $size_name ] ) && is_array( $files[ $size_name ] ),
			'fallback' => $src && empty( $files[ $size_name ] ),
			'url'      => $src ? $src[0] : '',
			'width'    => $src ? (int) $src[1] : 0,
			'height'   => $src ? (int) $src[2] : 0,
		);
	}

	return $result;
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
 * Register an ability with MCP adapter-compatible empty input handling.
 *
 * Some MCP clients send omitted/empty JSON input as null and populated JSON
 * objects as PHP arrays before schema validation. For abilities with only
 * optional input fields, accept that transport shape and normalize callbacks
 * to an empty array without relaxing abilities that require IDs,
 * confirmations, or payloads.
 */
function mcp_abilities_generatepress_register_ability( string $name, array $args ): void {
	if (
		isset( $args['input_schema'] )
		&& is_array( $args['input_schema'] )
		&& isset( $args['input_schema']['type'] )
		&& 'object' === $args['input_schema']['type']
		&& empty( $args['input_schema']['required'] )
	) {
		$args['input_schema']['type'] = array( 'object', 'array', 'null' );
		if ( isset( $args['execute_callback'] ) && is_callable( $args['execute_callback'] ) ) {
			$callback                 = $args['execute_callback'];
			$args['execute_callback'] = static function ( $input = array() ) use ( $callback ) {
				return call_user_func( $callback, is_array( $input ) ? $input : array() );
			};
		}
	}

	wp_register_ability( $name, $args );
}

/**
 * Call a local WordPress REST route from an ability.
 */
function mcp_abilities_generatepress_rest_request( string $method, string $route, array $params = array(), array $headers = array() ): array {
	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	foreach ( $headers as $key => $value ) {
		if ( '' !== (string) $value ) {
			$request->set_header( $key, (string) $value );
		}
	}

	$response = rest_do_request( $request );
	if ( is_wp_error( $response ) ) {
		return array(
			'success' => false,
			'status'  => 500,
			'data'    => array(),
			'message' => $response->get_error_message(),
		);
	}

	$server = rest_get_server();
	$data   = $server->response_to_data( $response, false );
	$status = $response->get_status();

	return array(
		'success' => $status >= 200 && $status < 300,
		'status'  => $status,
		'data'    => $data,
		'message' => $status >= 200 && $status < 300 ? 'REST request completed.' : 'REST request failed.',
	);
}

/**
 * Normalize GenerateBlocks Pattern Library REST response data.
 */
function mcp_abilities_generatepress_pattern_response_data( array $response ) {
	$data = $response['data'] ?? array();
	if ( is_array( $data ) && array_key_exists( 'response', $data ) ) {
		$response_data = $data['response'];
		if ( is_array( $response_data ) && array_key_exists( 'data', $response_data ) ) {
			return $response_data['data'];
		}
		return $response_data;
	}
	if ( is_array( $data ) && array_key_exists( 'data', $data ) ) {
		return $data['data'];
	}
	return $data;
}

/**
 * Convert a GenerateBlocks pattern library DTO into a redaction-friendly array.
 *
 * @param mixed $library GenerateBlocks library DTO or array.
 */
function mcp_abilities_generatepress_pattern_library_to_array( $library ): array {
	if ( is_array( $library ) ) {
		return $library;
	}

	if ( ! is_object( $library ) ) {
		return array();
	}

	return array(
		'id'        => (string) $library->id,
		'name'      => (string) $library->name,
		'domain'    => (string) $library->domain,
		'publicKey' => (string) $library->public_key,
		'isEnabled' => (bool) $library->is_enabled,
		'isDefault' => (bool) $library->is_default,
		'isLocal'   => (bool) $library->is_local,
	);
}

/**
 * Get GenerateBlocks pattern libraries from the same registry the editor uses.
 */
function mcp_abilities_generatepress_get_pattern_libraries(): array {
	if ( ! class_exists( 'GenerateBlocks_Libraries' ) ) {
		return array();
	}

	$libraries = GenerateBlocks_Libraries::get_instance()->get_all( false );
	if ( ! is_array( $libraries ) ) {
		return array();
	}

	return array_values(
		array_filter(
			array_map( 'mcp_abilities_generatepress_pattern_library_to_array', $libraries )
		)
	);
}

/**
 * Find a GenerateBlocks pattern library by ID.
 */
function mcp_abilities_generatepress_find_pattern_library( string $library_id ): ?array {
	foreach ( mcp_abilities_generatepress_get_pattern_libraries() as $library ) {
		if ( is_array( $library ) && isset( $library['id'] ) && $library_id === (string) $library['id'] ) {
			return $library;
		}
	}
	return null;
}

/**
 * Remove sensitive fields before returning pattern library metadata.
 */
function mcp_abilities_generatepress_public_pattern_library( array $library, bool $include_key_hint = false ): array {
	$public_key = isset( $library['publicKey'] ) ? (string) $library['publicKey'] : '';
	unset( $library['publicKey'] );
	$library['has_public_key'] = '' !== $public_key;
	return $library;
}

/**
 * Get GenerateBlocks Pattern Library categories or patterns.
 */
function mcp_abilities_generatepress_get_pattern_library_items( string $kind, string $library_id, string $category_id = '', string $search = '' ): array {
	$library = mcp_abilities_generatepress_find_pattern_library( $library_id );
	if ( null === $library ) {
		return array(
			'success' => false,
			'status'  => 404,
			'items'   => array(),
			'message' => 'Pattern library not found.',
		);
	}

	$is_local   = ! empty( $library['isLocal'] );
	$route_base = $is_local ? '/generateblocks-pro/v1' : '/generateblocks/v1';
	$route      = $route_base . '/pattern-library/' . ( 'categories' === $kind ? 'categories' : 'patterns' );
	$params     = array(
		'libraryId' => $library_id,
		'isLocal'   => $is_local ? 'true' : 'false',
	);

	if ( 'patterns' === $kind ) {
		$params['categoryId'] = $category_id;
		$params['search']     = $search;
	}

	$response = mcp_abilities_generatepress_rest_request(
		'GET',
		$route,
		$params,
		array(
			'X-GB-Public-Key' => isset( $library['publicKey'] ) ? (string) $library['publicKey'] : '',
			'Host'            => wp_parse_url( home_url(), PHP_URL_HOST ),
		)
	);

	$data = mcp_abilities_generatepress_pattern_response_data( $response );
	return array(
		'success' => ! empty( $response['success'] ) && is_array( $data ),
		'status'  => (int) ( $response['status'] ?? 0 ),
		'items'   => is_array( $data ) ? $data : array(),
		'message' => is_array( $data ) ? 'Pattern library items retrieved.' : (string) ( $response['message'] ?? 'Pattern library request failed.' ),
		'library' => mcp_abilities_generatepress_public_pattern_library( $library ),
	);
}

/**
 * Strip pattern markup unless explicitly requested.
 */
function mcp_abilities_generatepress_public_pattern_item( array $pattern, bool $include_pattern = false ): array {
	if ( ! $include_pattern ) {
		unset( $pattern['pattern'] );
		unset( $pattern['preview'] );
	}
	return $pattern;
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
	mcp_abilities_generatepress_register_ability(
		'generatepress/get-info',
		array(
			'label'               => 'Get GeneratePress Theme Info',
			'description'         => 'Get active theme information and GeneratePress Premium status.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'force' => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'Compatibility flag for clients that require at least one object property.',
					),
				),
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
	mcp_abilities_generatepress_register_ability(
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
	mcp_abilities_generatepress_register_ability(
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
	mcp_abilities_generatepress_register_ability(
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
	mcp_abilities_generatepress_register_ability(
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
	mcp_abilities_generatepress_register_ability(
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
					'body_letter_spacing',
					'font_heading_1', 'heading_1_weight', 'heading_1_transform', 'heading_1_font_size', 'mobile_heading_1_font_size', 'heading_1_line_height', 'heading_1_letter_spacing',
					'font_heading_2', 'heading_2_weight', 'heading_2_transform', 'heading_2_font_size', 'mobile_heading_2_font_size', 'heading_2_line_height', 'heading_2_letter_spacing',
					'font_heading_3', 'heading_3_weight', 'heading_3_transform', 'heading_3_font_size', 'mobile_heading_3_font_size', 'heading_3_line_height', 'heading_3_letter_spacing',
					'font_heading_4', 'heading_4_weight', 'heading_4_transform', 'heading_4_font_size', 'mobile_heading_4_font_size', 'heading_4_line_height', 'heading_4_letter_spacing',
					'font_heading_5', 'heading_5_weight', 'heading_5_transform', 'heading_5_font_size', 'mobile_heading_5_font_size', 'heading_5_line_height', 'heading_5_letter_spacing',
					'font_heading_6', 'heading_6_weight', 'heading_6_transform', 'heading_6_font_size', 'mobile_heading_6_font_size', 'heading_6_line_height', 'heading_6_letter_spacing',
					'font_navigation', 'navigation_font_weight', 'navigation_font_transform', 'navigation_font_size', 'navigation_letter_spacing',
					'font_buttons', 'buttons_font_weight', 'buttons_font_transform', 'buttons_font_size', 'buttons_letter_spacing',
				);

				// Layout keys.
				$layout_keys = array(
					'container_width', 'content_layout_setting', 'content_width',
					'layout_setting', 'blog_layout_setting', 'single_layout_setting',
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

	mcp_abilities_generatepress_register_ability(
		'generatepress/list-control-surface',
		array(
			'label'               => 'List GeneratePress Control Surface',
			'description'         => 'Discovers the active GeneratePress, GeneratePress Premium, GenerateBlocks, and Pro control surfaces available on this site.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'include_values' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Include full option values where practical.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'         => array( 'type' => 'boolean' ),
					'theme'           => array( 'type' => 'object' ),
					'plugins'         => array( 'type' => 'object' ),
					'options'         => array( 'type' => 'array' ),
					'theme_mods'      => array( 'type' => 'array' ),
					'custom_css'      => array( 'type' => 'object' ),
					'module_settings' => array( 'type' => 'array' ),
					'elements'        => array( 'type' => 'object' ),
					'generateblocks'  => array( 'type' => 'object' ),
					'message'         => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				global $wpdb;

				$include_values = ! empty( $input['include_values'] );
				$theme_info     = mcp_abilities_generatepress_get_theme_info();
				$stylesheet     = get_stylesheet();

				$plugin_versions = array(
					'generatepress_premium' => defined( 'GP_PREMIUM_VERSION' ) ? GP_PREMIUM_VERSION : '',
					'generateblocks'        => defined( 'GENERATEBLOCKS_VERSION' ) ? GENERATEBLOCKS_VERSION : '',
					'generateblocks_pro'    => defined( 'GENERATEBLOCKS_PRO_VERSION' ) ? GENERATEBLOCKS_PRO_VERSION : '',
				);

				$conditions = array();
				foreach ( mcp_abilities_generatepress_allowed_option_prefixes() as $prefix ) {
					$conditions[] = $wpdb->prepare( 'option_name LIKE %s', $wpdb->esc_like( $prefix ) . '%' );
				}
				foreach ( mcp_abilities_generatepress_allowed_option_names() as $name ) {
					$conditions[] = $wpdb->prepare( 'option_name = %s', $name );
				}

				$options = array();
				if ( ! empty( $conditions ) ) {
					$query = 'SELECT option_name, option_value, autoload FROM ' . $wpdb->options . ' WHERE ' . implode( ' OR ', $conditions ) . ' ORDER BY option_name ASC LIMIT 1000';

					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Control-surface discovery with prepared conditions.
					$rows = $wpdb->get_results( $query, ARRAY_A );
					foreach ( is_array( $rows ) ? $rows : array() as $row ) {
						$value = maybe_unserialize( $row['option_value'] );
						$item  = array(
							'name'     => (string) $row['option_name'],
							'autoload' => (string) $row['autoload'],
							'type'     => gettype( $value ),
							'count'    => is_array( $value ) ? count( $value ) : 0,
						);
						if ( $include_values ) {
							$item['value'] = $value;
						} elseif ( is_array( $value ) ) {
							$item['keys'] = array_values( array_map( 'strval', array_keys( $value ) ) );
						}
						$options[] = $item;
					}
				}

				$theme_mods      = get_theme_mods();
				$theme_mod_items = array();
				foreach ( is_array( $theme_mods ) ? $theme_mods : array() as $key => $value ) {
					if ( ! is_string( $key ) || ! mcp_abilities_generatepress_allowed_theme_mod_key( $key ) ) {
						continue;
					}
					$item = array(
						'key'  => $key,
						'type' => gettype( $value ),
					);
					if ( $include_values ) {
						$item['value'] = $value;
					}
					$theme_mod_items[] = $item;
				}

				$custom_css_post = wp_get_custom_css_post( $stylesheet );
				$custom_css      = wp_get_custom_css( $stylesheet );

				$element_counts = array();
				if ( post_type_exists( 'gp_elements' ) ) {
					$counts = wp_count_posts( 'gp_elements' );
					foreach ( get_object_vars( $counts ) as $status => $count ) {
						$element_counts[ $status ] = (int) $count;
					}
				}

				$gb_known_posts = get_option( 'generateblocks_dynamic_css_posts', array() );
				$upload_dir     = wp_get_upload_dir();

				return array(
					'success'         => true,
					'theme'           => $theme_info,
					'plugins'         => $plugin_versions,
					'options'         => $options,
					'theme_mods'      => $theme_mod_items,
					'custom_css'      => array(
						'stylesheet' => $stylesheet,
						'post_id'    => $custom_css_post ? (int) $custom_css_post->ID : 0,
						'length'     => strlen( $custom_css ),
						'css'        => $include_values ? $custom_css : '',
					),
					'module_settings' => mcp_abilities_generatepress_discover_module_setting_options(),
					'elements'        => array(
						'post_type_exists' => post_type_exists( 'gp_elements' ),
						'counts'           => $element_counts,
					),
					'generateblocks'  => array(
						'global_styles_count' => count( MCP_Abilities_GeneratePress_GenerateBlocks_Global_Styles::get_all() ),
						'defaults_keys'       => array_values( array_map( 'strval', array_keys( (array) get_option( 'generateblocks_defaults', array() ) ) ) ),
						'settings_keys'       => array_values( array_map( 'strval', array_keys( (array) get_option( 'generateblocks', array() ) ) ) ),
						'dynamic_css_posts'   => is_array( $gb_known_posts ) ? count( $gb_known_posts ) : 0,
						'css_directory'       => trailingslashit( $upload_dir['basedir'] ) . 'generateblocks/',
					),
					'message'         => 'GeneratePress and GenerateBlocks control surface discovered successfully.',
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
	// GENERATEPRESS - List Setting Keys
	// =========================================================================
	mcp_abilities_generatepress_register_ability(
		'generatepress/list-setting-keys',
		array(
			'label'               => 'List GeneratePress Setting Keys',
			'description'         => 'Discovers the live GeneratePress setting keys, classifies them, and returns module/theme-mod control surfaces so MCP clients can avoid guessing.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'include_values' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Include current values for every discovered key.',
					),
					'include_known_absent' => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'Include known GeneratePress setting keys that are not currently stored.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'       => array( 'type' => 'boolean' ),
					'setting_count' => array( 'type' => 'integer' ),
					'groups'        => array( 'type' => 'object' ),
					'keys'          => array( 'type' => 'array' ),
					'module_options' => array( 'type' => 'object' ),
					'theme_mods'    => array( 'type' => 'array' ),
					'image_sizes'   => array( 'type' => 'object' ),
					'message'       => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$settings             = get_option( 'generate_settings', array() );
				$settings             = is_array( $settings ) ? $settings : array();
				$include_values       = ! empty( $input['include_values'] );
				$include_known_absent = ! array_key_exists( 'include_known_absent', $input ) || (bool) $input['include_known_absent'];
				$known_groups         = mcp_abilities_generatepress_setting_groups();
				$grouped              = array();
				$keys                 = array();

				foreach ( $settings as $key => $value ) {
					if ( ! is_string( $key ) ) {
						continue;
					}
					$group = mcp_abilities_generatepress_classify_setting_key( $key );
					if ( ! isset( $grouped[ $group ] ) ) {
						$grouped[ $group ] = array();
					}
					$item = array(
						'key'     => $key,
						'present' => true,
						'type'    => gettype( $value ),
					);
					if ( $include_values ) {
						$item['value'] = $value;
					}
					$grouped[ $group ][] = $item;
					$keys[]              = $item;
				}

				if ( $include_known_absent ) {
					foreach ( $known_groups as $group => $known_keys ) {
						foreach ( $known_keys as $key ) {
							if ( array_key_exists( $key, $settings ) ) {
								continue;
							}
							if ( ! isset( $grouped[ $group ] ) ) {
								$grouped[ $group ] = array();
							}
							$item = array(
								'key'     => $key,
								'present' => false,
								'type'    => 'missing',
							);
							$grouped[ $group ][] = $item;
							$keys[]              = $item;
						}
					}
				}

				$module_options = array();
				foreach ( mcp_abilities_generatepress_module_settings_map() as $module => $option_name ) {
					$value = get_option( $option_name, array() );
					$module_options[ $module ] = array(
						'option_name' => $option_name,
						'active'      => ( 'activated' === get_option( 'generate_package_' . $module, '' ) ),
						'keys'        => is_array( $value ) ? array_values( array_map( 'strval', array_keys( $value ) ) ) : array(),
					);
				}

				$theme_mods      = get_theme_mods();
				$theme_mod_items = array();
				foreach ( is_array( $theme_mods ) ? $theme_mods : array() as $key => $value ) {
					if ( is_string( $key ) && mcp_abilities_generatepress_allowed_theme_mod_key( $key ) ) {
						$item = array(
							'key'  => $key,
							'type' => gettype( $value ),
						);
						if ( $include_values ) {
							$item['value'] = $value;
						}
						$theme_mod_items[] = $item;
					}
				}

				return array(
					'success'        => true,
					'setting_count'  => count( $settings ),
					'groups'         => $grouped,
					'keys'           => $keys,
					'module_options' => $module_options,
					'theme_mods'     => $theme_mod_items,
					'image_sizes'    => mcp_abilities_generatepress_image_sizes(),
					'message'        => 'GeneratePress control surface discovered successfully.',
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
	// GENERATEPRESS - Theme Mods
	// =========================================================================
	mcp_abilities_generatepress_register_ability(
		'generatepress/get-theme-mods',
		array(
			'label'               => 'Get GeneratePress Theme Mods',
			'description'         => 'Gets GeneratePress-relevant theme mods such as custom_logo and GP-prefixed mods.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'force' => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'Compatibility flag for clients that require at least one object property.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'mods'    => array( 'type' => 'object' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function (): array {
				$mods   = get_theme_mods();
				$result = array();
				foreach ( is_array( $mods ) ? $mods : array() as $key => $value ) {
					if ( is_string( $key ) && mcp_abilities_generatepress_allowed_theme_mod_key( $key ) ) {
						$result[ $key ] = $value;
					}
				}
				return array(
					'success' => true,
					'mods'    => $result,
					'message' => 'Theme mods retrieved successfully.',
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

	mcp_abilities_generatepress_register_ability(
		'generatepress/update-theme-mods',
		array(
			'label'               => 'Update GeneratePress Theme Mods',
			'description'         => 'Updates GeneratePress-relevant theme mods. Use null to remove a mod.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'mods' ),
				'properties'           => array(
					'mods' => array(
						'type'        => 'object',
						'description' => 'Map of allowed theme mod keys to values. Use null to remove.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'updated'  => array( 'type' => 'array' ),
					'removed'  => array( 'type' => 'array' ),
					'rejected' => array( 'type' => 'array' ),
					'message'  => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$mods = isset( $input['mods'] ) && is_array( $input['mods'] ) ? $input['mods'] : array();
				if ( empty( $mods ) ) {
					return array( 'success' => false, 'message' => 'No theme mods provided.' );
				}

				$updated  = array();
				$removed  = array();
				$rejected = array();

				foreach ( $mods as $key => $value ) {
					if ( ! is_string( $key ) || ! mcp_abilities_generatepress_allowed_theme_mod_key( $key ) ) {
						$rejected[] = (string) $key;
						continue;
					}
					if ( null === $value ) {
						remove_theme_mod( $key );
						$removed[] = $key;
					} else {
						set_theme_mod( $key, $value );
						$updated[] = $key;
					}
				}

				mcp_abilities_generatepress_clear_dynamic_css_cache();

				return array(
					'success'  => true,
					'updated'  => $updated,
					'removed'  => $removed,
					'rejected' => $rejected,
					'message'  => 'Theme mods updated successfully.',
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
	// GENERATEPRESS - Custom CSS
	// =========================================================================
	mcp_abilities_generatepress_register_ability(
		'generatepress/get-custom-css',
		array(
			'label'               => 'Get GeneratePress Custom CSS',
			'description'         => 'Gets the WordPress Custom CSS for the active GeneratePress stylesheet.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'force' => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'Compatibility flag for clients that require at least one object property.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'    => array( 'type' => 'boolean' ),
					'stylesheet' => array( 'type' => 'string' ),
					'post_id'    => array( 'type' => 'integer' ),
					'css'        => array( 'type' => 'string' ),
					'length'     => array( 'type' => 'integer' ),
					'message'    => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function (): array {
				$stylesheet = get_stylesheet();
				$css        = wp_get_custom_css( $stylesheet );
				$post       = wp_get_custom_css_post( $stylesheet );

				return array(
					'success'    => true,
					'stylesheet' => $stylesheet,
					'post_id'    => $post ? (int) $post->ID : 0,
					'css'        => $css,
					'length'     => strlen( $css ),
					'message'    => 'Custom CSS retrieved successfully.',
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

	mcp_abilities_generatepress_register_ability(
		'generatepress/update-custom-css',
		array(
			'label'               => 'Update GeneratePress Custom CSS',
			'description'         => 'Updates the WordPress Custom CSS for the active GeneratePress stylesheet. Pass an empty string to clear it.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'css' ),
				'properties'           => array(
					'css' => array(
						'type'        => 'string',
						'description' => 'The full Custom CSS content to save for the active stylesheet.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'        => array( 'type' => 'boolean' ),
					'stylesheet'     => array( 'type' => 'string' ),
					'post_id'        => array( 'type' => 'integer' ),
					'previous_length' => array( 'type' => 'integer' ),
					'new_length'      => array( 'type' => 'integer' ),
					'message'        => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$stylesheet = get_stylesheet();
				$css        = isset( $input['css'] ) && is_string( $input['css'] ) ? $input['css'] : '';
				$previous   = wp_get_custom_css( $stylesheet );

				$result = wp_update_custom_css_post(
					$css,
					array(
						'stylesheet' => $stylesheet,
					)
				);

				if ( is_wp_error( $result ) ) {
					return array(
						'success'    => false,
						'stylesheet' => $stylesheet,
						'post_id'    => 0,
						'message'    => $result->get_error_message(),
					);
				}

				mcp_abilities_generatepress_clear_dynamic_css_cache();

				return array(
					'success'         => true,
					'stylesheet'      => $stylesheet,
					'post_id'         => $result instanceof WP_Post ? (int) $result->ID : 0,
					'previous_length' => strlen( $previous ),
					'new_length'      => strlen( $css ),
					'message'         => 'Custom CSS updated successfully.',
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

	mcp_abilities_generatepress_register_ability(
		'generatepress/patch-custom-css',
		array(
			'label'               => 'Patch GeneratePress Custom CSS',
			'description'         => 'Patches the WordPress Custom CSS for the active GeneratePress stylesheet using exact or regex replacement.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'find', 'replace' ),
				'properties'           => array(
					'find'    => array(
						'type'        => 'string',
						'description' => 'Exact text or regex pattern to find in the current Custom CSS.',
					),
					'replace' => array(
						'type'        => 'string',
						'description' => 'Replacement text.',
					),
					'regex'   => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Treat find as a PHP regex pattern.',
					),
					'limit'   => array(
						'type'        => 'integer',
						'default'     => -1,
						'minimum'     => -1,
						'description' => 'Maximum replacements. Use -1 for all matches.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'        => array( 'type' => 'boolean' ),
					'stylesheet'     => array( 'type' => 'string' ),
					'post_id'        => array( 'type' => 'integer' ),
					'replacements'   => array( 'type' => 'integer' ),
					'previous_length' => array( 'type' => 'integer' ),
					'new_length'      => array( 'type' => 'integer' ),
					'message'        => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$stylesheet = get_stylesheet();
				$find       = isset( $input['find'] ) && is_string( $input['find'] ) ? $input['find'] : '';
				$replace    = isset( $input['replace'] ) && is_string( $input['replace'] ) ? $input['replace'] : '';
				$is_regex   = ! empty( $input['regex'] );
				$limit      = isset( $input['limit'] ) ? (int) $input['limit'] : -1;
				$current    = wp_get_custom_css( $stylesheet );

				if ( '' === $find ) {
					return array(
						'success'    => false,
						'stylesheet' => $stylesheet,
						'post_id'    => 0,
						'message'    => 'Find value cannot be empty.',
					);
				}

				$count = 0;
				if ( $is_regex ) {
					$patched = preg_replace( $find, $replace, $current, $limit, $count );
					if ( null === $patched ) {
						return array(
							'success'    => false,
							'stylesheet' => $stylesheet,
							'post_id'    => 0,
							'message'    => 'Invalid regex pattern.',
						);
					}
				} else {
					$count   = substr_count( $current, $find );
					$patched = str_replace( $find, $replace, $current );
					if ( $limit >= 0 && $count > $limit ) {
						$parts   = explode( $find, $current, $limit + 1 );
						$patched = implode( $replace, array_slice( $parts, 0, $limit + 1 ) );
						if ( count( $parts ) > $limit + 1 ) {
							$patched .= $find . implode( $find, array_slice( $parts, $limit + 1 ) );
						}
						$count = $limit;
					}
				}

				if ( 0 === $count ) {
					$post = wp_get_custom_css_post( $stylesheet );
					return array(
						'success'         => false,
						'stylesheet'      => $stylesheet,
						'post_id'         => $post ? (int) $post->ID : 0,
						'replacements'    => 0,
						'previous_length' => strlen( $current ),
						'new_length'      => strlen( $current ),
						'message'         => 'Find value was not present in Custom CSS.',
					);
				}

				$result = wp_update_custom_css_post(
					$patched,
					array(
						'stylesheet' => $stylesheet,
					)
				);

				if ( is_wp_error( $result ) ) {
					return array(
						'success'      => false,
						'stylesheet'   => $stylesheet,
						'post_id'      => 0,
						'replacements' => 0,
						'message'      => $result->get_error_message(),
					);
				}

				mcp_abilities_generatepress_clear_dynamic_css_cache();

				return array(
					'success'         => true,
					'stylesheet'      => $stylesheet,
					'post_id'         => $result instanceof WP_Post ? (int) $result->ID : 0,
					'replacements'    => (int) $count,
					'previous_length' => strlen( $current ),
					'new_length'      => strlen( $patched ),
					'message'         => 'Custom CSS patched successfully.',
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

	mcp_abilities_generatepress_register_ability(
		'generatepress/clear-custom-css',
		array(
			'label'               => 'Clear GeneratePress Custom CSS',
			'description'         => 'Clears the WordPress Custom CSS for the active GeneratePress stylesheet.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'confirm' => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'Confirm clearing the current Custom CSS.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'         => array( 'type' => 'boolean' ),
					'stylesheet'      => array( 'type' => 'string' ),
					'post_id'         => array( 'type' => 'integer' ),
					'previous_length' => array( 'type' => 'integer' ),
					'new_length'      => array( 'type' => 'integer' ),
					'message'         => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$stylesheet = get_stylesheet();
				$confirm    = ! array_key_exists( 'confirm', $input ) || (bool) $input['confirm'];
				$current    = wp_get_custom_css( $stylesheet );

				if ( ! $confirm ) {
					return array(
						'success'         => false,
						'stylesheet'      => $stylesheet,
						'post_id'         => 0,
						'previous_length' => strlen( $current ),
						'new_length'      => strlen( $current ),
						'message'         => 'Confirmation required to clear Custom CSS.',
					);
				}

				$result = wp_update_custom_css_post(
					'',
					array(
						'stylesheet' => $stylesheet,
					)
				);

				if ( is_wp_error( $result ) ) {
					return array(
						'success'    => false,
						'stylesheet' => $stylesheet,
						'post_id'    => 0,
						'message'    => $result->get_error_message(),
					);
				}

				mcp_abilities_generatepress_clear_dynamic_css_cache();

				return array(
					'success'         => true,
					'stylesheet'      => $stylesheet,
					'post_id'         => $result instanceof WP_Post ? (int) $result->ID : 0,
					'previous_length' => strlen( $current ),
					'new_length'      => 0,
					'message'         => 'Custom CSS cleared successfully.',
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
	// GENERATEPRESS - Update Settings
	// =========================================================================
	mcp_abilities_generatepress_register_ability(
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
	mcp_abilities_generatepress_register_ability(
		'generatepress/update-global-design-settings',
		array(
			'label'               => 'Update GeneratePress Global Design Settings',
			'description'         => 'Updates global GeneratePress design settings. Use this instead of page/block-level styling for site-wide design decisions.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'typography'   => array( 'type' => 'object' ),
					'colors'       => array( 'type' => 'object' ),
					'layout'       => array( 'type' => 'object' ),
					'spacing'      => array( 'type' => 'object' ),
					'buttons'      => array( 'type' => 'object' ),
					'site_identity' => array( 'type' => 'object' ),
					'settings'     => array(
						'type'        => 'object',
						'description' => 'Flat generate_settings keys to update. Use for any GeneratePress global setting not covered by the named sections.',
					),
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

				$allowed = mcp_abilities_generatepress_setting_groups();

				$updated_sections = array();
				$updated_keys     = array();

				if ( isset( $input['typography'] ) && is_array( $input['typography'] ) ) {
					foreach ( array( 'body', 'html', 'site_title', 'mobile_navigation_site_title', 'site_tagline', 'navigation', 'subnavigation', 'buttons', 'entry_meta', 'sidebar_widget_title', 'sidebar_widget_text', 'footer_widget_title', 'footer_widget_text', 'footer_bar_text', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $group ) {
						if ( isset( $input['typography'][ $group ] ) && is_array( $input['typography'][ $group ] ) ) {
							$changed = mcp_abilities_generatepress_apply_typography_group( $settings, $rules, $group, $input['typography'][ $group ] );
							if ( ! empty( $changed ) ) {
								$updated_sections[] = 'typography.' . $group;
								$updated_keys       = array_merge( $updated_keys, $changed );
							}
						}
					}
				}

				if ( isset( $input['settings'] ) && is_array( $input['settings'] ) ) {
					foreach ( $input['settings'] as $key => $value ) {
						if ( ! is_string( $key ) || '' === $key ) {
							continue;
						}
						if ( 'typography' === $key ) {
							return array(
								'success' => false,
								'message' => 'Use the typography groups in generatepress/update-global-design-settings instead of writing the raw typography rule array.',
							);
						}
						if ( ! mcp_abilities_generatepress_is_flat_setting_value( $value ) ) {
							return array(
								'success' => false,
								'message' => 'Refusing nested value for GeneratePress setting "' . $key . '".',
							);
						}
						if ( null === $value || '' === $value ) {
							unset( $settings[ $key ] );
						} else {
							$settings[ $key ] = $value;
						}
						$updated_sections[] = 'settings';
						$updated_keys[]     = $key;
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
	mcp_abilities_generatepress_register_ability(
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

	mcp_abilities_generatepress_register_ability(
		'generatepress/list-module-settings',
		array(
			'label'               => 'List GeneratePress Module Settings',
			'description'         => 'Discovers all GeneratePress and GP Premium module settings options currently stored.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'include_values' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Include full option values.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'options'  => array( 'type' => 'array' ),
					'message'  => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$include_values = ! empty( $input['include_values'] );
				$options        = mcp_abilities_generatepress_discover_module_setting_options();

				if ( $include_values ) {
					foreach ( $options as &$option ) {
						$option['value'] = get_option( (string) $option['option_name'], array() );
					}
					unset( $option );
				}

				return array(
					'success' => true,
					'options' => $options,
					'message' => 'Module settings options discovered successfully.',
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
	// GENERATEPRESS - Update Modules
	// =========================================================================
	mcp_abilities_generatepress_register_ability(
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
	mcp_abilities_generatepress_register_ability(
		'generatepress/get-module-settings',
		array(
			'label'               => 'Get GeneratePress Module Settings',
			'description'         => 'Gets settings for any discovered GeneratePress or GP Premium module settings option.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'module' => array(
						'type'        => 'string',
						'description' => 'Known module slug to retrieve, such as blog, spacing, menu_plus, secondary_nav, or woocommerce.',
					),
					'option_name' => array(
						'type'        => 'string',
						'description' => 'Explicit GeneratePress module settings option name, such as generate_menu_plus_settings.',
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
				$option_name = isset( $input['option_name'] ) && is_string( $input['option_name'] ) ? sanitize_key( $input['option_name'] ) : '';

				if ( '' !== $option_name ) {
					if ( ! mcp_abilities_generatepress_is_allowed_option_name( $option_name ) || ! str_ends_with( $option_name, '_settings' ) ) {
						return array( 'success' => false, 'message' => 'Option name is not an allowed GeneratePress settings option.' );
					}
				} elseif ( '' !== $module && isset( $map[ $module ] ) ) {
					$option_name = $map[ $module ];
				} else {
					return array( 'success' => false, 'message' => 'Provide a known module or allowed option_name.' );
				}

				$settings    = get_option( $option_name, array() );

				if ( ! is_array( $settings ) ) {
					$settings = array();
				}

				return array(
					'success'     => true,
					'module'      => '' !== $module ? $module : preg_replace( '/^generate_|_settings$/', '', $option_name ),
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
	mcp_abilities_generatepress_register_ability(
		'generatepress/update-module-settings',
		array(
			'label'               => 'Update GeneratePress Module Settings',
			'description'         => 'Updates settings for any discovered GeneratePress or GP Premium module settings option.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'settings' ),
				'properties'           => array(
					'module' => array(
						'type'        => 'string',
						'description' => 'Known module slug to update, such as blog, spacing, menu_plus, secondary_nav, or woocommerce.',
					),
					'option_name' => array(
						'type'        => 'string',
						'description' => 'Explicit GeneratePress module settings option name, such as generate_menu_plus_settings.',
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
				$option_name = isset( $input['option_name'] ) && is_string( $input['option_name'] ) ? sanitize_key( $input['option_name'] ) : '';

				if ( '' !== $option_name ) {
					if ( ! mcp_abilities_generatepress_is_allowed_option_name( $option_name ) || ! str_ends_with( $option_name, '_settings' ) ) {
						return array( 'success' => false, 'message' => 'Option name is not an allowed GeneratePress settings option.' );
					}
				} elseif ( '' !== $module && isset( $map[ $module ] ) ) {
					$option_name = $map[ $module ];
				} else {
					return array( 'success' => false, 'message' => 'Provide a known module or allowed option_name.' );
				}

				if ( empty( $settings ) ) {
					return array( 'success' => false, 'message' => 'No settings provided.' );
				}

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
					'module'      => '' !== $module ? $module : preg_replace( '/^generate_|_settings$/', '', $option_name ),
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
	// GENERATEPRESS - Blog Archive Settings
	// =========================================================================
	mcp_abilities_generatepress_register_ability(
		'generatepress/get-blog-archive-settings',
		array(
			'label'               => 'Get GeneratePress Blog Archive Settings',
			'description'         => 'Gets native WordPress reading settings and GeneratePress blog/layout settings that control the posts archive.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'          => array( 'type' => 'boolean' ),
					'reading_settings' => array( 'type' => 'object' ),
					'generate_settings' => array( 'type' => 'object' ),
					'blog_settings'    => array( 'type' => 'object' ),
					'posts_page'       => array( 'type' => 'object' ),
					'message'          => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function (): array {
				$page_for_posts = (int) get_option( 'page_for_posts', 0 );
				$page_on_front  = (int) get_option( 'page_on_front', 0 );
				$posts_page     = $page_for_posts ? get_post( $page_for_posts ) : null;
				$settings       = get_option( 'generate_settings', array() );
				$settings       = is_array( $settings ) ? $settings : array();
				$blog_settings  = get_option( 'generate_blog_settings', array() );
				$blog_settings  = is_array( $blog_settings ) ? $blog_settings : array();
				$gp_keys        = array(
					'blog_layout_setting',
					'single_layout_setting',
					'layout_setting',
					'content_layout_setting',
					'container_width',
					'content_width',
				);
				$gp_values      = array();
				foreach ( $gp_keys as $key ) {
					if ( array_key_exists( $key, $settings ) ) {
						$gp_values[ $key ] = $settings[ $key ];
					}
				}

				return array(
					'success'           => true,
					'reading_settings'  => array(
						'show_on_front'  => get_option( 'show_on_front', 'posts' ),
						'page_on_front'  => $page_on_front,
						'page_for_posts' => $page_for_posts,
						'posts_per_page' => (int) get_option( 'posts_per_page', 10 ),
					),
					'generate_settings' => $gp_values,
					'blog_settings'     => $blog_settings,
					'posts_page'        => $posts_page ? array(
						'id'        => $posts_page->ID,
						'title'     => get_the_title( $posts_page ),
						'status'    => $posts_page->post_status,
						'permalink' => get_permalink( $posts_page ),
					) : array(),
					'message'           => 'Blog archive settings retrieved successfully.',
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

	mcp_abilities_generatepress_register_ability(
		'generatepress/update-blog-archive-settings',
		array(
			'label'               => 'Update GeneratePress Blog Archive Settings',
			'description'         => 'Updates native blog archive controls: reading options, GP archive layout keys, and GP Premium blog module settings.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'reading_settings' => array(
						'type'        => 'object',
						'description' => 'Allowed keys: show_on_front, page_on_front, page_for_posts, posts_per_page.',
					),
					'generate_settings' => array(
						'type'        => 'object',
						'description' => 'Allowed GP layout keys for archives.',
					),
					'blog_settings' => array(
						'type'        => 'object',
						'description' => 'GeneratePress Premium blog module settings such as post_image, post_image_size, masonry, date, author, categories, read_more_button.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'updated'  => array( 'type' => 'array' ),
					'rejected' => array( 'type' => 'array' ),
					'message'  => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$updated  = array();
				$rejected = array();

				if ( isset( $input['reading_settings'] ) && is_array( $input['reading_settings'] ) ) {
					$allowed_reading = array(
						'show_on_front',
						'page_on_front',
						'page_for_posts',
						'posts_per_page',
					);
					foreach ( $input['reading_settings'] as $key => $value ) {
						if ( ! is_string( $key ) || ! in_array( $key, $allowed_reading, true ) ) {
							$rejected[] = 'reading_settings.' . (string) $key;
							continue;
						}
						if ( 'show_on_front' === $key ) {
							if ( ! in_array( $value, array( 'posts', 'page' ), true ) ) {
								$rejected[] = 'reading_settings.show_on_front';
								continue;
							}
							update_option( $key, $value );
						} else {
							update_option( $key, max( 0, (int) $value ) );
						}
						$updated[] = 'reading_settings.' . $key;
					}
				}

				if ( isset( $input['generate_settings'] ) && is_array( $input['generate_settings'] ) ) {
					$allowed_gp = array(
						'blog_layout_setting',
						'single_layout_setting',
						'layout_setting',
						'content_layout_setting',
						'container_width',
						'content_width',
					);
					$settings   = get_option( 'generate_settings', array() );
					$settings   = is_array( $settings ) ? $settings : array();
					foreach ( $input['generate_settings'] as $key => $value ) {
						if ( ! is_string( $key ) || ! in_array( $key, $allowed_gp, true ) || ! mcp_abilities_generatepress_is_flat_setting_value( $value ) ) {
							$rejected[] = 'generate_settings.' . (string) $key;
							continue;
						}
						if ( null === $value || '' === $value ) {
							unset( $settings[ $key ] );
						} else {
							$settings[ $key ] = $value;
						}
						$updated[] = 'generate_settings.' . $key;
					}
					update_option( 'generate_settings', $settings );
				}

				if ( isset( $input['blog_settings'] ) && is_array( $input['blog_settings'] ) ) {
					$blog_settings = get_option( 'generate_blog_settings', array() );
					$blog_settings = is_array( $blog_settings ) ? $blog_settings : array();
					foreach ( $input['blog_settings'] as $key => $value ) {
						if ( ! is_string( $key ) || ! mcp_abilities_generatepress_is_flat_setting_value( $value ) ) {
							$rejected[] = 'blog_settings.' . (string) $key;
							continue;
						}
						if ( null === $value || '' === $value ) {
							unset( $blog_settings[ $key ] );
						} else {
							$blog_settings[ $key ] = $value;
						}
						$updated[] = 'blog_settings.' . $key;
					}
					update_option( 'generate_blog_settings', $blog_settings );
				}

				if ( empty( $updated ) && empty( $rejected ) ) {
					return array( 'success' => false, 'message' => 'No archive settings provided.' );
				}

				mcp_abilities_generatepress_clear_dynamic_css_cache();

				return array(
					'success'  => true,
					'updated'  => $updated,
					'rejected' => $rejected,
					'message'  => 'Blog archive settings updated successfully.',
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
	// GENERATEPRESS - Get Typography
	// =========================================================================
	mcp_abilities_generatepress_register_ability(
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
	mcp_abilities_generatepress_register_ability(
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
	mcp_abilities_generatepress_register_ability(
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
	mcp_abilities_generatepress_register_ability(
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
	// GENERATEBLOCKS - Get current Global Styles.
	// =========================================================================
	mcp_abilities_generatepress_register_ability(
		'generateblocks/get-global-styles',
		array(
			'label'               => 'Get GenerateBlocks Global Styles',
			'description'         => 'Retrieves current GenerateBlocks Pro Global Styles backed by native global classes.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'       => array( 'type' => 'boolean' ),
					'global_styles' => array( 'type' => 'array' ),
					'message'       => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				return array(
					'success'       => true,
					'global_styles' => MCP_Abilities_GeneratePress_GenerateBlocks_Global_Styles::get_all(),
					'message'       => 'GenerateBlocks current Global Styles retrieved successfully',
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
	// GENERATEBLOCKS - Synchronize current Global Styles.
	// =========================================================================
	mcp_abilities_generatepress_register_ability(
		'generateblocks/update-global-styles',
		array(
			'label'               => 'Update GenerateBlocks Global Styles',
			'description'         => 'Upserts current GenerateBlocks Pro Global Styles, compiles their native style data, and explicitly deletes named class selectors.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'global_styles' => array(
						'type'        => 'array',
						'description' => 'Current Global Styles to create or update.',
						'items'       => array(
							'type'                 => 'object',
							'properties'           => array(
								'selector'   => array( 'type' => 'string' ),
								'status'     => array( 'type' => 'string', 'enum' => array( 'publish', 'draft', 'private' ) ),
								'menu_order' => array( 'type' => 'integer', 'minimum' => 0 ),
								'styles'     => array( 'type' => 'object' ),
							),
							'required'             => array( 'selector', 'styles' ),
							'additionalProperties' => false,
						),
					),
					'delete_selectors' => array(
						'type'        => 'array',
						'description' => 'Exact current Global Style selectors to delete, including compound selectors.',
						'items'       => array( 'type' => 'string' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'created' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'updated' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'unchanged' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'deleted' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'styles'  => array( 'type' => 'array' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$input = is_array( $input ) ? $input : array();

				if ( empty( $input['global_styles'] ) && empty( $input['delete_selectors'] ) ) {
					return array( 'success' => false, 'message' => 'No Global Styles or selectors provided to synchronize' );
				}

				return MCP_Abilities_GeneratePress_GenerateBlocks_Global_Styles::synchronize(
					(array) ( $input['global_styles'] ?? array() ),
					(array) ( $input['delete_selectors'] ?? array() )
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

	mcp_abilities_generatepress_register_ability(
		'generateblocks/list-options',
		array(
			'label'               => 'List GenerateBlocks Options',
			'description'         => 'Lists GenerateBlocks and GenerateBlocks Pro options in wp_options.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
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
					'include_values' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Include current option values.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'options' => array( 'type' => 'array' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				global $wpdb;

				$limit          = isset( $input['limit'] ) ? max( 1, min( 500, (int) $input['limit'] ) ) : 200;
				$offset         = isset( $input['offset'] ) ? max( 0, (int) $input['offset'] ) : 0;
				$include_values = ! empty( $input['include_values'] );

				$likes = array(
					$wpdb->esc_like( 'generateblocks' ) . '%',
					$wpdb->esc_like( 'generate_blocks_' ) . '%',
					$wpdb->esc_like( 'gb_' ) . '%',
				);
				$conditions = array();
				foreach ( $likes as $like ) {
					$conditions[] = $wpdb->prepare( 'option_name LIKE %s', $like );
				}

				$query = 'SELECT option_name, option_value, autoload FROM ' . $wpdb->options . ' WHERE ' . implode( ' OR ', $conditions ) . ' ORDER BY option_name ASC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Option discovery with prepared LIKE conditions.
				$rows = $wpdb->get_results( $query, ARRAY_A );

				$options = array();
				foreach ( is_array( $rows ) ? $rows : array() as $row ) {
					$value = maybe_unserialize( $row['option_value'] );
					$item  = array(
						'name'     => (string) $row['option_name'],
						'autoload' => (string) $row['autoload'],
						'type'     => gettype( $value ),
						'count'    => is_array( $value ) ? count( $value ) : 0,
					);
					if ( $include_values ) {
						$item['value'] = $value;
					} elseif ( is_array( $value ) ) {
						$item['keys'] = array_values( array_map( 'strval', array_keys( $value ) ) );
					}
					$options[] = $item;
				}

				return array(
					'success' => true,
					'options' => $options,
					'message' => 'GenerateBlocks options listed successfully.',
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

	mcp_abilities_generatepress_register_ability(
		'generateblocks/get-options',
		array(
			'label'               => 'Get GenerateBlocks Options',
			'description'         => 'Gets specific GenerateBlocks or GenerateBlocks Pro options by name.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'options' ),
				'properties'           => array(
					'options' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'GenerateBlocks option names to retrieve.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'options'  => array( 'type' => 'object' ),
					'missing'  => array( 'type' => 'array' ),
					'rejected' => array( 'type' => 'array' ),
					'message'  => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$names    = isset( $input['options'] ) && is_array( $input['options'] ) ? $input['options'] : array();
				$marker   = 'mcp_missing_' . wp_generate_password( 12, false );
				$options  = array();
				$missing  = array();
				$rejected = array();

				foreach ( $names as $name ) {
					if ( ! is_string( $name ) || '' === $name ) {
						continue;
					}
					if ( ! mcp_abilities_generatepress_is_allowed_generateblocks_option_name( $name ) ) {
						$rejected[] = $name;
						continue;
					}
					$value = get_option( $name, $marker );
					if ( $value === $marker ) {
						$missing[] = $name;
						continue;
					}
					$options[ $name ] = $value;
				}

				return array(
					'success'  => true,
					'options'  => $options,
					'missing'  => $missing,
					'rejected' => $rejected,
					'message'  => 'GenerateBlocks options retrieved successfully.',
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

	mcp_abilities_generatepress_register_ability(
		'generateblocks/update-options',
		array(
			'label'               => 'Update GenerateBlocks Options',
			'description'         => 'Updates or deletes GenerateBlocks and GenerateBlocks Pro options by name.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'updates' => array(
						'type'        => 'object',
						'description' => 'Map of GenerateBlocks option names to values. Use null to delete.',
					),
					'deletes' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'GenerateBlocks option names to delete.',
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
				$updates  = isset( $input['updates'] ) && is_array( $input['updates'] ) ? $input['updates'] : array();
				$deletes  = isset( $input['deletes'] ) && is_array( $input['deletes'] ) ? $input['deletes'] : array();
				$updated  = array();
				$deleted  = array();
				$rejected = array();

				if ( empty( $updates ) && empty( $deletes ) ) {
					return array( 'success' => false, 'message' => 'No updates or deletes provided.' );
				}

				foreach ( $updates as $name => $value ) {
					if ( ! is_string( $name ) || ! mcp_abilities_generatepress_is_allowed_generateblocks_option_name( $name ) ) {
						$rejected[] = (string) $name;
						continue;
					}
					if ( null === $value ) {
						delete_option( $name );
						$deleted[] = $name;
					} else {
						update_option( $name, $value );
						$updated[] = $name;
					}
				}

				foreach ( $deletes as $name ) {
					if ( ! is_string( $name ) || ! mcp_abilities_generatepress_is_allowed_generateblocks_option_name( $name ) ) {
						$rejected[] = (string) $name;
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
					'message'  => 'GenerateBlocks options updated successfully.',
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
	// GENERATEBLOCKS - Control Surface
	// =========================================================================
	mcp_abilities_generatepress_register_ability(
		'generateblocks/list-control-surface',
		array(
			'label'               => 'List GenerateBlocks Control Surface',
			'description'         => 'Discovers GenerateBlocks global styles, defaults, plugin settings, dynamic CSS posts, and generated CSS file status.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'include_values' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Include full option values.',
					),
					'post_ids' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'integer' ),
						'description' => 'Optional post IDs for CSS file status. Defaults to known dynamic CSS posts.',
					),
					'limit' => array(
						'type'        => 'integer',
						'default'     => 100,
						'minimum'     => 1,
						'maximum'     => 500,
						'description' => 'Maximum CSS posts to report.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'       => array( 'type' => 'boolean' ),
					'options'       => array( 'type' => 'object' ),
					'css_posts'     => array( 'type' => 'array' ),
					'css_directory' => array( 'type' => 'string' ),
					'message'       => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$include_values = ! empty( $input['include_values'] );
				$options        = array(
					'generateblocks_global_styles' => MCP_Abilities_GeneratePress_GenerateBlocks_Global_Styles::get_all(),
					'generateblocks_defaults'      => get_option( 'generateblocks_defaults', array() ),
					'generateblocks'               => get_option( 'generateblocks', array() ),
					'generateblocks_dynamic_css_posts' => get_option( 'generateblocks_dynamic_css_posts', array() ),
					'generateblocks_css_version'   => get_option( 'generateblocks_css_version', '' ),
					'generateblocks_dynamic_css_time' => get_option( 'generateblocks_dynamic_css_time', '' ),
				);
				$summary = array();
				foreach ( $options as $name => $value ) {
					$item = array(
						'type'  => gettype( $value ),
						'count' => is_array( $value ) ? count( $value ) : 0,
					);
					if ( $include_values ) {
						$item['value'] = $value;
					} elseif ( is_array( $value ) ) {
						$item['keys'] = array_values( array_map( 'strval', array_keys( $value ) ) );
					}
					$summary[ $name ] = $item;
				}

				$known_posts = get_option( 'generateblocks_dynamic_css_posts', array() );
				$post_ids    = isset( $input['post_ids'] ) && is_array( $input['post_ids'] )
					? array_values( array_unique( array_filter( array_map( 'intval', $input['post_ids'] ) ) ) )
					: array_values( array_unique( array_filter( array_map( 'intval', array_keys( is_array( $known_posts ) ? $known_posts : array() ) ) ) ) );
				$limit       = isset( $input['limit'] ) ? max( 1, min( 500, (int) $input['limit'] ) ) : 100;
				$css_posts   = array();
				foreach ( array_slice( $post_ids, 0, $limit ) as $post_id ) {
					$path        = mcp_abilities_generatepress_generateblocks_css_path( $post_id );
					$css_posts[] = array(
						'post_id'   => $post_id,
						'title'     => get_the_title( $post_id ),
						'permalink' => get_permalink( $post_id ),
						'path'      => $path,
						'exists'    => file_exists( $path ),
						'bytes'     => file_exists( $path ) ? (int) filesize( $path ) : 0,
					);
				}

				$upload_dir = wp_get_upload_dir();

				return array(
					'success'       => true,
					'options'       => $summary,
					'css_posts'     => $css_posts,
					'css_directory' => trailingslashit( $upload_dir['basedir'] ) . 'generateblocks/',
					'message'       => 'GenerateBlocks control surface discovered successfully.',
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
	// GENERATEBLOCKS - Pattern Library
	// =========================================================================
	mcp_abilities_generatepress_register_ability(
		'generateblocks/list-pattern-libraries',
		array(
			'label'               => 'List GenerateBlocks Pattern Libraries',
			'description'         => 'Lists the GenerateBlocks Pattern Libraries available to the WordPress editor using the same local REST source as the editor.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'include_disabled' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Include disabled libraries.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'   => array( 'type' => 'boolean' ),
					'libraries' => array( 'type' => 'array' ),
					'count'     => array( 'type' => 'integer' ),
					'message'   => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$include_disabled = ! empty( $input['include_disabled'] );
				$libraries        = array();
				foreach ( mcp_abilities_generatepress_get_pattern_libraries() as $library ) {
					if ( ! is_array( $library ) ) {
						continue;
					}
					if ( ! $include_disabled && isset( $library['isEnabled'] ) && ! $library['isEnabled'] ) {
						continue;
					}
					$libraries[] = mcp_abilities_generatepress_public_pattern_library( $library, true );
				}

				return array(
					'success'   => true,
					'libraries' => $libraries,
					'count'     => count( $libraries ),
					'message'   => 'GenerateBlocks pattern libraries listed successfully.',
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_posts' );
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

	mcp_abilities_generatepress_register_ability(
		'generateblocks/list-pattern-categories',
		array(
			'label'               => 'List GenerateBlocks Pattern Categories',
			'description'         => 'Lists categories for a GenerateBlocks Pattern Library using the same local REST source as the editor.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'library_id' => array(
						'type'        => 'string',
						'default'     => 'gb_default_pro_library',
						'description' => 'Pattern library ID. Defaults to the GenerateBlocks Pro library.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'    => array( 'type' => 'boolean' ),
					'library'    => array( 'type' => 'object' ),
					'categories' => array( 'type' => 'array' ),
					'count'      => array( 'type' => 'integer' ),
					'message'    => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$library_id = isset( $input['library_id'] ) && is_string( $input['library_id'] ) && '' !== $input['library_id']
					? sanitize_text_field( $input['library_id'] )
					: 'gb_default_pro_library';

				$result = mcp_abilities_generatepress_get_pattern_library_items( 'categories', $library_id );
				return array(
					'success'    => $result['success'],
					'library'    => $result['library'] ?? array(),
					'categories' => $result['items'],
					'count'      => count( $result['items'] ),
					'message'    => $result['message'],
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_posts' );
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

	mcp_abilities_generatepress_register_ability(
		'generateblocks/search-pattern-library',
		array(
			'label'               => 'Search GenerateBlocks Pattern Library',
			'description'         => 'Searches a GenerateBlocks Pattern Library using the same local REST source as the editor. Pattern block markup is returned only when explicitly requested.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'library_id' => array(
						'type'        => 'string',
						'default'     => 'gb_default_pro_library',
						'description' => 'Pattern library ID. Defaults to the GenerateBlocks Pro library.',
					),
					'category_id' => array(
						'type'        => 'string',
						'default'     => '',
						'description' => 'Optional category ID, for example the Hero category ID from list-pattern-categories.',
					),
					'search' => array(
						'type'        => 'string',
						'default'     => '',
						'description' => 'Optional search term.',
					),
					'limit' => array(
						'type'        => 'integer',
						'default'     => 20,
						'minimum'     => 1,
						'maximum'     => 100,
						'description' => 'Maximum patterns to return.',
					),
					'include_pattern' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Include saved block markup for each pattern.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'library'  => array( 'type' => 'object' ),
					'patterns' => array( 'type' => 'array' ),
					'count'    => array( 'type' => 'integer' ),
					'message'  => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$library_id = isset( $input['library_id'] ) && is_string( $input['library_id'] ) && '' !== $input['library_id']
					? sanitize_text_field( $input['library_id'] )
					: 'gb_default_pro_library';
				$category_id     = isset( $input['category_id'] ) && is_string( $input['category_id'] ) ? sanitize_text_field( $input['category_id'] ) : '';
				$search          = isset( $input['search'] ) && is_string( $input['search'] ) ? sanitize_text_field( $input['search'] ) : '';
				$limit           = isset( $input['limit'] ) ? max( 1, min( 100, (int) $input['limit'] ) ) : 20;
				$include_pattern = ! empty( $input['include_pattern'] );

				$result   = mcp_abilities_generatepress_get_pattern_library_items( 'patterns', $library_id, $category_id, $search );
				$patterns = array();
				foreach ( array_slice( $result['items'], 0, $limit ) as $pattern ) {
					if ( is_array( $pattern ) ) {
						$patterns[] = mcp_abilities_generatepress_public_pattern_item( $pattern, $include_pattern );
					}
				}

				return array(
					'success'  => $result['success'],
					'library'  => $result['library'] ?? array(),
					'patterns' => $patterns,
					'count'    => count( $patterns ),
					'message'  => $result['message'],
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_posts' );
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
	// GENERATEPRESS - Get Page Meta
	// =========================================================================
	mcp_abilities_generatepress_register_ability(
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
	mcp_abilities_generatepress_register_ability(
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
					'verify_frontend' => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'Verify the public frontend body classes after layout-related page meta changes. Defaults to true for layout safety.',
					),
					'frontend_url' => array(
						'type'        => 'string',
						'description' => 'Optional exact frontend URL to verify. Defaults to the post permalink.',
					),
					'expected_body_classes' => array(
						'type'        => 'array',
						'description' => 'Optional additional body classes that must be present during frontend verification.',
						'items'       => array( 'type' => 'string' ),
					),
					'forbidden_body_classes' => array(
						'type'        => 'array',
						'description' => 'Optional additional body classes that must not be present during frontend verification.',
						'items'       => array( 'type' => 'string' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'updated' => array( 'type' => 'array' ),
					'meta_verification' => array( 'type' => 'object' ),
					'frontend_verification' => array( 'type' => 'object' ),
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
				$expected_meta = array();

				// Meta key mappings.
				$meta_map = mcp_abilities_generatepress_page_meta_map();

				foreach ( $meta_map as $input_key => $meta_key ) {
					if ( isset( $input[ $input_key ] ) ) {
						$value = $input[ $input_key ];
						$expected_meta[ $input_key ] = mcp_abilities_generatepress_expected_page_meta_value( $input_key, $value );

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

				$actual_meta       = array();
				$meta_mismatches   = array();
				foreach ( $expected_meta as $input_key => $expected_value ) {
					$actual_value             = get_post_meta( $post_id, $meta_map[ $input_key ], true );
					$actual_meta[ $input_key ] = $actual_value;
					if ( $actual_value !== $expected_value ) {
						$meta_mismatches[ $input_key ] = array(
							'expected' => $expected_value,
							'actual'   => $actual_value,
						);
					}
				}

				$meta_verification = array(
					'success'    => empty( $meta_mismatches ),
					'expected'   => $expected_meta,
					'actual'     => $actual_meta,
					'mismatches' => $meta_mismatches,
				);

				$frontend_verification = array(
					'success' => true,
					'skipped' => true,
					'message' => 'No frontend layout verification was required for this page meta update.',
				);
				$expectations          = mcp_abilities_generatepress_frontend_layout_expectations( $input );
				if ( ! empty( $input['expected_body_classes'] ) && is_array( $input['expected_body_classes'] ) ) {
					foreach ( $input['expected_body_classes'] as $class_name ) {
						if ( is_string( $class_name ) && '' !== $class_name ) {
							$expectations['expected_body_classes'][] = sanitize_html_class( $class_name );
						}
					}
					$expectations['expected_body_classes'] = array_values( array_unique( array_filter( $expectations['expected_body_classes'] ) ) );
				}
				if ( ! empty( $input['forbidden_body_classes'] ) && is_array( $input['forbidden_body_classes'] ) ) {
					foreach ( $input['forbidden_body_classes'] as $class_name ) {
						if ( is_string( $class_name ) && '' !== $class_name ) {
							$expectations['forbidden_body_classes'][] = sanitize_html_class( $class_name );
						}
					}
					$expectations['forbidden_body_classes'] = array_values( array_unique( array_filter( $expectations['forbidden_body_classes'] ) ) );
				}
				$verify_frontend       = ! array_key_exists( 'verify_frontend', $input ) || true === (bool) $input['verify_frontend'];
				$has_frontend_checks   = ! empty( $expectations['expected_body_classes'] )
					|| ! empty( $expectations['forbidden_body_classes'] )
					|| ! empty( $expectations['forbid_entry_title'] );

				if ( $verify_frontend && $has_frontend_checks ) {
					$frontend_url = '';
					if ( ! empty( $input['frontend_url'] ) && is_string( $input['frontend_url'] ) ) {
						$frontend_url = $input['frontend_url'];
					} else {
						$permalink = get_permalink( $post_id );
						$frontend_url = is_string( $permalink ) ? $permalink : '';
					}

					$frontend_verification = mcp_abilities_generatepress_verify_frontend_page_layout( $post_id, $frontend_url, $expectations );
				}

				$success = ! empty( $meta_verification['success'] )
					&& ! empty( $frontend_verification['success'] );

				return array(
					'success'               => $success,
					'updated'               => $updated,
					'meta_verification'     => $meta_verification,
					'frontend_verification' => $frontend_verification,
					'message'               => $success
						? 'GeneratePress page meta updated and verified for post ' . $post_id
						: 'GeneratePress page meta was updated, but verification failed. Do not declare the page fixed until the mismatches are resolved.',
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
	// GENERATEPRESS - Audit Duplicate Headlines
	// =========================================================================
	mcp_abilities_generatepress_register_ability(
		'generatepress/audit-duplicate-headlines',
		array(
			'label'               => 'Audit GeneratePress Duplicate Headlines',
			'description'         => 'Finds pages where GeneratePress would render the theme headline while Gutenberg content already contains an H1, and optionally disables the GeneratePress headline.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'post_type' => array(
						'type'        => 'string',
						'default'     => 'page',
						'description' => 'Public post type to audit. Defaults to page.',
					),
					'status' => array(
						'type'        => 'string',
						'enum'        => array( 'publish', 'draft', 'pending', 'private', 'any' ),
						'default'     => 'publish',
						'description' => 'Post status to audit.',
					),
					'limit' => array(
						'type'        => 'integer',
						'default'     => 100,
						'minimum'     => 1,
						'maximum'     => 500,
						'description' => 'Maximum posts to inspect.',
					),
					'fix' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'When true, set GeneratePress disable_headline meta on affected posts.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'   => array( 'type' => 'boolean' ),
					'checked'   => array( 'type' => 'integer' ),
					'found'     => array( 'type' => 'integer' ),
					'fixed'     => array( 'type' => 'integer' ),
					'candidates' => array( 'type' => 'array' ),
					'message'   => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$input = is_array( $input ) ? $input : array();

				$post_type = isset( $input['post_type'] ) ? sanitize_key( (string) $input['post_type'] ) : 'page';
				if ( ! post_type_exists( $post_type ) ) {
					return array(
						'success' => false,
						'message' => "Post type {$post_type} not found.",
					);
				}

				$post_type_object = get_post_type_object( $post_type );
				if ( ! $post_type_object || empty( $post_type_object->public ) ) {
					return array(
						'success' => false,
						'message' => "Post type {$post_type} is not public.",
					);
				}

				$status = isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : 'publish';
				$limit  = isset( $input['limit'] ) ? max( 1, min( 500, (int) $input['limit'] ) ) : 100;
				$fix    = ! empty( $input['fix'] );

				$query = new WP_Query(
					array(
						'post_type'              => $post_type,
						'post_status'            => $status,
						'posts_per_page'         => $limit,
						'orderby'                => 'modified',
						'order'                  => 'DESC',
						'no_found_rows'          => true,
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
					)
				);

				$candidates = array();
				$fixed      = 0;

				foreach ( $query->posts as $post ) {
					if ( ! $post instanceof WP_Post ) {
						continue;
					}

					$has_h1            = mcp_abilities_generatepress_content_has_h1( (string) $post->post_content );
					$headline_disabled = 'true' === get_post_meta( $post->ID, '_generate-disable-headline', true );

					if ( ! $has_h1 || $headline_disabled ) {
						continue;
					}

					if ( $fix ) {
						update_post_meta( $post->ID, '_generate-disable-headline', 'true' );
						$headline_disabled = true;
						$fixed++;
					}

					$candidates[] = array(
						'id'                => (int) $post->ID,
						'title'             => get_the_title( $post ),
						'post_type'         => $post->post_type,
						'status'            => $post->post_status,
						'link'              => get_permalink( $post ),
						'has_h1'            => true,
						'disable_headline'  => $headline_disabled ? 'true' : '',
						'action'            => $fix ? 'disabled_generatepress_headline' : 'needs_disable_headline',
					);
				}

				return array(
					'success'    => true,
					'checked'    => count( $query->posts ),
					'found'      => count( $candidates ),
					'fixed'      => $fixed,
					'candidates' => $candidates,
					'message'    => $fix
						? "Checked {$limit} {$post_type} item(s) and disabled duplicate GeneratePress headlines on {$fixed} item(s)."
						: "Checked {$limit} {$post_type} item(s) for duplicate GeneratePress headlines.",
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
	// GENERATEPRESS - Audit Page Layout Meta
	// =========================================================================
	mcp_abilities_generatepress_register_ability(
		'generatepress/audit-page-layout-meta',
		array(
			'label'               => 'Audit GeneratePress Page Layout Meta',
			'description'         => 'Audits pages or page families for expected GeneratePress layout meta and optional required content markers. Can repair GeneratePress meta mismatches.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'post_type' => array(
						'type'        => 'string',
						'default'     => 'page',
						'description' => 'Public post type to audit. Defaults to page.',
					),
					'status' => array(
						'type'        => 'string',
						'enum'        => array( 'publish', 'draft', 'pending', 'private', 'any' ),
						'default'     => 'publish',
						'description' => 'Post status to audit.',
					),
					'ids' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'integer' ),
						'description' => 'Optional explicit post/page IDs to audit.',
					),
					'parent' => array(
						'type'        => 'integer',
						'description' => 'Optional parent page ID. When set, audits children of this parent.',
					),
					'include_descendants' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'When used with parent, include all descendants instead of only direct children.',
					),
					'limit' => array(
						'type'        => 'integer',
						'default'     => 100,
						'minimum'     => 1,
						'maximum'     => 500,
						'description' => 'Maximum posts to inspect.',
					),
					'disable_headline' => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'Expected GeneratePress disable-headline state.',
					),
					'sidebar_layout' => array(
						'type'        => 'string',
						'enum'        => array( '', 'right-sidebar', 'left-sidebar', 'no-sidebar', 'both-sidebars', 'both-left', 'both-right' ),
						'default'     => 'no-sidebar',
						'description' => 'Expected GeneratePress sidebar layout.',
					),
					'content_area' => array(
						'type'        => 'string',
						'enum'        => array( '', 'full-width', 'contained', 'full-width-content' ),
						'default'     => 'full-width-content',
						'description' => 'Expected GeneratePress content area.',
					),
					'required_content_patterns' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'Optional literal strings that must appear in post_content, useful for detecting missing reusable layout markers. These are reported but not auto-repaired.',
					),
					'fix' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'When true, repair GeneratePress meta mismatches. Content pattern misses are never auto-repaired.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'          => array( 'type' => 'boolean' ),
					'checked'          => array( 'type' => 'integer' ),
					'found'            => array( 'type' => 'integer' ),
					'fixed'            => array( 'type' => 'integer' ),
					'expected_meta'    => array( 'type' => 'object' ),
					'content_patterns' => array( 'type' => 'array' ),
					'items'            => array( 'type' => 'array' ),
					'message'          => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$input = is_array( $input ) ? $input : array();

				$post_type = isset( $input['post_type'] ) ? sanitize_key( (string) $input['post_type'] ) : 'page';
				if ( ! post_type_exists( $post_type ) ) {
					return array(
						'success' => false,
						'message' => "Post type {$post_type} not found.",
					);
				}

				$post_type_object = get_post_type_object( $post_type );
				if ( ! $post_type_object || empty( $post_type_object->public ) ) {
					return array(
						'success' => false,
						'message' => "Post type {$post_type} is not public.",
					);
				}

				$meta_map = mcp_abilities_generatepress_page_meta_map();
				$expected = mcp_abilities_generatepress_expected_layout_meta_from_input( $input );
				$fix      = ! empty( $input['fix'] );
				$patterns = array();

				if ( ! empty( $input['required_content_patterns'] ) && is_array( $input['required_content_patterns'] ) ) {
					foreach ( $input['required_content_patterns'] as $pattern ) {
						if ( is_string( $pattern ) && '' !== $pattern ) {
							$patterns[] = $pattern;
						}
					}
				}

				$collection = mcp_abilities_generatepress_collect_layout_audit_posts( $input );
				$items      = array();
				$fixed      = 0;

				foreach ( $collection['posts'] as $post ) {
					$mismatches = array();
					$meta_after = array();

					foreach ( $expected as $input_key => $expected_value ) {
						if ( empty( $meta_map[ $input_key ] ) ) {
							continue;
						}

						$meta_key     = $meta_map[ $input_key ];
						$actual_value = (string) get_post_meta( $post->ID, $meta_key, true );

						if ( $actual_value === $expected_value ) {
							$meta_after[ $input_key ] = $actual_value;
							continue;
						}

						$mismatches[ $input_key ] = array(
							'key'      => $meta_key,
							'expected' => $expected_value,
							'actual'   => $actual_value,
						);

						if ( $fix ) {
							if ( '' === $expected_value ) {
								delete_post_meta( $post->ID, $meta_key );
								$meta_after[ $input_key ] = '';
							} else {
								update_post_meta( $post->ID, $meta_key, $expected_value );
								$meta_after[ $input_key ] = $expected_value;
							}
						} else {
							$meta_after[ $input_key ] = $actual_value;
						}
					}

					$missing_patterns = mcp_abilities_generatepress_missing_content_patterns( $post, $patterns );

					if ( empty( $mismatches ) && empty( $missing_patterns ) ) {
						continue;
					}

					if ( $fix && ! empty( $mismatches ) ) {
						$fixed++;
					}

					$items[] = array(
						'id'                       => (int) $post->ID,
						'title'                    => get_the_title( $post ),
						'post_type'                => $post->post_type,
						'status'                   => $post->post_status,
						'parent_id'                => (int) $post->post_parent,
						'link'                     => get_permalink( $post ),
						'meta_mismatches'          => $mismatches,
						'meta_after'               => $meta_after,
						'missing_content_patterns' => $missing_patterns,
						'action'                   => $fix && ! empty( $mismatches ) ? 'repaired_generatepress_meta' : 'needs_review',
					);
				}

				return array(
					'success'          => true,
					'checked'          => count( $collection['posts'] ),
					'found'            => count( $items ),
					'fixed'            => $fixed,
					'expected_meta'    => $expected,
					'content_patterns' => $patterns,
					'items'            => $items,
					'message'          => $fix
						? "Checked " . count( $collection['posts'] ) . " {$post_type} item(s), repaired GeneratePress meta on {$fixed} item(s)."
						: "Checked " . count( $collection['posts'] ) . " {$post_type} item(s) for GeneratePress layout meta.",
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
	mcp_abilities_generatepress_register_ability(
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
	mcp_abilities_generatepress_register_ability(
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
	mcp_abilities_generatepress_register_ability(
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
	mcp_abilities_generatepress_register_ability(
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
	// GENERATEPRESS - Upsert Block Element
	// =========================================================================
	mcp_abilities_generatepress_register_ability(
		'generatepress/upsert-block-element',
		array(
			'label'               => 'Upsert GeneratePress Block Element',
			'description'         => 'Idempotently creates or updates one native GeneratePress Block Element at a stable slug with exact display conditions.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'title', 'slug', 'content', 'block_type', 'display_conditions' ),
				'properties'           => array(
					'title' => array(
						'type'        => 'string',
						'description' => 'Element title.',
					),
					'slug' => array(
						'type'        => 'string',
						'description' => 'Stable Element slug used for idempotent updates.',
					),
					'content' => array(
						'type'        => 'string',
						'description' => 'GenerateBlocks/Gutenberg block markup for the Element.',
					),
					'block_type' => array(
						'type' => 'string',
						'enum' => array( 'hook', 'content-template', 'loop-template', 'post-meta-template', 'post-navigation-template', 'archive-navigation-template', 'site-header', 'site-footer', 'right-sidebar', 'left-sidebar', 'search-modal' ),
						'description' => 'Native GeneratePress Block Element subtype.',
					),
					'hook' => array(
						'type'        => 'string',
						'description' => 'GeneratePress hook name.',
					),
					'priority' => array(
						'type'        => 'integer',
						'default'     => 10,
						'description' => 'Hook priority.',
					),
					'status' => array(
						'type'        => 'string',
						'default'     => 'publish',
						'description' => 'Post status (publish or draft).',
					),
					'display_conditions' => array(
						'type'        => 'array',
						'description' => 'Exact native GeneratePress Element display conditions.',
					),
					'exclude_conditions' => array(
						'type'        => 'array',
						'default'     => array(),
						'description' => 'Exact native GeneratePress Element exclusion conditions.',
					),
					'user_conditions' => array(
						'type'        => 'array',
						'default'     => array(),
						'description' => 'Exact native GeneratePress Element user conditions.',
					),
					'use_theme_post_container' => array(
						'type' => 'boolean',
						'default' => false,
						'description' => 'Whether a content template keeps the GeneratePress inside-article container.',
					),
					'post_loop_item_tagname' => array(
						'type' => 'string',
						'default' => 'article',
						'description' => 'Semantic wrapper tag for a content template.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'            => array( 'type' => 'boolean' ),
					'id'                 => array( 'type' => 'integer' ),
					'action'             => array( 'type' => 'string' ),
					'block_type'         => array( 'type' => 'string' ),
					'display_conditions' => array( 'type' => 'array' ),
					'message'            => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				if ( ! post_type_exists( 'gp_elements' ) ) {
					return array(
						'success' => false,
						'message' => 'GeneratePress Elements are not available (gp_elements post type missing).',
					);
				}

				return mcp_abilities_generatepress_upsert_block_element( $input );
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
	// GENERATEPRESS - Delete Element
	// =========================================================================
	mcp_abilities_generatepress_register_ability(
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
	mcp_abilities_generatepress_register_ability(
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
	// GENERATEPRESS - Featured Image Size Audit
	// =========================================================================
	mcp_abilities_generatepress_register_ability(
		'generatepress/audit-featured-image-sizes',
		array(
			'label'               => 'Audit Featured Image Sizes',
			'description'         => 'Audits posts for missing featured images and missing generated image sizes so native GeneratePress archive images can be trusted.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'post_type' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'Post types to audit. Defaults to post.',
					),
					'post_status' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'Post statuses to audit. Defaults to publish.',
					),
					'sizes' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'Image sizes to require. Defaults to thumbnail, medium, medium_large, large.',
					),
					'limit' => array(
						'type'        => 'integer',
						'default'     => 100,
						'minimum'     => 1,
						'maximum'     => 500,
						'description' => 'Maximum posts to audit.',
					),
					'page' => array(
						'type'        => 'integer',
						'default'     => 1,
						'minimum'     => 1,
						'description' => 'Result page.',
					),
					'only_problematic' => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'Only return posts with missing featured image or missing requested sizes.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'       => array( 'type' => 'boolean' ),
					'image_sizes'   => array( 'type' => 'object' ),
					'audited'       => array( 'type' => 'integer' ),
					'problem_count' => array( 'type' => 'integer' ),
					'items'         => array( 'type' => 'array' ),
					'message'       => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$post_types       = isset( $input['post_type'] ) && is_array( $input['post_type'] ) ? array_map( 'sanitize_key', $input['post_type'] ) : array( 'post' );
				$post_status      = isset( $input['post_status'] ) && is_array( $input['post_status'] ) ? array_map( 'sanitize_key', $input['post_status'] ) : array( 'publish' );
				$sizes            = isset( $input['sizes'] ) && is_array( $input['sizes'] ) ? array_values( array_filter( array_map( 'sanitize_key', $input['sizes'] ) ) ) : array( 'thumbnail', 'medium', 'medium_large', 'large' );
				$limit            = isset( $input['limit'] ) ? max( 1, min( 500, (int) $input['limit'] ) ) : 100;
				$page             = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
				$only_problematic = ! array_key_exists( 'only_problematic', $input ) || (bool) $input['only_problematic'];

				$query = new WP_Query(
					array(
						'post_type'      => $post_types,
						'post_status'    => $post_status,
						'posts_per_page' => $limit,
						'paged'          => $page,
						'orderby'        => 'date',
						'order'          => 'DESC',
						'fields'         => 'ids',
					)
				);

				$items         = array();
				$problem_count = 0;

				foreach ( $query->posts as $post_id ) {
					$post_id       = (int) $post_id;
					$thumbnail_id  = (int) get_post_thumbnail_id( $post_id );
					$missing_sizes = array();
					$size_audit    = array();

					if ( $thumbnail_id > 0 ) {
						$size_audit = mcp_abilities_generatepress_audit_attachment_image_sizes( $thumbnail_id, $sizes );
						foreach ( $size_audit as $size_name => $details ) {
							if ( empty( $details['exists'] ) ) {
								$missing_sizes[] = $size_name;
							}
						}
					}

					$problem = ( $thumbnail_id <= 0 || ! empty( $missing_sizes ) );
					if ( $problem ) {
						$problem_count++;
					}
					if ( $only_problematic && ! $problem ) {
						continue;
					}

					$items[] = array(
						'post_id'       => $post_id,
						'post_type'     => get_post_type( $post_id ),
						'title'         => get_the_title( $post_id ),
						'permalink'     => get_permalink( $post_id ),
						'thumbnail_id'  => $thumbnail_id,
						'missing_image' => ( $thumbnail_id <= 0 ),
						'missing_sizes' => $missing_sizes,
						'sizes'         => $size_audit,
					);
				}

				return array(
					'success'       => true,
					'image_sizes'   => mcp_abilities_generatepress_image_sizes(),
					'audited'       => count( $query->posts ),
					'problem_count' => $problem_count,
					'items'         => $items,
					'message'       => 'Featured image sizes audited successfully.',
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'upload_files' );
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

	mcp_abilities_generatepress_register_ability(
		'generatepress/regenerate-featured-image-sizes',
		array(
			'label'               => 'Regenerate Featured Image Sizes',
			'description'         => 'Regenerates attachment metadata for featured images on selected posts or recent posts, then reports remaining missing sizes.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'post_ids' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'integer' ),
						'description' => 'Specific post IDs whose featured image sizes should be regenerated.',
					),
					'post_type' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'Post types to scan when post_ids is omitted. Defaults to post.',
					),
					'post_status' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'Statuses to scan when post_ids is omitted. Defaults to publish.',
					),
					'sizes' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'Sizes to verify after regeneration.',
					),
					'limit' => array(
						'type'        => 'integer',
						'default'     => 25,
						'minimum'     => 1,
						'maximum'     => 100,
						'description' => 'Maximum posts to process when post_ids is omitted.',
					),
					'confirm' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Must be true because this rewrites attachment metadata.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'      => array( 'type' => 'boolean' ),
					'processed'    => array( 'type' => 'array' ),
					'failed'       => array( 'type' => 'object' ),
					'still_missing' => array( 'type' => 'array' ),
					'message'      => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				if ( empty( $input['confirm'] ) ) {
					return array(
						'success' => false,
						'message' => 'confirm=true is required before regenerating image metadata.',
					);
				}

				if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
					require_once ABSPATH . 'wp-admin/includes/image.php';
				}

				$post_ids = isset( $input['post_ids'] ) && is_array( $input['post_ids'] )
					? array_values( array_unique( array_filter( array_map( 'intval', $input['post_ids'] ) ) ) )
					: array();
				if ( empty( $post_ids ) ) {
					$post_types  = isset( $input['post_type'] ) && is_array( $input['post_type'] ) ? array_map( 'sanitize_key', $input['post_type'] ) : array( 'post' );
					$post_status = isset( $input['post_status'] ) && is_array( $input['post_status'] ) ? array_map( 'sanitize_key', $input['post_status'] ) : array( 'publish' );
					$limit       = isset( $input['limit'] ) ? max( 1, min( 100, (int) $input['limit'] ) ) : 25;
					$scan_limit  = min( 500, max( $limit * 4, $limit ) );
					$query       = new WP_Query(
						array(
							'post_type'      => $post_types,
							'post_status'    => $post_status,
							'posts_per_page' => $scan_limit,
							'orderby'        => 'date',
							'order'          => 'DESC',
							'fields'         => 'ids',
						)
					);
					foreach ( array_map( 'intval', $query->posts ) as $candidate_id ) {
						if ( (int) get_post_thumbnail_id( $candidate_id ) <= 0 ) {
							continue;
						}
						$post_ids[] = $candidate_id;
						if ( count( $post_ids ) >= $limit ) {
							break;
						}
					}
				}

				$sizes         = isset( $input['sizes'] ) && is_array( $input['sizes'] ) ? array_values( array_filter( array_map( 'sanitize_key', $input['sizes'] ) ) ) : array( 'thumbnail', 'medium', 'medium_large', 'large' );
				$processed     = array();
				$failed        = array();
				$still_missing = array();

				foreach ( $post_ids as $post_id ) {
					$attachment_id = (int) get_post_thumbnail_id( $post_id );
					if ( $attachment_id <= 0 ) {
						$failed[ $post_id ] = 'Post has no featured image.';
						continue;
					}
					$file = get_attached_file( $attachment_id );
					if ( ! is_string( $file ) || '' === $file || ! file_exists( $file ) ) {
						$failed[ $post_id ] = 'Original attachment file is missing.';
						continue;
					}

					$metadata = wp_generate_attachment_metadata( $attachment_id, $file );
					if ( empty( $metadata ) || ! is_array( $metadata ) ) {
						$failed[ $post_id ] = 'Attachment metadata regeneration failed.';
						continue;
					}
					wp_update_attachment_metadata( $attachment_id, $metadata );

					$audit   = mcp_abilities_generatepress_audit_attachment_image_sizes( $attachment_id, $sizes );
					$missing = array();
					foreach ( $audit as $size_name => $details ) {
						if ( empty( $details['exists'] ) ) {
							$missing[] = $size_name;
						}
					}
					if ( ! empty( $missing ) ) {
						$still_missing[] = array(
							'post_id'       => $post_id,
							'thumbnail_id'  => $attachment_id,
							'missing_sizes' => $missing,
						);
					}
					$processed[] = array(
						'post_id'      => $post_id,
						'thumbnail_id' => $attachment_id,
					);
				}

				return array(
					'success'       => true,
					'processed'     => $processed,
					'failed'        => $failed,
					'still_missing' => $still_missing,
					'message'       => 'Featured image metadata regeneration completed.',
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'upload_files' );
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
	mcp_abilities_generatepress_register_ability(
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
					'rolled_back' => array( 'type' => 'boolean' ),
					'restored' => array( 'type' => 'integer' ),
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
				$warm         = isset( $input['warm'] ) ? (bool) $input['warm'] : ( $delete_files || $has_post_ids );
				if ( $delete_files && null === $post_ids ) {
					return array(
						'success'      => false,
						'deleted'      => 0,
						'delete_files' => true,
						'warmed'       => array(),
						'failed'       => array( 'scope' => 'Destructive CSS refresh requires explicit post_ids.' ),
						'skipped'      => 0,
						'rolled_back'  => false,
						'restored'     => 0,
						'message'      => 'GenerateBlocks CSS refresh stopped before mutation because destructive global deletion is not supported.',
					);
				}
				$known_posts  = get_option( 'generateblocks_dynamic_css_posts', array() );
				$known_posts  = is_array( $known_posts ) ? $known_posts : array();
				$registry_before = $known_posts;
				$missing_option = new stdClass();
				$css_version_before = get_option( 'generateblocks_css_version', $missing_option );
				$css_time_before = get_option( 'generateblocks_dynamic_css_time', $missing_option );
				$global_ids   = array();
				if ( null === $post_ids ) {
					$known_ids      = array_values( array_unique( array_filter( array_map( 'intval', array_keys( $known_posts ) ) ) ) );
					$discovered_ids = mcp_abilities_generatepress_discover_generateblocks_post_ids();
					$global_ids     = array_values( array_unique( array_merge( $known_ids, $discovered_ids ) ) );
				}
				sort( $global_ids );
				$metadata_before = array();
				$rollback_ids    = null === $post_ids ? $global_ids : $post_ids;
				if ( $warm ) {
					foreach ( $rollback_ids as $post_id ) {
						$metadata_before[ $post_id ] = array(
							'exists' => metadata_exists( 'post', $post_id, '_generateblocks_dynamic_css_version' ),
							'value'  => get_post_meta( $post_id, '_generateblocks_dynamic_css_version', true ),
						);
					}
				}
				$files        = array();
				$file_backups = array();
				$filesystem   = null;

				if ( $delete_files ) {
					$filesystem = generateblocks_get_wp_filesystem();
					if ( ! $filesystem ) {
						return array(
							'success'      => false,
							'deleted'      => 0,
							'delete_files' => true,
							'warmed'       => array(),
							'failed'       => array( 'filesystem' => 'WordPress filesystem is unavailable.' ),
							'skipped'      => 0,
							'rolled_back'  => false,
							'restored'     => 0,
							'message'      => 'GenerateBlocks CSS refresh stopped before deletion because the WordPress filesystem is unavailable.',
						);
					}
					foreach ( $rollback_ids as $post_id ) {
						$files[] = mcp_abilities_generatepress_generateblocks_css_path( $post_id );
					}
					foreach ( $files as $file ) {
						$file_backups[ $file ] = null;
						if ( ! file_exists( $file ) ) {
							continue;
						}
						$prior_content = $filesystem->get_contents( $file );
						if ( false === $prior_content ) {
							return array(
								'success'      => false,
								'deleted'      => 0,
								'delete_files' => true,
								'warmed'       => array(),
								'failed'       => array( 'snapshot' => 'An existing CSS file could not be read safely.' ),
								'skipped'      => 0,
								'rolled_back'  => false,
								'restored'     => 0,
								'message'      => 'GenerateBlocks CSS refresh stopped before deletion because a prior file could not be snapshotted.',
							);
						}
						$file_backups[ $file ] = $prior_content;
					}
					foreach ( $files as $file ) {
						if ( file_exists( $file ) && ! wp_delete_file( $file ) ) {
							$delete_rollback_ok = true;
							$delete_restored    = 0;
							foreach ( $file_backups as $restore_file => $prior_content ) {
								if ( is_string( $prior_content ) ) {
									$put_ok = $filesystem->put_contents( $restore_file, $prior_content, defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644 );
									$exact_restore = $put_ok && $prior_content === $filesystem->get_contents( $restore_file );
									$delete_rollback_ok = $exact_restore && $delete_rollback_ok;
									if ( $exact_restore ) {
										$delete_restored++;
									}
								}
							}
							return array(
								'success'      => false,
								'deleted'      => $deleted,
								'delete_files' => true,
								'warmed'       => array(),
								'failed'       => array( 'delete' => 'A CSS file could not be deleted safely.' ),
								'skipped'      => 0,
								'rolled_back'  => $delete_rollback_ok,
								'restored'     => $delete_restored,
								'message'      => $delete_rollback_ok
									? 'GenerateBlocks CSS refresh stopped because deletion failed; prior files were restored.'
									: 'GenerateBlocks CSS refresh stopped because deletion failed and rollback was incomplete.',
							);
						}
						if ( isset( $file_backups[ $file ] ) ) {
							$deleted++;
						}
					}
				}

				// A targeted clear must preserve unrelated registry ownership. A global
				// clear may reset the registry because the complete authoritative post
				// inventory has already been captured from WordPress content above.
				if ( null !== $post_ids ) {
					foreach ( $post_ids as $post_id ) {
						unset( $known_posts[ $post_id ], $known_posts[ (string) $post_id ] );
						if (
							defined( 'GENERATEBLOCKS_VERSION' )
							&& false !== strpos( (string) get_post_field( 'post_content', $post_id, 'raw' ), '<!-- wp:generateblocks/' )
						) {
							update_post_meta( $post_id, '_generateblocks_dynamic_css_version', sanitize_text_field( GENERATEBLOCKS_VERSION ) );
						} else {
							delete_post_meta( $post_id, '_generateblocks_dynamic_css_version' );
						}
					}
					update_option( 'generateblocks_dynamic_css_posts', $known_posts );
				} else {
					delete_option( 'generateblocks_css_version' );
					update_option( 'generateblocks_dynamic_css_posts', array() );
				}

				$warm_result = array(
					'warmed'  => array(),
					'failed'  => array(),
					'skipped' => 0,
				);
				if ( $warm ) {
					$limit       = isset( $input['limit'] ) ? max( 1, min( 500, (int) $input['limit'] ) ) : 100;
					$warm_result = mcp_abilities_generatepress_warm_generateblocks_css( null === $post_ids ? $global_ids : $post_ids, $limit );
				}
				$refresh_failed = $warm && ( ! empty( $warm_result['failed'] ) || 0 < $warm_result['skipped'] );
				$rolled_back    = false;
				$restored       = 0;
				if ( $refresh_failed ) {
					update_option( 'generateblocks_dynamic_css_posts', $registry_before );
					$rollback_ok = $registry_before === get_option( 'generateblocks_dynamic_css_posts', array() );
					if ( $css_version_before === $missing_option ) {
						delete_option( 'generateblocks_css_version' );
						$rollback_ok = $missing_option === get_option( 'generateblocks_css_version', $missing_option ) && $rollback_ok;
					} else {
						update_option( 'generateblocks_css_version', $css_version_before );
						$rollback_ok = $css_version_before === get_option( 'generateblocks_css_version', $missing_option ) && $rollback_ok;
					}
					if ( $css_time_before === $missing_option ) {
						delete_option( 'generateblocks_dynamic_css_time' );
						$rollback_ok = $missing_option === get_option( 'generateblocks_dynamic_css_time', $missing_option ) && $rollback_ok;
					} else {
						update_option( 'generateblocks_dynamic_css_time', $css_time_before );
						$rollback_ok = $css_time_before === get_option( 'generateblocks_dynamic_css_time', $missing_option ) && $rollback_ok;
					}
					foreach ( $metadata_before as $post_id => $prior_meta ) {
						if ( ! empty( $prior_meta['exists'] ) ) {
							update_post_meta( $post_id, '_generateblocks_dynamic_css_version', $prior_meta['value'] );
							$rollback_ok = metadata_exists( 'post', $post_id, '_generateblocks_dynamic_css_version' )
								&& $prior_meta['value'] === get_post_meta( $post_id, '_generateblocks_dynamic_css_version', true )
								&& $rollback_ok;
						} else {
							delete_post_meta( $post_id, '_generateblocks_dynamic_css_version' );
							$rollback_ok = ! metadata_exists( 'post', $post_id, '_generateblocks_dynamic_css_version' ) && $rollback_ok;
						}
					}
					if ( $delete_files && $file_backups ) {
						$filesystem = $filesystem ?: generateblocks_get_wp_filesystem();
						foreach ( $file_backups as $file => $prior_content ) {
							if ( null === $prior_content || false === $prior_content ) {
								if ( file_exists( $file ) && ! wp_delete_file( $file ) ) {
									$rollback_ok = false;
								}
								$rollback_ok = ! file_exists( $file ) && $rollback_ok;
								continue;
							}
							$put_ok = $filesystem && $filesystem->put_contents( $file, $prior_content, defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644 );
							if ( ! $put_ok || $prior_content !== $filesystem->get_contents( $file ) ) {
								$rollback_ok = false;
							} else {
								$restored++;
							}
						}
					}
					$rolled_back = $rollback_ok;
				}

				return array(
					'success' => ! $refresh_failed,
					'deleted' => $deleted,
					'delete_files' => $delete_files,
					'warmed'  => $warm_result['warmed'],
					'failed'  => $warm_result['failed'],
					'skipped' => $warm_result['skipped'],
					'rolled_back' => $rolled_back,
					'restored' => $restored,
					'message' => $refresh_failed
						? ( $rolled_back
							? 'GenerateBlocks CSS refresh failed and the prior cache state was restored.'
							: 'GenerateBlocks CSS refresh failed and rollback was incomplete.' )
						: ( $warm
						? sprintf(
							'Cleared GenerateBlocks CSS metadata; %1$d file(s) deleted; warmed %2$d post(s); %3$d failed; %4$d skipped.',
							$deleted,
							count( $warm_result['warmed'] ),
							count( $warm_result['failed'] ),
							$warm_result['skipped']
						)
						: ( $delete_files
							? "Cleared GenerateBlocks CSS metadata and deleted {$deleted} CSS file(s)."
							: 'Cleared GenerateBlocks CSS metadata; existing CSS files were preserved.' ) ),
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
