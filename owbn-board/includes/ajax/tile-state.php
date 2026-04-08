<?php
/**
 * AJAX: save per-user tile state (collapse, pin, snooze, dismiss).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_owbn_board_tile_state', 'owbn_board_ajax_tile_state' );

function owbn_board_ajax_tile_state() {
	if ( ! check_ajax_referer( 'owbn_board', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
	}

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( [ 'message' => 'Not logged in' ], 401 );
	}

	$tile_id      = isset( $_POST['tile_id'] ) ? sanitize_text_field( wp_unslash( $_POST['tile_id'] ) ) : '';
	$state        = isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '';
	$snooze_until = isset( $_POST['snooze_until'] ) ? sanitize_text_field( wp_unslash( $_POST['snooze_until'] ) ) : null;

	if ( ! $tile_id || ! $state ) {
		wp_send_json_error( [ 'message' => 'Missing tile_id or state' ], 400 );
	}

	$ok = owbn_board_set_user_tile_state( $user_id, $tile_id, $state, $snooze_until );
	if ( ! $ok ) {
		wp_send_json_error( [ 'message' => 'Invalid state' ], 400 );
	}

	wp_send_json_success();
}
