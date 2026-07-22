<?php
/**
 * Pure contract for the GenerateBlocks Query Card Projection Module.
 *
 * @package MCP_Abilities_GeneratePress
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['mcp_card_projection_posts'] = array();
$GLOBALS['mcp_card_projection_post'] = (object) array(
	'ID'           => 42,
	'post_type'    => 'page',
	'post_parent'  => 0,
	'post_excerpt' => '',
	'post_content' => 'This full page body must never become a card summary.',
);
$GLOBALS['mcp_card_projection_posts'][42] = $GLOBALS['mcp_card_projection_post'];
$GLOBALS['mcp_card_projection_parsed_blocks'] = array();

function get_the_ID(): int {
	return 42;
}

function get_post( $post_id ) {
	return $GLOBALS['mcp_card_projection_posts'][ (int) $post_id ] ?? null;
}

function wp_kses_post( $value ): string {
	return (string) $value;
}

function wp_strip_all_tags( $value ): string {
	return strip_tags( (string) $value );
}

function esc_html( $value ): string {
	return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function parse_blocks( $content ): array {
	return $GLOBALS['mcp_card_projection_parsed_blocks'][ (string) $content ] ?? array();
}

require_once dirname( __DIR__ ) . '/includes/class-generateblocks-card-projection.php';

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$summary_block = array(
	'blockName' => 'generateblocks/text',
	'attrs'     => array(
		'tagName'        => 'p',
		'htmlAttributes' => array(
			'data-devenia-card-summary' => 'explicit',
			'data-devenia-card-summary-max' => '120',
		),
	),
	'innerHTML' => '<p class="gb-text">{{post_excerpt}}</p>',
);
$summary_block_100 = $summary_block;
$summary_block_100['attrs']['htmlAttributes']['data-devenia-card-summary-max'] = '100';

$action_block = array(
	'blockName' => 'generateblocks/text',
	'attrs'     => array(
		'tagName'        => 'a',
		'htmlAttributes' => array(
			'href'                     => '{{post_permalink}}',
			'aria-label'               => 'View {{post_title}} plugin details',
			'data-devenia-card-action' => 'plugin-details',
		),
	),
	'innerHTML' => '<a class="gb-text" href="{{post_permalink}}" aria-label="View {{post_title}} plugin details" data-devenia-card-action="plugin-details">View plugin →</a>',
);
$card_template_block = array(
	'blockName' => 'generateblocks/loop-item',
	'attrs' => array(),
	'innerBlocks' => array( $summary_block, $action_block ),
);
$card_template_block_100 = $card_template_block;
$card_template_block_100['innerBlocks'][0] = $summary_block_100;

$fragments = MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::translatable_html_fragments(
	array(),
	(string) $action_block['blockName'],
	(array) $action_block['attrs'],
	(string) $action_block['innerHTML']
);
$assert( 1 === count( $fragments ), 'Card action must expose exactly one visible-copy fragment.' );
$assert( 'View plugin →' === (string) ( $fragments[0]['source_html'] ?? '' ), 'Visible CTA fragment was not isolated from the dynamic link.' );

$attr_fragments = MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::structured_attr_fragments(
	array(),
	(string) $action_block['blockName'],
	(array) $action_block['attrs']
);
$assert( 1 === count( $attr_fragments ), 'Card action must expose exactly one accessible-name fragment.' );
$assert( array( 'htmlAttributes', 'aria-label' ) === (array) ( $attr_fragments[0]['attr_path'] ?? array() ), 'ARIA fragment must project back to its native GenerateBlocks attribute.' );
$assert( 'View {{post_title}} plugin details' === (string) ( $attr_fragments[0]['text'] ?? '' ), 'ARIA fragment must retain the dynamic title token.' );
$assert(
	false === (bool) ( MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::validate_localized_fragment_value(
		array( 'success' => true ),
		array( 'role' => 'devenia_generateblocks_card_accessible_name', 'source_html' => 'View {{post_title}} plugin details' ),
		array( 'text' => 'See how the plugin solves the problem' ),
		'See how the plugin solves the problem'
	)['success'] ?? true ),
	'A localized accessible name was allowed to drop its dynamic title token.'
);
$assert(
	true === (bool) ( MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::validate_localized_fragment_value(
		array( 'success' => true ),
		array( 'role' => 'devenia_generateblocks_card_accessible_name', 'source_html' => 'View {{post_title}} plugin details' ),
		array( 'text' => 'See how {{post_title}} solves the problem' ),
		'See how {{post_title}} solves the problem'
	)['success'] ?? false ),
	'A localized accessible name preserving its title token was rejected.'
);
$assert(
	false === (bool) ( MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::validate_localized_fragment_value(
		array( 'success' => true ),
		array( 'role' => 'devenia_generateblocks_card_action', 'source_html' => 'View plugin →' ),
		array( 'html' => '<strong>See the fit</strong>' ),
		'<strong>See the fit</strong>'
	)['success'] ?? true ),
	'Card action markup was accepted as plain localized copy.'
);
$assert(
	false === (bool) ( MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::validate_localized_fragment_value(
		array( 'success' => true ),
		array( 'role' => 'devenia_generateblocks_card_action', 'source_html' => 'View plugin →' ),
		array( 'html' => 'View {{post_title}} →' ),
		'View {{post_title}} →'
	)['success'] ?? true ),
	'A localized visible action was allowed to invent a dynamic token.'
);

$localized = MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::project_translatable_html_fragment(
	(string) $action_block['innerHTML'],
	(string) $fragments[0]['source_html'],
	'See what it solves →'
);
$assert( false !== strpos( $localized, 'See what it solves →' ), 'Localized action copy was not projected.' );
$assert( false !== strpos( $localized, 'href="{{post_permalink}}"' ), 'Permalink token changed during action projection.' );
$assert( false !== strpos( $localized, 'aria-label="View {{post_title}} plugin details"' ), 'Title token changed during visible-copy projection.' );
$escaped_localized = MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::project_workflow_html_fragment(
	(string) $action_block['innerHTML'],
	(string) $fragments[0]['source_html'],
	'A &copy; B',
	$fragments[0]
);
$assert( false !== strpos( $escaped_localized, '>A &amp;copy; B</a>' ), 'Localized action text was not escaped for its text-node context.' );

$assert(
	'' === MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::filter_dynamic_tag_replacement(
		'This full page body must never become a card summary.',
		array( 'tag' => 'post_excerpt', 'block' => $summary_block, 'options' => array() )
	),
	'GenerateBlocks runtime Adapter did not suppress the generated full-content fallback.'
);
$GLOBALS['mcp_card_projection_post']->post_excerpt = 'One clear customer outcome.';
$assert(
	'One clear customer outcome.' === MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::filter_dynamic_tag_replacement(
		'Generated fallback.',
		array( 'tag' => 'post_excerpt', 'block' => $summary_block, 'options' => array() )
	),
	'GenerateBlocks runtime Adapter did not return the explicit card summary.'
);
$GLOBALS['mcp_card_projection_post']->post_excerpt = str_repeat( 'x', 121 );
$assert( '' === MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::filter_dynamic_tag_replacement( 'Generated fallback.', array( 'tag' => 'post_excerpt', 'block' => $summary_block, 'options' => array() ) ), 'Canonical runtime accepted an overlong card summary.' );
$GLOBALS['mcp_card_projection_post']->post_excerpt = '<strong>Markup summary</strong>';
$assert( '' === MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::filter_dynamic_tag_replacement( 'Generated fallback.', array( 'tag' => 'post_excerpt', 'block' => $summary_block, 'options' => array() ) ), 'Canonical runtime accepted markup in a card summary.' );

$inventory = MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::validate_inventory(
	array(
		array( 'id' => 1, 'excerpt' => 'Short, useful outcome.' ),
		array( 'id' => 2, 'excerpt' => '' ),
		array( 'id' => 3, 'excerpt' => str_repeat( 'x', 121 ) ),
	),
	array( 1, 2, 3, 4 ),
	120
);
$assert( array( 4 ) === (array) ( $inventory['missing_ids'] ?? array() ), 'Direct-child inventory drift was not detected.' );
$assert( array( 2 ) === (array) ( $inventory['empty_excerpt_ids'] ?? array() ), 'Missing explicit summaries were not detected.' );
$assert( array( 3 ) === (array) ( $inventory['long_excerpt_ids'] ?? array() ), 'Overlong summaries were not detected.' );
$assert( false === (bool) ( $inventory['valid'] ?? true ), 'Invalid card inventory was accepted.' );
$formatted_inventory = MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::validate_inventory(
	array( array( 'id' => 1, 'excerpt' => "<strong>Useful</strong>\nsecond" ) ),
	array( 1 ),
	120
);
$assert( array( 1 ) === (array) ( $formatted_inventory['invalid_excerpt_ids'] ?? array() ), 'Formatted or multiline card summary was accepted.' );

$contract = MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::find_card_inventory_contract(
	array(
		array(
			'blockName' => 'generateblocks/query',
			'attrs'     => array(
				'query'          => array( 'post_type' => array( 'page' ), 'posts_per_page' => -1, 'post_parent__in' => array( 'current' ) ),
				'htmlAttributes' => array(
					'data-devenia-card-inventory'   => 'plugin-pages',
					'data-devenia-card-summary-max' => '120',
				),
			),
			'innerBlocks' => array( $card_template_block ),
		),
	)
);
$assert( array( 'max_characters' => 120, 'post_types' => array( 'page' ), 'valid' => true, 'issues' => array() ) === $contract, 'Complete current-parent Query card contract was not discovered.' );
$invalid_contract = MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::find_card_inventory_contract(
	array(
		array(
			'blockName' => 'generateblocks/query',
			'attrs' => array(
				'query' => array( 'post_type' => array( 'page' ), 'posts_per_page' => -1, 'post_parent__in' => array( 'current' ) ),
				'htmlAttributes' => array( 'data-devenia-card-inventory' => 'plugin-pages', 'data-devenia-card-summary-max' => '120' ),
			),
			'innerBlocks' => array(),
		),
	)
);
$assert( false === (bool) ( $invalid_contract['valid'] ?? true ) && 2 === count( (array) ( $invalid_contract['issues'] ?? array() ) ), 'Query declaration without required summary/action roles was accepted.' );
$malformed_parent_contract = MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::find_card_inventory_contract(
	array(
		array(
			'blockName' => 'generateblocks/query',
			'attrs' => array(
				'query' => array( 'post_type' => array( 'product' ), 'posts_per_page' => -1, 'post_parent__in' => array() ),
				'htmlAttributes' => array( 'data-devenia-card-inventory' => 'plugin-pages', 'data-devenia-card-summary-max' => '120' ),
			),
			'innerBlocks' => array( $card_template_block ),
		),
	)
);
$assert(
	is_array( $malformed_parent_contract )
	&& false === (bool) ( $malformed_parent_contract['valid'] ?? true )
	&& in_array( 'current_parent_query_required', (array) ( $malformed_parent_contract['issues'] ?? array() ), true )
	&& array( 'product' ) === (array) ( $malformed_parent_contract['post_types'] ?? array() ),
	'A declared inventory with a broken current-parent query failed open.'
);
$multiple_contracts = MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::dynamic_inventory_contracts(
	array(),
	array(
		array(
			'blockName' => 'generateblocks/query',
			'attrs' => array( 'query' => array( 'post_type' => array( 'page' ), 'posts_per_page' => -1, 'post_parent__in' => array( 'current' ) ), 'htmlAttributes' => array( 'data-devenia-card-inventory' => 'plugin-pages', 'data-devenia-card-summary-max' => '120' ) ),
			'innerBlocks' => array( $card_template_block ),
		),
		array(
			'blockName' => 'generateblocks/query',
			'attrs' => array( 'query' => array( 'post_type' => array( 'product' ), 'posts_per_page' => -1, 'post_parent__in' => array( 'current' ) ), 'htmlAttributes' => array( 'data-devenia-card-inventory' => 'plugin-pages', 'data-devenia-card-summary-max' => '100' ) ),
			'innerBlocks' => array( $card_template_block_100 ),
		),
	)
);
$assert( 2 === count( $multiple_contracts ), 'Only the first declared Query inventory was exposed to Workflow.' );
$bounded_query_contract = MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::find_card_inventory_contract(
	array(
		array(
			'blockName' => 'generateblocks/query',
			'attrs' => array( 'query' => array( 'post_type' => array( 'page' ), 'posts_per_page' => 100, 'post_parent__in' => array( 'current' ) ), 'htmlAttributes' => array( 'data-devenia-card-inventory' => 'plugin-pages', 'data-devenia-card-summary-max' => '120' ) ),
			'innerBlocks' => array( $card_template_block ),
		),
	)
);
$assert( false === (bool) ( $bounded_query_contract['valid'] ?? true ) && in_array( 'complete_inventory_query_required', (array) ( $bounded_query_contract['issues'] ?? array() ), true ), 'A result-limited inventory Query was accepted as complete.' );

$parent_blocks = array(
	array(
		'blockName' => 'generateblocks/query',
		'attrs' => array( 'query' => array( 'post_type' => array( 'page' ), 'posts_per_page' => -1, 'post_parent__in' => array( 'current' ) ), 'htmlAttributes' => array( 'data-devenia-card-inventory' => 'plugin-pages', 'data-devenia-card-summary-max' => '120' ) ),
		'innerBlocks' => array( $card_template_block ),
	),
	array(
		'blockName' => 'generateblocks/query',
		'attrs' => array( 'query' => array( 'post_type' => array( 'product' ), 'posts_per_page' => -1, 'post_parent__in' => array( 'current' ) ), 'htmlAttributes' => array( 'data-devenia-card-inventory' => 'plugin-pages', 'data-devenia-card-summary-max' => '100' ) ),
		'innerBlocks' => array( $card_template_block_100 ),
	),
);
$GLOBALS['mcp_card_projection_posts'][99] = (object) array( 'ID' => 99, 'post_type' => 'page', 'post_parent' => 0, 'post_content' => 'parent-contracts' );
$GLOBALS['mcp_card_projection_posts'][101] = (object) array( 'ID' => 101, 'post_type' => 'product', 'post_parent' => 99, 'post_excerpt' => str_repeat( 'x', 110 ) );
$GLOBALS['mcp_card_projection_parsed_blocks']['parent-contracts'] = $parent_blocks;
$product_policy = MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::validate_translation_artifact(
	array( 'success' => true ),
	$GLOBALS['mcp_card_projection_posts'][101],
	array( 'excerpt' => str_repeat( 'y', 110 ) ),
	array()
);
$assert( false === (bool) ( $product_policy['success'] ?? true ), 'Product child summary was validated against the unrelated page inventory bound.' );
$assert(
	false === (bool) ( MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::validate_card_summary_contract( 'Useful source.', '', 120 )['success'] ?? true ),
	'Empty localized card summary passed the Artifact policy.'
);
$assert(
	false === (bool) ( MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::validate_card_summary_contract( str_repeat( 'x', 121 ), 'Useful target.', 120 )['success'] ?? true ),
	'Overlong source card summary passed the Artifact policy.'
);
$assert(
	true === (bool) ( MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::validate_card_summary_contract( 'Useful source.', 'Nyttig sammendrag.', 120 )['success'] ?? false ),
	'Bounded explicit source and target summaries were rejected.'
);
$assert(
	true === (bool) ( MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::validate_card_summary_contract( str_repeat( '漢', 120 ), str_repeat( '字', 120 ), 120 )['success'] ?? false ),
	'Valid non-Latin summaries were counted as UTF-8 bytes instead of characters.'
);
$assert(
	false === (bool) ( MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::validate_card_summary_contract( str_repeat( '漢', 121 ), '短い要約', 120 )['success'] ?? true ),
	'Overlong non-Latin summary was not rejected by character count.'
);

$foreign_parent_contract = MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::find_card_inventory_contract(
	array(
		array(
			'blockName' => 'generateblocks/query',
			'attrs' => array( 'query' => array( 'post_type' => array( 'page' ), 'posts_per_page' => -1, 'post_parent__in' => array( 'current', 123 ) ), 'htmlAttributes' => array( 'data-devenia-card-inventory' => 'plugin-pages', 'data-devenia-card-summary-max' => '120' ) ),
			'innerBlocks' => array( $card_template_block ),
		),
	)
);
$assert( false === (bool) ( $foreign_parent_contract['valid'] ?? true ) && in_array( 'current_parent_query_required', (array) ( $foreign_parent_contract['issues'] ?? array() ), true ), 'Inventory Query was allowed to include cards from a foreign parent.' );

$nested_action_block = $action_block;
$nested_action_block['innerHTML'] = '<a class="gb-text" href="{{post_permalink}}" aria-label="View {{post_title}} plugin details" data-devenia-card-action="plugin-details"><span>View plugin</span> →</a>';
$nested_action_template = $card_template_block;
$nested_action_template['innerBlocks'][1] = $nested_action_block;
$nested_action_contract = MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::find_card_inventory_contract(
	array(
		array(
			'blockName' => 'generateblocks/query',
			'attrs' => array( 'query' => array( 'post_type' => array( 'page' ), 'posts_per_page' => -1, 'post_parent__in' => array( 'current' ) ), 'htmlAttributes' => array( 'data-devenia-card-inventory' => 'plugin-pages', 'data-devenia-card-summary-max' => '120' ) ),
			'innerBlocks' => array( $nested_action_template ),
		),
	)
);
$assert( false === (bool) ( $nested_action_contract['valid'] ?? true ) && in_array( 'localized_action_role_missing', (array) ( $nested_action_contract['issues'] ?? array() ), true ), 'Nested source action markup was accepted for destructive plain-text projection.' );

$nested_unrelated_query_contract = MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::find_card_inventory_contract(
	array(
		array(
			'blockName' => 'generateblocks/query',
			'attrs' => array( 'query' => array( 'post_type' => array( 'page' ), 'posts_per_page' => -1, 'post_parent__in' => array( 'current' ) ), 'htmlAttributes' => array( 'data-devenia-card-inventory' => 'plugin-pages', 'data-devenia-card-summary-max' => '120' ) ),
			'innerBlocks' => array(
				array(
					'blockName' => 'generateblocks/query',
					'attrs' => array( 'query' => array( 'post_type' => array( 'page' ), 'posts_per_page' => -1, 'post_parent__in' => array( 'current' ) ) ),
					'innerBlocks' => array( $card_template_block ),
				),
			),
		),
	)
);
$assert( false === (bool) ( $nested_unrelated_query_contract['valid'] ?? true ), 'Outer inventory borrowed card roles from a nested unrelated Query.' );

$summary_only_template = $card_template_block;
$summary_only_template['innerBlocks'] = array( $summary_block );
$mixed_template_contract = MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::find_card_inventory_contract(
	array(
		array(
			'blockName' => 'generateblocks/query',
			'attrs' => array( 'query' => array( 'post_type' => array( 'page' ), 'posts_per_page' => -1, 'post_parent__in' => array( 'current' ) ), 'htmlAttributes' => array( 'data-devenia-card-inventory' => 'plugin-pages', 'data-devenia-card-summary-max' => '120' ) ),
			'innerBlocks' => array( $card_template_block, $summary_only_template ),
		),
	)
);
$assert( false === (bool) ( $mixed_template_contract['valid'] ?? true ) && in_array( 'coherent_card_template_required', (array) ( $mixed_template_contract['issues'] ?? array() ), true ), 'One complete Loop Item hid an incomplete sibling card template.' );

$wired_inventory = MCP_Abilities_GeneratePress_GenerateBlocks_Card_Projection::validate_dynamic_inventory(
	array( 'success' => true ),
	array( array( 'id' => 1, 'excerpt' => 'Useful.' ) ),
	array( 1, 2 ),
	120
);
$assert( false === (bool) ( $wired_inventory['success'] ?? true ) && 'dynamic_card_inventory_invalid' === (string) ( $wired_inventory['code'] ?? '' ), 'Production inventory Adapter accepted a missing direct child.' );

echo "GenerateBlocks card projection contract passed.\n";
