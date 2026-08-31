<?php
/**
 * Pure contract for the GenerateBlocks Content Save Guard Module.
 *
 * @package MCP_Abilities_GeneratePress
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

final class WP_Error {
	private string $code;

	public function __construct( string $code ) {
		$this->code = $code;
	}

	public function get_error_code(): string {
		return $this->code;
	}
}

function is_wp_error( $value ): bool {
	return $value instanceof WP_Error;
}

function add_filter( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	$GLOBALS['mcp_guard_filters'][ $hook ] = array( $callback, $priority, $accepted_args );
	return true;
}

function get_block_type( string $name ) {
	return in_array( $name, array( 'generateblocks/element', 'generateblocks/text', 'core/post-featured-image' ), true )
		? new stdClass()
		: null;
}

function parse_blocks( string $content ): array {
	return $GLOBALS['mcp_guard_parsed'][ $content ] ?? array();
}

final class MCP_Abilities_GeneratePress_GenerateBlocks_Global_Styles {
	public static function get_all(): array {
		return $GLOBALS['mcp_guard_global_styles'] ?? array();
	}
}

require_once dirname( __DIR__ ) . '/includes/class-generateblocks-content-save-guard.php';

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

MCP_Abilities_GeneratePress_GenerateBlocks_Content_Save_Guard::register();
$assert( isset( $GLOBALS['mcp_guard_filters']['mcp_content_write_preflight'] ), 'MCP write preflight was not registered.' );
$assert( isset( $GLOBALS['mcp_guard_filters']['rest_pre_insert_page'] ), 'Gutenberg page REST preflight was not registered.' );

$assert( true === MCP_Abilities_GeneratePress_GenerateBlocks_Content_Save_Guard::validate_content( 'Plain page copy.' ), 'Classic page content was blocked.' );

$valid = '<!-- wp:generateblocks/element {"tagName":"div"} --><div></div><!-- /wp:generateblocks/element -->';
$GLOBALS['mcp_guard_parsed'][ $valid ] = array(
	array(
		'blockName'    => 'generateblocks/element',
		'attrs'        => array( 'tagName' => 'div' ),
		'innerBlocks'  => array(),
		'innerHTML'    => '<div></div>',
		'innerContent' => array( '<div></div>' ),
	),
);
$assert( true === MCP_Abilities_GeneratePress_GenerateBlocks_Content_Save_Guard::validate_content( $valid ), 'Valid GenerateBlocks content was blocked.' );

$missing_style = '<!-- wp:generateblocks/element {"tagName":"div","globalClasses":["gbp-section"]} --><div></div><!-- /wp:generateblocks/element -->';
$GLOBALS['mcp_guard_parsed'][ $missing_style ] = array(
	array(
		'blockName'   => 'generateblocks/element',
		'attrs'       => array( 'tagName' => 'div', 'globalClasses' => array( 'gbp-section' ) ),
		'innerBlocks' => array(),
	),
);
$missing_style_result = MCP_Abilities_GeneratePress_GenerateBlocks_Content_Save_Guard::validate_content( $missing_style );
$assert( is_wp_error( $missing_style_result ) && 'generateblocks_global_styles_missing' === $missing_style_result->get_error_code(), 'A missing GenerateBlocks Global Style was allowed to save.' );

$GLOBALS['mcp_guard_global_styles'] = array(
	array(
		'selector' => '.gbp-section',
		'status'   => 'publish',
		'css'      => '.gbp-section{padding:1rem;}',
	)
);
$assert( true === MCP_Abilities_GeneratePress_GenerateBlocks_Content_Save_Guard::validate_content( $missing_style ), 'An existing GenerateBlocks Global Style was reported missing.' );
$assert( true === MCP_Abilities_GeneratePress_GenerateBlocks_Content_Save_Guard::validate_content( $missing_style, true ), 'A published GenerateBlocks Global Style with CSS was rejected.' );

$GLOBALS['mcp_guard_global_styles'][0]['selector'] = '.gbp-section .fixture-child';
$GLOBALS['mcp_guard_global_styles'][0]['css']      = '.gbp-section .fixture-child{padding:1rem;}';
$assert( true === MCP_Abilities_GeneratePress_GenerateBlocks_Content_Save_Guard::validate_content( $missing_style, true ), 'A Global Style class used by a compound native selector was reported missing.' );
$GLOBALS['mcp_guard_global_styles'][0]['selector'] = '.gbp-section';
$GLOBALS['mcp_guard_global_styles'][0]['css']      = '.gbp-section{padding:1rem;}';

$GLOBALS['mcp_guard_global_styles'][0]['status'] = 'draft';
$draft_style_result = MCP_Abilities_GeneratePress_GenerateBlocks_Content_Save_Guard::validate_content( $missing_style, true );
$assert( is_wp_error( $draft_style_result ) && 'generateblocks_global_styles_missing' === $draft_style_result->get_error_code(), 'A draft Global Style was allowed on published page content.' );
$GLOBALS['mcp_guard_global_styles'][0]['status'] = 'publish';
$GLOBALS['mcp_guard_global_styles'][0]['css']    = '';
$empty_css_result = MCP_Abilities_GeneratePress_GenerateBlocks_Content_Save_Guard::validate_content( $missing_style, true );
$assert( is_wp_error( $empty_css_result ) && 'generateblocks_global_styles_missing' === $empty_css_result->get_error_code(), 'A Global Style without generated CSS was allowed to save.' );
$GLOBALS['mcp_guard_global_styles'][0]['css'] = '.gbp-section{padding:1rem;}';

$unknown = '<!-- wp:devenia/missing /-->';
$GLOBALS['mcp_guard_parsed'][ $unknown ] = array( array( 'blockName' => 'devenia/missing', 'innerBlocks' => array() ) );
$unknown_result = MCP_Abilities_GeneratePress_GenerateBlocks_Content_Save_Guard::validate_content( $unknown );
$assert( is_wp_error( $unknown_result ) && 'generateblocks_invalid_editor_content' === $unknown_result->get_error_code(), 'Unregistered blocks were allowed to save.' );

$nested = '<!-- wp:generateblocks/element {"tagName":"a"} --><a><!-- wp:core/post-featured-image /--></a><!-- /wp:generateblocks/element -->';
$GLOBALS['mcp_guard_parsed'][ $nested ] = array(
	array(
		'blockName'   => 'generateblocks/element',
		'attrs'       => array( 'tagName' => 'a' ),
		'innerBlocks' => array( array( 'blockName' => 'core/post-featured-image', 'attrs' => array(), 'innerBlocks' => array() ) ),
	),
);
$nested_result = MCP_Abilities_GeneratePress_GenerateBlocks_Content_Save_Guard::validate_content( $nested );
$assert( is_wp_error( $nested_result ) && 'generateblocks_invalid_editor_content' === $nested_result->get_error_code(), 'Featured image nested in an anchor was allowed to save.' );

$mismatched = '<!-- wp:generateblocks/element --><div><!-- /wp:generateblocks/text -->';
$mismatched_result = MCP_Abilities_GeneratePress_GenerateBlocks_Content_Save_Guard::validate_content( $mismatched );
$assert( is_wp_error( $mismatched_result ) && 'generateblocks_block_comments_invalid' === $mismatched_result->get_error_code(), 'Mismatched block comments were allowed to save.' );

$mcp_result = MCP_Abilities_GeneratePress_GenerateBlocks_Content_Save_Guard::validate_mcp_write(
	true,
	array( 'post_type' => 'page', 'content' => $nested )
);
$assert( is_wp_error( $mcp_result ), 'MCP page write bypassed the shared guard.' );
$GLOBALS['mcp_guard_global_styles'] = array();
$mcp_missing_style_result = MCP_Abilities_GeneratePress_GenerateBlocks_Content_Save_Guard::validate_mcp_write(
	true,
	array( 'post_type' => 'page', 'target_status' => 'publish', 'content' => $missing_style )
);
$assert( is_wp_error( $mcp_missing_style_result ) && 'generateblocks_global_styles_missing' === $mcp_missing_style_result->get_error_code(), 'MCP page write allowed a missing Global Style.' );
$assert(
	true === MCP_Abilities_GeneratePress_GenerateBlocks_Content_Save_Guard::validate_mcp_write(
		true,
		array( 'post_type' => 'post', 'content' => $nested )
	),
	'MCP post write was changed outside the page guard scope.'
);

$prepared = (object) array( 'post_content' => $nested );
$rest_result = MCP_Abilities_GeneratePress_GenerateBlocks_Content_Save_Guard::validate_rest_write( $prepared, null );
$assert( is_wp_error( $rest_result ), 'Gutenberg page REST write bypassed the shared guard.' );

$prepared_missing_style = (object) array( 'post_content' => $missing_style, 'post_status' => 'publish' );
$rest_missing_style_result = MCP_Abilities_GeneratePress_GenerateBlocks_Content_Save_Guard::validate_rest_write( $prepared_missing_style, null );
$assert( is_wp_error( $rest_missing_style_result ) && 'generateblocks_global_styles_missing' === $rest_missing_style_result->get_error_code(), 'Gutenberg REST page write allowed a missing Global Style.' );

echo "GenerateBlocks content save guard checks passed.\n";
