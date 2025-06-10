<?php
// File: accessschema-client/utils.php
// @version 1.1.0
// Author: greghacke

defined('ABSPATH') || exit;

function accessSchema_client_access_granted( $patterns ) {
	if ( ! is_user_logged_in() ) {
		return apply_filters( 'accessSchema_client_access_granted', false, $patterns, 0 );
	}

	$user_id = get_current_user_id();
	$user    = wp_get_current_user();

	if ( is_string( $patterns ) ) {
		$patterns = array_map( 'trim', explode( ',', $patterns ) );
	}
	if ( ! is_array( $patterns ) || empty( $patterns ) ) {
		return apply_filters( 'accessSchema_client_access_granted', false, $patterns, $user_id );
	}

	$result = accessSchema_client_remote_user_matches_any( $user->user_email, $patterns );

	return apply_filters( 'accessSchema_client_access_granted', $result, $patterns, $user_id );
}

function accessSchema_client_access_denied( $patterns ) {
	return ! accessSchema_client_access_granted( $patterns );
}

function accessSchema_client_remote_user_matches_any( $email, array $patterns ) {
	$response = accessSchema_remote_get_roles_by_email( $email );
	if ( is_wp_error( $response ) || empty( $response['roles'] ) ) {
		return false;
	}

	foreach ( $patterns as $pattern ) {
		if ( accessSchema_client_roles_match_pattern( $response['roles'], $pattern ) ) {
			return true;
		}
	}

	return false;
}

function accessSchema_client_roles_match_pattern( array $roles, $pattern ) {
	$regex = accessSchema_client_pattern_to_regex( $pattern );

	foreach ( $roles as $role ) {
		if ( preg_match( $regex, $role ) ) {
			return true;
		}
	}

	return false;
}

function accessSchema_client_pattern_to_regex( $pattern ) {
	$escaped = preg_quote( $pattern, '#' );
	$regex   = str_replace(
		['\*\*', '\*'],
		['.*', '[^/]+'],
		$escaped
	);
	return "#^{$regex}$#";
}

function accessSchema_client_roles_match_pattern_from_email( $email, $pattern ) {
	$roles = accessSchema_remote_get_roles_by_email( $email );
	if ( is_wp_error( $roles ) || empty( $roles['roles'] ) ) {
		return false;
	}
	return accessSchema_client_roles_match_pattern( $roles['roles'], $pattern );
}