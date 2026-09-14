<?php
/** Distinguish a native empty declaration from absent or malformed metadata. */
declare( strict_types=1 );
define( 'ABSPATH', __DIR__ . '/' );
function post_type_exists( $type ) { return 'gblocks_styles' === $type; }
function update_meta_cache( $type, $ids ) {}
function get_post_meta( $id, $key, $single ) { return $GLOBALS['fixture_meta'][ $id ][ $key ] ?? ''; }
$GLOBALS['query_count'] = 0;
function get_posts( $args ) {
 ++$GLOBALS['query_count'];
 if ( 'ids' !== $args['fields'] || 'gblocks_styles' !== $args['post_type'] ) {
  throw new RuntimeException( 'Validation loaded full posts or another post type.' );
 }
 return array( 'publish' => array( 1, 2 ), 'draft' => array( 3 ), 'private' => array( 4 ) )[ $args['post_status'] ];
}
$GLOBALS['fixture_meta'] = array(
 1 => array( 'gb_style_selector'=>'.missing', 'gb_style_css'=>'' ),
 2 => array( 'gb_style_selector'=>'.semantic', 'gb_style_data'=>array(), 'gb_style_css'=>'' ),
 3 => array( 'gb_style_selector'=>'.malformed', 'gb_style_data'=>'invalid', 'gb_style_css'=>'' ),
 4 => array( 'gb_style_selector'=>'.private', 'gb_style_data'=>array(), 'gb_style_css'=>'' ),
);
require dirname( __DIR__ ) . '/includes/class-generateblocks-global-styles.php';
$index = MCP_Abilities_GeneratePress_GenerateBlocks_Global_Styles::get_validation_index();
if ( null !== $index[0]['styles'] || array() !== $index[1]['styles'] || null !== $index[2]['styles'] ) {
 throw new RuntimeException( 'Missing or malformed native declarations were presented as intentional empty styles.' );
}
echo "Native style validation index: absent, empty and malformed declarations distinguished.\n";
if ( array( 'publish', 'publish', 'draft', 'private' ) !== array_column( $index, 'status' ) ) { throw new RuntimeException( 'Native statuses were lost.' ); }
MCP_Abilities_GeneratePress_GenerateBlocks_Global_Styles::get_validation_index();
if ( 3 !== $GLOBALS['query_count'] ) { throw new RuntimeException( 'Per-request index was reloaded.' ); }
