<?php
/**
 * Ballot — unified card-based election ballot. Reads via owc_wpvp_* and
 * casts via owc_wpvp_cast_ballot (local on council, gateway-proxied elsewhere).
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/models.php';
require_once __DIR__ . '/tiles.php';
require_once __DIR__ . '/ajax.php';

owbn_board_register_module( [
	'id'          => 'ballot',
	'label'       => __( 'Ballot', 'owbn-board' ),
	'description' => __( 'Unified card-based ballot for OWBN elections. Reads wp-voting-plugin and owbn-election-bridge data.', 'owbn-board' ),
	'version'     => '1.0.0',
	'default'     => false,
	'depends_on'  => [],
	'schema'      => null,
	'loader'      => 'owbn_board_ballot_init',
] );

function owbn_board_ballot_init() {
	add_action( 'init', 'owbn_board_ballot_register_shortcode' );
	add_action( 'owbn_board_register_tiles', 'owbn_board_ballot_register_tile' );
	add_action( 'wp_ajax_owbn_board_ballot_status', 'owbn_board_ballot_ajax_status' );
	add_action( 'wp_ajax_owbn_board_ballot_cast', 'owbn_board_ballot_ajax_cast' );
}
