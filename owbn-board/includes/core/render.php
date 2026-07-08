<?php
/**
 * Main board renderer — tile loop + wrapper HTML.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_render() {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return '<div class="owbn-board-login-required">' . esc_html__( 'Please log in to view the board.', 'owbn-board' ) . '</div>';
	}

	$layout    = owbn_board_get_site_layout();
	$site_slug = owbn_board_get_site_slug();
	$tiles     = owbn_board_get_visible_tiles( $user_id, $site_slug );

	// Bucket tiles by tab. Unknown tab values fall back to 'comms'.
	$tabs_tiles = array_fill_keys( owbn_board_allowed_tabs(), array() );
	foreach ( $tiles as $tile ) {
		$tab = isset( $tile['tab'] ) && in_array( $tile['tab'], owbn_board_allowed_tabs(), true )
			? $tile['tab']
			: 'comms';
		$tabs_tiles[ $tab ][] = $tile;
	}

	$has_chron_role = function_exists( 'owc_workspace_user_has_chronicle_role' )
		? owc_workspace_user_has_chronicle_role( $user_id )
		: false;
	$has_coord_role = function_exists( 'owc_workspace_user_has_coord_role' )
		? owc_workspace_user_has_coord_role( $user_id )
		: false;

	$tab_meta = array(
		'schedule'     => array( 'label' => __( 'Schedule', 'owbn-board' ),     'visible' => true ),
		'comms'        => array( 'label' => __( 'Comms', 'owbn-board' ),        'visible' => true ),
		'players'      => array( 'label' => __( 'Players', 'owbn-board' ),      'visible' => true ),
		'chronicles'   => array( 'label' => __( 'Chronicles', 'owbn-board' ),   'visible' => $has_chron_role ),
		'coordinators' => array( 'label' => __( 'Coordinators', 'owbn-board' ), 'visible' => $has_coord_role ),
	);

	do_action( 'owbn_board_before_render', $user_id );

	ob_start();
	?>
	<div class="owbn-board owbn-board-mode-<?php echo esc_attr( $layout['layout_mode'] ); ?>" data-user-id="<?php echo esc_attr( $user_id ); ?>">
		<?php if ( ! empty( $layout['header_html'] ) ) : ?>
			<div class="owbn-board-header"><?php echo wp_kses_post( $layout['header_html'] ); ?></div>
		<?php endif; ?>

		<?php if ( function_exists( 'owc_render_workspace_top_header' ) ) : ?>
			<?php echo owc_render_workspace_top_header(); ?>
		<?php endif; ?>

		<ul class="owbn-board-tabs" role="tablist">
			<?php $first = true; foreach ( $tab_meta as $tab_key => $meta ) : ?>
				<?php if ( ! $meta['visible'] ) continue; ?>
				<li role="presentation">
					<button type="button"
						role="tab"
						class="owbn-board-tab<?php echo $first ? ' is-active' : ''; ?>"
						data-owbn-tab="<?php echo esc_attr( $tab_key ); ?>"
						aria-selected="<?php echo $first ? 'true' : 'false'; ?>"
					><?php echo esc_html( $meta['label'] ); ?></button>
				</li>
				<?php $first = false; ?>
			<?php endforeach; ?>
		</ul>

		<?php $first = true; foreach ( $tab_meta as $tab_key => $meta ) : ?>
			<?php if ( ! $meta['visible'] ) continue; ?>
			<div class="owbn-board-tab-panel<?php echo $first ? ' is-active' : ''; ?>"
				role="tabpanel"
				data-owbn-panel="<?php echo esc_attr( $tab_key ); ?>"
				data-owbn-loaded="<?php echo $first ? '1' : '0'; ?>"
				<?php echo $first ? '' : 'hidden'; ?>>
				<?php if ( $first ) : ?>
					<?php echo owbn_board_render_tab_panel( $tab_key, $tabs_tiles[ $tab_key ], $user_id ); ?>
				<?php else : ?>
					<div class="owbn-board-tab-loading">
						<?php esc_html_e( 'Loading…', 'owbn-board' ); ?>
					</div>
				<?php endif; ?>
			</div>
			<?php $first = false; ?>
		<?php endforeach; ?>
	</div>
	<?php
	$html = ob_get_clean();

	do_action( 'owbn_board_after_render', $user_id, count( $tiles ) );

	return $html;
}

/**
 * Render the inside of one tab panel.
 *
 *   - chronicles: per-chronicle tile grid via workspace helper.
 *   - coordinators: per-coord/exec tile grid via workspace helper.
 *   - schedule/comms: standard tile grid (or empty/placeholder).
 */
