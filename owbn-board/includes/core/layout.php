<?php
/**
 * Per-site layout stored as owbn_board_layout option.
 */

defined( 'ABSPATH' ) || exit;

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

			// Absent keys mean "use the tile's registered default", so only
			// persist the override keys that are actually present.
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

	// Invalidate object cache before update_option to tighten the layout-save
	// vs tile-access-save race window.
	wp_cache_delete( 'owbn_board_layout', 'options' );
	return update_option( 'owbn_board_layout', $sanitized );
}

// Mirrors tile-access/sanitize_patterns but lives in core so layout save still
// works when the tile-access module is disabled.
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
