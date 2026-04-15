<?php
/**
 * Resources tile — two sections: recent articles and curated links.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_resources_register_tile() {
	owbn_board_register_tile( [
		'id'         => 'board:resources',
		'title'      => __( 'Resources', 'owbn-board' ),
		'icon'       => 'dashicons-book',
		'read_roles' => [], // visible to all authenticated users
		'size'       => '2x2',
		'category'   => 'reference',
		'priority'   => 28,
		'poll_interval' => 15000,
		'render'     => 'owbn_board_render_resources_tile',
	] );
}

function owbn_board_render_resources_tile( $tile, $user_id, $can_write ) {
	$articles = owbn_board_resources_get_articles( 5 );
	$links    = owbn_board_resources_get_links( 8 );
	?>
	<div class="owbn-board-resources">
		<?php if ( ! empty( $articles ) ) : ?>
			<div class="owbn-board-resources__section">
				<h4 class="owbn-board-resources__section-title"><?php esc_html_e( 'Articles', 'owbn-board' ); ?></h4>
				<ul class="owbn-board-resources__articles">
					<?php foreach ( $articles as $article ) : ?>
						<li>
							<a href="<?php echo esc_url( get_permalink( $article ) ); ?>">
								<strong><?php echo esc_html( get_the_title( $article ) ); ?></strong>
							</a>
							<?php
							$excerpt = get_the_excerpt( $article );
							if ( $excerpt ) :
								?>
								<div class="owbn-board-resources__excerpt"><?php echo esc_html( wp_trim_words( $excerpt, 20 ) ); ?></div>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $links ) ) : ?>
			<div class="owbn-board-resources__section">
				<h4 class="owbn-board-resources__section-title"><?php esc_html_e( 'Links', 'owbn-board' ); ?></h4>
				<ul class="owbn-board-resources__links">
					<?php foreach ( $links as $link ) : ?>
						<li>
							<a href="<?php echo esc_url( $link->url ); ?>" target="_blank" rel="noopener">
								<?php echo esc_html( $link->title ); ?>
							</a>
							<?php if ( ! empty( $link->description ) ) : ?>
								<div class="owbn-board-resources__link-desc"><?php echo esc_html( wp_trim_words( $link->description, 15 ) ); ?></div>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( empty( $articles ) && empty( $links ) ) : ?>
			<p class="owbn-board-resources__empty"><?php esc_html_e( 'No resources yet.', 'owbn-board' ); ?></p>
		<?php endif; ?>

		<?php if ( owbn_board_user_can_manage() ) : ?>
			<p class="owbn-board-resources__manage">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=owbn_resource' ) ); ?>"><?php esc_html_e( 'Manage articles →', 'owbn-board' ); ?></a>
				&nbsp;|&nbsp;
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=owbn-board-resource-links' ) ); ?>"><?php esc_html_e( 'Manage links →', 'owbn-board' ); ?></a>
			</p>
		<?php endif;
	?>
	</div>
	<?php
}
