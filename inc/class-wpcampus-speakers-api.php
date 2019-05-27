<?php

/**
 * Our speakers API class.
 */
final class WPCampus_Speakers_API {

	/**
	 * We don't need to instantiate this class.
	 */
	protected function __construct() {}

	/**
	 * Register our API routes.
	 */
	public static function register() {
		$plugin = new self();

		// Hide our rest routes.
		add_filter( 'rest_route_data', array( $plugin, 'filter_rest_route_data' ), 10, 2 );

		// Restrict access to our API routes.
		add_filter( 'rest_authentication_errors', array( $plugin, 'restrict_api_access' ) );

		// Register custom routes.
		add_action( 'rest_api_init', array( $plugin, 'register_rest_routes' ) );

	}

	/**
	 * Returns true if route matches one of our sessions routes.
	 */
	public function is_sessions_route( $route ) {
		return preg_match( '/^\/wpcampus\/data\/(proposal|profile|sessions)/i', $route );
	}

	/**
	 * Hide all of our session-related rest routes from being seen.
	 */
	public function filter_rest_route_data( $available, $routes ) {
		foreach ( $available as $route => $route_data ) {
			if ( $this->is_sessions_route( $route ) ) {
				unset( $available[ $route ] );
			}
		}
		return $available;
	}

	/**
	 * Restrict access to our speakers routes.
	 *
	 * @param   $result - WP_Error|null|bool WP_Error - If authentication error,
	 *    null if authentication method wasn't used, true if authentication succeeded.
	 * @return  WP_Error|null|bool - the result.
	 */
	public function restrict_api_access( $result ) {

		// Get the current route.
		$rest_route = $GLOBALS['wp']->query_vars['rest_route'];

		if ( empty( $rest_route ) || ! $this->is_sessions_route( $rest_route ) ) {
			return $result;
		}

		// Make sure the access request matches.
		if ( ! empty( $_SERVER['HTTP_WPC_ACCESS'] ) ) {
			if ( get_option( 'http_wpc_access' ) === $_SERVER['HTTP_WPC_ACCESS'] ) {
				return true;
			}
		}

		return new WP_Error(
			'rest_cannot_access',
			esc_html__( 'Only authenticated requests can access this REST API route.', 'wpcampus-speakers' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Register our custom REST routes.
	 */
	public function register_rest_routes() {

		// Get all WPCampus proposals.
		register_rest_route( 'wpcampus', '/data/proposal/', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_proposals' ),
		));

		// Get a specific proposal
		register_rest_route( 'wpcampus', '/data/proposal/(?P<id>\d+)', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_proposals' ),
			'args' => array(
				'id' => array(
					'validate_callback' => function( $param, $request, $key ) {
						return is_numeric( $param );
					},
				),
			),
		));

		// Get all WPCampus profiles.
		register_rest_route( 'wpcampus', '/data/profile/', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_profiles' ),
		));

		// Get a specific profile.
		register_rest_route( 'wpcampus', '/data/profile/(?P<id>\d+)', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_profiles' ),
			'args' => array(
				'id' => array(
					'validate_callback' => function( $param, $request, $key ) {
						return is_numeric( $param );
					},
				),
			),
		));
	}

	/**
	 * Respond with our proposals.
	 */
	public function get_proposals( $request ) {

		// Build proposal args.
		$args = array(
			'get_profiles' => true,
			'get_subjects' => true,
		);

		if ( ! empty( $request['id'] ) ) {
			$args['p'] = (int) $request['id'];
		}

		if ( ! empty( $request['post__in'] ) ) {
			$args['post__in'] = $request['post__in'];
		}

		if ( ! empty( $_GET['proposal_speaker'] ) && is_numeric( $_GET['proposal_speaker'] ) ) {
			$args['proposal_speaker'] = sanitize_text_field( $_GET['proposal_speaker'] );
		}

		if ( ! empty( $_GET['proposal_status'] ) ) {
			$args['proposal_status'] = sanitize_text_field( $_GET['proposal_status'] );
		}

		if ( ! empty( $_GET['proposal_event'] ) ) {
			$args['proposal_event'] = sanitize_text_field( $_GET['proposal_event'] );
		}

		if ( ! empty( $_GET['get_feedback'] ) ) {
			$args['get_feedback'] = sanitize_text_field( $_GET['get_feedback'] );
		}

		if ( ! empty( $_GET['get_headshot'] ) ) {
			$args['get_headshot'] = sanitize_text_field( $_GET['get_headshot'] );
		}

		if ( ! empty( $_GET['subjects'] ) ) {
			$args['subjects'] = sanitize_text_field( $_GET['subjects'] );
		}

		if ( ! empty( $_GET['by_profile'] ) ) {
			$args['by_profile'] = $_GET['by_profile'];

			// Make sure it's an array.
			if ( ! is_array( $args['by_profile'] ) ) {
				$args['by_profile'] = explode( ',', str_replace( ' ', '', $args['by_profile'] ) );
			}

			// Make sure they're IDs.
			$args['by_profile'] = array_filter( $args['by_profile'], 'is_numeric' );

		}

		// Build the response with the proposals.
		$response = wpcampus_speakers()->get_proposals( $args );

		// If no response, return an error.
		if ( false === $response ) {
			return new WP_Error( 'wpcampus', __( 'This data set is either invalid or does not contain information.', 'wpcampus-speakers' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $response );
	}

	/**
	 * Respond with our profiles.
	 */
	public function get_profiles( WP_REST_Request $request ) {

		// Build profile args.
		$args = array();

		if ( ! empty( $request['id'] ) ) {
			$args['p'] = (int) $request['id'];
		}

		if ( ! empty( $_GET['by_proposal'] ) ) {

			$args['by_proposal'] = $_GET['by_proposal'];

			// Make sure it's an array.
			if ( ! is_array( $args['by_proposal'] ) ) {
				$args['by_proposal'] = explode( ',', str_replace( ' ', '', $args['by_proposal'] ) );
			}

			// Make sure they're IDs.
			$args['by_proposal'] = array_filter( $args['by_proposal'], 'is_numeric' );

		}

		if ( ! empty( $_GET['profile_user'] ) && is_numeric( $_GET['profile_user'] ) ) {
			$args['profile_user'] = sanitize_text_field( $_GET['profile_user'] );
		}

		if ( ! empty( $_GET['proposal_event'] ) && is_numeric( $_GET['proposal_event'] ) ) {
			$args['proposal_event'] = sanitize_text_field( $_GET['proposal_event'] );
		}

		if ( ! empty( $_GET['proposal_status'] ) ) {
			$args['proposal_status'] = sanitize_text_field( $_GET['proposal_status'] );
		}

		if ( ! empty( $_GET['get_headshot'] ) ) {
			$args['get_headshot'] = sanitize_text_field( $_GET['get_headshot'] );
		}

		if ( ! empty( $_GET['get_proposals'] ) ) {
			$args['get_proposals'] = sanitize_text_field( $_GET['get_proposals'] );
		}

		if ( ! empty( $_GET['get_feedback'] ) ) {
			$args['get_feedback'] = sanitize_text_field( $_GET['get_feedback'] );
		}

		$response = wpcampus_speakers()->get_profiles( $args );

		// If no response, return an error.
		if ( false === $response ) {
			return new WP_Error( 'wpcampus', __( 'This data set is either invalid or does not contain information.', 'wpcampus-speakers' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $response );
	}
}
WPCampus_Speakers_API::register();
