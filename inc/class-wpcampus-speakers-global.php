<?php
/**
 * The class that sets up
 * global plugin functionality.
 *
 * This class is initiated on every page
 * load and does not have to be instantiated.
 *
 * @class       WPCampus_Speakers_Global
 * @category    Class
 * @package     WPCampus Speakers
 */
final class WPCampus_Speakers_Global {

	/**
	 * We don't need to instantiate this class.
	 */
	protected function __construct() {}

	/**
	 * Registers all of our hooks and what not.
	 */
	public static function register() {
		$plugin = new self();

		// Hide our rest routes.
		add_filter( 'rest_route_data', array( $plugin, 'filter_rest_route_data' ), 10, 2 );

		// Restrict access to our API routes.
		add_filter( 'rest_authentication_errors', array( $plugin, 'restrict_api_access' ) );

		// Filters the REST query for the speaker post types.
		add_filter( 'rest_profile_query', array( $plugin, 'filter_profile_rest_query' ), 10, 2 );
		add_filter( 'rest_proposal_query', array( $plugin, 'filter_proposal_rest_query' ), 10, 2 );

		// Filters the post data for a response for the speaker post types.
		add_filter( 'rest_prepare_profile', array( $plugin, 'prepare_profile_rest_response' ), 10, 2 );
		add_filter( 'rest_prepare_proposal', array( $plugin, 'prepare_proposal_rest_response' ), 10, 2 );

		// Filter queries.
		add_filter( 'query_vars', array( $plugin, 'filter_query_vars' ) );
		add_filter( 'rest_profile_collection_params', array( $plugin, 'filter_rest_params' ), 10, 2 );
		add_filter( 'rest_proposal_collection_params', array( $plugin, 'filter_rest_params' ), 10, 2 );
		add_filter( 'posts_clauses', array( $plugin, 'filter_posts_clauses' ), 100, 2 );

		// Register our post types and taxonomies.
		add_action( 'init', array( $plugin, 'register_custom_post_types_taxonomies' ) );

		// Filter terms when retrieved.
		add_filter( 'get_terms', array( $plugin, 'filter_get_terms' ), 10, 4 );

		// Add rewrite rules.
		//add_action( 'init', array( $plugin, 'add_rewrite_rules' ) );

	}

	/**
	 * Returns true if route matches
	 * one of our speaker post types.
	 */
	public function is_speakers_route( $route, $post_type = null ) {
		if ( empty( $post_type ) ) {
			$post_type = '(proposal|profile)';
		}
		return preg_match( '/^\/wp(\/v2)?\/' . $post_type . '/i', $route );
	}

	/**
	 * Hide our rest routes from being seen.
	 */
	public function filter_rest_route_data( $available, $routes ) {

		// Remove routes for "proposal" and "profile".
		foreach ( $available as $route => $route_data ) {
			if ( $this->is_speakers_route( $route ) ) {
				unset( $available[ $route ] );
			}
		}

		return $available;
	}

	/**
	 * Restrict access to our speakers routes.
	 */
	public function restrict_api_access( $result ) {

		// Get the current route.
		$rest_route = $GLOBALS['wp']->query_vars['rest_route'];

		// Restrict access to our speakers routes.
		if ( ! empty( $rest_route ) && $this->is_speakers_route( $rest_route ) ) {

			// Make sure the access request matches.
			if ( ! empty( $_SERVER['HTTP_WPC_ACCESS'] ) ) {
				if ( get_option( 'http_wpc_access' ) === $_SERVER['HTTP_WPC_ACCESS'] ) {
					return true;
				}
			}

			return new WP_Error(
				'rest_cannot_access',
				esc_html__( 'Only authenticated requests can access this REST API route.', 'wpcampus' ),
				array( 'status' => 401 )
			);
		}

		return $result;
	}

