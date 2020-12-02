<?php

namespace Endurance\WP\Module\Data\API;

use Endurance\WP\Module\Data\Event as DataEvent;
use Endurance\WP\Module\Data\HubConnection;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Response;
use WP_Error;

/**
 * REST API controller for verifying a hub connection attempt
 */
class Event extends WP_REST_Controller {

	/**
	 * Instance of the EventManager class
	 *
	 * @var EventManager
	 */
	public $manager;

	/**
	 * Constructor.
	 *
	 * @param EventManager $hub Instance of the Event manager
	 * @since 1.4
	 */
	public function __construct( EventManager $manager ) {
		$this->manager   = $manager;
		$this->namespace = 'bluehost/v1/data';
		$this->rest_base = 'event';
	}

	/**
	 * Registers the routes for the objects of the controller.
	 *
	 * @since 1.4
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

	}

	/**
	 * Returns a verification of the supplied connection token
	 *
	 * @since 1.4
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {
		if ( empty( $request['key'] ) ) {
			return new WP_Error(
				'bh_data_key_required',
				__( 'Event key is required.' ),
				array( 'status' => 400 )
			);
		} else {
			$key = sanitize_title( $request['key'] );
		}
		$cat  = ( ! empty( $request['category'] ) ) ? sanitize_title( $request['category'] ) : 'Admin';
		$data = ( ! empty( $request['data'] ) ) ? $request['data'] : null;

		$event = new DataEvent( $cat, $key, $data );

		$this->manager->push( $event );

		return new WP_REST_Response(
			array(
				'category' => $cat,
				'key'      => $key,
				'data'     => $data,
			)
		);
	}

	/**
	 * Just required to be logged in for this endpoint
	 *
	 * @since 1.4
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( 'read' ) ) {
			return new WP_Error(
				'rest_cannot_log_event',
				__( 'Sorry, you are not allowed to use this endpoint.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}
}
