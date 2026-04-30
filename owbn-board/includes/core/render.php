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

	// Bucket tiles by tab. Unknown tab values fall back to 'data'.
	$tabs_tiles = array_fill_keys( owbn_board_allowed_tabs(), array() );
	foreach ( $tiles as $tile ) {
		$tab = isset( $tile['tab'] ) && in_array( $tile['tab'], owbn_board_allowed_tabs(), true )
			? $tile['tab']
			: 'data';
		$tabs_tiles[ $tab ][] = $tile;
	}

	// C&C tab visibility: shown if the user has any chronicle/coord/exec role
	// (the nine concrete patterns) OR has any role-gated tab=cc tile passing
	// the read_roles filter. Either source of eligibility surfaces the tab.
	$cc_eligible = ! empty( $tabs_tiles['cc'] );
	if ( ! $cc_eligible && function_exists( 'owc_workspace_user_is_cc_eligible' ) ) {
		$cc_eligible = owc_workspace_user_is_cc_eligible( $user_id );
	}

	$tab_meta = array(
		'links'    => array( 'label' => __( 'Links', 'owbn-board' ),    'visible' => true ),
		'schedule' => array( 'label' => __( 'Schedule', 'owbn-board' ), 'visible' => true ),
		'data'     => array( 'label' => __( 'Data', 'owbn-board' ),     'visible' => true ),
		'cc'       => array( 'label' => __( 'C&C', 'owbn-board' ),      'visible' => $cc_eligible ),
	);

	do_action( 'owbn_board_before_render', $user_id );

	ob_start();
	?>
	<div class="owbn-board owbn-board-mode-<?php echo esc_attr( $layout['layout_mode'] ); ?>" data-user-id="<?php echo esc_attr( $user_id ); ?>">
		<?php if ( ! empty( $layout['header_html'] ) ) : ?>
			<div class="owbn-board-header"><?php echo wp_kses_post( $layout['header_html'] ); ?></div>
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
 * Step 1 behavior:
 *   - links/schedule: placeholder text (will be filled in steps 5/2 respectively)
 *   - data: standard tile grid (or empty-state if no tiles)
 *   - cc: standard tile grid (sections come in step 6)
 */
function owbn_board_render_tab_panel( $tab_key, array $panel_tiles, $user_id ) {
	ob_start();

	// Links tab: workspace helper renders Section A (admin-curated org
	// resources) + Section B (My Stuff). No tiles on this tab.
	if ( 'links' === $tab_key ) {
		if ( function_exists( 'owc_render_workspace_sections' ) ) {
			echo owc_render_workspace_sections( $user_id, array( 'admin', 'my_stuff' ) );
		} else {
			echo '<div class="owbn-board-tab-placeholder">'
				. esc_html__( 'Links tab requires owbn-core 1.10+ for the workspace helper.', 'owbn-board' )
				. '</div>';
		}
		return ob_get_clean();
	}

	// C&C tab: three role-based sections (chronicles, coord, exec) at the
	// top, then any role-gated tab=cc tiles below in the standard grid.
	if ( 'cc' === $tab_key ) {
		if ( function_exists( 'owc_render_workspace_sections' ) ) {
			echo owc_render_workspace_sections( $user_id, array( 'chronicles', 'coord', 'exec' ) );
		}
		if ( ! empty( $panel_tiles ) ) {
			echo '<div class="owbn-board-grid">';
			foreach ( $panel_tiles as $tile ) {
				echo owbn_board_render_tile( $tile, $user_id );
			}
			echo '</div>';
		}
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
