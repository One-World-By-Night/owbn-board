<?php
/**
 * Resources module — articles (CPT) + curated links.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/cpt.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/models.php';
require_once __DIR__ . '/tiles.php';
if ( is_admin() ) {
	require_once __DIR__ . '/admin.php';
}

owbn_board_register_module( [
	'id'          => 'resources',
	'label'       => __( 'Resources', 'owbn-board' ),
	'description' => __( 'Reference library — articles and curated links for players and staff', 'owbn-board' ),
	'version'     => '1.0.0',
	'default'     => false,
	'depends_on'  => [],
	'schema'      => 'owbn_board_resources_install_schema',
	'loader'      => 'owbn_board_resources_init',
] );

function owbn_board_resources_init() {
	add_action( 'init', 'owbn_board_resources_register_cpt' );
	add_action( 'owbn_board_register_tiles', 'owbn_board_resources_register_tile' );
	if ( is_admin() ) {
		add_action( 'admin_menu', 'owbn_board_resources_register_admin', 25 );
	}
}
