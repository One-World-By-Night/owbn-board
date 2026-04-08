<?php
/**
 * Events module schema — RSVP table.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_events_install_schema() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();
	$table           = $wpdb->prefix . 'owbn_board_event_rsvps';

	$sql = "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		event_id BIGINT UNSIGNED NOT NULL,
		user_id BIGINT UNSIGNED NOT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'interested',
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY unique_event_user (event_id, user_id),
		KEY idx_event (event_id)
	) $charset_collate;";

	dbDelta( $sql );
}
