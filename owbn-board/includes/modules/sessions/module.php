<?php
/**
 * Sessions module — chronicle session log.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/models.php';
require_once __DIR__ . '/tiles.php';
if ( is_admin() ) {
	require_once __DIR__ . '/admin.php';
}

owbn_board_register_module( [
	'id'          => 'sessions',
	'label'       => __( 'Sessions', 'owbn-board' ),
	'description' => __( 'Chronicle session log with title, summary, notes, and optional player sharing', 'owbn-board' ),
	'version'     => '1.0.0',
	'default'     => false,
	'depends_on'  => [],
	'schema'      => 'owbn_board_sessions_install_schema',
	'loader'      => 'owbn_board_sessions_init',
] );

function owbn_board_sessions_init() {
	add_action( 'owbn_board_register_tiles', 'owbn_board_sessions_register_tile' );
	if ( is_admin() ) {
		add_action( 'admin_menu', 'owbn_board_sessions_register_admin', 30 );
	}
}
