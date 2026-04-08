<?php
/**
 * Site layout — per-site enable/disable, reorder, size overrides.
 * Stored as a single option: owbn_board_layout.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get the current site's board layout config.
 * Merges with sensible defaults for any missing fields.
 *
 * @return array
 */
function owbn_board_get_site_layout() {
	$layout = get_option( 'owbn_board_layout', [] );

	$defaults = [
		'url_path'      => '/dashboard',
		'is_front_page' => false,
		'layout_mode'   => 'grid',
		'header_html'   => '',
		'tiles'         => [],
	];

	return wp_parse_args( $layout, $defaults );
}

/**
 * Save the site layout.
 *
 * @param array $layout
 * @return bool
 */
function owbn_board_save_site_layout( array $layout ) {
	$sanitized = [
		'url_path'      => isset( $layout['url_path'] ) ? esc_url_raw( $layout['url_path'] ) : '/dashboard',
		'is_front_page' => ! empty( $layout['is_front_page'] ),
		'layout_mode'   => in_array( $layout['layout_mode'] ?? 'grid', [ 'grid', 'list', 'tabs' ], true ) ? $layout['layout_mode'] : 'grid',
		'header_html'   => isset( $layout['header_html'] ) ? wp_kses_post( $layout['header_html'] ) : '',
		'tiles'         => [],
	];

	if ( isset( $layout['tiles'] ) && is_array( $layout['tiles'] ) ) {
		foreach ( $layout['tiles'] as $tile_id => $config ) {
			$tile_id = sanitize_text_field( $tile_id );
			if ( '' === $tile_id ) {
				continue;
			}
			$sanitized['tiles'][ $tile_id ] = [
				'enabled'  => ! empty( $config['enabled'] ),
				'size'     => in_array( $config['size'] ?? '1x1', owbn_board_allowed_sizes(), true ) ? $config['size'] : '1x1',
				'priority' => isset( $config['priority'] ) ? (int) $config['priority'] : 10,
				'category' => isset( $config['category'] ) ? sanitize_text_field( $config['category'] ) : 'general',
			];
		}
	}

	return update_option( 'owbn_board_layout', $sanitized );
}