	/**
	 * Add query vars to the whitelist.
	 */
	public function filter_query_vars( $query_vars ) {
		$query_vars[] = 'profile_user';
		$query_vars[] = 'by_proposal';
		$query_vars[] = 'proposal_event';
		$query_vars[] = 'proposal_speaker';
		$query_vars[] = 'proposal_status';
		return $query_vars;
	}

	/**
	 * Setup the profile rest query.
	 */
	public function filter_profile_rest_query( $args, $request ) {

		$args['post_status'] = 'publish';
		$args['posts_per_page'] = 100;
		$args['ignore_sticky_posts'] = true;

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

		return $args;
	}

	/**
	 * Setup the proposal rest query.
	 *
	 * NOTE: Don't set default proposal status here.
	 * We do that in the post clauses filter.
	 */
	public function filter_proposal_rest_query( $args, $request ) {

		$args['post_status'] = 'publish';
		$args['posts_per_page'] = 100;
		$args['ignore_sticky_posts'] = true;

		if ( ! empty( $_GET['proposal_speaker'] ) && is_numeric( $_GET['proposal_speaker'] ) ) {
			$args['proposal_speaker'] = sanitize_text_field( $_GET['proposal_speaker'] );
		}

		if ( ! empty( $_GET['proposal_status'] ) ) {
			$args['proposal_status'] = sanitize_text_field( $_GET['proposal_status'] );
		}

		return $args;
	}

	/**
	 * Prepare REST response for profiles.
	 */
	public function prepare_profile_rest_response( $response, $post ) {

		// We only want to keep specific data.
		$keys_to_keep = array( 'id', 'status', 'type', 'slug', 'link', 'content', 'excerpt', 'featured_media' );
		foreach ( $response->data as $key => $value ) {
			if ( ! in_array( $key, $keys_to_keep ) ) {
				unset( $response->data[ $key ] );
			}
		}

		// Add speaker data.
		$response = $this->prepare_speaker_rest_response( $response->data, $post->ID );

		return $response;
	}

	/**
	 * Prepare REST response for proposals.
	 */
	public function prepare_proposal_rest_response( $response, $post ) {

		// We only want to keep specific data.
		$keys_to_keep = array( 'id', 'status', 'type', 'slug', 'link', 'title', 'content', 'excerpt', 'featured_media', 'session_type' );
		foreach ( $response->data as $key => $value ) {
			if ( ! in_array( $key, $keys_to_keep ) ) {
				unset( $response->data[ $key ] );
			}
		}

		// Add proposal status.
		$proposal_status = wpcampus_speakers()->get_proposal_status( $post->ID );
		if ( ! empty( $proposal_status ) ) {
			$proposal_status = strtolower( preg_replace( '/([^a-z])/i', '', $proposal_status ) );
		}
		$response->data['proposal_status'] = ! empty( $proposal_status ) ? $proposal_status : null;

		// Get speaker(s) data.
		$response->data['speakers'] = array();

		if ( function_exists( 'have_rows' ) && have_rows( 'speakers', $post->ID ) ) {
			while ( have_rows( 'speakers', $post->ID ) ) {
				the_row();

				$speaker_id = intval( get_sub_field( 'speaker' ) );
				if ( $speaker_id > 0 ) {

					$speaker_data = array(
						'id' => $speaker_id,
						'content' => array(
							'rendered'  => wpautop( get_post_field( 'post_content', $speaker_id ) ),
						),
						'href' => rest_url( 'wp/v2/profile/' . $speaker_id ),
					);

					// Add to list of speakers.
					$response->data['speakers'][] = $this->prepare_speaker_rest_response( $speaker_data, $speaker_id );

				}
			}
		}

		// Get event(s).
		$response->data['events'] = array();
		$events = wp_get_object_terms( $post->ID, 'proposal_event' );
		if ( ! empty( $events ) ) {
			foreach ( $events as $event ) {
				$response->data['events'][] = array(
					'id'    => $event->term_id,
					'slug'  => $event->slug,
					'name'  => $event->name,
				);
			}
		}

		// Get subjects.
		$response->data['subjects'] = array();
		$subjects = wp_get_object_terms( $post->ID, 'subjects' );
		if ( ! empty( $subjects ) ) {
			foreach ( $subjects as $subject ) {
				$response->data['subjects'][] = array(
					'id'    => $subject->term_id,
					'slug'  => $subject->slug,
					'name'  => $subject->name,
				);
			}
		}

		// Get WPCampus video post ID.
		$session_video_id = wpcampus_speakers()->get_session_video( $post->ID );

		// Get the YouTube ID.
		$youtube_id = $session_video_id > 0 ? wpcampus_speakers()->get_video_youtube_id( $session_video_id ) : null;

		// Store the YouTube ID and URL.
		$response->data['session_video'] = ! empty( $youtube_id ) ? $youtube_id : null;
		$response->data['session_video_url'] = ! empty( $youtube_id ) ? wpcampus_speakers()->get_youtube_url( $youtube_id ) : null;

		return $response;
	}

