<?php
// File: accessschema-client/fields.php
// @version 1.6.1
// @tool accessschema-client

defined('ABSPATH') || exit;

// Validate host defined the prefix
if (!defined('AS_CLIENT_PREFIX')) {
    wp_die('AccessSchema Client: AS_CLIENT_PREFIX not defined by host plugin.');
}

// Use prefix for all option keys
function as_client_option_key($key) {
    return AS_CLIENT_PREFIX . '_accessschema_' . $key;
}

add_action('admin_init', function () {
    $mode_key = as_client_option_key('mode');
    $url_key  = as_client_option_key('client_url');
    $key_key  = as_client_option_key('client_key');

    register_setting('accessschema_client', $mode_key);
    register_setting('accessschema_client', $url_key, ['sanitize_callback' => 'esc_url_raw']);
    register_setting('accessschema_client', $key_key, ['sanitize_callback' => 'sanitize_text_field']);

    // === Mode Section
    add_settings_section(
        'accessschema_mode_section',
        'AccessSchema Client Mode',
        '__return_null',
        'accessschema-client'
    );

    add_settings_field(
        $mode_key,
        'Connection Mode',
        function () use ($mode_key) {
            $mode = get_option($mode_key, 'remote');
            ?>
            <label style="margin-right: 1rem;">
                <input type="radio" name="<?php echo esc_attr($mode_key); ?>" value="remote" <?php checked($mode, 'remote'); ?> />
                Remote
            </label>
            <label>
                <input type="radio" name="<?php echo esc_attr($mode_key); ?>" value="local" <?php checked($mode, 'local'); ?> />
                Local
            </label>
            <?php
        },
        'accessschema-client',
        'accessschema_mode_section'
    );

    // === Remote API Section
    add_settings_section(
        'accessschema_client_section',
        'Remote API Settings',
        '__return_null',
        'accessschema-client'
    );

    add_settings_field(
        $url_key,
        'Remote AccessSchema URL',
        function () use ($mode_key, $url_key) {
            $mode = get_option($mode_key, 'remote');
            $val  = esc_url(get_option($url_key));
            $style = $mode === 'remote' ? '' : 'style="display:none"';

            echo "<div $style><input type='url' name='" . esc_attr($url_key) . "' value='" . esc_attr($val) . "' class='regular-text' /></div>";
        },
        'accessschema-client',
        'accessschema_client_section'
    );

    add_settings_field(
        $key_key,
        'Remote API Key',
        function () use ($mode_key, $key_key) {
            $mode = get_option($mode_key, 'remote');
            $val  = get_option($key_key);
            $style = $mode === 'remote' ? '' : 'style="display:none"';

            echo "<div $style><input type='text' name='" . esc_attr($key_key) . "' value='" . esc_attr($val) . "' class='regular-text' /></div>";
        },
        'accessschema-client',
        'accessschema_client_section'
    );
});