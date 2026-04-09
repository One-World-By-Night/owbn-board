<?php
/**
 * Portals module — data helpers for counts and recent-item queries.
 *
 * Reads from external plugins (OAT, wp-voting-plugin, owbn-territory-manager)
 * when they're installed locally. Falls back gracefully when they aren't.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_portals_oat_available() {
	return function_exists( 'owc_oat_get_dashboard_counts' );
}

function owbn_board_portals_wpvp_available() {
	return function_exists( 'owc_wpvp_get_vote_counts' );
}

function owbn_board_portals_tm_available() {
	return function_exists( 'owc_get_territories' );
}

function owbn_board_portals_oat_counts( $user_id = 0 ) {
	if ( ! owbn_board_portals_oat_available() ) {
		return null;
	}
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}
	$result = owc_oat_get_dashboard_counts( $user_id );
	return is_wp_error( $result ) ? null : $result;
}

function owbn_board_portals_oat_recent( $limit = 5, $user_id = 0 ) {
	if ( ! function_exists( 'owc_oat_get_recent_activity' ) ) {
		return [];
	}
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}
	$result = owc_oat_get_recent_activity( $user_id, $limit );
	return is_wp_error( $result ) ? [] : (array) $result;
}

function owbn_board_portals_wpvp_counts() {
	if ( ! owbn_board_portals_wpvp_available() ) {
		return null;
	}
	$result = owc_wpvp_get_vote_counts();
	return is_array( $result ) ? $result : null;
}

function owbn_board_portals_wpvp_recent_open( $limit = 5 ) {
	if ( ! owbn_board_portals_wpvp_available() ) {
		return [];
	}
	return (array) owc_wpvp_get_open_votes( absint( $limit ) );
}

function owbn_board_portals_tm_counts() {
	if ( ! owbn_board_portals_tm_available() ) {
		return null;
	}
	$territories = owc_get_territories();
	if ( is_wp_error( $territories ) || ! is_array( $territories ) ) {
		return null;
	}
	return [ 'publish' => count( $territories ) ];
}

function owbn_board_portals_tm_recent( $limit = 5 ) {
	if ( ! owbn_board_portals_tm_available() ) {
		return [];
	}
	$territories = owc_get_territories();
	if ( is_wp_error( $territories ) || ! is_array( $territories ) ) {
		return [];
	}
	usort( $territories, function ( $a, $b ) {
		return strcmp( (string) ( $b['update_date'] ?? '' ), (string) ( $a['update_date'] ?? '' ) );
	} );
	return array_slice( $territories, 0, absint( $limit ) );
}
