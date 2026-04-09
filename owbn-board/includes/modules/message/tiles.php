<?php
/**
 * Group Messages tile — lightweight chat scoped to the user's role path.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_message_register_tile() {
	owbn_board_register_tile( [
		'id'          => 'board:message',
		'title'       => __( 'Group Messages', 'owbn-board' ),
		'icon'        => 'dashicons-format-chat',
		'read_roles'  => [ 'chronicle/*/*', 'coordinator/*/*', 'exec/*/*' ],
		'write_roles' => [ 'chronicle/*/*', 'coordinator/*/*', 'exec/*/*' ],
		'size'        => '1x2',
		'category'    => 'communication',
		'priority'    => 10,
		'render'      => 'owbn_board_render_message_tile',
	] );
}

function owbn_board_render_message_tile( $tile, $user_id, $can_write ) {
	$role_path = function_exists( 'owbn_board_notebook_resolve_role_path' )
		? owbn_board_notebook_resolve_role_path( $user_id )
		: null;

	if ( ! $role_path ) {
		echo '<p>' . esc_html__( 'No matching group found for your roles.', 'owbn-board' ) . '</p>';
		return;
	}

	global $wpdb;
	$messages = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT m.*, u.display_name
			 FROM {$wpdb->prefix}owbn_board_messages m
			 LEFT JOIN {$wpdb->users} u ON u.ID = m.user_id
			 WHERE m.role_path = %s AND m.site_id = 0 AND m.deleted_at IS NULL
			 ORDER BY m.created_at DESC
			 LIMIT 20",
			$role_path
		)
	);
	?>
	<div class="owbn-board-message" data-role-path="<?php echo esc_attr( $role_path ); ?>">
		<?php if ( $can_write ) : ?>
			<form class="owbn-board-message__form">
				<textarea class="owbn-board-message__input" rows="2" placeholder="<?php esc_attr_e( 'Post a message to your group…', 'owbn-board' ); ?>" maxlength="2000"></textarea>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Post', 'owbn-board' ); ?></button>
			</form>
		<?php endif; ?>

		<div class="owbn-board-message__feed">
			<?php if ( empty( $messages ) ) : ?>
				<p class="owbn-board-message__empty"><?php esc_html_e( 'No messages yet.', 'owbn-board' ); ?></p>
			<?php else : ?>
				<?php foreach ( $messages as $msg ) :
					$author      = $msg->display_name ?: __( 'unknown', 'owbn-board' );
					$ago         = human_time_diff( strtotime( $msg->created_at ), time() ) . ' ' . __( 'ago', 'owbn-board' );
					$can_delete  = ( (int) $msg->user_id === $user_id ) || owbn_board_user_can_manage();
					?>
					<div class="owbn-board-message__item" data-message-id="<?php echo esc_attr( $msg->id ); ?>">
						<div class="owbn-board-message__meta">
							<strong class="owbn-board-message__author"><?php echo esc_html( $author ); ?></strong>
							<span class="owbn-board-message__time"><?php echo esc_html( $ago ); ?></span>
							<?php if ( $can_delete ) : ?>
								<button type="button" class="owbn-board-message__delete" aria-label="<?php esc_attr_e( 'Delete message', 'owbn-board' ); ?>">&times;</button>
							<?php endif; ?>
						</div>
						<div class="owbn-board-message__body"><?php echo esc_html( $msg->content ); ?></div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>
	<?php
}
