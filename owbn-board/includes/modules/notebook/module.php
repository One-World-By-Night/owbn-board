<?php
/**
 * Notebook module — shared group notebook scoped by accessSchema role path.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/tiles.php';

owbn_board_register_module( [
	'id'          => 'notebook',
	'label'       => __( 'Shared Notebook', 'owbn-board' ),
	'description' => __( 'Collaborative TinyMCE notebook scoped to accessSchema role path', 'owbn-board' ),
	'version'     => '1.0.0',
	'default'     => true,
	'depends_on'  => [],
	'schema'      => null,
	'loader'      => 'owbn_board_notebook_init',
] );

/**
 * Module loader — runs when the module is enabled for the current site.
 * Registers the tile via the standard owbn_board_register_tiles action.
 */
function owbn_board_notebook_init() {
	add_action( 'owbn_board_register_tiles', 'owbn_board_notebook_register_tile' );
}