function owbn_board_render_tab_panel( $tab_key, array $panel_tiles, $user_id ) {
	ob_start();

	if ( 'chronicles' === $tab_key ) {
		if ( function_exists( 'owc_workspace_render_chronicles_grid' ) ) {
			echo owc_workspace_render_chronicles_grid( $user_id );
		}
		return ob_get_clean();
	}

	if ( 'coordinators' === $tab_key ) {
		if ( function_exists( 'owc_workspace_render_coordinators_grid' ) ) {
			echo owc_workspace_render_coordinators_grid( $user_id );
		}
		return ob_get_clean();
	}

	if ( 'players' === $tab_key ) {
		// Characters + Registration & Upkeep live in OAT on the Archivist. The
		// dedicated renderer fetches the viewer's own characters cross-site and
		// deep-links each to its R&U page; it falls back to a single registry
		// link when the OAT client isn't available.
		echo owbn_board_render_players_tab( $user_id );
		return ob_get_clean();
	}

	if ( 'schedule' === $tab_key && empty( $panel_tiles ) ) {
		echo '<div class="owbn-board-tab-placeholder">'
			. esc_html__( 'Schedule tab — calendar, events, and sessions will live here.', 'owbn-board' )
			. '</div>';
		return ob_get_clean();
	}

	if ( empty( $panel_tiles ) ) {
		echo owbn_board_render_empty_state( $user_id );
		return ob_get_clean();
	}

	echo '<div class="owbn-board-grid">';
	foreach ( $panel_tiles as $tile ) {
		echo owbn_board_render_tile( $tile, $user_id );
	}
	echo '</div>';

	return ob_get_clean();
}

/**
 * Players tab — the viewer's own characters and their Registration & Upkeep.
 *
 * Characters + R&U live in OAT on the Archivist. When owbn-archivist is present
 * on this site (remote mode on sso), owc_oat_get_registry() fetches the viewer's
 * OWN characters cross-site (signed with the API key + x-oat-user-email header).
 * Each row deep-links to the front-end character detail / R&U page on the
 * Archivist through the SSO redirect so the member lands authenticated.
 *
 * Degrades gracefully: if the OAT client isn't loaded or the remote fetch fails,
 * it shows the single "open the registry" link the tab shipped with before.
 */
