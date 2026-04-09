<?php
/**
 * Resources module — custom post type for articles.
 *
 * Articles use WordPress's native post editor. No custom admin UI.
 * Links live in a separate custom table (see schema.php / admin.php).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the owbn_resource CPT and its taxonomy.
 */
function owbn_board_resources_register_cpt() {
	register_post_type( 'owbn_resource', [
		'labels' => [
			'name'               => __( 'Resources', 'owbn-board' ),
			'singular_name'      => __( 'Resource Article', 'owbn-board' ),
			'add_new'            => __( 'Add New', 'owbn-board' ),
			'add_new_item'       => __( 'Add New Article', 'owbn-board' ),
			'edit_item'          => __( 'Edit Article', 'owbn-board' ),
			'new_item'           => __( 'New Article', 'owbn-board' ),
			'view_item'          => __( 'View Article', 'owbn-board' ),
			'search_items'       => __( 'Search Articles', 'owbn-board' ),
			'not_found'          => __( 'No articles found', 'owbn-board' ),
			'not_found_in_trash' => __( 'No articles in trash', 'owbn-board' ),
			'menu_name'          => __( 'Resource Articles', 'owbn-board' ),
		],
		'public'              => true,
		'show_ui'             => true,
		'show_in_menu'        => 'owbn-board',
		'show_in_rest'        => true,
		'has_archive'         => true,
		'rewrite'             => [ 'slug' => 'resources' ],
		'supports'            => [ 'title', 'editor', 'excerpt', 'author', 'thumbnail', 'revisions' ],
		'taxonomies'          => [ 'owbn_resource_category', 'owbn_resource_tag' ],
		'menu_icon'           => 'dashicons-book',
	] );

	register_taxonomy( 'owbn_resource_category', [ 'owbn_resource' ], [
		'labels' => [
			'name'          => __( 'Resource Categories', 'owbn-board' ),
			'singular_name' => __( 'Category', 'owbn-board' ),
			'menu_name'     => __( 'Categories', 'owbn-board' ),
		],
		'hierarchical'      => true,
		'show_ui'           => true,
		'show_in_rest'      => true,
		'show_admin_column' => true,
		'rewrite'           => [ 'slug' => 'resource-category' ],
	] );

	register_taxonomy( 'owbn_resource_tag', [ 'owbn_resource' ], [
		'labels' => [
			'name'          => __( 'Resource Tags', 'owbn-board' ),
			'singular_name' => __( 'Tag', 'owbn-board' ),
			'menu_name'     => __( 'Tags', 'owbn-board' ),
		],
		'hierarchical'      => false,
		'show_ui'           => true,
		'show_in_rest'      => true,
		'show_admin_column' => true,
		'rewrite'           => [ 'slug' => 'resource-tag' ],
	] );
}

add_filter( 'rest_pre_insert_owbn_resource', 'owbn_board_resources_rest_pre_insert_guard', 10, 2 );
function owbn_board_resources_rest_pre_insert_guard( $prepared_post, $request ) {
	if ( ! owbn_board_user_can_manage() ) {
		return new WP_Error( 'rest_forbidden', __( 'Only site administrators can create or edit resource articles.', 'owbn-board' ), [ 'status' => 403 ] );
	}
	return $prepared_post;
}
