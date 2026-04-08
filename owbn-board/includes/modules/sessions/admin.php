<?php
/**
 * Sessions module — admin page for chronicle staff.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_sessions_register_admin() {
	// Only show the menu if the user has a chronicle staff role
	if ( ! owbn_board_sessions_current_user_has_access() ) {
		return;
	}
	add_submenu_page(
		'owbn-board',
		__( 'Sessions', 'owbn-board' ),
		__( 'Sessions', 'owbn-board' ),
		'read',
		'owbn-board-sessions',
		'owbn_board_sessions_render_admin_page'
	);
}

function owbn_board_sessions_current_user_has_access() {
	return ! empty( owbn_board_sessions_user_chronicle_slugs( get_current_user_id() ) );
}

function owbn_board_sessions_render_admin_page() {
	if ( ! owbn_board_sessions_current_user_has_access() ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'owbn-board' ) );
	}

	owbn_board_sessions_handle_post();

	$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';
	$id     = isset( $_GET['session'] ) ? absint( $_GET['session'] ) : 0;

	if ( 'edit' === $action || 'new' === $action ) {
		$session = $id ? owbn_board_sessions_get( $id ) : null;
		owbn_board_sessions_render_form( $session );
	} else {
		owbn_board_sessions_render_list();
	}
}

function owbn_board_sessions_handle_post() {
	if ( empty( $_POST['owbn_board_sessions_action'] ) ) {
		return;
	}
	$action  = sanitize_key( wp_unslash( $_POST['owbn_board_sessions_action'] ) );
	$user_id = get_current_user_id();

	if ( 'save' === $action ) {
		check_admin_referer( 'owbn_board_sessions_save' );
		$id     = isset( $_POST['session_id'] ) ? absint( $_POST['session_id'] ) : 0;
		$slug   = isset( $_POST['chronicle_slug'] ) ? sanitize_key( wp_unslash( $_POST['chronicle_slug'] ) ) : '';
		$scopes = owbn_board_sessions_user_chronicle_slugs( $user_id );

		if ( ! in_array( $slug, $scopes, true ) ) {
			wp_die( esc_html__( 'Forbidden: you do not have access to that chronicle.', 'owbn-board' ) );
		}

		$data = [
			'chronicle_slug'     => $slug,
			'session_date'       => isset( $_POST['session_date'] ) ? wp_unslash( (string) $_POST['session_date'] ) : '',
			'title'              => isset( $_POST['title'] ) ? wp_unslash( (string) $_POST['title'] ) : '',
			'summary'            => isset( $_POST['summary'] ) ? wp_unslash( (string) $_POST['summary'] ) : '',
			'notes'              => isset( $_POST['notes'] ) ? wp_unslash( (string) $_POST['notes'] ) : '',
			'attendance'         => isset( $_POST['attendance'] ) ? wp_unslash( (string) $_POST['attendance'] ) : '',
			'share_with_players' => ! empty( $_POST['share_with_players'] ),
		];

		if ( $id ) {
			$existing = owbn_board_sessions_get( $id );
			if ( ! $existing || ! in_array( $existing->chronicle_slug, $scopes, true ) ) {
				wp_die( esc_html__( 'Forbidden.', 'owbn-board' ) );
			}
			$ok = owbn_board_sessions_update( $id, $data );
			owbn_board_audit( $user_id, 'sessions.update', 'session', $id, [ 'ok' => $ok ] );
			$msg = $ok ? 'updated' : 'error';
		} else {
			$new_id = owbn_board_sessions_create( $data );
			owbn_board_audit( $user_id, 'sessions.create', 'session', (int) $new_id );
			$msg = $new_id ? 'created' : 'error';
		}

		wp_safe_redirect( add_query_arg( [ 'page' => 'owbn-board-sessions', 'msg' => $msg ], admin_url( 'admin.php' ) ) );
		exit;
	}

	if ( 'delete' === $action ) {
		check_admin_referer( 'owbn_board_sessions_delete' );
		$id = isset( $_POST['session_id'] ) ? absint( $_POST['session_id'] ) : 0;
		if ( $id ) {
			$existing = owbn_board_sessions_get( $id );
			$scopes   = owbn_board_sessions_user_chronicle_slugs( $user_id );
			if ( $existing && in_array( $existing->chronicle_slug, $scopes, true ) ) {
				$ok = owbn_board_sessions_delete( $id );
				owbn_board_audit( $user_id, 'sessions.delete', 'session', $id, [ 'ok' => $ok ] );
				wp_safe_redirect( add_query_arg( [ 'page' => 'owbn-board-sessions', 'msg' => 'deleted' ], admin_url( 'admin.php' ) ) );
				exit;
			}
			wp_die( esc_html__( 'Forbidden.', 'owbn-board' ) );
		}
	}
}

function owbn_board_sessions_render_list() {
	$user_id = get_current_user_id();
	$scopes  = owbn_board_sessions_user_chronicle_slugs( $user_id );
	$primary = $scopes[0];
	$sessions = owbn_board_sessions_get_by_chronicle( $primary, 100 );
	$msg      = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Sessions', 'owbn-board' ); ?></h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=owbn-board-sessions&action=new&chronicle=' . rawurlencode( $primary ) ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'New Session', 'owbn-board' ); ?>
		</a>

		<?php if ( $msg ) : ?>
			<div class="notice notice-<?php echo 'error' === $msg ? 'error' : 'success'; ?> is-dismissible">
				<p><?php echo esc_html( ucfirst( $msg ) ); ?>.</p>
			</div>
		<?php endif; ?>

		<p class="description">
			<?php printf(
				/* translators: %s: chronicle slug */
				esc_html__( 'Sessions for %s', 'owbn-board' ),
				'<code>' . esc_html( $primary ) . '</code>'
			); ?>
		</p>

		<?php if ( empty( $sessions ) ) : ?>
			<p><?php esc_html_e( 'No sessions logged yet.', 'owbn-board' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'owbn-board' ); ?></th>
						<th><?php esc_html_e( 'Title', 'owbn-board' ); ?></th>
						<th><?php esc_html_e( 'Shared', 'owbn-board' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'owbn-board' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $sessions as $s ) : ?>
						<tr>
							<td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $s->session_date ) ) ); ?></td>
							<td><strong><?php echo esc_html( $s->title ?: __( '(untitled)', 'owbn-board' ) ); ?></strong></td>
							<td><?php echo $s->share_with_players ? '✓' : '—'; ?></td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=owbn-board-sessions&action=edit&session=' . (int) $s->id ) ); ?>"><?php esc_html_e( 'Edit', 'owbn-board' ); ?></a>
								|
								<form method="post" style="display:inline" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this session?', 'owbn-board' ) ); ?>');">
									<?php wp_nonce_field( 'owbn_board_sessions_delete' ); ?>
									<input type="hidden" name="owbn_board_sessions_action" value="delete" />
									<input type="hidden" name="session_id" value="<?php echo (int) $s->id; ?>" />
									<button type="submit" class="button-link button-link-delete"><?php esc_html_e( 'Delete', 'owbn-board' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

function owbn_board_sessions_render_form( $session = null ) {
	$is_edit = ! empty( $session );
	$user_id = get_current_user_id();
	$scopes  = owbn_board_sessions_user_chronicle_slugs( $user_id );

	$slug       = $is_edit ? $session->chronicle_slug : ( isset( $_GET['chronicle'] ) ? sanitize_key( wp_unslash( $_GET['chronicle'] ) ) : $scopes[0] );
	$date       = $is_edit ? $session->session_date : current_time( 'Y-m-d' );
	$title      = $is_edit ? $session->title : '';
	$summary    = $is_edit ? $session->summary : '';
	$notes      = $is_edit ? $session->notes : '';
	$attendance = $is_edit ? $session->attendance : '';
	$shared     = $is_edit ? (int) $session->share_with_players : 0;

	if ( $is_edit && ! in_array( $session->chronicle_slug, $scopes, true ) ) {
		wp_die( esc_html__( 'Forbidden.', 'owbn-board' ) );
	}
	?>
	<div class="wrap">
		<h1><?php echo $is_edit ? esc_html__( 'Edit Session', 'owbn-board' ) : esc_html__( 'New Session', 'owbn-board' ); ?></h1>

		<form method="post">
			<?php wp_nonce_field( 'owbn_board_sessions_save' ); ?>
			<input type="hidden" name="owbn_board_sessions_action" value="save" />
			<input type="hidden" name="chronicle_slug" value="<?php echo esc_attr( $slug ); ?>" />
			<?php if ( $is_edit ) : ?>
				<input type="hidden" name="session_id" value="<?php echo (int) $session->id; ?>" />
			<?php endif; ?>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Chronicle', 'owbn-board' ); ?></th>
					<td><code><?php echo esc_html( $slug ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><label for="session_date"><?php esc_html_e( 'Session Date', 'owbn-board' ); ?></label></th>
					<td><input type="date" name="session_date" id="session_date" value="<?php echo esc_attr( $date ); ?>" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="title"><?php esc_html_e( 'Title', 'owbn-board' ); ?></label></th>
					<td><input type="text" name="title" id="title" class="regular-text" value="<?php echo esc_attr( $title ); ?>" maxlength="255" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="summary"><?php esc_html_e( 'Summary', 'owbn-board' ); ?></label></th>
					<td>
						<?php
						wp_editor(
							$summary,
							'summary',
							[
								'textarea_name' => 'summary',
								'textarea_rows' => 8,
								'media_buttons' => true,
								'teeny'         => false,
							]
						);
						?>
						<p class="description"><?php esc_html_e( 'Visible to players if "Share with players" is enabled.', 'owbn-board' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="notes"><?php esc_html_e( 'Staff Notes', 'owbn-board' ); ?></label></th>
					<td>
						<?php
						wp_editor(
							$notes,
							'notes',
							[
								'textarea_name' => 'notes',
								'textarea_rows' => 6,
								'media_buttons' => false,
								'teeny'         => true,
							]
						);
						?>
						<p class="description"><?php esc_html_e( 'Staff-only — never shown to players.', 'owbn-board' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="attendance"><?php esc_html_e( 'Attendance', 'owbn-board' ); ?></label></th>
					<td><textarea name="attendance" id="attendance" rows="3" class="large-text"><?php echo esc_textarea( $attendance ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Share with Players', 'owbn-board' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="share_with_players" value="1" <?php checked( $shared, 1 ); ?> />
							<?php esc_html_e( 'Make summary visible to players', 'owbn-board' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php echo $is_edit ? esc_html__( 'Update Session', 'owbn-board' ) : esc_html__( 'Add Session', 'owbn-board' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=owbn-board-sessions' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'owbn-board' ); ?></a>
			</p>
		</form>
	</div>
	<?php
}
