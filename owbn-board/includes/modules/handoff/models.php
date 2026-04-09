<?php
/**
 * Handoff module — data access + scope resolution.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_handoff_table() {
	global $wpdb;
	return $wpdb->prefix . 'owbn_board_handoffs';
}

function owbn_board_handoff_sections_table() {
	global $wpdb;
	return $wpdb->prefix . 'owbn_board_handoff_sections';
}

function owbn_board_handoff_entries_table() {
	global $wpdb;
	return $wpdb->prefix . 'owbn_board_handoff_entries';
}

/**
 * Resolve the handoff scopes a user has access to based on their ASC roles.
 *
 * Valid scope patterns:
 *   chronicle/{slug}/hst — HST handoff (only HSTs of that chronicle)
 *   chronicle/{slug}/staff — staff handoff (staff, cm, hst of that chronicle)
 *   coordinator/{genre}/coordinator — coordinator office handoff
 *   exec/{office}/coordinator — exec office handoff
 *
 * @param int $user_id
 * @return array Array of scope strings
 */
function owbn_board_handoff_user_scopes( $user_id ) {
	$roles  = owbn_board_get_user_roles( $user_id );
	$scopes = [];

	foreach ( (array) $roles as $role ) {
		if ( preg_match( '#^chronicle/([^/]+)/(hst|cm|staff)$#', (string) $role, $m ) ) {
			$slug  = $m[1];
			$level = $m[2];
			// Everyone in chronicle staff accesses the staff handoff
			$scopes[] = "chronicle/{$slug}/staff";
			// HSTs also access their HST-specific handoff
			if ( 'hst' === $level ) {
				$scopes[] = "chronicle/{$slug}/hst";
			}
		} elseif ( preg_match( '#^coordinator/([^/]+)/(coordinator|sub-coordinator)$#', (string) $role, $m ) ) {
			$scopes[] = "coordinator/{$m[1]}/coordinator";
		} elseif ( preg_match( '#^exec/([^/]+)/coordinator$#', (string) $role, $m ) ) {
			$scopes[] = "exec/{$m[1]}/coordinator";
		}
	}

	$scopes = array_values( array_unique( $scopes ) );
	sort( $scopes, SORT_STRING );
	return $scopes;
}

/**
 * Fetch (or create) a handoff row for a given scope.
 */
function owbn_board_handoff_get_or_create( $scope ) {
	global $wpdb;
	$table = owbn_board_handoff_table();

	$row = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE scope = %s AND site_id = 0 LIMIT 1", $scope )
	);

	if ( $row ) {
		return $row;
	}

	$title = owbn_board_handoff_scope_title( $scope );
	$wpdb->insert(
		$table,
		[
			'scope'      => $scope,
			'site_id'    => 0,
			'title'      => $title,
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		],
		[ '%s', '%d', '%s', '%s', '%s' ]
	);

	$new_id = (int) $wpdb->insert_id;
	if ( ! $new_id ) {
		return null;
	}

	// Seed with default sections
	$defaults = owbn_board_handoff_default_sections();
	foreach ( $defaults as $i => $label ) {
		$wpdb->insert(
			owbn_board_handoff_sections_table(),
			[
				'handoff_id' => $new_id,
				'label'      => $label,
				'sort_order' => ( $i + 1 ) * 10,
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			],
			[ '%d', '%s', '%d', '%s', '%s' ]
		);
	}

	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $new_id ) );
}

/**
 * Generate a friendly title for a scope string.
 */
function owbn_board_handoff_scope_title( $scope ) {
	if ( preg_match( '#^chronicle/([^/]+)/(hst|staff)$#', $scope, $m ) ) {
		return sprintf( '%s — %s Handoff', strtoupper( $m[1] ), ucfirst( $m[2] ) );
	}
	if ( preg_match( '#^coordinator/([^/]+)/coordinator$#', $scope, $m ) ) {
		return sprintf( '%s Coordinator Office', ucfirst( $m[1] ) );
	}
	if ( preg_match( '#^exec/([^/]+)/coordinator$#', $scope, $m ) ) {
		return sprintf( 'Exec %s Office', strtoupper( $m[1] ) );
	}
	return $scope;
}

/**
 * Get sections for a handoff, ordered.
 */
function owbn_board_handoff_get_sections( $handoff_id ) {
	global $wpdb;
	$table = owbn_board_handoff_sections_table();
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE handoff_id = %d ORDER BY sort_order ASC, id ASC",
			absint( $handoff_id )
		)
	);
}

/**
 * Get entries within a section, newest first, excluding soft-deleted.
 */
function owbn_board_handoff_get_entries( $section_id, $limit = 50 ) {
	global $wpdb;
	$table = owbn_board_handoff_entries_table();
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table}
			 WHERE section_id = %d AND deleted_at IS NULL
			 ORDER BY created_at DESC, id DESC
			 LIMIT %d",
			absint( $section_id ),
			absint( $limit )
		)
	);
}

/**
 * Get recent entries across a whole handoff (for the tile).
 */
