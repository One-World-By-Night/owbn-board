<?php
/**
 * Activity Feed tile. Contributors add items via owbn_board_activity_items
 * filter; each item: [id, title, url, timestamp, icon, priority, source, category].
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_activity_register_tile() {
	owbn_board_register_tile( [
		'id'         => 'board:activity',
		'title'      => __( 'Activity Feed (Pending Development)', 'owbn-board' ),
		'icon'       => 'dashicons-rss',
		'read_roles' => [ 'chronicle/*/*', 'coordinator/*/*', 'exec/*/*' ],
		'size'       => '1x2',
		'category'   => 'communication',
		'priority'   => 20,
		'poll_interval' => 15000,
		'render'     => 'owbn_board_render_activity_tile',
	] );
}

function owbn_board_render_activity_tile( $tile, $user_id, $can_write ) {
	$roles = owbn_board_get_user_roles( $user_id );
	$since = time() - ( 7 * DAY_IN_SECONDS );

	// Contract: contributors MUST self-filter items for the target user — no post-filter role validation.
	$items = apply_filters( 'owbn_board_activity_items', [], $user_id, $roles, $since );

	usort( $items, function ( $a, $b ) {
		$a_time = is_numeric( $a['timestamp'] ?? 0 ) ? (int) $a['timestamp'] : strtotime( (string) ( $a['timestamp'] ?? '' ) );
		$b_time = is_numeric( $b['timestamp'] ?? 0 ) ? (int) $b['timestamp'] : strtotime( (string) ( $b['timestamp'] ?? '' ) );
		return $b_time - $a_time;
	} );

	$items = array_slice( $items, 0, 20 );

	if ( empty( $items ) ) {
		echo '<p class="owbn-board-activity__empty">' . esc_html__( 'No recent activity.', 'owbn-board' ) . '</p>';
		return;
	}

	echo '<ul class="owbn-board-activity">';
	foreach ( $items as $item ) {
		$title = $item['title'] ?? '';
		$url   = $item['url'] ?? '';
		$icon  = $item['icon'] ?? 'dashicons-admin-post';
		$time  = is_numeric( $item['timestamp'] ?? 0 ) ? (int) $item['timestamp'] : strtotime( (string) ( $item['timestamp'] ?? '' ) );
		$ago   = $time ? human_time_diff( $time, time() ) . ' ' . __( 'ago', 'owbn-board' ) : '';
		?>
		<li class="owbn-board-activity__item">
			<span class="owbn-board-activity__icon <?php echo esc_attr( $icon ); ?>"></span>
			<a href="<?php echo esc_url( $url ); ?>" class="owbn-board-activity__title"><?php echo esc_html( $title ); ?></a>
			<span class="owbn-board-activity__time"><?php echo esc_html( $ago ); ?></span>
		</li>
		<?php
	}
	echo '</ul>';
}
