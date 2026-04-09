<?php
/**
 * Tile Access — admin page.
 *
 * OWBN Board > Tile Access. One row per registered tile, with three
 * multi-line inputs: Accessible to, Can edit, Share Level. Share Level
 * is grayed out for tiles that don't declare supports_share_level.
 *
 * Reads/writes via owbn_board_tile_access_get_config() and the
 * owbn_board_tile_access_save AJAX endpoint. Storage is the existing
 * owbn_board_layout option.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_tile_access_register_admin() {
	add_submenu_page(
		'owbn-board',
		__( 'Tile Access', 'owbn-board' ),
		__( 'Tile Access', 'owbn-board' ),
		'manage_options',
		'owbn-board-tile-access',
		'owbn_board_tile_access_render_page'
	);
}

function owbn_board_tile_access_render_page() {
	if ( ! owbn_board_user_can_manage() ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'owbn-board' ) );
	}

	// Fire tile registration so we see everything.
	do_action( 'owbn_board_register_tiles' );

	$tiles = owbn_board_get_registered_tiles();

	if ( function_exists( 'owbn_board_enqueue_assets' ) ) {
		owbn_board_enqueue_assets();
	}
	?>
	<div class="wrap owbn-board-tile-access">
		<h1><?php esc_html_e( 'OWBN Board — Tile Access', 'owbn-board' ); ?></h1>
		<p class="description">
			<?php esc_html_e( 'Override who can see and edit each tile, and how its content is scoped into groups. Fields left unchanged use the tile\'s registered defaults. Each input takes one role pattern per line (e.g. chronicle/*/cm). * is a single-segment wildcard.', 'owbn-board' ); ?>
		</p>

		<?php if ( empty( $tiles ) ) : ?>
			<div class="notice notice-warning">
				<p><?php esc_html_e( 'No tiles registered yet. Activate modules or install OWBN plugins that contribute tiles.', 'owbn-board' ); ?></p>
			</div>
		<?php else : ?>
			<div class="owbn-board-tile-access__grid">
				<?php foreach ( $tiles as $tile_id => $tile ) :
					$config          = owbn_board_tile_access_get_config( $tile_id );
					$supports_share  = ! empty( $config['supports_share'] );
					$read_text       = implode( "\n", (array) $config['read_roles'] );
					$write_text      = implode( "\n", (array) $config['write_roles'] );
					$share_text      = implode( "\n", (array) $config['share_level'] );
					?>
					<section class="owbn-board-tile-access__card" data-tile-id="<?php echo esc_attr( $tile_id ); ?>">
						<header class="owbn-board-tile-access__header">
							<h2 class="owbn-board-tile-access__title">
								<?php echo esc_html( $tile['title'] ); ?>
								<code><?php echo esc_html( $tile_id ); ?></code>
							</h2>
							<span class="owbn-board-tile-access__category">
								<?php echo esc_html( $tile['category'] ); ?>
							</span>
						</header>

						<div class="owbn-board-tile-access__fields">
							<label class="owbn-board-tile-access__field">
								<span class="owbn-board-tile-access__label">
									<?php esc_html_e( 'Accessible to (read)', 'owbn-board' ); ?>
									<?php if ( $config['has_read_override'] ) : ?>
										<em class="owbn-board-tile-access__override-flag"><?php esc_html_e( 'overridden', 'owbn-board' ); ?></em>
									<?php endif; ?>
								</span>
								<textarea
									class="owbn-board-tile-access__input owbn-board-tile-access__read"
									rows="4"
									placeholder="chronicle/*/*&#10;exec/*"><?php echo esc_textarea( $read_text ); ?></textarea>
							</label>

							<label class="owbn-board-tile-access__field">
								<span class="owbn-board-tile-access__label">
									<?php esc_html_e( 'Can edit (write)', 'owbn-board' ); ?>
									<?php if ( $config['has_write_override'] ) : ?>
										<em class="owbn-board-tile-access__override-flag"><?php esc_html_e( 'overridden', 'owbn-board' ); ?></em>
									<?php endif; ?>
								</span>
								<textarea
									class="owbn-board-tile-access__input owbn-board-tile-access__write"
									rows="4"
									placeholder="chronicle/*/cm&#10;chronicle/*/hst"><?php echo esc_textarea( $write_text ); ?></textarea>
							</label>

							<label class="owbn-board-tile-access__field<?php echo $supports_share ? '' : ' owbn-board-tile-access__field--disabled'; ?>">
								<span class="owbn-board-tile-access__label">
									<?php esc_html_e( 'Share Level (scope)', 'owbn-board' ); ?>
									<?php if ( ! $supports_share ) : ?>
										<em class="owbn-board-tile-access__hint"><?php esc_html_e( 'this tile doesn\'t use scoping', 'owbn-board' ); ?></em>
									<?php elseif ( $config['has_share_override'] ) : ?>
										<em class="owbn-board-tile-access__override-flag"><?php esc_html_e( 'set', 'owbn-board' ); ?></em>
									<?php endif; ?>
								</span>
								<textarea
									class="owbn-board-tile-access__input owbn-board-tile-access__share"
									rows="3"
									<?php disabled( ! $supports_share ); ?>
									placeholder="chronicle/*/*&#10;coordinator/*/*"><?php echo esc_textarea( $share_text ); ?></textarea>
							</label>
						</div>

						<footer class="owbn-board-tile-access__footer">
							<button type="button" class="button button-primary owbn-board-tile-access__save">
								<?php esc_html_e( 'Save', 'owbn-board' ); ?>
							</button>
							<button type="button" class="button-link owbn-board-tile-access__reset">
								<?php esc_html_e( 'Reset to defaults', 'owbn-board' ); ?>
							</button>
							<span class="owbn-board-tile-access__status" aria-live="polite"></span>
						</footer>
					</section>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
