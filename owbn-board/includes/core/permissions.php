<?php
/**
 * Tile permission helpers — read vs write checks.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Can a user read a tile?
 *
 * @param array $tile     Tile definition
 * @param int   $user_id
 * @return bool
 */
function owbn_board_user_can_read_tile( array $tile, $user_id ) {
	if ( empty( $tile['read_roles'] ) ) {
		return true;
	}
	$roles = owbn_board_get_user_roles( $user_id );
	return owbn_board_user_matches_any_pattern( $roles, $tile['read_roles'] );
}

/**
 * Can a user write (interact with) a tile?
 *
 * @param array $tile     Tile definition
 * @param int   $user_id
 * @return bool
 */
function owbn_board_user_can_write_tile( array $tile, $user_id ) {
	$write_roles = ! empty( $tile['write_roles'] ) ? $tile['write_roles'] : $tile['read_roles'];
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
