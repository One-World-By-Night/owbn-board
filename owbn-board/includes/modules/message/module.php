<?php
/**
 * Message — lightweight group chat scoped by ASC role path.
 * Table created in core activation.php.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/tiles.php';

owbn_board_register_module( [
	'id'          => 'message',
	'label'       => __( 'Group Messages', 'owbn-board' ),
	'description' => __( 'Lightweight group chat scoped by accessSchema role path', 'owbn-board' ),
	'version'     => '1.0.0',
	'default'     => true,
	'depends_on'  => [],
	'schema'      => null,
	'loader'      => 'owbn_board_message_init',
] );

function owbn_board_message_init() {
	add_action( 'owbn_board_register_tiles', 'owbn_board_message_register_tile' );
	add_action( 'wp_ajax_owbn_board_message_post', 'owbn_board_message_ajax_post' );
	add_action( 'wp_ajax_owbn_board_message_delete', 'owbn_board_message_ajax_delete' );
}

function owbn_board_message_ajax_post() {
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

	$content = isset( $_POST['content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['content'] ) ) : '';
	$scope   = isset( $_POST['role_path'] ) ? sanitize_text_field( wp_unslash( $_POST['role_path'] ) ) : '';

	if ( '' === trim( $content ) || '' === $scope ) {
		wp_send_json_error( [ 'message' => 'Missing content or scope' ], 400 );
	}
	if ( false !== strpos( $scope, '*' ) ) {
		wp_send_json_error( [ 'message' => 'scope must be a literal group key, not a pattern' ], 400 );
	}
	if ( strlen( $content ) > 2000 ) {
		wp_send_json_error( [ 'message' => 'Message too long' ], 400 );
	}

	$allowed_groups = owbn_board_message_resolve_scope_groups( $user_id );
	if ( ! in_array( $scope, $allowed_groups, true ) ) {
		wp_send_json_error( [ 'message' => 'Forbidden (scope)' ], 403 );
	}

	$result = function_exists( 'owc_board_messages_post' )
		? owc_board_messages_post( $scope, $user->user_email, $content )
		: new WP_Error( 'no_wrapper', 'owc_board_messages_post unavailable' );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( [ 'message' => $result->get_error_message() ], 500 );
	}

	$row = (array) $result;
	wp_send_json_success( [
		'id'           => isset( $row['id'] ) ? (int) $row['id'] : 0,
		'content'      => $content,
		'display_name' => $user->display_name,
		'time_label'   => __( 'just now', 'owbn-board' ),
		'can_delete'   => true,
	] );
}

function owbn_board_message_ajax_delete() {
	if ( ! check_ajax_referer( 'owbn_board', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
	}
	$user_id    = get_current_user_id();
	$message_id = isset( $_POST['message_id'] ) ? absint( $_POST['message_id'] ) : 0;
	if ( ! $user_id || ! $message_id ) {
		wp_send_json_error( [ 'message' => 'Missing user or message_id' ], 400 );
	}
	$user = get_userdata( $user_id );
	if ( ! $user || ! $user->user_email ) {
		wp_send_json_error( [ 'message' => 'No email on user' ], 400 );
	}

	$ok = function_exists( 'owc_board_messages_delete' )
		? owc_board_messages_delete( $message_id, $user->user_email )
		: false;

	if ( ! $ok ) {
		wp_send_json_error( [ 'message' => 'Delete failed or forbidden' ], 403 );
	}
	wp_send_json_success();
}
