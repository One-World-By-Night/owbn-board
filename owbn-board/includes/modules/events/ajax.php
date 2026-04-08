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

	$event = get_post( $event_id );
	if ( ! $event || 'owbn_event' !== $event->post_type || 'publish' !== $event->post_status ) {
		wp_send_json_error( [ 'message' => 'Event not found' ], 404 );
	}

	if ( 'clear' === $status ) {
		owbn_board_events_rsvp_remove( $event_id, $user_id );
	} else {
		owbn_board_events_rsvp_set( $event_id, $user_id, $status );
	}

	wp_send_json_success( [
		'status' => 'clear' === $status ? null : $status,
		'counts' => owbn_board_events_rsvp_counts( $event_id ),
	] );
}
