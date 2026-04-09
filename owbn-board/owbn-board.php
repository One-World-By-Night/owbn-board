<?php
/**
 * Plugin Name: OWBN Board
 * Plugin URI: https://github.com/One-World-By-Night/owbn-board
 * Description: Unified working dashboard for One World by Night. Every site's landing page becomes a tile-based workspace scoped by accessSchema role.
 * Version: 0.2.6
 * Author: One World By Night
 * Author URI: https://www.owbn.net
 * Text Domain: owbn-board
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'OWBN_BOARD_VERSION', '0.2.6' );
define( 'OWBN_BOARD_DB_VERSION', '0.2.6' );
define( 'OWBN_BOARD_FILE', __FILE__ );
define( 'OWBN_BOARD_DIR', plugin_dir_path( __FILE__ ) );
define( 'OWBN_BOARD_URL', plugin_dir_url( __FILE__ ) );
define( 'OWBN_BOARD_BASENAME', plugin_basename( __FILE__ ) );

// Core
require_once OWBN_BOARD_DIR . 'includes/core/activation.php';
require_once OWBN_BOARD_DIR . 'includes/core/tile-registry.php';
require_once OWBN_BOARD_DIR . 'includes/core/role-resolver.php';
require_once OWBN_BOARD_DIR . 'includes/core/permissions.php';
require_once OWBN_BOARD_DIR . 'includes/core/layout.php';
require_once OWBN_BOARD_DIR . 'includes/core/state.php';
require_once OWBN_BOARD_DIR . 'includes/core/render.php';
require_once OWBN_BOARD_DIR . 'includes/core/module-registry.php';
require_once OWBN_BOARD_DIR . 'includes/core/site-map.php';

// AJAX
require_once OWBN_BOARD_DIR . 'includes/ajax/notebook-save.php';
require_once OWBN_BOARD_DIR . 'includes/ajax/layout-save.php';
require_once OWBN_BOARD_DIR . 'includes/ajax/tile-state.php';

// Admin
if ( is_admin() ) {
	require_once OWBN_BOARD_DIR . 'includes/admin/settings.php';
	require_once OWBN_BOARD_DIR . 'includes/admin/layout-page.php';
	require_once OWBN_BOARD_DIR . 'includes/admin/modules-page.php';
}

// Activation / deactivation
register_activation_hook( __FILE__, 'owbn_board_activate' );
register_deactivation_hook( __FILE__, 'owbn_board_deactivate' );

/**
 * Plugin init — load enabled modules, register built-in tiles.
 * Fires on plugins_loaded priority 20 so other OWBN plugins get a chance
 * to hook owbn_board_register_tiles at the default priority.
 */
add_action( 'plugins_loaded', function () {
	load_plugin_textdomain( 'owbn-board', false, dirname( OWBN_BOARD_BASENAME ) . '/languages' );
	owbn_board_ensure_tile_access_enabled();
	owbn_board_load_enabled_modules();
	do_action( 'owbn_board_register_tiles' );
}, 20 );

/**
 * Enqueue board assets on pages that render the board.
 * Called by the shortcode, Elementor widget, and admin layout page.
 */
function owbn_board_enqueue_assets() {
	wp_enqueue_style(
		'owbn-board',
		OWBN_BOARD_URL . 'assets/css/board.css',
		[],
		OWBN_BOARD_VERSION
	);
	wp_enqueue_script(
		'owbn-board',
		OWBN_BOARD_URL . 'assets/js/board.js',
		[ 'jquery', 'jquery-ui-sortable' ],
		OWBN_BOARD_VERSION,
		true
	);
	wp_localize_script(
		'owbn-board',
		'OWBN_BOARD',
		[
			'ajax_url'   => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'owbn_board' ),
			// wp-voting-plugin's cast-ballot endpoint validates with the
			// 'wpvp_public' action — we localize it here so the ballot tile's
			// Submit All button can POST directly to wpvp_cast_ballot without
			// going through a board-side proxy.
			'wpvp_nonce' => wp_create_nonce( 'wpvp_public' ),
			'user_id'    => get_current_user_id(),
			'i18n'       => [
				'saving'      => __( 'Saving...', 'owbn-board' ),
				'saved'       => __( 'Saved', 'owbn-board' ),
				'save_failed' => __( 'Save failed — retrying', 'owbn-board' ),
				'locked_by'   => __( 'Being edited by %s', 'owbn-board' ),
			],
		]
	);
	wp_enqueue_editor();
}

/**
 * [owbn_board] shortcode — render the full board.
 */
add_shortcode( 'owbn_board', function ( $atts ) {
	if ( ! is_user_logged_in() ) {
		return '<div class="owbn-board-login-required">' . esc_html__( 'Please log in to view the board.', 'owbn-board' ) . '</div>';
	}
	owbn_board_enqueue_assets();
	return owbn_board_render();
} );
