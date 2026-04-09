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
			$entry = [
				'enabled'  => ! empty( $config['enabled'] ),
				'size'     => in_array( $config['size'] ?? '1x1', owbn_board_allowed_sizes(), true ) ? $config['size'] : '1x1',
				'priority' => isset( $config['priority'] ) ? (int) $config['priority'] : 10,
				'category' => isset( $config['category'] ) ? sanitize_text_field( $config['category'] ) : 'general',
			];

			// Preserve per-tile access overrides (managed by the tile-access
			// module). Only persist the keys that are actually present — an
			// absent key means "use the tile's registered default".
			if ( isset( $config['read_roles'] ) && is_array( $config['read_roles'] ) ) {
				$entry['read_roles'] = owbn_board_layout_sanitize_patterns( $config['read_roles'] );
			}
			if ( isset( $config['write_roles'] ) && is_array( $config['write_roles'] ) ) {
				$entry['write_roles'] = owbn_board_layout_sanitize_patterns( $config['write_roles'] );
			}
			if ( isset( $config['share_level'] ) && is_array( $config['share_level'] ) ) {
				$entry['share_level'] = owbn_board_layout_sanitize_patterns( $config['share_level'] );
			}

			$sanitized['tiles'][ $tile_id ] = $entry;
		}
	}

	return update_option( 'owbn_board_layout', $sanitized );
}

/**
 * Sanitize a list of ASC role patterns stored in the layout option.
 * Mirrors owbn_board_tile_access_sanitize_patterns() but lives in core so
 * the layout save path doesn't depend on the tile-access module being
 * loaded (saved overrides must survive even if that module is disabled).
 *
 * @param mixed $input
 * @return array
 */
function owbn_board_layout_sanitize_patterns( $input ) {
	if ( ! is_array( $input ) ) {
		return [];
	}
	$out = [];
	foreach ( $input as $pattern ) {
		$pattern = trim( (string) $pattern );
		if ( '' === $pattern ) {
			continue;
		}
		$pattern = preg_replace( '#[^a-zA-Z0-9/_\-\*]#', '', $pattern );
		if ( '' === $pattern ) {
			continue;
		}
		$out[] = $pattern;
	}
	return array_values( array_unique( $out ) );
}
