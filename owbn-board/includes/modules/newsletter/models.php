<?php
/**
 * Newsletter module — data access layer.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_newsletter_table() {
	global $wpdb;
	return $wpdb->prefix . 'owbn_board_newsletter_editions';
}

/**
 * Fetch editions ordered by published_at DESC.
 *
 * @param int $limit  Max rows
 * @param int $offset Pagination offset
 * @return array
 */
function owbn_board_newsletter_get_editions( $limit = 10, $offset = 0 ) {
	global $wpdb;
	$table = owbn_board_newsletter_table();
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} ORDER BY published_at DESC, id DESC LIMIT %d OFFSET %d",
			absint( $limit ),
			absint( $offset )
		)
	);
}

/**
 * Fetch a single edition by ID.
 */
function owbn_board_newsletter_get_edition( $id ) {
	global $wpdb;
	$table = owbn_board_newsletter_table();
	return $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) )
	);
}

/**
 * Insert a new edition. Returns the new ID or false on failure.
 *
 * @param array $data ['title', 'published_at', 'url', 'summary', 'cover_image_id']
 * @return int|false
 */
function owbn_board_newsletter_create_edition( array $data ) {
	global $wpdb;

	$row = owbn_board_newsletter_sanitize( $data );
	if ( empty( $row['title'] ) || empty( $row['published_at'] ) || empty( $row['url'] ) ) {
		return false;
	}

	$row['created_at'] = current_time( 'mysql' );
	$row['created_by'] = get_current_user_id();

	$result = $wpdb->insert(
		owbn_board_newsletter_table(),
		$row,
		[ '%s', '%s', '%s', '%s', '%d', '%s', '%d' ]
	);

	return false === $result ? false : (int) $wpdb->insert_id;
}

/**
 * Update an edition. Returns true on success.
 */
function owbn_board_newsletter_update_edition( $id, array $data ) {
	global $wpdb;

	$row = owbn_board_newsletter_sanitize( $data );
	unset( $row['created_at'], $row['created_by'] );

	if ( empty( $row ) ) {
		return false;
	}

	$formats = [];
	foreach ( $row as $k => $v ) {
		$formats[] = 'cover_image_id' === $k ? '%d' : '%s';
	}

	$result = $wpdb->update(
		owbn_board_newsletter_table(),
		$row,
		[ 'id' => absint( $id ) ],
		$formats,
		[ '%d' ]
	);

	return false !== $result;
}

/**
 * Delete an edition.
 */
function owbn_board_newsletter_delete_edition( $id ) {
	global $wpdb;
	return false !== $wpdb->delete(
		owbn_board_newsletter_table(),
		[ 'id' => absint( $id ) ],
		[ '%d' ]
	);
}

/**
 * Count total editions (for pagination).
 */
function owbn_board_newsletter_count() {
	global $wpdb;
	$table = owbn_board_newsletter_table();
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
}

/**
 * Sanitize an edition row. Accepts any subset of the fields.
 */
function owbn_board_newsletter_sanitize( array $data ) {
	$out = [];
	if ( isset( $data['title'] ) ) {
		$out['title'] = sanitize_text_field( (string) $data['title'] );
	}
	if ( isset( $data['published_at'] ) ) {
		$date = sanitize_text_field( (string) $data['published_at'] );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$out['published_at'] = $date;
		}
	}
	if ( isset( $data['url'] ) ) {
		$out['url'] = esc_url_raw( (string) $data['url'] );
	}
	if ( isset( $data['summary'] ) ) {
		$out['summary'] = sanitize_textarea_field( (string) $data['summary'] );
	}
	if ( isset( $data['cover_image_id'] ) ) {
		$cover_id = absint( $data['cover_image_id'] );
		if ( $cover_id > 0 ) {
			$mime = get_post_mime_type( $cover_id );
			if ( ! $mime || 0 !== strpos( (string) $mime, 'image/' ) ) {
				$cover_id = 0;
			}
		}
		$out['cover_image_id'] = $cover_id;
	}
	return $out;
}
