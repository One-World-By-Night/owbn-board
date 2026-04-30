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
		'poll_interval' => 15000,
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
			'exec/head-coordinator/coordinator',
			'exec/ahc1/coordinator',
			'exec/ahc2/coordinator',
		],
		'size'       => '2x2',
		'category'   => 'admin',
		'priority'   => 42,
		'poll_interval' => 15000,
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
		'poll_interval' => 15000,
		'render'     => 'owbn_board_render_exec_votes_portal',
	] );
}

function owbn_board_render_archivist_portal( $tile, $user_id, $can_write ) {
	$counts = owbn_board_portals_oat_counts( $user_id );
	$recent = owbn_board_portals_oat_recent( 5, $user_id );
	?>
	<div class="owbn-board-portal owbn-board-portal--archivist">
		<?php if ( null === $counts ) : ?>
			<p class="owbn-board-portal__remote">
				<?php esc_html_e( 'OAT data unavailable. Configure owbn-archivist or check the remote OAT URL.', 'owbn-board' ); ?>
			</p>
		<?php else : ?>
			<div class="owbn-board-portal__counts">
				<div class="owbn-board-portal__count">
					<span class="owbn-board-portal__count-value"><?php echo (int) ( $counts['assigned'] ?? 0 ); ?></span>
					<span class="owbn-board-portal__count-label"><?php esc_html_e( 'Assigned to me', 'owbn-board' ); ?></span>
				</div>
				<div class="owbn-board-portal__count">
					<span class="owbn-board-portal__count-value"><?php echo (int) ( $counts['submissions'] ?? 0 ); ?></span>
					<span class="owbn-board-portal__count-label"><?php esc_html_e( 'My submissions', 'owbn-board' ); ?></span>
				</div>
				<div class="owbn-board-portal__count">
					<span class="owbn-board-portal__count-value"><?php echo (int) ( $counts['watching'] ?? 0 ); ?></span>
					<span class="owbn-board-portal__count-label"><?php esc_html_e( 'Watching', 'owbn-board' ); ?></span>
				</div>
			</div>

			<?php if ( ! empty( $recent ) ) : ?>
				<h4 class="owbn-board-portal__section-title"><?php esc_html_e( 'Recent Activity', 'owbn-board' ); ?></h4>
				<ul class="owbn-board-portal__list">
					<?php foreach ( $recent as $entry ) :
						$entry_id = (int) ( $entry['entry_id'] ?? 0 );
						$domain   = (string) ( $entry['domain_label'] ?? $entry['domain'] ?? '' );
						$action   = (string) ( $entry['action_type'] ?? '' );
						$actor    = (string) ( $entry['actor_name'] ?? '' );
						$created  = (string) ( $entry['created_at'] ?? '' );
						$time_ts  = $created ? strtotime( $created ) : 0;
						$ago      = $time_ts ? human_time_diff( $time_ts, time() ) . ' ' . __( 'ago', 'owbn-board' ) : '';
						$url      = owbn_board_tool_url( 'oat', '/wp-admin/admin.php?page=oat&entry_id=' . $entry_id );
						?>
						<li class="owbn-board-portal__item">
							<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">
								<span class="owbn-board-portal__item-title"><?php echo esc_html( $domain ); ?></span>
								<span class="owbn-board-portal__item-meta">
									<?php echo esc_html( $action ?: '—' ); ?>
									<?php if ( $actor ) : ?>· <?php echo esc_html( $actor ); ?><?php endif; ?>
									<?php if ( $ago ) : ?>· <?php echo esc_html( $ago ); ?><?php endif; ?>
								</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		<?php endif; ?>

		<div class="owbn-board-portal__actions">
			<a class="button button-primary" href="<?php echo esc_url( owbn_board_tool_url( 'oat', '/wp-admin/admin.php?page=oat' ) ); ?>" target="_blank" rel="noopener">
				<?php esc_html_e( 'OAT Dashboard', 'owbn-board' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( owbn_board_tool_url( 'oat', '/wp-admin/admin.php?page=oat-characters' ) ); ?>" target="_blank" rel="noopener">
				<?php esc_html_e( 'Character Registry', 'owbn-board' ); ?>
			</a>
		</div>
	</div>
	<?php
}

function owbn_board_render_territory_portal( $tile, $user_id, $can_write ) {
	$counts = owbn_board_portals_tm_counts();
	$recent = owbn_board_portals_tm_recent( 5 );
	?>
	<div class="owbn-board-portal owbn-board-portal--territory">
		<?php if ( null === $counts ) : ?>
			<p class="owbn-board-portal__remote">
				<?php esc_html_e( 'Territory data unavailable. Configure the territories remote URL in owbn-core.', 'owbn-board' ); ?>
			</p>
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
						$id    = (int) ( $territory['id'] ?? 0 );
						$title = (string) ( $territory['title'] ?? '' );
						$dt    = (string) ( $territory['update_date'] ?? '' );
						$ts    = $dt ? strtotime( $dt ) : 0;
						$ago   = $ts ? human_time_diff( $ts, time() ) . ' ' . __( 'ago', 'owbn-board' ) : '';
						$url   = owbn_board_tool_url( 'tm', '/wp-admin/post.php?action=edit&post=' . $id );
						?>
						<li class="owbn-board-portal__item">
							<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">
								<span class="owbn-board-portal__item-title"><?php echo esc_html( $title ); ?></span>
								<?php if ( $ago ) : ?><span class="owbn-board-portal__item-meta"><?php echo esc_html( $ago ); ?></span><?php endif; ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		<?php endif; ?>

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
	?>
	<div class="owbn-board-portal owbn-board-portal--exec-votes">
		<?php if ( null === $counts ) : ?>
			<p class="owbn-board-portal__remote">
				<?php esc_html_e( 'Vote data unavailable. Configure the votes remote URL in owbn-core.', 'owbn-board' ); ?>
			</p>
		<?php else : ?>
			<div class="owbn-board-portal__counts">
				<div class="owbn-board-portal__count">
					<span class="owbn-board-portal__count-value"><?php echo (int) ( $counts['draft'] ?? 0 ); ?></span>
					<span class="owbn-board-portal__count-label"><?php esc_html_e( 'Drafts', 'owbn-board' ); ?></span>
				</div>
				<div class="owbn-board-portal__count owbn-board-portal__count--pending">
					<span class="owbn-board-portal__count-value"><?php echo (int) ( $counts['open'] ?? 0 ); ?></span>
					<span class="owbn-board-portal__count-label"><?php esc_html_e( 'Open', 'owbn-board' ); ?></span>
				</div>
				<div class="owbn-board-portal__count">
					<span class="owbn-board-portal__count-value"><?php echo (int) ( $counts['closed'] ?? 0 ); ?></span>
					<span class="owbn-board-portal__count-label"><?php esc_html_e( 'Closed', 'owbn-board' ); ?></span>
				</div>
			</div>

			<?php if ( ! empty( $open ) ) : ?>
				<h4 class="owbn-board-portal__section-title"><?php esc_html_e( 'Open Votes', 'owbn-board' ); ?></h4>
				<ul class="owbn-board-portal__list">
					<?php foreach ( $open as $vote ) :
						$vid     = (int) ( $vote['id'] ?? 0 );
						$pname   = (string) ( $vote['proposal_name'] ?? '' );
						$closing = (string) ( $vote['closing_date'] ?? '' );
						$closes  = $closing ? wp_date( get_option( 'date_format' ), strtotime( $closing ) ) : '—';
						$url     = owbn_board_tool_url( 'wpvp', '/wp-admin/admin.php?page=wpvp-edit&vote_id=' . $vid );
						?>
						<li class="owbn-board-portal__item">
							<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">
								<span class="owbn-board-portal__item-title"><?php echo esc_html( $pname ); ?></span>
								<span class="owbn-board-portal__item-meta">
									<?php printf( esc_html__( 'Closes %s', 'owbn-board' ), esc_html( $closes ) ); ?>
								</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		<?php endif; ?>

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
	</div>
	<?php
}
