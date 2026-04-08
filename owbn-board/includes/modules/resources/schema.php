<?php
/**
 * Resources module schema — links table.
 *
 * Articles are a WP custom post type (see cpt.php) and need no table.
 * Links are lightweight pointers to external URLs, stored in a custom table.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_resources_install_schema() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();
	$table           = $wpdb->prefix . 'owbn_board_resource_links';

	$sql = "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		title VARCHAR(255) NOT NULL,
		url VARCHAR(500) NOT NULL,
		description TEXT NULL,
		category VARCHAR(100) NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_category (category),
		KEY idx_created (created_at)
	) $charset_collate;";

	dbDelta( $sql );
}
