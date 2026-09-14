<?php
/** Exercise attachment permission checks through the public regeneration callback. */
$source = file_get_contents( dirname( __DIR__ ) . '/mcp-abilities-generatepress.php' );
$start = strpos( $source, "\n\tmcp_abilities_generatepress_register_ability(\n\t\t'generatepress/regenerate-featured-image-sizes'," );
$end = strpos( $source, "\n\t);", $start );
if ( false === $start || false === $end ) { throw new RuntimeException( 'Regeneration registration missing.' ); }
function mcp_abilities_generatepress_register_ability( $name, $args ) { $GLOBALS['ability'] = $args; }
function current_user_can( $cap, ...$args ) { return 'upload_files' === $cap || ( 'edit_post' === $cap && $GLOBALS['may_edit'] && 99 === $args[0] ); }
function sanitize_key( $key ) { return $key; }
function get_post_thumbnail_id( $id ) { return 99; }
function get_attached_file( $id ) { ++$GLOBALS['file_reads']; return __FILE__; }
function wp_generate_attachment_metadata( $id, $file ) { ++$GLOBALS['writes']; return array( 'width' => 100 ); }
function wp_update_attachment_metadata( $id, $data ) { ++$GLOBALS['writes']; return true; }
function mcp_abilities_generatepress_audit_attachment_image_sizes( $id, $sizes ) { return array(); }
eval( substr( $source, $start, $end + 5 - $start ) );
$callback = $ability['execute_callback'];
foreach ( array( false, true ) as $may_edit ) {
 $file_reads = 0; $writes = 0;
 if ( ! $ability['permission_callback']() ) { throw new RuntimeException( 'Upload permission fixture is invalid.' ); }
 $result = $callback( array( 'confirm' => true, 'post_ids' => array( 42 ), 'sizes' => array() ) );
 if ( ! $may_edit && ( $writes || $file_reads || empty( $result['failed'][42] ) || $result['processed'] ) ) { throw new RuntimeException( 'An attachment was regenerated without edit permission.' ); }
 if ( $may_edit && ( 2 !== $writes || 1 !== count( $result['processed'] ) ) ) { throw new RuntimeException( 'An authorised attachment was not regenerated.' ); }
}
echo "Image regeneration permissions: denied and allowed cases passed.\n";
