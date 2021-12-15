<?php

namespace Endurance\WP\Module\Data;

use wpscholar\Url;

/**
 * Main class for the data plugin module
 */
class Data {

	/**
	 * Hub Connection instance
	 *
	 * @var HubConnection
	 */
	public $hub;

	/**
	 * Last instantiated instance of this class.
	 *
	 * @var Data
	 */
	public static $instance;

	/**
	 * Data constructor.
	 */
	public function __construct() {
		self::$instance = $this;
	}

	/**
	 * Start up the plugin module
	 *
	 * Do this separately so it isn't tied to class creation
	 *
	 * @return void
	 */
	public function start() {

		// Delays our primary module setup until init
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'rest_authentication_errors', array( $this, 'authenticate' ) );

	}

	/**
	 * Initialize all other module functionality
	 *
	 * @return void
	 */
	public function init() {

		$this->hub = new HubConnection();

		$manager = new EventManager();
		$manager->initialize_rest_endpoint();

		// If not connected, attempt to connect and
		// bail before registering the subscribers/listeners
		if ( ! $this->hub::is_connected() ) {

			// Initialize the required verification endpoints
			$this->hub->register_verification_hooks();

			// Attempt to connect
			if ( ! $this->hub->is_throttled() ) {
				$this->hub->connect();
			}

			return;
		}

		$manager->init();

		$manager->add_subscriber( $this->hub );

		if ( defined( 'BH_DATA_DEBUG' ) && BH_DATA_DEBUG ) {
			$this->logger = new Logger();
			$manager->add_subscriber( $this->logger );
		}

	}

	/**
	 * Authenticate incoming REST API requests.
	 *
	 * @param bool|null|\WP_Error $status
	 *
	 * @return bool|null|\WP_Error
	 */
	public function authenticate( $status ) {

		// Make sure there wasn't a different authentication method used before this
		if ( ! is_null( $status ) ) {
			return $status;
		}

		// Make sure this is a REST API request
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return $status;
		}

		// If no auth header included, bail to allow a different auth method
		if ( empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			return null;
		}

		$token = str_replace( 'Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'] );

		$data = [
			'method'    => $_SERVER['REQUEST_METHOD'],
			'url'       => Url::getCurrentUrl(),
			'body'      => file_get_contents( 'php://input' ),
			'timestamp' => data_get( getallheaders(), 'X-Timestamp' ),
		];

		$hash = hash( 'sha256', wp_json_encode( $data ) );
		$salt = hash( 'sha256', strrev( HubConnection::get_auth_token() ) );

		$is_valid = hash( 'sha256', $hash . $salt ) === $token;

		// Allow access if token is valid
		if ( $is_valid ) {

			if ( isset( $_GET['user_id'] ) ) {

				// If a user ID is provided, use it to find the desired user.
				$user = get_user_by( 'id', filter_input( INPUT_GET, 'user_id', FILTER_SANITIZE_NUMBER_INT ) );

			} else {

				// If no user ID is provided, find the first admin user.
				$admins = get_users( array( 'role' => 'administrator' ) );
				$user   = array_shift( $admins );

			}

			if ( ! empty( $user ) && is_a( $user, \WP_User::class ) ) {
				wp_set_current_user( $user->id );

				return true;
			}
		}

		// Don't return false, since we could be interfering with a basic auth implementation.
		return null;

	}

}
