<?php
/**
 * Ballot module — tile + shortcode.
 *
 * Tile: compact version showing the first 6 open votes on the dashboard.
 * Shortcode: full-page ballot with all cards and the Submit All button.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_ballot_register_tile() {
	owbn_board_register_tile( [
		'id'         => 'ballot:all-open',
		'title'      => __( 'Your Ballot', 'owbn-board' ),
		'icon'       => 'dashicons-list-view',
		'read_roles' => [],
		'size'       => '2x3',
		'category'   => 'communication',
		'priority'   => 12,
		'render'     => 'owbn_board_ballot_render_tile',
	] );
}

function owbn_board_ballot_register_shortcode() {
	add_shortcode( 'owbn_ballot', 'owbn_board_ballot_shortcode_handler' );
}

function owbn_board_ballot_render_tile( $tile, $user_id, $can_write ) {
	echo owbn_board_ballot_render_list( [
		'limit'     => 6,
		'show_cta'  => true,
		'compact'   => true,
	] );
}

function owbn_board_ballot_shortcode_handler( $atts ) {
	$atts = shortcode_atts(
		[
			'election_id' => 0,
			'limit'       => 0,
			'show_closed' => false,
		],
		$atts,
		'owbn_ballot'
	);

	if ( function_exists( 'owbn_board_enqueue_assets' ) ) {
		owbn_board_enqueue_assets();
	}

	return owbn_board_ballot_render_list( [
		'election_id' => (int) $atts['election_id'],
		'limit'       => (int) $atts['limit'],
		'show_closed' => filter_var( $atts['show_closed'], FILTER_VALIDATE_BOOLEAN ),
		'show_cta'    => false,
		'compact'     => false,
	] );
}

/**
 * Shared renderer used by both the tile and the shortcode.
 */