	/**
	 * Add speaker data to a REST response.
	 */
	public function prepare_speaker_rest_response( $response, $speaker_id ) {
		global $wpdb;

		// Run a query to get all the meta data.
		$meta_fields = array( 'first_name', 'last_name', 'company', 'company_position', 'company_website', 'website', 'facebook', 'twitter', 'instagram', 'linkedin' );
		$profile_fields = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %s AND meta_key IN ('" . implode( "','", $meta_fields ) . "')", $speaker_id ) );

		// Build display name.
		$display_name = sanitize_text_field( get_post_meta( $speaker_id, 'display_name', true ) );

		// Add display name.
		$response['display_name'] = ! empty( $display_name ) ? $display_name : null;

		// If not defined, use first and last name.
		if ( ! $display_name ) {
			$first_name = '';
			$last_name = '';
			$have_first_name = false;
			$have_last_name = false;
			foreach ( $profile_fields as $meta ) {
				if ( $have_first_name && $have_last_name ) {
					break;
				}
				if ( 'first_name' == $meta->meta_key ) {
					$first_name = $meta->meta_value;
					$have_first_name = true;
				}
				if ( 'last_name' == $meta->meta_key ) {
					$last_name = $meta->meta_value;
					$have_last_name = true;
				}
			}

			// Build display name.
			$display_name = preg_replace( '/([\s]{2,})/', ' ', "{$first_name} {$last_name}" );
		}

		// If still no display name, use post title.
		if ( ! $display_name ) {
			$display_name = get_post_field( 'post_title', $speaker_id );
		}

		// Add display name as title.
		$response['title'] = array(
			'rendered' => ! empty( $display_name ) ? $display_name : null,
		);

		// Add custom data.
		foreach ( $profile_fields as $meta ) {
			$meta_value = sanitize_text_field( $meta->meta_value );
			$response[ $meta->meta_key ] = ! empty( $meta_value ) ? $meta_value : null;
		}

		// Add the headshot.
		$headshot = get_the_post_thumbnail_url( $speaker_id, 'thumbnail' );
		$response['headshot'] = ! empty( $headshot ) ? $headshot : null;

		return $response;
	}

	/**
	 * Filter the query params
	 * allowed by the REST API.
	 */
	public function filter_rest_params( $query_params, $post_type ) {

		switch ( $post_type->name ) {

			case 'profile':

				$query_params['profile_user'] = array(
					'description'   => __( 'Filter the profiles by the user assigned to the profile.', 'wpcampus' ),
					'type'          => 'integer',
				);

				$query_params['by_proposal'] = array(
					'description'   => __( 'Filter the profiles by the profile assigned to a proposal ID.', 'wpcampus' ),
					'type'          => 'integer',
				);

				$query_params['proposal_event'] = array(
					'description'   => __( 'Filter the profiles by the event assigned to their proposal.', 'wpcampus' ),
					'type'          => 'integer',
				);

				$query_params['proposal_status'] = array(
					'description'   => __( 'Filter the profiles by the selection status assigned to their proposal.', 'wpcampus' ),
					'type'          => 'string',
				);

				break;

			case 'proposal':

				$query_params['proposal_speaker'] = array(
					'description'   => __( 'Filter the proposals by their speaker.', 'wpcampus' ),
					'type'          => 'integer',
				);

				$query_params['proposal_status'] = array(
					'description'   => __( 'Filter the proposals by their selection status.', 'wpcampus' ),
					'type'          => 'string',
				);

				break;
		}

		return $query_params;
	}

