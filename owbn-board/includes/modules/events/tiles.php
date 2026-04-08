<?php
/**
 * Events tile — upcoming approved events with RSVP controls.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_events_register_tile() {
	owbn_board_register_tile( [
		'id'         => 'board:events',
		'title'      => __( 'Upcoming Events', 'owbn-board' ),
		'icon'       => 'dashicons-megaphone',
		'read_roles' => [], // visible to all authenticated users
		'size'       => '2x2',
		'category'   => 'communication',
		'priority'   => 15,
		'render'     => 'owbn_board_render_events_tile',
	] );
}

function owbn_board_render_events_tile( $tile, $user_id, $can_write ) {
	$events = owbn_board_events_get_upcoming( 6 );

	if ( empty( $events ) ) {
		echo '<p class="owbn-board-events__empty">' . esc_html__( 'No upcoming events.', 'owbn-board' ) . '</p>';
		if ( owbn_board_events_user_can_create( $user_id ) ) {
			echo '<p class="owbn-board-events__manage"><a href="' . esc_url( admin_url( 'post-new.php?post_type=owbn_event' ) ) . '">' . esc_html__( 'Create an event →', 'owbn-board' ) . '</a></p>';
		}
		return;
	}
	?>
	<ul class="owbn-board-events">
		<?php foreach ( $events as $event ) :
			$meta      = owbn_board_events_get_meta( $event->ID );
			$start     = ! empty( $meta['start_dt'] ) ? strtotime( $meta['start_dt'] ) : 0;
			$banner_id = ! empty( $meta['banner_image_id'] ) ? (int) $meta['banner_image_id'] : 0;
			$banner    = $banner_id ? wp_get_attachment_image_url( $banner_id, 'medium' ) : '';
			$rsvp      = owbn_board_events_rsvp_get( $event->ID, $user_id );
			?>
			<li class="owbn-board-events__item" data-event-id="<?php echo (int) $event->ID; ?>">
				<?php if ( $banner ) : ?>
					<img class="owbn-board-events__banner" src="<?php echo esc_url( $banner ); ?>" alt="" />
				<?php endif; ?>
				<div class="owbn-board-events__body">
					<a class="owbn-board-events__title" href="<?php echo esc_url( get_permalink( $event ) ); ?>">
						<strong><?php echo esc_html( get_the_title( $event ) ); ?></strong>
					</a>
					<?php if ( ! empty( $meta['tagline'] ) ) : ?>
						<div class="owbn-board-events__tagline"><?php echo esc_html( $meta['tagline'] ); ?></div>
					<?php endif; ?>
					<div class="owbn-board-events__meta">
						<?php if ( $start ) : ?>
							<span class="owbn-board-events__date" data-start="<?php echo (int) $start; ?>">
								<?php echo esc_html( wp_date( get_option( 'date_format' ), $start ) ); ?>
							</span>
						<?php endif; ?>
						<?php if ( ! empty( $meta['location'] ) ) : ?>
							<span class="owbn-board-events__location"><?php echo esc_html( $meta['location'] ); ?></span>
						<?php endif; ?>
					</div>
					<div class="owbn-board-events__rsvp" data-event-id="<?php echo (int) $event->ID; ?>">
						<button type="button" class="button button-small owbn-board-events__rsvp-btn<?php echo 'interested' === $rsvp ? ' is-active' : ''; ?>" data-status="interested">
							<?php esc_html_e( 'Interested', 'owbn-board' ); ?>
						</button>
						<button type="button" class="button button-small owbn-board-events__rsvp-btn<?php echo 'going' === $rsvp ? ' is-active' : ''; ?>" data-status="going">
							<?php esc_html_e( 'Going', 'owbn-board' ); ?>
						</button>
					</div>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php if ( owbn_board_events_user_can_create( $user_id ) ) : ?>
		<p class="owbn-board-events__manage">
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=owbn_event' ) ); ?>"><?php esc_html_e( 'Create an event →', 'owbn-board' ); ?></a>
		</p>
	<?php endif;
}
