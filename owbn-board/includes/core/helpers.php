<?php
// File: includes/core/helpers.php
// @version 1.6.1
// Author: greghacke

defined('ABSPATH') || exit;

/**
 * Discover available tools by scanning the /tools directory.
 *
 * @return array List of tool slugs (lowercased).
 */
function owbn_board_discover_tools() {
    $base_path = dirname(plugin_dir_path(__FILE__), 2);
    $tools_dir = $base_path . '/tools';
    $tools     = [];

    if (is_dir($tools_dir)) {
        foreach (scandir($tools_dir) as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '_template') {
                continue;
            }

            $full_path = $tools_dir . '/' . $entry;

            if (is_dir($full_path)) {
                $tools[] = strtolower($entry); // Canonical lowercase form
            }
        }
    }

    return $tools;
}

/**
 * Get tool enablement status from saved config.
 */
function owbn_board_get_current_tool_role($tool = null) {
    $tool_roles = get_option('owbn_tool_roles', []);
    $tool = strtolower($tool ?? basename(dirname(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)['file'])));
    return strtoupper($tool_roles[$tool] ?? 'DISABLED');
}

/**
 * Parse accessSchema roles into matching groups by tool.
 */
function owbn_board_get_user_groups_by_tool($tool) {
    $email      = wp_get_current_user()->user_email;
    $raw_roles  = accessSchema_client_remote_get_roles_by_email($email);
    $role_paths = $raw_roles['roles'] ?? [];

    $groups = [];

    foreach ($role_paths as $path) {
        if (!is_string($path)) continue;

        $parts = explode('/', $path);
        if (count($parts) < 2) continue;

        if ($parts[0] === $tool) {
            $groups[] = $parts[1]; // Keep exact casing
        }
    }

    return array_unique($groups);
}