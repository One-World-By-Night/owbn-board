<?php

// File: includes/tools/tools.php
// @version 1.6.1
// @author greghacke

defined('ABSPATH') || exit;

function owbn_render_user_tool_page() {
    echo '<div class="wrap">';
    echo '<h1>My OWBN Access</h1>';

    $current_user = wp_get_current_user();
    $email = $current_user->user_email;

    if (empty($email)) {
        echo '<p>Could not determine your email for access lookup.</p></div>';
        return;
    }

    $roles = accessSchema_client_remote_get_roles_by_email($email);

    if (is_wp_error($roles)) {
        echo '<p>Error fetching roles: ' . esc_html($roles->get_error_message()) . '</p></div>';
        return;
    }

    if (empty($roles) || !is_array($roles)) {
        echo '<p>You do not have any registered access roles.</p></div>';
        return;
    }

    // Get the namespace from the URL slug
    $page = $_GET['page'] ?? '';
    $namespace = str_replace('owbn-board-', '', $page);
    $group = $_GET['group'] ?? null;

    // Load current tool activation settings
    $tool_roles = get_option('owbn_tool_roles', []);
    $enabled_tools = array_filter($tool_roles, fn($status) => strtoupper($status) === 'ENABLED');

    if (!array_key_exists($namespace, $enabled_tools)) {
        echo '<p>This tool <strong>' . esc_html($namespace) . '</strong> is currently <strong>DISABLED</strong>.</p></div>';
        return;
    }

    // Filter user roles to only include those matching this namespace
    $groups = [];
    foreach ($roles as $role_path) {
        $parts = explode('/', $role_path);
        if (count($parts) >= 2 && strtolower($parts[0]) === strtolower($namespace)) {
            $groups[] = $parts[1];
        }
    }

    $groups = array_unique($groups);

    if (empty($groups)) {
        echo '<p>You do not have access under this namespace: <code>' . esc_html($namespace) . '</code>.</p></div>';
        return;
    }

    // Adjust path to load from tools/<Tool>/views/<tool>.php
    $tool_folder = ucfirst(strtolower($namespace));
    $view_file = plugin_dir_path(__FILE__) . "../../tools/{$tool_folder}/views/{$namespace}.php";

    if (file_exists($view_file)) {
        include_once $view_file;
        $callback = 'owbn_render_namespace_view_' . strtolower($namespace);

        if (function_exists($callback)) {
            $callback([
                'email'          => $email,
                'roles'          => $roles,
                'groups'         => $groups,
                'selected_group' => $group,
            ]);
        } else {
            echo '<div class="notice notice-error"><p><strong>Error:</strong> Missing view function <code>' . esc_html($callback) . '()</code> for tool <strong>' . esc_html($namespace) . '</strong>.</p></div>';
        }
    } else {
        echo '<div class="notice notice-error"><p><strong>Error:</strong> View file not found at <code>' . esc_html($view_file) . '</code>.</p></div>';
    }

    echo '</div>';
}