<?php
// File: accessschema-client/init.php
// @version 1.6.1
// Author: greghacke
// @tool accessschema-client

if (!defined('ABSPATH')) exit;


// Define a constant for the client prefix if not already defined
if (!defined('AS_CLIENT_PREFIX')) {
    define('AS_CLIENT_PREFIX', 'owbn_board'); // or dynamically resolved slug
}

// Include required components
require_once __DIR__ . '/admin-ui.php';
require_once __DIR__ . '/admin-users.php';
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/cpt.php';
require_once __DIR__ . '/fields.php';
require_once __DIR__ . '/hooks.php';
require_once __DIR__ . '/render-admin.php';
require_once __DIR__ . '/render-ui.php';
require_once __DIR__ . '/shortcode.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/webhook.php';