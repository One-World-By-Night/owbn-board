<?php
/**
 * Handoff tile — shows recent entries from the user's primary handoff scope.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_handoff_register_tile() {
	owbn_board_register_tile( [
		'id'          => 'board:handoff',
		'title'       => __( 'Handoff', 'owbn-board' ),
		'icon'        => 'dashicons-clipboard',
		'read_roles'  => [
			'chronicle/*/staff', 'chronicle/*/cm', 'chronicle/*/hst',
			'coordinator/*/*', 'exec/*',
		],
		'write_roles' => [
			'chronicle/*/staff', 'chronicle/*/cm', 'chronicle/*/hst',
			'coordinator/*/*', 'exec/*',
		],
		'size'        => '2x2',
		'category'    => 'communication',
		'priority'    => 35,
		'render'      => 'owbn_board_render_handoff_tile',
	] );
}

function owbn_board_render_handoff_tile( $tile, $user_id, $can_write ) {
	$scopes = owbn_board_handoff_user_scopes( $user_id );
	if ( empty( $scopes ) ) {
		echo '<p class="owbn-board-handoff__empty">' . esc_html__( 'No handoff scopes found for your roles.', 'owbn-board' ) . '</p>';
		return;
	}

	// Primary scope = first one in the list. Users with multiple scopes can switch in the admin page.
	$scope   = $scopes[0];
	$handoff = owbn_board_handoff_get_or_create( $scope );
	if ( ! $handoff ) {
		echo '<p class="owbn-board-handoff__empty">' . esc_html__( 'Could not load handoff.', 'owbn-board' ) . '</p>';
		return;
	}

	$entries = owbn_board_handoff_get_recent_entries( $handoff->id, 5 );
	?>
	<div class="owbn-board-handoff">
		<div class="owbn-board-handoff__scope">
			<strong><?php echo esc_html( $handoff->title ); ?></strong>
			<?php if ( count( $scopes ) > 1 ) : ?>
				<span class="owbn-board-handoff__scope-count">
					<?php printf( esc_html__( '(+%d other scopes)', 'owbn-board' ), count( $scopes ) - 1 ); ?>
				</span>
			<?php endif; ?>
		</div>

		<?php if ( empty( $entries ) ) : ?>
			<p class="owbn-board-handoff__empty"><?php esc_html_e( 'No entries yet. Start writing for whoever comes next.', 'owbn-board' ); ?></p>
		<?php else : ?>
			<ul class="owbn-board-handoff__entries">
				<?php foreach ( $entries as $entry ) : ?>
					<li class="owbn-board-handoff__entry">
						<div class="owbn-board-handoff__entry-meta">
							<span class="owbn-board-handoff__entry-section"><?php echo esc_html( $entry->section_label ); ?></span>
							<span class="owbn-board-handoff__entry-date">
								<?php
								$time = strtotime( $entry->created_at );
								echo esc_html( human_time_diff( $time, time() ) . ' ' . __( 'ago', 'owbn-board' ) );
								?>
							</span>
						</div>
						<?php if ( ! empty( $entry->title ) ) : ?>
							<div class="owbn-board-handoff__entry-title"><?php echo esc_html( $entry->title ); ?></div>
						<?php endif; ?>
						<div class="owbn-board-handoff__entry-body">
							<?php echo wp_kses_post( wp_trim_words( wp_strip_all_tags( $entry->body ), 25 ) ); ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $can_write ) : ?>
			<p class="owbn-board-handoff__manage">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=owbn-board-handoff&scope=' . rawurlencode( $scope ) ) ); ?>">
					<?php esc_html_e( 'Open handoff →', 'owbn-board' ); ?>
				</a>
			</p>
		<?php endif;
	?>
	</div>
	<?php
}
