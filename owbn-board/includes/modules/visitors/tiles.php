<?php
/**
 * Visitors tile — cross-chronicle character travel log.
 *
 * For staff: shows visitors TO their chronicle with an add form.
 * For players: shows the player's own visit records.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_visitors_register_tile() {
	owbn_board_register_tile( [
		'id'          => 'board:visitors',
		'title'       => __( 'Visitors', 'owbn-board' ),
		'icon'        => 'dashicons-admin-users',
		'read_roles'  => [ 'chronicle/*/*' ],
		'write_roles' => [ 'chronicle/*/staff', 'chronicle/*/cm', 'chronicle/*/hst' ],
		'size'        => '2x2',
		'category'    => 'communication',
		'priority'    => 22,
		'render'      => 'owbn_board_render_visitors_tile',
	] );
}

function owbn_board_render_visitors_tile( $tile, $user_id, $can_write ) {
	$host_slugs = owbn_board_visitors_user_host_slugs( $user_id );
	$player_slug = owbn_board_visitors_user_home_slug( $user_id );

	// Staff: show visits to their primary chronicle, plus add form
	if ( ! empty( $host_slugs ) && $can_write ) {
		$host = $host_slugs[0]; // primary host chronicle
		$visits = owbn_board_visitors_get_by_host( $host, 10 );
		owbn_board_visitors_render_staff_view( $host, $visits );
		return;
	}

	// Player: show their own visit records
	if ( $player_slug ) {
		$visits = owbn_board_visitors_get_by_player( $user_id, 10 );
		owbn_board_visitors_render_player_view( $user_id, $visits );
		return;
	}

	echo '<p class="owbn-board-visitors__empty">' . esc_html__( 'No chronicle scope found for your roles.', 'owbn-board' ) . '</p>';
}

function owbn_board_visitors_render_staff_view( $host_slug, $visits ) {
	?>
	<div class="owbn-board-visitors" data-host="<?php echo esc_attr( $host_slug ); ?>">
		<div class="owbn-board-visitors__meta">
			<?php printf(
				/* translators: %s: chronicle slug */
				esc_html__( 'Visitors to %s', 'owbn-board' ),
				'<code>' . esc_html( $host_slug ) . '</code>'
			); ?>
		</div>

		<form class="owbn-board-visitors__form">
			<input type="hidden" name="host_chronicle_slug" value="<?php echo esc_attr( $host_slug ); ?>" />
			<div class="owbn-board-visitors__form-row">
				<input type="text" name="character_name" placeholder="<?php esc_attr_e( 'Character name', 'owbn-board' ); ?>" required maxlength="255" />
				<input type="date" name="visit_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required />
			</div>
			<div class="owbn-board-visitors__form-row">
				<input type="text" name="home_chronicle_slug" placeholder="<?php esc_attr_e( 'Home chronicle (optional)', 'owbn-board' ); ?>" />
				<input type="email" name="visitor_email" placeholder="<?php esc_attr_e( 'Player email (optional)', 'owbn-board' ); ?>" />
			</div>
			<textarea name="notes" placeholder="<?php esc_attr_e( 'Notes (optional)', 'owbn-board' ); ?>" rows="2"></textarea>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Log Visit', 'owbn-board' ); ?></button>
		</form>

		<div class="owbn-board-visitors__list">
			<?php if ( empty( $visits ) ) : ?>
				<p class="owbn-board-visitors__empty"><?php esc_html_e( 'No visitors logged yet.', 'owbn-board' ); ?></p>
			<?php else : ?>
				<?php foreach ( $visits as $visit ) : ?>
					<?php owbn_board_visitors_render_entry( $visit ); ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

function owbn_board_visitors_render_player_view( $user_id, $visits ) {
	?>
	<div class="owbn-board-visitors owbn-board-visitors--player">
		<div class="owbn-board-visitors__meta">
			<?php esc_html_e( 'Your visit history', 'owbn-board' ); ?>
		</div>
		<div class="owbn-board-visitors__list">
			<?php if ( empty( $visits ) ) : ?>
				<p class="owbn-board-visitors__empty"><?php esc_html_e( 'No visits logged.', 'owbn-board' ); ?></p>
			<?php else : ?>
				<?php foreach ( $visits as $visit ) : ?>
					<?php owbn_board_visitors_render_entry( $visit ); ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

function owbn_board_visitors_render_entry( $visit ) {
	$date  = wp_date( get_option( 'date_format' ), strtotime( $visit->visit_date ) );
	$host  = $visit->host_chronicle_slug;
	$home  = $visit->home_chronicle_slug;
	$char  = $visit->character_name;
	$name  = $visit->visitor_display_name;
	?>
	<div class="owbn-board-visitors__item">
		<div class="owbn-board-visitors__item-header">
			<strong><?php echo esc_html( $char ); ?></strong>
			<?php if ( $name ) : ?>
				<span class="owbn-board-visitors__player">(<?php echo esc_html( $name ); ?>)</span>
			<?php endif; ?>
			<span class="owbn-board-visitors__date"><?php echo esc_html( $date ); ?></span>
		</div>
		<div class="owbn-board-visitors__item-chronicles">
			<?php if ( $home ) : ?>
				<?php printf( esc_html__( 'from %1$s visiting %2$s', 'owbn-board' ), '<code>' . esc_html( $home ) . '</code>', '<code>' . esc_html( $host ) . '</code>' ); ?>
			<?php else : ?>
				<?php printf( esc_html__( 'visiting %s', 'owbn-board' ), '<code>' . esc_html( $host ) . '</code>' ); ?>
			<?php endif; ?>
		</div>
		<?php if ( ! empty( $visit->notes ) ) : ?>
			<div class="owbn-board-visitors__notes"><?php echo wp_kses_post( $visit->notes ); ?></div>
		<?php endif; ?>
	</div>
	<?php
}
