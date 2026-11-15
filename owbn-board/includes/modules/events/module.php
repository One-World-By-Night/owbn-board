<?php
/**
 * Events module — upcoming events marketing board with approval workflow.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/cpt.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/models.php';
require_once __DIR__ . '/tiles.php';
require_once __DIR__ . '/calendar.php';
require_once __DIR__ . '/ajax.php';
if ( is_admin() ) {
	require_once __DIR__ . '/admin.php';
	require_once __DIR__ . '/approval.php';
}

owbn_board_register_module( [
	'id'          => 'events',
	'label'       => __( 'Events', 'owbn-board' ),
	'description' => __( 'Upcoming events marketing board with approval workflow and RSVPs', 'owbn-board' ),
	'version'     => '1.0.0',
	'default'     => false,
	'depends_on'  => [],
	'schema'      => 'owbn_board_events_install_schema',
	'loader'      => 'owbn_board_events_init',
] );

/**
 * Whether this site holds the events (the owbn_event type, its editor, approvals and RSVPs): the site whose
 * owbn_board_events_host option is "1"; with the option unset anywhere, chronicles.
 *
 * @return bool
 */
function owbn_board_events_is_host() {
	$set = (string) get_option( 'owbn_board_events_host', '' );
	if ( '' !== $set ) {
		return '1' === $set;
	}
	return 'chronicles' === owbn_board_get_site_slug();
}

/**
 * Genres an event can be tagged with: C&C's genre list where it is installed, else the genres in the chronicle list.
 *
 * @return string[]
 */
function owbn_board_events_genre_choices() {
	$list = get_option( 'owbn_genre_list', array() );
	if ( is_array( $list ) && $list ) {
		return array_values( array_filter( array_map( 'strval', $list ) ) );
	}
	$genres = array();
	$chronicles = function_exists( 'owc_get_chronicles' ) ? owc_get_chronicles() : array();
	foreach ( is_array( $chronicles ) ? $chronicles : array() as $c ) {
		foreach ( (array) ( ( (array) $c )['genres'] ?? array() ) as $g ) {
			$g = trim( (string) $g );
			if ( '' !== $g ) {
				$genres[ $g ] = true;
			}
		}
	}
	$genres = array_keys( $genres );
	natcasesort( $genres );
	return array_values( $genres );
}

function owbn_board_events_init() {
	$is_host = owbn_board_events_is_host();

	owbn_board_at_init( 'owbn_board_events_register_shortcode' );
	add_action( 'owbn_board_register_tiles', 'owbn_board_events_register_tile' );
	add_filter( 'owbn_board_calendar_events', 'owbn_board_events_calendar_contribute', 10, 5 );

	// Runs on every site; owc_events_rsvp_* dispatches local or via gateway.
	add_action( 'wp_ajax_owbn_board_events_rsvp', 'owbn_board_events_ajax_rsvp' );

	if ( $is_host ) {
		owbn_board_at_init( 'owbn_board_events_register_cpt' );

		if ( is_admin() ) {
			add_action( 'add_meta_boxes', 'owbn_board_events_register_metabox' );
			add_action( 'save_post', 'owbn_board_events_save_post', 10, 2 );
			add_action( 'admin_menu', 'owbn_board_events_register_approval_page', 50 );
		}
	}
}
