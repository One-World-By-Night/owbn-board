<?php

// File: accessschema-client/admin-ui.php
// @version 0.7.5
// @author greghacke
// @tool accessschema-client

defined( 'ABSPATH' ) || exit;

add_action('admin_menu', function () {
    add_users_page(
        'AccessSchema Settings',
        'AS OWBN Board',        // <-- Change this per plugin context
        'manage_options',
        'accessschema-client',
        'accessSchema_client_render_admin_page'
    );
});