function owbn_board_handoff_get_recent_entries( $handoff_id, $limit = 5 ) {
	global $wpdb;
	$entries_table  = owbn_board_handoff_entries_table();
	$sections_table = owbn_board_handoff_sections_table();
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT e.*, s.label AS section_label
			 FROM {$entries_table} e
			 INNER JOIN {$sections_table} s ON s.id = e.section_id
			 WHERE s.handoff_id = %d AND e.deleted_at IS NULL
			 ORDER BY e.created_at DESC, e.id DESC
			 LIMIT %d",
			absint( $handoff_id ),
			absint( $limit )
		)
	);
}

function owbn_board_handoff_get_entry( $id ) {
	global $wpdb;
	$table = owbn_board_handoff_entries_table();
	return $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) )
	);
}

function owbn_board_handoff_create_entry( array $data ) {
	global $wpdb;

	$section_id = isset( $data['section_id'] ) ? absint( $data['section_id'] ) : 0;
	if ( ! $section_id ) {
		return false;
	}

	$result = $wpdb->insert(
		owbn_board_handoff_entries_table(),
		[
			'section_id' => $section_id,
			'author_id'  => get_current_user_id(),
			'title'      => isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '',
			'body'       => isset( $data['body'] ) ? wp_kses_post( $data['body'] ) : '',
			'tags'       => isset( $data['tags'] ) ? sanitize_text_field( $data['tags'] ) : '',
			'status'     => owbn_board_handoff_sanitize_status( $data['status'] ?? 'current' ),
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		],
		[ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
	);

	return false === $result ? false : (int) $wpdb->insert_id;
}

function owbn_board_handoff_update_entry( $id, array $data ) {
	global $wpdb;

	$update = [];
	$fmt    = [];

	if ( isset( $data['title'] ) ) {
		$update['title'] = sanitize_text_field( $data['title'] );
		$fmt[]           = '%s';
	}
	if ( isset( $data['body'] ) ) {
		$update['body'] = wp_kses_post( $data['body'] );
		$fmt[]          = '%s';
	}
	if ( isset( $data['tags'] ) ) {
		$update['tags'] = sanitize_text_field( $data['tags'] );
		$fmt[]          = '%s';
	}
	if ( isset( $data['status'] ) ) {
		$update['status'] = owbn_board_handoff_sanitize_status( $data['status'] );
		$fmt[]            = '%s';
	}

	if ( empty( $update ) ) {
		return false;
	}

	$update['updated_at'] = current_time( 'mysql' );
	$fmt[]                = '%s';

	$result = $wpdb->update(
		owbn_board_handoff_entries_table(),
		$update,
		[ 'id' => absint( $id ) ],
		$fmt,
		[ '%d' ]
	);
	return false !== $result;
}

function owbn_board_handoff_delete_entry( $id ) {
	global $wpdb;
	return false !== $wpdb->update(
		owbn_board_handoff_entries_table(),
		[ 'deleted_at' => current_time( 'mysql' ) ],
		[ 'id' => absint( $id ) ],
		[ '%s' ],
		[ '%d' ]
	);
}

function owbn_board_handoff_sanitize_status( $status ) {
	return in_array( $status, [ 'current', 'outdated', 'superseded' ], true ) ? $status : 'current';
}

/**
 * Add a new section to a handoff.
 */
function owbn_board_handoff_add_section( $handoff_id, $label ) {
	global $wpdb;

	$label = sanitize_text_field( $label );
	if ( empty( $label ) ) {
		return false;
	}

	// Place it at the end
	$max_order = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT MAX(sort_order) FROM " . owbn_board_handoff_sections_table() . " WHERE handoff_id = %d",
			absint( $handoff_id )
		)
	);

	$result = $wpdb->insert(
		owbn_board_handoff_sections_table(),
		[
			'handoff_id' => absint( $handoff_id ),
			'label'      => $label,
			'sort_order' => $max_order + 10,
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		],
		[ '%d', '%s', '%d', '%s', '%s' ]
	);

	return false === $result ? false : (int) $wpdb->insert_id;
}

/**
 * Verify a section belongs to a handoff that the user has access to.
 */
function owbn_board_handoff_section_belongs_to_user( $section_id, $user_id ) {
	global $wpdb;
	$section = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT s.*, h.scope FROM " . owbn_board_handoff_sections_table() . " s
			 INNER JOIN " . owbn_board_handoff_table() . " h ON h.id = s.handoff_id
			 WHERE s.id = %d",
			absint( $section_id )
		)
	);
	if ( ! $section ) {
		return false;
	}
	$scopes = owbn_board_handoff_user_scopes( $user_id );
	return in_array( $section->scope, $scopes, true );
}

/**
 * Verify an entry belongs to a handoff the user has access to.
 */
function owbn_board_handoff_entry_belongs_to_user( $entry_id, $user_id ) {
	global $wpdb;
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT e.*, h.scope FROM " . owbn_board_handoff_entries_table() . " e
			 INNER JOIN " . owbn_board_handoff_sections_table() . " s ON s.id = e.section_id
			 INNER JOIN " . owbn_board_handoff_table() . " h ON h.id = s.handoff_id
			 WHERE e.id = %d",
			absint( $entry_id )
		)
	);
	if ( ! $row ) {
		return false;
	}
	$scopes = owbn_board_handoff_user_scopes( $user_id );
	return in_array( $row->scope, $scopes, true );
}
