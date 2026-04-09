<?php
/**
 * Search tile — single input that dispatches to all search providers.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_search_register_tile() {
	owbn_board_register_tile( [
		'id'         => 'board:search',
		'title'      => __( 'Universal Search', 'owbn-board' ),
		'icon'       => 'dashicons-search',
		'read_roles' => [ 'chronicle/*/*', 'coordinator/*/*', 'exec/*/*' ],
		'size'       => '2x1',
		'category'   => 'reference',
		'priority'   => 1,
		'render'     => 'owbn_board_render_search_tile',
	] );
}

function owbn_board_render_search_tile( $tile, $user_id, $can_write ) {
	?>
	<div class="owbn-board-search">
		<input type="search" class="owbn-board-search__input" placeholder="<?php esc_attr_e( 'Search everything… (Ctrl+K)', 'owbn-board' ); ?>" />
		<div class="owbn-board-search__results" aria-live="polite"></div>
	</div>
	<?php
}
