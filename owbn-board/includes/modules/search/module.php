<?php
/**
 * Search module — universal search across all registered search providers.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/tiles.php';

owbn_board_register_module( [
	'id'          => 'search',
	'label'       => __( 'Universal Search (Pending Development)', 'owbn-board' ),
	'description' => __( 'Single search box that queries every registered OWBN data source. Pending Development — no providers wired yet; enabling the module renders a search box that returns empty until modules register callbacks via the owbn_board_search_providers filter.', 'owbn-board' ),
	'version'     => '1.0.0',
	'default'     => false,
	'depends_on'  => [],
	'schema'      => null,
	'loader'      => 'owbn_board_search_init',
] );

function owbn_board_search_init() {
	add_action( 'owbn_board_register_tiles', 'owbn_board_search_register_tile' );
	add_action( 'wp_ajax_owbn_board_search', 'owbn_board_search_ajax' );
}

/**
 * AJAX: dispatch a search query to all registered providers, merge results.
 */
function owbn_board_search_ajax() {
	if ( ! check_ajax_referer( 'owbn_board', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
	}

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( [ 'message' => 'Not logged in' ], 401 );
	}

	$query = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
	if ( strlen( $query ) < 2 ) {
		wp_send_json_success( [ 'results' => [] ] );
	}

	$roles     = owbn_board_get_user_roles( $user_id );
	$providers = apply_filters( 'owbn_board_search_providers', [] );

	$grouped = [];
	foreach ( $providers as $provider ) {
		if ( empty( $provider['id'] ) || ! is_callable( $provider['callback'] ?? null ) ) {
			continue;
		}
		try {
			$results = call_user_func( $provider['callback'], $query, $user_id, $roles );
			if ( is_array( $results ) && ! empty( $results ) ) {
				$grouped[] = [
					'id'      => $provider['id'],
					'label'   => $provider['label'] ?? $provider['id'],
					'results' => array_slice( $results, 0, (int) ( $provider['max_results'] ?? 5 ) ),
				];
			}
		} catch ( Throwable $e ) {
			error_log( sprintf( '[owbn-board] Search provider %s failed: %s', $provider['id'], $e->getMessage() ) );
		}
	}

	wp_send_json_success( [ 'results' => $grouped ] );
}
