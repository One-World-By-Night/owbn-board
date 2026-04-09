<?php
/**
 * Tile registry — plugins register tiles via owbn_board_register_tile().
 *
 * A tile has:
 *   id            string  unique namespaced ID, e.g. 'board:notebook', 'oat:inbox'
 *   title         string  display title
 *   icon          string  dashicons or eicons class (optional)
 *   read_roles    array   ASC role patterns for visibility
 *   write_roles   array   ASC role patterns for interaction (subset of read_roles)
 *   sites         array   site slugs where this tile can appear (optional, default all)
 *   size          string  one of: 1x1, 1x2, 1x3, 2x1, 2x2, 2x3, 3x1, 3x2, 3x3
 *   category      string  grouping hint: communication, approvals, reference, reports, admin
 *   render        callable function that outputs tile body HTML
 *   ajax_actions  array   [action => callable] handlers for interactive tiles (optional)
 *   priority      int     default sort order (default 10)
 *   data_version  int     schema version for backwards compat (default 1)
 *   audit         bool    log every read access (default false)
 */

defined( 'ABSPATH' ) || exit;

/**
 * Allowed tile sizes (width x height in grid cells).
 */
function owbn_board_allowed_sizes() {
	return [ '1x1', '1x2', '1x3', '2x1', '2x2', '2x3', '3x1', '3x2', '3x3' ];
}

/**
 * Register a tile.
 *
 * @param array $args Tile definition.
 * @return bool True on success, false on validation failure.
 */
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
		// Whether this tile honors the Share Level override from the
		// tile-access module. Tiles that support it should derive their
		// scope via owbn_board_tile_access_resolve_scope() and render a
		// group selector when multiple groups are returned.
		'supports_share_level' => false,
	];

	$tile = wp_parse_args( $args, $defaults );

	// Validate required fields
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

	// write_roles defaults to read_roles if empty
	if ( empty( $tile['write_roles'] ) ) {
		$tile['write_roles'] = $tile['read_roles'];
	}

	$owbn_board_tiles[ $tile['id'] ] = $tile;

	// Register AJAX handlers for interactive tiles
	if ( ! empty( $tile['ajax_actions'] ) ) {
		foreach ( $tile['ajax_actions'] as $action => $callback ) {
			if ( is_callable( $callback ) ) {
				add_action( 'wp_ajax_' . $action, $callback );
			}
		}
	}

	return true;
}

/**
 * Get all registered tiles.
 *
 * @return array
 */
function owbn_board_get_registered_tiles() {
	global $owbn_board_tiles;
	return is_array( $owbn_board_tiles ) ? $owbn_board_tiles : [];
}

/**
 * Get a single tile by ID.
 *
 * @param string $tile_id
 * @return array|null
 */
function owbn_board_get_tile( $tile_id ) {
	$tiles = owbn_board_get_registered_tiles();
	return isset( $tiles[ $tile_id ] ) ? $tiles[ $tile_id ] : null;
}

/**
 * Get tiles visible to a user on the current site.
 * Filters by site, read_roles, layout enable state, and per-user state.
 *
 * @param int    $user_id
 * @param string $site_slug Current site slug (e.g. 'council', 'archivist')
 * @return array Ordered array of tiles ready to render.
 */
function owbn_board_get_visible_tiles( $user_id, $site_slug = '' ) {
	$all_tiles = owbn_board_get_registered_tiles();
	if ( empty( $all_tiles ) ) {
		return [];
	}

	$user_roles  = owbn_board_get_user_roles( $user_id );
	$layout      = owbn_board_get_site_layout();
	$user_state  = owbn_board_get_user_tile_states( $user_id );
	$visible     = [];

	foreach ( $all_tiles as $tile ) {
		// Site filter
		if ( ! empty( $tile['sites'] ) && $site_slug && ! in_array( $site_slug, $tile['sites'], true ) ) {
			continue;
		}

		// Read role check — use effective roles (layout override wins)
		$effective_read = owbn_board_tile_effective_read_roles( $tile );
		if ( ! empty( $effective_read ) && ! owbn_board_user_matches_any_pattern( $user_roles, $effective_read ) ) {
			continue;
		}

		// Layout enable check (admin disabled this tile for the site)
		$layout_entry = isset( $layout['tiles'][ $tile['id'] ] ) ? $layout['tiles'][ $tile['id'] ] : null;
		if ( $layout_entry && isset( $layout_entry['enabled'] ) && ! $layout_entry['enabled'] ) {
			continue;
		}

		// User state — skip dismissed or snoozed
		$state = isset( $user_state[ $tile['id'] ] ) ? $user_state[ $tile['id'] ] : null;
		if ( $state && 'dismissed' === $state['state'] ) {
			continue;
		}
		if ( $state && 'snoozed' === $state['state'] && $state['snooze_until'] && strtotime( $state['snooze_until'] ) > time() ) {
			continue;
		}

		// Merge layout overrides into tile
		if ( $layout_entry ) {
			if ( ! empty( $layout_entry['size'] ) && in_array( $layout_entry['size'], owbn_board_allowed_sizes(), true ) ) {
				$tile['size'] = $layout_entry['size'];
			}
			if ( isset( $layout_entry['priority'] ) ) {
				$tile['priority'] = (int) $layout_entry['priority'];
			}
		}

		// Apply user state metadata
		$tile['_state'] = $state ? $state['state'] : 'default';

		// Allow filter override
		if ( ! apply_filters( 'owbn_board_tile_visible', true, $tile['id'], $user_id, $user_roles ) ) {
			continue;
		}

		$visible[] = $tile;
	}

	// Pinned first, then by priority
	usort( $visible, function ( $a, $b ) {
		$a_pinned = ( 'pinned' === ( $a['_state'] ?? '' ) ) ? 0 : 1;
		$b_pinned = ( 'pinned' === ( $b['_state'] ?? '' ) ) ? 0 : 1;
		if ( $a_pinned !== $b_pinned ) {
			return $a_pinned - $b_pinned;
		}
		return $a['priority'] - $b['priority'];
	} );

	return $visible;
}

/**
 * Get the current site's slug for site filtering.
 * Reads from a plugin option; falls back to parsing home_url.
 */
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
