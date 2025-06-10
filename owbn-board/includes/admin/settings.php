<?php
// File: includes/admin/settings.php
// @version 0.1.0
// Author: greghacke

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
    add_menu_page(
        'OWBN Board',
        'OWBN Board',
        'read',
        'owbn-board',
        'owbn_board_render_landing_page',
        'dashicons-admin-generic',
        3
    );

    add_submenu_page(
        'owbn-board',
        'Toolkit Config',
        'Config',
        'manage_options',
        'owbn-board-config',
        'owbn_board_render_settings_page'
    );

    $tools = owbn_board_discover_tools();

    // Define explicit order under "Config"
    $priority = 11; // Start after the default submenu (which gets 10)

    foreach ( $tools as $tool ) {
        $const = strtoupper( "OWBN_{$tool}_ROLE" );

        if ( defined( $const ) && constant( $const ) !== 'DISABLED' ) {
            add_submenu_page(
                'owbn-board',                                     // Parent slug
                strtoupper( $tool ),                              // Page title
                strtoupper( str_replace( '-', ' ', $tool ) ),     // Menu label
                'manage_options',                                 // Capability
                "owbn-board-{$tool}",                             // Menu slug
                'owbn_render_user_tool_page',                     // Callback
                $priority++                                       // Optional: rank them under Config
            );
        }
    }

    $tool_map = owbn_board_get_user_tools_with_groups();
    $user_tools_priority = 101;

    foreach ( $tool_map as $tool => $groups ) {
        foreach ( $groups as $group ) {
            $menu_label = $group;
            $menu_slug  = 'owbn-board-' . sanitize_title( $group );

            // Build the submenu and ensure the `page` param matches view file name (e.g., chronicles)
            add_submenu_page(
                'owbn-board',
                $group,
                $menu_label,
                'read',
                $menu_slug,
                function () use ( $tool, $group, $groups ) {
                    // Override $_GET manually for proper routing
                    $_GET['group'] = $group;
                    $_GET['page']  = 'owbn-board-' . $tool;

                    $user  = wp_get_current_user();
                    $email = $user->user_email;

                    $context = [
                        'email'          => $email,
                        'groups'         => $groups,
                        'selected_group' => $group,
                    ];

                    $view = plugin_dir_path(__FILE__) . "../render/views/{$tool}.php";
                    if ( file_exists( $view ) ) {
                        require_once $view;
                        $func = "owbn_render_namespace_view_{$tool}";
                        if ( function_exists( $func ) ) {
                            $func( $context );
                        } else {
                            echo '<p>Render function missing for ' . esc_html( $tool ) . '</p>';
                        }
                    } else {
                        echo '<p>View not found for ' . esc_html( $tool ) . '</p>';
                    }
                },
                $user_tools_priority++
            );
        }
    }
});

add_action( 'admin_init', function () {
    register_setting( 'owbn_board_settings', 'owbn_tool_roles', [
        'type' => 'array',
        'sanitize_callback' => 'owbn_sanitize_tool_roles',
    ] );

    add_settings_section(
        'owbn_tool_roles_section',
        'Tool Role Configuration',
        function () {
            echo '<p>Set each tool to MAIN, VIEWER, or DISABLED.</p>';
        },
        'owbn-board-config'
    );

    $tools = owbn_board_discover_tools();

    foreach ( $tools as $tool ) {
        add_settings_field(
            "owbn_tool_role_$tool",
            strtoupper( $tool ),
            function () use ( $tool ) {
                $roles = get_option( 'owbn_tool_roles', [] );
                $value = $roles[ $tool ] ?? 'DISABLED';
                echo '<select name="owbn_tool_roles[' . esc_attr( $tool ) . ']">';
                echo '<option value="MAIN"' . selected( $value, 'MAIN', false ) . '>MAIN</option>';
                echo '<option value="VIEWER"' . selected( $value, 'VIEWER', false ) . '>VIEWER</option>';
                echo '<option value="DISABLED"' . selected( $value, 'DISABLED', false ) . '>DISABLED</option>';
                echo '</select>';
            },
            'owbn-board-config',
            'owbn_tool_roles_section'
        );
    }
});

function owbn_board_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>OWBN Coordinator Toolkit – Config</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'owbn_board_settings' );
            do_settings_sections( 'owbn-board-config' );
            submit_button( 'Save Toolkit Settings' );
            ?>
        </form>
    </div>
    <?php
}