<?php
/**
 * Errata tile — recent bylaw changes feed.
 *
 * Read-only view of bylaw_clause CPT posts modified in the last N days.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_errata_register_tile() {
	owbn_board_register_tile( [
		'id'         => 'board:errata',
		'title'      => __( 'Recent Bylaw Changes', 'owbn-board' ),
		'icon'       => 'dashicons-book-alt',
		'read_roles' => [], // visible to all authenticated users
		'size'       => '1x2',
		'category'   => 'reference',
		'priority'   => 32,
		'poll_interval' => 15000,
		'render'     => 'owbn_board_render_errata_tile',
	] );
}

function owbn_board_render_errata_tile( $tile, $user_id, $can_write ) {
	if ( ! owbn_board_errata_bylaws_available() ) {
		echo '<p class="owbn-board-errata__empty">' . esc_html__( 'Bylaws are not available on this site.', 'owbn-board' ) . '</p>';
		return;
	}

	// Window is per-user, stored in user meta, defaults to 30 days
	$days = (int) get_user_meta( $user_id, 'owbn_board_errata_window', true );
	if ( $days < 1 ) {
		$days = 30;
	}

	$posts = owbn_board_errata_get_recent( $days, 15 );
	?>
	<div class="owbn-board-errata" data-user-id="<?php echo (int) $user_id; ?>">
		<div class="owbn-board-errata__controls">
			<label class="owbn-board-errata__window-label">
				<?php esc_html_e( 'Window:', 'owbn-board' ); ?>
				<select class="owbn-board-errata__window">
					<?php foreach ( [ 7, 30, 90 ] as $option ) : ?>
						<option value="<?php echo (int) $option; ?>" <?php selected( $days, $option ); ?>>
							<?php
							printf(
								/* translators: %d: number of days */
								esc_html( _n( 'Last %d day', 'Last %d days', $option, 'owbn-board' ) ),
								(int) $option
							);
							?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
		</div>

		<?php if ( empty( $posts ) ) : ?>
			<p class="owbn-board-errata__empty">
				<?php
				printf(
					/* translators: %d: number of days */
					esc_html__( 'No bylaw changes in the last %d days.', 'owbn-board' ),
					(int) $days
				);
				?>
			</p>
		<?php else : ?>
			<ul class="owbn-board-errata__list">
				<?php foreach ( $posts as $post ) :
					$data = owbn_board_errata_format_clause( $post );
					?>
					<li class="owbn-board-errata__item owbn-board-errata__item--<?php echo esc_attr( $data['change'] ); ?>">
						<div class="owbn-board-errata__meta">
							<span class="owbn-board-errata__change-badge">
								<?php echo 'added' === $data['change'] ? esc_html__( 'Added', 'owbn-board' ) : esc_html__( 'Amended', 'owbn-board' ); ?>
							</span>
							<?php if ( ! empty( $data['section'] ) ) : ?>
								<span class="owbn-board-errata__section"><?php echo esc_html( $data['section'] ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $data['group'] ) ) : ?>
								<span class="owbn-board-errata__group"><?php echo esc_html( $data['group'] ); ?></span>
							<?php endif; ?>
							<span class="owbn-board-errata__date">
								<?php echo esc_html( human_time_diff( $data['modified'], time() ) . ' ' . __( 'ago', 'owbn-board' ) ); ?>
							</span>
						</div>
						<a class="owbn-board-errata__title" href="<?php echo esc_url( $data['permalink'] ); ?>">
							<?php echo esc_html( $data['title'] ); ?>
						</a>
						<?php if ( ! empty( $data['vote_url'] ) ) : ?>
							<a class="owbn-board-errata__vote" href="<?php echo esc_url( $data['vote_url'] ); ?>" target="_blank" rel="noopener">
								<?php
								echo esc_html(
									! empty( $data['vote_ref'] )
										? sprintf( __( 'Vote: %s', 'owbn-board' ), $data['vote_ref'] )
										: __( 'View vote', 'owbn-board' )
								);
								?>
							</a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
}
