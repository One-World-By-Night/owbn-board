<?php
/**
 * Pinned Links module — personal bookmarks stored in user meta.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/tiles.php';

owbn_board_register_module( [
	'id'          => 'pinned-links',
	'label'       => __( 'Pinned Links', 'owbn-board' ),
	'description' => __( 'Personal bookmarks stored per user', 'owbn-board' ),
	'version'     => '1.0.0',
	'default'     => true,
	'depends_on'  => [],
	'schema'      => null,
	'loader'      => 'owbn_board_pinned_links_init',
] );

function owbn_board_pinned_links_init() {
	add_action( 'owbn_board_register_tiles', 'owbn_board_pinned_links_register_tile' );
	add_action( 'wp_ajax_owbn_board_pin_add', 'owbn_board_pinned_links_ajax_add' );
	add_action( 'wp_ajax_owbn_board_pin_remove', 'owbn_board_pinned_links_ajax_remove' );
}

function owbn_board_pinned_links_get( $user_id ) {
	$links = get_user_meta( $user_id, 'owbn_board_pinned_links', true );
	return is_array( $links ) ? $links : [];
}

function owbn_board_pinned_links_ajax_add() {
	if ( ! check_ajax_referer( 'owbn_board', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
	}
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( [ 'message' => 'Not logged in' ], 401 );
	}

	$label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
	$url   = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

	if ( '' === $label || '' === $url ) {
		wp_send_json_error( [ 'message' => 'Missing label or url' ], 400 );
	}

	$links = owbn_board_pinned_links_get( $user_id );
	if ( count( $links ) >= 50 ) {
		wp_send_json_error( [ 'message' => 'Pin limit reached (50). Remove an existing pin before adding a new one.' ], 400 );
	}
	$links[] = [
		'id'    => wp_generate_uuid4(),
		'label' => $label,
		'url'   => $url,
		'added' => current_time( 'mysql' ),
	];

	update_user_meta( $user_id, 'owbn_board_pinned_links', $links );
	wp_send_json_success( [ 'links' => $links ] );
}

function owbn_board_pinned_links_ajax_remove() {
	if ( ! check_ajax_referer( 'owbn_board', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
	}
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( [ 'message' => 'Not logged in' ], 401 );
	}

	$pin_id = isset( $_POST['pin_id'] ) ? sanitize_text_field( wp_unslash( $_POST['pin_id'] ) ) : '';
	if ( '' === $pin_id ) {
		wp_send_json_error( [ 'message' => 'Missing pin_id' ], 400 );
	}

	$links = owbn_board_pinned_links_get( $user_id );
	$links = array_values( array_filter( $links, function ( $link ) use ( $pin_id ) {
		return ( $link['id'] ?? '' ) !== $pin_id;
	} ) );

	update_user_meta( $user_id, 'owbn_board_pinned_links', $links );
	wp_send_json_success( [ 'links' => $links ] );
}
