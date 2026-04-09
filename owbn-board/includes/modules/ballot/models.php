<?php
/**
 * Ballot module — data helpers. Reads wpvp via owc_wpvp_* wrappers (cross-site).
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_ballot_wpvp_available() {
	return function_exists( 'owc_wpvp_get_open_votes' );
}

function owbn_board_ballot_oeb_available() {
	global $wpdb;
	$table = $wpdb->prefix . 'oeb_election_sets';
	return (bool) $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
}

function owbn_board_ballot_get_open_votes( $limit = 0 ) {
	if ( ! owbn_board_ballot_wpvp_available() ) {
		return [];
	}
	return (array) owc_wpvp_get_open_votes( (int) $limit );
}

function owbn_board_ballot_get_votes_for_election( $election_id, $include_closed = false ) {
	$election_id = absint( $election_id );
	if ( ! $election_id || ! owbn_board_ballot_wpvp_available() || ! owbn_board_ballot_oeb_available() ) {
		return [];
	}

	global $wpdb;
	$oeb_table = $wpdb->prefix . 'oeb_election_sets';
	$row       = $wpdb->get_row( $wpdb->prepare( "SELECT positions FROM {$oeb_table} WHERE id = %d", $election_id ) );
	if ( ! $row || empty( $row->positions ) ) {
		return [];
	}
	$positions = json_decode( $row->positions, true );
	if ( ! is_array( $positions ) ) {
		return [];
	}

	$wanted_ids = [];
	foreach ( $positions as $position ) {
		if ( ! empty( $position['vote_id'] ) ) {
			$wanted_ids[] = (int) $position['vote_id'];
		}
	}
	$wanted_ids = array_values( array_unique( array_filter( $wanted_ids ) ) );
	if ( empty( $wanted_ids ) ) {
		return [];
	}

	$open      = owc_wpvp_get_open_votes( 0 );
	$by_id     = [];
	foreach ( (array) $open as $vote ) {
		$by_id[ (int) ( $vote['id'] ?? 0 ) ] = $vote;
	}

	$out = [];
	foreach ( $wanted_ids as $id ) {
		if ( isset( $by_id[ $id ] ) ) {
			$out[] = $by_id[ $id ];
		} elseif ( $include_closed ) {
			$detail = owc_wpvp_get_vote( $id );
			if ( $detail && in_array( ( $detail['voting_stage'] ?? '' ), [ 'open', 'closed' ], true ) ) {
				$out[] = $detail;
			}
		}
	}

	usort( $out, function ( $a, $b ) {
		$ac = (string) ( $a['closing_date'] ?? '' );
		$bc = (string) ( $b['closing_date'] ?? '' );
		if ( $ac === $bc ) {
			return ( (int) ( $a['id'] ?? 0 ) ) - ( (int) ( $b['id'] ?? 0 ) );
		}
		return strcmp( $ac, $bc );
	} );

	return $out;
}

function owbn_board_ballot_get_vote( $vote_id ) {
	if ( ! owbn_board_ballot_wpvp_available() ) {
		return null;
	}
	return owc_wpvp_get_vote( $vote_id );
}

function owbn_board_ballot_decode_options( $vote ) {
	if ( ! $vote ) {
		return [];
	}
	$opts = $vote['voting_options'] ?? [];
	return is_array( $opts ) ? $opts : [];
}

function owbn_board_ballot_user_has_voted( $vote_id, $user_id ) {
	if ( ! function_exists( 'owc_wpvp_user_has_voted' ) || ! $user_id ) {
		return false;
	}
	return owc_wpvp_user_has_voted( $vote_id, $user_id );
}

function owbn_board_ballot_user_is_eligible( $vote, $user_id ) {
	if ( ! $vote || ! $user_id ) {
		return false;
	}
	$allowed = $vote['voting_roles'] ?? [];
	if ( empty( $allowed ) || ! is_array( $allowed ) ) {
		return true;
	}
	$user_roles = owbn_board_get_user_roles( $user_id );
	return owbn_board_user_matches_any_pattern( $user_roles, $allowed );
}

function owbn_board_ballot_card_state( $vote, $user_id ) {
	if ( ! $vote ) {
		return 'unknown';
	}
	$stage = $vote['voting_stage'] ?? '';
	if ( 'closed' === $stage ) {
		return 'closed';
	}
	if ( 'draft' === $stage ) {
		return 'scheduled';
	}
	if ( 'open' === $stage ) {
		if ( $user_id && owbn_board_ballot_user_has_voted( (int) ( $vote['id'] ?? 0 ), $user_id ) ) {
			return 'open-voted';
		}
		return 'open-not-voted';
	}
	return 'unknown';
}
