<?php
/** Distinguish a native empty declaration from absent or malformed metadata. */
declare( strict_types=1 );
define( 'ABSPATH', __DIR__ . '/' );
function post_type_exists( $type ) { return 'gblocks_styles' === $type; }
function update_meta_cache( $type, $ids ) {}
function get_post_meta( $id, $key, $single ) { return $GLOBALS['fixture_meta'][ $id ][ $key ] ?? ''; }
$GLOBALS['wpdb'] = new class {
 public $posts = 'wp_posts';
 public function prepare( $query, $values ) { return $query; }
 public function get_results( $query ) {
  return array( (object) array( 'ID'=>1, 'post_status'=>'publish' ), (object) array( 'ID'=>2, 'post_status'=>'publish' ), (object) array( 'ID'=>3, 'post_status'=>'draft' ) );
 }
};
$GLOBALS['fixture_meta'] = array(
 1 => array( 'gb_style_selector'=>'.missing', 'gb_style_css'=>'' ),
 2 => array( 'gb_style_selector'=>'.semantic', 'gb_style_data'=>array(), 'gb_style_css'=>'' ),
 3 => array( 'gb_style_selector'=>'.malformed', 'gb_style_data'=>'invalid', 'gb_style_css'=>'' ),
);
require dirname( __DIR__ ) . '/includes/class-generateblocks-global-styles.php';
$index = MCP_Abilities_GeneratePress_GenerateBlocks_Global_Styles::get_validation_index();
if ( null !== $index[0]['styles'] || array() !== $index[1]['styles'] || null !== $index[2]['styles'] ) {
 throw new RuntimeException( 'Missing or malformed native declarations were presented as intentional empty styles.' );
}
echo "Native style validation index: absent, empty and malformed declarations distinguished.\n";
