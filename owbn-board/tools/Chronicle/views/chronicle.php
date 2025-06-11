<?php
// File: tools/chronicle/views/chronicles.php
// @version 0.7.5
// Author: greghacke

defined( 'ABSPATH' ) || exit;

function owbn_render_namespace_view_chronicles( $context ) {
    $email     = $context['email'];
    $group     = $context['selected_group'] ?? null;
    $groups    = $context['groups'] ?? [];
    $is_admin  = current_user_can( 'administrator' );

    if ( $group ) {
        $raw_data = accessSchema_client_remote_get_roles_by_email( $email );
        $raw_roles = $raw_data['roles'] ?? [];

        // Normalize role list — flatten if needed
        $roles = [];

        foreach ( $raw_roles as $r ) {
            if ( is_string( $r ) ) {
                $roles[] = strtolower( $r );
            } elseif ( is_array( $r ) && isset( $r['role'] ) && is_string( $r['role'] ) ) {
                $roles[] = strtolower( $r['role'] );
            } else {
                error_log("Unexpected role format for user {$email}: " . print_r( $r, true ));
            }
        }

        $has_access = accessSchema_client_remote_check_access( $email, "Chronicles/$group", true );

        if ( is_wp_error( $has_access ) ) {
            echo '<p>Error checking access: ' . esc_html( $has_access->get_error_message() ) . '</p>';
            return;
        }

        if ( ! $has_access && ! $is_admin ) {
            echo '<p>You do not have access to this Chronicle: <strong>' . esc_html( strtoupper( $group ) ) . '</strong></p>';
            return;
        }

        echo '<h2>' . esc_html( strtoupper( $group ) ) . ' Chronicle View</h2>';

        // 🔥 Role cascade starts here
        $has_any_access = false;
        $base_path      = strtolower("Chronicles/$group");

        $role_check_order = [
            'HST'     => 'owbn_render_chronicle_hst_section',
            'CM'      => 'owbn_render_chronicle_cm_section',
            'Staff'   => 'owbn_render_chronicle_staff_section',
            'Player'  => 'owbn_render_chronicle_players_section',
        ];

        $matches_base_only = in_array( $base_path, $roles, true );

        foreach ( $role_check_order as $role_key => $render_func ) {
            $role_path = strtolower("$base_path/$role_key");

            $has_role = (
                in_array( $role_path, $roles, true ) ||
                ( $role_key === 'Player' &&
                    (
                        in_array( "$base_path/player", $roles, true ) ||
                        in_array( "$base_path/players", $roles, true ) ||
                        $matches_base_only
                    )
                )
            );

            if ( $has_role ) {
                $has_any_access = true;
                $start = array_search($role_key, array_keys($role_check_order));
                $to_render = array_slice($role_check_order, $start);

                foreach ( $to_render as $f ) {
                    $f( $group, $email );
                }

                break;
            }
        }

        if ( ! $has_any_access ) {
            echo '<p>You have no role-based access to this Chronicle: <strong>' . esc_html( strtoupper( $group ) ) . '</strong></p>';
        }

        return;
    }

    echo '<p>You must select a Chronicle group using the menu.</p>';
}

function owbn_render_chronicle_hst_section( $slug, $email ) {
    echo "<div><strong>" . esc_html( $slug ) ." HST Section" . "</div>";
    // Add your logic here
}

function owbn_render_chronicle_cm_section( $slug, $email ) {
    echo "<div><strong>" . esc_html( $slug ) ." CM Section" . "</div>";
}

function owbn_render_chronicle_staff_section( $slug, $email ) {
    echo "<div><strong>" . esc_html( $slug ) ." Staff Section" . "</div>";
}

function owbn_render_chronicle_players_section( $slug, $email ) {
    echo "<div><strong>" . esc_html( $slug ) ." Players Section" . "</div>";
}