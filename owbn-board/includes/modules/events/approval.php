<?php
/**
 * Events module — approval queue admin page.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_events_register_approval_page() {
	if ( ! owbn_board_events_user_can_review() ) {
		return;
	}
	add_submenu_page(
		'owbn-board',
		__( 'Event Approvals', 'owbn-board' ),
		__( 'Event Approvals', 'owbn-board' ),
		'read',
		'owbn-board-events-approval',
		'owbn_board_events_render_approval_page'
	);
}

function owbn_board_events_render_approval_page() {
	if ( ! owbn_board_events_user_can_review() ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'owbn-board' ) );
	}

	owbn_board_events_handle_approval_post();

	$pending = owbn_board_events_get_pending();
	$msg     = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Event Approvals', 'owbn-board' ); ?></h1>

		<?php if ( $msg ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo esc_html( ucfirst( str_replace( '_', ' ', $msg ) ) ); ?>.</p>
			</div>
		<?php endif; ?>

		<p class="description"><?php esc_html_e( 'Events submitted for review. Approved events go live immediately.', 'owbn-board' ); ?></p>

		<?php if ( empty( $pending ) ) : ?>
			<p><?php esc_html_e( 'No events awaiting approval.', 'owbn-board' ); ?></p>
		<?php else : ?>
			<?php foreach ( $pending as $event ) :
				$meta   = owbn_board_events_get_meta( $event->ID );
				$author = get_userdata( $event->post_author );
				?>
				<div class="owbn-board-events-approval__item">
					<h2><?php echo esc_html( get_the_title( $event ) ); ?></h2>
					<p class="description">
						<?php
						printf(
							/* translators: 1: author name, 2: submitted date */
							esc_html__( 'Submitted by %1$s on %2$s', 'owbn-board' ),
							esc_html( $author ? $author->display_name : __( 'unknown', 'owbn-board' ) ),
							esc_html( wp_date( get_option( 'date_format' ), strtotime( $event->post_date_gmt . ' UTC' ) ) )
						);
						?>
					</p>

					<?php if ( ! empty( $meta['banner_image_id'] ) ) :
						$banner = wp_get_attachment_image_url( (int) $meta['banner_image_id'], 'medium' );
						if ( $banner ) : ?>
							<img src="<?php echo esc_url( $banner ); ?>" alt="" style="max-width:400px;height:auto;" />
					<?php endif; endif; ?>

					<?php if ( ! empty( $meta['tagline'] ) ) : ?>
						<p><strong><?php echo esc_html( $meta['tagline'] ); ?></strong></p>
					<?php endif; ?>

					<p>
						<?php if ( ! empty( $meta['start_dt'] ) ) :
							$start = strtotime( $meta['start_dt'] . ' UTC' );
							?>
							<strong><?php esc_html_e( 'Starts:', 'owbn-board' ); ?></strong>
							<?php echo esc_html( wp_date( 'M j, Y g:i a', $start ) ); ?>
							(<?php echo esc_html( $meta['timezone'] ?: 'UTC' ); ?>)
						<?php endif; ?>
					</p>

					<?php if ( ! empty( $meta['location'] ) ) : ?>
						<p><strong><?php esc_html_e( 'Location:', 'owbn-board' ); ?></strong> <?php echo esc_html( $meta['location'] ); ?></p>
					<?php endif; ?>

					<?php if ( ! empty( $meta['host_scope'] ) ) : ?>
						<p><strong><?php esc_html_e( 'Host:', 'owbn-board' ); ?></strong> <code><?php echo esc_html( $meta['host_scope'] ); ?></code></p>
					<?php endif; ?>

					<div class="owbn-board-events-approval__body">
						<?php echo wp_kses_post( apply_filters( 'the_content', $event->post_content ) ); ?>
					</div>

					<div class="owbn-board-events-approval__actions">
						<form method="post" style="display:inline">
							<?php wp_nonce_field( 'owbn_board_events_approve' ); ?>
							<input type="hidden" name="owbn_board_events_action" value="approve" />
							<input type="hidden" name="event_id" value="<?php echo (int) $event->ID; ?>" />
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Approve', 'owbn-board' ); ?></button>
						</form>
						&nbsp;
						<form method="post" style="display:inline">
							<?php wp_nonce_field( 'owbn_board_events_reject' ); ?>
							<input type="hidden" name="owbn_board_events_action" value="reject" />
							<input type="hidden" name="event_id" value="<?php echo (int) $event->ID; ?>" />
							<input type="text" name="rejection_reason" placeholder="<?php esc_attr_e( 'Reason (optional)', 'owbn-board' ); ?>" class="regular-text" />
							<button type="submit" class="button"><?php esc_html_e( 'Reject', 'owbn-board' ); ?></button>
						</form>
						&nbsp;
						<a href="<?php echo esc_url( get_edit_post_link( $event->ID ) ); ?>" class="button"><?php esc_html_e( 'Edit', 'owbn-board' ); ?></a>
					</div>
				</div>
				<hr />
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<?php
}

function owbn_board_events_handle_approval_post() {
	if ( empty( $_POST['owbn_board_events_action'] ) ) {
		return;
	}
	if ( ! owbn_board_events_user_can_review() ) {
		wp_die( esc_html__( 'Forbidden.', 'owbn-board' ) );
	}

	$action   = sanitize_key( wp_unslash( $_POST['owbn_board_events_action'] ) );
	$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
	if ( ! $event_id ) {
		return;
	}

	if ( 'approve' === $action ) {
		check_admin_referer( 'owbn_board_events_approve' );
		wp_update_post( [
			'ID'          => $event_id,
			'post_status' => 'publish',
		] );
		delete_post_meta( $event_id, '_owbn_event_rejection_reason' );
		owbn_board_audit( get_current_user_id(), 'events.approve', 'event', $event_id );
		wp_safe_redirect( add_query_arg( [ 'page' => 'owbn-board-events-approval', 'msg' => 'event_approved' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	if ( 'reject' === $action ) {
		check_admin_referer( 'owbn_board_events_reject' );
		$reason = isset( $_POST['rejection_reason'] ) ? sanitize_text_field( wp_unslash( $_POST['rejection_reason'] ) ) : '';

		wp_update_post( [
			'ID'          => $event_id,
			'post_status' => 'rejected',
		] );
		if ( $reason ) {
			update_post_meta( $event_id, '_owbn_event_rejection_reason', $reason );
		}
		owbn_board_audit( get_current_user_id(), 'events.reject', 'event', $event_id, [ 'reason' => $reason ] );
		wp_safe_redirect( add_query_arg( [ 'page' => 'owbn-board-events-approval', 'msg' => 'event_rejected' ], admin_url( 'admin.php' ) ) );
		exit;
	}
}
