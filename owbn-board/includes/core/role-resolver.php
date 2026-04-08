<?php
/**
 * Role resolver — fetch user's accessSchema roles and match against tile patterns.
 *
 * Uses owbn-core's ASC wrappers when available. Falls back to an empty role set
 * if ASC is not present (tile won't be visible unless read_roles is empty).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get all ASC role paths for a user. Cached per-request.
 *
 * @param int $user_id
 * @return array Array of role path strings.
 */
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

/**
 * Check if any of the user's roles matches any of the given patterns.
 * Patterns support * as a single-segment wildcard.
 *
 * Pattern examples:
 *   chronicle/*\/cm         matches chronicle/mckn/cm, chronicle/wsr/cm
 *   exec/*                  matches exec/hc/coordinator, exec/archivist/coordinator
 *   chronicle/mckn/*        matches chronicle/mckn/cm, chronicle/mckn/hst
 *
 * @param array $user_roles Array of user's role paths
 * @param array $patterns   Array of role patterns to match against
 * @return bool
 */
function owbn_board_user_matches_any_pattern( array $user_roles, array $patterns ) {
	if ( empty( $patterns ) ) {
		return true; // No pattern restriction = visible to all
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

/**
 * Match a single role path against a pattern with * wildcards.
 * Uses fnmatch() for glob-style matching.
 *
 * @param string $pattern e.g. 'chronicle/*\/cm'
 * @param string $role    e.g. 'chronicle/mckn/cm'
 * @return bool
 */
function owbn_board_pattern_matches( $pattern, $role ) {
	$pattern = (string) $pattern;
	$role    = (string) $role;

	if ( '' === $pattern || '' === $role ) {
		return false;
	}

	// Exact match
	if ( $pattern === $role ) {
		return true;
	}

	// Escape regex metachars, then convert * to [^/]+ for single-segment wildcard
	// Example: chronicle/*/cm -> chronicle/[^/]+/cm
	$regex = preg_quote( $pattern, '#' );
	$regex = str_replace( '\*', '[^/]+', $regex );

	return (bool) preg_match( '#^' . $regex . '$#', $role );
}
