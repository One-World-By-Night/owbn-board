<?php
/**
 * Admin menu registration — top-level menu + submenus.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	add_menu_page(
		__( 'OWBN Board', 'owbn-board' ),
		__( 'OWBN Board', 'owbn-board' ),
		'manage_options',
		'owbn-board',
		'owbn_board_render_layout_page',
		'dashicons-grid-view',
		60
	);

	add_submenu_page(
		'owbn-board',
		__( 'Layout', 'owbn-board' ),
		__( 'Layout', 'owbn-board' ),
		'manage_options',
		'owbn-board',
		'owbn_board_render_layout_page'
	);

	add_submenu_page(
		'owbn-board',
		__( 'Modules', 'owbn-board' ),
		__( 'Modules', 'owbn-board' ),
		'manage_options',
		'owbn-board-modules',
		'owbn_board_render_modules_page'
	);
} );

