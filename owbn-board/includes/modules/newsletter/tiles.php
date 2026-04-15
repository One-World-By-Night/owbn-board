<?php
/**
 * Newsletter tile — shows the most recent newsletter editions as a link feed.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_newsletter_register_tile() {
	owbn_board_register_tile( [
		'id'         => 'board:newsletter',
		'title'      => __( 'Newsletter', 'owbn-board' ),
		'icon'       => 'dashicons-email',
		'read_roles' => [], // visible to all authenticated users
		'size'       => '1x2',
		'category'   => 'communication',
		'priority'   => 25,
		'poll_interval' => 15000,
		'render'     => 'owbn_board_render_newsletter_tile',
	] );
}

function owbn_board_render_newsletter_tile( $tile, $user_id, $can_write ) {
	$editions = owbn_board_newsletter_get_editions( 5 );

	if ( empty( $editions ) ) {
		echo '<p class="owbn-board-newsletter__empty">' . esc_html__( 'No newsletter editions yet.', 'owbn-board' ) . '</p>';
		return;
	}
	?>
	<ul class="owbn-board-newsletter">
		<?php foreach ( $editions as $edition ) : ?>
			<li class="owbn-board-newsletter__item">
				<?php if ( ! empty( $edition->cover_image_id ) ) : ?>
					<?php $thumb = wp_get_attachment_image_url( (int) $edition->cover_image_id, 'thumbnail' ); ?>
					<?php if ( $thumb ) : ?>
						<img class="owbn-board-newsletter__thumb" src="<?php echo esc_url( $thumb ); ?>" alt="" />
					<?php endif; ?>
				<?php endif; ?>
				<div class="owbn-board-newsletter__body">
					<a class="owbn-board-newsletter__title" href="<?php echo esc_url( $edition->url ); ?>" target="_blank" rel="noopener">
						<?php echo esc_html( $edition->title ); ?>
					</a>
					<div class="owbn-board-newsletter__meta">
						<time datetime="<?php echo esc_attr( $edition->published_at ); ?>">
							<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $edition->published_at ) ) ); ?>
						</time>
					</div>
					<?php if ( ! empty( $edition->summary ) ) : ?>
						<p class="owbn-board-newsletter__summary"><?php echo esc_html( $edition->summary ); ?></p>
					<?php endif; ?>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php if ( owbn_board_user_can_manage() ) : ?>
		<p class="owbn-board-newsletter__manage">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=owbn-board-newsletter' ) ); ?>">
				<?php esc_html_e( 'Manage editions →', 'owbn-board' ); ?>
			</a>
		</p>
	<?php endif;
}
