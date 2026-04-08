<?php
/**
 * Uninstall — drop all owbn-board tables and options if the user opted in.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

if ( get_option( 'owbn_board_remove_data_on_uninstall' ) ) {
	$tables = [
		$wpdb->prefix . 'owbn_board_notebooks',
		$wpdb->prefix . 'owbn_board_notebook_history',
		$wpdb->prefix . 'owbn_board_messages',
		$wpdb->prefix . 'owbn_board_tile_state',
		$wpdb->prefix . 'owbn_board_audit_log',
	];

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
	}

	// Best effort: let modules drop their own tables via uninstall hooks.
	do_action( 'owbn_board_uninstall_modules' );

	// Clean up options
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'owbn_board_%'" );
}
