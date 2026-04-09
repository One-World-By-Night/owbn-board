<?php
/**
 * Site map — resolves which OWBN site hosts each tool, and wraps cross-site
 * deep links with the SSO auth redirect so users don't get bounced to login.
 *
 * Source of truth: owbn-core + owbn-archivist per-data-type remote URL options.
 * Each OWBN site already tells its client plugins "if you need chronicles, go
 * here; if you need votes, go there" via:
 *   - owc_chronicles_remote_url   → where chronicle data lives (owbn-core)
 *   - owc_coordinators_remote_url → where coordinator data lives (owbn-core)
 *   - owc_territories_remote_url  → where territory-manager lives (owbn-core)
 *   - owc_votes_remote_url        → where wp-voting-plugin lives (owbn-core)
 *   - owc_oat_remote_url          → where OAT lives (owbn-archivist)
 *   - owc_remote_url              → generic fallback (owbn-core)
 *
 * We extract the base hostname from those options to learn the site URL of
 * each tool. Falls back to hardcoded defaults if the options aren't set
 * (shouldn't happen in production, but keeps dev/test environments working).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get base URL for a known OWBN tool.
 *
 * @param string $tool Tool identifier (e.g., 'oat', 'wpvp', 'tm', 'election_bridge').
 * @return string Base URL of the site that hosts that tool (no trailing slash).
 */
function owbn_board_get_tool_site_url( $tool ) {
	// Map tool identifiers to owbn-core / owbn-archivist option names.
	$option_map = [
		'oat'             => 'oat_remote_url',         // owbn-archivist provides this
		'tm'              => 'territories_remote_url', // owbn-core
		'wpvp'            => 'votes_remote_url',       // owbn-core
		'election_bridge' => 'votes_remote_url',       // election-bridge lives with wp-voting-plugin
		'chronicles'      => 'chronicles_remote_url',  // owbn-core
		'coordinators'    => 'coordinators_remote_url',// owbn-core
	];

	// Try owbn-core's configured remote URL first.
	if ( isset( $option_map[ $tool ] ) && function_exists( 'owc_option_name' ) ) {
		$remote_url = trim( get_option( owc_option_name( $option_map[ $tool ] ), '' ) );
		if ( '' === $remote_url ) {
			// Fall back to owbn-core's generic remote_url if set.
			$remote_url = trim( get_option( owc_option_name( 'remote_url' ), '' ) );
		}
		if ( '' !== $remote_url ) {
			$base = owbn_board_extract_base_url( $remote_url );
			if ( $base ) {
				return $base;
			}
		}
	}

	// Hardcoded fallbacks — match current OWBN deployment.
	$defaults = [
		'oat'             => 'https://archivist.owbn.net',
		'wpvp'            => 'https://council.owbn.net',
		'tm'              => 'https://chronicles.owbn.net',
		'election_bridge' => 'https://council.owbn.net',
		'bylaws'          => 'https://council.owbn.net',
		'chronicles'      => 'https://chronicles.owbn.net',
		'coordinators'    => 'https://council.owbn.net',
		'players'         => 'https://players.owbn.net',
		'sso'             => 'https://sso.owbn.net',
		'support'         => 'https://support.owbn.net',
		'council'         => 'https://council.owbn.net',
		'archivist'       => 'https://archivist.owbn.net',
	];

	// Allow per-site override via board's own option.
	$map = get_option( 'owbn_board_site_map', [] );
	if ( is_array( $map ) && isset( $map[ $tool ] ) && '' !== $map[ $tool ] ) {
		return rtrim( $map[ $tool ], '/' );
	}

	return rtrim( $defaults[ $tool ] ?? '', '/' );
}

/**
 * Extract the scheme+host from a URL, stripping any path/query.
 *
 * @param string $url
 * @return string Base URL (e.g., "https://council.owbn.net") or empty string on failure.
 */
function owbn_board_extract_base_url( $url ) {
	$parsed = wp_parse_url( (string) $url );
	if ( empty( $parsed['host'] ) ) {
		return '';
	}
	$scheme = $parsed['scheme'] ?? 'https';
	return $scheme . '://' . $parsed['host'];
}

/**
 * Build a deep-link URL for a tool path on its host site, SSO-wrapped when cross-site.
 *
 * If the target host matches the current site, returns a normal URL.
 * If the target is a different OWBN host, wraps it in the SSO auth redirect
 * pattern used throughout owbn-core: {base}/?auth=sso&redirect_uri=...
 *
 * @param string $tool Tool identifier from owbn_board_get_tool_site_url
 * @param string $path Path relative to the site root (e.g., '/wp-admin/admin.php?page=oat')
 * @return string Absolute URL, SSO-wrapped if cross-site.
 */
function owbn_board_tool_url( $tool, $path = '' ) {
	$base = owbn_board_get_tool_site_url( $tool );
	if ( empty( $base ) ) {
		return '';
	}

	$path = '/' . ltrim( (string) $path, '/' );

	$target_host  = wp_parse_url( $base, PHP_URL_HOST );
	$current_host = wp_parse_url( home_url(), PHP_URL_HOST );

	if ( $target_host && $target_host === $current_host ) {
		// Same site — no need for SSO wrapper.
		return $base . $path;
	}

	// Cross-site — wrap with SSO redirect so the user stays authenticated.
	return $base . '/?auth=sso&redirect_uri=' . rawurlencode( $path );
}

/**
 * Convenience: wrap any fully-qualified URL with SSO redirect when cross-site.
 */
function owbn_board_sso_wrap_url( $url ) {
	$target_host  = wp_parse_url( $url, PHP_URL_HOST );
	$current_host = wp_parse_url( home_url(), PHP_URL_HOST );

	if ( ! $target_host || $target_host === $current_host ) {
		return $url;
	}

	$parsed = wp_parse_url( $url );
	$path   = $parsed['path'] ?? '/';
	$query  = ! empty( $parsed['query'] ) ? '?' . $parsed['query'] : '';
	$base   = ( $parsed['scheme'] ?? 'https' ) . '://' . $target_host;

	return $base . '/?auth=sso&redirect_uri=' . rawurlencode( $path . $query );
}
