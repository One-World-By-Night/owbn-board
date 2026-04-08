<?php
/**
 * Calendar module — chronicle sessions contributor.
 *
 * Reads chronicle data via owc_get_chronicles() (local or remote via owbn-gateway)
 * and expands each chronicle's session_list recurrence rules into concrete dates
 * in the calendar window.
 *
 * Times are stored as local-to-chronicle HH:MM. We convert to UTC using the
 * chronicle's timezone field, then return UTC timestamps. The calendar tile's
 * JS converts UTC to each user's browser timezone on render.
 *
 * Per-user filters (genre, day, session type) are read from user meta:
 *   owbn_board_calendar_filters = [
 *     'genres'        => ['vampire', 'mage'] | [],     // empty = all
 *     'days'          => ['Saturday', 'Sunday'] | [],   // empty = all
 *     'session_types' => ['Game'] | [],                 // empty = Game only
 *   ]
 */

defined( 'ABSPATH' ) || exit;

/**
 * Contribute chronicle session events to the board calendar.
 */
function owbn_board_calendar_chronicle_events( $events, $user_id, $roles, $from, $to ) {
	if ( ! function_exists( 'owc_get_chronicles' ) ) {
		return $events;
	}

	$chronicles = owc_get_chronicles();
	if ( is_wp_error( $chronicles ) || empty( $chronicles ) ) {
		return $events;
	}

	$filters = owbn_board_calendar_get_user_filters( $user_id );

	foreach ( $chronicles as $chronicle ) {
		$sessions = $chronicle['session_list'] ?? [];
		if ( empty( $sessions ) || ! is_array( $sessions ) ) {
			continue;
		}

		$tz_name  = $chronicle['timezone'] ?? 'UTC';
		$c_title  = $chronicle['title'] ?? '';
		$c_slug   = $chronicle['slug'] ?? '';
		if ( $c_slug && function_exists( 'owc_get_chronicles_slug' ) ) {
			$c_url = home_url( '/' . owc_get_chronicles_slug() . '/' . rawurlencode( $c_slug ) . '/' );
		} else {
			$c_url = '';
		}
		$c_genres = is_array( $chronicle['genres'] ?? null ) ? $chronicle['genres'] : [];

		foreach ( $sessions as $session ) {
			$type = $session['session_type'] ?? '';
			$day  = $session['day'] ?? '';

			// Apply user filters
			if ( ! empty( $filters['session_types'] ) && ! in_array( $type, $filters['session_types'], true ) ) {
				continue;
			}
			if ( empty( $filters['session_types'] ) && 'Game' !== $type ) {
				// Default: show only Game sessions when user hasn't picked types
				continue;
			}
			if ( ! empty( $filters['days'] ) && ! in_array( $day, $filters['days'], true ) ) {
				continue;
			}

			// Genre filter: session genres OR chronicle genres
			$session_genres = is_array( $session['genres'] ?? null ) ? $session['genres'] : $c_genres;
			if ( ! empty( $filters['genres'] ) && empty( array_intersect( $session_genres, $filters['genres'] ) ) ) {
				continue;
			}

			$dates = owbn_board_calendar_expand_recurrence( $session, $tz_name, $from, $to );

			foreach ( $dates as $ts ) {
				$events[] = [
					'id'       => 'chronicle-' . $c_slug . '-' . $ts,
					'title'    => $c_title . ' — ' . ( $type ?: 'Session' ),
					'start'    => $ts,
					'end'      => $ts + ( 4 * HOUR_IN_SECONDS ),
					'url'      => $c_url,
					'category' => strtolower( $type ?: 'game' ),
					'color'    => '',
					'all_day'  => false,
				];
			}
		}
	}

	return $events;
}

/**
 * Fetch a user's calendar filter preferences.
 */
function owbn_board_calendar_get_user_filters( $user_id ) {
	$defaults = [
		'genres'        => [],
		'days'          => [],
		'session_types' => [],
	];
	$filters  = get_user_meta( $user_id, 'owbn_board_calendar_filters', true );
	if ( ! is_array( $filters ) ) {
		return $defaults;
	}
	return wp_parse_args( $filters, $defaults );
}

