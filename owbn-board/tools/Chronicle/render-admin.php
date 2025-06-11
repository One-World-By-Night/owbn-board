<?php
// File: tools/chronicle/render-admin.php
// @version 0.7.5
// Author: greghacke
// Tool: chronicle

defined('ABSPATH') || exit;

function owbn_chronicle_render_admin_page() {
    $role = owbn_board_get_current_tool_role('chronicle');
    ?>
    <div class="wrap">
        <h2><?php echo esc_html( ucfirst( basename(__DIR__) ) ); ?> Admin Panel</h2>

        <?php if ($role !== 'DISABLED') : ?>
            <p>Welcome! This section is enabled and ready for Chronicle-specific settings.</p>

            <form method="post" action="">
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">Example Field</th>
                            <td>
                                <input type="text" name="chronicle_example" value="" class="regular-text" />
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php if (function_exists('submit_button')) submit_button('Save Chronicle Settings'); ?>
            </form>
        <?php else : ?>
            <p><strong>This tool is currently disabled or not configured correctly.</strong></p>
        <?php endif; ?>
    </div>
    <?php
}