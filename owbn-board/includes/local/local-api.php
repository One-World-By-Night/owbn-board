<?php
/**
 * Local board data API. Direct DB queries against the host site (chronicles).
 * These are the implementations behind the owc_board_* wrappers when running
 * on the host. Cross-site callers hit the gateway routes which delegate here.
 *
 * Identity is by EMAIL — not WP user_id, since user IDs differ per site.
 */

defined( 'ABSPATH' ) || exit;

// ─── Messages ────────────────────────────────────────────────────────────

function owbn_board_local_messages_list( $scope, $limit = 20 ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, scope, owner_email, content, created_at
		 FROM {$wpdb->prefix}owbn_board_messages
		 WHERE scope = %s AND deleted_at IS NULL
		 ORDER BY created_at DESC
		 LIMIT %d",
		$scope,
		max( 1, (int) $limit )
	), ARRAY_A );
	return $rows ?: [];
}

function owbn_board_local_message_post( $scope, $email, $content ) {
	global $wpdb;
	$content = trim( wp_strip_all_tags( (string) $content ) );
	if ( '' === $content || '' === $scope || '' === $email ) {
		return new WP_Error( 'board_invalid', 'Missing required fields' );
	}
	if ( strlen( $content ) > 2000 ) {
		return new WP_Error( 'board_too_long', 'Message too long' );
	}
	$wpdb->insert(
		$wpdb->prefix . 'owbn_board_messages',
		[
			'scope'       => $scope,
			'owner_email' => $email,
			'content'     => $content,
			'created_at'  => current_time( 'mysql' ),
		],
		[ '%s', '%s', '%s', '%s' ]
	);
	$id = (int) $wpdb->insert_id;
	owbn_board_local_audit_log( $email, 'message.post', 'message', $id );
	return [
		'id'          => $id,
		'scope'       => $scope,
		'owner_email' => $email,
		'content'     => $content,
		'created_at'  => current_time( 'mysql' ),
	];
}

function owbn_board_local_message_delete( $message_id, $email ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT owner_email FROM {$wpdb->prefix}owbn_board_messages WHERE id = %d",
		(int) $message_id
	) );
	if ( ! $row ) {
		return false;
	}
	if ( $row->owner_email !== $email ) {
		return false;
	}
	$wpdb->update(
		$wpdb->prefix . 'owbn_board_messages',
		[ 'deleted_at' => current_time( 'mysql' ) ],
		[ 'id' => (int) $message_id ],
		[ '%s' ],
		[ '%d' ]
	);
	owbn_board_local_audit_log( $email, 'message.delete', 'message', (int) $message_id );
	return true;
}

// ─── Notebook ────────────────────────────────────────────────────────────

function owbn_board_local_notebook_get( $scope, $email = '', $create = false ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}owbn_board_notebooks WHERE scope = %s LIMIT 1",
		$scope
	), ARRAY_A );
	if ( $row || ! $create ) {
		return $row;
	}
	$wpdb->insert(
		$wpdb->prefix . 'owbn_board_notebooks',
		[
			'scope'            => $scope,
			'title'            => '',
			'content'          => '',
			'updated_at'       => current_time( 'mysql' ),
			'updated_by_email' => $email,
		],
		[ '%s', '%s', '%s', '%s', '%s' ]
	);
	$id = (int) $wpdb->insert_id;
	return $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}owbn_board_notebooks WHERE id = %d",
		$id
	), ARRAY_A );
}

function owbn_board_local_notebook_save( $scope, $email, $content ) {
	global $wpdb;
	$existing = owbn_board_local_notebook_get( $scope, $email, true );
	if ( ! $existing ) {
		return false;
	}
	$wpdb->insert(
		$wpdb->prefix . 'owbn_board_notebook_history',
		[
			'notebook_id'      => (int) $existing['id'],
			'content'          => $existing['content'],
			'changed_at'       => current_time( 'mysql' ),
			'changed_by_email' => $email,
		],
		[ '%d', '%s', '%s', '%s' ]
	);
	$wpdb->update(
		$wpdb->prefix . 'owbn_board_notebooks',
		[
			'content'          => wp_kses_post( $content ),
			'updated_at'       => current_time( 'mysql' ),
			'updated_by_email' => $email,
		],
		[ 'id' => (int) $existing['id'] ],
		[ '%s', '%s', '%s' ],
		[ '%d' ]
	);
	owbn_board_local_audit_log( $email, 'notebook.edit', 'notebook', (int) $existing['id'] );
	return true;
}

// ─── Handoff ─────────────────────────────────────────────────────────────

function owbn_board_local_handoff_get( $scope ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}owbn_board_handoffs WHERE scope = %s LIMIT 1",
		$scope
	), ARRAY_A );
	if ( ! $row ) {
		$wpdb->insert(
			$wpdb->prefix . 'owbn_board_handoffs',
			[
				'scope'      => $scope,
				'title'      => $scope,
				'created_at' => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%s' ]
		);
		$id  = (int) $wpdb->insert_id;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}owbn_board_handoffs WHERE id = %d",
			$id
		), ARRAY_A );
	}
	return $row;
}

