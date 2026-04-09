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
			'stage'    => $vote->voting_stage,
		];
	}

	wp_send_json_success( [ 'votes' => $status ] );
}
