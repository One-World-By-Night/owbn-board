<?php
/**
 * Module registry — internal LARP tool modules live in includes/modules/{name}/.
 *
 * Each module declares itself via owbn_board_register_module() at plugins_loaded.
 * Enabled modules run their loader callback to wire up hooks, tiles, admin pages.
 * Disabled modules are idle but their data is preserved.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register a module.
 *
 * @param array $args {
 *   @type string   $id          Unique module ID (e.g. 'sessions', 'downtime')
 *   @type string   $label       Human-readable label
 *   @type string   $description Short description for the admin UI
 *   @type string   $version     Module version string
 *   @type bool     $default     Enabled by default on fresh install? (default false)
 *   @type array    $sites       Site slugs where this module is allowed (default all)
 *   @type array    $depends_on  Other module IDs required (default [])
 *   @type callable $schema      Callback to create tables (run on install)
 *   @type callable $loader      Callback to register hooks/tiles (run on every pageload when enabled)
 * }
 * @return bool
 */
function owbn_board_register_module( array $args ) {
	global $owbn_board_modules;

	if ( ! is_array( $owbn_board_modules ) ) {
		$owbn_board_modules = [];
	}

	$defaults = [
		'id'          => '',
		'label'       => '',
		'description' => '',
		'version'     => '1.0.0',
		'default'     => false,
		'sites'       => [],
		'depends_on'  => [],
		'schema'      => null,
		'loader'      => null,
	];

	$module = wp_parse_args( $args, $defaults );

	if ( empty( $module['id'] ) || empty( $module['label'] ) ) {
		error_log( '[owbn-board] Module registration failed: missing id or label' );
		return false;
	}

	$owbn_board_modules[ $module['id'] ] = $module;
	return true;
}

/**
 * Get all registered modules.
 */
function owbn_board_get_registered_modules() {
	global $owbn_board_modules;
	return is_array( $owbn_board_modules ) ? $owbn_board_modules : [];
}

/**
 * Get the list of enabled module IDs for the current site.
 *
 * @return array
 */
function owbn_board_get_enabled_modules() {
	$enabled = get_option( 'owbn_board_enabled_modules', [] );
	return is_array( $enabled ) ? $enabled : [];
}

/**
 * Set the enabled modules list.
 *
 * @param array $module_ids
 * @return bool
 */
function owbn_board_set_enabled_modules( array $module_ids ) {
	$clean = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $module_ids ) ) ) );
	return update_option( 'owbn_board_enabled_modules', $clean );
}

/**
 * Discover and auto-register modules in includes/modules/{name}/module.php.
 * Must be called before enabled modules are loaded.
 */
function owbn_board_discover_modules() {
	$modules_dir = OWBN_BOARD_DIR . 'includes/modules';
	if ( ! is_dir( $modules_dir ) ) {
		return;
	}

	$entries = scandir( $modules_dir );
	foreach ( (array) $entries as $entry ) {
		if ( '.' === $entry || '..' === $entry || 'README.md' === $entry ) {
			continue;
		}
		$module_file = $modules_dir . '/' . $entry . '/module.php';
		if ( file_exists( $module_file ) ) {
			require_once $module_file;
		}
	}
}

/**
 * Load all enabled modules — call their loader callbacks.
 * Runs on plugins_loaded after modules have been discovered/registered.
 */
function owbn_board_load_enabled_modules() {
	owbn_board_discover_modules();

	$modules = owbn_board_get_registered_modules();
	$enabled = owbn_board_get_enabled_modules();

	foreach ( $modules as $module_id => $module ) {
		if ( ! in_array( $module_id, $enabled, true ) ) {
			continue;
		}
		if ( is_callable( $module['loader'] ) ) {
			call_user_func( $module['loader'] );
		}
	}
}

/**
 * Install schemas for all enabled modules.
 * Called from activation hook.
 */
function owbn_board_install_enabled_modules() {
	owbn_board_discover_modules();

	$modules = owbn_board_get_registered_modules();
	$enabled = owbn_board_get_enabled_modules();

	foreach ( $modules as $module_id => $module ) {
		if ( ! in_array( $module_id, $enabled, true ) ) {
			continue;
		}
		if ( is_callable( $module['schema'] ) ) {
			call_user_func( $module['schema'] );
		}
	}
}

/**
 * Enable a module — run its schema install and add to enabled list.
 *
 * @param string $module_id
 * @return bool
 */
function owbn_board_enable_module( $module_id ) {
	owbn_board_discover_modules();

	$modules = owbn_board_get_registered_modules();
	if ( ! isset( $modules[ $module_id ] ) ) {
		return false;
	}

	// Check dependencies
	$enabled = owbn_board_get_enabled_modules();
	foreach ( (array) $modules[ $module_id ]['depends_on'] as $dep ) {
		if ( ! in_array( $dep, $enabled, true ) ) {
			return false;
		}
	}

	if ( is_callable( $modules[ $module_id ]['schema'] ) ) {
		call_user_func( $modules[ $module_id ]['schema'] );
	}

	$enabled[] = $module_id;
	return owbn_board_set_enabled_modules( $enabled );
}

/**
 * Disable a module — remove from enabled list. Data preserved.
 *
 * @param string $module_id
 * @return bool
 */
function owbn_board_disable_module( $module_id ) {
	$enabled = owbn_board_get_enabled_modules();
	$enabled = array_values( array_diff( $enabled, [ $module_id ] ) );
	return owbn_board_set_enabled_modules( $enabled );
}
