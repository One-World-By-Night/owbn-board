<?php
/**
 * Admin page: OWBN Board > Modules
 *
 * Lists registered internal modules, lets admin enable/disable.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_render_modules_page() {
	if ( ! owbn_board_user_can_manage() ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'owbn-board' ) );
	}

	// Handle form submission (enable/disable)
	if ( isset( $_POST['owbn_board_modules_action'] ) && check_admin_referer( 'owbn_board_modules' ) ) {
		$action    = sanitize_text_field( wp_unslash( $_POST['owbn_board_modules_action'] ) );
		$module_id = isset( $_POST['module_id'] ) ? sanitize_text_field( wp_unslash( $_POST['module_id'] ) ) : '';

		if ( 'enable' === $action && $module_id ) {
			$ok = owbn_board_enable_module( $module_id );
			owbn_board_audit( get_current_user_id(), 'module.enable', 'module', 0, [ 'module_id' => $module_id, 'ok' => $ok ] );
			echo $ok
				? '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Module enabled.', 'owbn-board' ) . '</p></div>'
				: '<div class="notice notice-error"><p>' . esc_html__( 'Could not enable module — check dependencies.', 'owbn-board' ) . '</p></div>';
		} elseif ( 'disable' === $action && $module_id ) {
			$ok = owbn_board_disable_module( $module_id );
			owbn_board_audit( get_current_user_id(), 'module.disable', 'module', 0, [ 'module_id' => $module_id, 'ok' => $ok ] );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Module disabled. Data preserved.', 'owbn-board' ) . '</p></div>';
		}
	}

	owbn_board_discover_modules();
	$modules = owbn_board_get_registered_modules();
	$enabled = owbn_board_get_enabled_modules();
	?>
	<div class="wrap owbn-board-modules">
		<h1><?php esc_html_e( 'OWBN Board — Modules', 'owbn-board' ); ?></h1>
		<p><?php esc_html_e( 'Enable or disable internal LARP tool modules for this site. Disabling preserves data.', 'owbn-board' ); ?></p>

		<?php if ( empty( $modules ) ) : ?>
			<div class="notice notice-info">
				<p><?php esc_html_e( 'No modules registered yet. Add modules to includes/modules/{name}/module.php inside this plugin.', 'owbn-board' ); ?></p>
			</div>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Module', 'owbn-board' ); ?></th>
						<th><?php esc_html_e( 'Description', 'owbn-board' ); ?></th>
						<th><?php esc_html_e( 'Version', 'owbn-board' ); ?></th>
						<th><?php esc_html_e( 'Dependencies', 'owbn-board' ); ?></th>
						<th><?php esc_html_e( 'Status', 'owbn-board' ); ?></th>
						<th><?php esc_html_e( 'Action', 'owbn-board' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $modules as $module_id => $module ) :
						$is_enabled = in_array( $module_id, $enabled, true );
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $module['label'] ); ?></strong><br>
								<code><?php echo esc_html( $module_id ); ?></code>
							</td>
							<td><?php echo esc_html( $module['description'] ); ?></td>
							<td><?php echo esc_html( $module['version'] ); ?></td>
							<td><?php echo esc_html( implode( ', ', (array) $module['depends_on'] ) ?: '—' ); ?></td>
							<td>
								<?php if ( $is_enabled ) : ?>
									<span class="dashicons dashicons-yes" style="color:green;"></span> <?php esc_html_e( 'Enabled', 'owbn-board' ); ?>
								<?php else : ?>
									<span class="dashicons dashicons-minus"></span> <?php esc_html_e( 'Disabled', 'owbn-board' ); ?>
								<?php endif; ?>
							</td>
							<td>
								<form method="post">
									<?php wp_nonce_field( 'owbn_board_modules' ); ?>
									<input type="hidden" name="module_id" value="<?php echo esc_attr( $module_id ); ?>" />
									<?php if ( $is_enabled ) : ?>
										<button type="submit" name="owbn_board_modules_action" value="disable" class="button"><?php esc_html_e( 'Disable', 'owbn-board' ); ?></button>
									<?php else : ?>
										<button type="submit" name="owbn_board_modules_action" value="enable" class="button button-primary"><?php esc_html_e( 'Enable', 'owbn-board' ); ?></button>
									<?php endif; ?>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}
