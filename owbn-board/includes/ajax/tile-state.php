<?php
/**
 * AJAX: save per-user tile state (collapse, pin, snooze, dismiss).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_owbn_board_tile_state', 'owbn_board_ajax_tile_state' );
add_action( 'wp_ajax_owbn_board_tile_size', 'owbn_board_ajax_tile_size' );
add_action( 'wp_ajax_owbn_board_tile_order', 'owbn_board_ajax_tile_order' );
add_action( 'wp_ajax_owbn_board_tile_refresh', 'owbn_board_ajax_tile_refresh' );

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

function owbn_board_ajax_tile_size() {
	if ( ! check_ajax_referer( 'owbn_board', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
	}

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( [ 'message' => 'Not logged in' ], 401 );
	}

	$tile_id = isset( $_POST['tile_id'] ) ? sanitize_text_field( wp_unslash( $_POST['tile_id'] ) ) : '';
	$size    = isset( $_POST['size'] ) ? sanitize_text_field( wp_unslash( $_POST['size'] ) ) : '';

	if ( ! $tile_id ) {
		wp_send_json_error( [ 'message' => 'Missing tile_id' ], 400 );
	}

	if ( '__reset__' === $size ) {
		owbn_board_clear_user_tile_size( $user_id, $tile_id );
		wp_send_json_success( [ 'size' => null ] );
	}

	if ( ! in_array( $size, owbn_board_allowed_sizes(), true ) ) {
		wp_send_json_error( [ 'message' => 'Invalid size' ], 400 );
	}

	$ok = owbn_board_set_user_tile_size( $user_id, $tile_id, $size );
	if ( ! $ok ) {
		wp_send_json_error( [ 'message' => 'Could not save size' ], 500 );
	}

	wp_send_json_success( [ 'size' => $size ] );
}

function owbn_board_ajax_tile_order() {
	if ( ! check_ajax_referer( 'owbn_board', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
	}

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( [ 'message' => 'Not logged in' ], 401 );
	}

	$tile_ids = isset( $_POST['tile_ids'] ) ? (array) wp_unslash( $_POST['tile_ids'] ) : [];
	$ok       = owbn_board_set_user_tile_order( $user_id, $tile_ids );
	if ( ! $ok ) {
		wp_send_json_error( [ 'message' => 'Could not save order' ], 500 );
	}

	wp_send_json_success( [ 'count' => count( $tile_ids ) ] );
}

// AJAX: re-render a tile body for the polling subsystem.
function owbn_board_ajax_tile_refresh() {
	if ( ! check_ajax_referer( 'owbn_board', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
	}

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( [ 'message' => 'Not logged in' ], 401 );
	}

	$tile_id = isset( $_POST['tile_id'] ) ? sanitize_text_field( wp_unslash( $_POST['tile_id'] ) ) : '';
	if ( '' === $tile_id ) {
		wp_send_json_error( [ 'message' => 'Missing tile_id' ], 400 );
	}

	$tile = owbn_board_get_tile( $tile_id );
	if ( ! $tile ) {
		wp_send_json_error( [ 'message' => 'Tile not found' ], 404 );
	}

	if ( ! owbn_board_user_can_read_tile( $tile, $user_id ) ) {
		wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
	}

	$can_write = owbn_board_user_can_write_tile( $tile, $user_id );
	$html      = owbn_board_render_tile_body( $tile, $user_id, $can_write );

	wp_send_json_success( [ 'html' => $html ] );
}
