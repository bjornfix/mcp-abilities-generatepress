<?php
/** Exercise the public element response selection without a running WordPress site. */
$source = file_get_contents( dirname( __DIR__ ) . '/mcp-abilities-generatepress.php' );
$start = strpos( $source, "\n\tmcp_abilities_generatepress_register_ability(\n\t\t'generatepress/get-element'," );
$end = strpos( $source, "\n\t);", $start );
if ( false === $start || false === $end ) { throw new RuntimeException( 'Element registration missing.' ); }
function mcp_abilities_generatepress_register_ability( $name, $args ) { $GLOBALS['ability'] = $args; }
function post_type_exists( $type ) { return true; }
function get_post( $id ) { return (object) array( 'ID' => $id, 'post_type' => 'gp_elements', 'post_content' => 'native block content', 'post_title' => 'Fixture', 'post_status' => 'draft', 'post_name' => 'fixture' ); }
function get_post_meta( $id, $key, $single ) { return '_generate_element_content' === $key ? 'native hook content' : 'hook'; }
function mcp_abilities_generatepress_default_element_meta_keys() { return array( '_generate_element_type', '_generate_element_content' ); }
eval( substr( $source, $start, $end + 5 - $start ) );
$callback = $ability['execute_callback'];
foreach ( array( false, true ) as $include_meta ) {
 $result = $callback( array( 'id' => 1, 'include_content' => false, 'include_meta' => $include_meta ) );
 if ( '' !== $result['content'] || '' !== $result['post_content'] || isset( $result['meta']['_generate_element_content'] ) ) { throw new RuntimeException( 'Excluded content was returned.' ); }
}
$result = $callback( array( 'id' => 1, 'include_content' => true, 'include_meta' => true ) );
if ( 'native hook content' !== $result['content'] || 'native block content' !== $result['post_content'] || 'native hook content' !== $result['meta']['_generate_element_content'] ) { throw new RuntimeException( 'Requested content was lost.' ); }
echo "Element content selection: 3 cases passed.\n";
