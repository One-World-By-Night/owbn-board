<?php
/**
 * Newsletter module — link feed of published newsletter editions.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/models.php';
require_once __DIR__ . '/tiles.php';
if ( is_admin() ) {
	require_once __DIR__ . '/admin.php';
}

owbn_board_register_module( [
	'id'          => 'newsletter',
	'label'       => __( 'Newsletter', 'owbn-board' ),
	'description' => __( 'Link feed of published newsletter editions', 'owbn-board' ),
	'version'     => '1.0.0',
	'default'     => false,
	'depends_on'  => [],
	'schema'      => 'owbn_board_newsletter_install_schema',
	'loader'      => 'owbn_board_newsletter_init',
] );

function owbn_board_newsletter_init() {
	add_action( 'owbn_board_register_tiles', 'owbn_board_newsletter_register_tile' );
	if ( is_admin() ) {
		add_action( 'admin_menu', 'owbn_board_newsletter_register_admin', 20 );
	}
}
