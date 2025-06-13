<?php
// File: tools/chronicle/render-admin.php
// @vesion 0.8.0
// Author: greghacke
// Tool: chronicle

defined('ABSPATH') || exit;

function owbn_chronicle_render_admin_page() {
    $role = owbn_board_get_current_tool_role('chronicle');

    // Load current options
    $api_url = get_option('owbn_chronicle_manager_api_url', '');
    $api_key = get_option('owbn_chronicle_manager_api_key', '');

    // Save if posted
    if (
        isset($_POST['owbn_chronicle_settings_submit']) &&
        check_admin_referer('owbn_chronicle_settings_action', 'owbn_chronicle_settings_nonce')
    ) {
        $new_url = isset($_POST['owbn_chronicle_manager_api_url']) ? sanitize_text_field(wp_unslash($_POST['owbn_chronicle_manager_api_url'])) : '';
        $new_key = isset($_POST['owbn_chronicle_manager_api_key']) ? sanitize_text_field(wp_unslash($_POST['owbn_chronicle_manager_api_key'])) : '';

        update_option('owbn_chronicle_manager_api_url', $new_url);
        update_option('owbn_chronicle_manager_api_key', $new_key);

        echo '<div class="notice notice-success is-dismissible"><p>Chronicle Manager settings saved.</p></div>';

        // Refresh local values
        $api_url = $new_url;
        $api_key = $new_key;
    }
    ?>

    <div class="wrap">
        <h2><?php echo esc_html( ucfirst( basename(__DIR__) ) ); ?> Admin Panel</h2>

        <?php if ($role !== 'DISABLED') : ?>
            <p>Welcome! This section is enabled and ready for Chronicle-specific settings.</p>

            <form method="post" action="">
                <?php wp_nonce_field('owbn_chronicle_settings_action', 'owbn_chronicle_settings_nonce'); ?>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="owbn_chronicle_manager_api_url">Chronicle Manager URL</label></th>
                            <td>
                                <input type="url" name="owbn_chronicle_manager_api_url" id="owbn_chronicle_manager_api_url" value="<?php echo esc_attr($api_url); ?>" class="regular-text" placeholder="https://yoursite.com/wp-json/owbn/v1/" />
                                <p class="description">Must include the full base REST API endpoint. Example: <code>https://example.com/wp-json/owbn/v1/</code></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="owbn_chronicle_manager_api_key">Chronicle Manager API Key</label></th>
                            <td>
                                <input type="text" name="owbn_chronicle_manager_api_key" id="owbn_chronicle_manager_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
                                <p class="description">Paste the key required to access protected endpoints, if needed.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button('Save Chronicle Settings', 'primary', 'owbn_chronicle_settings_submit'); ?>
            </form>
        <?php else : ?>
            <p><strong>This tool is currently disabled or not configured correctly.</strong></p>
        <?php endif; ?>
    </div>
    <?php
}