<?php
/**
 * Events tile + shortcode — upcoming approved events with optional RSVP controls.
 *
 * Tile: rendered in the dashboard for authenticated users.
 * Shortcode: [owbn_events] can be placed on any page, including public/logged-out.
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
	echo owbn_board_events_render_list( [
		'limit'     => 6,
		'show_rsvp' => true,
		'show_cta'  => true,
	] );
}

/**
 * Register the [owbn_events] shortcode.
 */
function owbn_board_events_register_shortcode() {
	add_shortcode( 'owbn_events', 'owbn_board_events_shortcode_handler' );
}

/**
 * Shortcode handler.
 *
 * Attributes:
 *   limit      int    Number of events to show (default 6)
 *   id         int    Specific event ID to render as a single card (optional)
 *   host       string Filter by host scope (e.g., "chronicle/mckn")
 *   show_rsvp  bool   Show RSVP buttons for logged-in users (default true)
 *   show_cta   bool   Show "Create an event" link for authorized users (default false)
 *
 * @param array $atts
 * @return string
 */
function owbn_board_events_shortcode_handler( $atts ) {
	$atts = shortcode_atts(
		[
			'limit'     => 6,
			'id'        => 0,
			'host'      => '',
			'show_rsvp' => true,
			'show_cta'  => false,
		],
		$atts,
		'owbn_events'
	);

	// Ensure CSS/JS are enqueued when the shortcode is on a page
	if ( function_exists( 'owbn_board_enqueue_assets' ) ) {
		owbn_board_enqueue_assets();
	}

	return owbn_board_events_render_list( [
		'limit'     => (int) $atts['limit'],
		'event_id'  => (int) $atts['id'],
		'host'      => sanitize_text_field( $atts['host'] ),
		'show_rsvp' => filter_var( $atts['show_rsvp'], FILTER_VALIDATE_BOOLEAN ),
		'show_cta'  => filter_var( $atts['show_cta'], FILTER_VALIDATE_BOOLEAN ),
	] );
}

/**
 * Shared renderer used by both the tile and the shortcode.
 *
 * @param array $args {
 *   @type int    $limit     Max events (default 6)
 *   @type int    $event_id  Specific event to render (optional; overrides limit)
 *   @type string $host      Host scope filter (optional)
 *   @type bool   $show_rsvp Show RSVP buttons (default true)
 *   @type bool   $show_cta  Show "Create an event" link (default false)
 * }
 * @return string HTML
 */
function owbn_board_events_render_list( array $args = [] ) {
	$args = wp_parse_args( $args, [
		'limit'     => 6,
		'event_id'  => 0,
		'host'      => '',
		'show_rsvp' => true,
		'show_cta'  => false,
	] );

	// Resolve the event set
	if ( $args['event_id'] ) {
		$post = get_post( $args['event_id'] );
		$events = ( $post && 'owbn_event' === $post->post_type && 'publish' === $post->post_status ) ? [ $post ] : [];
	} elseif ( ! empty( $args['host'] ) ) {
		$events = owbn_board_events_get_upcoming_for_host( $args['host'], (int) $args['limit'] );
	} else {
		$events = owbn_board_events_get_upcoming( (int) $args['limit'] );
	}

	$user_id  = get_current_user_id();
	$is_admin = $user_id && owbn_board_events_user_can_create( $user_id );

	ob_start();

	if ( empty( $events ) ) {
		echo '<p class="owbn-board-events__empty">' . esc_html__( 'No upcoming events.', 'owbn-board' ) . '</p>';
		if ( $args['show_cta'] && $is_admin ) {
			echo '<p class="owbn-board-events__manage"><a href="' . esc_url( admin_url( 'post-new.php?post_type=owbn_event' ) ) . '">' . esc_html__( 'Create an event →', 'owbn-board' ) . '</a></p>';
		}
		return ob_get_clean();
	}
	?>
	<ul class="owbn-board-events">
		<?php foreach ( $events as $event ) :
			$meta      = owbn_board_events_get_meta( $event->ID );
			$start     = ! empty( $meta['start_dt'] ) ? strtotime( $meta['start_dt'] . ' UTC' ) : 0;
			$banner_id = ! empty( $meta['banner_image_id'] ) ? (int) $meta['banner_image_id'] : 0;
			$banner    = $banner_id ? wp_get_attachment_image_url( $banner_id, 'medium' ) : '';
			$rsvp      = $user_id ? owbn_board_events_rsvp_get( $event->ID, $user_id ) : null;
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

					<?php if ( $args['show_rsvp'] ) : ?>
						<?php if ( $user_id ) : ?>
							<div class="owbn-board-events__rsvp" data-event-id="<?php echo (int) $event->ID; ?>">
								<button type="button" class="button button-small owbn-board-events__rsvp-btn<?php echo 'interested' === $rsvp ? ' is-active' : ''; ?>" data-status="interested">
									<?php esc_html_e( 'Interested', 'owbn-board' ); ?>
								</button>
								<button type="button" class="button button-small owbn-board-events__rsvp-btn<?php echo 'going' === $rsvp ? ' is-active' : ''; ?>" data-status="going">
									<?php esc_html_e( 'Going', 'owbn-board' ); ?>
								</button>
							</div>
						<?php else : ?>
							<div class="owbn-board-events__rsvp owbn-board-events__rsvp--login">
								<a href="<?php echo esc_url( wp_login_url( get_permalink( $event ) ) ); ?>" class="button button-small">
									<?php esc_html_e( 'Log in to RSVP', 'owbn-board' ); ?>
								</a>
							</div>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php if ( $args['show_cta'] && $is_admin ) : ?>
		<p class="owbn-board-events__manage">
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=owbn_event' ) ); ?>"><?php esc_html_e( 'Create an event →', 'owbn-board' ); ?></a>
		</p>
	<?php endif;
	return ob_get_clean();
}
