<?php
/**
 * Activity module — aggregated recent events from every plugin via filter hook.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/tiles.php';

owbn_board_register_module( [
	'id'          => 'activity',
	'label'       => __( 'Activity Feed', 'owbn-board' ),
	'description' => __( 'Aggregated recent events from every plugin via filter hook', 'owbn-board' ),
	'version'     => '1.0.0',
	'default'     => true,
	'depends_on'  => [],
	'schema'      => null,
	'loader'      => 'owbn_board_activity_init',
] );

function owbn_board_activity_init() {
	add_action( 'owbn_board_register_tiles', 'owbn_board_activity_register_tile' );
}
