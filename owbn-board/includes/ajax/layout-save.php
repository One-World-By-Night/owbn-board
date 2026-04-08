<?php
/**
 * AJAX: save site layout (admin drag-and-drop, enable/disable).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_owbn_board_layout_save', 'owbn_board_ajax_layout_save' );

function owbn_board_ajax_layout_save() {
	if ( ! check_ajax_referer( 'owbn_board', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
	}
	if ( ! owbn_board_user_can_manage() ) {
		wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
	}

	$layout_raw = isset( $_POST['layout'] ) ? wp_unslash( $_POST['layout'] ) : '';
	$layout     = json_decode( $layout_raw, true );

	if ( ! is_array( $layout ) ) {
		wp_send_json_error( [ 'message' => 'Invalid layout payload' ], 400 );
	}

	$saved = owbn_board_save_site_layout( $layout );
	owbn_board_audit( get_current_user_id(), 'layout.save', 'layout', 0, [ 'tile_count' => count( $layout['tiles'] ?? [] ) ] );

	wp_send_json_success( [ 'saved' => $saved ] );
}
