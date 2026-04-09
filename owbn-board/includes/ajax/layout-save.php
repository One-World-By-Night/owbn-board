<?php
/**
 * Layout save AJAX. Merges incoming shape fields (enabled/size/priority) onto
 * the current layout so tile-access overrides (read/write/share) aren't wiped.
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
	$incoming   = json_decode( $layout_raw, true );

	if ( ! is_array( $incoming ) ) {
		wp_send_json_error( [ 'message' => 'Invalid layout payload' ], 400 );
	}

	$current = owbn_board_get_site_layout();

	foreach ( [ 'url_path', 'is_front_page', 'layout_mode', 'header_html' ] as $top_key ) {
		if ( array_key_exists( $top_key, $incoming ) ) {
			$current[ $top_key ] = $incoming[ $top_key ];
		}
	}

	if ( isset( $incoming['tiles'] ) && is_array( $incoming['tiles'] ) ) {
		foreach ( $incoming['tiles'] as $tile_id => $incoming_entry ) {
			$existing_entry = isset( $current['tiles'][ $tile_id ] ) && is_array( $current['tiles'][ $tile_id ] )
				? $current['tiles'][ $tile_id ]
				: [];

			$existing_entry['enabled']  = ! empty( $incoming_entry['enabled'] );
			$existing_entry['size']     = isset( $incoming_entry['size'] ) ? $incoming_entry['size'] : '1x1';
			$existing_entry['priority'] = isset( $incoming_entry['priority'] ) ? (int) $incoming_entry['priority'] : 10;

			if ( isset( $incoming_entry['category'] ) ) {
				$existing_entry['category'] = $incoming_entry['category'];
			}

			$current['tiles'][ $tile_id ] = $existing_entry;
		}
	}

	$saved = owbn_board_save_site_layout( $current );
	owbn_board_audit( get_current_user_id(), 'layout.save', 'layout', 0, [ 'tile_count' => count( $current['tiles'] ) ] );

	wp_send_json_success( [ 'saved' => $saved ] );
}
