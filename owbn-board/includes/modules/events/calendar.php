<?php
/**
 * Events module — calendar contributor. Reads via owc_events_* wrapper.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_events_calendar_contribute( $events, $user_id, $roles, $from, $to ) {
	if ( ! function_exists( 'owc_events_get_in_window' ) ) {
		return $events;
	}

	$window = owc_events_get_in_window( (int) $from, (int) $to );
	if ( empty( $window ) || ! is_array( $window ) ) {
		return $events;
	}

	foreach ( $window as $event ) {
		$start_dt = (string) ( $event['start_dt'] ?? '' );
		$end_dt   = (string) ( $event['end_dt'] ?? '' );
		$start    = $start_dt ? strtotime( $start_dt . ' UTC' ) : 0;
		$end      = $end_dt ? strtotime( $end_dt . ' UTC' ) : 0;

		if ( ! $start ) {
			continue;
		}

		$events[] = [
			'id'       => 'event-' . (int) ( $event['id'] ?? 0 ),
			'title'    => (string) ( $event['title'] ?? '' ),
			'start'    => $start,
			'end'      => $end ?: ( $start + HOUR_IN_SECONDS * 3 ),
			'url'      => (string) ( $event['permalink'] ?? '' ),
			'category' => 'event',
			'color'    => '',
			'all_day'  => false,
		];
	}

	return $events;
}
