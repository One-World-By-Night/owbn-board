<?php
/**
 * Chronicle sessions contributor. Expands session_list recurrence into UTC
 * timestamps; JS converts to the browser's local timezone on render.
 * User filters live in user_meta[owbn_board_calendar_filters].
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_calendar_chronicle_events( $events, $user_id, $roles, $from, $to ) {
	if ( ! function_exists( 'owc_get_chronicles' ) ) {
		return $events;
	}

	$chronicles = owc_get_chronicles();
	if ( is_wp_error( $chronicles ) || empty( $chronicles ) ) {
		return $events;
	}

	$filters = owbn_board_calendar_get_user_filters( $user_id );

	// "mine" mode narrows to user's chronicle roles; exec/coord with no
	// chronicle roles fall through to all so the tile isn't empty.
	$my_slugs = owbn_board_calendar_user_chronicle_slugs( $user_id );
	$scope_to_mine = ( 'mine' === $filters['chronicles_mode'] ) && ! empty( $my_slugs );

	foreach ( $chronicles as $chronicle ) {
		if ( $scope_to_mine ) {
			$c_slug_check = $chronicle['slug'] ?? '';
			if ( ! in_array( $c_slug_check, $my_slugs, true ) ) {
				continue;
			}
		}
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

function owbn_board_calendar_get_user_filters( $user_id ) {
	$defaults = [
		'genres'          => [],
		'days'            => [],
		'session_types'   => [],
		'chronicles_mode' => 'mine',
	];
	$filters  = get_user_meta( $user_id, 'owbn_board_calendar_filters', true );
	if ( ! is_array( $filters ) ) {
		return $defaults;
	}
	$filters = wp_parse_args( $filters, $defaults );
	if ( ! in_array( $filters['chronicles_mode'], [ 'mine', 'all' ], true ) ) {
		$filters['chronicles_mode'] = 'mine';
	}
	return $filters;
}

function owbn_board_calendar_user_chronicle_slugs( $user_id ) {
	$roles = owbn_board_get_user_roles( $user_id );
	$slugs = [];
	foreach ( (array) $roles as $role ) {
		if ( preg_match( '#^chronicle/([^/]+)(/|$)#', (string) $role, $m ) ) {
			$slugs[] = $m[1];
		}
	}
	return array_values( array_unique( $slugs ) );
}

// Mirrors chronicle-manager's expand_session_dates helper; delegates to it when present.
function owbn_board_calendar_expand_recurrence( array $session, $tz_name, $from, $to ) {
	if ( function_exists( 'owbn_chronicle_expand_session_dates' ) ) {
		return owbn_chronicle_expand_session_dates( $session, $tz_name, $from, $to );
	}
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

	$week_count = 0;

	while ( $cursor <= $end_dt ) {
		$day_of_month  = (int) $cursor->format( 'j' );
		$week_of_month = (int) ceil( $day_of_month / 7 );

		$include = false;
		switch ( $frequency ) {
			case 'Every':
				$include = true;
				break;
			case 'Every Other':
				$global_week = (int) floor( $cursor->getTimestamp() / ( 7 * DAY_IN_SECONDS ) );
				$include     = ( 0 === $global_week % 2 );
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
	$mode   = isset( $_POST['chronicles_mode'] ) ? sanitize_key( wp_unslash( $_POST['chronicles_mode'] ) ) : 'mine';

	$allowed_days  = [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ];
	$allowed_types  = [ 'Game', 'OOC Social Meetup', 'Other' ];
	$allowed_genres = (array) get_option( 'owbn_genre_list', [] );
	if ( ! in_array( $mode, [ 'mine', 'all' ], true ) ) {
		$mode = 'mine';
	}

	$filters = [
		'genres'          => empty( $allowed_genres ) ? [] : array_values( array_intersect( $genres, $allowed_genres ) ),
		'days'            => array_values( array_intersect( $days, $allowed_days ) ),
		'session_types'   => array_values( array_intersect( $types, $allowed_types ) ),
		'chronicles_mode' => $mode,
	];

	update_user_meta( $user_id, 'owbn_board_calendar_filters', $filters );
	wp_send_json_success( [ 'filters' => $filters ] );
}
