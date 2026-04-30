<?php
/**
 * Sessions tile — recent session log per chronicle.
 * Multi-chronicle staff get a switcher.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_sessions_register_tile() {
	owbn_board_register_tile( [
		'id'                   => 'board:sessions',
		'title'                => __( 'Recent Sessions', 'owbn-board' ),
		'icon'                 => 'dashicons-book-alt',
		'read_roles'           => [ 'chronicle/*/staff', 'chronicle/*/cm', 'chronicle/*/hst' ],
		'write_roles'          => [ 'chronicle/*/staff', 'chronicle/*/cm', 'chronicle/*/hst' ],
		'size'                 => '2x2',
		'category'             => 'communication',
		'tab'                  => 'schedule',
		'priority'             => 18,
		'supports_share_level' => true,
		'poll_interval'        => 15000,
		'render'               => 'owbn_board_render_sessions_tile',
	] );
}

function owbn_board_render_sessions_tile( $tile, $user_id, $can_write ) {
	$slugs = owbn_board_sessions_user_chronicle_slugs( $user_id );

	if ( empty( $slugs ) ) {
		echo '<p class="owbn-board-sessions__empty">' . esc_html__( 'No chronicle scope found for your roles.', 'owbn-board' ) . '</p>';
		return;
	}

	// Each chronicle slug is a "scope" — wrap in chronicle/{slug} for the switcher.
	$scopes = array_map( function ( $slug ) {
		return 'chronicle/' . $slug;
	}, $slugs );
	$active_scope = $scopes[0];
	?>
	<div class="owbn-board-sessions" data-active-scope="<?php echo esc_attr( $active_scope ); ?>">
		<?php owbn_board_render_scope_switcher( $scopes, $active_scope, __( 'Switch chronicle', 'owbn-board' ) ); ?>

		<?php foreach ( $scopes as $i => $scope ) :
			$slug      = $slugs[ $i ];
			$is_active = ( $scope === $active_scope );
			?>
			<div class="owbn-board-sessions__panel<?php echo $is_active ? ' is-active' : ''; ?>" data-scope="<?php echo esc_attr( $scope ); ?>">
				<?php owbn_board_render_sessions_panel( $slug, $can_write ); ?>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}

function owbn_board_render_sessions_panel( $slug, $can_write ) {
	$sessions = owbn_board_sessions_get_by_chronicle( $slug, 5 );
	?>
	<div class="owbn-board-sessions__header">
		<span class="owbn-board-sessions__scope"><code><?php echo esc_html( $slug ); ?></code></span>
		<?php if ( $can_write ) : ?>
			<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=owbn-board-sessions&action=new&chronicle=' . rawurlencode( $slug ) ) ); ?>">
				<?php esc_html_e( 'New Session', 'owbn-board' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<?php if ( empty( $sessions ) ) : ?>
		<p class="owbn-board-sessions__empty"><?php esc_html_e( 'No sessions logged yet.', 'owbn-board' ); ?></p>
	<?php else : ?>
		<ul class="owbn-board-sessions__list">
			<?php foreach ( $sessions as $session ) : ?>
				<?php
				$date  = wp_date( get_option( 'date_format' ), strtotime( $session->session_date ) );
				$title = $session->title ?: __( '(untitled)', 'owbn-board' );
				$url   = admin_url( 'admin.php?page=owbn-board-sessions&action=edit&session=' . (int) $session->id );
				?>
				<li class="owbn-board-sessions__item">
					<?php if ( $can_write ) : ?>
						<a href="<?php echo esc_url( $url ); ?>" class="owbn-board-sessions__title">
							<strong><?php echo esc_html( $title ); ?></strong>
						</a>
					<?php else : ?>
						<strong><?php echo esc_html( $title ); ?></strong>
					<?php endif; ?>
					<span class="owbn-board-sessions__date"><?php echo esc_html( $date ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif;
}
