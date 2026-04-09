<?php
/**
 * Module registry. Modules self-register at plugins_loaded; enabled ones run
 * their loader callback. Disabled modules are idle but their data is preserved.
 */

defined( 'ABSPATH' ) || exit;

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

function owbn_board_get_registered_modules() {
	global $owbn_board_modules;
	return is_array( $owbn_board_modules ) ? $owbn_board_modules : [];
}

function owbn_board_get_enabled_modules() {
	$enabled = get_option( 'owbn_board_enabled_modules', [] );
	return is_array( $enabled ) ? $enabled : [];
}

function owbn_board_set_enabled_modules( array $module_ids ) {
	$clean = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $module_ids ) ) ) );
	return update_option( 'owbn_board_enabled_modules', $clean );
}

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

function owbn_board_enable_module( $module_id ) {
	owbn_board_discover_modules();

	$modules = owbn_board_get_registered_modules();
	if ( ! isset( $modules[ $module_id ] ) ) {
		return false;
	}

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

function owbn_board_disable_module( $module_id ) {
	$enabled = owbn_board_get_enabled_modules();
	$enabled = array_values( array_diff( $enabled, [ $module_id ] ) );
	return owbn_board_set_enabled_modules( $enabled );
}
