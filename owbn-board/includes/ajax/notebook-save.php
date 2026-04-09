<?php
/**
 * AJAX: save and load notebook content.
 *
 * The notebook tile may render many notebooks for one user when the
 * tile-access module's Share Level resolves to multiple groups (e.g.
 * a player holding several coordinator posts). The load handler lets
 * the client swap notebooks without a full page reload; the save
 * handler accepts writes keyed by notebook_id and verifies scope
 * ownership against both the share-level resolution and the legacy
 * direct-pattern match.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_owbn_board_notebook_save', 'owbn_board_ajax_notebook_save' );
add_action( 'wp_ajax_owbn_board_notebook_load', 'owbn_board_ajax_notebook_load' );

/**
 * Confirm the user is authorized to interact with a notebook whose
 * role_path may be either a Share Level group key (e.g. "chronicle/mckn")
 * or a legacy full role path (e.g. "chronicle/mckn/hst").
 */
function owbn_board_notebook_user_owns_scope( $role_path, $user_id ) {
	// Share Level mode: role_path is in the user's resolved group set.
	if ( function_exists( 'owbn_board_tile_access_resolve_scope' ) ) {
		$groups = owbn_board_tile_access_resolve_scope( 'board:notebook', $user_id );
		if ( in_array( $role_path, $groups, true ) ) {
			return true;
		}
	}
	// Legacy mode: any of the user's ASC roles matches role_path as a pattern.
	$user_roles = owbn_board_get_user_roles( $user_id );
	return owbn_board_user_matches_any_pattern( $user_roles, [ $role_path ] );
}

function owbn_board_ajax_notebook_save() {
	if ( ! check_ajax_referer( 'owbn_board', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
	}

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( [ 'message' => 'Not logged in' ], 401 );
	}

	$notebook_id = isset( $_POST['notebook_id'] ) ? absint( $_POST['notebook_id'] ) : 0;
	$content     = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';

	if ( ! $notebook_id ) {
		wp_send_json_error( [ 'message' => 'Missing notebook_id' ], 400 );
	}

	global $wpdb;

	$notebook = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}owbn_board_notebooks WHERE id = %d",
			$notebook_id
		)
	);

	if ( ! $notebook ) {
		wp_send_json_error( [ 'message' => 'Notebook not found' ], 404 );
	}

	// Must be able to write the notebook tile in the first place.
	$tile = owbn_board_get_tile( 'board:notebook' );
	if ( $tile && ! owbn_board_user_can_write_tile( $tile, $user_id ) ) {
		wp_send_json_error( [ 'message' => 'Forbidden (tile)' ], 403 );
	}

	// Scope ownership: either via resolved Share Level group or legacy pattern.
	if ( ! owbn_board_notebook_user_owns_scope( $notebook->role_path, $user_id ) ) {
		wp_send_json_error( [ 'message' => 'Forbidden (scope)' ], 403 );
	}

	// Write history row first (preserving prior content)
	$wpdb->insert(
		$wpdb->prefix . 'owbn_board_notebook_history',
		[
			'notebook_id' => $notebook_id,
			'content'     => $notebook->content,
			'changed_at'  => current_time( 'mysql' ),
			'changed_by'  => $user_id,
		],
		[ '%d', '%s', '%s', '%d' ]
	);

	// Update current
	$wpdb->update(
		$wpdb->prefix . 'owbn_board_notebooks',
		[
			'content'    => $content,
			'updated_at' => current_time( 'mysql' ),
			'updated_by' => $user_id,
		],
		[ 'id' => $notebook_id ],
		[ '%s', '%s', '%d' ],
		[ '%d' ]
	);

	// Prune history: keep last 50 per notebook
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}owbn_board_notebook_history
			 WHERE notebook_id = %d
			 AND id NOT IN (
				SELECT id FROM (
					SELECT id FROM {$wpdb->prefix}owbn_board_notebook_history
					WHERE notebook_id = %d
					ORDER BY changed_at DESC
					LIMIT 50
				) AS keep_ids
			 )",
			$notebook_id,
			$notebook_id
		)
	);

	owbn_board_audit( $user_id, 'notebook.edit', 'notebook', $notebook_id );

	wp_send_json_success( [
		'updated_at' => current_time( 'mysql' ),
		'message'    => 'Saved',
	] );
}

/**
 * Load a notebook by its scope group key (role_path). Used by the
 * client-side group switcher to swap notebook content without a page
 * reload. Verifies the user actually owns the requested scope.
 */
function owbn_board_ajax_notebook_load() {
	if ( ! check_ajax_referer( 'owbn_board', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
	}

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( [ 'message' => 'Not logged in' ], 401 );
	}

	$group = isset( $_POST['group'] ) ? sanitize_text_field( wp_unslash( $_POST['group'] ) ) : '';
	if ( '' === $group ) {
		wp_send_json_error( [ 'message' => 'Missing group' ], 400 );
	}

	// Must be able to read the notebook tile.
	$tile = owbn_board_get_tile( 'board:notebook' );
	if ( $tile && ! owbn_board_user_can_read_tile( $tile, $user_id ) ) {
		wp_send_json_error( [ 'message' => 'Forbidden (tile)' ], 403 );
	}

	if ( ! owbn_board_notebook_user_owns_scope( $group, $user_id ) ) {
		wp_send_json_error( [ 'message' => 'Forbidden (scope)' ], 403 );
	}

	$notebook = owbn_board_notebook_get_or_create( $group );
	if ( ! $notebook ) {
		wp_send_json_error( [ 'message' => 'Could not load notebook' ], 500 );
	}

	$updated_by      = $notebook->updated_by ? get_userdata( $notebook->updated_by ) : null;
	$updated_by_name = $updated_by ? $updated_by->display_name : __( 'unknown', 'owbn-board' );

	wp_send_json_success( [
		'notebook_id'     => (int) $notebook->id,
		'role_path'       => $notebook->role_path,
		'content'         => $notebook->content,
		'updated_by_name' => $updated_by_name,
		'updated_at'      => $notebook->updated_at,
		'updated_ago'     => human_time_diff( strtotime( $notebook->updated_at ), time() ) . ' ' . __( 'ago', 'owbn-board' ),
		'can_write'       => $tile ? owbn_board_user_can_write_tile( $tile, $user_id ) : true,
	] );
}
