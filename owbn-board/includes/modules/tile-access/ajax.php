<?php
/**
 * Tile Access admin save. Writes per-tile overrides into the layout option
 * without disturbing other layout fields.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_tile_access_ajax_save() {
	if ( ! check_ajax_referer( 'owbn_board', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
	}
	if ( ! owbn_board_user_can_manage() ) {
		wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
	}

	$tile_id = isset( $_POST['tile_id'] ) ? sanitize_text_field( wp_unslash( $_POST['tile_id'] ) ) : '';
	if ( '' === $tile_id ) {
		wp_send_json_error( [ 'message' => 'Missing tile_id' ], 400 );
	}

	// Field: string (textarea), "__reset__" to clear, or absent to leave as-is.
	$raw_read  = isset( $_POST['read_roles'] )  ? wp_unslash( $_POST['read_roles'] )  : null;
	$raw_write = isset( $_POST['write_roles'] ) ? wp_unslash( $_POST['write_roles'] ) : null;
	$raw_share = isset( $_POST['share_level'] ) ? wp_unslash( $_POST['share_level'] ) : null;

	$current = owbn_board_tile_access_get_config( $tile_id );

	$read_roles = $current['has_read_override']
		? $current['read_roles']
		: null;
	$write_roles = $current['has_write_override']
		? $current['write_roles']
		: null;
	$share_level = $current['has_share_override']
		? $current['share_level']
		: null;

	if ( null !== $raw_read ) {
		$read_roles = ( '__reset__' === $raw_read ) ? null : owbn_board_tile_access_sanitize_patterns( $raw_read );
	}
	if ( null !== $raw_write ) {
		$write_roles = ( '__reset__' === $raw_write ) ? null : owbn_board_tile_access_sanitize_patterns( $raw_write );
	}
	if ( null !== $raw_share ) {
		$tile = owbn_board_get_tile( $tile_id );
		if ( ! $tile || empty( $tile['supports_share_level'] ) ) {
			wp_send_json_error( [ 'message' => 'Tile does not support share_level' ], 400 );
		}
		$share_level = ( '__reset__' === $raw_share ) ? null : owbn_board_tile_access_sanitize_patterns( $raw_share );
	}

	$saved = owbn_board_tile_access_save_config( $tile_id, $read_roles, $write_roles, $share_level );

	// update_option returns false on no-change; that's fine, not an error.

	if ( function_exists( 'owbn_board_audit' ) ) {
		owbn_board_audit(
			get_current_user_id(),
			'tile_access.save',
			'tile',
			0,
			[
				'tile_id'     => $tile_id,
				'has_read'    => null !== $read_roles,
				'has_write'   => null !== $write_roles,
				'has_share'   => null !== $share_level,
			]
		);
	}

	wp_send_json_success( [
		'tile_id'     => $tile_id,
		'config'      => owbn_board_tile_access_get_config( $tile_id ),
	] );
}
