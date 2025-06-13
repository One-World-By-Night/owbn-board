<?php
// File: includes/admin/settings.php
// @version 0.7.5
// Author: greghacke

defined('ABSPATH') || exit;

/**
 * Convert group names into slug-safe format for use in URLs.
 */
function owbn_board_sanitize_group_slug($group) {
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $group), '-'));
    // error_log("Slugified [$group] → [$slug]");
    return $slug;
}

// Hook into admin_menu
add_action('admin_menu', function () {
    // error_log("admin_menu hook starting...");

    try {
        $user  = wp_get_current_user();
        $email = $user->user_email;
        // error_log('--- CURRENT USER EMAIL --- ' . $email);

        $roles_response = accessSchema_client_remote_get_roles_by_email($email);
        // error_log('--- ACCESSSCHEMA ROLES ---');
        // error_log(print_r($roles_response, true));

        add_menu_page(
            'OWBN Board',
            'OWBN Board',
            'read',
            'owbn-board',
            'owbn_board_render_landing_page',
            'dashicons-excerpt-view',
            5
        );

        $tools = [
            'Chronicle'   => 'chronicle',
            'Coordinator' => 'coordinator',
            'Exec'        => 'exec',
        ];

        foreach ($tools as $tool_label => $tool_slug) {
            $groups = owbn_board_get_user_groups_by_tool($tool_label);
            // error_log("Fetched groups for $tool_label: " . print_r($groups, true));
            // error_log("TOOL: $tool_label → $tool_slug");
            foreach ($groups as $group) {
                // error_log("  GROUP: $group");
                $safe_slug = owbn_board_sanitize_group_slug($group);
                // error_log("    SAFE SLUG: $safe_slug");

                add_submenu_page(
                    'owbn-board',
                    "$group $tool_label",
                    $group,
                    'read',
                    "owbn-board-$tool_slug-$safe_slug",
                    "owbn_board_render_{$tool_slug}_namespace_page"
                );
            }
        }

        add_submenu_page(
            'owbn-board',
            'Config',
            'Config',
            'manage_options',
            'owbn-board-config',
            'owbn_board_render_settings_page'
        );

    } catch (Throwable $e) {
        error_log("admin_menu ERROR: " . $e->getMessage());
    }

    // error_log("admin_menu hook completed.");
});

// Save config options from admin panel
add_action('admin_init', function () {
    if (
        isset($_POST['owbn_tool_roles_nonce']) &&
        wp_verify_nonce($_POST['owbn_tool_roles_nonce'], 'owbn_tool_roles_save')
    ) {
        if (current_user_can('manage_options')) {
            $raw   = $_POST['owbn_tool_roles'] ?? [];
            $clean = [];

            foreach (owbn_board_discover_tools() as $tool) {
                $clean[$tool] = (isset($raw[$tool]) && $raw[$tool] === 'enabled') ? 'enabled' : 'disabled';
            }

            update_option('owbn_tool_roles', $clean);
        }
    }
});