<?php

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/cpt.php';
require_once __DIR__ . '/fields.php';
require_once __DIR__ . '/admin-ui.php';
require_once __DIR__ . '/hooks.php';
require_once __DIR__ . '/webhook.php';
require_once __DIR__ . '/shortcode.php';
require_once __DIR__ . '/render-admin.php';
require_once __DIR__ . '/render-ui.php';

add_action( 'wp_enqueue_scripts', 'owbn_enqueue_chronicle_styles' );
add_action( 'admin_enqueue_scripts', 'owbn_enqueue_chronicle_styles' );

function owbn_enqueue_chronicle_styles() {
    wp_enqueue_style(
        'owbn-chronicle-css',
        plugin_dir_url( __FILE__ ) . 'assets/chronicle.css',
        [],
        '0.7.5'
    );
}