function owbn_board_render_players_tab( $user_id ) {
	$registry_url = owbn_board_tool_url( 'oat', '/oat-registry/' );

	// OAT client absent on this site — nothing to fetch, show the registry link.
	if ( ! function_exists( 'owc_oat_get_registry' ) ) {
		return owbn_board_players_fallback( $registry_url );
	}

	$result = owc_oat_get_registry();
	if ( is_wp_error( $result ) ) {
		return owbn_board_players_fallback( $registry_url, $result->get_error_message() );
	}

	$characters = ( is_array( $result ) && isset( $result['characters'] ) && is_array( $result['characters'] ) )
		? $result['characters']
		: array();

	ob_start();
	?>
	<style>
	.owbn-board-players__intro { color: #50575e; margin: 0 0 12px; }
	.owbn-board-players__list { list-style: none; margin: 0 0 12px; padding: 0; }
	.owbn-board-players__item { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border: 1px solid #e0e0e0; border-radius: 6px; margin-bottom: 8px; background: #fff; }
	.owbn-board-players__main { display: flex; flex-direction: column; min-width: 0; flex: 1; }
	.owbn-board-players__name { font-weight: 600; text-decoration: none; }
	.owbn-board-players__meta { color: #6c7781; font-size: 12px; }
	.owbn-board-players__status { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .02em; padding: 2px 8px; border-radius: 10px; background: #eef0f2; color: #50575e; white-space: nowrap; }
	.owbn-board-players__status--active { background: #e6f4ea; color: #1a7f37; }
	.owbn-board-players__status--decommissioned, .owbn-board-players__status--dead, .owbn-board-players__status--retired { background: #f0e6e6; color: #842029; }
	.owbn-board-players__ru { white-space: nowrap; }
	.owbn-board-players__all a, .owbn-board-players__err { font-size: 13px; }
	@media (max-width: 600px) { .owbn-board-players__item { flex-wrap: wrap; } }
	</style>
	<div class="owbn-board-players">
		<p class="owbn-board-players__intro">
			<?php esc_html_e( 'Your characters and their Registration & Upkeep live on the Archivist. Open one to view its record or file R&U.', 'owbn-board' ); ?>
		</p>
		<?php if ( empty( $characters ) ) : ?>
			<div class="owbn-board-tab-placeholder">
				<p><?php esc_html_e( "You don't have any registered characters yet.", 'owbn-board' ); ?></p>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( $registry_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Open the Registry on the Archivist', 'owbn-board' ); ?> &rarr;
					</a>
				</p>
			</div>
		<?php else : ?>
			<ul class="owbn-board-players__list">
				<?php foreach ( $characters as $char ) :
					$c   = (array) $char;
					$cid = isset( $c['id'] ) ? (int) $c['id'] : 0;
					if ( ! $cid ) {
						continue;
					}
					// Safety net: this tab lists ONLY the viewer's own characters.
					// Remote mode already returns own-only; in local mode the scoped
					// registry is broader, so drop anything owned by someone else.
					$owner = isset( $c['wp_user_id'] ) ? (int) $c['wp_user_id'] : 0;
					if ( $owner && $owner !== (int) $user_id ) {
						continue;
					}
					$name = trim( (string) ( $c['character_name'] ?? '' ) );
					if ( '' === $name ) {
						$name = __( '(unnamed character)', 'owbn-board' );
					}
					$chron  = trim( (string) ( $c['chronicle_slug'] ?? '' ) );
					$type   = trim( (string) ( $c['creature_type'] ?? '' ) );
					$status = trim( (string) ( $c['status'] ?? '' ) );

					$detail_url = owbn_board_tool_url( 'oat', '/oat-registry-detail/?character_id=' . $cid );

					$meta_bits = array_filter( array( $type, $chron ) );
					?>
					<li class="owbn-board-players__item">
						<div class="owbn-board-players__main">
							<a class="owbn-board-players__name" href="<?php echo esc_url( $detail_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $name ); ?></a>
							<?php if ( ! empty( $meta_bits ) ) : ?>
								<span class="owbn-board-players__meta"><?php echo esc_html( implode( ' · ', $meta_bits ) ); ?></span>
							<?php endif; ?>
						</div>
						<?php if ( '' !== $status ) : ?>
							<span class="owbn-board-players__status owbn-board-players__status--<?php echo esc_attr( sanitize_html_class( strtolower( $status ) ) ); ?>"><?php echo esc_html( $status ); ?></span>
						<?php endif; ?>
						<a class="owbn-board-players__ru button button-secondary" href="<?php echo esc_url( $detail_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'R&U', 'owbn-board' ); ?> &rarr;</a>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="owbn-board-players__all">
				<a href="<?php echo esc_url( $registry_url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Open the full registry on the Archivist', 'owbn-board' ); ?> &rarr;
				</a>
			</p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Single-link fallback for the Players tab (OAT client missing or fetch failed).
 * The error string is only surfaced to admins to avoid leaking infra detail.
 */
function owbn_board_players_fallback( $registry_url, $error = '' ) {
	ob_start();
	?>
	<div class="owbn-board-tab-placeholder">
		<p><?php esc_html_e( 'Your characters and their Registration & Upkeep live on the Archivist. Open the registry to view or manage them.', 'owbn-board' ); ?></p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( $registry_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Open my Characters & R&U on the Archivist', 'owbn-board' ); ?> &rarr;
			</a>
		</p>
		<?php if ( $error && current_user_can( 'manage_options' ) ) : ?>
			<p class="owbn-board-players__err"><small><?php echo esc_html( $error ); ?></small></p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

function owbn_board_render_tile( array $tile, $user_id ) {
	$size      = $tile['size'];
	list( $cols, $rows ) = explode( 'x', $size );
	$can_write = owbn_board_user_can_write_tile( $tile, $user_id );
	$state     = $tile['_state'] ?? 'default';
	$classes   = [
		'owbn-board-tile',
		'owbn-board-tile--size-' . $size,
		'owbn-board-tile--state-' . $state,
		'owbn-board-tile--category-' . sanitize_html_class( $tile['category'] ),
	];
	if ( ! $can_write ) {
		$classes[] = 'owbn-board-tile--readonly';
	}

	if ( ! empty( $tile['audit'] ) ) {
		owbn_board_audit( $user_id, 'tile.read', 'tile', 0, [ 'tile_id' => $tile['id'] ] );
	}

	$poll_interval = isset( $tile['poll_interval'] ) ? (int) $tile['poll_interval'] : 0;

	ob_start();
	?>
	<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
		data-tile-id="<?php echo esc_attr( $tile['id'] ); ?>"
		data-size="<?php echo esc_attr( $size ); ?>"
		<?php if ( $poll_interval > 0 ) : ?>data-poll-interval-ms="<?php echo (int) $poll_interval; ?>"<?php endif; ?>
		style="grid-column: span <?php echo (int) $cols; ?>; grid-row: span <?php echo (int) $rows; ?>;">
		<div class="owbn-board-tile__header">
			<?php if ( ! empty( $tile['icon'] ) ) : ?>
				<span class="owbn-board-tile__icon <?php echo esc_attr( $tile['icon'] ); ?>" aria-hidden="true"></span>
			<?php endif; ?>
			<h3 class="owbn-board-tile__title"><?php echo esc_html( $tile['title'] ); ?></h3>
			<div class="owbn-board-tile__actions">
				<select class="owbn-board-tile__size-picker" aria-label="<?php esc_attr_e( 'Tile size', 'owbn-board' ); ?>">
					<?php foreach ( owbn_board_allowed_sizes() as $allowed ) : ?>
						<option value="<?php echo esc_attr( $allowed ); ?>" <?php selected( $size, $allowed ); ?>><?php echo esc_html( $allowed ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="owbn-board-tile__action owbn-board-tile__collapse" aria-label="<?php esc_attr_e( 'Collapse', 'owbn-board' ); ?>">&#9650;</button>
				<div class="owbn-board-tile__menu-wrapper">
					<button type="button" class="owbn-board-tile__action owbn-board-tile__menu" aria-haspopup="true" aria-expanded="false" aria-label="<?php esc_attr_e( 'Tile menu', 'owbn-board' ); ?>">&#8942;</button>
					<ul class="owbn-board-tile__menu-popup" hidden>
						<li><button type="button" class="owbn-board-tile__menu-item" data-action="move"><?php esc_html_e( 'Move', 'owbn-board' ); ?></button></li>
						<li><button type="button" class="owbn-board-tile__menu-item" data-action="snooze"><?php esc_html_e( 'Snooze 24h', 'owbn-board' ); ?></button></li>
						<li><button type="button" class="owbn-board-tile__menu-item" data-action="hide"><?php esc_html_e( 'Hide', 'owbn-board' ); ?></button></li>
					</ul>
				</div>
			</div>
		</div>
		<div class="owbn-board-tile__body">
			<?php echo owbn_board_render_tile_body( $tile, $user_id, $can_write ); ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

// Render just the tile body — used by both the full render and the polling AJAX refresh.
function owbn_board_render_tile_body( array $tile, $user_id, $can_write ) {
	ob_start();
	try {
		$start = microtime( true );
		call_user_func( $tile['render'], $tile, $user_id, $can_write );
		$elapsed_ms = ( microtime( true ) - $start ) * 1000;
		if ( $elapsed_ms > 200 ) {
			error_log( sprintf( '[owbn-board] Slow tile render: %s took %dms', $tile['id'], $elapsed_ms ) );
		}
	} catch ( Throwable $e ) {
		error_log( sprintf( '[owbn-board] Tile render failed: %s — %s', $tile['id'], $e->getMessage() ) );
		echo '<div class="owbn-board-tile__error">' . esc_html__( 'This tile failed to render.', 'owbn-board' ) . '</div>';
	}
	return ob_get_clean();
}

/**
 * Render a generic scope switcher dropdown for tiles that support multi-group scoping.
 * Returns nothing if there's only one scope (no need to switch).
 *
 * @param string[] $scopes  Available scope keys.
 * @param string   $active  The currently active scope.
 * @param string   $label   Optional aria-label.
 */
function owbn_board_render_scope_switcher( array $scopes, $active, $label = '' ) {
	if ( count( $scopes ) < 2 ) {
		return;
	}
	$label = $label ?: __( 'Switch scope', 'owbn-board' );
	?>
	<div class="owbn-board-scope-switcher-wrapper">
		<label class="screen-reader-text"><?php echo esc_html( $label ); ?></label>
		<select class="owbn-board-scope-switcher">
			<?php foreach ( $scopes as $scope ) : ?>
				<option value="<?php echo esc_attr( $scope ); ?>" <?php selected( $scope, $active ); ?>>
					<?php echo esc_html( $scope ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</div>
	<?php
}

// Three distinct empty states: ASC missing (infra failure), no user roles
// (user issue), no tiles configured (admin issue).
function owbn_board_render_empty_state( $user_id ) {
	$asc_missing = ! function_exists( 'owc_asc_get_user_roles' );
	$user_roles  = $asc_missing ? [] : owbn_board_get_user_roles( $user_id );
	$can_manage  = owbn_board_user_can_manage();

	ob_start();
	?>
	<div class="owbn-board-empty">
		<h2><?php esc_html_e( 'Your workspace is empty', 'owbn-board' ); ?></h2>
		<?php if ( $asc_missing ) : ?>
			<p class="owbn-board-empty__error">
				<?php esc_html_e( 'The board cannot resolve user roles because owbn-core (which provides the accessSchema wrapper) is not active on this site.', 'owbn-board' ); ?>
			</p>
			<?php if ( $can_manage ) : ?>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">
						<?php esc_html_e( 'Open Plugins admin →', 'owbn-board' ); ?>
					</a>
				</p>
			<?php else : ?>
				<p><?php esc_html_e( 'Please ask a site administrator to activate owbn-core.', 'owbn-board' ); ?></p>
			<?php endif; ?>
		<?php elseif ( empty( $user_roles ) ) : ?>
			<p><?php esc_html_e( "You don't have any roles assigned yet. Contact your chronicle's CM or file a support ticket.", 'owbn-board' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( "No tiles are configured for this site yet. Check back soon, or ask an admin to enable tiles in the layout settings.", 'owbn-board' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
