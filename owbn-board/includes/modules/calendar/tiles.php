<?php
/**
 * Calendar tile. Contributors add events via owbn_board_calendar_events filter;
 * each event: [id, title, start, end, url, category, color, all_day].
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_calendar_register_tile() {
	owbn_board_register_tile( [
		'id'         => 'board:calendar',
		'title'      => __( 'Calendar', 'owbn-board' ),
		'icon'       => 'dashicons-calendar-alt',
		'read_roles' => [ 'chronicle/*/*', 'coordinator/*/*', 'exec/*/*' ],
		'size'       => '2x2',
		'category'   => 'reference',
		'priority'   => 15,
		'render'     => 'owbn_board_render_calendar_tile',
	] );
}

function owbn_board_render_calendar_tile( $tile, $user_id, $can_write ) {
	$roles  = owbn_board_get_user_roles( $user_id );
	$from   = time();
	$to     = $from + ( 30 * DAY_IN_SECONDS );
	$events = apply_filters( 'owbn_board_calendar_events', [], $user_id, $roles, $from, $to );

	usort( $events, function ( $a, $b ) {
		$a_time = is_numeric( $a['start'] ?? 0 ) ? (int) $a['start'] : strtotime( (string) ( $a['start'] ?? '' ) );
		$b_time = is_numeric( $b['start'] ?? 0 ) ? (int) $b['start'] : strtotime( (string) ( $b['start'] ?? '' ) );
		return $a_time - $b_time;
	} );

	$events = array_slice( $events, 0, 30 );

	if ( empty( $events ) ) {
		echo '<p class="owbn-board-calendar__empty">' . esc_html__( 'No upcoming events in the next 30 days.', 'owbn-board' ) . '</p>';
		return;
	}

	// Render filter panel (hidden by default, toggled by button)
	owbn_board_calendar_render_filter_panel( $user_id );

	echo '<ul class="owbn-board-calendar">';
	foreach ( $events as $event ) {
		$title   = $event['title'] ?? '';
		$url     = $event['url'] ?? '';
		$start   = is_numeric( $event['start'] ?? 0 ) ? (int) $event['start'] : strtotime( (string) ( $event['start'] ?? '' ) );
		$all_day = ! empty( $event['all_day'] );
		$cat     = $event['category'] ?? '';
		?>
		<li class="owbn-board-calendar__item" data-start="<?php echo esc_attr( $start ); ?>" data-all-day="<?php echo $all_day ? '1' : '0'; ?>">
			<div class="owbn-board-calendar__date">
				<strong class="owbn-board-calendar__date-label" data-format="date"><?php echo esc_html( wp_date( 'M j', $start ) ); ?></strong>
				<?php if ( ! $all_day ) : ?>
					<span class="owbn-board-calendar__time-label" data-format="time"><?php echo esc_html( wp_date( 'g:ia', $start ) ); ?></span>
				<?php endif; ?>
			</div>
			<div class="owbn-board-calendar__body">
				<a href="<?php echo esc_url( $url ); ?>" class="owbn-board-calendar__title"><?php echo esc_html( $title ); ?></a>
				<?php if ( $cat ) : ?>
					<span class="owbn-board-calendar__category"><?php echo esc_html( $cat ); ?></span>
				<?php endif; ?>
			</div>
		</li>
		<?php
	}
	echo '</ul>';
}

/**
 * Render the per-user filter panel for the calendar tile.
 * Filters are stored in user meta via AJAX (handled by chronicle-manager).
 */
function owbn_board_calendar_render_filter_panel( $user_id ) {
	$filters = owbn_board_calendar_get_user_filters( $user_id );
	$sel_genres = (array) $filters['genres'];
	$sel_days   = (array) $filters['days'];
	$sel_types  = (array) $filters['session_types'];
	$sel_mode   = $filters['chronicles_mode'];
	$my_slugs   = owbn_board_calendar_user_chronicle_slugs( $user_id );

	$all_genres = get_option( 'owbn_genre_list', [] );
	$all_days   = [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ];
	$all_types  = [ 'Game', 'OOC Social Meetup', 'Other' ];
	?>
	<div class="owbn-board-calendar__filters">
		<button type="button" class="owbn-board-calendar__filters-toggle button-link"><?php esc_html_e( 'Filters', 'owbn-board' ); ?> &#9662;</button>
		<div class="owbn-board-calendar__filters-panel" hidden>
			<?php if ( ! empty( $my_slugs ) ) : ?>
				<fieldset>
					<legend><?php esc_html_e( 'Chronicles', 'owbn-board' ); ?></legend>
					<label><input type="radio" name="chronicles_mode" value="mine" <?php checked( 'mine', $sel_mode ); ?> /> <?php esc_html_e( 'My chronicles only', 'owbn-board' ); ?></label>
					<label><input type="radio" name="chronicles_mode" value="all" <?php checked( 'all', $sel_mode ); ?> /> <?php esc_html_e( 'All chronicles', 'owbn-board' ); ?></label>
				</fieldset>
			<?php endif; ?>
			<?php if ( ! empty( $all_genres ) ) : ?>
				<fieldset>
					<legend><?php esc_html_e( 'Genres', 'owbn-board' ); ?></legend>
					<?php foreach ( $all_genres as $genre ) : ?>
						<label><input type="checkbox" name="genres" value="<?php echo esc_attr( $genre ); ?>" <?php checked( in_array( $genre, $sel_genres, true ) ); ?> /> <?php echo esc_html( $genre ); ?></label>
					<?php endforeach; ?>
				</fieldset>
			<?php endif; ?>
			<fieldset>
				<legend><?php esc_html_e( 'Days', 'owbn-board' ); ?></legend>
				<?php foreach ( $all_days as $d ) : ?>
					<label><input type="checkbox" name="days" value="<?php echo esc_attr( $d ); ?>" <?php checked( in_array( $d, $sel_days, true ) ); ?> /> <?php echo esc_html( $d ); ?></label>
				<?php endforeach; ?>
			</fieldset>
			<fieldset>
				<legend><?php esc_html_e( 'Session Types', 'owbn-board' ); ?></legend>
				<?php foreach ( $all_types as $t ) : ?>
					<label><input type="checkbox" name="session_types" value="<?php echo esc_attr( $t ); ?>" <?php checked( in_array( $t, $sel_types, true ) ); ?> /> <?php echo esc_html( $t ); ?></label>
				<?php endforeach; ?>
			</fieldset>
			<button type="button" class="button owbn-board-calendar__filters-save"><?php esc_html_e( 'Save Filters', 'owbn-board' ); ?></button>
		</div>
	</div>
	<?php
}
