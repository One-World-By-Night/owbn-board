<?php
// File: includes/core/helpers.php
// @vesion 0.8.0
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
    $user = wp_get_current_user();
    $email = $user->user_email;

    // error_log("[OWBN] Looking up groups for tool: {$tool}, user: {$email}");

    $raw_roles  = accessSchema_client_remote_get_roles_by_email($email, 'owbn_board');
    $role_paths = $raw_roles['roles'] ?? [];

    // error_log("[OWBN] Raw roles for {$email}: " . print_r($role_paths, true));

    $groups = [];

    foreach ($role_paths as $path) {
        if (!is_string($path)) continue;

        $parts = explode('/', $path);
        // error_log("[OWBN] Parsed role path: {$path} → " . json_encode($parts));

        if (count($parts) >= 2 && $parts[0] === $tool) {
            $groups[] = $parts[1]; // Keep exact casing
            // error_log("[OWBN] → Matched group: {$parts[1]}");
        }
    }

    $unique = array_unique($groups);
    // error_log("[OWBN] Final groups for tool {$tool}: " . json_encode($unique));
    return $unique;
}