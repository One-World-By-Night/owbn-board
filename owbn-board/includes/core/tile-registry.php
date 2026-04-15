<?php
/**
 * Tile registry. Modules register tiles via owbn_board_register_tile() at
 * plugins_loaded; core renders them filtered by site, read_roles, layout,
 * and per-user state.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_allowed_sizes() {
	return [ '1x1', '1x2', '1x3', '2x1', '2x2', '2x3', '3x1', '3x2', '3x3' ];
}

function owbn_board_register_tile( array $args ) {
	global $owbn_board_tiles;

	if ( ! is_array( $owbn_board_tiles ) ) {
		$owbn_board_tiles = [];
	}

	$defaults = [
		'id'                   => '',
		'title'                => '',
		'icon'                 => '',
		'read_roles'           => [],
		'write_roles'          => [],
		'sites'                => [],
		'size'                 => '1x1',
		'category'             => 'general',
		'render'               => null,
		'ajax_actions'         => [],
		'priority'             => 10,
		'data_version'         => 1,
		'audit'                => false,
		'supports_share_level' => false,
	];

	$tile = wp_parse_args( $args, $defaults );

	if ( empty( $tile['id'] ) || empty( $tile['title'] ) ) {
		error_log( '[owbn-board] Tile registration failed: missing id or title' );
		return false;
	}

	if ( ! is_callable( $tile['render'] ) ) {
		error_log( sprintf( '[owbn-board] Tile "%s" registration failed: render callback not callable', $tile['id'] ) );
		return false;
	}

	if ( ! in_array( $tile['size'], owbn_board_allowed_sizes(), true ) ) {
		error_log( sprintf( '[owbn-board] Tile "%s" has invalid size "%s", defaulting to 1x1', $tile['id'], $tile['size'] ) );
		$tile['size'] = '1x1';
	}

	if ( empty( $tile['write_roles'] ) ) {
		$tile['write_roles'] = $tile['read_roles'];
	}

	$owbn_board_tiles[ $tile['id'] ] = $tile;

	if ( ! empty( $tile['ajax_actions'] ) ) {
		foreach ( $tile['ajax_actions'] as $action => $callback ) {
			if ( is_callable( $callback ) ) {
				add_action( 'wp_ajax_' . $action, $callback );
			}
		}
	}

	return true;
}

function owbn_board_get_registered_tiles() {
	global $owbn_board_tiles;
	return is_array( $owbn_board_tiles ) ? $owbn_board_tiles : [];
}

function owbn_board_get_tile( $tile_id ) {
	$tiles = owbn_board_get_registered_tiles();
	return isset( $tiles[ $tile_id ] ) ? $tiles[ $tile_id ] : null;
}

function owbn_board_get_visible_tiles( $user_id, $site_slug = '' ) {
	$all_tiles = owbn_board_get_registered_tiles();
	if ( empty( $all_tiles ) ) {
		return [];
	}

	$user_roles  = owbn_board_get_user_roles( $user_id );
	$layout      = owbn_board_get_site_layout();
	$user_state  = owbn_board_get_user_tile_states( $user_id );
	$user_sizes  = owbn_board_get_user_tile_sizes( $user_id );
	$visible     = [];

	foreach ( $all_tiles as $tile ) {
		if ( ! empty( $tile['sites'] ) && $site_slug && ! in_array( $site_slug, $tile['sites'], true ) ) {
			continue;
		}

		$effective_read = owbn_board_tile_effective_read_roles( $tile );
		if ( ! empty( $effective_read ) && ! owbn_board_user_matches_any_pattern( $user_roles, $effective_read ) ) {
			continue;
		}

		$layout_entry = isset( $layout['tiles'][ $tile['id'] ] ) ? $layout['tiles'][ $tile['id'] ] : null;
		if ( $layout_entry && isset( $layout_entry['enabled'] ) && ! $layout_entry['enabled'] ) {
			continue;
		}

		$state = isset( $user_state[ $tile['id'] ] ) ? $user_state[ $tile['id'] ] : null;
		if ( $state && 'dismissed' === $state['state'] ) {
			continue;
		}
		if ( $state && 'snoozed' === $state['state'] && $state['snooze_until'] && strtotime( $state['snooze_until'] ) > time() ) {
			continue;
		}

		if ( $layout_entry ) {
			if ( ! empty( $layout_entry['size'] ) && in_array( $layout_entry['size'], owbn_board_allowed_sizes(), true ) ) {
				$tile['size'] = $layout_entry['size'];
			}
			if ( isset( $layout_entry['priority'] ) ) {
				$tile['priority'] = (int) $layout_entry['priority'];
			}
		}

		// User size override wins over layout override and registered default.
		if ( isset( $user_sizes[ $tile['id'] ] ) && in_array( $user_sizes[ $tile['id'] ], owbn_board_allowed_sizes(), true ) ) {
			$tile['size'] = $user_sizes[ $tile['id'] ];
		}

		$tile['_state'] = $state ? $state['state'] : 'default';

		if ( ! apply_filters( 'owbn_board_tile_visible', true, $tile['id'], $user_id, $user_roles ) ) {
			continue;
		}

		$visible[] = $tile;
	}

	// Pinned first, then by priority.
	usort( $visible, function ( $a, $b ) {
		$a_pinned = ( 'pinned' === ( $a['_state'] ?? '' ) ) ? 0 : 1;
		$b_pinned = ( 'pinned' === ( $b['_state'] ?? '' ) ) ? 0 : 1;
		if ( $a_pinned !== $b_pinned ) {
			return $a_pinned - $b_pinned;
		}
		return $a['priority'] - $b['priority'];
	} );

	// Apply per-user tile order: tiles in user's saved order first (in that order),
	// remaining tiles fall through to the priority sort above.
	$user_order = owbn_board_get_user_tile_order( $user_id );
	if ( ! empty( $user_order ) ) {
		$by_id = [];
		foreach ( $visible as $t ) {
			$by_id[ $t['id'] ] = $t;
		}
		$ordered = [];
		foreach ( $user_order as $id ) {
			if ( isset( $by_id[ $id ] ) ) {
				$ordered[] = $by_id[ $id ];
				unset( $by_id[ $id ] );
			}
		}
		$visible = array_merge( $ordered, array_values( $by_id ) );
	}

	return $visible;
}

function owbn_board_get_site_slug() {
	$slug = get_option( 'owbn_board_site_slug', '' );
	if ( $slug ) {
		return $slug;
	}

	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	if ( preg_match( '/^([^.]+)\.owbn\.net$/', (string) $host, $matches ) ) {
		return $matches[1];
	}
	return '';
}
