<?php

// File: tools/_template/init.php
// @version 0.1.0
// @author greghacke
// @tool _template

defined( 'ABSPATH' ) || exit;

// Dynamically get tool name from folder name
$tool_slug = basename( __DIR__ );
$tool_role_const = strtoupper( "OWBN_{$tool_slug}_ROLE" );

if ( defined( $tool_role_const ) ) {
    $role = constant( $tool_role_const );

    if ( $role === 'MAIN' ) {
        require_once __DIR__ . '/cpt.php';
        require_once __DIR__ . '/fields.php';
        require_once __DIR__ . '/admin-ui.php';
        require_once __DIR__ . '/hooks.php';
        require_once __DIR__ . '/webhook.php';
        require_once __DIR__ . '/shortcode.php';
        require_once __DIR__ . '/render-admin.php';
        require_once __DIR__ . '/render-ui.php';
    } elseif ( $role === 'VIEWER' ) {
        require_once __DIR__ . '/webhook.php';
        require_once __DIR__ . '/shortcode.php';
        require_once __DIR__ . '/render-admin.php';
        require_once __DIR__ . '/render-ui.php';
    }
}