<?php
/**
 * Plugin Name: OWBN Board
 * Description: Modular infrastructure to support cross-site tools for OWBN members in all roles.
 * Version: 0.7.5
 * Author: greghacke
 * Author URI: https://www.owbn.net
 * Text Domain: owbn-board
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * GitHub Plugin URI: https://github.com/One-World-By-Night/owbn-board
 * GitHub Branch: main
 */

defined('ABSPATH') || exit;

// ─── Core Includes ───────────────────────────────────────────────────────────
require_once plugin_dir_path(__FILE__) . 'includes/core/init.php';               // helpers, webhooks, bootstrap-tools
require_once plugin_dir_path(__FILE__) . 'includes/admin/settings.php';         // admin_menu + config rendering
require_once plugin_dir_path(__FILE__) . 'includes/render/init.php';            // render-admin.php, render-ui.php
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/shortcodes.php';  // shortcodes like [owbn-chronicles]
require_once plugin_dir_path(__FILE__) . 'includes/tools/tools.php';            // common tool utilities
require_once plugin_dir_path(__FILE__) . 'includes/utils/utilities.php';        // formatting, output, general helpers

// --- Load the AccessSchema Client --------------------------------------------
require_once plugin_dir_path(__FILE__) . 'includes/accessschema-client/init.php';

// ─── Load Tools Conditionally ────────────────────────────────────────────────
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

// ─── i18n (optional) ─────────────────────────────────────────────────────────
// load_plugin_textdomain('owbn-board', false, dirname(plugin_basename(__FILE__)) . '/languages');