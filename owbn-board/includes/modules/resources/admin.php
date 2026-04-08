<?php
/**
 * Resources module — admin page for managing links.
 *
 * Articles use WP's native post editor (registered via the CPT). This page only
 * manages the separate links table.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_resources_register_admin() {
	add_submenu_page(
		'owbn-board',
		__( 'Resource Links', 'owbn-board' ),
		__( 'Resource Links', 'owbn-board' ),
		'manage_options',
		'owbn-board-resource-links',
		'owbn_board_resources_render_admin_page'
	);
}

function owbn_board_resources_render_admin_page() {
	if ( ! owbn_board_user_can_manage() ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'owbn-board' ) );
	}

	owbn_board_resources_handle_post();

	$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';
	$id     = isset( $_GET['link'] ) ? absint( $_GET['link'] ) : 0;

	if ( 'edit' === $action || 'new' === $action ) {
		$link = $id ? owbn_board_resources_get_link( $id ) : null;
		owbn_board_resources_render_form( $link );
	} else {
		owbn_board_resources_render_list();
	}
}

function owbn_board_resources_handle_post() {
	if ( empty( $_POST['owbn_board_resources_action'] ) ) {
		return;
	}
	$action = sanitize_key( wp_unslash( $_POST['owbn_board_resources_action'] ) );

	if ( 'save' === $action ) {
		check_admin_referer( 'owbn_board_resources_save' );
		$id   = isset( $_POST['link_id'] ) ? absint( $_POST['link_id'] ) : 0;
		$data = [
			'title'       => isset( $_POST['title'] ) ? wp_unslash( (string) $_POST['title'] ) : '',
			'url'         => isset( $_POST['url'] ) ? wp_unslash( (string) $_POST['url'] ) : '',
			'description' => isset( $_POST['description'] ) ? wp_unslash( (string) $_POST['description'] ) : '',
			'category'    => isset( $_POST['category'] ) ? wp_unslash( (string) $_POST['category'] ) : '',
		];

		if ( $id ) {
			$ok      = owbn_board_resources_update_link( $id, $data );
			$message = $ok ? 'updated' : 'error';
			owbn_board_audit( get_current_user_id(), 'resources.link.update', 'resource_link', $id, [ 'ok' => $ok ] );
		} else {
			$new_id  = owbn_board_resources_create_link( $data );
			$message = $new_id ? 'created' : 'error';
			owbn_board_audit( get_current_user_id(), 'resources.link.create', 'resource_link', (int) $new_id );
		}

		wp_safe_redirect( add_query_arg( [ 'page' => 'owbn-board-resource-links', 'msg' => $message ], admin_url( 'admin.php' ) ) );
		exit;
	}

	if ( 'delete' === $action ) {
		check_admin_referer( 'owbn_board_resources_delete' );
		$id = isset( $_POST['link_id'] ) ? absint( $_POST['link_id'] ) : 0;
		if ( $id ) {
			$ok = owbn_board_resources_delete_link( $id );
			owbn_board_audit( get_current_user_id(), 'resources.link.delete', 'resource_link', $id, [ 'ok' => $ok ] );
			wp_safe_redirect( add_query_arg( [ 'page' => 'owbn-board-resource-links', 'msg' => $ok ? 'deleted' : 'error' ], admin_url( 'admin.php' ) ) );
			exit;
		}
	}
}

function owbn_board_resources_render_list() {
	$links = owbn_board_resources_get_links( 100 );
	$msg   = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Resource Links', 'owbn-board' ); ?></h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=owbn-board-resource-links&action=new' ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'Add Link', 'owbn-board' ); ?>
		</a>

		<?php if ( $msg ) : ?>
			<div class="notice notice-<?php echo 'error' === $msg ? 'error' : 'success'; ?> is-dismissible">
				<p><?php echo esc_html( ucfirst( $msg ) ); ?>.</p>
			</div>
		<?php endif; ?>

		<p class="description"><?php esc_html_e( 'Curated external links shown in the Resources tile. For full articles, use the Resources post type in the left menu.', 'owbn-board' ); ?></p>

		<?php if ( empty( $links ) ) : ?>
			<p><?php esc_html_e( 'No links yet.', 'owbn-board' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Title', 'owbn-board' ); ?></th>
						<th><?php esc_html_e( 'URL', 'owbn-board' ); ?></th>
						<th><?php esc_html_e( 'Category', 'owbn-board' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'owbn-board' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $links as $link ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $link->title ); ?></strong></td>
							<td><a href="<?php echo esc_url( $link->url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( mb_strimwidth( $link->url, 0, 50, '…' ) ); ?></a></td>
							<td><?php echo esc_html( $link->category ?: '—' ); ?></td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=owbn-board-resource-links&action=edit&link=' . (int) $link->id ) ); ?>"><?php esc_html_e( 'Edit', 'owbn-board' ); ?></a>
								|
								<form method="post" style="display:inline" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this link?', 'owbn-board' ) ); ?>');">
									<?php wp_nonce_field( 'owbn_board_resources_delete' ); ?>
									<input type="hidden" name="owbn_board_resources_action" value="delete" />
									<input type="hidden" name="link_id" value="<?php echo (int) $link->id; ?>" />
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

function owbn_board_resources_render_form( $link = null ) {
	$is_edit = ! empty( $link );
	$title   = $is_edit ? $link->title : '';
	$url     = $is_edit ? $link->url : '';
	$desc    = $is_edit ? $link->description : '';
	$cat     = $is_edit ? $link->category : '';
	?>
	<div class="wrap">
		<h1><?php echo $is_edit ? esc_html__( 'Edit Link', 'owbn-board' ) : esc_html__( 'New Link', 'owbn-board' ); ?></h1>

		<form method="post">
			<?php wp_nonce_field( 'owbn_board_resources_save' ); ?>
			<input type="hidden" name="owbn_board_resources_action" value="save" />
			<?php if ( $is_edit ) : ?>
				<input type="hidden" name="link_id" value="<?php echo (int) $link->id; ?>" />
			<?php endif; ?>

			<table class="form-table">
				<tr>
					<th scope="row"><label for="title"><?php esc_html_e( 'Title', 'owbn-board' ); ?></label></th>
					<td><input type="text" name="title" id="title" class="regular-text" value="<?php echo esc_attr( $title ); ?>" required maxlength="255" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="url"><?php esc_html_e( 'URL', 'owbn-board' ); ?></label></th>
					<td><input type="url" name="url" id="url" class="regular-text" value="<?php echo esc_attr( $url ); ?>" required placeholder="https://…" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="description"><?php esc_html_e( 'Description', 'owbn-board' ); ?></label></th>
					<td><textarea name="description" id="description" rows="3" class="large-text"><?php echo esc_textarea( $desc ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="category"><?php esc_html_e( 'Category', 'owbn-board' ); ?></label></th>
					<td><input type="text" name="category" id="category" class="regular-text" value="<?php echo esc_attr( $cat ); ?>" maxlength="100" /></td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php echo $is_edit ? esc_html__( 'Update Link', 'owbn-board' ) : esc_html__( 'Add Link', 'owbn-board' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=owbn-board-resource-links' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'owbn-board' ); ?></a>
			</p>
		</form>
	</div>
	<?php
}
