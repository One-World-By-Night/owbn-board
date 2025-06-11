<?php
// File: tools/coordinator/views/coordinator.php
// @version 0.7.5
// Author: greghacke

defined('ABSPATH') || exit;

function owbn_render_namespace_view_coordinator($context) {
    $email     = $context['email'];
    $group     = $context['selected_group'] ?? null;
    $groups    = $context['groups'] ?? [];
    $is_admin  = current_user_can('administrator');

    if ($group) {

        $raw_data  = accessSchema_client_remote_get_roles_by_email($email);
        $raw_roles = $raw_data['roles'] ?? [];

        $roles = [];
        foreach ($raw_roles as $r) {
            if (is_string($r)) {
                $roles[] = strtolower($r);
            } elseif (is_array($r) && isset($r['role']) && is_string($r['role'])) {
                $roles[] = strtolower($r['role']);
            } else {
                error_log("Unexpected role format for user {$email}: " . print_r($r, true));
            }
        }

        $base_path = strtolower("coordinators/$group");

        // Custom access check: either exact or starts with base path
        $has_access = (
            in_array($base_path, $roles, true) ||
            !empty(preg_grep('#^' . preg_quote($base_path, '#') . '/#', $roles))
        );

        if (!$has_access && !$is_admin) {
            echo '<p>You do not have access to this Coordinator group: <strong>' . esc_html(strtoupper($group)) . '</strong></p>';
            return;
        }

        echo '<h2>' . esc_html(strtoupper($group)) . ' Coordinator View</h2>';

        $has_any_access = false;
        $role_check_order = [
            'Coordinator'      => 'owbn_render_coordinator_main_section',
            'Sub-Coordinators' => 'owbn_render_coordinator_sub_section',
            'Player'           => 'owbn_render_coordinator_player_section',
        ];

        $matches_base_only = in_array($base_path, $roles, true);

        foreach ($role_check_order as $role_key => $render_func) {
            $role_path = strtolower("$base_path/$role_key");

            $has_role = (
                in_array($role_path, $roles, true) ||
                ($role_key === 'Player' &&
                    (
                        in_array("$base_path/player", $roles, true) ||
                        in_array("$base_path/players", $roles, true) ||
                        $matches_base_only
                    )
                )
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

        if (!$has_any_access) {
            echo '<p>You have no role-based access to this Coordinator group: <strong>' . esc_html(strtoupper($group)) . '</strong></p>';
        }

        return;
    }

    echo '<p>You must select a Coordinator group using the menu.</p>';
}

function owbn_render_coordinator_main_section($slug, $email) {
    echo "<div><strong>" . esc_html($slug) . " Coordinator Section</strong></div>";
}

function owbn_render_coordinator_sub_section($slug, $email) {
    echo "<div><strong>" . esc_html($slug) . " Sub-Coordinators Section</strong></div>";
}

function owbn_render_coordinator_player_section($slug, $email) {
    echo "<div><strong>" . esc_html($slug) . " Players Section</strong></div>";
}