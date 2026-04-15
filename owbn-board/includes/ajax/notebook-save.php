<?php
// Notebook save/load AJAX. Cross-site via owc_board_notebook_* wrappers.

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_owbn_board_notebook_save', 'owbn_board_ajax_notebook_save' );
add_action( 'wp_ajax_owbn_board_notebook_load', 'owbn_board_ajax_notebook_load' );

function owbn_board_notebook_user_owns_scope( $group, $user_id ) {
	$groups = owbn_board_notebook_resolve_scope_groups( $user_id );
	return in_array( $group, $groups, true );
}

function owbn_board_ajax_notebook_save() {
	if ( ! check_ajax_referer( 'owbn_board', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
	}
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( [ 'message' => 'Not logged in' ], 401 );
	}
	$user = get_userdata( $user_id );
	if ( ! $user || ! $user->user_email ) {
		wp_send_json_error( [ 'message' => 'No email on user' ], 400 );
	}

	$scope   = isset( $_POST['scope'] ) ? sanitize_text_field( wp_unslash( $_POST['scope'] ) ) : '';
	$content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';

	if ( '' === $scope ) {
		wp_send_json_error( [ 'message' => 'Missing scope' ], 400 );
	}

	$tile = owbn_board_get_tile( 'board:notebook' );
	if ( $tile && ! owbn_board_user_can_write_tile( $tile, $user_id ) ) {
		wp_send_json_error( [ 'message' => 'Forbidden (tile)' ], 403 );
	}
	if ( ! owbn_board_notebook_user_owns_scope( $scope, $user_id ) ) {
		wp_send_json_error( [ 'message' => 'Forbidden (scope)' ], 403 );
	}

	$ok = function_exists( 'owc_board_notebook_save' )
		? owc_board_notebook_save( $scope, $user->user_email, $content )
		: false;

	if ( ! $ok ) {
		wp_send_json_error( [ 'message' => 'Save failed' ], 500 );
	}

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
	$user = get_userdata( $user_id );
	if ( ! $user || ! $user->user_email ) {
		wp_send_json_error( [ 'message' => 'No email on user' ], 400 );
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

	$notebook = function_exists( 'owc_board_notebook_get' )
		? owc_board_notebook_get( $group, $user->user_email, $can_write )
		: null;

	if ( ! $notebook ) {
		wp_send_json_success( [
			'notebook_id'     => 0,
			'role_path'       => $group,
			'content'         => '',
			'updated_by_name' => '',
			'updated_at'      => '',
			'updated_ago'     => '',
			'can_write'       => $can_write,
			'empty'           => true,
		] );
	}

	$nb              = (array) $notebook;
	$updated_email   = isset( $nb['updated_by_email'] ) ? $nb['updated_by_email'] : '';
	$updated_user    = $updated_email ? get_user_by( 'email', $updated_email ) : null;
	$updated_by_name = $updated_user ? $updated_user->display_name : ( $updated_email ?: __( 'unknown', 'owbn-board' ) );

	wp_send_json_success( [
		'notebook_id'     => isset( $nb['id'] ) ? (int) $nb['id'] : 0,
		'role_path'       => $group,
		'content'         => isset( $nb['content'] ) ? $nb['content'] : '',
		'updated_by_name' => $updated_by_name,
		'updated_at'      => isset( $nb['updated_at'] ) ? $nb['updated_at'] : '',
		'updated_ago'     => isset( $nb['updated_at'] ) ? human_time_diff( strtotime( $nb['updated_at'] ), time() ) . ' ' . __( 'ago', 'owbn-board' ) : '',
		'can_write'       => $can_write,
		'empty'           => false,
	] );
}
