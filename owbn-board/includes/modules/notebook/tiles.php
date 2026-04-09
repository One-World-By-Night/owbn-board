<?php
/**
 * Shared group notebook scoped by ASC role path or by Share Level override.
 * When Share Level is unset, falls back to the legacy "best matching role" picker.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_notebook_register_tile() {
	// Staff-only by default; admins broaden via Tile Access.
	$staff_patterns = [
		'chronicle/*/cm',
		'chronicle/*/hst',
		'chronicle/*/staff',
		'coordinator/*/*',
		'exec/*/*',
	];

	owbn_board_register_tile( [
		'id'                   => 'board:notebook',
		'title'                => __( 'Shared Notebook', 'owbn-board' ),
		'icon'                 => 'dashicons-welcome-write-blog',
		'read_roles'           => $staff_patterns,
		'write_roles'          => $staff_patterns,
		'size'                 => '2x2',
		'category'             => 'communication',
		'priority'             => 5,
		'supports_share_level' => true,
		'render'               => 'owbn_board_render_notebook_tile',
	] );
}

// Legacy picker: top-level category weight × 10 + segment count, with
// alphabetical tiebreak so the pick is stable across requests regardless
// of owc_asc_get_user_roles() ordering.
function owbn_board_notebook_resolve_role_path( $user_id ) {
	$roles = owbn_board_get_user_roles( $user_id );
	if ( empty( $roles ) ) {
		return null;
	}

	$priority = [ 'exec' => 3, 'coordinator' => 2, 'chronicle' => 1 ];
	$scored   = [];

	foreach ( $roles as $role ) {
		$parts   = explode( '/', $role );
		$top     = $parts[0] ?? '';
		$score   = ( $priority[ $top ] ?? 0 ) * 10 + count( $parts );
		$scored[] = [ 'role' => $role, 'score' => $score ];
	}

	usort(
		$scored,
		function ( $a, $b ) {
			if ( $a['score'] !== $b['score'] ) {
				return $b['score'] - $a['score'];
			}
			return strcmp( $a['role'], $b['role'] );
		}
	);

	return $scored[0]['role'];
}

function owbn_board_notebook_resolve_scope_groups( $user_id ) {
	if ( function_exists( 'owbn_board_tile_access_resolve_scope' ) ) {
		$groups = owbn_board_tile_access_resolve_scope( 'board:notebook', $user_id );
		if ( ! empty( $groups ) ) {
			return $groups;
		}
	}

	$legacy = owbn_board_notebook_resolve_role_path( $user_id );
	return $legacy ? [ $legacy ] : [];
}

// Read-only fetch — avoids creating empty rows for passive viewers.
function owbn_board_notebook_get( $role_path ) {
	global $wpdb;

	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}owbn_board_notebooks WHERE role_path = %s AND site_id = 0 LIMIT 1",
			$role_path
		)
	);
}

// Creates on first write; use only on writer paths, not passive reads.
function owbn_board_notebook_get_or_create( $role_path ) {
	$existing = owbn_board_notebook_get( $role_path );
	if ( $existing ) {
		return $existing;
	}

	global $wpdb;

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

function owbn_board_render_notebook_tile( $tile, $user_id, $can_write ) {
	$groups = owbn_board_notebook_resolve_scope_groups( $user_id );

	if ( empty( $groups ) ) {
		echo '<p>' . esc_html__( 'No matching group found for your roles.', 'owbn-board' ) . '</p>';
		return;
	}

	// First group is the initial active; JS swaps on switcher change.
	$active_group = $groups[0];

	// Writers materialize the row so the editor has a notebook_id to save against.
	if ( $can_write ) {
		$notebook = owbn_board_notebook_get_or_create( $active_group );
		if ( ! $notebook ) {
			echo '<p>' . esc_html__( 'Could not load notebook.', 'owbn-board' ) . '</p>';
			return;
		}
	} else {
		$notebook = owbn_board_notebook_get( $active_group );
		if ( ! $notebook ) {
			// Read-only empty state, no DB row created.
			?>
			<div class="owbn-board-notebook owbn-board-notebook--empty"
				data-role-path="<?php echo esc_attr( $active_group ); ?>"
				data-groups="<?php echo esc_attr( wp_json_encode( $groups ) ); ?>">
				<?php if ( count( $groups ) > 1 ) : ?>
					<div class="owbn-board-notebook__group-switcher">
						<label class="screen-reader-text" for="owbn-board-notebook-group-empty-<?php echo esc_attr( sanitize_html_class( $active_group ) ); ?>">
							<?php esc_html_e( 'Select notebook group', 'owbn-board' ); ?>
						</label>
						<select
							id="owbn-board-notebook-group-empty-<?php echo esc_attr( sanitize_html_class( $active_group ) ); ?>"
							class="owbn-board-notebook__group-select">
							<?php foreach ( $groups as $group ) : ?>
								<option value="<?php echo esc_attr( $group ); ?>" <?php selected( $group, $active_group ); ?>>
									<?php echo esc_html( $group ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>
				<div class="owbn-board-notebook__meta">
					<span class="owbn-board-notebook__scope"><?php echo esc_html( $active_group ); ?></span>
				</div>
				<p class="owbn-board-notebook__empty-message">
					<?php esc_html_e( 'This notebook is empty.', 'owbn-board' ); ?>
				</p>
			</div>
			<?php
			return;
		}
	}

	$updated_by      = $notebook->updated_by ? get_userdata( $notebook->updated_by ) : null;
	$updated_by_name = $updated_by ? $updated_by->display_name : __( 'unknown', 'owbn-board' );
	$has_multiple    = count( $groups ) > 1;
	?>
	<div class="owbn-board-notebook"
		data-notebook-id="<?php echo esc_attr( $notebook->id ); ?>"
		data-role-path="<?php echo esc_attr( $active_group ); ?>"
		data-groups="<?php echo esc_attr( wp_json_encode( $groups ) ); ?>">

		<?php if ( $has_multiple ) : ?>
			<div class="owbn-board-notebook__group-switcher">
				<label class="screen-reader-text" for="owbn-board-notebook-group-<?php echo esc_attr( $notebook->id ); ?>">
					<?php esc_html_e( 'Select notebook group', 'owbn-board' ); ?>
				</label>
				<select
					id="owbn-board-notebook-group-<?php echo esc_attr( $notebook->id ); ?>"
					class="owbn-board-notebook__group-select">
					<?php foreach ( $groups as $group ) : ?>
						<option value="<?php echo esc_attr( $group ); ?>" <?php selected( $group, $active_group ); ?>>
							<?php echo esc_html( $group ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php endif; ?>

		<div class="owbn-board-notebook__meta">
			<span class="owbn-board-notebook__scope"><?php echo esc_html( $active_group ); ?></span>
			<span class="owbn-board-notebook__updated">
				<?php
				printf(
					/* translators: 1: user display name, 2: relative time */
					esc_html__( 'Updated by %1$s %2$s', 'owbn-board' ),
					esc_html( $updated_by_name ),
					esc_html( human_time_diff( strtotime( $notebook->updated_at ), time() ) . ' ' . __( 'ago', 'owbn-board' ) )
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
