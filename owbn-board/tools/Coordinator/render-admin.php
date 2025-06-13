<?php
// File: tools/coordinator/render-admin.php
// @version 1.6.1
// Author: greghacke
// Tool: coordinator

defined('ABSPATH') || exit;

function owbn_coordinator_render_admin_page() {
    $role = owbn_board_get_current_tool_role('coordinator');
    ?>
    <div class="wrap">
        <h2><?php echo esc_html( ucfirst( basename(__DIR__) ) ); ?> Admin Panel</h2>

        <?php if ($role !== 'DISABLED') : ?>
            <p>Welcome! The <strong>Coordinator</strong> tool is currently <code>ENABLED</code>.</p>

            <form method="post" action="">
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">Coordinator Notes</th>
                            <td>
                                <textarea name="coordinator_notes" rows="5" class="large-text"></textarea>
                                <p class="description">Internal documentation or coordinator-specific data.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php if (function_exists('submit_button')) submit_button('Save Coordinator Settings'); ?>
            </form>
        <?php else : ?>
            <p><strong>This tool is currently disabled or not configured correctly.</strong></p>
        <?php endif; ?>
    </div>
    <?php
}