function owbn_board_local_handoff_recent_entries( $handoff_id, $limit = 5 ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, handoff_id, section_id, section_label, title, body, created_at, created_by_email
		 FROM {$wpdb->prefix}owbn_board_handoff_entries
		 WHERE handoff_id = %d
		 ORDER BY created_at DESC
		 LIMIT %d",
		(int) $handoff_id,
		max( 1, (int) $limit )
	), ARRAY_A );
	return $rows ?: [];
}

// ─── Sessions ────────────────────────────────────────────────────────────

function owbn_board_local_sessions_list( $chronicle_slug, $limit = 5 ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, chronicle_slug, session_date, title, summary
		 FROM {$wpdb->prefix}owbn_board_sessions
		 WHERE chronicle_slug = %s
		 ORDER BY session_date DESC
		 LIMIT %d",
		$chronicle_slug,
		max( 1, (int) $limit )
	), ARRAY_A );
	return $rows ?: [];
}

// ─── Visitors ────────────────────────────────────────────────────────────

function owbn_board_local_visitors_list( $host_slug, $limit = 10 ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, host_chronicle_slug, home_chronicle_slug, character_name,
		        visitor_email, visitor_display_name, visit_date, notes
		 FROM {$wpdb->prefix}owbn_board_visitors
		 WHERE host_chronicle_slug = %s
		 ORDER BY visit_date DESC
		 LIMIT %d",
		$host_slug,
		max( 1, (int) $limit )
	), ARRAY_A );
	return $rows ?: [];
}

function owbn_board_local_visitors_by_player( $email, $limit = 10 ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, host_chronicle_slug, home_chronicle_slug, character_name,
		        visitor_email, visitor_display_name, visit_date, notes
		 FROM {$wpdb->prefix}owbn_board_visitors
		 WHERE visitor_email = %s
		 ORDER BY visit_date DESC
		 LIMIT %d",
		$email,
		max( 1, (int) $limit )
	), ARRAY_A );
	return $rows ?: [];
}

// ─── Per-user state + prefs ──────────────────────────────────────────────

function owbn_board_local_state_get( $email ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT tile_id, state, snooze_until FROM {$wpdb->prefix}owbn_board_user_state WHERE owner_email = %s",
		$email
	), ARRAY_A );
	$state = [];
	foreach ( (array) $rows as $row ) {
		$state[ $row['tile_id'] ] = [
			'state'        => $row['state'],
			'snooze_until' => $row['snooze_until'],
		];
	}
	return $state;
}

function owbn_board_local_state_set( $email, $tile_id, $state, $snooze_until = null ) {
	global $wpdb;
	$allowed = [ 'default', 'collapsed', 'pinned', 'snoozed', 'dismissed' ];
	if ( ! in_array( $state, $allowed, true ) ) {
		return false;
	}
	return false !== $wpdb->replace(
		$wpdb->prefix . 'owbn_board_user_state',
		[
			'owner_email'  => $email,
			'tile_id'      => sanitize_text_field( $tile_id ),
			'state'        => $state,
			'snooze_until' => $snooze_until,
			'updated_at'   => current_time( 'mysql' ),
		],
		[ '%s', '%s', '%s', '%s', '%s' ]
	);
}

function owbn_board_local_prefs_get( $email ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT pref_key, pref_value FROM {$wpdb->prefix}owbn_board_user_prefs WHERE owner_email = %s",
		$email
	), ARRAY_A );
	$out = [ 'sizes' => [], 'order' => [] ];
	foreach ( (array) $rows as $row ) {
		$decoded = json_decode( $row['pref_value'], true );
		if ( 'sizes' === $row['pref_key'] && is_array( $decoded ) ) {
			$out['sizes'] = $decoded;
		} elseif ( 'order' === $row['pref_key'] && is_array( $decoded ) ) {
			$out['order'] = $decoded;
		}
	}
	return $out;
}

function owbn_board_local_prefs_set( $email, $key, $value ) {
	global $wpdb;
	if ( ! in_array( $key, [ 'sizes', 'order' ], true ) ) {
		return false;
	}
	return false !== $wpdb->replace(
		$wpdb->prefix . 'owbn_board_user_prefs',
		[
			'owner_email' => $email,
			'pref_key'    => $key,
			'pref_value'  => wp_json_encode( $value ),
			'updated_at'  => current_time( 'mysql' ),
		],
		[ '%s', '%s', '%s', '%s' ]
	);
}

// ─── Audit ───────────────────────────────────────────────────────────────

function owbn_board_local_audit_log( $email, $action, $subject_type = '', $subject_id = 0, $details = [] ) {
	global $wpdb;
	$wpdb->insert(
		$wpdb->prefix . 'owbn_board_audit_log',
		[
			'owner_email'  => (string) $email,
			'action'       => sanitize_text_field( $action ),
			'subject_type' => $subject_type ? sanitize_text_field( $subject_type ) : null,
			'subject_id'   => $subject_id ? absint( $subject_id ) : null,
			'details'      => $details ? wp_json_encode( $details ) : null,
			'ip_address'   => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : null,
			'created_at'   => current_time( 'mysql' ),
		],
		[ '%s', '%s', '%s', '%d', '%s', '%s', '%s' ]
	);
	return true;
}
