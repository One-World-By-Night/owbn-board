<?php
// File: tools/exec/views/exec.php
// @vesion 0.8.0
// Author: greghacke

defined('ABSPATH') || exit;

function owbn_render_namespace_view_exec($context) {
    $email     = $context['email'];
    $group     = $context['selected_group'] ?? null;
    $groups    = $context['groups'] ?? [];
    $is_admin  = current_user_can('administrator');

    if ($group) {
        $raw_data  = accessSchema_client_remote_get_roles_by_email($email, 'owbn_board');

        if (is_wp_error($raw_data)) {
            echo '<p>Error retrieving user roles: ' . esc_html($raw_data->get_error_message()) . '</p>';
            error_log("[OWBN] ERROR: Failed to retrieve roles for Exec: {$email} — " . $raw_data->get_error_message());
            return;
        }

        $raw_roles = $raw_data['roles'] ?? [];

        $roles = [];
        foreach ($raw_roles as $r) {
            if (is_string($r)) {
                $roles[] = $r;
            } elseif (is_array($r) && isset($r['role']) && is_string($r['role'])) {
                $roles[] = $r['role'];
            } else {
                error_log("Unexpected role format for user {$email}: " . print_r($r, true));
            }
        }

        $base_path        = "Exec/$group"; // ✅ Maintain case
        $matches_base     = in_array($base_path, $roles, true);
        $matches_subroles = !empty(preg_grep('#^' . preg_quote($base_path, '#') . '/#', $roles));
        $has_access       = $matches_base || $matches_subroles;

        if (!$has_access && !$is_admin) {
            echo '<p>You do not have access to this Exec group: <strong>' . esc_html($group) . '</strong></p>';
            return;
        }

        echo '<h2>' . esc_html($group) . ' Exec View</h2>';

        $has_any_access = false;
        $role_check_order = [
            'Coordinator' => 'owbn_render_exec_coordinator_section',
            'Staff'       => 'owbn_render_exec_staff_section',
        ];

        foreach ($role_check_order as $role_key => $render_func) {
            $role_path = "$base_path/$role_key";

            $has_role = (
                in_array($role_path, $roles, true) ||
                ($role_key === 'Staff' && ($matches_base || $matches_subroles))
            );

            if ($has_role) {
                $has_any_access = true;
                $start = array_search($role_key, array_keys($role_check_order));
                $to_render = array_slice($role_check_order, $start);

                foreach ($to_render as $f) {
                    $f($group, $email);
                }

                break;
            }
        }

        if (!$has_any_access && !$is_admin) {
            echo '<p>You have no role-based access to this Exec group: <strong>' . esc_html($group) . '</strong></p>';
            echo '<p>Your Exec roles: <code>' . esc_html(implode(', ', array_filter($roles, fn($r) => str_starts_with($r, "Exec/$group"))) ) . '</code></p>';
        }

        return;
    }

    if ($is_admin) {
        echo '<h2>Select an Exec Group:</h2><ul>';
        foreach ($groups as $g) {
            $url = add_query_arg([
                'page'  => 'owbn-board-exec',
                'group' => $g,
            ], admin_url('admin.php'));
            echo '<li><a href="' . esc_url($url) . '">' . esc_html($g) . '</a></li>';
        }
        echo '</ul>';
    } else {
        echo '<p>You must have access to a specific Exec group to view this page.</p>';
    }
}

function owbn_render_exec_coordinator_section($slug, $email) {
    echo "<div><strong>" . esc_html($slug) . " Exec Coordinator Section</strong></div>";
}

function owbn_render_exec_staff_section($slug, $email) {
    echo "<div><strong>" . esc_html($slug) . " Exec Staff Section</strong></div>";
}