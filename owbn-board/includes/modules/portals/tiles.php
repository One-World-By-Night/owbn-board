<?php
/**
 * Portals module — three quick-access tiles for archivist, territory, and exec votes.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register all three portal tiles.
 */
function owbn_board_portals_register_tiles() {
	// Archivist portal
	owbn_board_register_tile( [
		'id'         => 'portals:archivist',
		'title'      => __( 'Archivist', 'owbn-board' ),
		'icon'       => 'dashicons-archive',
		'read_roles' => [
			'exec/archivist/*',
			'exec/web/*',
			'exec/admin/*',
		],
		'size'       => '2x2',
		'category'   => 'admin',
		'priority'   => 40,
		'render'     => 'owbn_board_render_archivist_portal',
	] );

	// Territory Manager portal
	owbn_board_register_tile( [
		'id'         => 'portals:territory',
		'title'      => __( 'Territory Manager', 'owbn-board' ),
		'icon'       => 'dashicons-admin-site-alt3',
		'read_roles' => [
			'exec/membership/*',
			'exec/web/*',
			'exec/admin/*',
			'coordinator/*/*',
		],
		'size'       => '2x2',
		'category'   => 'admin',
		'priority'   => 42,
		'render'     => 'owbn_board_render_territory_portal',
	] );

	// Exec vote quick actions portal
	owbn_board_register_tile( [
		'id'         => 'portals:exec-votes',
		'title'      => __( 'Exec Vote Actions', 'owbn-board' ),
		'icon'       => 'dashicons-yes-alt',
		'read_roles' => [
			'exec/hc/coordinator',
			'exec/ahc1/coordinator',
			'exec/ahc2/coordinator',
		],
		'size'       => '2x2',
		'category'   => 'admin',
		'priority'   => 44,
		'render'     => 'owbn_board_render_exec_votes_portal',
	] );
}

/**
 * Archivist portal renderer — OAT counts + links.
 *
 * If OAT isn't installed locally, the tile redirects to archivist.owbn.net
 * where OAT actually lives.
 */
