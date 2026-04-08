<?php
/**
 * Events module — data access helpers.
 *
 * Events are CPT posts. Structured fields live in post meta.
 * RSVPs live in a custom table.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_events_rsvp_table() {
	global $wpdb;
	return $wpdb->prefix . 'owbn_board_event_rsvps';
}

/**
 * Meta keys (all prefixed with _owbn_event_ to avoid collision with other plugins).
 */
function owbn_board_events_meta_keys() {
	return [
		'start_dt'         => '_owbn_event_start_dt',
		'end_dt'           => '_owbn_event_end_dt',
		'timezone'         => '_owbn_event_timezone',
		'location'         => '_owbn_event_location',
		'host_scope'       => '_owbn_event_host_scope',
		'banner_image_id'  => '_owbn_event_banner_image_id',
		'tagline'          => '_owbn_event_tagline',
		'registration_url' => '_owbn_event_registration_url',
		'registration_fee' => '_owbn_event_registration_fee',
		'max_attendees'    => '_owbn_event_max_attendees',
		'website'          => '_owbn_event_website',
		'social_links'     => '_owbn_event_social_links',
		'rejection_reason' => '_owbn_event_rejection_reason',
	];
}

/**
 * Get all structured meta for an event post, keyed by short name.
 */
function owbn_board_events_get_meta( $post_id ) {
	$keys = owbn_board_events_meta_keys();
	$out  = [];
	foreach ( $keys as $short => $meta_key ) {
		$out[ $short ] = get_post_meta( $post_id, $meta_key, true );
	}
	return $out;
}

/**
 * Save structured meta from a sanitized array.
 */
function owbn_board_events_save_meta( $post_id, array $data ) {
	$keys = owbn_board_events_meta_keys();
	foreach ( $keys as $short => $meta_key ) {
		if ( ! array_key_exists( $short, $data ) ) {
			continue;
		}
		$value = $data[ $short ];
		if ( is_array( $value ) || is_object( $value ) ) {
			update_post_meta( $post_id, $meta_key, wp_slash( wp_json_encode( $value ) ) );
		} else {
			update_post_meta( $post_id, $meta_key, $value );
		}
	}
}

/**
 * Fetch approved events in the future. Ordered by start date ascending.
 */
function owbn_board_events_get_upcoming( $limit = 10 ) {
	if ( ! post_type_exists( 'owbn_event' ) ) {
		return [];
	}

	$now_gmt = gmdate( 'Y-m-d H:i:s' );

	return (array) get_posts( [
		'post_type'      => 'owbn_event',
		'post_status'    => 'publish',
		'posts_per_page' => absint( $limit ),
		'meta_query'     => [
			[
				'key'     => '_owbn_event_start_dt',
				'value'   => $now_gmt,
				'compare' => '>=',
				'type'    => 'DATETIME',
			],
		],
		'meta_key'       => '_owbn_event_start_dt',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
	] );
}

/**
 * Fetch events awaiting review (pending status).
 */
function owbn_board_events_get_pending() {
	if ( ! post_type_exists( 'owbn_event' ) ) {
		return [];
	}
	return (array) get_posts( [
		'post_type'      => 'owbn_event',
		'post_status'    => 'pending',
		'posts_per_page' => 100,
		'orderby'        => 'date',
		'order'          => 'ASC',
	] );
}

/**
 * Can the current user review events?
 */
function owbn_board_events_user_can_review( $user_id = null ) {
	if ( null === $user_id ) {
		$user_id = get_current_user_id();
	}
	if ( owbn_board_user_can_manage() ) {
		return true;
	}
	$roles = owbn_board_get_user_roles( $user_id );
	foreach ( (array) $roles as $role ) {
		if ( preg_match( '#^exec/#', (string) $role ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Can the current user create events?
 */
function owbn_board_events_user_can_create( $user_id = null ) {
	if ( null === $user_id ) {
		$user_id = get_current_user_id();
	}
	if ( owbn_board_user_can_manage() ) {
		return true;
	}
	$roles = owbn_board_get_user_roles( $user_id );
	foreach ( (array) $roles as $role ) {
		if ( preg_match( '#^(chronicle/[^/]+/(hst|cm)|coordinator/|exec/)#', (string) $role ) ) {
			return true;
		}
	}
	return false;
}

/**
 * RSVP helpers.
 */
function owbn_board_events_rsvp_set( $event_id, $user_id, $status ) {
	global $wpdb;
	$status = in_array( $status, [ 'interested', 'going' ], true ) ? $status : 'interested';

	$table = owbn_board_events_rsvp_table();
	$wpdb->replace(
		$table,
		[
			'event_id'   => absint( $event_id ),
			'user_id'    => absint( $user_id ),
			'status'     => $status,
			'created_at' => current_time( 'mysql' ),
		],
		[ '%d', '%d', '%s', '%s' ]
	);
}

function owbn_board_events_rsvp_remove( $event_id, $user_id ) {
	global $wpdb;
	$wpdb->delete(
		owbn_board_events_rsvp_table(),
		[
			'event_id' => absint( $event_id ),
			'user_id'  => absint( $user_id ),
		],
		[ '%d', '%d' ]
	);
}

function owbn_board_events_rsvp_get( $event_id, $user_id ) {
	global $wpdb;
	$table = owbn_board_events_rsvp_table();
	return $wpdb->get_var(
		$wpdb->prepare(
			"SELECT status FROM {$table} WHERE event_id = %d AND user_id = %d LIMIT 1",
			absint( $event_id ),
			absint( $user_id )
		)
	);
}

function owbn_board_events_rsvp_counts( $event_id ) {
	global $wpdb;
	$table = owbn_board_events_rsvp_table();
	$rows  = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT status, COUNT(*) AS n FROM {$table} WHERE event_id = %d GROUP BY status",
			absint( $event_id )
		),
		ARRAY_A
	);
	$out = [ 'interested' => 0, 'going' => 0 ];
	foreach ( (array) $rows as $row ) {
		$out[ $row['status'] ] = (int) $row['n'];
	}
	return $out;
}
