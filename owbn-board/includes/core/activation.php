<?php
/**
 * Plugin activation — schema. Tables for cross-site data only created on chronicles (host).
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_is_host_site() {
	if ( defined( 'OWBN_BOARD_FORCE_HOST' ) && OWBN_BOARD_FORCE_HOST ) {
		return true;
	}
	$slug = function_exists( 'owbn_board_get_site_slug' ) ? owbn_board_get_site_slug() : '';
	if ( 'chronicles' === $slug ) {
		return true;
	}
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	return ( false !== strpos( (string) $host, 'chronicles.owbn.net' ) );
}

function owbn_board_activate() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();
	$prefix          = $wpdb->prefix;

	// Cross-site canonical tables — only on chronicles
	if ( owbn_board_is_host_site() ) {
		$tables = [];

		$tables[] = "CREATE TABLE {$prefix}owbn_board_messages (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			scope VARCHAR(255) NOT NULL,
			owner_email VARCHAR(255) NOT NULL,
			content TEXT NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL,
			PRIMARY KEY (id),
			KEY idx_feed (scope, created_at),
			KEY idx_owner (owner_email)
		) $charset_collate;";

		$tables[] = "CREATE TABLE {$prefix}owbn_board_notebooks (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			scope VARCHAR(255) NOT NULL,
			title VARCHAR(255) NOT NULL DEFAULT '',
			content LONGTEXT NULL,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_by_email VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY (id),
			UNIQUE KEY unique_scope (scope)
		) $charset_collate;";

		$tables[] = "CREATE TABLE {$prefix}owbn_board_notebook_history (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			notebook_id BIGINT UNSIGNED NOT NULL,
			content LONGTEXT NULL,
			changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			changed_by_email VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY (id),
			KEY idx_notebook (notebook_id, changed_at)
		) $charset_collate;";

		$tables[] = "CREATE TABLE {$prefix}owbn_board_handoffs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			scope VARCHAR(255) NOT NULL,
			title VARCHAR(255) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY unique_scope (scope)
		) $charset_collate;";

		$tables[] = "CREATE TABLE {$prefix}owbn_board_handoff_sections (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			handoff_id BIGINT UNSIGNED NOT NULL,
			label VARCHAR(255) NOT NULL,
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY idx_handoff (handoff_id, sort_order)
		) $charset_collate;";

		$tables[] = "CREATE TABLE {$prefix}owbn_board_handoff_entries (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			handoff_id BIGINT UNSIGNED NOT NULL,
			section_id BIGINT UNSIGNED NULL,
			section_label VARCHAR(255) NOT NULL DEFAULT '',
			title VARCHAR(255) NULL,
			body LONGTEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			created_by_email VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY (id),
			KEY idx_handoff (handoff_id, created_at)
		) $charset_collate;";

		$tables[] = "CREATE TABLE {$prefix}owbn_board_sessions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			chronicle_slug VARCHAR(100) NOT NULL,
			session_date DATE NOT NULL,
			title VARCHAR(255) NOT NULL DEFAULT '',
			summary LONGTEXT NULL,
			notes LONGTEXT NULL,
			attendance TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			created_by_email VARCHAR(255) NOT NULL DEFAULT '',
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_by_email VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY (id),
			KEY idx_chronicle (chronicle_slug, session_date)
		) $charset_collate;";

		$tables[] = "CREATE TABLE {$prefix}owbn_board_visitors (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			host_chronicle_slug VARCHAR(100) NOT NULL,
			home_chronicle_slug VARCHAR(100) NULL,
			character_name VARCHAR(255) NOT NULL,
			visitor_email VARCHAR(255) NULL,
			visitor_display_name VARCHAR(255) NULL,
			visit_date DATE NOT NULL,
			notes TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			created_by_email VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY (id),
			KEY idx_host (host_chronicle_slug, visit_date),
			KEY idx_visitor (visitor_email)
		) $charset_collate;";

		$tables[] = "CREATE TABLE {$prefix}owbn_board_user_state (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			owner_email VARCHAR(255) NOT NULL,
			tile_id VARCHAR(100) NOT NULL,
			state VARCHAR(20) NOT NULL DEFAULT 'default',
			snooze_until DATETIME NULL,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY unique_user_tile (owner_email, tile_id)
		) $charset_collate;";

		$tables[] = "CREATE TABLE {$prefix}owbn_board_user_prefs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			owner_email VARCHAR(255) NOT NULL,
			pref_key VARCHAR(100) NOT NULL,
			pref_value LONGTEXT NULL,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY unique_user_pref (owner_email, pref_key)
		) $charset_collate;";

		$tables[] = "CREATE TABLE {$prefix}owbn_board_audit_log (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			owner_email VARCHAR(255) NOT NULL DEFAULT '',
			action VARCHAR(100) NOT NULL,
			subject_type VARCHAR(50) NULL,
			subject_id BIGINT UNSIGNED NULL,
			details TEXT NULL,
			ip_address VARCHAR(45) NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_owner (owner_email, created_at),
			KEY idx_action (action, created_at)
		) $charset_collate;";

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}
	}

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
		owbn_board_ensure_tile_access_enabled();
	}

	if ( function_exists( 'owbn_board_install_enabled_modules' ) ) {
		owbn_board_install_enabled_modules();
	}

	update_option( 'owbn_board_db_version', OWBN_BOARD_DB_VERSION );
}

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

function owbn_board_deactivate() {
	wp_clear_scheduled_hook( 'owbn_board_daily_cleanup' );
}

// Convenience: write an audit row via the cross-site wrapper.
function owbn_board_audit( $user_id, $action, $subject_type = null, $subject_id = null, $details = [] ) {
	$email = '';
	if ( $user_id ) {
		$user = get_user_by( 'id', $user_id );
		$email = $user ? $user->user_email : '';
	}
	if ( function_exists( 'owc_board_audit_log' ) ) {
		owc_board_audit_log( $email, $action, $subject_type, $subject_id, $details );
	}
}
