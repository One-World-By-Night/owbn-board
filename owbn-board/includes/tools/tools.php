<?php

// File: includes/utils/utilities.php
// @version 0.1.0
// @author greghacke

defined( 'ABSPATH' ) || exit;

function owbn_render_user_tool_page() {
    echo '<div class="wrap">';
    echo '<h1>My OWBN Access</h1>';

    $current_user = wp_get_current_user();
    $email = $current_user->user_email;

    if ( empty( $email ) ) {
        echo '<p>Could not determine your email for access lookup.</p></div>';
        return;
    }

    $roles = accessSchema_remote_get_roles_by_email( $email );

    if ( is_wp_error( $roles ) ) {
        echo '<p>Error fetching roles: ' . esc_html( $roles->get_error_message() ) . '</p></div>';
        return;
    }

    if ( empty( $roles ) || ! is_array( $roles ) ) {
        echo '<p>You do not have any registered access roles.</p></div>';
        return;
    }

    // Determine which namespace is being viewed
    $page = $_GET['page'] ?? '';
    $namespace = str_replace( 'owbn-board-', '', $page );
    $group = $_GET['group'] ?? null;

    // Only extract roles under that namespace
    $groups = [];
    foreach ( $roles as $role_path ) {
        $parts = explode( '/', $role_path );
        if ( count( $parts ) >= 2 && strtolower( $parts[0] ) === strtolower( $namespace ) ) {
            $groups[] = $parts[1];
        }
    }
    $groups = array_unique( $groups );

    if ( empty( $groups ) ) {
        echo '<p>You do not have access under this namespace: <code>' . esc_html( $namespace ) . '</code>.</p></div>';
        return;
    }

    // Auto-load the view file: includes/render/views/chronicles.php
    $view_file = plugin_dir_path( __FILE__ ) . '/../render/views/' . strtolower( $namespace ) . '.php';
    if ( file_exists( $view_file ) ) {
        include_once $view_file;
        $callback = 'owbn_render_namespace_view_' . strtolower( $namespace );
        if ( function_exists( $callback ) ) {
            call_user_func( $callback, [
                'email' => $email,
                'roles' => $roles,
                'groups' => $groups,
                'selected_group' => $group,
            ]);
        } else {
            echo '<p>No view function found for <code>' . esc_html( $namespace ) . '</code>.</p>';
        }
    } else {
        echo '<p>No view file found for <code>' . esc_html( $namespace ) . '</code>.</p>';
    }

    echo '</div>';
}