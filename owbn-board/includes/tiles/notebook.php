<?php
/**
 * Notebook tile — shared group notebook scoped by accessSchema role path.
 *
 * Phase 1 feature set:
 *   - TinyMCE editor via wp_editor()
 *   - Autosave via AJAX (notebook-save.php)
 *   - One notebook per role_path (deterministic lookup)
 *   - Content stored as HTML, sanitized with wp_kses_post
 *
 * Deferred to later phases:
 *   - Edit lock indicator
 *   - History viewer with restore
 *   - Templates
 *   - @mention parsing
 *   - File attach beyond default WP media
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the notebook tile on the standard tile registration hook.
 */
add_action( 'owbn_board_register_tiles', function () {
	owbn_board_register_tile( [
		'id'          => 'board:notebook',
		'title'       => __( 'Shared Notebook', 'owbn-board' ),
		'icon'        => 'dashicons-welcome-write-blog',
		'read_roles'  => [
			'chronicle/*/*',
			'coordinator/*/*',
			'exec/*',
		],
		'write_roles' => [
			'chronicle/*/*',
			'coordinator/*/*',
			'exec/*',
		],
		'size'        => '2x2',
		'category'    => 'communication',
		'priority'    => 5,
		'render'      => 'owbn_board_render_notebook_tile',
	] );
} );

/**
 * Determine which notebook a given user sees.
 * Picks the most-specific matching role path from the user's roles.
 *
 * For now: picks the first role path that has both a group scope and a position
 * (e.g. chronicle/mckn/staff). A user with multiple roles gets the one with
 * the highest priority (exec > coordinator > chronicle) and most depth.
 *
 * @param int $user_id
 * @return string|null Role path or null if no matching role
 */
function owbn_board_notebook_resolve_role_path( $user_id ) {
	$roles = owbn_board_get_user_roles( $user_id );
	if ( empty( $roles ) ) {
		return null;
	}

	$priority = [ 'exec' => 3, 'coordinator' => 2, 'chronicle' => 1 ];
	$best     = null;
	$best_score = -1;

	foreach ( $roles as $role ) {
		$parts = explode( '/', $role );
		$top   = $parts[0] ?? '';
		$score = ( $priority[ $top ] ?? 0 ) * 10 + count( $parts );
		if ( $score > $best_score ) {
			$best_score = $score;
			$best       = $role;
		}
	}

	return $best;
}

/**
 * Load or create a notebook for a given role path (cross-site: site_id = 0).
 *
 * @param string $role_path
 * @return object|null
 */
function owbn_board_notebook_get_or_create( $role_path ) {
	global $wpdb;

	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}owbn_board_notebooks WHERE role_path = %s AND site_id = 0 LIMIT 1",
			$role_path
		)
	);

	if ( $row ) {
		return $row;
	}

	$wpdb->insert(
		$wpdb->prefix . 'owbn_board_notebooks',
		[
			'role_path'  => $role_path,
			'site_id'    => 0,
			'title'      => '',
			'content'    => '',
			'updated_at' => current_time( 'mysql' ),
			'updated_by' => get_current_user_id(),
		],
		[ '%s', '%d', '%s', '%s', '%s', '%d' ]
	);

	$new_id = (int) $wpdb->insert_id;
	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}owbn_board_notebooks WHERE id = %d",
			$new_id
		)
	);
}

/**
 * Render the notebook tile body.
 *
 * @param array $tile
 * @param int   $user_id
 * @param bool  $can_write
 */
function owbn_board_render_notebook_tile( $tile, $user_id, $can_write ) {
	$role_path = owbn_board_notebook_resolve_role_path( $user_id );

	if ( ! $role_path ) {
		echo '<p>' . esc_html__( 'No matching group found for your roles.', 'owbn-board' ) . '</p>';
		return;
	}

	$notebook = owbn_board_notebook_get_or_create( $role_path );
	if ( ! $notebook ) {
		echo '<p>' . esc_html__( 'Could not load notebook.', 'owbn-board' ) . '</p>';
		return;
	}

	$updated_by = $notebook->updated_by ? get_userdata( $notebook->updated_by ) : null;
	$updated_by_name = $updated_by ? $updated_by->display_name : __( 'unknown', 'owbn-board' );
	?>
	<div class="owbn-board-notebook" data-notebook-id="<?php echo esc_attr( $notebook->id ); ?>" data-role-path="<?php echo esc_attr( $role_path ); ?>">
		<div class="owbn-board-notebook__meta">
			<span class="owbn-board-notebook__scope"><?php echo esc_html( $role_path ); ?></span>
			<span class="owbn-board-notebook__updated">
				<?php
				printf(
					/* translators: 1: user display name, 2: relative time */
					esc_html__( 'Updated by %1$s %2$s', 'owbn-board' ),
					esc_html( $updated_by_name ),
					esc_html( human_time_diff( strtotime( $notebook->updated_at ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'owbn-board' ) )
				);
				?>
			</span>
			<span class="owbn-board-notebook__status" aria-live="polite"></span>
		</div>

		<?php if ( $can_write ) : ?>
			<?php
			wp_editor(
				$notebook->content,
				'owbn_board_notebook_' . $notebook->id,
				[
					'textarea_name' => 'owbn_board_notebook_content',
					'textarea_rows' => 12,
					'media_buttons' => true,
					'teeny'         => false,
					'quicktags'     => true,
					'tinymce'       => [
						'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,wp_more,forecolor,table',
						'toolbar2' => '',
					],
				]
			);
			?>
		<?php else : ?>
			<div class="owbn-board-notebook__readonly">
				<?php echo wp_kses_post( $notebook->content ); ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
