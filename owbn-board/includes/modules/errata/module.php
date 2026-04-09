<?php
/**
 * Errata — read-only feed of recent bylaw_clause changes via owc_bylaws_*.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/models.php';
require_once __DIR__ . '/tiles.php';

owbn_board_register_module( [
	'id'          => 'errata',
	'label'       => __( 'Errata', 'owbn-board' ),
	'description' => __( 'Recent bylaw changes feed (reads bylaw-clause-manager)', 'owbn-board' ),
	'version'     => '1.0.0',
	'default'     => false,
	'depends_on'  => [],
	'schema'      => null,
	'loader'      => 'owbn_board_errata_init',
] );

function owbn_board_errata_init() {
	add_action( 'owbn_board_register_tiles', 'owbn_board_errata_register_tile' );
	add_action( 'wp_ajax_owbn_board_errata_save_window', 'owbn_board_errata_ajax_save_window' );
}

function owbn_board_errata_ajax_save_window() {
	if ( ! check_ajax_referer( 'owbn_board', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
	}
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( [ 'message' => 'Not logged in' ], 401 );
	}

	$days = isset( $_POST['days'] ) ? absint( $_POST['days'] ) : 30;
	if ( ! in_array( $days, [ 7, 30, 90 ], true ) ) {
		$days = 30;
	}

	update_user_meta( $user_id, 'owbn_board_errata_window', $days );
	wp_send_json_success( [ 'days' => $days ] );
}
