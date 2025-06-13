<?php
// File: tools/exec/render-admin.php
// @version 1.6.1
// Author: greghacke
// Tool: exec

defined('ABSPATH') || exit;

function owbn_exec_render_admin_page() {
    $role = owbn_board_get_current_tool_role('exec');
    ?>
    <div class="wrap">
        <h2><?php echo esc_html( ucfirst( basename(__DIR__) ) ); ?> Admin Panel</h2>

        <?php if ($role !== 'DISABLED') : ?>
            <p>Welcome! The <strong>Executive</strong> tool is currently <code>ENABLED</code>.</p>

            <form method="post" action="">
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">Executive Control Flag</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="exec_flag" value="1" />
                                    Enable special executive override
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php if (function_exists('submit_button')) submit_button('Save Executive Settings'); ?>
            </form>
        <?php else : ?>
            <p><strong>This tool is currently disabled or not configured correctly.</strong></p>
        <?php endif; ?>
    </div>
    <?php
}