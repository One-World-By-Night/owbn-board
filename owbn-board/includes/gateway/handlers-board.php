<?php
/**
 * Board gateway handlers. Thin layer that parses REST request body, calls
 * the matching owbn_board_local_* function, and returns a structured response.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_gateway_messages_list( $request ) {
	$body  = $request->get_json_params();
	$scope = isset( $body['scope'] ) ? sanitize_text_field( $body['scope'] ) : '';
	$limit = isset( $body['limit'] ) ? max( 1, (int) $body['limit'] ) : 20;
	if ( '' === $scope ) {
		return new WP_Error( 'missing_scope', 'scope required', [ 'status' => 400 ] );
	}
	return owbn_board_gateway_respond( [ 'messages' => owbn_board_local_messages_list( $scope, $limit ) ] );
}

function owbn_board_gateway_messages_post( $request ) {
	$body    = $request->get_json_params();
	$scope   = isset( $body['scope'] ) ? sanitize_text_field( $body['scope'] ) : '';
	$email   = isset( $body['email'] ) ? sanitize_email( $body['email'] ) : '';
	$content = isset( $body['content'] ) ? wp_strip_all_tags( $body['content'] ) : '';
	$result  = owbn_board_local_message_post( $scope, $email, $content );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	return owbn_board_gateway_respond( [ 'message' => $result ] );
}

function owbn_board_gateway_messages_delete( $request ) {
	$body  = $request->get_json_params();
	$id    = isset( $body['message_id'] ) ? (int) $body['message_id'] : 0;
	$email = isset( $body['email'] ) ? sanitize_email( $body['email'] ) : '';
	$ok    = owbn_board_local_message_delete( $id, $email );
	return owbn_board_gateway_respond( [ 'deleted' => $ok ] );
}

function owbn_board_gateway_notebook_get( $request ) {
	$body   = $request->get_json_params();
	$scope  = isset( $body['scope'] ) ? sanitize_text_field( $body['scope'] ) : '';
	$email  = isset( $body['email'] ) ? sanitize_email( $body['email'] ) : '';
	$create = ! empty( $body['create'] );
	return owbn_board_gateway_respond( [ 'notebook' => owbn_board_local_notebook_get( $scope, $email, $create ) ] );
}

function owbn_board_gateway_notebook_save( $request ) {
	$body    = $request->get_json_params();
	$scope   = isset( $body['scope'] ) ? sanitize_text_field( $body['scope'] ) : '';
	$email   = isset( $body['email'] ) ? sanitize_email( $body['email'] ) : '';
	$content = isset( $body['content'] ) ? wp_kses_post( $body['content'] ) : '';
	$ok      = owbn_board_local_notebook_save( $scope, $email, $content );
	return owbn_board_gateway_respond( [ 'saved' => $ok ] );
}

function owbn_board_gateway_handoff_get( $request ) {
	$body  = $request->get_json_params();
	$scope = isset( $body['scope'] ) ? sanitize_text_field( $body['scope'] ) : '';
	return owbn_board_gateway_respond( [ 'handoff' => owbn_board_local_handoff_get( $scope ) ] );
}

function owbn_board_gateway_handoff_entries( $request ) {
	$body       = $request->get_json_params();
	$handoff_id = isset( $body['handoff_id'] ) ? (int) $body['handoff_id'] : 0;
	$limit      = isset( $body['limit'] ) ? max( 1, (int) $body['limit'] ) : 5;
	return owbn_board_gateway_respond( [ 'entries' => owbn_board_local_handoff_recent_entries( $handoff_id, $limit ) ] );
}

function owbn_board_gateway_sessions_list( $request ) {
	$body = $request->get_json_params();
	$slug = isset( $body['chronicle_slug'] ) ? sanitize_text_field( $body['chronicle_slug'] ) : '';
	$limit = isset( $body['limit'] ) ? max( 1, (int) $body['limit'] ) : 5;
	return owbn_board_gateway_respond( [ 'sessions' => owbn_board_local_sessions_list( $slug, $limit ) ] );
}

function owbn_board_gateway_visitors_list( $request ) {
	$body = $request->get_json_params();
	$slug = isset( $body['host_slug'] ) ? sanitize_text_field( $body['host_slug'] ) : '';
	$limit = isset( $body['limit'] ) ? max( 1, (int) $body['limit'] ) : 10;
	return owbn_board_gateway_respond( [ 'visitors' => owbn_board_local_visitors_list( $slug, $limit ) ] );
}

function owbn_board_gateway_visitors_by_player( $request ) {
	$body  = $request->get_json_params();
	$email = isset( $body['email'] ) ? sanitize_email( $body['email'] ) : '';
	$limit = isset( $body['limit'] ) ? max( 1, (int) $body['limit'] ) : 10;
	return owbn_board_gateway_respond( [ 'visitors' => owbn_board_local_visitors_by_player( $email, $limit ) ] );
}

function owbn_board_gateway_state_get( $request ) {
	$body  = $request->get_json_params();
	$email = isset( $body['email'] ) ? sanitize_email( $body['email'] ) : '';
	return owbn_board_gateway_respond( [ 'state' => owbn_board_local_state_get( $email ) ] );
}

function owbn_board_gateway_state_set( $request ) {
	$body         = $request->get_json_params();
	$email        = isset( $body['email'] ) ? sanitize_email( $body['email'] ) : '';
	$tile_id      = isset( $body['tile_id'] ) ? sanitize_text_field( $body['tile_id'] ) : '';
	$state        = isset( $body['state'] ) ? sanitize_text_field( $body['state'] ) : '';
	$snooze_until = isset( $body['snooze_until'] ) ? sanitize_text_field( $body['snooze_until'] ) : null;
	$ok           = owbn_board_local_state_set( $email, $tile_id, $state, $snooze_until );
	return owbn_board_gateway_respond( [ 'saved' => $ok ] );
}

function owbn_board_gateway_prefs_get( $request ) {
	$body  = $request->get_json_params();
	$email = isset( $body['email'] ) ? sanitize_email( $body['email'] ) : '';
	return owbn_board_gateway_respond( owbn_board_local_prefs_get( $email ) );
}

function owbn_board_gateway_prefs_set( $request ) {
	$body  = $request->get_json_params();
	$email = isset( $body['email'] ) ? sanitize_email( $body['email'] ) : '';
	$key   = isset( $body['key'] ) ? sanitize_text_field( $body['key'] ) : '';
	$value = isset( $body['value'] ) ? $body['value'] : null;
	$ok    = owbn_board_local_prefs_set( $email, $key, $value );
	return owbn_board_gateway_respond( [ 'saved' => $ok ] );
}

function owbn_board_gateway_audit_log( $request ) {
	$body         = $request->get_json_params();
	$email        = isset( $body['email'] ) ? sanitize_email( $body['email'] ) : '';
	$action       = isset( $body['action'] ) ? sanitize_text_field( $body['action'] ) : '';
	$subject_type = isset( $body['subject_type'] ) ? sanitize_text_field( $body['subject_type'] ) : '';
	$subject_id   = isset( $body['subject_id'] ) ? (int) $body['subject_id'] : 0;
	$details      = isset( $body['details'] ) ? (array) $body['details'] : [];
	$ok           = owbn_board_local_audit_log( $email, $action, $subject_type, $subject_id, $details );
	return owbn_board_gateway_respond( [ 'logged' => $ok ] );
}

function owbn_board_gateway_respond( $data ) {
	if ( function_exists( 'owbn_gateway_respond' ) ) {
		return owbn_gateway_respond( $data );
	}
	return new WP_REST_Response( $data, 200 );
}
