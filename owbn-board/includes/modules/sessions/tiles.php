<?php
/**
 * Sessions tile — recent session log for the user's chronicle.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_sessions_register_tile() {
	owbn_board_register_tile( [
		'id'          => 'board:sessions',
		'title'       => __( 'Recent Sessions', 'owbn-board' ),
		'icon'        => 'dashicons-book-alt',
		'read_roles'  => [ 'chronicle/*/staff', 'chronicle/*/cm', 'chronicle/*/hst' ],
		'write_roles' => [ 'chronicle/*/staff', 'chronicle/*/cm', 'chronicle/*/hst' ],
		'size'        => '2x2',
		'category'    => 'communication',
		'priority'    => 18,
		'render'      => 'owbn_board_render_sessions_tile',
	] );
}

function owbn_board_render_sessions_tile( $tile, $user_id, $can_write ) {
	$slugs = owbn_board_sessions_user_chronicle_slugs( $user_id );

	if ( empty( $slugs ) ) {
		echo '<p class="owbn-board-sessions__empty">' . esc_html__( 'No chronicle scope found for your roles.', 'owbn-board' ) . '</p>';
		return;
	}

	$slug     = $slugs[0]; // primary chronicle
	$sessions = owbn_board_sessions_get_by_chronicle( $slug, 5 );
	?>
	<div class="owbn-board-sessions" data-chronicle="<?php echo esc_attr( $slug ); ?>">
		<div class="owbn-board-sessions__header">
			<span class="owbn-board-sessions__scope"><code><?php echo esc_html( $slug ); ?></code></span>
			<?php if ( count( $slugs ) > 1 ) : ?>
				<span class="owbn-board-sessions__scope-count">
					<?php printf( esc_html__( '(+%d other chronicles)', 'owbn-board' ), count( $slugs ) - 1 ); ?>
				</span>
			<?php endif; ?>
			<?php if ( $can_write ) : ?>
				<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=owbn-board-sessions&action=new&chronicle=' . rawurlencode( $slug ) ) ); ?>">
					<?php esc_html_e( 'New Session', 'owbn-board' ); ?>
				</a>
			<?php endif; ?>
		</div>

		<?php if ( empty( $sessions ) ) : ?>
			<p class="owbn-board-sessions__empty"><?php esc_html_e( 'No sessions logged yet.', 'owbn-board' ); ?></p>
		<?php else : ?>
			<ul class="owbn-board-sessions__list">
				<?php foreach ( $sessions as $session ) : ?>
					<?php
					$date  = wp_date( get_option( 'date_format' ), strtotime( $session->session_date ) );
					$title = $session->title ?: __( '(untitled)', 'owbn-board' );
					$url   = admin_url( 'admin.php?page=owbn-board-sessions&action=edit&session=' . (int) $session->id );
					?>
					<li class="owbn-board-sessions__item">
						<?php if ( $can_write ) : ?>
							<a href="<?php echo esc_url( $url ); ?>" class="owbn-board-sessions__title">
								<strong><?php echo esc_html( $title ); ?></strong>
							</a>
						<?php else : ?>
							<strong><?php echo esc_html( $title ); ?></strong>
						<?php endif; ?>
						<span class="owbn-board-sessions__date"><?php echo esc_html( $date ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
}