	/**
	 * Filter queries.
	 */
	public function filter_posts_clauses( $pieces, $query ) {
		global $wpdb;

		$post_type = $query->get( 'post_type' );

		switch ( $post_type ) {

			case 'profile':

				// Only if we're querying by the profile user.
				$profile_user = $query->get( 'profile_user' );
				if ( ! empty( $profile_user ) && is_numeric( $profile_user ) ) {

					// "Join" to get profile user.
					$pieces['join'] .= " LEFT JOIN {$wpdb->postmeta} profile_user ON profile_user.post_id = {$wpdb->posts}.ID AND profile_user.meta_key = 'wordpress_user'";
					$pieces['where'] .= $wpdb->prepare( ' AND profile_user.meta_value = %s', $profile_user );

				}

				/*
				 * Set up query by proposal event.
				 */
				$proposal_event = $query->get( 'proposal_event' );
				if ( ! is_numeric( $proposal_event ) || ! $proposal_event ) {
					$proposal_event = 0;
				}

				/*
				 * Set up query by proposal status.
				 */
				$proposal_status = $query->get( 'proposal_status' );

				if ( ! $proposal_status && ! empty( $_GET['proposal_status'] ) ) {
					$proposal_status = sanitize_text_field( $_GET['proposal_status'] );
				} elseif ( ! $proposal_status ) {
					$proposal_status = null;
				}

				// By default, only get confirmed proposals.
				if ( empty( $proposal_status ) ) {

					// Don't filter in the admin.
					if ( ! is_admin() ) {
						$proposal_status = array( 'confirmed' );
					}
				} elseif ( ! is_array( $proposal_status ) ) {
					$proposal_status = explode( ',', str_replace( ' ', '', $proposal_status ) );
				}

				if ( ! empty( $proposal_status ) ) {
					$proposal_status = array_map( 'strtolower', $proposal_status );
				}

				/*
				 * Set up query by proposal ID(s).
				 */
				$by_proposal = $query->get( 'by_proposal' );

				if ( ! $by_proposal && ! empty( $_GET['by_proposal'] ) ) {
					$by_proposal = sanitize_text_field( $_GET['by_proposal'] );
				} elseif ( ! $by_proposal ) {
					$by_proposal = null;
				}

				if ( ! empty( $by_proposal ) ) {

					// Make sure its an array.
					if ( ! is_array( $by_proposal ) ) {
						$by_proposal = explode( ',', str_replace( ' ', '', $by_proposal ) );
					}

					// Make sure they're IDs.
					$by_proposal = array_filter( $by_proposal, 'is_numeric' );

				}

				if ( ! empty( $proposal_event ) || ! empty( $proposal_status ) || ! empty( $by_proposal ) ) {

					// Join to get proposal information.
					$pieces['join'] .= " INNER JOIN {$wpdb->postmeta} profile_sel ON profile_sel.meta_value = {$wpdb->posts}.ID AND profile_sel.meta_key REGEXP '^speakers\_([0-9]+)\_speaker$'";
					$pieces['join'] .= " INNER JOIN {$wpdb->posts} proposal ON proposal.ID = profile_sel.post_id AND proposal.post_type = 'proposal' AND proposal.post_status = 'publish'";

					// Join by event.
					if ( ! empty( $proposal_event ) ) {
						$pieces['join'] .= " INNER JOIN {$wpdb->term_relationships} proposal_event_rel ON proposal_event_rel.object_id = proposal.ID";
						$pieces['join'] .= $wpdb->prepare( " INNER JOIN {$wpdb->term_taxonomy} proposal_event_tax ON proposal_event_tax.term_taxonomy_id = proposal_event_rel.term_taxonomy_id AND proposal_event_tax.taxonomy = 'proposal_event' AND proposal_event_tax.term_id = %s", $proposal_event );
					}

					// Join by status.
					if ( ! empty( $proposal_status ) ) {

						// "Join" to get proposal status.
						$pieces['join'] .= " LEFT JOIN {$wpdb->postmeta} proposal_status ON proposal_status.post_id = proposal.ID AND proposal_status.meta_key = 'proposal_status'";

						// If looking for submitted proposals, could be blank.
						if ( in_array( 'submitted', $proposal_status ) ) {
							$pieces['where'] .= " AND ( proposal_status.post_id IS NULL OR proposal_status.meta_value IN ('" . implode( "','", $proposal_status ) . "') )";
						} else {
							$pieces['where'] .= " AND proposal_status.meta_value IN ('" . implode( "','", $proposal_status ) . "')";
						}
					}

					// Only by proposal ID.
					if ( ! empty( $by_proposal ) ) {
						$pieces['where'] .= " AND proposal.ID IN ('" . implode( "','", $by_proposal ) . "')";
					}
				}

				break;

			case 'proposal':

				// Only if we're querying by the speaker.
				$proposal_speaker = $query->get( 'proposal_speaker' );
				if ( ! empty( $proposal_speaker ) && is_numeric( $proposal_speaker ) ) {

					// "Join" to get proposal status.
					$pieces['join'] .= " LEFT JOIN {$wpdb->postmeta} proposal_speaker ON proposal_speaker.post_id = {$wpdb->posts}.ID AND proposal_speaker.meta_key REGEXP '^speakers\_([0-9]+)\_speaker$'";
					$pieces['where'] .= $wpdb->prepare( ' AND proposal_speaker.meta_value = %s', $proposal_speaker );

				}

				// Query against proposal status.
				$proposal_status = $query->get( 'proposal_status' );

				if ( ! $proposal_status && ! empty( $_GET['proposal_status'] ) ) {
					$proposal_status = sanitize_text_field( $_GET['proposal_status'] );
				}

				// By default, only get confirmed proposals.
				if ( empty( $proposal_status ) ) {

					// Don't filter in the admin.
					if ( ! is_admin() ) {
						$proposal_status = array( 'confirmed' );
					}
				}

				if ( ! empty( $proposal_status ) ) {

					// Clean up query.
					if ( ! is_array( $proposal_status ) ) {
						$proposal_status = explode( ',', str_replace( ' ', '', $proposal_status ) );
					}

					$proposal_status = array_map( 'strtolower', $proposal_status );

					// "Join" to get proposal status.
					$pieces['join'] .= " LEFT JOIN {$wpdb->postmeta} proposal_status ON proposal_status.post_id = {$wpdb->posts}.ID AND proposal_status.meta_key = 'proposal_status'";

					// If looking for submitted proposals, could be blank.
					if ( in_array( 'submitted', $proposal_status ) ) {
						$pieces['where'] .= " AND ( proposal_status.post_id IS NULL OR proposal_status.meta_value IN ('" . implode( "','", $proposal_status ) . "') )";
					} else {
						$pieces['where'] .= " AND proposal_status.meta_value IN ('" . implode( "','", $proposal_status ) . "')";
					}
				}

				break;
		}

		return $pieces;
	}

