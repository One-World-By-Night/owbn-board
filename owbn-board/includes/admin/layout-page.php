<?php
/**
 * Admin page: OWBN Board > Layout
 *
 * Lists all registered tiles, lets admin enable/disable, reorder, set size.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_render_layout_page() {
	if ( ! owbn_board_user_can_manage() ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'owbn-board' ) );
	}

	// Fire tile registration so we see everything
	do_action( 'owbn_board_register_tiles' );

	$tiles  = owbn_board_get_registered_tiles();
	$layout = owbn_board_get_site_layout();

	owbn_board_enqueue_assets();
	?>
	<div class="wrap owbn-board-admin">
		<h1><?php esc_html_e( 'OWBN Board — Layout', 'owbn-board' ); ?></h1>
		<p><?php esc_html_e( 'Enable, disable, reorder, and resize tiles for this site. Changes save automatically.', 'owbn-board' ); ?></p>

		<?php if ( empty( $tiles ) ) : ?>
			<div class="notice notice-warning">
				<p><?php esc_html_e( 'No tiles registered yet. Activate modules or install OWBN plugins that contribute tiles.', 'owbn-board' ); ?></p>
			</div>
		<?php else : ?>
			<table class="widefat striped owbn-board-admin__tiles">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Enabled', 'owbn-board' ); ?></th>
						<th><?php esc_html_e( 'Tile', 'owbn-board' ); ?></th>
						<th><?php esc_html_e( 'Category', 'owbn-board' ); ?></th>
						<th><?php esc_html_e( 'Size', 'owbn-board' ); ?></th>
						<th><?php esc_html_e( 'Priority', 'owbn-board' ); ?></th>
						<th><?php esc_html_e( 'Read Roles', 'owbn-board' ); ?></th>
					</tr>
				</thead>
				<tbody id="owbn-board-tiles-body">
					<?php foreach ( $tiles as $tile_id => $tile ) :
						$entry    = $layout['tiles'][ $tile_id ] ?? [];
						$enabled  = isset( $entry['enabled'] ) ? (bool) $entry['enabled'] : true;
						$size     = $entry['size'] ?? $tile['size'];
						$priority = $entry['priority'] ?? $tile['priority'];
						?>
						<tr data-tile-id="<?php echo esc_attr( $tile_id ); ?>">
							<td>
								<input type="checkbox" class="owbn-board-admin__enable" <?php checked( $enabled ); ?> />
							</td>
							<td>
								<strong><?php echo esc_html( $tile['title'] ); ?></strong><br>
								<code><?php echo esc_html( $tile_id ); ?></code>
							</td>
							<td><?php echo esc_html( $tile['category'] ); ?></td>
							<td>
								<select class="owbn-board-admin__size">
									<?php foreach ( owbn_board_allowed_sizes() as $allowed_size ) : ?>
										<option value="<?php echo esc_attr( $allowed_size ); ?>" <?php selected( $size, $allowed_size ); ?>><?php echo esc_html( $allowed_size ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<input type="number" class="owbn-board-admin__priority" value="<?php echo esc_attr( $priority ); ?>" min="0" max="999" step="1" style="width:60px;" />
							</td>
							<td>
								<?php echo esc_html( implode( ', ', (array) $tile['read_roles'] ) ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p class="owbn-board-admin__status" aria-live="polite"></p>
		<?php endif; ?>
	</div>
	<?php
}
