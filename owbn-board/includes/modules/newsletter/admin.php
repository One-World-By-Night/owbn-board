<?php
/**
 * Newsletter module — admin page for editors.
 *
 * Registered as a submenu under OWBN Board. Lists all editions with add/edit/delete.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the admin submenu.
 */
function owbn_board_newsletter_register_admin() {
	add_submenu_page(
		'owbn-board',
		__( 'Newsletter', 'owbn-board' ),
		__( 'Newsletter', 'owbn-board' ),
		'manage_options',
		'owbn-board-newsletter',
		'owbn_board_newsletter_render_admin_page'
	);
}

/**
 * Main admin page renderer — routes between list and edit views.
 */
function owbn_board_newsletter_render_admin_page() {
	if ( ! owbn_board_user_can_manage() ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'owbn-board' ) );
	}

	// Handle form submissions
	owbn_board_newsletter_handle_post();

	$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';
	$id     = isset( $_GET['edition'] ) ? absint( $_GET['edition'] ) : 0;

	if ( 'edit' === $action || 'new' === $action ) {
		$edition = $id ? owbn_board_newsletter_get_edition( $id ) : null;
		owbn_board_newsletter_render_form( $edition );
	} else {
		owbn_board_newsletter_render_list();
	}
}

/**
 * Handle POST requests for create/update/delete.
 */
function owbn_board_newsletter_handle_post() {
	if ( empty( $_POST['owbn_board_newsletter_action'] ) ) {
		return;
	}

	$action = sanitize_key( wp_unslash( $_POST['owbn_board_newsletter_action'] ) );

	if ( 'save' === $action ) {
		check_admin_referer( 'owbn_board_newsletter_save' );
		$id   = isset( $_POST['edition_id'] ) ? absint( $_POST['edition_id'] ) : 0;
		$data = [
			'title'          => isset( $_POST['title'] ) ? wp_unslash( (string) $_POST['title'] ) : '',
			'published_at'   => isset( $_POST['published_at'] ) ? wp_unslash( (string) $_POST['published_at'] ) : '',
			'url'            => isset( $_POST['url'] ) ? wp_unslash( (string) $_POST['url'] ) : '',
			'summary'        => isset( $_POST['summary'] ) ? wp_unslash( (string) $_POST['summary'] ) : '',
			'cover_image_id' => isset( $_POST['cover_image_id'] ) ? absint( $_POST['cover_image_id'] ) : 0,
		];

		if ( $id ) {
			$ok = owbn_board_newsletter_update_edition( $id, $data );
			owbn_board_audit( get_current_user_id(), 'newsletter.update', 'newsletter', $id, [ 'ok' => $ok ] );
			$message = $ok ? 'updated' : 'error';
		} else {
			$new_id  = owbn_board_newsletter_create_edition( $data );
			owbn_board_audit( get_current_user_id(), 'newsletter.create', 'newsletter', (int) $new_id );
			$message = $new_id ? 'created' : 'error';
		}

		wp_safe_redirect( add_query_arg( [ 'page' => 'owbn-board-newsletter', 'msg' => $message ], admin_url( 'admin.php' ) ) );
		exit;
	}

	if ( 'delete' === $action ) {
		check_admin_referer( 'owbn_board_newsletter_delete' );
		$id = isset( $_POST['edition_id'] ) ? absint( $_POST['edition_id'] ) : 0;
		if ( $id ) {
			$ok = owbn_board_newsletter_delete_edition( $id );
			owbn_board_audit( get_current_user_id(), 'newsletter.delete', 'newsletter', $id, [ 'ok' => $ok ] );
			wp_safe_redirect( add_query_arg( [ 'page' => 'owbn-board-newsletter', 'msg' => $ok ? 'deleted' : 'error' ], admin_url( 'admin.php' ) ) );
			exit;
		}
	}
}

/**
 * Render the list view.
 */
function owbn_board_newsletter_render_list() {
	$editions = owbn_board_newsletter_get_editions( 100 );
	$total    = owbn_board_newsletter_count();
	$msg      = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Newsletter Editions', 'owbn-board' ); ?></h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=owbn-board-newsletter&action=new' ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'Add Edition', 'owbn-board' ); ?>
		</a>

		<?php if ( $msg ) : ?>
			<div class="notice notice-<?php echo 'error' === $msg ? 'error' : 'success'; ?> is-dismissible">
				<p>
					<?php
					switch ( $msg ) {
						case 'created':
							esc_html_e( 'Edition added.', 'owbn-board' );
							break;
						case 'updated':
							esc_html_e( 'Edition updated.', 'owbn-board' );
							break;
						case 'deleted':
							esc_html_e( 'Edition deleted.', 'owbn-board' );
							break;
						default:
							esc_html_e( 'Something went wrong.', 'owbn-board' );
					}
					?>
				</p>
			</div>
		<?php endif; ?>

		<p class="description">
			<?php printf(
				/* translators: %d: total editions */
				esc_html( _n( '%d edition total.', '%d editions total.', $total, 'owbn-board' ) ),
				(int) $total
			); ?>
		</p>

		<?php if ( empty( $editions ) ) : ?>
			<p><?php esc_html_e( 'No editions yet. Click "Add Edition" to create one.', 'owbn-board' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Title', 'owbn-board' ); ?></th>
						<th><?php esc_html_e( 'Published', 'owbn-board' ); ?></th>
						<th><?php esc_html_e( 'URL', 'owbn-board' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'owbn-board' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $editions as $edition ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $edition->title ); ?></strong></td>
							<td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $edition->published_at ) ) ); ?></td>
							<td><a href="<?php echo esc_url( $edition->url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( owbn_board_newsletter_truncate_url( $edition->url ) ); ?></a></td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=owbn-board-newsletter&action=edit&edition=' . (int) $edition->id ) ); ?>"><?php esc_html_e( 'Edit', 'owbn-board' ); ?></a>
								|
								<form method="post" style="display:inline" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this edition?', 'owbn-board' ) ); ?>');">
									<?php wp_nonce_field( 'owbn_board_newsletter_delete' ); ?>
									<input type="hidden" name="owbn_board_newsletter_action" value="delete" />
									<input type="hidden" name="edition_id" value="<?php echo (int) $edition->id; ?>" />
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

