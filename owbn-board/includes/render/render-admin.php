<?php

// File: includes/render/render-admin.php
// @version 0.7.5
// @author greghacke

defined('ABSPATH') || exit;

// Get the absolute path to a tool's admin render file
function owbn_board_get_tool_admin_render_path($tool) {
    return dirname(__FILE__, 3) . "/tools/{$tool}/render-admin.php";
}

// Admin Config page renderer
function owbn_board_render_settings_page() {
    $tools = owbn_board_discover_tools();
    $roles = get_option('owbn_tool_roles', []);
    ?>
    <div class="wrap">
        <h1>OWBN Coordinator Toolkit – Config</h1>
        <form method="post" action="">
            <?php wp_nonce_field('owbn_tool_roles_save', 'owbn_tool_roles_nonce'); ?>
            <table class="form-table">
                <tbody>
                    <?php foreach ($tools as $tool):
                        $is_enabled = strtolower($roles[$tool] ?? 'disabled') === 'enabled'; ?>
                        <tr>
                            <th scope="row"><?php echo esc_html(ucfirst($tool)); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="owbn_tool_roles[<?php echo esc_attr($tool); ?>]" value="enabled" <?php checked($is_enabled); ?> />
                                    Enabled
                                </label>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php submit_button('Save Settings'); ?>
        </form>
    </div>

    <?php
    // After the form, render admin panels for enabled tools
    foreach ($tools as $tool) {
        if (strtolower($roles[$tool] ?? 'disabled') === 'enabled') {
            $render_path = owbn_board_get_tool_admin_render_path($tool);
            if (file_exists($render_path)) {
                include_once $render_path;
                $render_function = "owbn_{$tool}_render_admin_page";
                if (function_exists($render_function)) {
                    echo '<hr>'; // Optional visual divider between tools
                    call_user_func($render_function);
                } else {
                    echo "<div class='notice notice-error'><p>Missing function <code>{$render_function}()</code> for <strong>{$tool}</strong></p></div>";
                }
            }
        }
    }
}

// Optional placeholder for the landing page
function owbn_board_render_landing_page() {
    ?>
    <div class="wrap">
        <h1>OWBN Board Overview</h1>
        <p>This is the landing page for the OWBN Coordinator Toolkit.</p>
    </div>
    <?php
}