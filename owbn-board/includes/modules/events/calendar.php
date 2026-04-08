<?php
/**
 * Events module — calendar contributor.
 *
 * Feeds approved events into the calendar module's aggregator.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_events_calendar_contribute( $events, $user_id, $roles, $from, $to ) {
	if ( ! post_type_exists( 'owbn_event' ) ) {
		return $events;
	}

	$from_gmt = gmdate( 'Y-m-d H:i:s', $from );
	$to_gmt   = gmdate( 'Y-m-d H:i:s', $to );

	$posts = get_posts( [
		'post_type'      => 'owbn_event',
		'post_status'    => 'publish',
		'posts_per_page' => 100,
		'meta_query'     => [
			[
				'key'     => '_owbn_event_start_dt',
				'value'   => [ $from_gmt, $to_gmt ],
				'compare' => 'BETWEEN',
				'type'    => 'DATETIME',
			],
		],
	] );

	foreach ( $posts as $post ) {
		$meta  = owbn_board_events_get_meta( $post->ID );
		$start = ! empty( $meta['start_dt'] ) ? strtotime( $meta['start_dt'] . ' UTC' ) : 0;
		$end   = ! empty( $meta['end_dt'] ) ? strtotime( $meta['end_dt'] . ' UTC' ) : 0;

		if ( ! $start ) {
			continue;
		}

		$events[] = [
			'id'       => 'event-' . $post->ID,
			'title'    => get_the_title( $post ),
			'start'    => $start,
			'end'      => $end ?: ( $start + HOUR_IN_SECONDS * 3 ),
			'url'      => get_permalink( $post ),
			'category' => 'event',
			'color'    => '',
			'all_day'  => false,
		];
	}

	return $events;
}
