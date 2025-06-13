<?php

// File: tools/Exec/init.php
// @vesion 0.8.0
// @author greghacke

defined( 'ABSPATH' ) || exit;

// Load all subcomponents of the tool
// 0. Tool-specific utility functions
require_once __DIR__ . '/utils.php';

// 1. Register custom post types (CPTs)
require_once __DIR__ . '/cpt.php';

// 2. Register custom fields
require_once __DIR__ . '/fields.php';

// 3. Admin UI config (menus, pages)
require_once __DIR__ . '/admin-ui.php';

// 4. Hooks and filters
require_once __DIR__ . '/hooks.php';

// 5. Handle incoming remote/webhook data
require_once __DIR__ . '/webhook.php';

// 6. Shortcodes for rendering
require_once __DIR__ . '/shortcode.php';

// 7. Admin UI rendering
require_once __DIR__ . '/render-admin.php';

// 8. User interface rendering (frontend or dashboard)
require_once __DIR__ . '/render-ui.php';