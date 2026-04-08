<?php
/**
 * Events module — custom post type + custom statuses for approval workflow.
 *
 * Custom statuses map to the approval pipeline:
 *   draft (WP built-in)  → organizer working on it, only creator sees
 *   pending (WP built-in)→ submitted for review
 *   publish (WP built-in)→ approved and live
 *   rejected (custom)    → sent back with feedback
 *   cancelled (custom)   → pulled by organizer
 *
 * We reuse WP's built-in draft/pending/publish for the happy path and add two
 * custom statuses for edge cases. This keeps WP admin integration smooth.
 */

defined( 'ABSPATH' ) || exit;

function owbn_board_events_register_cpt() {
	register_post_type( 'owbn_event', [
		'labels' => [
			'name'               => __( 'Events', 'owbn-board' ),
			'singular_name'      => __( 'Event', 'owbn-board' ),
			'add_new'            => __( 'Add New', 'owbn-board' ),
			'add_new_item'       => __( 'Add New Event', 'owbn-board' ),
			'edit_item'          => __( 'Edit Event', 'owbn-board' ),
			'new_item'           => __( 'New Event', 'owbn-board' ),
			'view_item'          => __( 'View Event', 'owbn-board' ),
			'search_items'       => __( 'Search Events', 'owbn-board' ),
			'not_found'          => __( 'No events found', 'owbn-board' ),
			'not_found_in_trash' => __( 'No events in trash', 'owbn-board' ),
			'menu_name'          => __( 'Events', 'owbn-board' ),
		],
		'public'              => true,
		'show_ui'             => true,
		'show_in_menu'        => 'owbn-board',
		'show_in_rest'        => false, // custom editor flow, not block editor
		'has_archive'         => true,
		'rewrite'             => [ 'slug' => 'events' ],
		'supports'            => [ 'title', 'editor', 'author', 'thumbnail', 'excerpt' ],
		'menu_icon'           => 'dashicons-megaphone',
		'capability_type'     => 'post',
	] );

	register_taxonomy( 'owbn_event_type', [ 'owbn_event' ], [
		'labels' => [
			'name'          => __( 'Event Types', 'owbn-board' ),
			'singular_name' => __( 'Event Type', 'owbn-board' ),
			'menu_name'     => __( 'Types', 'owbn-board' ),
		],
		'hierarchical'      => false,
		'show_ui'           => true,
		'show_admin_column' => true,
		'rewrite'           => [ 'slug' => 'event-type' ],
	] );

	// Custom post statuses for the approval pipeline.
	register_post_status( 'rejected', [
		'label'                     => _x( 'Rejected', 'post status', 'owbn-board' ),
		'public'                    => false,
		'exclude_from_search'       => true,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		/* translators: %s: post count */
		'label_count'               => _n_noop( 'Rejected <span class="count">(%s)</span>', 'Rejected <span class="count">(%s)</span>', 'owbn-board' ),
	] );

	register_post_status( 'cancelled', [
		'label'                     => _x( 'Cancelled', 'post status', 'owbn-board' ),
		'public'                    => false,
		'exclude_from_search'       => true,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		/* translators: %s: post count */
		'label_count'               => _n_noop( 'Cancelled <span class="count">(%s)</span>', 'Cancelled <span class="count">(%s)</span>', 'owbn-board' ),
	] );
}
