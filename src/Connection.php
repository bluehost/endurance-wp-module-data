<?php

namespace Endurance\WP\Module\Data;

/**
 * Manages a Hub connection instance
 */
class Connection {

	/**
	 * Hub API url
	 *
	 * @var string
	 */
	private $api;

	/**
	 * Authentication token for data api
	 *
	 * @var string
	 */
	private $token;

	/**
	 * Construct
	 */
	public function __construct() {

		if ( ! defined( 'BH_HUB_URL' ) ) {
			define( 'BH_HUB_URL', 'https://hub.wpteamhub.com/api' );
		}

		$this->api = BH_HUB_URL;

		if ( ! $this->is_connected() ) {
			$this->connect();
		} else {
			$this->register_hooks();
		}
	}

	/**
	 * Register all event hooks
	 *
	 * @return void
	 */
	public function register_hooks() {

	}

	/**
	 * Check whether site has established connection to hub
	 *
	 * @return boolean
	 */
	public function is_connected() {
		$this->token = get_option( 'bh_data_token' );
		return (bool) ( $this->token );
	}

	/**
	 * Attempt to connect to hub
	 *
	 * @return void
	 */
	public function connect() {

		if ( ! get_transient( 'bh_data_connection_throttle' ) ) {

			set_transient( 'bh_data_connection_throttle', true, 30 * MINUTE_IN_SECONDS );

			$token = md5( wp_generate_password() );
			set_transient( 'bh_data_verify_token', $token, 5 * MINUTE_IN_SECONDS );

			$data                 = $this->get_core_data();
			$data['verify_token'] = $token;

			$args = array(
				'body'    => wp_json_encode( $data ),
				'headers' => array(
					'Content-Type' => 'applicaton/json',
					'Accept'       => 'applicaton/json',
				),
				'timeout' => 30,
			);

			$response = wp_remote_post( $this->api . '/connect', $args );
			$status   = wp_remote_retrieve_response_code( $response );

			// Created = 201; Updated = 200
			if ( 201 === $status || 200 === $status ) {
				$body = json_decode( wp_remote_retrieve_body( $response ) );
				if ( ! empty( $body->token ) ) {
					$encryption      = new Encryption();
					$encrypted_token = $encryption->encrypt( $body->token );
					update_option( 'bh_data_token', $encrypted_token );
				}
			}
		}
	}

	/**
	 * Get core site data
	 *
	 * @return array
	 */
	public function get_core_data() {
		global $wpdb, $wp_version;

		return array(
			'url'   => get_site_url(),
			'php'   => phpversion(),
			'mysql' => $wpdb->db_version(),
			'wp'    => $wp_version,
		);
	}
}
