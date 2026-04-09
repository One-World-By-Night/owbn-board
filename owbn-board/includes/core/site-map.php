<?php
/**
 * Resolves which OWBN site hosts each tool and SSO-wraps cross-site deep links.
 * Reads per-tool remote URLs from owbn-core / owbn-archivist options so the
 * board doesn't hardcode hostnames.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_get_tool_site_url( $tool ) {
	$option_map = [
		'oat'             => 'oat_remote_url',
		'tm'              => 'territories_remote_url',
		'wpvp'            => 'votes_remote_url',
		'election_bridge' => 'votes_remote_url',
		'chronicles'      => 'chronicles_remote_url',
		'coordinators'    => 'coordinators_remote_url',
	];

	if ( isset( $option_map[ $tool ] ) && function_exists( 'owc_option_name' ) ) {
		$remote_url = trim( get_option( owc_option_name( $option_map[ $tool ] ), '' ) );
		if ( '' === $remote_url ) {
			$remote_url = trim( get_option( owc_option_name( 'remote_url' ), '' ) );
		}
		if ( '' !== $remote_url ) {
			$base = owbn_board_extract_base_url( $remote_url );
			if ( $base ) {
				return $base;
			}
		}
	}

	// Hardcoded fallbacks for dev/test environments where remote URLs aren't set.
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

	$map = get_option( 'owbn_board_site_map', [] );
	if ( is_array( $map ) && isset( $map[ $tool ] ) && '' !== $map[ $tool ] ) {
		return rtrim( $map[ $tool ], '/' );
	}

	return rtrim( $defaults[ $tool ] ?? '', '/' );
}

function owbn_board_extract_base_url( $url ) {
	$parsed = wp_parse_url( (string) $url );
	if ( empty( $parsed['host'] ) ) {
		return '';
	}
	$scheme = $parsed['scheme'] ?? 'https';
	return $scheme . '://' . $parsed['host'];
}

function owbn_board_tool_url( $tool, $path = '' ) {
	$base = owbn_board_get_tool_site_url( $tool );
	if ( empty( $base ) ) {
		return '';
	}

	$path = '/' . ltrim( (string) $path, '/' );

	$target_host  = wp_parse_url( $base, PHP_URL_HOST );
	$current_host = wp_parse_url( home_url(), PHP_URL_HOST );

	if ( $target_host && $target_host === $current_host ) {
		return $base . $path;
	}

	return $base . '/?auth=sso&redirect_uri=' . rawurlencode( $path );
}

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
