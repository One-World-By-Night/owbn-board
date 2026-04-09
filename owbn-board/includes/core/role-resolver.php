<?php
/**
 * Fetches user ASC roles via owbn-core and matches them against tile patterns.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_get_user_roles( $user_id ) {
	static $cache = [];

	$user_id = absint( $user_id );
	if ( ! $user_id ) {
		return [];
	}

	if ( isset( $cache[ $user_id ] ) ) {
		return $cache[ $user_id ];
	}

	$roles = [];

	if ( function_exists( 'owc_asc_get_user_roles' ) ) {
		$user = get_userdata( $user_id );
		if ( $user && $user->user_email ) {
			$result = owc_asc_get_user_roles( $user->user_email );
			if ( is_array( $result ) ) {
				$roles = $result;
			}
		}
	}

	$cache[ $user_id ] = $roles;
	return $roles;
}

function owbn_board_user_matches_any_pattern( array $user_roles, array $patterns ) {
	if ( empty( $patterns ) ) {
		return true;
	}
	if ( empty( $user_roles ) ) {
		return false;
	}

	foreach ( $patterns as $pattern ) {
		foreach ( $user_roles as $role ) {
			if ( owbn_board_pattern_matches( $pattern, $role ) ) {
				return true;
			}
		}
	}
	return false;
}

// Glob-style match: * is a single-segment wildcard.
function owbn_board_pattern_matches( $pattern, $role ) {
	$pattern = (string) $pattern;
	$role    = (string) $role;

	if ( '' === $pattern || '' === $role ) {
		return false;
	}

	if ( $pattern === $role ) {
		return true;
	}

	$regex = preg_quote( $pattern, '#' );
	$regex = str_replace( '\*', '[^/]+', $regex );

	return (bool) preg_match( '#^' . $regex . '$#', $role );
}
