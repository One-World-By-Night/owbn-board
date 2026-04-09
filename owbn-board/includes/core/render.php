<?php
/**
 * Render the board — main entry point, tile loop, wrapper HTML.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render the full board for the current user.
 *
 * @return string HTML
 */
function owbn_board_render() {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return '<div class="owbn-board-login-required">' . esc_html__( 'Please log in to view the board.', 'owbn-board' ) . '</div>';
	}

	$layout    = owbn_board_get_site_layout();
	$site_slug = owbn_board_get_site_slug();
	$tiles     = owbn_board_get_visible_tiles( $user_id, $site_slug );

	do_action( 'owbn_board_before_render', $user_id );

	ob_start();
	?>
	<div class="owbn-board owbn-board-mode-<?php echo esc_attr( $layout['layout_mode'] ); ?>" data-user-id="<?php echo esc_attr( $user_id ); ?>">
		<?php if ( ! empty( $layout['header_html'] ) ) : ?>
			<div class="owbn-board-header"><?php echo wp_kses_post( $layout['header_html'] ); ?></div>
		<?php endif; ?>

		<?php if ( empty( $tiles ) ) : ?>
			<?php echo owbn_board_render_empty_state( $user_id ); ?>
		<?php else : ?>
			<div class="owbn-board-grid">
				<?php foreach ( $tiles as $tile ) : ?>
					<?php echo owbn_board_render_tile( $tile, $user_id ); ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
	$html = ob_get_clean();

	do_action( 'owbn_board_after_render', $user_id, count( $tiles ) );

	return $html;
}

/**
 * Render a single tile (wrapper + body).
 *
 * @param array $tile
 * @param int   $user_id
 * @return string
 */
function owbn_board_render_tile( array $tile, $user_id ) {
	$size      = $tile['size'];
	list( $cols, $rows ) = explode( 'x', $size );
	$can_write = owbn_board_user_can_write_tile( $tile, $user_id );
	$state     = $tile['_state'] ?? 'default';
	$classes   = [
		'owbn-board-tile',
		'owbn-board-tile--size-' . $size,
		'owbn-board-tile--state-' . $state,
		'owbn-board-tile--category-' . sanitize_html_class( $tile['category'] ),
	];
	if ( ! $can_write ) {
		$classes[] = 'owbn-board-tile--readonly';
	}

	if ( ! empty( $tile['audit'] ) ) {
		owbn_board_audit( $user_id, 'tile.read', 'tile', 0, [ 'tile_id' => $tile['id'] ] );
	}

	ob_start();
	?>
	<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
		data-tile-id="<?php echo esc_attr( $tile['id'] ); ?>"
		data-size="<?php echo esc_attr( $size ); ?>"
		style="grid-column: span <?php echo (int) $cols; ?>; grid-row: span <?php echo (int) $rows; ?>;">
		<div class="owbn-board-tile__header">
			<?php if ( ! empty( $tile['icon'] ) ) : ?>
				<span class="owbn-board-tile__icon <?php echo esc_attr( $tile['icon'] ); ?>" aria-hidden="true"></span>
			<?php endif; ?>
			<h3 class="owbn-board-tile__title"><?php echo esc_html( $tile['title'] ); ?></h3>
			<div class="owbn-board-tile__actions">
				<select class="owbn-board-tile__size-picker" aria-label="<?php esc_attr_e( 'Tile size', 'owbn-board' ); ?>">
					<?php foreach ( owbn_board_allowed_sizes() as $allowed ) : ?>
						<option value="<?php echo esc_attr( $allowed ); ?>" <?php selected( $size, $allowed ); ?>><?php echo esc_html( $allowed ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="owbn-board-tile__action owbn-board-tile__collapse" aria-label="<?php esc_attr_e( 'Collapse', 'owbn-board' ); ?>">&#9650;</button>
				<button type="button" class="owbn-board-tile__action owbn-board-tile__menu" aria-label="<?php esc_attr_e( 'Menu', 'owbn-board' ); ?>">&#8942;</button>
			</div>
		</div>
		<div class="owbn-board-tile__body">
			<?php
			try {
				$start = microtime( true );
				call_user_func( $tile['render'], $tile, $user_id, $can_write );
				$elapsed_ms = ( microtime( true ) - $start ) * 1000;
				if ( $elapsed_ms > 200 ) {
					error_log( sprintf( '[owbn-board] Slow tile render: %s took %dms', $tile['id'], $elapsed_ms ) );
				}
			} catch ( Throwable $e ) {
				error_log( sprintf( '[owbn-board] Tile render failed: %s — %s', $tile['id'], $e->getMessage() ) );
				echo '<div class="owbn-board-tile__error">' . esc_html__( 'This tile failed to render.', 'owbn-board' ) . '</div>';
			}
			?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Empty state shown when no tiles are visible to the user.
 *
 * Distinguishes three cases:
 *   1. owbn-core is inactive — the ASC wrapper is missing, the board
 *      can't resolve any roles for anyone. Direct the admin to activate
 *      owbn-core (this is an infrastructure failure, not a user issue).
 *   2. User is logged in but has no roles — they genuinely need to
 *      contact their CM or file a support ticket.
 *   3. User has roles but no tiles are enabled/configured on this site —
 *      they need an admin to configure the layout.
 */
function owbn_board_render_empty_state( $user_id ) {
	$asc_missing = ! function_exists( 'owc_asc_get_user_roles' );
	$user_roles  = $asc_missing ? [] : owbn_board_get_user_roles( $user_id );
	$can_manage  = owbn_board_user_can_manage();

	ob_start();
	?>
	<div class="owbn-board-empty">
		<h2><?php esc_html_e( 'Your workspace is empty', 'owbn-board' ); ?></h2>
		<?php if ( $asc_missing ) : ?>
			<p class="owbn-board-empty__error">
				<?php esc_html_e( 'The board cannot resolve user roles because owbn-core (which provides the accessSchema wrapper) is not active on this site.', 'owbn-board' ); ?>
			</p>
			<?php if ( $can_manage ) : ?>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">
						<?php esc_html_e( 'Open Plugins admin →', 'owbn-board' ); ?>
					</a>
				</p>
			<?php else : ?>
				<p><?php esc_html_e( 'Please ask a site administrator to activate owbn-core.', 'owbn-board' ); ?></p>
			<?php endif; ?>
		<?php elseif ( empty( $user_roles ) ) : ?>
			<p><?php esc_html_e( "You don't have any roles assigned yet. Contact your chronicle's CM or file a support ticket.", 'owbn-board' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( "No tiles are configured for this site yet. Check back soon, or ask an admin to enable tiles in the layout settings.", 'owbn-board' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
