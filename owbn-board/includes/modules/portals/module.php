<?php
/**
 * Portals — launcher tiles for Archivist, Territory Manager, Exec Votes.
 * Counts and recent items come from owc_* cross-site wrappers.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/models.php';
require_once __DIR__ . '/tiles.php';

owbn_board_register_module( [
	'id'          => 'portals',
	'label'       => __( 'Portals', 'owbn-board' ),
	'description' => __( 'Quick-access launcher tiles for Archivist, Territory Manager, and Exec Vote Actions', 'owbn-board' ),
	'version'     => '1.0.0',
	'default'     => false,
	'depends_on'  => [],
	'schema'      => null,
	'loader'      => 'owbn_board_portals_init',
] );

function owbn_board_portals_init() {
	add_action( 'owbn_board_register_tiles', 'owbn_board_portals_register_tiles' );
}
