<?php
/**
 * Handoff module — persistent staff diary across role transitions.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/models.php';
require_once __DIR__ . '/tiles.php';
if ( is_admin() ) {
	require_once __DIR__ . '/admin.php';
}

owbn_board_register_module( [
	'id'          => 'handoff',
	'label'       => __( 'Handoff', 'owbn-board' ),
	'description' => __( 'Persistent staff diary scoped by role group. What you want your successor to know.', 'owbn-board' ),
	'version'     => '1.0.0',
	'default'     => false,
	'depends_on'  => [],
	'schema'      => 'owbn_board_handoff_install_schema',
	'loader'      => 'owbn_board_handoff_init',
] );

function owbn_board_handoff_init() {
	add_action( 'owbn_board_register_tiles', 'owbn_board_handoff_register_tile' );
	if ( is_admin() ) {
		add_action( 'admin_menu', 'owbn_board_handoff_register_admin', 40 );
	}
}
