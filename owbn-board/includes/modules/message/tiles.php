<?php
/**
 * Group Messages tile — chat scoped to a shared group key (top + chronicle/office).
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_message_register_tile() {
	owbn_board_register_tile( [
		'id'                   => 'board:message',
		'title'                => __( 'Group Messages', 'owbn-board' ),
		'icon'                 => 'dashicons-format-chat',
		'read_roles'           => [ 'chronicle/*/hst', 'chronicle/*/cm', 'chronicle/*/staff', 'coordinator/*/coordinator', 'coordinator/*/sub-coordinator', 'exec/*/coordinator' ],
		'write_roles'          => [ 'chronicle/*/hst', 'chronicle/*/cm', 'chronicle/*/staff', 'coordinator/*/coordinator', 'coordinator/*/sub-coordinator', 'exec/*/coordinator' ],
		'size'                 => '1x2',
		'category'             => 'communication',
		'priority'             => 10,
		'supports_share_level' => true,
		'poll_interval'        => 15000,
		'render'               => 'owbn_board_render_message_tile',
	] );
}

// Returns all staff-tier group keys for the user (e.g. chronicle/mckn, exec/web).
// Filters out player-tier roles. Used by both render and the AJAX save/load auth checks.
function owbn_board_message_resolve_scope_groups( $user_id ) {
	$roles = owbn_board_get_user_roles( $user_id );
	if ( empty( $roles ) ) {
		return [];
	}

	$groups   = [];
	$patterns = [
		'chronicle/*/hst', 'chronicle/*/cm', 'chronicle/*/staff',
		'coordinator/*/coordinator', 'coordinator/*/sub-coordinator',
		'exec/*/coordinator',
	];

	foreach ( $patterns as $pattern ) {
		foreach ( $roles as $role ) {
			if ( ! owbn_board_pattern_matches( $pattern, $role ) ) {
				continue;
			}
			$parts = explode( '/', $role );
			if ( count( $parts ) >= 2 ) {
				$key = $parts[0] . '/' . $parts[1];
				if ( ! in_array( $key, $groups, true ) ) {
					$groups[] = $key;
				}
			}
		}
	}

	sort( $groups, SORT_STRING );
	return $groups;
}

function owbn_board_message_resolve_scope_key( $user_id ) {
	$roles = owbn_board_get_user_roles( $user_id );
	if ( empty( $roles ) ) {
		return null;
	}

	$priority = [ 'exec' => 3, 'coordinator' => 2, 'chronicle' => 1 ];
	$scored   = [];

	foreach ( $roles as $role ) {
		$parts = explode( '/', (string) $role );
		if ( count( $parts ) < 2 ) {
			continue;
		}
		$top = $parts[0];
		if ( ! isset( $priority[ $top ] ) ) {
			continue;
		}
		$group_key = $parts[0] . '/' . $parts[1];
		$scored[]  = [
			'key'   => $group_key,
			'score' => $priority[ $top ],
		];
	}

	if ( empty( $scored ) ) {
		return null;
	}

	usort(
		$scored,
		function ( $a, $b ) {
			if ( $a['score'] !== $b['score'] ) {
				return $b['score'] - $a['score'];
			}
			return strcmp( $a['key'], $b['key'] );
		}
	);

	return $scored[0]['key'];
}

function owbn_board_render_message_tile( $tile, $user_id, $can_write ) {
	$groups = owbn_board_message_resolve_scope_groups( $user_id );

	if ( empty( $groups ) ) {
		echo '<p>' . esc_html__( 'No matching group found for your roles.', 'owbn-board' ) . '</p>';
		return;
	}

	$active = $groups[0];
	?>
	<div class="owbn-board-message" data-active-scope="<?php echo esc_attr( $active ); ?>">
		<?php owbn_board_render_scope_switcher( $groups, $active, __( 'Switch message group', 'owbn-board' ) ); ?>

		<?php if ( $can_write ) : ?>
			<form class="owbn-board-message__form">
				<textarea class="owbn-board-message__input" rows="2" placeholder="<?php esc_attr_e( 'Post a message to your group…', 'owbn-board' ); ?>" maxlength="2000"></textarea>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Post', 'owbn-board' ); ?></button>
			</form>
		<?php endif; ?>

		<?php foreach ( $groups as $group ) :
			$is_active = ( $group === $active );
			?>
			<div class="owbn-board-message__panel<?php echo $is_active ? ' is-active' : ''; ?>" data-scope="<?php echo esc_attr( $group ); ?>">
				<?php owbn_board_render_message_panel( $group, $user_id ); ?>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}

function owbn_board_render_message_panel( $group, $user_id ) {
	$messages   = function_exists( 'owc_board_messages_list' ) ? owc_board_messages_list( $group, 20 ) : [];
	$user_email = '';
	if ( $user_id ) {
		$user       = get_userdata( $user_id );
		$user_email = $user ? $user->user_email : '';
	}
	?>
	<div class="owbn-board-message__feed">
		<?php if ( empty( $messages ) ) : ?>
			<p class="owbn-board-message__empty"><?php esc_html_e( 'No messages yet.', 'owbn-board' ); ?></p>
		<?php else : ?>
			<?php foreach ( $messages as $msg ) :
				$msg        = (array) $msg;
				$owner      = isset( $msg['owner_email'] ) ? $msg['owner_email'] : '';
				$author     = owbn_board_message_display_name_for( $owner );
				$ago        = human_time_diff( strtotime( $msg['created_at'] ), time() ) . ' ' . __( 'ago', 'owbn-board' );
				$can_delete = ( $owner === $user_email ) || owbn_board_user_can_manage();
				?>
				<div class="owbn-board-message__item" data-message-id="<?php echo esc_attr( $msg['id'] ); ?>">
					<div class="owbn-board-message__meta">
						<strong class="owbn-board-message__author"><?php echo esc_html( $author ); ?></strong>
						<span class="owbn-board-message__time"><?php echo esc_html( $ago ); ?></span>
						<?php if ( $can_delete ) : ?>
							<button type="button" class="owbn-board-message__delete" aria-label="<?php esc_attr_e( 'Delete message', 'owbn-board' ); ?>">&times;</button>
						<?php endif; ?>
					</div>
					<div class="owbn-board-message__body"><?php echo esc_html( $msg['content'] ); ?></div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<?php
}

// Resolve a display name for a message author by email. Falls back to email local-part.
function owbn_board_message_display_name_for( $email ) {
	if ( ! $email ) {
		return __( 'unknown', 'owbn-board' );
	}
	$user = get_user_by( 'email', $email );
	if ( $user ) {
		return $user->display_name;
	}
	$at = strpos( $email, '@' );
	return false !== $at ? substr( $email, 0, $at ) : $email;
}
