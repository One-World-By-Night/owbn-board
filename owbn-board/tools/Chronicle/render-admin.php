<?php
// File: tools/_template/render-admin.php
// @version 0.1.0
// @author greghacke
// @tool _template

defined( 'ABSPATH' ) || exit;

function owbn__template_render_admin_page() {
    $role = owbn_board_get_current_tool_role();
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( strtoupper( basename( __DIR__ ) ) ); ?> Admin Interface</h1>

        <?php if ( $role === 'VIEWER' ) : ?>
            <p><em>This tool is in VIEWER mode. Editing is disabled.</em></p>
            <!-- Optional: summary or viewer-specific output -->

        <?php elseif ( $role === 'MAIN' ) : ?>
            <!-- Main interface for managing tool content -->
            <form method="post" action="options.php">
                <?php
                // If needed, insert your tool-specific settings here
                // settings_fields( 'your_option_group' );
                // do_settings_sections( 'your_page_slug' );
                // submit_button();
                ?>
                <p>Main interface controls go here.</p>
            </form>

        <?php else : ?>
            <p><strong>This tool is currently disabled or misconfigured.</strong></p>
        <?php endif; ?>
    </div>
    <?php
}