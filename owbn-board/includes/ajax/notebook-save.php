<?php
/**
 * Notebook AJAX: save and load. Supports both Share Level group keys
 * (chronicle/mckn) and legacy full role paths (chronicle/mckn/hst).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_owbn_board_notebook_save', 'owbn_board_ajax_notebook_save' );
add_action( 'wp_ajax_owbn_board_notebook_load', 'owbn_board_ajax_notebook_load' );

function owbn_board_notebook_user_owns_scope( $role_path, $user_id ) {
	if ( function_exists( 'owbn_board_tile_access_resolve_scope' ) ) {
		$groups = owbn_board_tile_access_resolve_scope( 'board:notebook', $user_id );
		if ( in_array( $role_path, $groups, true ) ) {
			return true;
		}
	}
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

	$tile = owbn_board_get_tile( 'board:notebook' );
	if ( $tile && ! owbn_board_user_can_write_tile( $tile, $user_id ) ) {
		wp_send_json_error( [ 'message' => 'Forbidden (tile)' ], 403 );
	}

	if ( ! owbn_board_notebook_user_owns_scope( $notebook->role_path, $user_id ) ) {
		wp_send_json_error( [ 'message' => 'Forbidden (scope)' ], 403 );
	}

	// History row captures prior content before update.
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

	// Keep last 50 history rows per notebook.
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

	$tile = owbn_board_get_tile( 'board:notebook' );
	if ( $tile && ! owbn_board_user_can_read_tile( $tile, $user_id ) ) {
		wp_send_json_error( [ 'message' => 'Forbidden (tile)' ], 403 );
	}

	if ( ! owbn_board_notebook_user_owns_scope( $group, $user_id ) ) {
		wp_send_json_error( [ 'message' => 'Forbidden (scope)' ], 403 );
	}

	$can_write = $tile ? owbn_board_user_can_write_tile( $tile, $user_id ) : true;

	if ( $can_write ) {
		$notebook = owbn_board_notebook_get_or_create( $group );
		if ( ! $notebook ) {
			wp_send_json_error( [ 'message' => 'Could not load notebook' ], 500 );
		}
	} else {
		$notebook = owbn_board_notebook_get( $group );
		if ( ! $notebook ) {
			wp_send_json_success( [
				'notebook_id'     => 0,
				'role_path'       => $group,
				'content'         => '',
				'updated_by_name' => '',
				'updated_at'      => '',
				'updated_ago'     => '',
				'can_write'       => false,
				'empty'           => true,
			] );
		}
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
		'can_write'       => $can_write,
		'empty'           => false,
	] );
}
