<?php
/**
 * Visitors module — cross-chronicle character travel log.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/models.php';
require_once __DIR__ . '/tiles.php';

owbn_board_register_module( [
	'id'          => 'visitors',
	'label'       => __( 'Visitors', 'owbn-board' ),
	'description' => __( 'Cross-chronicle character travel log. Staff at a host chronicle log visiting characters from elsewhere.', 'owbn-board' ),
	'version'     => '1.0.0',
	'default'     => false,
	'depends_on'  => [],
	'schema'      => 'owbn_board_visitors_install_schema',
	'loader'      => 'owbn_board_visitors_init',
] );

function owbn_board_visitors_init() {
	add_action( 'owbn_board_register_tiles', 'owbn_board_visitors_register_tile' );
	add_action( 'wp_ajax_owbn_board_visitors_create', 'owbn_board_visitors_ajax_create' );
}

/**
 * AJAX: create a visit entry.
 */
function owbn_board_visitors_ajax_create() {
	if ( ! check_ajax_referer( 'owbn_board', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
	}

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( [ 'message' => 'Not logged in' ], 401 );
	}

	$host_slug = isset( $_POST['host_chronicle_slug'] ) ? sanitize_key( wp_unslash( $_POST['host_chronicle_slug'] ) ) : '';
	if ( empty( $host_slug ) ) {
		wp_send_json_error( [ 'message' => 'Missing host chronicle' ], 400 );
	}

	// User must have staff-level role for the host chronicle
	$host_slugs = owbn_board_visitors_user_host_slugs( $user_id );
	if ( ! in_array( $host_slug, $host_slugs, true ) ) {
		wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
	}

	// Resolve visitor user by email if provided
	$visitor_user_id = 0;
	$visitor_name    = '';
	if ( ! empty( $_POST['visitor_email'] ) ) {
		$email = sanitize_email( wp_unslash( $_POST['visitor_email'] ) );
		$user  = get_user_by( 'email', $email );
		if ( $user ) {
			$visitor_user_id = $user->ID;
			$visitor_name    = $user->display_name;
		}
	}

	$data = [
		'host_chronicle_slug'  => $host_slug,
		'home_chronicle_slug'  => isset( $_POST['home_chronicle_slug'] ) ? sanitize_key( wp_unslash( $_POST['home_chronicle_slug'] ) ) : '',
		'visitor_user_id'      => $visitor_user_id,
		'visitor_display_name' => $visitor_name,
		'character_name'       => isset( $_POST['character_name'] ) ? sanitize_text_field( wp_unslash( $_POST['character_name'] ) ) : '',
		'visit_date'           => isset( $_POST['visit_date'] ) ? sanitize_text_field( wp_unslash( $_POST['visit_date'] ) ) : '',
		'notes'                => isset( $_POST['notes'] ) ? wp_kses_post( wp_unslash( $_POST['notes'] ) ) : '',
	];

	$new_id = owbn_board_visitors_create( $data );
	if ( false === $new_id ) {
		wp_send_json_error( [ 'message' => 'Missing required fields or invalid date' ], 400 );
	}

	owbn_board_audit( $user_id, 'visitors.create', 'visitor', $new_id, [ 'host' => $host_slug ] );

	// TODO: notify home chronicle staff via owbn-notifications when that plugin exists

	wp_send_json_success( [ 'id' => $new_id ] );
}
