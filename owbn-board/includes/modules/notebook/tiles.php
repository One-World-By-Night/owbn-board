<?php
/**
 * Notebook tile — shared group notebook scoped by accessSchema role path
 * or by an admin-configured Share Level (tile-access module).
 *
 * Scope resolution:
 *   1. If the tile-access module has Share Level set for this tile, the user
 *      gets one notebook per matching scope group (chronicle/mckn,
 *      coordinator/sabbat, etc.). A group selector at the top of the tile
 *      switches between them.
 *   2. Otherwise fall back to the legacy "best matching role" picker —
 *      the user lands on the notebook for their highest-priority role path.
 *
 * The role_path column in owbn_board_notebooks is an opaque string, so
 * it works for both full role paths ("chronicle/mckn/hst") and group keys
 * ("chronicle/mckn"). Existing legacy notebooks remain readable whenever
 * Share Level is unset.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_notebook_register_tile() {
	// Registered defaults are staff-only. The "Shared Notebook" is a staff
	// collaboration tool; players get no shared notebook by default. Admins
	// who want a per-tier player notebook can broaden these patterns via
	// OWBN Board > Tile Access.
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

/**
 * Legacy "best matching role" picker — used only when no Share Level
 * override is set for the notebook tile.
 *
 * Scoring: top-level category weight × 10 + segment count. On ties
 * (multiple roles share the top score), break alphabetically by role
 * path so the choice is deterministic across requests. Without this
 * tiebreak, the chosen role would depend on whatever order
 * owc_asc_get_user_roles() happened to return them in, which is not
 * a stable contract.
 */
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

	// Highest score wins; alphabetical by role path for deterministic ties.
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

/**
 * Resolve the list of scope group keys the user should see notebooks for.
 * Honors Share Level first; falls back to the legacy single-role picker.
 *
 * @return string[] Ordered list of role_path keys, possibly empty.
 */
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

/**
 * Fetch a notebook for a given role path without creating it. Returns
 * null if no row exists. Use this on read-only render paths to avoid
 * polluting the table with empty rows for passive viewers.
 */
function owbn_board_notebook_get( $role_path ) {
	global $wpdb;

	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}owbn_board_notebooks WHERE role_path = %s AND site_id = 0 LIMIT 1",
			$role_path
		)
	);
}

/**
 * Load or create a notebook for a given role path (cross-site: site_id = 0).
 * Use this only on paths where creating is intentional (writers rendering
 * the editor, or the save AJAX handler when a new group gets its first
 * write). Read-only paths should use owbn_board_notebook_get() instead.
 */
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

	// Active group: first in the list for initial render. Client-side JS
	// swaps the notebook body when the user picks a different group.
	$active_group = $groups[0];

	// Writers materialize the row on first view so the editor has a real
	// notebook_id to save against. Read-only viewers use get-only to avoid
	// creating empty rows for every role_path a tile happens to surface.
	if ( $can_write ) {
		$notebook = owbn_board_notebook_get_or_create( $active_group );
		if ( ! $notebook ) {
			echo '<p>' . esc_html__( 'Could not load notebook.', 'owbn-board' ) . '</p>';
			return;
		}
	} else {
		$notebook = owbn_board_notebook_get( $active_group );
		if ( ! $notebook ) {
			// Render a minimal read-only empty state with the scope visible
			// but no DB row created. If the group is later written by
			// someone with write access, the row will exist on the next view.
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
