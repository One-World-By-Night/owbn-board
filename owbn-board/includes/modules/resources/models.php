<?php
/**
 * Resources module — data access.
 *
 * Articles use get_posts()/WP_Query directly. This file covers the links table.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_resources_links_table() {
	global $wpdb;
	return $wpdb->prefix . 'owbn_board_resource_links';
}

/**
 * Fetch recent links, most recent first.
 */
function owbn_board_resources_get_links( $limit = 10, $offset = 0 ) {
	global $wpdb;
	$table = owbn_board_resources_links_table();
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
			absint( $limit ),
			absint( $offset )
		)
	);
}

function owbn_board_resources_get_link( $id ) {
	global $wpdb;
	$table = owbn_board_resources_links_table();
	return $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) )
	);
}

function owbn_board_resources_create_link( array $data ) {
	global $wpdb;

	$title = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '';
	$url   = isset( $data['url'] ) ? esc_url_raw( $data['url'] ) : '';

	if ( empty( $title ) || empty( $url ) ) {
		return false;
	}

	$result = $wpdb->insert(
		owbn_board_resources_links_table(),
		[
			'title'       => $title,
			'url'         => $url,
			'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
			'category'    => isset( $data['category'] ) ? sanitize_text_field( $data['category'] ) : '',
			'created_at'  => current_time( 'mysql' ),
			'created_by'  => get_current_user_id(),
		],
		[ '%s', '%s', '%s', '%s', '%s', '%d' ]
	);

	return false === $result ? false : (int) $wpdb->insert_id;
}

function owbn_board_resources_update_link( $id, array $data ) {
	global $wpdb;

	$update = [];
	$fmt    = [];
	if ( isset( $data['title'] ) ) {
		$update['title'] = sanitize_text_field( $data['title'] );
		$fmt[]           = '%s';
	}
	if ( isset( $data['url'] ) ) {
		$update['url'] = esc_url_raw( $data['url'] );
		$fmt[]         = '%s';
	}
	if ( isset( $data['description'] ) ) {
		$update['description'] = sanitize_textarea_field( $data['description'] );
		$fmt[]                 = '%s';
	}
	if ( isset( $data['category'] ) ) {
		$update['category'] = sanitize_text_field( $data['category'] );
		$fmt[]              = '%s';
	}

	if ( empty( $update ) ) {
		return false;
	}

	$result = $wpdb->update(
		owbn_board_resources_links_table(),
		$update,
		[ 'id' => absint( $id ) ],
		$fmt,
		[ '%d' ]
	);
	return false !== $result;
}

function owbn_board_resources_delete_link( $id ) {
	global $wpdb;
	return false !== $wpdb->delete(
		owbn_board_resources_links_table(),
		[ 'id' => absint( $id ) ],
		[ '%d' ]
	);
}

/**
 * Fetch recent resource articles (CPT).
 */
function owbn_board_resources_get_articles( $limit = 5 ) {
	if ( ! post_type_exists( 'owbn_resource' ) ) {
		return [];
	}
	return (array) get_posts( [
		'post_type'      => 'owbn_resource',
		'post_status'    => 'publish',
		'posts_per_page' => absint( $limit ),
		'orderby'        => 'date',
		'order'          => 'DESC',
	] );
}
