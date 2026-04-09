<?php
/**
 * Activity module — aggregated recent events from every plugin via filter hook.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/tiles.php';

owbn_board_register_module( [
	'id'          => 'activity',
	'label'       => __( 'Activity Feed (Pending Development)', 'owbn-board' ),
	'description' => __( 'Aggregated recent events from every plugin via filter hook. Pending Development — no contributors wired yet; enabling the module renders an empty tile until modules push items into the owbn_board_activity_items filter.', 'owbn-board' ),
	'version'     => '1.0.0',
	'default'     => false,
	'depends_on'  => [],
	'schema'      => null,
	'loader'      => 'owbn_board_activity_init',
] );

function owbn_board_activity_init() {
	add_action( 'owbn_board_register_tiles', 'owbn_board_activity_register_tile' );
}
