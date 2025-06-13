<?php
// File: accessschema-client/shortcode.php
// @vesion 0.8.0
// Author: greghacke

defined('ABSPATH') || exit;

add_action('init', function () {
    $slugs = apply_filters('accessschema_registered_slugs', []);

    foreach ($slugs as $slug => $label) {
        add_shortcode("access_schema_{$slug}", function ($atts, $content = null) use ($slug) {
            if (!is_user_logged_in()) return '';

            $user = wp_get_current_user();

            $atts = shortcode_atts([
                'role'     => '',       // Single role path (exact or pattern)
                'any'      => '',       // Comma-separated list of paths/patterns
                'wildcard' => 'false',  // true/false for wildcard/glob mode
                'fallback' => '',       // Optional fallback if user doesn't match
            ], $atts, "access_schema_{$slug}");

            $wildcard = filter_var($atts['wildcard'], FILTER_VALIDATE_BOOLEAN);
            $email = $user->user_email;

            // === Handle 'any' multiple patterns ===
            if (!empty($atts['any'])) {
                $patterns = array_map('trim', explode(',', $atts['any']));
                if (accessSchema_remote_user_matches_any($email, $patterns, $slug)) {
                    return do_shortcode($content);
                }
                return $atts['fallback'] ?? '';
            }

            // === Handle single role ===
            $role = trim($atts['role']);
            if (!$role) return '';

            if ($wildcard) {
                if (accessSchema_roles_match_pattern_from_email($email, $role, $slug)) {
                    return do_shortcode($content);
                }
            } else {
                $granted = accessSchema_client_remote_check_access($email, $role, false, $slug);
                if (!is_wp_error($granted) && $granted) {
                    return do_shortcode($content);
                }
            }

            return $atts['fallback'] ?? '';
        });
    }
});