/**
 * Render the add/edit form.
 */
function owbn_board_newsletter_render_form( $edition = null ) {
	$is_edit = ! empty( $edition );
	$title   = $is_edit ? $edition->title : '';
	$date    = $is_edit ? $edition->published_at : current_time( 'Y-m-d' );
	$url     = $is_edit ? $edition->url : '';
	$summary = $is_edit ? $edition->summary : '';
	$cover   = $is_edit && $edition->cover_image_id ? (int) $edition->cover_image_id : 0;

	wp_enqueue_media();
	?>
	<div class="wrap">
		<h1><?php echo $is_edit ? esc_html__( 'Edit Newsletter Edition', 'owbn-board' ) : esc_html__( 'New Newsletter Edition', 'owbn-board' ); ?></h1>

		<form method="post">
			<?php wp_nonce_field( 'owbn_board_newsletter_save' ); ?>
			<input type="hidden" name="owbn_board_newsletter_action" value="save" />
			<?php if ( $is_edit ) : ?>
				<input type="hidden" name="edition_id" value="<?php echo (int) $edition->id; ?>" />
			<?php endif; ?>

			<table class="form-table">
				<tr>
					<th scope="row"><label for="title"><?php esc_html_e( 'Title', 'owbn-board' ); ?></label></th>
					<td><input type="text" name="title" id="title" class="regular-text" value="<?php echo esc_attr( $title ); ?>" required maxlength="255" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="published_at"><?php esc_html_e( 'Publication Date', 'owbn-board' ); ?></label></th>
					<td><input type="date" name="published_at" id="published_at" value="<?php echo esc_attr( $date ); ?>" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="url"><?php esc_html_e( 'URL', 'owbn-board' ); ?></label></th>
					<td><input type="url" name="url" id="url" class="regular-text" value="<?php echo esc_attr( $url ); ?>" required placeholder="https://…" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="summary"><?php esc_html_e( 'Summary', 'owbn-board' ); ?></label></th>
					<td><textarea name="summary" id="summary" rows="4" class="large-text"><?php echo esc_textarea( $summary ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="cover_image_id"><?php esc_html_e( 'Cover Image', 'owbn-board' ); ?></label></th>
					<td>
						<input type="hidden" name="cover_image_id" id="cover_image_id" value="<?php echo (int) $cover; ?>" />
						<div id="owbn-board-newsletter-cover-preview">
							<?php if ( $cover ) : ?>
								<?php $thumb = wp_get_attachment_image_url( $cover, 'thumbnail' ); ?>
								<?php if ( $thumb ) : ?>
									<img src="<?php echo esc_url( $thumb ); ?>" alt="" style="max-width:150px;height:auto;" />
								<?php endif; ?>
							<?php endif; ?>
						</div>
						<button type="button" class="button" id="owbn-board-newsletter-cover-pick"><?php esc_html_e( 'Select Image', 'owbn-board' ); ?></button>
						<button type="button" class="button" id="owbn-board-newsletter-cover-clear"><?php esc_html_e( 'Remove', 'owbn-board' ); ?></button>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php echo $is_edit ? esc_html__( 'Update Edition', 'owbn-board' ) : esc_html__( 'Add Edition', 'owbn-board' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=owbn-board-newsletter' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'owbn-board' ); ?></a>
			</p>
		</form>
	</div>

	<script>
	(function ($) {
		$(function () {
			var frame;
			$('#owbn-board-newsletter-cover-pick').on('click', function (e) {
				e.preventDefault();
				if (frame) { frame.open(); return; }
				frame = wp.media({
					title: '<?php echo esc_js( __( 'Select Cover Image', 'owbn-board' ) ); ?>',
					button: { text: '<?php echo esc_js( __( 'Use this image', 'owbn-board' ) ); ?>' },
					multiple: false
				});
				frame.on('select', function () {
					var att = frame.state().get('selection').first().toJSON();
					$('#cover_image_id').val(att.id);
					$('#owbn-board-newsletter-cover-preview').html('<img src="' + att.sizes.thumbnail.url + '" alt="" style="max-width:150px;height:auto;" />');
				});
				frame.open();
			});
			$('#owbn-board-newsletter-cover-clear').on('click', function (e) {
				e.preventDefault();
				$('#cover_image_id').val(0);
				$('#owbn-board-newsletter-cover-preview').empty();
			});
		});
	})(jQuery);
	</script>
	<?php
}

/**
 * Truncate long URLs for display in the table.
 */
function owbn_board_newsletter_truncate_url( $url ) {
	$url = (string) $url;
	if ( strlen( $url ) <= 60 ) {
		return $url;
	}
	return substr( $url, 0, 57 ) . '…';
}