/**
 * Expand a session recurrence rule into concrete UTC timestamps within [$from, $to].
 *
 * Supports:
 *   frequency: 1st, 2nd, 3rd, 4th, 5th, Every, Every Other
 *   day:       Monday - Sunday
 *   start_time: HH:MM
 *
 * Does NOT handle: Random, Other, Week.
 * "Every Other" is interpreted as every 14 days starting from the first matching week in the window.
 */
function owbn_board_calendar_expand_recurrence( array $session, $tz_name, $from, $to ) {
	$frequency  = $session['frequency'] ?? '';
	$day        = $session['day'] ?? '';
	$start_time = $session['start_time'] ?? '';

	$day_map = [
		'Monday'    => 1,
		'Tuesday'   => 2,
		'Wednesday' => 3,
		'Thursday'  => 4,
		'Friday'    => 5,
		'Saturday'  => 6,
		'Sunday'    => 7,
	];

	if ( ! isset( $day_map[ $day ] ) ) {
		return [];
	}
	if ( empty( $start_time ) || ! preg_match( '/^(\d{1,2}):(\d{2})$/', $start_time, $m ) ) {
		return [];
	}

	$target_dow = $day_map[ $day ];
	$hour       = (int) $m[1];
	$minute     = (int) $m[2];

	try {
		$tz = new DateTimeZone( $tz_name );
	} catch ( Exception $e ) {
		$tz = new DateTimeZone( 'UTC' );
	}

	$utc = new DateTimeZone( 'UTC' );

	$start_dt = ( new DateTime( '@' . $from ) )->setTimezone( $tz );
	$start_dt->setTime( 0, 0, 0 );
	$end_dt = new DateTime( '@' . $to );
	$end_dt->setTimezone( $tz );

	$dates  = [];
	$cursor = clone $start_dt;

	// Move cursor to the first target day-of-week at or after $from
	$cursor_dow = (int) $cursor->format( 'N' );
	$offset     = ( $target_dow - $cursor_dow + 7 ) % 7;
	$cursor->modify( '+' . $offset . ' days' );

	$every_other_toggle = 0;
	$week_count         = 0;

	while ( $cursor <= $end_dt ) {
		$day_of_month  = (int) $cursor->format( 'j' );
		$week_of_month = (int) ceil( $day_of_month / 7 );

		$include = false;
		switch ( $frequency ) {
			case 'Every':
				$include = true;
				break;
			case 'Every Other':
				$include = ( $every_other_toggle % 2 === 0 );
				$every_other_toggle++;
				break;
			case '1st':
				$include = ( 1 === $week_of_month );
				break;
			case '2nd':
				$include = ( 2 === $week_of_month );
				break;
			case '3rd':
				$include = ( 3 === $week_of_month );
				break;
			case '4th':
				$include = ( 4 === $week_of_month );
				break;
			case '5th':
				$include = ( 5 === $week_of_month );
				break;
			default:
				$include = false;
		}

		if ( $include ) {
			$event_local = clone $cursor;
			$event_local->setTime( $hour, $minute, 0 );
			$event_utc = clone $event_local;
			$event_utc->setTimezone( $utc );
			$ts = $event_utc->getTimestamp();
			if ( $ts >= $from && $ts <= $to ) {
				$dates[] = $ts;
			}
		}

		$cursor->modify( '+7 days' );
		$week_count++;
		if ( $week_count > 52 ) {
			break;
		}
	}

	return $dates;
}

/**
 * AJAX: save a user's calendar filter preferences.
 */
function owbn_board_calendar_ajax_save_filters() {
	if ( ! check_ajax_referer( 'owbn_board', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
	}
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( [ 'message' => 'Not logged in' ], 401 );
	}

	$genres = isset( $_POST['genres'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['genres'] ) ) : [];
	$days   = isset( $_POST['days'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['days'] ) ) : [];
	$types  = isset( $_POST['session_types'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['session_types'] ) ) : [];

	$allowed_days  = [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ];
	$allowed_types = [ 'Game', 'OOC Social Meetup', 'Other' ];

	$filters = [
		'genres'        => array_values( array_unique( $genres ) ),
		'days'          => array_values( array_intersect( $days, $allowed_days ) ),
		'session_types' => array_values( array_intersect( $types, $allowed_types ) ),
	];

	update_user_meta( $user_id, 'owbn_board_calendar_filters', $filters );
	wp_send_json_success( [ 'filters' => $filters ] );
}
