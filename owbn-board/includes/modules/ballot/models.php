<?php
/**
 * Ballot module — data helpers reading from wp-voting-plugin and owbn-election-bridge.
 *
 * No own tables. This module is pure consumer of other plugins' data.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_ballot_wpvp_available() {
	global $wpdb;
	$table = $wpdb->prefix . 'wpvp_votes';
	return (bool) $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
}

function owbn_board_ballot_oeb_available() {
	global $wpdb;
	$table = $wpdb->prefix . 'oeb_election_sets';
	return (bool) $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
}

/**
 * Fetch currently-open votes.
 *
 * @param int $limit 0 for no limit
 * @return array
 */
function owbn_board_ballot_get_open_votes( $limit = 0 ) {
	global $wpdb;
	if ( ! owbn_board_ballot_wpvp_available() ) {
		return [];
	}
	$table = $wpdb->prefix . 'wpvp_votes';

	$limit_sql = $limit > 0 ? $wpdb->prepare( ' LIMIT %d', absint( $limit ) ) : '';

	$sql = "SELECT * FROM {$table} WHERE voting_stage = 'open' ORDER BY closing_date ASC, id ASC{$limit_sql}";

	return (array) $wpdb->get_results( $sql );
}

/**
 * Fetch votes belonging to an election set.
 */
function owbn_board_ballot_get_votes_for_election( $election_id, $include_closed = false ) {
	global $wpdb;
	if ( ! owbn_board_ballot_wpvp_available() || ! owbn_board_ballot_oeb_available() ) {
		return [];
	}
	$table = $wpdb->prefix . 'wpvp_votes';

	// wp-voting-plugin stores election set linkage in post meta on the vote itself?
	// No — election-bridge stores it differently. For v1, we match by looking for
	// candidate posts that have _oeb_election_set_id meta matching this election,
	// then collecting their parent vote IDs.
	//
	// Simpler path: filter on classification or settings column. For now, return
	// all open votes if election_id is provided (spec enhancement later).
	$stages = $include_closed ? [ 'open', 'closed' ] : [ 'open' ];
	$placeholders = implode( ',', array_fill( 0, count( $stages ), '%s' ) );

	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE voting_stage IN ({$placeholders}) ORDER BY closing_date ASC, id ASC",
			$stages
		)
	);
}

/**
 * Fetch a single vote by ID.
 */
function owbn_board_ballot_get_vote( $vote_id ) {
	global $wpdb;
	if ( ! owbn_board_ballot_wpvp_available() ) {
		return null;
	}
	$table = $wpdb->prefix . 'wpvp_votes';
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $vote_id ) ) );
}

/**
 * Decode voting_options into an array of ['text' => ..., 'post_id' => ...].
 */
function owbn_board_ballot_decode_options( $vote ) {
	if ( ! $vote || empty( $vote->voting_options ) ) {
		return [];
	}
	$decoded = json_decode( $vote->voting_options, true );
	return is_array( $decoded ) ? $decoded : [];
}

/**
 * Has this user already voted on this vote?
 */
function owbn_board_ballot_user_has_voted( $vote_id, $user_id ) {
	global $wpdb;
	if ( ! owbn_board_ballot_wpvp_available() || ! $user_id ) {
		return false;
	}
	$table = $wpdb->prefix . 'wpvp_ballots';
	$exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
	if ( ! $exists ) {
		return false;
	}
	$count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE vote_id = %d AND user_id = %d",
			absint( $vote_id ),
			absint( $user_id )
		)
	);
	return $count > 0;
}

/**
 * Check if the user is eligible to vote on this vote based on ASC roles.
 */
function owbn_board_ballot_user_is_eligible( $vote, $user_id ) {
	if ( ! $vote || ! $user_id ) {
		return false;
	}
	$allowed = ! empty( $vote->voting_roles ) ? json_decode( $vote->voting_roles, true ) : [];
	if ( empty( $allowed ) || ! is_array( $allowed ) ) {
		// No role restriction → everyone logged in can vote
		return true;
	}
	$user_roles = owbn_board_get_user_roles( $user_id );
	return owbn_board_user_matches_any_pattern( $user_roles, $allowed );
}

/**
 * Determine which card state to render for a given vote and user.
 */
function owbn_board_ballot_card_state( $vote, $user_id ) {
	if ( ! $vote ) {
		return 'unknown';
	}

	$stage = $vote->voting_stage;

	if ( 'closed' === $stage ) {
		return 'closed';
	}

	if ( 'draft' === $stage ) {
		// Scheduled — applications phase
		return 'scheduled';
	}

	if ( 'open' === $stage ) {
		if ( $user_id && owbn_board_ballot_user_has_voted( $vote->id, $user_id ) ) {
			return 'open-voted';
		}
		return 'open-not-voted';
	}

	return 'unknown';
}
