<?php
/**
 * Handoff tile — shows recent entries from the user's handoff scopes.
 * Multi-scope users get a switcher to flip between handoffs.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_handoff_register_tile() {
	owbn_board_register_tile( [
		'id'                   => 'board:handoff',
		'title'                => __( 'Handoff', 'owbn-board' ),
		'icon'                 => 'dashicons-clipboard',
		'read_roles'           => [
			'chronicle/*/staff', 'chronicle/*/cm', 'chronicle/*/hst',
			'coordinator/*/coordinator', 'coordinator/*/sub-coordinator', 'exec/*/coordinator',
		],
		'write_roles'          => [
			'chronicle/*/staff', 'chronicle/*/cm', 'chronicle/*/hst',
			'coordinator/*/coordinator', 'coordinator/*/sub-coordinator', 'exec/*/coordinator',
		],
		'size'                 => '2x2',
		'category'             => 'communication',
		'priority'             => 35,
		'supports_share_level' => true,
		'poll_interval'        => 15000,
		'render'               => 'owbn_board_render_handoff_tile',
	] );
}

function owbn_board_render_handoff_tile( $tile, $user_id, $can_write ) {
	$scopes = owbn_board_handoff_user_scopes( $user_id );
	if ( empty( $scopes ) ) {
		echo '<p class="owbn-board-handoff__empty">' . esc_html__( 'No handoff scopes found for your roles.', 'owbn-board' ) . '</p>';
		return;
	}

	$active = $scopes[0];
	?>
	<div class="owbn-board-handoff" data-active-scope="<?php echo esc_attr( $active ); ?>">
		<?php owbn_board_render_scope_switcher( $scopes, $active, __( 'Switch handoff scope', 'owbn-board' ) ); ?>

		<?php foreach ( $scopes as $scope ) :
			$is_active = ( $scope === $active );
			?>
			<div class="owbn-board-handoff__panel<?php echo $is_active ? ' is-active' : ''; ?>" data-scope="<?php echo esc_attr( $scope ); ?>">
				<?php owbn_board_render_handoff_panel( $scope, $can_write ); ?>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}

function owbn_board_render_handoff_panel( $scope, $can_write ) {
	$handoff = owbn_board_handoff_get_or_create( $scope );
	if ( ! $handoff ) {
		echo '<p class="owbn-board-handoff__empty">' . esc_html__( 'Could not load handoff.', 'owbn-board' ) . '</p>';
		return;
	}
	$entries = owbn_board_handoff_get_recent_entries( $handoff->id, 5 );
	?>
	<div class="owbn-board-handoff__scope">
		<strong><?php echo esc_html( $handoff->title ); ?></strong>
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
}
