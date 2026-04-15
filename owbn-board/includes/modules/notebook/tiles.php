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
		'poll_interval'        => 15000,
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

// Staff-tier scope patterns for the shared notebook. Player roles never get
// a notebook scope — only roles in this list contribute groups.
function owbn_board_notebook_default_scope_patterns() {
	return [
		'chronicle/*/hst',
		'chronicle/*/cm',
		'chronicle/*/staff',
		'coordinator/*/coordinator',
		'coordinator/*/sub-coordinator',
		'exec/*/coordinator',
	];
}

function owbn_board_notebook_resolve_scope_groups( $user_id ) {
	if ( function_exists( 'owbn_board_tile_access_resolve_scope' ) ) {
		$groups = owbn_board_tile_access_resolve_scope( 'board:notebook', $user_id );
		if ( ! empty( $groups ) ) {
			return $groups;
		}
	}

	$user_roles = owbn_board_get_user_roles( $user_id );
	if ( empty( $user_roles ) ) {
		return [];
	}

	$groups = [];
	foreach ( owbn_board_notebook_default_scope_patterns() as $pattern ) {
		foreach ( $user_roles as $role ) {
			if ( ! owbn_board_pattern_matches( $pattern, $role ) ) {
				continue;
			}
			// Derive group key by stripping the trailing role tier from the pattern.
			// chronicle/*/hst + chronicle/mckn/hst → chronicle/mckn
			// exec/*/coordinator + exec/web/coordinator → exec/web
			$pat_parts  = explode( '/', $pattern );
			$role_parts = explode( '/', $role );
			array_pop( $pat_parts ); // drop the trailing tier (hst, cm, coordinator, etc.)

			$group = [];
			foreach ( $pat_parts as $i => $seg ) {
				$group[] = ( '*' === $seg ) ? ( $role_parts[ $i ] ?? '' ) : $seg;
			}
			$key = implode( '/', array_filter( $group, 'strlen' ) );
			if ( '' !== $key && ! in_array( $key, $groups, true ) ) {
				$groups[] = $key;
			}
		}
	}

	sort( $groups, SORT_STRING );
	return $groups;
}

// Read-only fetch via wrapper. Returns assoc array (cross-site).
function owbn_board_notebook_get( $scope ) {
	if ( function_exists( 'owc_board_notebook_get' ) ) {
		return owc_board_notebook_get( $scope, '', false );
	}
	return null;
}

// Materializes the row on first write.
function owbn_board_notebook_get_or_create( $scope ) {
	$user  = wp_get_current_user();
	$email = $user && $user->user_email ? $user->user_email : '';
	if ( function_exists( 'owc_board_notebook_get' ) ) {
		return owc_board_notebook_get( $scope, $email, true );
	}
	return null;
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

	$notebook = (array) $notebook;
	$nb_id           = isset( $notebook['id'] ) ? (int) $notebook['id'] : 0;
	$nb_content      = isset( $notebook['content'] ) ? (string) $notebook['content'] : '';
	$nb_updated_at   = isset( $notebook['updated_at'] ) ? (string) $notebook['updated_at'] : current_time( 'mysql' );
	$nb_updated_email = isset( $notebook['updated_by_email'] ) ? (string) $notebook['updated_by_email'] : '';
	$updated_user    = $nb_updated_email ? get_user_by( 'email', $nb_updated_email ) : null;
	$updated_by_name = $updated_user ? $updated_user->display_name : ( $nb_updated_email ?: __( 'unknown', 'owbn-board' ) );
	$has_multiple    = count( $groups ) > 1;
	?>
	<div class="owbn-board-notebook"
		data-notebook-id="<?php echo esc_attr( $nb_id ); ?>"
		data-role-path="<?php echo esc_attr( $active_group ); ?>"
		data-groups="<?php echo esc_attr( wp_json_encode( $groups ) ); ?>">

		<?php if ( $has_multiple ) : ?>
			<div class="owbn-board-notebook__group-switcher">
				<label class="screen-reader-text" for="owbn-board-notebook-group-<?php echo esc_attr( $nb_id ); ?>">
					<?php esc_html_e( 'Select notebook group', 'owbn-board' ); ?>
				</label>
				<select
					id="owbn-board-notebook-group-<?php echo esc_attr( $nb_id ); ?>"
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
					esc_html( human_time_diff( strtotime( $nb_updated_at ), time() ) . ' ' . __( 'ago', 'owbn-board' ) )
				);
				?>
			</span>
			<span class="owbn-board-notebook__status" aria-live="polite"></span>
		</div>

		<?php if ( $can_write ) : ?>
			<?php
			wp_editor(
				$nb_content,
				'owbn_board_notebook_' . $nb_id,
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
				<?php echo wp_kses_post( $nb_content ); ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
