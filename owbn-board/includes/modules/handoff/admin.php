<?php
/**
 * Handoff module — admin page.
 *
 * Lets users view/edit the handoff for each scope they have access to.
 * Entries are organized by section, with add/edit/delete actions.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_handoff_register_admin() {
	if ( ! owbn_board_handoff_current_user_has_access() ) {
		return;
	}
	add_submenu_page(
		'owbn-board',
		__( 'Handoff', 'owbn-board' ),
		__( 'Handoff', 'owbn-board' ),
		'read',
		'owbn-board-handoff',
		'owbn_board_handoff_render_admin_page'
	);
}

function owbn_board_handoff_current_user_has_access() {
	return ! empty( owbn_board_handoff_user_scopes( get_current_user_id() ) );
}

function owbn_board_handoff_render_admin_page() {
	$user_id = get_current_user_id();
	$scopes  = owbn_board_handoff_user_scopes( $user_id );

	if ( empty( $scopes ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'owbn-board' ) );
	}

	owbn_board_handoff_handle_post();

	// Resolve which scope is currently selected
	$scope = isset( $_GET['scope'] ) ? sanitize_text_field( wp_unslash( $_GET['scope'] ) ) : $scopes[0];
	if ( ! in_array( $scope, $scopes, true ) ) {
		$scope = $scopes[0];
	}

	$handoff  = owbn_board_handoff_get_or_create( $scope );
	$sections = owbn_board_handoff_get_sections( $handoff->id );
	$msg      = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';
	?>
	<div class="wrap owbn-board-handoff-admin">
		<h1 class="wp-heading-inline"><?php echo esc_html( $handoff->title ); ?></h1>

		<?php if ( count( $scopes ) > 1 ) : ?>
			<div class="owbn-board-handoff-admin__scope-picker">
				<label for="handoff-scope-picker"><?php esc_html_e( 'Scope:', 'owbn-board' ); ?></label>
				<select id="handoff-scope-picker" onchange="window.location.href=this.value">
					<?php foreach ( $scopes as $s ) : ?>
						<option value="<?php echo esc_url( admin_url( 'admin.php?page=owbn-board-handoff&scope=' . rawurlencode( $s ) ) ); ?>" <?php selected( $s, $scope ); ?>>
							<?php echo esc_html( owbn_board_handoff_scope_title( $s ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php endif; ?>

		<?php if ( $msg ) : ?>
			<div class="notice notice-<?php echo 'error' === $msg ? 'error' : 'success'; ?> is-dismissible">
				<p><?php echo esc_html( ucfirst( str_replace( '_', ' ', $msg ) ) ); ?>.</p>
			</div>
		<?php endif; ?>

		<p class="description">
			<?php esc_html_e( 'Persistent notes for whoever holds this role next. Append-only by convention — edit your own entries, mark old ones as outdated, add new context for your successor.', 'owbn-board' ); ?>
		</p>

		<?php if ( empty( $sections ) ) : ?>
			<p><?php esc_html_e( 'No sections configured. Add one below.', 'owbn-board' ); ?></p>
		<?php else : ?>
			<?php foreach ( $sections as $section ) : ?>
				<?php owbn_board_handoff_render_section( $section, $handoff, $scope ); ?>
			<?php endforeach; ?>
		<?php endif; ?>

		<div class="owbn-board-handoff-admin__add-section">
			<h3><?php esc_html_e( 'Add a section', 'owbn-board' ); ?></h3>
			<form method="post">
				<?php wp_nonce_field( 'owbn_board_handoff_section' ); ?>
				<input type="hidden" name="owbn_board_handoff_action" value="add_section" />
				<input type="hidden" name="handoff_id" value="<?php echo (int) $handoff->id; ?>" />
				<input type="hidden" name="scope" value="<?php echo esc_attr( $scope ); ?>" />
				<input type="text" name="label" placeholder="<?php esc_attr_e( 'Section name', 'owbn-board' ); ?>" required maxlength="255" />
				<button type="submit" class="button"><?php esc_html_e( 'Add Section', 'owbn-board' ); ?></button>
			</form>
		</div>
	</div>
	<?php
}

function owbn_board_handoff_render_section( $section, $handoff, $scope ) {
	$entries = owbn_board_handoff_get_entries( $section->id );
	?>
	<div class="owbn-board-handoff-admin__section">
		<h2 class="owbn-board-handoff-admin__section-title"><?php echo esc_html( $section->label ); ?></h2>

		<?php if ( empty( $entries ) ) : ?>
			<p class="description"><?php esc_html_e( 'No entries in this section yet.', 'owbn-board' ); ?></p>
		<?php else : ?>
			<div class="owbn-board-handoff-admin__entries">
				<?php foreach ( $entries as $entry ) : ?>
					<?php owbn_board_handoff_render_entry( $entry, $scope ); ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<details class="owbn-board-handoff-admin__add-entry">
			<summary><?php esc_html_e( 'Add entry', 'owbn-board' ); ?></summary>
			<form method="post">
				<?php wp_nonce_field( 'owbn_board_handoff_entry' ); ?>
				<input type="hidden" name="owbn_board_handoff_action" value="add_entry" />
				<input type="hidden" name="section_id" value="<?php echo (int) $section->id; ?>" />
				<input type="hidden" name="scope" value="<?php echo esc_attr( $scope ); ?>" />

				<p>
					<input type="text" name="title" placeholder="<?php esc_attr_e( 'Title (optional)', 'owbn-board' ); ?>" class="large-text" maxlength="255" />
				</p>
				<p>
					<?php
					wp_editor(
						'',
						'body_' . (int) $section->id,
						[
							'textarea_name' => 'body',
							'textarea_rows' => 6,
							'media_buttons' => false,
							'teeny'         => true,
						]
					);
					?>
				</p>
				<p>
					<input type="text" name="tags" placeholder="<?php esc_attr_e( 'Tags (comma-separated, optional)', 'owbn-board' ); ?>" class="regular-text" />
				</p>
				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Add Entry', 'owbn-board' ); ?></button>
				</p>
			</form>
		</details>
	</div>
	<?php
}

function owbn_board_handoff_render_entry( $entry, $scope ) {
	$author      = get_userdata( $entry->author_id );
	$author_name = $author ? $author->display_name : __( 'unknown', 'owbn-board' );
	$date        = wp_date( get_option( 'date_format' ), strtotime( $entry->created_at ) );
	$is_author   = ( (int) $entry->author_id === get_current_user_id() );
	?>
	<div class="owbn-board-handoff-admin__entry owbn-board-handoff-admin__entry--<?php echo esc_attr( $entry->status ); ?>">
		<div class="owbn-board-handoff-admin__entry-header">
			<?php if ( ! empty( $entry->title ) ) : ?>
				<strong class="owbn-board-handoff-admin__entry-title"><?php echo esc_html( $entry->title ); ?></strong>
			<?php endif; ?>
			<span class="owbn-board-handoff-admin__entry-author">
				<?php
				printf(
					/* translators: 1: author name, 2: date */
					esc_html__( '%1$s · %2$s', 'owbn-board' ),
					esc_html( $author_name ),
					esc_html( $date )
				);
				?>
			</span>
			<?php if ( 'current' !== $entry->status ) : ?>
				<span class="owbn-board-handoff-admin__entry-status"><?php echo esc_html( $entry->status ); ?></span>
			<?php endif; ?>
		</div>
		<div class="owbn-board-handoff-admin__entry-body">
			<?php echo wp_kses_post( $entry->body ); ?>
		</div>
		<?php if ( ! empty( $entry->tags ) ) : ?>
			<div class="owbn-board-handoff-admin__entry-tags"><?php echo esc_html( $entry->tags ); ?></div>
		<?php endif; ?>

		<div class="owbn-board-handoff-admin__entry-actions">
			<?php if ( 'current' === $entry->status ) : ?>
				<form method="post" style="display:inline">
					<?php wp_nonce_field( 'owbn_board_handoff_entry' ); ?>
					<input type="hidden" name="owbn_board_handoff_action" value="mark_outdated" />
					<input type="hidden" name="entry_id" value="<?php echo (int) $entry->id; ?>" />
					<input type="hidden" name="scope" value="<?php echo esc_attr( $scope ); ?>" />
					<button type="submit" class="button-link"><?php esc_html_e( 'Mark outdated', 'owbn-board' ); ?></button>
				</form>
			<?php endif; ?>
			<?php if ( $is_author || owbn_board_user_can_manage() ) : ?>
				|
				<form method="post" style="display:inline" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this entry?', 'owbn-board' ) ); ?>');">
					<?php wp_nonce_field( 'owbn_board_handoff_entry' ); ?>
					<input type="hidden" name="owbn_board_handoff_action" value="delete_entry" />
					<input type="hidden" name="entry_id" value="<?php echo (int) $entry->id; ?>" />
					<input type="hidden" name="scope" value="<?php echo esc_attr( $scope ); ?>" />
					<button type="submit" class="button-link button-link-delete"><?php esc_html_e( 'Delete', 'owbn-board' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

function owbn_board_handoff_handle_post() {
	if ( empty( $_POST['owbn_board_handoff_action'] ) ) {
		return;
	}

	$action  = sanitize_key( wp_unslash( $_POST['owbn_board_handoff_action'] ) );
	$user_id = get_current_user_id();
	$scope   = isset( $_POST['scope'] ) ? sanitize_text_field( wp_unslash( $_POST['scope'] ) ) : '';
	$scopes  = owbn_board_handoff_user_scopes( $user_id );

	if ( ! in_array( $scope, $scopes, true ) ) {
		wp_die( esc_html__( 'Forbidden.', 'owbn-board' ) );
	}

	$redirect_base = [
		'page'  => 'owbn-board-handoff',
		'scope' => $scope,
	];

	if ( 'add_section' === $action ) {
		check_admin_referer( 'owbn_board_handoff_section' );
		$handoff_id = isset( $_POST['handoff_id'] ) ? absint( $_POST['handoff_id'] ) : 0;
		$label      = isset( $_POST['label'] ) ? wp_unslash( (string) $_POST['label'] ) : '';

		global $wpdb;
		$handoff_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT scope FROM " . owbn_board_handoff_table() . " WHERE id = %d",
				$handoff_id
			)
		);
		if ( ! $handoff_row || $handoff_row->scope !== $scope ) {
			wp_die( esc_html__( 'Forbidden.', 'owbn-board' ) );
		}

		$new_id = owbn_board_handoff_add_section( $handoff_id, $label );
		owbn_board_audit( $user_id, 'handoff.section.add', 'handoff_section', (int) $new_id, [ 'scope' => $scope ] );
		wp_safe_redirect( add_query_arg( array_merge( $redirect_base, [ 'msg' => 'section_added' ] ), admin_url( 'admin.php' ) ) );
		exit;
	}

	if ( 'add_entry' === $action ) {
		check_admin_referer( 'owbn_board_handoff_entry' );
		$section_id = isset( $_POST['section_id'] ) ? absint( $_POST['section_id'] ) : 0;

		if ( ! owbn_board_handoff_section_belongs_to_user( $section_id, $user_id ) ) {
			wp_die( esc_html__( 'Forbidden.', 'owbn-board' ) );
		}

		$data = [
			'section_id' => $section_id,
			'title'      => isset( $_POST['title'] ) ? wp_unslash( (string) $_POST['title'] ) : '',
			'body'       => isset( $_POST['body'] ) ? wp_unslash( (string) $_POST['body'] ) : '',
			'tags'       => isset( $_POST['tags'] ) ? wp_unslash( (string) $_POST['tags'] ) : '',
		];

		$new_id = owbn_board_handoff_create_entry( $data );
		owbn_board_audit( $user_id, 'handoff.entry.add', 'handoff_entry', (int) $new_id, [ 'scope' => $scope ] );
		wp_safe_redirect( add_query_arg( array_merge( $redirect_base, [ 'msg' => 'entry_added' ] ), admin_url( 'admin.php' ) ) );
		exit;
	}

	if ( 'mark_outdated' === $action ) {
		check_admin_referer( 'owbn_board_handoff_entry' );
		$entry_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;

		if ( ! owbn_board_handoff_entry_belongs_to_user( $entry_id, $user_id ) ) {
			wp_die( esc_html__( 'Forbidden.', 'owbn-board' ) );
		}

		owbn_board_handoff_update_entry( $entry_id, [ 'status' => 'outdated' ] );
		owbn_board_audit( $user_id, 'handoff.entry.mark_outdated', 'handoff_entry', $entry_id, [ 'scope' => $scope ] );
		wp_safe_redirect( add_query_arg( array_merge( $redirect_base, [ 'msg' => 'entry_outdated' ] ), admin_url( 'admin.php' ) ) );
		exit;
	}

	if ( 'delete_entry' === $action ) {
		check_admin_referer( 'owbn_board_handoff_entry' );
		$entry_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;

		$entry = owbn_board_handoff_get_entry( $entry_id );
		if ( ! $entry ) {
			wp_die( esc_html__( 'Entry not found.', 'owbn-board' ) );
		}

		// Only author or admin can delete
		$is_author = ( (int) $entry->author_id === $user_id );
		if ( ! $is_author && ! owbn_board_user_can_manage() ) {
			wp_die( esc_html__( 'Forbidden.', 'owbn-board' ) );
		}

		if ( ! owbn_board_handoff_entry_belongs_to_user( $entry_id, $user_id ) ) {
			wp_die( esc_html__( 'Forbidden.', 'owbn-board' ) );
		}

		owbn_board_handoff_delete_entry( $entry_id );
		owbn_board_audit( $user_id, 'handoff.entry.delete', 'handoff_entry', $entry_id, [ 'scope' => $scope ] );
		wp_safe_redirect( add_query_arg( array_merge( $redirect_base, [ 'msg' => 'entry_deleted' ] ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
