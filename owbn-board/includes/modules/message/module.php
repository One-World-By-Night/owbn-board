<?php
/**
 * Message module — lightweight group chat scoped by accessSchema role path.
 *
 * Uses the core owbn_board_messages table (created in activation.php).
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

/**
 * AJAX: post a new message to the user's group feed.
 */
function owbn_board_message_ajax_post() {
	if ( ! check_ajax_referer( 'owbn_board', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
	}

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( [ 'message' => 'Not logged in' ], 401 );
	}

	$content   = isset( $_POST['content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['content'] ) ) : '';
	$role_path = isset( $_POST['role_path'] ) ? sanitize_text_field( wp_unslash( $_POST['role_path'] ) ) : '';

	if ( '' === trim( $content ) || '' === $role_path ) {
		wp_send_json_error( [ 'message' => 'Missing content or role_path' ], 400 );
	}
	if ( false !== strpos( $role_path, '*' ) ) {
		wp_send_json_error( [ 'message' => 'role_path must be a literal group key, not a pattern' ], 400 );
	}
	if ( strlen( $content ) > 2000 ) {
		wp_send_json_error( [ 'message' => 'Message too long' ], 400 );
	}

	// Verify user belongs to the target role group
	$user_roles = owbn_board_get_user_roles( $user_id );
	if ( ! owbn_board_user_matches_any_pattern( $user_roles, [ $role_path ] ) ) {
		wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
	}

	global $wpdb;
	$wpdb->insert(
		$wpdb->prefix . 'owbn_board_messages',
		[
			'role_path'  => $role_path,
			'site_id'    => 0,
			'user_id'    => $user_id,
			'content'    => $content,
			'created_at' => current_time( 'mysql' ),
		],
		[ '%s', '%d', '%d', '%s', '%s' ]
	);

	owbn_board_audit( $user_id, 'message.post', 'message', (int) $wpdb->insert_id, [ 'role_path' => $role_path ] );

	wp_send_json_success( [
		'id'         => (int) $wpdb->insert_id,
		'created_at' => current_time( 'mysql' ),
	] );
}

/**
 * AJAX: soft-delete a message (the author or an admin can delete).
 */
function owbn_board_message_ajax_delete() {
	if ( ! check_ajax_referer( 'owbn_board', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
	}

	$user_id    = get_current_user_id();
	$message_id = isset( $_POST['message_id'] ) ? absint( $_POST['message_id'] ) : 0;
	if ( ! $message_id ) {
		wp_send_json_error( [ 'message' => 'Missing message_id' ], 400 );
	}

	global $wpdb;
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}owbn_board_messages WHERE id = %d",
			$message_id
		)
	);
	if ( ! $row ) {
		wp_send_json_error( [ 'message' => 'Not found' ], 404 );
	}

	$is_author = ( (int) $row->user_id === $user_id );
	$is_admin  = owbn_board_user_can_manage();

	if ( ! $is_author && ! $is_admin ) {
		wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
	}

	$wpdb->update(
		$wpdb->prefix . 'owbn_board_messages',
		[ 'deleted_at' => current_time( 'mysql' ) ],
		[ 'id' => $message_id ],
		[ '%s' ],
		[ '%d' ]
	);

	owbn_board_audit( $user_id, 'message.delete', 'message', $message_id );

	wp_send_json_success();
}
