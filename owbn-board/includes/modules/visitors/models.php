<?php
/**
 * Visitors module — data access.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_visitors_table() {
	global $wpdb;
	return $wpdb->prefix . 'owbn_board_visitors';
}

/**
 * Get visits hosted by a chronicle (visitors TO that chronicle).
 */
function owbn_board_visitors_get_by_host( $host_slug, $limit = 20 ) {
	global $wpdb;
	$table = owbn_board_visitors_table();
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table}
			 WHERE host_chronicle_slug = %s AND deleted_at IS NULL
			 ORDER BY visit_date DESC, id DESC LIMIT %d",
			$host_slug,
			absint( $limit )
		)
	);
}

/**
 * Get visits where players from a home chronicle were guests elsewhere.
 */
function owbn_board_visitors_get_by_home( $home_slug, $limit = 20 ) {
	global $wpdb;
	$table = owbn_board_visitors_table();
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table}
			 WHERE home_chronicle_slug = %s AND deleted_at IS NULL
			 ORDER BY visit_date DESC, id DESC LIMIT %d",
			$home_slug,
			absint( $limit )
		)
	);
}

/**
 * Get visits for a specific player (records they appear in).
 */
function owbn_board_visitors_get_by_player( $user_id, $limit = 20 ) {
	global $wpdb;
	$table = owbn_board_visitors_table();
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table}
			 WHERE visitor_user_id = %d AND deleted_at IS NULL
			 ORDER BY visit_date DESC, id DESC LIMIT %d",
			absint( $user_id ),
			absint( $limit )
		)
	);
}

/**
 * Create a visit entry. Returns new ID or false.
 */
function owbn_board_visitors_create( array $data ) {
	global $wpdb;

	$host      = isset( $data['host_chronicle_slug'] ) ? sanitize_key( $data['host_chronicle_slug'] ) : '';
	$home      = isset( $data['home_chronicle_slug'] ) ? sanitize_key( $data['home_chronicle_slug'] ) : '';
	$user_id   = isset( $data['visitor_user_id'] ) ? absint( $data['visitor_user_id'] ) : 0;
	$disp      = isset( $data['visitor_display_name'] ) ? sanitize_text_field( $data['visitor_display_name'] ) : '';
	$char      = isset( $data['character_name'] ) ? sanitize_text_field( $data['character_name'] ) : '';
	$date_raw  = isset( $data['visit_date'] ) ? sanitize_text_field( $data['visit_date'] ) : '';
	$notes     = isset( $data['notes'] ) ? wp_kses_post( $data['notes'] ) : '';

	if ( empty( $host ) || empty( $char ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_raw ) ) {
		return false;
	}

	// Resolve visitor display name if not provided
	if ( empty( $disp ) && $user_id ) {
		$u = get_userdata( $user_id );
		if ( $u ) {
			$disp = $u->display_name;
		}
	}

	$result = $wpdb->insert(
		owbn_board_visitors_table(),
		[
			'host_chronicle_slug'  => $host,
			'home_chronicle_slug'  => $home ?: null,
			'visitor_user_id'      => $user_id ?: null,
			'visitor_display_name' => $disp ?: null,
			'character_name'       => $char,
			'visit_date'           => $date_raw,
			'notes'                => $notes,
			'site_id'              => 0,
			'created_at'           => current_time( 'mysql' ),
			'created_by'           => get_current_user_id(),
		],
		[ '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d' ]
	);

	return false === $result ? false : (int) $wpdb->insert_id;
}

/**
 * Soft-delete a visit (admin or host HST only).
 */
function owbn_board_visitors_delete( $id ) {
	global $wpdb;
	return false !== $wpdb->update(
		owbn_board_visitors_table(),
		[ 'deleted_at' => current_time( 'mysql' ) ],
		[ 'id' => absint( $id ) ],
		[ '%s' ],
		[ '%d' ]
	);
}

/**
 * Return chronicle slugs where the user has staff-level roles.
 * Reads from their ASC roles, extracts slug from patterns like chronicle/{slug}/staff|cm|hst.
 *
 * @param int $user_id
 * @return array
 */
function owbn_board_visitors_user_host_slugs( $user_id ) {
	$roles = owbn_board_get_user_roles( $user_id );
	$slugs = [];
	foreach ( (array) $roles as $role ) {
		if ( preg_match( '#^chronicle/([^/]+)/(staff|cm|hst)$#', (string) $role, $m ) ) {
			$slugs[] = $m[1];
		}
	}
	$slugs = array_values( array_unique( $slugs ) );
	sort( $slugs, SORT_STRING );
	return $slugs;
}

/**
 * Derive a player's home chronicle slug from their ASC roles.
 * Picks the first chronicle role found.
 */
function owbn_board_visitors_user_home_slug( $user_id ) {
	$roles = owbn_board_get_user_roles( $user_id );
	foreach ( (array) $roles as $role ) {
		if ( preg_match( '#^chronicle/([^/]+)(/|$)#', (string) $role, $m ) ) {
			return $m[1];
		}
	}
	return '';
}
