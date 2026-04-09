<?php
/**
 * Errata module — reads bylaw clause data via owc_bylaws_* cross-site wrappers.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_errata_bylaws_available() {
	return function_exists( 'owc_bylaws_get_recent' );
}

function owbn_board_errata_get_recent( $days = 30, $limit = 20 ) {
	if ( ! owbn_board_errata_bylaws_available() ) {
		return [];
	}
	$days  = absint( $days );
	$limit = absint( $limit );
	if ( $days < 1 ) {
		$days = 30;
	}
	if ( $limit < 1 ) {
		$limit = 20;
	}
	return (array) owc_bylaws_get_recent( $days, $limit );
}

function owbn_board_errata_format_clause( $clause ) {
	if ( ! is_array( $clause ) ) {
		return [
			'id'         => 0,
			'title'      => '',
			'section'    => '',
			'group'      => '',
			'permalink'  => '',
			'vote_url'   => '',
			'vote_ref'   => '',
			'vote_date'  => '',
			'change'     => 'amended',
			'modified'   => 0,
		];
	}
	return [
		'id'         => (int) ( $clause['id'] ?? 0 ),
		'title'      => (string) ( $clause['title'] ?? '' ),
		'section'    => (string) ( $clause['section_id'] ?? '' ),
		'group'      => (string) ( $clause['bylaw_group'] ?? '' ),
		'permalink'  => (string) ( $clause['permalink'] ?? '' ),
		'vote_url'   => (string) ( $clause['vote_url'] ?? '' ),
		'vote_ref'   => (string) ( $clause['vote_reference'] ?? '' ),
		'vote_date'  => (string) ( $clause['vote_date'] ?? '' ),
		'change'     => (string) ( $clause['change'] ?? 'amended' ),
		'modified'   => (int) ( $clause['modified_ts'] ?? 0 ),
	];
}
