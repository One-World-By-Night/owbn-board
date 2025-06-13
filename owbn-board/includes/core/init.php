<?php
// File: includes/core/init.php
// @version 1.6.1
// @author greghacke

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/webhook-router.php';
require_once __DIR__ . '/bootstrap-tools.php';

/**
 * Load enabled tools and define role constants.
 */
function owbn_coordinator_toolkit_init() {
    $tool_config = get_option( 'owbn_toolkit_tools', [] );

    foreach ( $tool_config as $tool => $role ) {
        if ( empty( $role ) || $role === 'DISABLED' ) {
            continue;
        }

        $tool_dir = plugin_dir_path( __DIR__ ) . "../tools/{$tool}/";

        if ( file_exists( $tool_dir . 'init.php' ) ) {
            // Define role constant before require so init.php can access it
            define( strtoupper( "OWBN_{$tool}_ROLE" ), strtoupper( $role ) );
            require_once $tool_dir . 'init.php';
        }
    }
}
add_action( 'plugins_loaded', 'owbn_coordinator_toolkit_init' );

/**
 * Ensure admin users get owbn_coord_staff by default.
 */
add_action( 'init', function () {
    $admin = get_role( 'administrator' );
    if ( $admin && ! $admin->has_cap( 'owbn_coord_staff' ) ) {
        $admin->add_cap( 'owbn_coord_staff' );
    }
}, 11 );