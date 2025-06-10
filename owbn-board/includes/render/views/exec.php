<?php
// File: includes/render/views/exec.php
// @version 0.1.1
// Author: greghacke

defined( 'ABSPATH' ) || exit;

function owbn_render_namespace_view_exec( $context ) {
    $email     = $context['email'];
    $group     = $context['selected_group'] ?? null;
    $groups    = $context['groups'] ?? [];
    $is_admin  = current_user_can( 'administrator' );

    echo '<p>Welcome to the Exec interface.</p>';

    // If a group is selected, check if user is authorized for that group
    if ( $group ) {
        $has_access = accessSchema_client_remote_check_access( $email, "Exec/$group", true );

        if ( is_wp_error( $has_access ) ) {
            echo '<p>Error checking access: ' . esc_html( $has_access->get_error_message() ) . '</p>';
            return;
        }

        if ( ! $has_access && ! $is_admin ) {
            echo '<p>You do not have access to this Exec group: <strong>' . esc_html( strtoupper( $group ) ) . '</strong></p>';
            return;
        }

        echo '<h2>' . esc_html( strtoupper( $group ) ) . ' Exec View</h2>';
        // 👉 Render actual Exec interface here
        return;
    }

    // No group selected — only admins can view the list
    if ( $is_admin ) {
        echo '<h2>Select an Exec Group:</h2><ul>';
        foreach ( $groups as $g ) {
            $url = add_query_arg( [
                'page'  => 'owbn-board-exec',
                'group' => $g,
            ], admin_url( 'admin.php' ) );
            echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $g ) . '</a></li>';
        }
        echo '</ul>';
    } else {
        echo '<p>You must have access to a specific Exec group to view this page.</p>';
    }
}