<?php
/**
 * Portals module — data helpers for counts and recent-item queries.
 *
 * Reads from external plugins (OAT, wp-voting-plugin, owbn-territory-manager)
 * when they're installed locally. Falls back gracefully when they aren't.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Availability checks
 */
function owbn_board_portals_oat_available() {
	global $wpdb;
	$table = $wpdb->prefix . 'oat_entries';
	return (bool) $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
}

function owbn_board_portals_wpvp_available() {
	global $wpdb;
	// wp-voting-plugin uses prefix wpvp_votes
	$table = $wpdb->prefix . 'wpvp_votes';
	return (bool) $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
}

function owbn_board_portals_tm_available() {
	return post_type_exists( 'owbn_territory' );
}

/**
 * OAT counts.
 */
function owbn_board_portals_oat_counts() {
	global $wpdb;
	if ( ! owbn_board_portals_oat_available() ) {
		return null;
	}
	$table = $wpdb->prefix . 'oat_entries';
	return [
		'pending'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" ),
		'approved' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'approved'" ),
		'denied'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'denied'" ),
	];
}

/**
 * Recent OAT entries (most recent 5 by updated_at).
 */
function owbn_board_portals_oat_recent( $limit = 5 ) {
	global $wpdb;
	if ( ! owbn_board_portals_oat_available() ) {
		return [];
	}
	$table = $wpdb->prefix . 'oat_entries';
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, domain, status, chronicle_slug, updated_at
			 FROM {$table}
			 ORDER BY updated_at DESC
			 LIMIT %d",
			absint( $limit )
		)
	);
}

/**
 * wp-voting-plugin counts.
 */
function owbn_board_portals_wpvp_counts() {
	global $wpdb;
	if ( ! owbn_board_portals_wpvp_available() ) {
		return null;
	}
	$table = $wpdb->prefix . 'wpvp_votes';
	return [
		'draft'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE voting_stage = 'draft'" ),
		'open'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE voting_stage = 'open'" ),
		'closed' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE voting_stage = 'closed'" ),
	];
}

/**
 * Recent active votes for the exec quick-actions tile.
 */
function owbn_board_portals_wpvp_recent_open( $limit = 5 ) {
	global $wpdb;
	if ( ! owbn_board_portals_wpvp_available() ) {
		return [];
	}
	$table = $wpdb->prefix . 'wpvp_votes';
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, proposal_name, voting_stage, opening_date, closing_date
			 FROM {$table}
			 WHERE voting_stage = 'open'
			 ORDER BY closing_date ASC
			 LIMIT %d",
			absint( $limit )
		)
	);
}

/**
 * Territory Manager counts + recents.
 */
function owbn_board_portals_tm_counts() {
	if ( ! owbn_board_portals_tm_available() ) {
		return null;
	}
	$count = wp_count_posts( 'owbn_territory' );
	return [
		'publish' => (int) ( $count->publish ?? 0 ),
	];
}

/**
 * Most recent territory posts (last 5 modified).
 */
function owbn_board_portals_tm_recent( $limit = 5 ) {
	if ( ! owbn_board_portals_tm_available() ) {
		return [];
	}
	return (array) get_posts( [
		'post_type'      => 'owbn_territory',
		'post_status'    => 'publish',
		'posts_per_page' => absint( $limit ),
		'orderby'        => 'modified',
		'order'          => 'DESC',
	] );
}
