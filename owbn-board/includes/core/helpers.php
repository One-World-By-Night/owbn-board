<?php

// File: includes/core/helpers.php
// @version 0.1.0
// @author greghacke

defined( 'ABSPATH' ) || exit;

function owbn_sanitize_tool_roles( $input ) {
    $allowed = [ 'MAIN', 'VIEWER', 'DISABLED' ];
    $output = [];

    foreach ( $input as $tool => $role ) {
        $clean_tool = sanitize_key( $tool );
        $clean_role = in_array( $role, $allowed, true ) ? $role : 'DISABLED';
        $output[ $clean_tool ] = $clean_role;
    }

    return $output;
}

function owbn_board_discover_tools() {
    $base_path = dirname( plugin_dir_path( __FILE__ ), 2 ); // goes up two levels from includes/core
    $tools_dir = $base_path . '/tools';
    $tools = [];

    if ( is_dir( $tools_dir ) ) {
        foreach ( scandir( $tools_dir ) as $entry ) {
            if ( $entry === '.' || $entry === '..' || $entry === '_template' ) {
                continue;
            }
            if ( is_dir( $tools_dir . '/' . $entry ) ) {
                $tools[] = $entry;
            }
        }
    }

    return $tools;
}

function owbn_board_get_current_tool_role( $dir = null ) {
    $slug = basename( $dir ?? dirname( debug_backtrace()[0]['file'] ) );
    $const = strtoupper( "OWBN_{$slug}_ROLE" );
    return defined( $const ) ? constant( $const ) : 'DISABLED';
}

function owbn_board_get_user_tools_with_groups() {
    if ( ! is_user_logged_in() ) return [];

    $user  = wp_get_current_user();
    $email = $user->user_email;

    $response = accessSchema_client_remote_get_roles_by_email( $email );
    if ( is_wp_error( $response ) || empty( $response['roles'] ) ) {
        return [];
    }

    $roles = $response['roles'];
    $tools = [];

    foreach ( $roles as $role ) {
        if ( preg_match( '#^Chronicles/([^/]+)#', $role, $m ) ) {
            $tools['chronicles'][] = $m[1];
        }
        if ( preg_match( '#^Coordinators/([^/]+)#', $role, $m ) ) {
            $tools['coordinators'][] = $m[1];
        }
        if ( preg_match( '#^Exec/([^/]+)#', $role, $m ) ) {
            $tools['exec'][] = $m[1];
        }
    }

    // Deduplicate
    foreach ( $tools as &$list ) {
        $list = array_unique( $list );
    }
    return $tools;
}