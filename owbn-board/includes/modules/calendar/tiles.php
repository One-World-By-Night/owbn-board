<?php
/**
 * Calendar tile — upcoming dates aggregator.
 *
 * Plugins contribute events via:
 *   apply_filters('owbn_board_calendar_events', $events, $user_id, $roles, $from, $to)
 *
 * Each event: ['id', 'title', 'start', 'end', 'url', 'category', 'color', 'all_day']
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_calendar_register_tile() {
	owbn_board_register_tile( [
		'id'         => 'board:calendar',
		'title'      => __( 'Calendar', 'owbn-board' ),
		'icon'       => 'dashicons-calendar-alt',
		'read_roles' => [ 'chronicle/*/*', 'coordinator/*/*', 'exec/*' ],
		'size'       => '2x2',
		'category'   => 'reference',
		'priority'   => 15,
		'render'     => 'owbn_board_render_calendar_tile',
	] );
}

function owbn_board_render_calendar_tile( $tile, $user_id, $can_write ) {
	$roles  = owbn_board_get_user_roles( $user_id );
	$from   = time();
	$to     = $from + ( 30 * DAY_IN_SECONDS );
	$events = apply_filters( 'owbn_board_calendar_events', [], $user_id, $roles, $from, $to );

	usort( $events, function ( $a, $b ) {
		$a_time = is_numeric( $a['start'] ?? 0 ) ? (int) $a['start'] : strtotime( (string) ( $a['start'] ?? '' ) );
		$b_time = is_numeric( $b['start'] ?? 0 ) ? (int) $b['start'] : strtotime( (string) ( $b['start'] ?? '' ) );
		return $a_time - $b_time;
	} );

	$events = array_slice( $events, 0, 30 );

	if ( empty( $events ) ) {
		echo '<p class="owbn-board-calendar__empty">' . esc_html__( 'No upcoming events in the next 30 days.', 'owbn-board' ) . '</p>';
		return;
	}

	echo '<ul class="owbn-board-calendar">';
	foreach ( $events as $event ) {
		$title   = $event['title'] ?? '';
		$url     = $event['url'] ?? '';
		$start   = is_numeric( $event['start'] ?? 0 ) ? (int) $event['start'] : strtotime( (string) ( $event['start'] ?? '' ) );
		$date    = $start ? wp_date( 'M j', $start ) : '';
		$time    = $start ? wp_date( 'g:ia', $start ) : '';
		$all_day = ! empty( $event['all_day'] );
		$cat     = $event['category'] ?? '';
		?>
		<li class="owbn-board-calendar__item">
			<div class="owbn-board-calendar__date">
				<strong><?php echo esc_html( $date ); ?></strong>
				<?php if ( ! $all_day ) : ?>
					<span><?php echo esc_html( $time ); ?></span>
				<?php endif; ?>
			</div>
			<div class="owbn-board-calendar__body">
				<a href="<?php echo esc_url( $url ); ?>" class="owbn-board-calendar__title"><?php echo esc_html( $title ); ?></a>
				<?php if ( $cat ) : ?>
					<span class="owbn-board-calendar__category"><?php echo esc_html( $cat ); ?></span>
				<?php endif; ?>
			</div>
		</li>
		<?php
	}
	echo '</ul>';
}