	/**
	 * Registers all of our custom
	 * post types and taxonomies.
	 */
	public function register_custom_post_types_taxonomies() {

		register_post_type( 'proposal', array(
			'label'                 => __( 'Proposals', 'wpcampus' ),
			'labels'                => array(
				'name'                  => _x( 'Proposals', 'Post Type General Name', 'wpcampus' ),
				'singular_name'         => _x( 'Proposal', 'Post Type Singular Name', 'wpcampus' ),
				'menu_name'             => __( 'Proposals', 'wpcampus' ),
				'name_admin_bar'        => __( 'Proposals', 'wpcampus' ),
				'archives'              => __( 'Proposal Archives', 'wpcampus' ),
				'attributes'            => __( 'Proposal Attributes', 'wpcampus' ),
				'all_items'             => __( 'All Proposals', 'wpcampus' ),
				'add_new_item'          => __( 'Add New Proposal', 'wpcampus' ),
				'new_item'              => __( 'New Proposal', 'wpcampus' ),
				'edit_item'             => __( 'Edit Proposal', 'wpcampus' ),
				'update_item'           => __( 'Update Proposal', 'wpcampus' ),
				'view_item'             => __( 'View Proposal', 'wpcampus' ),
				'view_items'            => __( 'View Proposals', 'wpcampus' ),
				'search_items'          => __( 'Search Proposals', 'wpcampus' ),
				'insert_into_item'      => __( 'Insert into proposal', 'wpcampus' ),
				'uploaded_to_this_item' => __( 'Uploaded to this proposal', 'wpcampus' ),
				'items_list'            => __( 'Proposals list', 'wpcampus' ),
				'items_list_navigation' => __( 'Proposals list navigation', 'wpcampus' ),
				'filter_items_list'     => __( 'Filter proposals list', 'wpcampus' ),
			),
			'supports'              => array( 'title', 'editor', 'excerpt', 'revisions' ),
			'taxonomies'            => array( 'subjects' ),
			'hierarchical'          => false,
			'public'                => false,
			'show_ui'               => true,
			'show_in_menu'          => 'wpc-speakers',
			'menu_icon'             => 'dashicons-format-aside',
			'show_in_admin_bar'     => true,
			'show_in_nav_menus'     => false,
			'can_export'            => true,
			'has_archive'           => false,
			'exclude_from_search'   => true,
			'publicly_queryable'    => false,
			'capability_type'       => array( 'proposal', 'proposals' ),
			'rewrite'               => false,
			'show_in_rest'          => true,
		));

		register_post_type( 'profile', array(
			'label'               => __( 'Profiles', 'wpcampus' ),
			'labels'              => array(
				'name'                  => _x( 'Profiles', 'Post Type General Name', 'wpcampus' ),
				'singular_name'         => _x( 'Profile', 'Post Type Singular Name', 'wpcampus' ),
				'menu_name'             => __( 'Profiles', 'wpcampus' ),
				'name_admin_bar'        => __( 'Profiles', 'wpcampus' ),
				'archives'              => __( 'Profile Archives', 'wpcampus' ),
				'attributes'            => __( 'Profile Attributes', 'wpcampus' ),
				'all_items'             => __( 'All Profiles', 'wpcampus' ),
				'add_new_item'          => __( 'Add New Profile', 'wpcampus' ),
				'new_item'              => __( 'New Profile', 'wpcampus' ),
				'edit_item'             => __( 'Edit Profile', 'wpcampus' ),
				'update_item'           => __( 'Update Profile', 'wpcampus' ),
				'view_item'             => __( 'View Profile', 'wpcampus' ),
				'view_items'            => __( 'View Profiles', 'wpcampus' ),
				'search_items'          => __( 'Search Profiles', 'wpcampus' ),
				'insert_into_item'      => __( 'Insert into profile', 'wpcampus' ),
				'uploaded_to_this_item' => __( 'Uploaded to this profile', 'wpcampus' ),
				'items_list'            => __( 'Profiles list', 'wpcampus' ),
				'items_list_navigation' => __( 'Profiles list navigation', 'wpcampus' ),
				'filter_items_list'     => __( 'Filter profiles list', 'wpcampus' ),
			),
			'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'wpc-speakers',
			'menu_icon'           => 'dashicons-admin-users',
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => false,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => array( 'profile', 'profiles' ),
			'rewrite'             => false,
			'show_in_rest'        => true,
		));

		// @TODO Only be able to access with authentication?
		register_taxonomy( 'proposal_event', array( 'proposal' ), array(
			'labels'            => array(
				'name'                          => _x( 'Events', 'Taxonomy General Name', 'wpcampus' ),
				'singular_name'                 => _x( 'Event', 'Taxonomy Singular Name', 'wpcampus' ),
				'menu_name'                     => __( 'Events', 'wpcampus' ),
				'all_items'                     => __( 'All Events', 'wpcampus' ),
				'new_item_name'                 => __( 'New Event', 'wpcampus' ),
				'add_new_item'                  => __( 'Add New Event', 'wpcampus' ),
				'edit_item'                     => __( 'Edit Event', 'wpcampus' ),
				'update_item'                   => __( 'Update Event', 'wpcampus' ),
				'view_item'                     => __( 'View Event', 'wpcampus' ),
				'separate_items_with_commas'    => __( 'Separate events with commas', 'wpcampus' ),
				'add_or_remove_items'           => __( 'Add or remove events', 'wpcampus' ),
				'choose_from_most_used'         => __( 'Choose from the most used events', 'wpcampus' ),
				'popular_items'                 => __( 'Popular events', 'wpcampus' ),
				'search_items'                  => __( 'Search Events', 'wpcampus' ),
				'not_found'                     => __( 'No events found.', 'wpcampus' ),
				'no_terms'                      => __( 'No events', 'wpcampus' ),
				'items_list'                    => __( 'Events list', 'wpcampus' ),
				'items_list_navigation'         => __( 'Events list navigation', 'wpcampus' ),
			),
			'hierarchical'      => false,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => false,
			'show_tagcloud'     => false,
			'show_in_rest'      => true,
		));

		register_taxonomy( 'session_type', array( 'proposal' ), array(
			'labels'            => array(
				'name'                          => _x( 'Session Types', 'Taxonomy General Name', 'wpcampus' ),
				'singular_name'                 => _x( 'Session Type', 'Taxonomy Singular Name', 'wpcampus' ),
				'menu_name'                     => __( 'Session Types', 'wpcampus' ),
				'all_items'                     => __( 'All Session Types', 'wpcampus' ),
				'new_item_name'                 => __( 'New Session Type', 'wpcampus' ),
				'add_new_item'                  => __( 'Add New Session Type', 'wpcampus' ),
				'edit_item'                     => __( 'Edit Session Type', 'wpcampus' ),
				'update_item'                   => __( 'Update Session Type', 'wpcampus' ),
				'view_item'                     => __( 'View Session Type', 'wpcampus' ),
				'separate_items_with_commas'    => __( 'Separate session types with commas', 'wpcampus' ),
				'add_or_remove_items'           => __( 'Add or remove session types', 'wpcampus' ),
				'choose_from_most_used'         => __( 'Choose from the most used session types', 'wpcampus' ),
				'popular_items'                 => __( 'Popular session types', 'wpcampus' ),
				'search_items'                  => __( 'Search Session Types', 'wpcampus' ),
				'not_found'                     => __( 'No session types found.', 'wpcampus' ),
				'no_terms'                      => __( 'No session types', 'wpcampus' ),
				'items_list'                    => __( 'Session types list', 'wpcampus' ),
				'items_list_navigation'         => __( 'Session types list navigation', 'wpcampus' ),
			),
			'hierarchical'      => false,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => false,
			'show_tagcloud'     => false,
			// 'meta_box_cb'       => 'post_categories_meta_box', // Causes term ID string issues
			'show_in_rest'      => true,
		));

		// Register the session technical taxonomy.
		register_taxonomy( 'session_technical', array( 'proposal' ), array(
			'labels'             => array(
				'name'                          => _x( 'Technical Levels', 'Taxonomy General Name', 'wpcampus' ),
				'singular_name'                 => _x( 'Technical Level', 'Taxonomy Singular Name', 'wpcampus' ),
				'menu_name'                     => __( 'Technical Levels', 'wpcampus' ),
				'all_items'                     => __( 'All Technical Levels', 'wpcampus' ),
				'new_item_name'                 => __( 'New Technical Level', 'wpcampus' ),
				'add_new_item'                  => __( 'Add New Technical Level', 'wpcampus' ),
				'edit_item'                     => __( 'Edit Technical Level', 'wpcampus' ),
				'update_item'                   => __( 'Update Technical Level', 'wpcampus' ),
				'view_item'                     => __( 'View Technical Level', 'wpcampus' ),
				'separate_items_with_commas'    => __( 'Separate technical levels with commas', 'wpcampus' ),
				'add_or_remove_items'           => __( 'Add or remove technical levels', 'wpcampus' ),
				'choose_from_most_used'         => __( 'Choose from the most used technical levels', 'wpcampus' ),
				'popular_items'                 => __( 'Popular technical levels', 'wpcampus' ),
				'search_items'                  => __( 'Search Technical Levels', 'wpcampus' ),
				'not_found'                     => __( 'No technical levels found.', 'wpcampus' ),
				'no_terms'                      => __( 'No technical levels', 'wpcampus' ),
			),
			'hierarchical'      => false,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => false,
			'show_tagcloud'     => false,
			//'meta_box_cb'       => 'post_categories_meta_box', // Causes term ID string issues
			'show_in_rest'      => true,
		));
	}

