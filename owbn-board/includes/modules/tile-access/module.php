<?php
/**
 * Tile Access — admin editor for per-tile read/write/share overrides.
 * Enforcement lives in core/permissions.php so saved overrides still apply
 * when this module is disabled.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/helpers.php';
if ( is_admin() ) {
	require_once __DIR__ . '/admin.php';
	require_once __DIR__ . '/ajax.php';
}

owbn_board_register_module( [
	'id'          => 'tile-access',
	'label'       => __( 'Tile Access', 'owbn-board' ),
	'description' => __( 'Admin editor for per-tile read/write role overrides and share-level content scoping.', 'owbn-board' ),
	'version'     => '1.0.0',
	'default'     => true,
	'depends_on'  => [],
	'schema'      => null,
	'loader'      => 'owbn_board_tile_access_init',
] );

function owbn_board_tile_access_init() {
	if ( is_admin() ) {
		add_action( 'admin_menu', 'owbn_board_tile_access_register_admin', 30 );
		add_action( 'wp_ajax_owbn_board_tile_access_save', 'owbn_board_tile_access_ajax_save' );
	}
}
