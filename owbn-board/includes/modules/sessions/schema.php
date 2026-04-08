<?php
/**
 * Sessions module schema.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_sessions_install_schema() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();
	$prefix          = $wpdb->prefix;

	$sessions_sql = "CREATE TABLE {$prefix}owbn_board_sessions (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		chronicle_slug VARCHAR(100) NOT NULL,
		site_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		session_date DATE NOT NULL,
		title VARCHAR(255) NOT NULL DEFAULT '',
		summary LONGTEXT NULL,
		notes LONGTEXT NULL,
		attendance TEXT NULL,
		share_with_players TINYINT(1) NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_chronicle (chronicle_slug, session_date)
	) $charset_collate;";

	$history_sql = "CREATE TABLE {$prefix}owbn_board_session_history (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		session_id BIGINT UNSIGNED NOT NULL,
		summary LONGTEXT NULL,
		notes LONGTEXT NULL,
		changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		changed_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_session (session_id, changed_at)
	) $charset_collate;";

	dbDelta( $sessions_sql );
	dbDelta( $history_sql );
}
