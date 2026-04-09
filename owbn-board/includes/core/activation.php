<?php
/**
 * Plugin activation — create DB tables, set defaults.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Create all owbn-board tables. Idempotent via dbDelta.
 */
function owbn_board_activate() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();
	$prefix          = $wpdb->prefix;

	$tables = [];

	$tables[] = "CREATE TABLE {$prefix}owbn_board_notebooks (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		role_path VARCHAR(255) NOT NULL,
		site_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		title VARCHAR(255) NOT NULL DEFAULT '',
		content LONGTEXT NULL,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
		locked_by BIGINT UNSIGNED NULL,
		locked_at DATETIME NULL,
		PRIMARY KEY (id),
		KEY idx_role_site (site_id, role_path),
		KEY idx_locked (locked_at)
	) $charset_collate;";

	$tables[] = "CREATE TABLE {$prefix}owbn_board_notebook_history (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		notebook_id BIGINT UNSIGNED NOT NULL,
		content LONGTEXT NULL,
		changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		changed_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
		change_summary VARCHAR(255) NULL,
		PRIMARY KEY (id),
		KEY idx_notebook (notebook_id, changed_at)
	) $charset_collate;";

	$tables[] = "CREATE TABLE {$prefix}owbn_board_messages (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		role_path VARCHAR(255) NOT NULL,
		site_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		user_id BIGINT UNSIGNED NOT NULL,
		content TEXT NOT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		deleted_at DATETIME NULL,
		PRIMARY KEY (id),
		KEY idx_feed (site_id, role_path, created_at)
	) $charset_collate;";

	$tables[] = "CREATE TABLE {$prefix}owbn_board_tile_state (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id BIGINT UNSIGNED NOT NULL,
		tile_id VARCHAR(100) NOT NULL,
		state VARCHAR(20) NOT NULL DEFAULT 'default',
		snooze_until DATETIME NULL,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY unique_user_tile (user_id, tile_id)
	) $charset_collate;";

	$tables[] = "CREATE TABLE {$prefix}owbn_board_audit_log (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id BIGINT UNSIGNED NOT NULL,
		action VARCHAR(100) NOT NULL,
		subject_type VARCHAR(50) NULL,
		subject_id BIGINT UNSIGNED NULL,
		details TEXT NULL,
		ip_address VARCHAR(45) NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_user (user_id, created_at),
		KEY idx_action (action, created_at)
	) $charset_collate;";

	foreach ( $tables as $sql ) {
		dbDelta( $sql );
	}

	// On fresh install, auto-enable all default modules
	if ( false === get_option( 'owbn_board_enabled_modules', false ) ) {
		owbn_board_discover_modules();
		$defaults = [];
		foreach ( owbn_board_get_registered_modules() as $module_id => $module ) {
			if ( ! empty( $module['default'] ) ) {
				$defaults[] = $module_id;
			}
		}
		update_option( 'owbn_board_enabled_modules', $defaults );
	} else {
		// On re-activation, ensure newly introduced always-on admin modules
		// (tile-access) are present without clobbering the user's choices.
		owbn_board_ensure_tile_access_enabled();
	}

	if ( function_exists( 'owbn_board_install_enabled_modules' ) ) {
		owbn_board_install_enabled_modules();
	}

	update_option( 'owbn_board_db_version', OWBN_BOARD_DB_VERSION );
}

/**
 * One-time migration: ensure the tile-access module is in the enabled list.
 * Runs on activation and on plugins_loaded for upgrades-without-reactivation.
 * Uses a dedicated option flag so admins can still disable tile-access
 * afterward without it being auto-re-added on the next pageload.
 */
function owbn_board_ensure_tile_access_enabled() {
	if ( get_option( 'owbn_board_tile_access_migrated', false ) ) {
		return;
	}
	$enabled = get_option( 'owbn_board_enabled_modules', [] );
	if ( is_array( $enabled ) && ! in_array( 'tile-access', $enabled, true ) ) {
		$enabled[] = 'tile-access';
		update_option( 'owbn_board_enabled_modules', $enabled );
	}
	update_option( 'owbn_board_tile_access_migrated', 1 );
}

/**
 * Plugin deactivation. Preserves all data.
 */
function owbn_board_deactivate() {
	wp_clear_scheduled_hook( 'owbn_board_daily_cleanup' );
}

/**
 * Write to the audit log.
 */
function owbn_board_audit( $user_id, $action, $subject_type = null, $subject_id = null, $details = [] ) {
	global $wpdb;

	$wpdb->insert(
		$wpdb->prefix . 'owbn_board_audit_log',
		[
			'user_id'      => absint( $user_id ),
			'action'       => sanitize_text_field( $action ),
			'subject_type' => $subject_type ? sanitize_text_field( $subject_type ) : null,
			'subject_id'   => $subject_id ? absint( $subject_id ) : null,
			'details'      => $details ? wp_json_encode( $details ) : null,
			'ip_address'   => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : null,
			'created_at'   => current_time( 'mysql' ),
		],
		[ '%d', '%s', '%s', '%d', '%s', '%s', '%s' ]
	);
}
