<?php
/**
 * Ballot module — AJAX handler for collecting and submitting votes.
 *
 * The actual vote casting is delegated to wp-voting-plugin's existing
 * wpvp_cast_ballot endpoint. This file only provides a helper for our
 * client-side JS to check eligibility and vote state before submission.
 */

defined( 'ABSPATH' ) || exit;

/**
 * AJAX: check if user is eligible for a list of votes.
 * Returns a map of vote_id => {eligible, voted, roles}
 *
 * Not strictly required — JS can also call wpvp_cast_ballot directly for each
 * vote, but this gives us one round-trip to populate state before submission.
 */
function owbn_board_ballot_ajax_status() {
	if ( ! check_ajax_referer( 'owbn_board', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
	}

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( [ 'message' => 'Not logged in' ], 401 );
	}

	$vote_ids = isset( $_POST['vote_ids'] ) ? (array) wp_unslash( $_POST['vote_ids'] ) : [];
	$vote_ids = array_map( 'absint', $vote_ids );
	$vote_ids = array_filter( $vote_ids );

	$status = [];
	foreach ( $vote_ids as $vote_id ) {
		$vote = owbn_board_ballot_get_vote( $vote_id );
		if ( ! $vote ) {
			$status[ $vote_id ] = [ 'eligible' => false, 'voted' => false, 'error' => 'not_found' ];
			continue;
		}
		$status[ $vote_id ] = [
			'eligible' => owbn_board_ballot_user_is_eligible( $vote, $user_id ),
			'voted'    => owbn_board_ballot_user_has_voted( $vote_id, $user_id ),
			'stage'    => (string) ( $vote['voting_stage'] ?? '' ),
		];
	}

	wp_send_json_success( [ 'votes' => $status ] );
}

/**
 * AJAX: cast a ballot via owc_wpvp_cast_ballot (local or remote).
 * Replaces the previous direct call to wp-voting-plugin's wpvp_cast_ballot
 * so the Submit All button works from any OWBN site, not just council.
 */
function owbn_board_ballot_ajax_cast() {
	if ( ! check_ajax_referer( 'owbn_board', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
	}

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( [ 'message' => 'Not logged in' ], 401 );
	}

	$vote_id     = isset( $_POST['vote_id'] ) ? absint( $_POST['vote_id'] ) : 0;
	$ballot_data = isset( $_POST['ballot_data'] ) ? wp_unslash( $_POST['ballot_data'] ) : '';
	$voting_role = isset( $_POST['voting_role'] ) ? sanitize_text_field( wp_unslash( $_POST['voting_role'] ) ) : '';

	if ( ! $vote_id || '' === $ballot_data ) {
		wp_send_json_error( [ 'message' => 'vote_id and ballot_data required' ], 400 );
	}

	if ( ! function_exists( 'owc_wpvp_cast_ballot' ) ) {
		wp_send_json_error( [ 'message' => 'Voting API unavailable. Upgrade owbn-core.' ], 503 );
	}

	$result = owc_wpvp_cast_ballot( $vote_id, $user_id, $ballot_data, $voting_role );

	if ( is_wp_error( $result ) ) {
		$code     = $result->get_error_code();
		$data     = [ 'message' => $result->get_error_message() ];
		$err_data = $result->get_error_data();
		if ( is_array( $err_data ) ) {
			$data = array_merge( $err_data, $data );
		}
		if ( 'requires_role_selection' === $code ) {
			$data['requires_role_selection'] = true;
		}
		wp_send_json_error( $data, 400 );
	}

	wp_send_json_success( $result );
}
