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
			$loaded    = $is_active ? '1' : '0';
			?>
			<div class="owbn-board-handoff__panel<?php echo $is_active ? ' is-active' : ''; ?>"
				data-scope="<?php echo esc_attr( $scope ); ?>"
				data-can-write="<?php echo $can_write ? '1' : '0'; ?>"
				data-loaded="<?php echo esc_attr( $loaded ); ?>">
				<?php if ( $is_active ) : ?>
					<?php owbn_board_render_handoff_panel( $scope, $can_write ); ?>
				<?php else : ?>
					<div class="owbn-board-handoff__loading"><?php esc_html_e( 'Loading…', 'owbn-board' ); ?></div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}

add_action( 'wp_ajax_owbn_board_handoff_scope', 'owbn_board_ajax_handoff_scope' );

function owbn_board_ajax_handoff_scope() {
	if ( ! check_ajax_referer( 'owbn_board', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
	}
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( array( 'message' => 'Not logged in' ), 401 );
	}
	$scope = isset( $_POST['scope'] ) ? sanitize_text_field( wp_unslash( $_POST['scope'] ) ) : '';
	if ( ! $scope ) {
		wp_send_json_error( array( 'message' => 'Missing scope' ), 400 );
	}
	$allowed = owbn_board_handoff_user_scopes( $user_id );
	if ( ! in_array( $scope, $allowed, true ) ) {
		wp_send_json_error( array( 'message' => 'Scope not in user roles' ), 403 );
	}
	$can_write = true; // user holds the scope role; same write_roles logic applies
	ob_start();
	owbn_board_render_handoff_panel( $scope, $can_write );
	$html = ob_get_clean();
	wp_send_json_success( array( 'html' => $html ) );
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
