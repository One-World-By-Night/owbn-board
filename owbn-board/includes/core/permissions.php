<?php
/**
 * Tile permission helpers. Layout overrides are read here (not in tile-access/)
 * so saved overrides still enforce when the tile-access module is disabled.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_tile_effective_read_roles( array $tile ) {
	$layout = owbn_board_get_site_layout();
	$entry  = isset( $layout['tiles'][ $tile['id'] ] ) ? $layout['tiles'][ $tile['id'] ] : null;
	if ( $entry && isset( $entry['read_roles'] ) && is_array( $entry['read_roles'] ) ) {
		return $entry['read_roles'];
	}
	return (array) ( $tile['read_roles'] ?? [] );
}

function owbn_board_tile_effective_write_roles( array $tile ) {
	$layout = owbn_board_get_site_layout();
	$entry  = isset( $layout['tiles'][ $tile['id'] ] ) ? $layout['tiles'][ $tile['id'] ] : null;
	if ( $entry && isset( $entry['write_roles'] ) && is_array( $entry['write_roles'] ) ) {
		return $entry['write_roles'];
	}
	$write = (array) ( $tile['write_roles'] ?? [] );
	if ( empty( $write ) ) {
		return owbn_board_tile_effective_read_roles( $tile );
	}
	return $write;
}

function owbn_board_user_can_read_tile( array $tile, $user_id ) {
	$read_roles = owbn_board_tile_effective_read_roles( $tile );
	if ( empty( $read_roles ) ) {
		return true;
	}
	$roles = owbn_board_get_user_roles( $user_id );
	return owbn_board_user_matches_any_pattern( $roles, $read_roles );
}

function owbn_board_user_can_write_tile( array $tile, $user_id ) {
	$write_roles = owbn_board_tile_effective_write_roles( $tile );
	if ( empty( $write_roles ) ) {
		return true;
	}
	$roles = owbn_board_get_user_roles( $user_id );
	return owbn_board_user_matches_any_pattern( $roles, $write_roles );
}

function owbn_board_user_can_manage() {
	return current_user_can( 'manage_options' );
}
