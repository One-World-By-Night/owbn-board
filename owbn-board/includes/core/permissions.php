<?php
/**
 * Tile permission helpers — read vs write checks.
 *
 * Effective roles come from the layout option first (admin override via
 * the tile-access module), then fall back to the tile's registered values.
 * This keeps saved overrides working even when the tile-access module is
 * disabled — the module only owns the editor UI, not the enforcement path.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get the effective read_roles for a tile on this site. Layout override
 * wins if set; otherwise the tile's registered read_roles are used.
 *
 * @param array $tile
 * @return array
 */
function owbn_board_tile_effective_read_roles( array $tile ) {
	$layout = owbn_board_get_site_layout();
	$entry  = isset( $layout['tiles'][ $tile['id'] ] ) ? $layout['tiles'][ $tile['id'] ] : null;
	if ( $entry && isset( $entry['read_roles'] ) && is_array( $entry['read_roles'] ) ) {
		return $entry['read_roles'];
	}
	return (array) ( $tile['read_roles'] ?? [] );
}

/**
 * Get the effective write_roles for a tile on this site. Layout override
 * wins if set; otherwise the tile's registered write_roles are used,
 * falling back to read_roles when write_roles is empty (matches the
 * behavior of the tile registry default).
 *
 * @param array $tile
 * @return array
 */
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

/**
 * Can a user read a tile?
 *
 * @param array $tile     Tile definition
 * @param int   $user_id
 * @return bool
 */
function owbn_board_user_can_read_tile( array $tile, $user_id ) {
	$read_roles = owbn_board_tile_effective_read_roles( $tile );
	if ( empty( $read_roles ) ) {
		return true;
	}
	$roles = owbn_board_get_user_roles( $user_id );
	return owbn_board_user_matches_any_pattern( $roles, $read_roles );
}

/**
 * Can a user write (interact with) a tile?
 *
 * @param array $tile     Tile definition
 * @param int   $user_id
 * @return bool
 */
function owbn_board_user_can_write_tile( array $tile, $user_id ) {
	$write_roles = owbn_board_tile_effective_write_roles( $tile );
	if ( empty( $write_roles ) ) {
		return true;
	}
	$roles = owbn_board_get_user_roles( $user_id );
	return owbn_board_user_matches_any_pattern( $roles, $write_roles );
}

/**
 * Admin capability for layout and settings pages.
 */
function owbn_board_user_can_manage() {
	return current_user_can( 'manage_options' );
}
