<?php
/**
 * AJAX: render the body of one tab panel on demand.
 *
 * The initial page load only renders the default-active Links tab. Other tabs
 * arrive here when the user clicks them. Result is cached client-side so
 * subsequent clicks toggle visibility without refetching.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_owbn_board_load_tab', 'owbn_board_ajax_load_tab' );

function owbn_board_ajax_load_tab() {
	if ( ! check_ajax_referer( 'owbn_board', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
	}

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( array( 'message' => 'Not logged in' ), 401 );
	}

	$tab_key = isset( $_POST['tab'] ) ? sanitize_text_field( wp_unslash( $_POST['tab'] ) ) : '';
	if ( ! in_array( $tab_key, owbn_board_allowed_tabs(), true ) ) {
		wp_send_json_error( array( 'message' => 'Invalid tab' ), 400 );
	}

	// Re-run visibility filtering so a user can't request a tab they shouldn't
	// see (e.g. C&C without matching roles).
	$site_slug = owbn_board_get_site_slug();
	$tiles     = owbn_board_get_visible_tiles( $user_id, $site_slug );

	$panel_tiles = array();
	foreach ( $tiles as $tile ) {
		$tile_tab = isset( $tile['tab'] ) && in_array( $tile['tab'], owbn_board_allowed_tabs(), true )
			? $tile['tab']
			: 'data';
		if ( $tile_tab === $tab_key ) {
			$panel_tiles[] = $tile;
		}
	}

	if ( 'cc' === $tab_key ) {
		$cc_eligible = ! empty( $panel_tiles );
		if ( ! $cc_eligible && function_exists( 'owc_workspace_user_is_cc_eligible' ) ) {
			$cc_eligible = owc_workspace_user_is_cc_eligible( $user_id );
		}
		if ( ! $cc_eligible ) {
			wp_send_json_error( array( 'message' => 'Not eligible' ), 403 );
		}
	}

	$html = owbn_board_render_tab_panel( $tab_key, $panel_tiles, $user_id );
	wp_send_json_success( array( 'html' => $html ) );
}
