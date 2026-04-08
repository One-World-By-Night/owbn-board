<?php
/**
 * Handoff module schema.
 *
 * Three tables: handoffs (one per scope), sections (topical groups within a handoff),
 * entries (individual notes within a section, the actual content).
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_handoff_install_schema() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();
	$prefix          = $wpdb->prefix;

	$handoffs_sql = "CREATE TABLE {$prefix}owbn_board_handoffs (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		scope VARCHAR(255) NOT NULL,
		site_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		title VARCHAR(255) NOT NULL DEFAULT '',
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY unique_scope_site (scope, site_id)
	) $charset_collate;";

	$sections_sql = "CREATE TABLE {$prefix}owbn_board_handoff_sections (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		handoff_id BIGINT UNSIGNED NOT NULL,
		label VARCHAR(255) NOT NULL,
		sort_order INT NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_handoff (handoff_id, sort_order)
	) $charset_collate;";

	$entries_sql = "CREATE TABLE {$prefix}owbn_board_handoff_entries (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		section_id BIGINT UNSIGNED NOT NULL,
		author_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		title VARCHAR(255) NOT NULL DEFAULT '',
		body LONGTEXT NULL,
		tags TEXT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'current',
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		deleted_at DATETIME NULL,
		PRIMARY KEY (id),
		KEY idx_section (section_id, created_at),
		KEY idx_author (author_id)
	) $charset_collate;";

	dbDelta( $handoffs_sql );
	dbDelta( $sections_sql );
	dbDelta( $entries_sql );
}

/**
 * The default set of sections that get created when a new handoff is provisioned.
 */
function owbn_board_handoff_default_sections() {
	return [
		__( 'How I run this', 'owbn-board' ),
		__( 'Tools I use', 'owbn-board' ),
		__( 'People to know', 'owbn-board' ),
		__( 'Ongoing priorities', 'owbn-board' ),
		__( 'Pitfalls and lessons learned', 'owbn-board' ),
		__( 'Current relationships', 'owbn-board' ),
		__( 'Standing commitments', 'owbn-board' ),
		__( 'Useful links', 'owbn-board' ),
		__( 'Open questions', 'owbn-board' ),
		__( 'What I\'d do differently', 'owbn-board' ),
	];
}
