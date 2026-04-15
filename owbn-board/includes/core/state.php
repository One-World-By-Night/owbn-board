<?php
/**
 * Per-user tile state, size, order. Cross-site via owc_board_state/prefs wrappers.
 * Function signatures still take WP user_id for backward compat — internally the
 * user_id is mapped to email (the cross-site identity key).
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_user_email( $user_id ) {
	$user_id = absint( $user_id );
	if ( ! $user_id ) {
		return '';
	}
	$user = get_userdata( $user_id );
	return $user ? $user->user_email : '';
}

function owbn_board_get_user_tile_states( $user_id ) {
	$email = owbn_board_user_email( $user_id );
	if ( '' === $email ) {
		return [];
	}
	if ( function_exists( 'owc_board_state_get' ) ) {
		return owc_board_state_get( $email );
	}
	return [];
}

function owbn_board_set_user_tile_state( $user_id, $tile_id, $state, $snooze_until = null ) {
	$email = owbn_board_user_email( $user_id );
	if ( '' === $email ) {
		return false;
	}
	if ( function_exists( 'owc_board_state_set' ) ) {
		return owc_board_state_set( $email, $tile_id, $state, $snooze_until );
	}
	return false;
}

function owbn_board_reset_user_tile_states( $user_id ) {
	// Single-pass reset: set every tile back to 'default'. Bulk endpoint not implemented.
	$email = owbn_board_user_email( $user_id );
	if ( '' === $email ) {
		return false;
	}
	$current = function_exists( 'owc_board_state_get' ) ? owc_board_state_get( $email ) : [];
	foreach ( (array) $current as $tile_id => $row ) {
		owc_board_state_set( $email, $tile_id, 'default', null );
	}
	return true;
}

function owbn_board_get_user_tile_sizes( $user_id ) {
	$email = owbn_board_user_email( $user_id );
	if ( '' === $email ) {
		return [];
	}
	if ( function_exists( 'owc_board_prefs_get' ) ) {
		$prefs = owc_board_prefs_get( $email );
		return isset( $prefs['sizes'] ) ? (array) $prefs['sizes'] : [];
	}
	return [];
}

function owbn_board_set_user_tile_size( $user_id, $tile_id, $size ) {
	$email = owbn_board_user_email( $user_id );
	if ( '' === $email || '' === $tile_id ) {
		return false;
	}
	if ( ! in_array( $size, owbn_board_allowed_sizes(), true ) ) {
		return false;
	}
	$sizes             = owbn_board_get_user_tile_sizes( $user_id );
	$sizes[ $tile_id ] = $size;
	if ( function_exists( 'owc_board_prefs_set' ) ) {
		return owc_board_prefs_set( $email, 'sizes', $sizes );
	}
	return false;
}

function owbn_board_clear_user_tile_size( $user_id, $tile_id ) {
	$email = owbn_board_user_email( $user_id );
	if ( '' === $email || '' === $tile_id ) {
		return false;
	}
	$sizes = owbn_board_get_user_tile_sizes( $user_id );
	unset( $sizes[ $tile_id ] );
	if ( function_exists( 'owc_board_prefs_set' ) ) {
		return owc_board_prefs_set( $email, 'sizes', $sizes );
	}
	return false;
}

function owbn_board_get_user_tile_order( $user_id ) {
	$email = owbn_board_user_email( $user_id );
	if ( '' === $email ) {
		return [];
	}
	if ( function_exists( 'owc_board_prefs_get' ) ) {
		$prefs = owc_board_prefs_get( $email );
		return isset( $prefs['order'] ) ? array_values( array_filter( (array) $prefs['order'], 'is_string' ) ) : [];
	}
	return [];
}

function owbn_board_set_user_tile_order( $user_id, array $tile_ids ) {
	$email = owbn_board_user_email( $user_id );
	if ( '' === $email ) {
		return false;
	}
	$clean = [];
	foreach ( $tile_ids as $id ) {
		$id = sanitize_text_field( (string) $id );
		if ( '' !== $id && ! in_array( $id, $clean, true ) ) {
			$clean[] = $id;
		}
	}
	if ( function_exists( 'owc_board_prefs_set' ) ) {
		return owc_board_prefs_set( $email, 'order', $clean );
	}
	return false;
}
