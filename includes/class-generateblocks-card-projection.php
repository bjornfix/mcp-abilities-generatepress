<?php
/**
 * Token-preserving GenerateBlocks Query card projection.
 *
 * @package MCP_Abilities_GeneratePress
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns the reusable contract between native Query cards and localized copy.
 */
final class MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection {
	private const ARTIFACT_SOURCE_REWRITE = 'source_rewrite';
	private const ARTIFACT_TRANSLATION    = 'translation';

	/** Register the GenerateBlocks runtime and Workflow projection Adapters. */
	public static function register(): void {
		add_filter( 'render_block_data', array( __CLASS__, 'project_rendered_card_media' ), 15, 3 );
		add_filter( 'generateblocks_dynamic_tag_replacement', array( __CLASS__, 'filter_dynamic_tag_replacement' ), 20, 2 );
		add_filter( 'devenia_workflow_translatable_block_html_fragments', array( __CLASS__, 'translatable_html_fragments' ), 10, 4 );
		add_filter( 'devenia_workflow_structured_text_attr_fragments', array( __CLASS__, 'structured_attr_fragments' ), 10, 3 );
		add_filter( 'devenia_workflow_project_translatable_block_html_fragment', array( __CLASS__, 'project_workflow_html_fragment' ), 10, 4 );
		add_filter( 'devenia_workflow_validate_localized_fragment_value', array( __CLASS__, 'validate_localized_fragment_value' ), 10, 4 );
		add_filter( 'devenia_workflow_source_rewrite_artifact_policy', array( __CLASS__, 'validate_source_rewrite_artifact' ), 10, 4 );
		add_filter( 'devenia_workflow_translation_job_artifact_policy', array( __CLASS__, 'validate_translation_artifact' ), 10, 4 );
		add_filter( 'devenia_workflow_dynamic_inventory_contracts', array( __CLASS__, 'dynamic_inventory_contracts' ), 10, 2 );
		add_filter( 'devenia_workflow_validate_dynamic_inventory', array( __CLASS__, 'validate_dynamic_inventory' ), 10, 4 );
	}

	/**
	 * Add one native dynamic featured-image slot to every complete Query card.
	 *
	 * The card declares its semantic roles; this Adapter owns the reusable media
	 * composition. No caller, page, post type, category, or language is special.
	 *
	 * @param array<string,mixed> $parsed_block Parsed render block.
	 * @param array<string,mixed> $source_block Original parsed block.
	 * @param mixed               $parent_block Parent render block.
	 * @return array<string,mixed>
	 * @since 1.1.44
	 */
	public static function project_rendered_card_media( array $parsed_block, array $source_block, $parent_block = null ): array {
		unset( $source_block, $parent_block );
		$name = (string) ( $parsed_block['blockName'] ?? '' );
		if ( 'generateblocks/loop-item' === $name ) {
			return self::project_featured_image_into_card( $parsed_block );
		}
		if ( 'generateblocks/query' !== $name ) {
			return $parsed_block;
		}

		return self::project_query_card_media( $parsed_block );
	}

