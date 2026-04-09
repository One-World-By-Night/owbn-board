<?php
/**
 * Events module — RSVP AJAX handlers.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_events_ajax_rsvp() {
	if ( ! check_ajax_referer( 'owbn_board', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
	}

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( [ 'message' => 'Not logged in' ], 401 );
	}

	$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
	$status   = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
	if ( ! $event_id || ! in_array( $status, [ 'interested', 'going', 'clear' ], true ) ) {
		wp_send_json_error( [ 'message' => 'Bad request' ], 400 );
	}

	if ( ! function_exists( 'owc_events_rsvp_set' ) ) {
		wp_send_json_error( [ 'message' => 'Events API unavailable' ], 503 );
	}

	$result = owc_events_rsvp_set( $event_id, $user_id, $status );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( [ 'message' => $result->get_error_message() ], 400 );
	}

	wp_send_json_success( $result );
}
