<?php
/**
 * AJAX: save notebook content.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_owbn_board_notebook_save', 'owbn_board_ajax_notebook_save' );

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

	// Fetch the notebook and verify the user has write access to its role_path
	$notebook = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}owbn_board_notebooks WHERE id = %d",
			$notebook_id
		)
	);

	if ( ! $notebook ) {
		wp_send_json_error( [ 'message' => 'Notebook not found' ], 404 );
	}

	// Pattern check: does the user match the role_path exactly or via wildcard?
	$user_roles = owbn_board_get_user_roles( $user_id );
	if ( ! owbn_board_user_matches_any_pattern( $user_roles, [ $notebook->role_path ] ) ) {
		wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
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
