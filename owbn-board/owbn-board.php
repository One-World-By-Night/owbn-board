<?php
/**
 * Plugin Name: OWBN Board
 * Plugin URI: https://github.com/One-World-By-Night/owbn-board
 * Description: Modular infrastructure for cross-site OWBN tools with role-based access.
 * Version: 0.9.0
 * Author: greghacke
 * License: GPL-2.0-or-later
 * Text Domain: owbn-board
 */

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . 'includes/core/init.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin/settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/render/init.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/shortcodes.php';
require_once plugin_dir_path(__FILE__) . 'includes/tools/tools.php';
require_once plugin_dir_path(__FILE__) . 'includes/utils/utilities.php';

require_once plugin_dir_path(__FILE__) . 'includes/accessschema-client/init.php';

$tool_roles = get_option('owbn_tool_roles', []);

foreach (glob(plugin_dir_path(__FILE__) . 'tools/*', GLOB_ONLYDIR) as $tool_dir) {
    $tool_slug = strtolower(basename($tool_dir));

    if ($tool_slug === '_template') {
        continue; // skip template folder
    }

    $role = strtoupper($tool_roles[$tool_slug] ?? 'DISABLED');
    if ($role === 'DISABLED') {
        continue; // skip disabled tools
    }

    // Define a role constant like OWBN_CHRONICLE_ROLE
    $const_name = 'OWBN_' . strtoupper($tool_slug) . '_ROLE';
    if (!defined($const_name)) {
        define($const_name, $role);
    }

    // Load init.php if it exists for this tool
    $init = $tool_dir . '/init.php';
    if (file_exists($init)) {
        require_once $init;
    }
}

// load_plugin_textdomain('owbn-board', false, dirname(plugin_basename(__FILE__)) . '/languages');