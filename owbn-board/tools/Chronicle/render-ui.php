<?php

// File: tools/chronicle/render-ui.php
// @version 0.7.5
// @author greghacke
// @tool chronicle

defined( 'ABSPATH' ) || exit;

function owbn_board_render_chronicle_namespace_page() {
    $email  = wp_get_current_user()->user_email;
    $group  = isset($_GET['group']) ? sanitize_text_field($_GET['group']) : null;
    $groups = owbn_board_get_user_groups_by_tool('Chronicles');

    // Fallback: extract sanitized group slug and map it back to the original
    if (!$group && isset($_GET['page']) && preg_match('/owbn-board-chronicle-(.+)/', $_GET['page'], $m)) {
        $slug = $m[1];
        foreach ($groups as $candidate) {
            if (owbn_board_sanitize_group_slug($candidate) === $slug) {
                $group = $candidate;
                break;
            }
        }
    }

    require_once dirname(__FILE__, 3) . '/tools/chronicle/views/chronicle.php';
    owbn_render_namespace_view_chronicles([
        'email' => $email,
        'selected_group' => $group,
        'groups' => $groups,
    ]);
}