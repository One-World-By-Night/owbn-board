<?php
/**
 * Per-user tile state — collapse, pin, snooze, dismiss.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get all tile states for a user as [tile_id => [state, snooze_until]].
 * Batched into a single query for all tiles.
 *
 * @param int $user_id
 * @return array
 */
function owbn_board_get_user_tile_states( $user_id ) {
	global $wpdb;

	$user_id = absint( $user_id );
	if ( ! $user_id ) {
		return [];
	}

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT tile_id, state, snooze_until FROM {$wpdb->prefix}owbn_board_tile_state WHERE user_id = %d",
			$user_id
		),
		ARRAY_A
	);

	$state = [];
	foreach ( (array) $rows as $row ) {
		$state[ $row['tile_id'] ] = [
			'state'        => $row['state'],
			'snooze_until' => $row['snooze_until'],
		];
	}
	return $state;
}

/**
 * Set a single tile's state for a user.
 *
 * @param int    $user_id
 * @param string $tile_id
 * @param string $state        default, collapsed, pinned, snoozed, dismissed
 * @param string $snooze_until DATETIME string or null
 * @return bool
 */
function owbn_board_set_user_tile_state( $user_id, $tile_id, $state, $snooze_until = null ) {
	global $wpdb;

	$allowed_states = [ 'default', 'collapsed', 'pinned', 'snoozed', 'dismissed' ];
	if ( ! in_array( $state, $allowed_states, true ) ) {
		return false;
	}

	$result = $wpdb->replace(
		$wpdb->prefix . 'owbn_board_tile_state',
		[
			'user_id'      => absint( $user_id ),
			'tile_id'      => sanitize_text_field( $tile_id ),
			'state'        => $state,
			'snooze_until' => $snooze_until,
			'updated_at'   => current_time( 'mysql' ),
		],
		[ '%d', '%s', '%s', '%s', '%s' ]
	);

	return false !== $result;
}

/**
 * Reset all tile states for a user.
 */
function owbn_board_reset_user_tile_states( $user_id ) {
	global $wpdb;
	return false !== $wpdb->delete(
		$wpdb->prefix . 'owbn_board_tile_state',
		[ 'user_id' => absint( $user_id ) ],
		[ '%d' ]
	);
}

/**
 * Per-user tile size overrides. Stored as a single user_meta value
 * (array keyed by tile_id) to avoid fanning out into N meta rows.
 */
function owbn_board_get_user_tile_sizes( $user_id ) {
	$user_id = absint( $user_id );
	if ( ! $user_id ) {
		return [];
	}
	$sizes = get_user_meta( $user_id, 'owbn_board_tile_sizes', true );
	return is_array( $sizes ) ? $sizes : [];
}

function owbn_board_set_user_tile_size( $user_id, $tile_id, $size ) {
	$user_id = absint( $user_id );
	$tile_id = sanitize_text_field( $tile_id );
	if ( ! $user_id || '' === $tile_id ) {
		return false;
	}
	if ( ! in_array( $size, owbn_board_allowed_sizes(), true ) ) {
		return false;
	}
	$sizes             = owbn_board_get_user_tile_sizes( $user_id );
	$sizes[ $tile_id ] = $size;
	return (bool) update_user_meta( $user_id, 'owbn_board_tile_sizes', $sizes );
}

function owbn_board_clear_user_tile_size( $user_id, $tile_id ) {
	$user_id = absint( $user_id );
	$tile_id = sanitize_text_field( $tile_id );
	if ( ! $user_id || '' === $tile_id ) {
		return false;
	}
	$sizes = owbn_board_get_user_tile_sizes( $user_id );
	unset( $sizes[ $tile_id ] );
	return (bool) update_user_meta( $user_id, 'owbn_board_tile_sizes', $sizes );
}