	/** Project nested cards before GenerateBlocks renders its Query subtree directly. */
	private static function project_query_card_media( array $block ): array {
		foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $index => $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			$block['innerBlocks'][ $index ] = 'generateblocks/loop-item' === (string) ( $child['blockName'] ?? '' )
				? self::project_featured_image_into_card( $child )
				: self::project_query_card_media( $child );
		}
		return $block;
	}

	/**
	 * @param array<string,mixed> $block Parsed Query-card block.
	 * @return array<string,mixed>
	 * @since 1.1.44
	 */
	public static function project_featured_image_into_card( array $block ): array {
		$target_path = self::deepest_complete_card_path( $block );
		if ( null === $target_path || self::contains_featured_image( $block ) ) {
			return $block;
		}

		$target =& $block;
		foreach ( $target_path as $index ) {
			$target =& $target['innerBlocks'][ $index ];
		}
		$target['innerBlocks'] = is_array( $target['innerBlocks'] ?? null ) ? $target['innerBlocks'] : array();
		array_unshift( $target['innerBlocks'], self::featured_image_block() );
		$target['innerContent'] = is_array( $target['innerContent'] ?? null ) ? $target['innerContent'] : array();
		$first_child = array_search( null, $target['innerContent'], true );
		if ( false === $first_child ) {
			$target['innerContent'][] = null;
		} else {
			array_splice( $target['innerContent'], (int) $first_child, 0, array( null ) );
		}
		return $block;
	}

	/** @param array<string,mixed> $block @return array<int,int>|null */
	private static function deepest_complete_card_path( array $block ): ?array {
		$best = null;
		$walk = static function ( array $candidate, array $path ) use ( &$walk, &$best ): void {
			$summary = false;
			$action  = false;
			self::collect_card_projection_roles( array( $candidate ), $summary, $action );
			if ( $summary && $action && ( null === $best || count( $path ) > count( $best ) ) ) {
				$best = $path;
			}
			foreach ( (array) ( $candidate['innerBlocks'] ?? array() ) as $index => $child ) {
				if ( is_array( $child ) ) {
					$walk( $child, array_merge( $path, array( (int) $index ) ) );
				}
			}
		};
		$walk( $block, array() );
		return $best;
	}

	private static function collect_card_projection_roles( array $blocks, bool &$summary, bool &$action ): void {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$name  = (string) ( $block['blockName'] ?? '' );
			$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
			$html  = (string) ( $block['innerHTML'] ?? '' );
			if ( self::is_role( $name, $attrs, 'data-devenia-card-summary', 'explicit' ) && false !== strpos( $html, '{{post_excerpt}}' ) ) {
				$summary = true;
			}
			if (
				self::is_role( $name, $attrs, 'data-devenia-card-action', 'plugin-details' )
				&& '{{post_permalink}}' === (string) ( $attrs['htmlAttributes']['href'] ?? '' )
				&& false !== strpos( (string) ( $attrs['htmlAttributes']['aria-label'] ?? '' ), '{{post_title}}' )
				&& preg_match( '/<a\b[^>]*>(.*?)<\/a>/isu', $html, $matches )
				&& self::is_plain_action_text( (string) ( $matches[1] ?? '' ) )
			) {
				$action = true;
			}
			self::collect_card_projection_roles( (array) ( $block['innerBlocks'] ?? array() ), $summary, $action );
		}
	}

	/** @param array<string,mixed> $block */
	private static function contains_featured_image( array $block ): bool {
		if ( 'core/post-featured-image' === (string) ( $block['blockName'] ?? '' ) ) {
			return true;
		}
		foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $child ) {
			if ( is_array( $child ) && self::contains_featured_image( $child ) ) {
				return true;
			}
		}
		return false;
	}

	/** @return array<string,mixed> */
	private static function featured_image_block(): array {
		return array(
			'blockName'    => 'core/post-featured-image',
			'attrs'        => array(
				'isLink'      => true,
				'aspectRatio' => '1',
				'scale'       => 'contain',
				'sizeSlug'    => 'medium',
				'style'       => array( 'spacing' => array( 'margin' => array( 'top' => '0', 'bottom' => '1.25rem' ) ) ),
			),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}

	/**
	 * Replace GenerateBlocks' generated excerpt with the explicit field only for
	 * a block that declares the card-summary contract.
	 *
	 * @param mixed               $replacement Upstream replacement.
	 * @param array<string,mixed> $context GenerateBlocks tag context.
	 * @return mixed
	 */
	public static function filter_dynamic_tag_replacement( $replacement, array $context ) {
		if ( 'post_excerpt' !== (string) ( $context['tag'] ?? '' ) ) {
			return $replacement;
		}

		$block = is_array( $context['block'] ?? null ) ? $context['block'] : array();
		$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		if ( ! self::is_role( (string) ( $block['blockName'] ?? '' ), $attrs, 'data-devenia-card-summary', 'explicit' ) ) {
			return $replacement;
		}

		$options  = is_array( $context['options'] ?? null ) ? $context['options'] : array();
		$instance = is_object( $context['instance'] ?? null ) ? $context['instance'] : null;
		$post_id  = 0;
		if ( class_exists( 'GenerateBlocks_Dynamic_Tags' ) && is_callable( array( 'GenerateBlocks_Dynamic_Tags', 'get_id' ) ) ) {
			$post_id = (int) GenerateBlocks_Dynamic_Tags::get_id( $options, 'post', $instance );
		} elseif ( function_exists( 'get_the_ID' ) ) {
			$post_id = (int) get_the_ID();
		}

		$post = $post_id > 0 && function_exists( 'get_post' ) ? get_post( $post_id ) : null;
		if ( ! $post || ! isset( $post->post_excerpt ) ) {
			return '';
		}

		$excerpt        = trim( (string) $post->post_excerpt );
		$max_characters = self::summary_max_characters( $attrs );
		$validation     = self::validate_one_summary( $excerpt, 'canonical', $max_characters );
		return empty( $validation ) ? $excerpt : '';
	}

	/**
	 * Expose the visible action text as a localized fragment without owning the
	 * surrounding native link or its dynamic tags.
	 *
	 * @param array<int,array<string,mixed>> $fragments Existing fragments.
	 * @param array<string,mixed>            $attrs Block attributes.
	 * @return array<int,array<string,mixed>>
	 */
	public static function translatable_html_fragments( array $fragments, string $block_name, array $attrs, string $html ): array {
		if ( ! self::is_role( $block_name, $attrs, 'data-devenia-card-action', 'plugin-details' ) ) {
			return $fragments;
		}

		if ( ! preg_match( '/^\s*<a\b[^>]*>(.*?)<\/a>\s*$/isu', $html, $matches ) ) {
			return $fragments;
		}

		$source_html = trim( (string) $matches[1] );
		if ( ! self::is_plain_action_text( $source_html ) ) {
			return $fragments;
		}

		$fragments[] = array(
			'id'          => 'devenia-generateblocks-card-action-label',
			'role'        => 'devenia_generateblocks_card_action',
			'heading'     => false,
			'text'        => trim( wp_strip_all_tags( $source_html ) ),
			'source_html' => $source_html,
		);

		return $fragments;
	}

	/**
	 * Expose the action accessible name through Workflow's structured-attribute
	 * projection Interface.
	 *
	 * @param array<int,array<string,mixed>> $fragments Existing fragments.
	 * @param array<string,mixed>            $attrs Block attributes.
	 * @return array<int,array<string,mixed>>
	 */
	public static function structured_attr_fragments( array $fragments, string $block_name, array $attrs ): array {
		if ( ! self::is_role( $block_name, $attrs, 'data-devenia-card-action', 'plugin-details' ) ) {
			return $fragments;
		}

		$label = trim( (string) ( $attrs['htmlAttributes']['aria-label'] ?? '' ) );
		if ( '' === $label ) {
			return $fragments;
		}

		$fragments[] = array(
			'attr_path'  => array( 'htmlAttributes', 'aria-label' ),
			'label_path' => 'htmlAttributes.aria-label',
			'row_id'     => 'card-action',
			'field'      => 'aria-label',
			'role'       => 'devenia_generateblocks_card_accessible_name',
			'html_context' => 'attribute_plain_text',
			'heading'    => false,
			'text'       => $label,
		);

		return $fragments;
	}

	/** Project one exact localized fragment while preserving every dynamic tag. */
	public static function project_translatable_html_fragment( string $html, string $source_html, string $localized_html ): string {
		if ( '' === $source_html || false === strpos( $html, $source_html ) ) {
			return $html;
		}

		$projected = preg_replace_callback(
			'/' . preg_quote( $source_html, '/' ) . '/u',
			static fn( array $matches ): string => $localized_html,
			$html,
			1
		);
		if ( ! is_string( $projected ) || self::dynamic_tags( $html ) !== self::dynamic_tags( $projected ) ) {
			return $html;
		}

		return $projected;
	}

	/** Workflow filter Adapter for the token-preserving projection Interface. */
	public static function project_workflow_html_fragment( string $html, string $source_html, string $localized_html, array $fragment ): string {
		if ( 'devenia-generateblocks-card-action-label' !== (string) ( $fragment['id'] ?? '' ) ) {
			return $html;
		}
		if ( '{{text}}' === $localized_html ) {
			return '' !== $source_html ? str_replace( $source_html, '{{text}}', $html ) : $html;
		}
		if ( 'devenia_generateblocks_card_action' === (string) ( $fragment['role'] ?? '' ) ) {
			$localized_html = esc_html( $localized_html );
		}

		return self::project_translatable_html_fragment( $html, $source_html, $localized_html );
	}

	/**
	 * Preserve the exact dynamic title token in the localized accessible name.
	 *
	 * @param array<string,mixed> $result Existing validation result.
	 * @param array<string,mixed> $source_fragment Source contract fragment.
	 * @param array<string,mixed> $localized_fragment Submitted fragment.
	 * @return array<string,mixed>
	 */
	public static function validate_localized_fragment_value( array $result, array $source_fragment, array $localized_fragment, string $localized_value ): array {
		unset( $localized_fragment );
		$role = (string) ( $source_fragment['role'] ?? '' );
		if (
			empty( $result['success'] )
			|| ! in_array( $role, array( 'devenia_generateblocks_card_action', 'devenia_generateblocks_card_accessible_name' ), true )
		) {
			return $result;
		}
		$plain = trim( wp_strip_all_tags( $localized_value ) );
		if ( $plain !== trim( $localized_value ) || preg_match( '/[\r\n]/', $localized_value ) ) {
			return array( 'success' => false, 'code' => 'plain_text_required' );
		}

		$source_value = (string) ( $source_fragment['source_html'] ?? $source_fragment['text'] ?? '' );
		if ( self::dynamic_tags( $source_value ) !== self::dynamic_tags( $localized_value ) ) {
			return array(
				'success' => false,
				'code'    => 'dynamic_tags_changed',
			);
		}

		return $result;
	}

	/**
	 * Require bounded explicit summaries for children of a declared Query card
	 * inventory.
	 *
	 * @param array<string,mixed> $result Existing policy result.
	 * @param mixed               $source Source post.
	 * @param array<string,mixed> $artifact Translation Artifact.
	 * @param array<string,mixed> $job Translation Job.
	 * @return array<string,mixed>
	 */
	public static function validate_translation_artifact( array $result, $source, array $artifact, array $job ): array {
		unset( $job );
		return self::validate_artifact_summaries( $result, $source, $artifact, self::ARTIFACT_TRANSLATION );
	}

	/**
	 * Require the proposed source summary to satisfy the same declared Query
	 * card contract before Source Rewrite creates an immutable review candidate.
	 *
	 * @param array<string,mixed> $result Existing policy result.
	 * @param mixed               $source Source post.
	 * @param array<string,mixed> $artifact Proposed source values.
	 * @param array<string,mixed> $job Source Rewrite Job.
	 * @return array<string,mixed>
	 */
	public static function validate_source_rewrite_artifact( array $result, $source, array $artifact, array $job ): array {
		unset( $job );
		return self::validate_artifact_summaries( $result, $source, $artifact, self::ARTIFACT_SOURCE_REWRITE );
	}

	/**
	 * Validate summaries against every applicable parent Query card contract.
	 *
	 * Translation validates both the stored source and localized summary. Source
	 * Rewrite validates only its proposed replacement, so it can repair a stale
	 * or overlong current summary.
	 *
	 * @param array<string,mixed> $result Existing policy result.
	 * @param mixed               $source Source post.
	 * @param array<string,mixed> $artifact Proposed source or translation values.
	 * @return array<string,mixed>
	 */
	private static function validate_artifact_summaries( array $result, $source, array $artifact, string $artifact_kind ): array {
		if ( empty( $result['success'] ) || ! is_object( $source ) ) {
			return $result;
		}

		$parent_id = (int) ( $source->post_parent ?? 0 );
		$parent    = $parent_id > 0 && function_exists( 'get_post' ) ? get_post( $parent_id ) : null;
		if ( ! is_object( $parent ) || ! function_exists( 'parse_blocks' ) ) {
			return $result;
		}

		$contracts = self::find_card_inventory_contracts( parse_blocks( (string) ( $parent->post_content ?? '' ) ) );
		if ( ! $contracts ) {
			return $result;
		}

		$post_type = strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) ( $source->post_type ?? '' ) ) ?? '' );
		$applicable_contracts = array_values(
			array_filter(
				$contracts,
				static fn( array $contract ): bool => in_array( $post_type, (array) ( $contract['post_types'] ?? array() ), true )
			)
		);
		if ( ! $applicable_contracts ) {
			return $result;
		}

		foreach ( $applicable_contracts as $contract ) {
			if ( empty( $contract['valid'] ) ) {
				return array( 'success' => false, 'code' => 'card_query_contract_invalid', 'issues' => (array) ( $contract['issues'] ?? array() ) );
			}
			$summary = self::ARTIFACT_TRANSLATION === $artifact_kind
				? self::validate_card_summary_contract(
					(string) ( $source->post_excerpt ?? '' ),
					(string) ( $artifact['excerpt'] ?? '' ),
					(int) $contract['max_characters']
				)
				: self::validate_source_summary_contract(
					(string) ( $artifact['excerpt'] ?? '' ),
					(int) $contract['max_characters']
				);
			if ( empty( $summary['success'] ) ) {
				return $summary;
			}
		}

		return $result;
	}

	/** @return array<string,mixed> */
	private static function validate_source_summary_contract( string $source_excerpt, int $max_characters ): array {
		$issues = self::validate_one_summary( $source_excerpt, 'source', max( 1, $max_characters ) );
		return $issues
			? array( 'success' => false, 'code' => 'card_summary_contract_invalid', 'issues' => $issues )
			: array( 'success' => true );
	}

	/**
	 * @return array{max_characters:int,post_types:array<int,string>,valid:bool,issues:array<int,string>}|null
	 */
	public static function find_card_inventory_contract( array $blocks ): ?array {
		$contracts = self::find_card_inventory_contracts( $blocks );
		return $contracts[0] ?? null;
	}

	/**
	 * Discover every declared card inventory, including malformed declarations
	 * that must fail closed instead of disappearing from Workflow.
	 *
	 * @return array<int,array{max_characters:int,post_types:array<int,string>,valid:bool,issues:array<int,string>}>
	 */
	public static function find_card_inventory_contracts( array $blocks ): array {
		$contracts = array();
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
			$html_attributes = is_array( $attrs['htmlAttributes'] ?? null ) ? $attrs['htmlAttributes'] : array();
			$query = is_array( $attrs['query'] ?? null ) ? $attrs['query'] : array();
			if (
				'generateblocks/query' === (string) ( $block['blockName'] ?? '' )
				&& 'plugin-pages' === (string) ( $html_attributes['data-devenia-card-inventory'] ?? '' )
			) {
				$max        = max( 1, min( 300, (int) ( $html_attributes['data-devenia-card-summary-max'] ?? 120 ) ) );
				$post_types = self::query_post_types( $query );
				$issues     = array_merge(
					self::query_card_role_issues( (array) ( $block['innerBlocks'] ?? array() ), $max ),
					self::complete_inventory_query_issues( $query )
				);
				$current_parents = array_values( array_unique( array_map( 'strval', (array) ( $query['post_parent__in'] ?? array() ) ) ) );
				if ( array( 'current' ) !== $current_parents ) {
					$issues[] = 'current_parent_query_required';
				}
				$contracts[] = array( 'max_characters' => $max, 'post_types' => $post_types, 'valid' => ! $issues, 'issues' => $issues );
			}

			$children = is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : array();
			$contracts = array_merge( $contracts, self::find_card_inventory_contracts( $children ) );
		}

		return $contracts;
	}

	/** @return array<string,mixed> */
	public static function validate_card_summary_contract( string $source_excerpt, string $localized_excerpt, int $max_characters = 120 ): array {
		$max_characters = max( 1, $max_characters );
		$issues         = array();
		foreach ( array( 'source' => $source_excerpt, 'localized' => $localized_excerpt ) as $surface => $value ) {
			$issues = array_merge( $issues, self::validate_one_summary( $value, $surface, $max_characters ) );
		}

		return $issues
			? array( 'success' => false, 'code' => 'card_summary_contract_invalid', 'issues' => $issues )
			: array( 'success' => true );
	}

	/** Expose declared Query inventory contracts to Workflow's relation owner. */
	public static function dynamic_inventory_contracts( array $contracts, array $blocks ): array {
		foreach ( self::find_card_inventory_contracts( $blocks ) as $contract ) {
			$contracts[] = array_merge( array( 'adapter' => 'devenia-generateblocks-card-projection', 'type' => 'translated_direct_children' ), $contract );
		}
		return $contracts;
	}

	/** Run the reusable direct-child/card-summary validator for Workflow. */
	public static function validate_dynamic_inventory( array $result, array $rendered_cards, array $direct_child_ids, int $max_characters ): array {
		if ( empty( $result['success'] ) ) {
			return $result;
		}
		$validation = self::validate_inventory( $rendered_cards, $direct_child_ids, $max_characters );
		return ! empty( $validation['valid'] )
			? array( 'success' => true, 'validation' => $validation )
			: array( 'success' => false, 'code' => 'dynamic_card_inventory_invalid', 'validation' => $validation );
	}

	/**
	 * Validate one rendered parent inventory against its authoritative direct
	 * children and bounded explicit summaries.
	 *
	 * @param array<int,array{id:int,excerpt:string}> $rendered_cards Rendered card records.
	 * @param array<int,int>                          $direct_child_ids Authoritative children.
	 * @return array<string,mixed>
	 */
	public static function validate_inventory( array $rendered_cards, array $direct_child_ids, int $max_characters = 120 ): array {
		$rendered_ids      = array();
		$empty_excerpt_ids = array();
		$long_excerpt_ids  = array();
		$invalid_excerpt_ids = array();
		$max_characters    = max( 1, $max_characters );

		foreach ( $rendered_cards as $card ) {
			$id = (int) ( $card['id'] ?? 0 );
			if ( $id < 1 ) {
				continue;
			}
			$rendered_ids[] = $id;
			$excerpt = (string) ( $card['excerpt'] ?? '' );
			foreach ( self::validate_one_summary( $excerpt, 'localized', $max_characters ) as $issue ) {
				$reason = (string) ( $issue['reason'] ?? '' );
				if ( 'explicit_summary_required' === $reason ) {
					$empty_excerpt_ids[] = $id;
				} elseif ( 'summary_too_long' === $reason ) {
					$long_excerpt_ids[] = $id;
				} else {
					$invalid_excerpt_ids[] = $id;
				}
			}
		}

		$rendered_ids     = array_values( array_unique( $rendered_ids ) );
		$direct_child_ids = array_values( array_unique( array_filter( array_map( 'intval', $direct_child_ids ) ) ) );
		$missing_ids      = array_values( array_diff( $direct_child_ids, $rendered_ids ) );
		$unexpected_ids   = array_values( array_diff( $rendered_ids, $direct_child_ids ) );

		return array(
			'valid'             => ! $missing_ids && ! $unexpected_ids && ! $empty_excerpt_ids && ! $long_excerpt_ids && ! $invalid_excerpt_ids,
			'missing_ids'       => $missing_ids,
			'unexpected_ids'    => $unexpected_ids,
			'empty_excerpt_ids' => $empty_excerpt_ids,
			'long_excerpt_ids'  => $long_excerpt_ids,
			'invalid_excerpt_ids' => $invalid_excerpt_ids,
		);
	}

	/** @return array<int,string> */
	private static function query_post_types( array $query ): array {
		$post_types = array();
		foreach ( (array) ( $query['post_type'] ?? array( 'page' ) ) as $post_type ) {
			$post_type = strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $post_type ) ?? '' );
			if ( '' !== $post_type ) {
				$post_types[] = $post_type;
			}
		}
		return $post_types ? array_values( array_unique( $post_types ) ) : array( 'page' );
	}

	/** @return array<int,string> */
	private static function complete_inventory_query_issues( array $query ): array {
		$issues = array();
		if ( -1 !== (int) ( $query['posts_per_page'] ?? 0 ) ) {
			$issues[] = 'complete_inventory_query_required';
		}
		$allowed = array( 'post_type', 'posts_per_page', 'post_parent__in', 'orderby', 'order' );
		if ( array_diff( array_keys( $query ), $allowed ) ) {
			$issues[] = 'unrestricted_direct_children_query_required';
		}
		return $issues;
	}

	/** @param array<string,mixed> $attrs */
	private static function is_role( string $block_name, array $attrs, string $attribute, string $value ): bool {
		return 'generateblocks/text' === $block_name
			&& $value === (string) ( $attrs['htmlAttributes'][ $attribute ] ?? '' );
	}

	/** @param array<string,mixed> $attrs */
	private static function summary_max_characters( array $attrs ): int {
		$raw = (int) ( $attrs['htmlAttributes']['data-devenia-card-summary-max'] ?? 120 );
		return max( 1, min( 300, $raw ) );
	}

	/** @return array<int,array<string,mixed>> */
	private static function validate_one_summary( string $value, string $surface, int $max_characters ): array {
		$plain = trim( wp_strip_all_tags( $value ) );
		if ( '' === $plain ) {
			return array( array( 'surface' => $surface, 'reason' => 'explicit_summary_required' ) );
		}
		if ( $plain !== trim( $value ) || preg_match( '/[\r\n]/', $value ) ) {
			return array( array( 'surface' => $surface, 'reason' => 'plain_single_paragraph_required' ) );
		}
		if ( self::text_length( $plain ) > $max_characters ) {
			return array( array( 'surface' => $surface, 'reason' => 'summary_too_long', 'max_characters' => $max_characters ) );
		}
		return array();
	}

	/** @return array<int,string> */
	private static function query_card_role_issues( array $blocks, int $max_characters ): array {
		$templates = array();
		self::collect_query_card_templates( $blocks, $max_characters, $templates );
		$complete_templates = array_filter(
			$templates,
			static fn( array $template ): bool => ! empty( $template['summary'] ) && ! empty( $template['action'] )
		);
		if ( $templates && count( $complete_templates ) === count( $templates ) ) {
			return array();
		}
		$found_summary = (bool) array_filter( array_column( $templates, 'summary' ) );
		$found_action  = (bool) array_filter( array_column( $templates, 'action' ) );
		$issues = array();
		if ( ! $found_summary ) {
			$issues[] = 'explicit_summary_role_missing';
		}
		if ( ! $found_action ) {
			$issues[] = 'localized_action_role_missing';
		}
		if ( $found_summary && $found_action ) {
			$issues[] = 'coherent_card_template_required';
		}
		return $issues;
	}

	/** @param array<int,array{summary:bool,action:bool}> $templates */
	private static function collect_query_card_templates( array $blocks, int $max_characters, array &$templates ): void {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$name = (string) ( $block['blockName'] ?? '' );
			if ( 'generateblocks/query' === $name ) {
				continue;
			}
			if ( 'generateblocks/loop-item' === $name ) {
				$found_summary = false;
				$found_action  = false;
				self::collect_card_template_roles( (array) ( $block['innerBlocks'] ?? array() ), $max_characters, $found_summary, $found_action );
				$templates[] = array( 'summary' => $found_summary, 'action' => $found_action );
				continue;
			}
			self::collect_query_card_templates( (array) ( $block['innerBlocks'] ?? array() ), $max_characters, $templates );
		}
	}

	private static function collect_card_template_roles( array $blocks, int $max_characters, bool &$found_summary, bool &$found_action ): void {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$name  = (string) ( $block['blockName'] ?? '' );
			if ( in_array( $name, array( 'generateblocks/query', 'generateblocks/loop-item' ), true ) ) {
				continue;
			}
			$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
			$html  = (string) ( $block['innerHTML'] ?? '' );
			if ( self::is_role( $name, $attrs, 'data-devenia-card-summary', 'explicit' ) ) {
				$found_summary = $found_summary || (
					false !== strpos( $html, '{{post_excerpt}}' )
					&& $max_characters === self::summary_max_characters( $attrs )
				);
			}
			if ( self::is_role( $name, $attrs, 'data-devenia-card-action', 'plugin-details' ) ) {
				$action_match = array();
				preg_match( '/<a\b[^>]*>(.*?)<\/a>/isu', $html, $action_match );
				$action_text = (string) ( $action_match[1] ?? '' );
				$found_action = $found_action || (
					'{{post_permalink}}' === (string) ( $attrs['htmlAttributes']['href'] ?? '' )
					&& false !== strpos( (string) ( $attrs['htmlAttributes']['aria-label'] ?? '' ), '{{post_title}}' )
					&& self::is_plain_action_text( $action_text )
				);
			}
			self::collect_card_template_roles( (array) ( $block['innerBlocks'] ?? array() ), $max_characters, $found_summary, $found_action );
		}
	}

	/** @return array<int,string> */
	private static function dynamic_tags( string $html ): array {
		preg_match_all( '/\{\{[^{}]+\}\}/u', $html, $matches );
		return array_values( $matches[0] ?? array() );
	}

	private static function is_plain_action_text( string $text ): bool {
		$text = trim( $text );
		return '' !== $text && $text === trim( wp_strip_all_tags( $text ) ) && ! preg_match( '/[\r\n]/', $text );
	}

	private static function text_length( string $text ): int {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $text, 'UTF-8' );
		}
		$matched = preg_match_all( '/./us', $text, $characters );
		return false !== $matched ? $matched : strlen( $text );
	}
}
