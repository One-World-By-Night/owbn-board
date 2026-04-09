<?php
/**
 * Tile Access — per-tile read/write role and share-level overrides.
 *
 * Storage: extends the existing owbn_board_layout option. Each tile entry
 * can carry three extra keys:
 *
 *   read_roles   array   Patterns that override the tile's registered read_roles
 *   write_roles  array   Patterns that override the tile's registered write_roles
 *   share_level  array   Patterns that define how the tile's content is scoped
 *                         into groups for multi-group users
 *
 * All three keys are optional. When absent, the tile uses its registered
 * values. When present (even as an empty array), the admin override is the
 * authority.
 *
 * NOTE: effective read/write role resolution lives in includes/core/permissions.php
 * so that core permission checks work even when this module is disabled (saved
 * overrides still take effect; only the admin editor disappears).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fetch the per-tile access config from the layout option.
 * Returns an array with keys: read_roles, write_roles, share_level,
 * has_read_override, has_write_override, has_share_override.
 *
 * When no override is saved, read_roles / write_roles fall back to the
 * tile's registered values. share_level falls back to an empty array.
 *
 * @param string $tile_id
 * @return array
 */
function owbn_board_tile_access_get_config( $tile_id ) {
	$tile   = owbn_board_get_tile( $tile_id );
	$layout = owbn_board_get_site_layout();
	$entry  = isset( $layout['tiles'][ $tile_id ] ) ? $layout['tiles'][ $tile_id ] : [];

	$has_read  = isset( $entry['read_roles'] )  && is_array( $entry['read_roles'] );
	$has_write = isset( $entry['write_roles'] ) && is_array( $entry['write_roles'] );
	$has_share = isset( $entry['share_level'] ) && is_array( $entry['share_level'] );

	return [
		'read_roles'         => $has_read  ? $entry['read_roles']  : (array) ( $tile['read_roles']  ?? [] ),
		'write_roles'        => $has_write ? $entry['write_roles'] : (array) ( $tile['write_roles'] ?? [] ),
		'share_level'        => $has_share ? $entry['share_level'] : [],
		'has_read_override'  => $has_read,
		'has_write_override' => $has_write,
		'has_share_override' => $has_share,
		'supports_share'     => ! empty( $tile['supports_share_level'] ),
	];
}

/**
 * Save access config for a single tile. Writes into the existing layout
 * option without clobbering other layout keys.
 *
 * Pass null for any of the three arrays to CLEAR that override (reverts
 * to the tile's registered values).
 *
 * @param string     $tile_id
 * @param array|null $read_roles
 * @param array|null $write_roles
 * @param array|null $share_level
 * @return bool
 */
function owbn_board_tile_access_save_config( $tile_id, $read_roles, $write_roles, $share_level ) {
	$tile_id = sanitize_text_field( $tile_id );
	if ( '' === $tile_id ) {
		return false;
	}

	$layout = owbn_board_get_site_layout();
	if ( ! isset( $layout['tiles'][ $tile_id ] ) || ! is_array( $layout['tiles'][ $tile_id ] ) ) {
		$layout['tiles'][ $tile_id ] = [];
	}

	$entry = $layout['tiles'][ $tile_id ];

	if ( null === $read_roles ) {
		unset( $entry['read_roles'] );
	} else {
		$entry['read_roles'] = owbn_board_tile_access_sanitize_patterns( $read_roles );
	}

	if ( null === $write_roles ) {
		unset( $entry['write_roles'] );
	} else {
		$entry['write_roles'] = owbn_board_tile_access_sanitize_patterns( $write_roles );
	}

	if ( null === $share_level ) {
		unset( $entry['share_level'] );
	} else {
		$entry['share_level'] = owbn_board_tile_access_sanitize_patterns( $share_level );
	}

	$layout['tiles'][ $tile_id ] = $entry;

	return owbn_board_save_site_layout( $layout );
}

/**
 * Sanitize an array of role patterns. Accepts array or newline/comma
 * separated string, trims whitespace, drops empties, keeps one per entry.
 *
 * @param mixed $input
 * @return array
 */
function owbn_board_tile_access_sanitize_patterns( $input ) {
	if ( is_string( $input ) ) {
		$input = preg_split( '/[\r\n,]+/', $input );
	}
	if ( ! is_array( $input ) ) {
		return [];
	}
	$out = [];
	foreach ( $input as $pattern ) {
		$pattern = trim( (string) $pattern );
		if ( '' === $pattern ) {
			continue;
		}
		// Strip anything that isn't a valid ASC path segment char or /, *, -, _
		$pattern = preg_replace( '#[^a-zA-Z0-9/_\-\*]#', '', $pattern );
		if ( '' === $pattern ) {
			continue;
		}
		$out[] = $pattern;
	}
	return array_values( array_unique( $out ) );
}

/**
 * Resolve the set of scope groups a user belongs to for a given tile's
 * share_level config.
 *
 * Returns an empty array if the tile has no share_level configured OR the
 * user has no matching role. Returns a unique, ordered list of group keys
 * (e.g. "chronicle/mckn", "coordinator/sabbat") when matches exist.
 *
 * A user with roles across multiple chronicles/coordinator positions will
 * get multiple groups returned — the tile decides how to display them.
 *
 * @param string $tile_id
 * @param int    $user_id
 * @return string[]
 */
function owbn_board_tile_access_resolve_scope( $tile_id, $user_id ) {
	$config = owbn_board_tile_access_get_config( $tile_id );
	if ( empty( $config['share_level'] ) ) {
		return [];
	}

	$user_roles = owbn_board_get_user_roles( $user_id );
	if ( empty( $user_roles ) ) {
		return [];
	}

	$groups = [];
	foreach ( $config['share_level'] as $pattern ) {
		foreach ( $user_roles as $role ) {
			if ( ! owbn_board_pattern_matches( $pattern, $role ) ) {
				continue;
			}
			$group = owbn_board_tile_access_derive_group( $pattern, $role );
			if ( '' !== $group && ! in_array( $group, $groups, true ) ) {
				$groups[] = $group;
			}
		}
	}

	return $groups;
}

/**
 * Derive the scope group identifier from a matched (pattern, role) pair.
 *
 * Algorithm: strip any trailing "*" segments from the pattern, then walk
 * the remaining segments and substitute any remaining "*" with the
 * corresponding segment from the role.
 *
 * Examples:
 *   chronicle/\*\/\*   matched by chronicle/mckn/hst  -> chronicle/mckn
 *   chronicle/mckn/\*  matched by chronicle/mckn/hst  -> chronicle/mckn
 *   exec/\*            matched by exec/hc/coordinator -> exec
 *   chronicle/\*\/hst  matched by chronicle/mckn/hst  -> chronicle/mckn/hst
 *
 * @param string $pattern
 * @param string $role
 * @return string
 */
function owbn_board_tile_access_derive_group( $pattern, $role ) {
	$pat_parts  = explode( '/', (string) $pattern );
	$role_parts = explode( '/', (string) $role );

	// Strip trailing wildcard segments.
	while ( ! empty( $pat_parts ) && end( $pat_parts ) === '*' ) {
		array_pop( $pat_parts );
	}

	// Substitute any remaining wildcards with the matching role segment.
	$group = [];
	foreach ( $pat_parts as $i => $seg ) {
		if ( '*' === $seg ) {
			$group[] = isset( $role_parts[ $i ] ) ? $role_parts[ $i ] : '';
		} else {
			$group[] = $seg;
		}
	}

	return implode( '/', array_filter( $group, 'strlen' ) );
}