function owbn_board_ballot_render_list( array $args = [] ) {
	$args = wp_parse_args( $args, [
		'limit'       => 0,
		'election_id' => 0,
		'show_closed' => false,
		'show_cta'    => false,
		'compact'     => false,
	] );

	if ( ! owbn_board_ballot_wpvp_available() ) {
		ob_start();
		?>
		<div class="owbn-board-ballot owbn-board-ballot--remote">
			<p><?php esc_html_e( 'Votes are managed on council.owbn.net.', 'owbn-board' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( owbn_board_tool_url( 'wpvp', '/wp-admin/admin.php?page=wpvp' ) ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Open Ballot on Council →', 'owbn-board' ); ?>
				</a>
			</p>
		</div>
		<?php
		return ob_get_clean();
	}

	if ( $args['election_id'] ) {
		$votes = owbn_board_ballot_get_votes_for_election( $args['election_id'], $args['show_closed'] );
	} else {
		$votes = owbn_board_ballot_get_open_votes( $args['limit'] );
	}

	$user_id = get_current_user_id();

	ob_start();
	?>
	<div class="owbn-board-ballot<?php echo $args['compact'] ? ' owbn-board-ballot--compact' : ''; ?>">
		<?php if ( empty( $votes ) ) : ?>
			<p class="owbn-board-ballot__empty"><?php esc_html_e( 'No open votes right now.', 'owbn-board' ); ?></p>
		<?php else : ?>
			<div class="owbn-board-ballot__cards">
				<?php foreach ( $votes as $vote ) : ?>
					<?php owbn_board_ballot_render_card( $vote, $user_id, $args['compact'] ); ?>
				<?php endforeach; ?>
			</div>

			<?php if ( ! $args['compact'] && $user_id ) : ?>
				<div class="owbn-board-ballot__submit-all">
					<button type="button" class="button button-primary button-large owbn-board-ballot__submit-all-btn">
						<?php esc_html_e( 'Submit All Votes', 'owbn-board' ); ?>
					</button>
					<span class="owbn-board-ballot__remaining" aria-live="polite"></span>
				</div>
			<?php endif; ?>

			<?php if ( $args['compact'] && $args['show_cta'] ) : ?>
				<p class="owbn-board-ballot__cta">
					<a href="<?php echo esc_url( owbn_board_tool_url( 'wpvp', '/ballot/' ) ); ?>" target="_blank" rel="noopener">
						<?php esc_html_e( 'View full ballot →', 'owbn-board' ); ?>
					</a>
				</p>
			<?php endif; ?>

			<?php if ( ! $user_id ) : ?>
				<p class="owbn-board-ballot__login-prompt">
					<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="button">
						<?php esc_html_e( 'Log in to vote', 'owbn-board' ); ?>
					</a>
				</p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Render a single vote card.
 */
function owbn_board_ballot_render_card( $vote, $user_id, $compact = false ) {
	$state    = owbn_board_ballot_card_state( $vote, $user_id );
	$options  = owbn_board_ballot_decode_options( $vote );
	$type     = (string) ( $vote['voting_type'] ?? '' ) ?: 'singleton';
	$eligible = $user_id && owbn_board_ballot_user_is_eligible( $vote, $user_id );

	$vote_id        = (int) ( $vote['id'] ?? 0 );
	$proposal_name  = (string) ( $vote['proposal_name'] ?? '' );
	$proposal_desc  = (string) ( $vote['proposal_description'] ?? '' );
	$opening_date   = (string) ( $vote['opening_date'] ?? '' );
	$closing_date   = (string) ( $vote['closing_date'] ?? '' );

	$type_label = owbn_board_ballot_type_label( $type );
	$open_date  = $opening_date ? wp_date( get_option( 'date_format' ), strtotime( $opening_date ) ) : '';
	$close_date = $closing_date ? wp_date( get_option( 'date_format' ), strtotime( $closing_date ) ) : '';
	?>
	<article class="owbn-board-ballot__card owbn-board-ballot__card--<?php echo esc_attr( $state ); ?>" data-vote-id="<?php echo $vote_id; ?>" data-vote-type="<?php echo esc_attr( $type ); ?>">
		<div class="owbn-board-ballot__card-header">
			<span class="owbn-board-ballot__type-badge"><?php echo esc_html( $type_label ); ?></span>
			<h3 class="owbn-board-ballot__title"><?php echo esc_html( $proposal_name ); ?></h3>
		</div>

		<?php if ( ! $compact && '' !== $proposal_desc ) : ?>
			<div class="owbn-board-ballot__description">
				<?php echo wp_kses_post( wp_trim_words( wp_strip_all_tags( $proposal_desc ), 30 ) ); ?>
			</div>
		<?php endif; ?>

		<div class="owbn-board-ballot__dates">
			<?php if ( $open_date ) : ?>
				<span><?php printf( esc_html__( 'Opens: %s', 'owbn-board' ), esc_html( $open_date ) ); ?></span>
			<?php endif; ?>
			<?php if ( $close_date ) : ?>
				<span><?php printf( esc_html__( 'Closes: %s', 'owbn-board' ), esc_html( $close_date ) ); ?></span>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $options ) ) : ?>
			<div class="owbn-board-ballot__options">
				<?php if ( 'open-not-voted' === $state && $eligible && ! $compact ) : ?>
					<?php owbn_board_ballot_render_controls( $vote, $options, $type ); ?>
				<?php elseif ( 'open-voted' === $state ) : ?>
					<div class="owbn-board-ballot__voted">
						<span class="owbn-board-ballot__voted-badge">✓ <?php esc_html_e( 'Voted', 'owbn-board' ); ?></span>
						<?php if ( $eligible && ! $compact ) : ?>
							<button type="button" class="button-link owbn-board-ballot__change-vote"><?php esc_html_e( 'Change vote', 'owbn-board' ); ?></button>
						<?php endif; ?>
					</div>
				<?php else : ?>
					<ul class="owbn-board-ballot__candidates">
						<?php foreach ( $options as $option ) : ?>
							<li>
								<?php if ( ! empty( $option['post_id'] ) ) : ?>
									<a href="<?php echo esc_url( get_permalink( (int) $option['post_id'] ) ); ?>" target="_blank" rel="noopener">
										<?php echo esc_html( $option['text'] ?? '' ); ?>
									</a>
								<?php else : ?>
									<?php echo esc_html( $option['text'] ?? '' ); ?>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( 'closed' === $state ) : ?>
			<div class="owbn-board-ballot__actions">
				<a class="button button-small" href="<?php echo esc_url( owbn_board_tool_url( 'wpvp', '/wp-admin/admin.php?page=wpvp-results&vote_id=' . $vote_id ) ); ?>">
					<?php esc_html_e( 'View Results', 'owbn-board' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</article>
	<?php
}

/**
 * Render the voting controls appropriate to the voting type.
 */
function owbn_board_ballot_render_controls( $vote, $options, $type ) {
	$vote_id = (int) ( $vote['id'] ?? 0 );

	if ( in_array( $type, [ 'rcv', 'irv', 'sequential_rcv', 'stv', 'condorcet' ], true ) ) {
		// Rank-based: dropdowns for each rank position
		$n = min( count( $options ), 5 );
		?>
		<div class="owbn-board-ballot__rank">
			<p class="owbn-board-ballot__rank-label"><?php esc_html_e( 'Rank your choices:', 'owbn-board' ); ?></p>
			<?php for ( $i = 1; $i <= $n; $i++ ) : ?>
				<select class="owbn-board-ballot__rank-select" name="rank_<?php echo (int) $i; ?>" data-rank="<?php echo (int) $i; ?>">
					<option value=""><?php printf( esc_html__( '%d. Select…', 'owbn-board' ), (int) $i ); ?></option>
					<?php foreach ( $options as $option ) : ?>
						<option value="<?php echo esc_attr( $option['text'] ?? '' ); ?>">
							<?php echo esc_html( $option['text'] ?? '' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php endfor; ?>
		</div>
		<?php
	} else {
		// FPTP / singleton / disciplinary / consent_agenda: radios
		?>
		<div class="owbn-board-ballot__radios">
			<?php foreach ( $options as $i => $option ) : ?>
				<label class="owbn-board-ballot__radio-label">
					<input type="radio" name="vote_<?php echo $vote_id; ?>" value="<?php echo esc_attr( $option['text'] ?? '' ); ?>" />
					<?php echo esc_html( $option['text'] ?? '' ); ?>
				</label>
			<?php endforeach; ?>
		</div>
		<?php
	}
}

/**
 * Human label for voting_type.
 */
function owbn_board_ballot_type_label( $type ) {
	$labels = [
		'singleton'      => __( 'FPTP', 'owbn-board' ),
		'fptp'           => __( 'FPTP', 'owbn-board' ),
		'rcv'            => __( 'RCV', 'owbn-board' ),
		'irv'            => __( 'IRV', 'owbn-board' ),
		'sequential_rcv' => __( 'Seq RCV', 'owbn-board' ),
		'stv'            => __( 'STV', 'owbn-board' ),
		'condorcet'      => __( 'Condorcet', 'owbn-board' ),
		'consent_agenda' => __( 'Consent', 'owbn-board' ),
		'disciplinary'   => __( 'Disciplinary', 'owbn-board' ),
	];
	return $labels[ $type ] ?? strtoupper( $type );
}
