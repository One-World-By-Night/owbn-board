<?php
/**
 * Errata module — reads bylaw-clause-manager data via the CPT.
 *
 * No own schema — this module is a read-only view over bylaw_clause posts.
 * It queries the local DB when running on a site where bylaw-clause-manager
 * is installed (council/chronicles). On sites without it, the tile returns
 * an empty result gracefully.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Is bylaw-clause-manager installed on this site?
 */
function owbn_board_errata_bylaws_available() {
	return post_type_exists( 'bylaw_clause' );
}

/**
 * Fetch recent bylaw clause changes within the given window.
 *
 * Uses post_modified_gmt so we catch both new clauses and amendments.
 *
 * @param int $days  Window in days (default 30)
 * @param int $limit Max results (default 20)
 * @return array Array of bylaw clause post objects
 */
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

	$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

	return (array) get_posts( [
		'post_type'      => 'bylaw_clause',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'orderby'        => 'modified',
		'order'          => 'DESC',
		'date_query'     => [
			[
				'column'    => 'post_modified_gmt',
				'after'     => $cutoff,
				'inclusive' => true,
			],
		],
	] );
}

/**
 * Determine whether a clause is newly added vs amended.
 *
 * A clause is "added" if its post_date equals its post_modified (never edited).
 * Otherwise it's "amended".
 */
function owbn_board_errata_classify_change( $post ) {
	if ( ! $post ) {
		return 'unknown';
	}
	$created  = strtotime( $post->post_date_gmt );
	$modified = strtotime( $post->post_modified_gmt );

	// If modified within 60 seconds of creation, treat as "added"
	if ( $modified - $created < 60 ) {
		return 'added';
	}
	return 'amended';
}

/**
 * Build a display-ready structure for a clause.
 */
function owbn_board_errata_format_clause( $post ) {
	$section  = get_post_meta( $post->ID, 'section_id', true );
	$group    = get_post_meta( $post->ID, 'bylaw_group', true );
	$vote_url = get_post_meta( $post->ID, 'vote_url', true );
	$vote_ref = get_post_meta( $post->ID, 'vote_reference', true );
	$vote_dt  = get_post_meta( $post->ID, 'vote_date', true );

	return [
		'id'         => $post->ID,
		'title'      => get_the_title( $post ),
		'section'    => $section,
		'group'      => $group,
		'permalink'  => get_permalink( $post ),
		'vote_url'   => $vote_url,
		'vote_ref'   => $vote_ref,
		'vote_date'  => $vote_dt,
		'change'     => owbn_board_errata_classify_change( $post ),
		'modified'   => strtotime( $post->post_modified_gmt ),
	];
}
