<?php

// File: includes/core/bootstrap-tools.php
// @version 0.7.5
// Author: greghacke

defined('ABSPATH') || exit;

add_action('plugins_loaded', function () {
    $base_path  = dirname(plugin_dir_path(__FILE__), 2); // up to plugin root
    $tools_dir  = $base_path . '/tools';
    $tool_roles = get_option('owbn_tool_roles', []);

    if (!is_dir($tools_dir)) {
        return;
    }

    foreach (scandir($tools_dir) as $tool) {
        if ($tool === '.' || $tool === '..' || $tool === '_template') {
            continue;
        }

        $tool_path = $tools_dir . '/' . $tool;
        $role      = strtoupper($tool_roles[$tool] ?? 'DISABLED');

        if ($role !== 'ENABLED') {
            continue;
        }

        $init_file = "{$tool_path}/init.php";
        if (file_exists($init_file)) {
            require_once $init_file;
        }
    }
});