	/**
	 * Filter when get_terms() is run
	 * to change up the terms.
	 *
	 * @args    $terms - array - Array of found terms.
	 * @args    $taxonomies - array - An array of taxonomies.
	 * @args    $args - array - An array of get_terms() arguments.
	 * @args    $term_query - WP_Term_Query - The WP_Term_Query object.
	 * @return  array - the filtered terms.
	 */
	public function filter_get_terms( $terms, $taxonomy, $query_vars, $term_query ) {

		// Filter technical levels to be in a specific order.
		$technical_tax = 'session_technical';
		if ( $technical_tax == $taxonomy
			|| ( is_array( $taxonomy ) && 1 == count( $taxonomy ) && $technical_tax == array_shift( $taxonomy ) ) ) {

			// Only sort if WP_Term objects.
			foreach( $terms as $term ) {
				if ( empty( $term->slug ) ) {
					break;
				}

				// Sort "Beginner", "Intermediate" then "Advanced" as first terms.
				usort( $terms, function( $a, $b ){
					if ( $a->slug == $b->slug ) {
						return 0;
					}
					if ( 'beginner' == $a->slug ) {
						return -1;
					}
					if ( 'beginner' == $b->slug ) {
						return 1;
					}
					if ( 'intermediate' == $a->slug && 'beginner' != $b->slug ) {
						return -1;
					}
					if ( 'advanced' == $a->slug && 'beginner' != $b->slug && 'intermediate' != $b->slug) {
						return -1;
					}
					return strcmp( $a->name, $b->name );
				});

				break;
			}
		}

		return $terms;
	}

	/**
	 * Add rewrite rules.
	 */
	public function add_rewrite_rules() {
		add_rewrite_tag( '%session%', '([^&]+)' );
		add_rewrite_rule( '^session/([^\/\s]+)/?', 'index.php?session=$matches[1]', 'top' );
		//add_rewrite_rule('^leaf/([0-9]+)/?', 'index.php?page_id=$matches[1]', 'top');
	}
}
WPCampus_Speakers_Global::register();