function owbn_board_render_archivist_portal( $tile, $user_id, $can_write ) {
	$counts = owbn_board_portals_oat_counts();
	$recent = owbn_board_portals_oat_recent( 5 );
	$is_local = ( null !== $counts );
	?>
	<div class="owbn-board-portal owbn-board-portal--archivist">
		<?php if ( ! $is_local ) : ?>
			<p class="owbn-board-portal__remote">
				<?php esc_html_e( 'OAT lives on archivist.owbn.net. Use the links below to jump there.', 'owbn-board' ); ?>
			</p>
			<div class="owbn-board-portal__actions">
				<a class="button button-primary" href="<?php echo esc_url( owbn_board_tool_url( 'oat', '/wp-admin/admin.php?page=oat' ) ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'OAT Dashboard', 'owbn-board' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( owbn_board_tool_url( 'oat', '/wp-admin/admin.php?page=oat-characters' ) ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Character Registry', 'owbn-board' ); ?>
				</a>
			</div>
		<?php else : ?>
			<div class="owbn-board-portal__counts">
				<div class="owbn-board-portal__count owbn-board-portal__count--pending">
					<span class="owbn-board-portal__count-value"><?php echo (int) $counts['pending']; ?></span>
					<span class="owbn-board-portal__count-label"><?php esc_html_e( 'Pending', 'owbn-board' ); ?></span>
				</div>
				<div class="owbn-board-portal__count">
					<span class="owbn-board-portal__count-value"><?php echo (int) $counts['approved']; ?></span>
					<span class="owbn-board-portal__count-label"><?php esc_html_e( 'Approved', 'owbn-board' ); ?></span>
				</div>
				<div class="owbn-board-portal__count">
					<span class="owbn-board-portal__count-value"><?php echo (int) $counts['denied']; ?></span>
					<span class="owbn-board-portal__count-label"><?php esc_html_e( 'Denied', 'owbn-board' ); ?></span>
				</div>
			</div>

			<?php if ( ! empty( $recent ) ) : ?>
				<h4 class="owbn-board-portal__section-title"><?php esc_html_e( 'Recent Entries', 'owbn-board' ); ?></h4>
				<ul class="owbn-board-portal__list">
					<?php foreach ( $recent as $entry ) :
						$edit_url = admin_url( 'admin.php?page=oat&entry_id=' . (int) $entry->id );
						$ago      = human_time_diff( (int) $entry->updated_at, time() ) . ' ' . __( 'ago', 'owbn-board' );
						?>
						<li class="owbn-board-portal__item">
							<a href="<?php echo esc_url( $edit_url ); ?>">
								<span class="owbn-board-portal__item-title"><?php echo esc_html( $entry->domain ); ?></span>
								<span class="owbn-board-portal__item-meta">
									<?php echo esc_html( $entry->chronicle_slug ?: '—' ); ?>
									·
									<?php echo esc_html( $entry->status ); ?>
									·
									<?php echo esc_html( $ago ); ?>
								</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<div class="owbn-board-portal__actions">
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=oat' ) ); ?>">
					<?php esc_html_e( 'OAT Dashboard', 'owbn-board' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=oat-characters' ) ); ?>">
					<?php esc_html_e( 'Character Registry', 'owbn-board' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Territory Manager portal renderer — 5 most recent + add/upload links.
 *
 * If territory-manager isn't installed locally, the tile redirects to
 * chronicles.owbn.net where it lives.
 */
function owbn_board_render_territory_portal( $tile, $user_id, $can_write ) {
	$counts = owbn_board_portals_tm_counts();
	$recent = owbn_board_portals_tm_recent( 5 );
	$is_local = ( null !== $counts );
	?>
	<div class="owbn-board-portal owbn-board-portal--territory">
		<?php if ( ! $is_local ) : ?>
			<p class="owbn-board-portal__remote">
				<?php esc_html_e( 'Territories are managed on chronicles.owbn.net. Use the links below to jump there.', 'owbn-board' ); ?>
			</p>
			<div class="owbn-board-portal__actions">
				<a class="button button-primary" href="<?php echo esc_url( owbn_board_tool_url( 'tm', '/wp-admin/post-new.php?post_type=owbn_territory' ) ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Add Territory', 'owbn-board' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( owbn_board_tool_url( 'tm', '/wp-admin/edit.php?post_type=owbn_territory&page=owbn-tm-import' ) ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Upload', 'owbn-board' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( owbn_board_tool_url( 'tm', '/wp-admin/edit.php?post_type=owbn_territory' ) ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Manage', 'owbn-board' ); ?>
				</a>
			</div>
		<?php else : ?>
			<div class="owbn-board-portal__counts">
				<div class="owbn-board-portal__count">
					<span class="owbn-board-portal__count-value"><?php echo (int) $counts['publish']; ?></span>
					<span class="owbn-board-portal__count-label"><?php esc_html_e( 'Territories', 'owbn-board' ); ?></span>
				</div>
			</div>

			<?php if ( ! empty( $recent ) ) : ?>
				<h4 class="owbn-board-portal__section-title"><?php esc_html_e( 'Recently Updated', 'owbn-board' ); ?></h4>
				<ul class="owbn-board-portal__list">
					<?php foreach ( $recent as $territory ) :
						$edit_url = get_edit_post_link( $territory->ID );
						$modified = strtotime( $territory->post_modified_gmt );
						$ago      = human_time_diff( $modified, time() ) . ' ' . __( 'ago', 'owbn-board' );
						?>
						<li class="owbn-board-portal__item">
							<a href="<?php echo esc_url( $edit_url ); ?>">
								<span class="owbn-board-portal__item-title"><?php echo esc_html( get_the_title( $territory ) ); ?></span>
								<span class="owbn-board-portal__item-meta"><?php echo esc_html( $ago ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<div class="owbn-board-portal__actions">
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=owbn_territory' ) ); ?>">
					<?php esc_html_e( 'Add Territory', 'owbn-board' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=owbn_territory&page=owbn-tm-import' ) ); ?>">
					<?php esc_html_e( 'Upload', 'owbn-board' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=owbn_territory' ) ); ?>">
					<?php esc_html_e( 'Manage', 'owbn-board' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Exec Vote Quick Actions portal renderer — wp-voting-plugin counts and shortcuts.
 *
 * If wp-voting-plugin isn't installed locally, the tile redirects to
 * council.owbn.net where it lives.
 */
function owbn_board_render_exec_votes_portal( $tile, $user_id, $can_write ) {
	$counts = owbn_board_portals_wpvp_counts();
	$open   = owbn_board_portals_wpvp_recent_open( 5 );
	$is_local = ( null !== $counts );
	?>
	<div class="owbn-board-portal owbn-board-portal--exec-votes">
		<?php if ( ! $is_local ) : ?>
			<p class="owbn-board-portal__remote">
				<?php esc_html_e( 'Votes are managed on council.owbn.net. Use the links below to jump there.', 'owbn-board' ); ?>
			</p>
			<div class="owbn-board-portal__actions">
				<a class="button button-primary" href="<?php echo esc_url( owbn_board_tool_url( 'wpvp', '/wp-admin/admin.php?page=wpvp-new' ) ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Create Vote', 'owbn-board' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( owbn_board_tool_url( 'election_bridge', '/wp-admin/admin.php?page=owbn-election-bridge' ) ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Build Election', 'owbn-board' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( owbn_board_tool_url( 'wpvp', '/wp-admin/admin.php?page=wpvp' ) ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Manage Votes', 'owbn-board' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( owbn_board_tool_url( 'wpvp', '/wp-admin/admin.php?page=wpvp-results' ) ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Results', 'owbn-board' ); ?>
				</a>
			</div>
		<?php else : ?>
			<div class="owbn-board-portal__counts">
				<div class="owbn-board-portal__count">
					<span class="owbn-board-portal__count-value"><?php echo (int) $counts['draft']; ?></span>
					<span class="owbn-board-portal__count-label"><?php esc_html_e( 'Drafts', 'owbn-board' ); ?></span>
				</div>
				<div class="owbn-board-portal__count owbn-board-portal__count--pending">
					<span class="owbn-board-portal__count-value"><?php echo (int) $counts['open']; ?></span>
					<span class="owbn-board-portal__count-label"><?php esc_html_e( 'Open', 'owbn-board' ); ?></span>
				</div>
				<div class="owbn-board-portal__count">
					<span class="owbn-board-portal__count-value"><?php echo (int) $counts['closed']; ?></span>
					<span class="owbn-board-portal__count-label"><?php esc_html_e( 'Closed', 'owbn-board' ); ?></span>
				</div>
			</div>

			<?php if ( ! empty( $open ) ) : ?>
				<h4 class="owbn-board-portal__section-title"><?php esc_html_e( 'Open Votes', 'owbn-board' ); ?></h4>
				<ul class="owbn-board-portal__list">
					<?php foreach ( $open as $vote ) :
						$edit_url = admin_url( 'admin.php?page=wpvp-edit&vote_id=' . (int) $vote->id );
						$closes   = $vote->closing_date ? wp_date( get_option( 'date_format' ), strtotime( $vote->closing_date ) ) : '—';
						?>
						<li class="owbn-board-portal__item">
							<a href="<?php echo esc_url( $edit_url ); ?>">
								<span class="owbn-board-portal__item-title"><?php echo esc_html( $vote->proposal_name ); ?></span>
								<span class="owbn-board-portal__item-meta">
									<?php printf( esc_html__( 'Closes %s', 'owbn-board' ), esc_html( $closes ) ); ?>
								</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<div class="owbn-board-portal__actions">
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wpvp-new' ) ); ?>">
					<?php esc_html_e( 'Create Vote', 'owbn-board' ); ?>
				</a>
				<?php if ( defined( 'OEB_VERSION' ) || function_exists( 'oeb_check_dependencies' ) ) : ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=owbn-election-bridge' ) ); ?>">
						<?php esc_html_e( 'Build Election', 'owbn-board' ); ?>
					</a>
				<?php endif; ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wpvp' ) ); ?>">
					<?php esc_html_e( 'Manage Votes', 'owbn-board' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wpvp-results' ) ); ?>">
					<?php esc_html_e( 'Results', 'owbn-board' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
