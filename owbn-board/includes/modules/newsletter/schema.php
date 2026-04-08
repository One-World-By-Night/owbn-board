<?php
/**
 * Newsletter module schema.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Create the newsletter editions table. Idempotent via dbDelta.
 */
function owbn_board_newsletter_install_schema() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();
	$table           = $wpdb->prefix . 'owbn_board_newsletter_editions';

	$sql = "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		title VARCHAR(255) NOT NULL,
		published_at DATE NOT NULL,
		url VARCHAR(500) NOT NULL,
		summary TEXT NULL,
		cover_image_id BIGINT UNSIGNED NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_published (published_at)
	) $charset_collate;";

	dbDelta( $sql );
}
