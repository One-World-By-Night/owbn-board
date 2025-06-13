<?php
// File: accessschema-client/hooks.php
// @version 1.6.1
// @tool accessschema-client

defined('ABSPATH') || exit;


if (!function_exists('as_client_option_key')) {
    /**
     * Generate option key name scoped to plugin slug.
     * @param string $slug The unique plugin slug.
     * @param string $key  The setting key.
     * @return string Fully qualified option key.
     */
    function as_client_option_key($slug, $key) {
        return "{$slug}_accessschema_{$key}";
    }
}

if (!function_exists('accessSchema_is_remote_mode')) {
    /**
     * Check if AccessSchema is in remote mode.
     *
     * @param string $slug The unique plugin slug.
     * @return bool
     */
    function accessSchema_is_remote_mode($slug) {
        return get_option(as_client_option_key($slug, 'mode'), 'remote') === 'remote';
    }
}

if (!function_exists('accessSchema_client_get_remote_url')) {
    /**
     * Get the stored remote AccessSchema URL.
     *
     * @param string $slug The unique plugin slug.
     * @return string
     */
    function accessSchema_client_get_remote_url($slug) {
        $url = trim(get_option("{$slug}_accessschema_client_url"));
        return rtrim($url, '/');
    }
}


if (!function_exists('accessSchema_client_get_remote_key')) {
    /**
     * Get the stored remote AccessSchema API key.
     *
     * @param string $slug The unique plugin slug.
     * @return string
     */
    function accessSchema_client_get_remote_key($slug) {
        return trim(get_option("{$slug}_accessschema_client_key"));
    }
}

/* ---------------------------------------------------------------------------------------------- */
if (!function_exists('accessSchema_client_remote_post')) {
    /**
     * Send a POST request to the AccessSchema API endpoint.
     *
     * @param string $slug     The unique plugin slug.
     * @param string $endpoint The API endpoint path (e.g., 'roles', 'grant', 'revoke').
     * @param array  $body     JSON body parameters.
     * @return array|WP_Error  Response array or error.
     */
    function accessSchema_client_remote_post($slug, $endpoint, array $body) {
        $url_base = accessSchema_client_get_remote_url($slug);
        $key      = accessSchema_client_get_remote_key($slug);

        if (!$url_base || !$key) {
            return new WP_Error('config_error', 'Remote URL or API key is not set for plugin: ' . esc_html($slug));
        }

        $url = trailingslashit($url_base) . ltrim($endpoint, '/');

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key'    => $key,
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $data   = json_decode(wp_remote_retrieve_body($response), true);

        if ($status !== 200 && $status !== 201) {
            return new WP_Error('api_error', 'Remote API returned HTTP ' . $status, $data);
        }

        return $data;
    }
}

if (!function_exists('accessSchema_client_remote_get_roles_by_email')) {
    /**
     * Get remote roles for a user by email (with caching).
     *
     * @param string $email The user's email address.
     * @param string $slug  The unique plugin slug.
     * @return array|WP_Error Response array or WP_Error on failure.
     */
    function accessSchema_client_remote_get_roles_by_email($email, $slug) {
        $user = get_user_by('email', $email);

        if (!accessSchema_is_remote_mode($slug)) {
            if (!$user) {
                return new WP_Error('user_not_found', 'User not found.', ['status' => 404]);
            }

            $response = accessSchema_client_local_post('roles', [
                'email' => sanitize_email($email),
            ]);

            return $response;
        }

        // REMOTE MODE — check user meta cache
        if ($user) {
            $cache_key = "{$slug}_accessschema_cached_roles";
            $cached = get_user_meta($user->ID, $cache_key, true);

            if (!empty($cached) && is_array($cached)) {
                return ['roles' => $cached];
            }
        }

        // Remote API fallback
        $response = accessSchema_client_remote_post($slug, 'roles', [
            'email' => sanitize_email($email),
        ]);

        if (!is_wp_error($response) && isset($response['roles']) && $user) {
            update_user_meta($user->ID, "{$slug}_accessschema_cached_roles", $response['roles']);
            update_user_meta($user->ID, "{$slug}_accessschema_cached_roles_timestamp", time());
        }

        return $response;
    }
}

