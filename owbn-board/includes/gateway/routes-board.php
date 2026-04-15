<?php
/**
 * Board gateway routes. Registered on every site, but only meaningful on chronicles
 * (the canonical host where the data lives). Other sites' routes resolve against
 * empty tables — never called in normal operation since wrappers route to chronicles.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', 'owbn_board_register_gateway_routes' );

function owbn_board_register_gateway_routes() {
	if ( ! get_option( 'owbn_gateway_enabled', false ) ) {
		return;
	}
	if ( ! function_exists( 'owbn_gateway_authenticate' ) ) {
		return;
	}
	$ns   = 'owbn/v1';
	$auth = 'owbn_gateway_authenticate';

	$routes = [
		'board/messages/list'    => 'owbn_board_gateway_messages_list',
		'board/messages/post'    => 'owbn_board_gateway_messages_post',
		'board/messages/delete'  => 'owbn_board_gateway_messages_delete',
		'board/notebook/get'     => 'owbn_board_gateway_notebook_get',
		'board/notebook/save'    => 'owbn_board_gateway_notebook_save',
		'board/handoff/get'      => 'owbn_board_gateway_handoff_get',
		'board/handoff/entries'  => 'owbn_board_gateway_handoff_entries',
		'board/sessions/list'    => 'owbn_board_gateway_sessions_list',
		'board/visitors/list'    => 'owbn_board_gateway_visitors_list',
		'board/visitors/by-player' => 'owbn_board_gateway_visitors_by_player',
		'board/state/get'        => 'owbn_board_gateway_state_get',
		'board/state/set'        => 'owbn_board_gateway_state_set',
		'board/prefs/get'        => 'owbn_board_gateway_prefs_get',
		'board/prefs/set'        => 'owbn_board_gateway_prefs_set',
		'board/audit/log'        => 'owbn_board_gateway_audit_log',
	];

	foreach ( $routes as $path => $callback ) {
		register_rest_route( $ns, '/' . $path, [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => $callback,
			'permission_callback' => $auth,
		] );
	}
}

