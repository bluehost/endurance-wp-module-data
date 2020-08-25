<?php

use Endurance\WP\Module\Data\API\Verification_Controller;
use Endurance\WP\Module\Data\Connection;

if ( function_exists( 'add_action' ) ) {
	add_action( 'after_setup_theme', 'eig_module_data_register' );
}

/**
 * Register the data module
 */
function eig_module_data_register() {
	eig_register_module(
		array(
			'name'     => 'data',
			'label'    => __( 'Data', 'endurance' ),
			'callback' => 'eig_module_data_load',
			'isActive' => true,
			'isHidden' => true,
		)
	);
}

/**
 * Load the data module
 */
function eig_module_data_load() {
	add_action( 'init', 'eig_module_data_init' );
	add_action( 'rest_api_init', 'eig_module_data_rest_init' );
}

/**
 * Initialize a hub connection
 *
 * @return void
 */
function eig_module_data_init() {
	$data = new Connection();
}

/**
 * Set up REST API routes
 *
 * @return void
 */
function eig_module_data_rest_init() {
	$controller = new Verification_Controller();
	$controller->register_routes();
}