if (!function_exists('accessSchema_client_remote_grant_role')) {
    /**
     * Grant a role to a user on the remote system (or local override).
     *
     * @param string $email      The user's email.
     * @param string $role_path  The role path to grant.
     * @param string $slug       The unique plugin slug.
     * @return array|WP_Error    API response or error.
     */
    function accessSchema_client_remote_grant_role($email, $role_path, $slug) {
        $user = get_user_by('email', $email);

        $payload = [
            'email'     => sanitize_email($email),
            'role_path' => sanitize_text_field($role_path),
        ];

        if (!accessSchema_is_remote_mode($slug)) {
            $result = accessSchema_client_local_post('grant', $payload);
        } else {
            $result = accessSchema_client_remote_post($slug, 'grant', $payload);
        }

        // Invalidate plugin-specific cache
        if ($user) {
            delete_user_meta($user->ID, "{$slug}_accessschema_cached_roles");
            delete_user_meta($user->ID, "{$slug}_accessschema_cached_roles_timestamp");
        }

        return $result;
    }
}

if (!function_exists('accessSchema_client_remote_revoke_role')) {
    /**
     * Revoke a role from a user on the remote (or local) system.
     *
     * @param string $email      The user's email.
     * @param string $role_path  The role path to revoke.
     * @param string $slug       The unique plugin slug.
     * @return array|WP_Error    API response or error.
     */
    function accessSchema_client_remote_revoke_role($email, $role_path, $slug) {
        $user = get_user_by('email', $email);

        $payload = [
            'email'     => sanitize_email($email),
            'role_path' => sanitize_text_field($role_path),
        ];

        if (!accessSchema_is_remote_mode($slug)) {
            $result = accessSchema_client_local_post('revoke', $payload);
        } else {
            $result = accessSchema_client_remote_post($slug, 'revoke', $payload);
        }

        // Invalidate plugin-specific cache
        if ($user) {
            delete_user_meta($user->ID, "{$slug}_accessschema_cached_roles");
            delete_user_meta($user->ID, "{$slug}_accessschema_cached_roles_timestamp");
        }

        return $result;
    }
}

if (!function_exists('accessSchema_refresh_roles_for_user')) {
    /**
     * Refresh roles for a user by fetching from the configured AccessSchema source.
     *
     * @param WP_User $user The user object.
     * @param string  $slug Unique plugin slug for isolation.
     * @return array|WP_Error The roles array or an error.
     */
    function accessSchema_refresh_roles_for_user($user, $slug) {
        if ($user instanceof WP_User) {
            $email = $user->user_email;

            // Fetch roles using the plugin-specific slug
            $roles = accessSchema_client_remote_get_roles_by_email($email, $slug);

            if (!is_wp_error($roles) && isset($roles['roles'])) {
                update_user_meta($user->ID, "{$slug}_accessschema_cached_roles", $roles['roles']);
                update_user_meta($user->ID, "{$slug}_accessschema_cached_roles_timestamp", time());
                return $roles;
            }
        }

        return new WP_Error('refresh_failed', 'Could not refresh roles.');
    }
}

if (!function_exists('accessSchema_client_remote_check_access')) {
    /**
     * Check if a user has a role or descendant, with slug-specific configuration.
     *
     * @param string  $email             The user's email address.
     * @param string  $role_path         The full role path to check.
     * @param string  $slug              Unique plugin slug.
     * @param boolean $include_children  Whether to include children of the role.
     * @return bool|WP_Error             True if granted, false otherwise, or WP_Error on failure.
     */
    function accessSchema_client_remote_check_access($email, $role_path, $slug, $include_children = true) {
        $payload = [
            'email'            => sanitize_email($email),
            'role_path'        => sanitize_text_field($role_path),
            'include_children' => $include_children,
        ];

        if (!accessSchema_is_remote_mode($slug)) {
            $data = accessSchema_client_local_post('check', $payload);
        } else {
            $data = accessSchema_client_remote_post('check', $payload, $slug);
        }

        if (is_wp_error($data)) {
            return $data;
        }

        return !empty($data['granted']);
    }
}

if (!function_exists('accessSchema_client_local_post')) {
    /**
     * Local API endpoint handler for client-side requests.
     *
     * @param string $endpoint The endpoint to call (e.g., 'roles', 'grant', 'revoke', 'check').
     * @param array $body The request body parameters.
     * @return array|WP_Error The response data or error.
     */
    function accessSchema_client_local_post($endpoint, array $body) {
        $request = new WP_REST_Request('POST', '/access-schema/v1/' . ltrim($endpoint, '/'));
        $request->set_body_params($body);

        $function_map = [
            'roles'  => 'accessSchema_api_get_roles',
            'grant'  => 'accessSchema_api_grant_role',
            'revoke' => 'accessSchema_api_revoke_role',
            'check'  => 'accessSchema_api_check_permission',
        ];

        if (!isset($function_map[$endpoint])) {
            return new WP_Error('invalid_local_endpoint', 'Unrecognized local endpoint.');
        }

        $response = call_user_func($function_map[$endpoint], $request);

        if ($response instanceof WP_Error) {
            return $response;
        }

        return $response->get_data();
    }
}

do_action('accessSchema_client_ready');