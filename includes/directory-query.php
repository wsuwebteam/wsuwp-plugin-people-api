<?php namespace WSUWP\Plugin\People\API;

class Directory_Query {

	/**
	 * create_organization
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public static function directory_path( \WP_REST_Request $request ) {

		$params         = $request->get_body_params();
		$directory_id   = $request['directory'] ? sanitize_text_field( $request['directory'] ) : false;
		$directory_path = array();

		if ( $directory_id ) {

			$directory_path = Directories::get_directory_path( $directory_id );

		}

		$directory_path = array_reverse( $directory_path );

		return new \WP_REST_Response( $directory_path, 200 );

	}

	public static function register_api_endpoints() {

		register_rest_route(
			'peopleapi/v1',
			'/editor/directory/path',
			array(
				'methods' => \WP_REST_Server::READABLE,
				'callback' => array( __CLASS__, 'directory_path' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'peopleapi/v1',
			'/directory/(?P<id>\d+)',
			array(
				'methods' => \WP_REST_Server::READABLE,
				'callback' => array( __CLASS__, 'get_directory' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'peopleapi/v1',
			'/directory/children/(?P<id>\d+)',
			array(
				'methods' => \WP_REST_Server::READABLE,
				'callback' => array( __CLASS__, 'get_directory_children' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'peopleapi/v1',
			'/directory/descendants/(?P<id>\d+)',
			array(
				'methods' => \WP_REST_Server::READABLE,
				'callback' => array( __CLASS__, 'get_directory_descendants' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'peopleapi/v1',
			'/directory/search',
			array(
				'methods' => \WP_REST_Server::READABLE,
				'callback' => array( __CLASS__, 'get_directory_search' ),
				'permission_callback' => '__return_true',
			)
		);

	}


	/**
	 * Get Directory
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public static function get_directory( \WP_REST_Request $request ) {

		$directory = array();

		$url_params     = $request->get_url_params();
		$params         = $request->get_body_params();

		$directory_id   = ( ! empty( $url_params['id'] ) ) ? $url_params['id'] : false;

		if ( $directory_id ) {

			$directory = Directories::get_directory( $directory_id );

		}

		return new \WP_REST_Response( $directory, 200 );

	}


	/**
	 * Get Directory
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public static function get_directory_children( \WP_REST_Request $request ) {

		$children = array();

		$url_params     = $request->get_url_params();

		$directory_id   = ( ! empty( $url_params['id'] ) ) ? $url_params['id'] : false;

		if ( $directory_id ) {

			$children = Directories::get_child_directories( $directory_id, array( 'inherit' => 'children' ) );

		}

		return new \WP_REST_Response( $children, 200 );

	}


	/**
	 * Get Directory
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public static function get_directory_descendants( \WP_REST_Request $request ) {

		$children = array();

		$url_params     = $request->get_url_params();

		$directory_id   = ( ! empty( $url_params['id'] ) ) ? $url_params['id'] : false;

		if ( $directory_id ) {

			$children = Directories::get_child_directories( $directory_id, array( 'inherit' => 'all' ) );

		}

		return new \WP_REST_Response( $children, 200 );

	}


	/**
	 * Get Directory
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public static function get_directory_search( \WP_REST_Request $request ) {


		$directories = array();

		$term = ( ! empty( $request['term'] ) ) ? $request['term'] : '';

		if ( ! empty( $term ) ) {

			$directories = Directories::get_directory_search( $term );

		}

		return new \WP_REST_Response( $directories, 200 );

	}


	public static function init() {

		add_action( 'rest_api_init', array( __CLASS__, 'register_api_endpoints' ) );

	}
}


Directory_Query::init();
