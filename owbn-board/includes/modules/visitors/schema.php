<?php
/**
 * Visitors module schema.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_visitors_install_schema() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();
	$table           = $wpdb->prefix . 'owbn_board_visitors';

	$sql = "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		host_chronicle_slug VARCHAR(100) NOT NULL,
		home_chronicle_slug VARCHAR(100) NULL,
		visitor_user_id BIGINT UNSIGNED NULL,
		visitor_display_name VARCHAR(255) NULL,
		character_name VARCHAR(255) NOT NULL,
		visit_date DATE NOT NULL,
		notes LONGTEXT NULL,
		site_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
		deleted_at DATETIME NULL,
		PRIMARY KEY (id),
		KEY idx_host (host_chronicle_slug, visit_date),
		KEY idx_home (home_chronicle_slug, visit_date),
		KEY idx_visitor (visitor_user_id, visit_date)
	) $charset_collate;";

	dbDelta( $sql );
}
