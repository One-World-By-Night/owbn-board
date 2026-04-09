<?php
/**
 * Tile Access — per-tile read/write/share overrides stored in owbn_board_layout.
 * Effective role resolution lives in core/permissions.php so saved overrides
 * still apply when this module is disabled.
 */

defined( 'ABSPATH' ) || exit;

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

function owbn_board_tile_access_save_config( $tile_id, $read_roles, $write_roles, $share_level ) {
	$tile_id = sanitize_text_field( $tile_id );
	if ( '' === $tile_id ) {
		return false;
	}

	$layout = owbn_board_get_site_layout();

	// Seed from registration when no prior entry exists, otherwise save_site_layout
	// would normalize an empty entry to enabled=false and silently disable the tile.
	if ( isset( $layout['tiles'][ $tile_id ] ) && is_array( $layout['tiles'][ $tile_id ] ) ) {
		$entry = $layout['tiles'][ $tile_id ];
	} else {
		$tile = owbn_board_get_tile( $tile_id );
		$entry = [
			'enabled'  => true,
			'size'     => $tile && ! empty( $tile['size'] ) ? $tile['size'] : '1x1',
			'priority' => $tile && isset( $tile['priority'] ) ? (int) $tile['priority'] : 10,
			'category' => $tile && ! empty( $tile['category'] ) ? $tile['category'] : 'general',
		];
	}

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
		$pattern = preg_replace( '#[^a-zA-Z0-9/_\-\*]#', '', $pattern );
		if ( '' === $pattern ) {
			continue;
		}
		$out[] = $pattern;
	}
	return array_values( array_unique( $out ) );
}

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

	// Alphabetical so groups[0] (the "default active") is stable across requests.
	sort( $groups, SORT_STRING );

	return $groups;
}

// Strip at most ONE trailing wildcard from the pattern, then substitute remaining
// wildcards with role segments. Stripping more than one would collapse distinct
// chronicles into one shared key — cross-chronicle data leak.
function owbn_board_tile_access_derive_group( $pattern, $role ) {
	$pat_parts  = explode( '/', (string) $pattern );
	$role_parts = explode( '/', (string) $role );

	if ( ! empty( $pat_parts ) && end( $pat_parts ) === '*' ) {
		array_pop( $pat_parts );
	}

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
