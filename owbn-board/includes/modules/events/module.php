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

function owbn_board_events_init() {
	add_action( 'init', 'owbn_board_events_register_cpt' );
	add_action( 'owbn_board_register_tiles', 'owbn_board_events_register_tile' );
	add_filter( 'owbn_board_calendar_events', 'owbn_board_events_calendar_contribute', 10, 5 );
	add_action( 'wp_ajax_owbn_board_events_rsvp', 'owbn_board_events_ajax_rsvp' );

	if ( is_admin() ) {
		add_action( 'add_meta_boxes', 'owbn_board_events_register_metabox' );
		add_action( 'save_post', 'owbn_board_events_save_post', 10, 2 );
		add_action( 'admin_menu', 'owbn_board_events_register_approval_page', 50 );
	}
}
