<?php
/**
 * Calendar module — aggregates upcoming dates from every plugin via filter hook.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/tiles.php';
require_once __DIR__ . '/chronicles.php';

owbn_board_register_module( [
	'id'          => 'calendar',
	'label'       => __( 'Calendar', 'owbn-board' ),
	'description' => __( 'Upcoming dates aggregated from every plugin (votes, sessions, deadlines)', 'owbn-board' ),
	'version'     => '1.0.0',
	'default'     => true,
	'depends_on'  => [],
	'schema'      => null,
	'loader'      => 'owbn_board_calendar_init',
] );

function owbn_board_calendar_init() {
	add_action( 'owbn_board_register_tiles', 'owbn_board_calendar_register_tile' );
	add_filter( 'owbn_board_calendar_events', 'owbn_board_calendar_chronicle_events', 10, 5 );
	add_action( 'wp_ajax_owbn_board_calendar_save_filters', 'owbn_board_calendar_ajax_save_filters' );
}
