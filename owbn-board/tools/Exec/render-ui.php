<?php
// File: tools/exec/render-ui.php
// @version 0.7.5
// Author: greghacke
// Tool: exec

defined('ABSPATH') || exit;

function owbn_board_render_exec_namespace_page() {
    $email  = wp_get_current_user()->user_email;
    $group  = isset($_GET['group']) ? sanitize_text_field($_GET['group']) : null;
    $groups = owbn_board_get_user_groups_by_tool('Exec');

    // Fallback: extract slug from page param if missing
    if (!$group && isset($_GET['page']) && preg_match('/owbn-board-exec-(.+)/', $_GET['page'], $m)) {
        $slug = $m[1];
        foreach ($groups as $candidate) {
            if (owbn_board_sanitize_group_slug($candidate) === $slug) {
                $group = $candidate;
                break;
            }
        }
    }

    require_once dirname(__FILE__, 3) . '/tools/exec/views/exec.php';
    owbn_render_namespace_view_exec([
        'email'          => $email,
        'selected_group' => $group,
        'groups'         => $groups,
    ]);
}