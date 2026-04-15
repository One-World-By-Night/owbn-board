<?php
/**
 * Sessions module — data access.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_sessions_table() {
	global $wpdb;
	return $wpdb->prefix . 'owbn_board_sessions';
}

function owbn_board_sessions_history_table() {
	global $wpdb;
	return $wpdb->prefix . 'owbn_board_session_history';
}

/**
 * Fetch sessions for a chronicle, most recent first. Cross-site via wrapper.
 */
function owbn_board_sessions_get_by_chronicle( $chronicle_slug, $limit = 20 ) {
	if ( function_exists( 'owc_board_sessions_list' ) ) {
		$rows = owc_board_sessions_list( $chronicle_slug, $limit );
		return array_map( function ( $r ) { return (object) $r; }, $rows );
	}
	return [];
}

/**
 * Fetch a single session.
 */
function owbn_board_sessions_get( $id ) {
	global $wpdb;
	$table = owbn_board_sessions_table();
	return $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) )
	);
}

/**
 * Create a session record.
 */
function owbn_board_sessions_create( array $data ) {
	global $wpdb;

	$slug  = isset( $data['chronicle_slug'] ) ? sanitize_key( $data['chronicle_slug'] ) : '';
	$date  = isset( $data['session_date'] ) ? sanitize_text_field( $data['session_date'] ) : '';
	$title = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '';

	if ( empty( $slug ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		return false;
	}

	$now    = current_time( 'mysql' );
	$user   = get_current_user_id();
	$result = $wpdb->insert(
		owbn_board_sessions_table(),
		[
			'chronicle_slug' => $slug,
			'site_id'        => 0,
			'session_date'   => $date,
			'title'          => $title,
			'summary'        => isset( $data['summary'] ) ? wp_kses_post( $data['summary'] ) : '',
			'notes'          => isset( $data['notes'] ) ? wp_kses_post( $data['notes'] ) : '',
			'attendance'     => isset( $data['attendance'] ) ? sanitize_textarea_field( $data['attendance'] ) : '',
			'created_at'     => $now,
			'created_by'     => $user,
			'updated_at'     => $now,
			'updated_by'     => $user,
		],
		[ '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d' ]
	);

	return false === $result ? false : (int) $wpdb->insert_id;
}

/**
 * Update a session. Writes a history row first.
 */
function owbn_board_sessions_update( $id, array $data ) {
	global $wpdb;

	$existing = owbn_board_sessions_get( $id );
	if ( ! $existing ) {
		return false;
	}

	// Snapshot current content into history before updating
	$wpdb->insert(
		owbn_board_sessions_history_table(),
		[
			'session_id' => absint( $id ),
			'summary'    => $existing->summary,
			'notes'      => $existing->notes,
			'changed_at' => current_time( 'mysql' ),
			'changed_by' => get_current_user_id(),
		],
		[ '%d', '%s', '%s', '%s', '%d' ]
	);

	$update = [];
	$fmt    = [];
	if ( isset( $data['session_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $data['session_date'] ) ) {
		$update['session_date'] = sanitize_text_field( $data['session_date'] );
		$fmt[]                  = '%s';
	}
	if ( isset( $data['title'] ) ) {
		$update['title'] = sanitize_text_field( $data['title'] );
		$fmt[]           = '%s';
	}
	if ( isset( $data['summary'] ) ) {
		$update['summary'] = wp_kses_post( $data['summary'] );
		$fmt[]             = '%s';
	}
	if ( isset( $data['notes'] ) ) {
		$update['notes'] = wp_kses_post( $data['notes'] );
		$fmt[]           = '%s';
	}
	if ( isset( $data['attendance'] ) ) {
		$update['attendance'] = sanitize_textarea_field( $data['attendance'] );
		$fmt[]                = '%s';
	}
	$update['updated_at'] = current_time( 'mysql' );
	$fmt[]                = '%s';
	$update['updated_by'] = get_current_user_id();
	$fmt[]                = '%d';

	$result = $wpdb->update(
		owbn_board_sessions_table(),
		$update,
		[ 'id' => absint( $id ) ],
		$fmt,
		[ '%d' ]
	);

	// Prune history to last 20 per session
	$history_table = owbn_board_sessions_history_table();
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$history_table}
			 WHERE session_id = %d
			 AND id NOT IN (
				SELECT id FROM (
					SELECT id FROM {$history_table}
					WHERE session_id = %d
					ORDER BY changed_at DESC
					LIMIT 20
				) AS keep_ids
			 )",
			absint( $id ),
			absint( $id )
		)
	);

	return false !== $result;
}

/**
 * Delete a session (hard delete — sessions are editable, deletion is deliberate).
 */
function owbn_board_sessions_delete( $id ) {
	global $wpdb;
	$wpdb->delete(
		owbn_board_sessions_history_table(),
		[ 'session_id' => absint( $id ) ],
		[ '%d' ]
	);
	return false !== $wpdb->delete(
		owbn_board_sessions_table(),
		[ 'id' => absint( $id ) ],
		[ '%d' ]
	);
}

/**
 * Return chronicle slugs where the user has staff-level access (for session authoring).
 */
function owbn_board_sessions_user_chronicle_slugs( $user_id ) {
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
