<?php
/**
 * Pinned Links tile — personal bookmarks.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_pinned_links_register_tile() {
	owbn_board_register_tile( [
		'id'          => 'board:pinned-links',
		'title'       => __( 'Pinned Links', 'owbn-board' ),
		'icon'        => 'dashicons-admin-links',
		'read_roles'  => [],
		'write_roles' => [],
		'size'        => '1x2',
		'category'    => 'reference',
		'priority'    => 30,
		'render'      => 'owbn_board_render_pinned_links_tile',
	] );
}

function owbn_board_render_pinned_links_tile( $tile, $user_id, $can_write ) {
	$links = owbn_board_pinned_links_get( $user_id );
	?>
	<div class="owbn-board-pins">
		<?php if ( $can_write ) : ?>
			<form class="owbn-board-pins__form">
				<input type="text" class="owbn-board-pins__label" placeholder="<?php esc_attr_e( 'Label', 'owbn-board' ); ?>" maxlength="100" />
				<input type="url" class="owbn-board-pins__url" placeholder="<?php esc_attr_e( 'https://…', 'owbn-board' ); ?>" />
				<button type="submit" class="button"><?php esc_html_e( 'Pin', 'owbn-board' ); ?></button>
			</form>
		<?php endif; ?>

		<ul class="owbn-board-pins__list">
			<?php if ( empty( $links ) ) : ?>
				<li class="owbn-board-pins__empty"><?php esc_html_e( 'No pinned links yet.', 'owbn-board' ); ?></li>
			<?php else : ?>
				<?php foreach ( $links as $link ) : ?>
					<li class="owbn-board-pins__item" data-pin-id="<?php echo esc_attr( $link['id'] ?? '' ); ?>">
						<a href="<?php echo esc_url( $link['url'] ?? '' ); ?>" class="owbn-board-pins__link"><?php echo esc_html( $link['label'] ?? '' ); ?></a>
						<?php if ( $can_write ) : ?>
							<button type="button" class="owbn-board-pins__remove" aria-label="<?php esc_attr_e( 'Remove pin', 'owbn-board' ); ?>">&times;</button>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			<?php endif; ?>
		</ul>
	</div>
	<?php
}
