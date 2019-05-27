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

		// Process user-submitted proposal rating and assignment.
		add_action( 'init', array( $plugin, 'process_proposal_rating' ) );
		add_action( 'init', array( $plugin, 'process_proposal_assign' ) );

		// Set priority later to make sure things are registered.
		add_action( 'init', array( $plugin, 'process_download_profile_headshots' ), 100 );
		add_action( 'init', array( $plugin, 'process_download_profile_csv' ), 100 );

		// Filter queries.
		add_filter( 'query_vars', array( $plugin, 'filter_query_vars' ) );

		add_action( 'pre_get_posts', array( $plugin, 'filter_post_query' ), 100 );
		add_filter( 'posts_clauses', array( $plugin, 'filter_posts_clauses' ), 100, 2 );
		//add_filter( 'posts_results', array( $plugin, 'filter_posts_results' ), 100, 2 );

		// Register our post types and taxonomies.
		add_action( 'init', array( $plugin, 'register_custom_post_types_taxonomies' ) );

		// Filter terms when retrieved.
		add_filter( 'get_terms', array( $plugin, 'filter_get_terms' ), 10, 4 );

		// Manage comments.
		add_filter( 'wpcampus_show_comments', array( $plugin, 'filter_show_comments' ) );
		add_filter( 'comments_open', array( $plugin, 'filter_comments_open' ), 100, 2 );

		// Add rewrite rules and tags.
		add_action( 'init', array( $plugin, 'add_rewrite_rules_tags' ) );

		// Add needed styles and scripts.
		add_action( 'wp_enqueue_scripts', array( $plugin, 'enqueue_styles_scripts' ) );

		// Filter the permalink.
		add_filter( 'post_type_link', array( $plugin, 'filter_permalink' ), 100, 2 );

		// Filter the post title.
		//add_filter( 'the_title', array( $plugin, 'filter_the_title' ), 100, 2 );
		//add_filter( 'wpcampus_page_title', array( $plugin, 'filter_wpcampus_page_title' ) );

		// Filter the content.
		//add_filter( 'the_content', array( $plugin, 'filter_the_content' ), 100 );

		// Return data to AJAX.
		add_action( 'wp_ajax_wpc_get_proposals', array( $plugin, 'ajax_get_proposals' ) );

		// Add sessions to contributor pages.
		add_filter( 'the_posts', array( $plugin, 'add_sessions_to_contributors' ), 100, 2 );

		add_filter( 'get_the_excerpt', array( $plugin, 'get_proposal_excerpt' ), 10, 2 );

	}

	/**
	 * Process a user submitting a proposal rating.
	 */
	public function process_proposal_rating() {

		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( ! ( isset( $_POST['wpc_session_rating'] ) && isset( $_POST['wpc_process_session_rating_nonce'] ) ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['wpc_process_session_rating_nonce'], 'wpc_process_session_rating' ) ) {
			return ;
		}

		if ( empty( $_POST['post_id'] ) || ! is_numeric( $_POST['post_id'] ) || 'proposal' != get_post_type( $_POST['post_id'] ) ) {
			return;
		}

		$current_user_id = (int) get_current_user_id();
		$rating = (int) $_POST['wpc_session_rating'];

		update_post_meta( $_POST['post_id'], 'wpc_session_rating_' . $current_user_id, $rating );

		// get_permalink() doesnt work here for some reason.
		$redirect = add_query_arg( 'rating', 'success', wpcampus_speakers()->get_session_review_permalink( $_POST['post_id'] ) );

		wp_safe_redirect( $redirect );
		exit;

	}

	/**
	 * Process a user submitting a proposal assignment.
	 */
	public function process_proposal_assign() {

		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( ! isset( $_POST['wpc_process_session_assign_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['wpc_process_session_assign_nonce'], 'wpc_process_session_assign' ) ) {
			return ;
		}

		if ( empty( $_POST['post_id'] ) || ! is_numeric( $_POST['post_id'] ) || 'proposal' != get_post_type( $_POST['post_id'] ) ) {
			return;
		}

		$helper = wpcampus_speakers();

		// Update status.
		if ( isset( $_POST['proposal_status'] ) ) {
			$proposal_status = sanitize_text_field( $_POST['proposal_status'] );
			if ( ! empty( $proposal_status ) ) {
				$helper->update_proposal_status( $_POST['post_id'], $proposal_status );
			}
		}

		// Update session format.
		if ( isset( $_POST['selected_session_format'] ) ) {
			$selected_session_format = (int) $_POST['selected_session_format'];
			if ( $selected_session_format > 0 && term_exists( $selected_session_format, 'session_format' ) ) {
				update_post_meta( $_POST['post_id'], 'selected_session_format', $selected_session_format );
			}
		}

		// Update feedback.
		if ( isset( $_POST['proposal_feedback'] ) ) {
			update_post_meta( $_POST['post_id'], 'proposal_feedback', trim( sanitize_text_field( $_POST['proposal_feedback'] ) ) );
		}

		// get_permalink() doesn't work here for some reason.
		$redirect = add_query_arg( 'assign', 'success', $helper->get_session_review_permalink( $_POST['post_id'] ) );

		wp_safe_redirect( $redirect );
		exit;

	}

	/**
	 * Process someone requesting a profile CSV.
	 */
	public function process_download_profile_headshots() {

		if ( ! isset( $_GET['download_profile_headshots_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_GET['download_profile_headshots_nonce'], 'download_profile_headshots' ) ) {
			return;
		}

		if ( ! current_user_can( 'download_wpc_profile_headshots' ) ) {
			return;
		}

		$speaker_args = array();

		if ( ! empty( $_GET['proposal_status'] ) ) {
			$speaker_args['proposal_status'] = $_GET['proposal_status'];
		}

		$headshots = wpcampus_speakers()->get_profile_headshots( $speaker_args );

		$zip = new ZipArchive();

		// Create a temp file & open it.
		$tmp_file = tempnam( '.', '' );
		$zip->open( $tmp_file, ZipArchive::CREATE );

		foreach( $headshots as $file ) {

			// Download file.
			$download_file = file_get_contents( $file );

			// Add it to the zip.
			$zip->addFromString( basename( $file ), $download_file );

		}

		$zip->close();

		// Send the file to the browser as a download.
		header( 'Content-disposition: attachment; filename=wpc-speaker-headshots.zip' );
		header( 'Content-type: application/zip' );
		readfile( $tmp_file );
		//header( 'Content-Length: ' . filesize( $csv_profiles_file_path ) );
		//header( 'Pragma: no-cache' );
		//header( 'Expires: 0' );

		exit;

	}

	/**
	 * Process someone requesting a profile CSV.
	 */
	public function process_download_profile_csv() {

		if ( ! isset( $_GET['download_profile_csv_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_GET['download_profile_csv_nonce'], 'download_profile_csv' ) ) {
			return;
		}

		if ( ! current_user_can( 'download_wpc_profile_csv' ) ) {
			return;
		}

		$profiles_csv = array();

		if ( ! empty( $_GET['confirmation_email'] ) ) {
			$profiles_csv = $this->get_confirmation_email_csv();
		}

		if ( empty( $profiles_csv ) ) {
			$profiles_csv = array();
		}

		// Create temporary CSV file for the complete photo list.
		$csv_profiles_filename = 'wpcampus-speakers.csv';
		$csv_profiles_file_path = "/tmp/{$csv_profiles_filename}";
		$csv_profiles_file = fopen( $csv_profiles_file_path, 'w' );

		// Write image info to the file.
		foreach ( $profiles_csv as $profile ) {
			fputcsv( $csv_profiles_file, $profile );
		}

		// Close the file.
		fclose( $csv_profiles_file );

		// Output headers so that the file is downloaded rather than displayed.
		header( 'Content-type: text/csv' );
		header( "Content-disposition: attachment; filename = {$csv_profiles_filename}" );
		header( 'Content-Length: ' . filesize( $csv_profiles_file_path ) );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Read the file.
		readfile( $csv_profiles_file_path );

		exit;
	}

	/**
	 *
	 */
	public function get_confirmation_email_csv() {
		$helper = wpcampus_speakers();

		$profile_args = array(
			'get_proposals' => true,
			'get_feedback'  => true,
		);

		if ( ! empty( $_GET['proposal_status'] ) ) {
			$profile_args['proposal_status'] = sanitize_text_field( $_GET['proposal_status'] );
		}

		if ( ! empty( $_GET['proposal_event'] ) ) {
			if ( 'all' == $_GET['proposal_event'] ) {

				// Get all events.
				$proposal_events = get_terms( array(
					'taxonomy' => 'proposal_event',
					'hide_empty' => true,
					'fields' => 'ids',
				));

				$profile_args['proposal_event'] = $proposal_events;

				// @TODO fix? otherwise it takes too long.
				$profile_args['get_proposals'] = false;

			}
		}

		if ( empty( $profile_args['proposal_event'] ) ) {
			$profile_args['proposal_event'] = $helper->get_proposal_event();
		}

		$profiles = $helper->get_profiles( $profile_args );

		$profiles_csv = [];

		// Create array for CSV. Start with headers.
		if ( $profile_args['get_proposals'] ) {

			$profiles_csv[] = array(
				'ID',
				'Display Name',
				'First Name',
				'Last Name',
				'Email',
				//'Phone',
				'Session',
				'Format',
				'Status',
				'Feedback',
				'Confirmation URL',
			);
		} else {

			$profiles_csv[] = array(
				'ID',
				'Display Name',
				'First Name',
				'Last Name',
				'Email',
			);
		}

		foreach ( $profiles as $profile ) {

			$profile_row = array(
				$profile->ID,
				$profile->display_name,
				$profile->first_name,
				$profile->last_name,
				$profile->email,
			);

			// Add row for each proposal.
			if ( $profile_args['get_proposals'] && ! empty( $profile->proposals ) ) {

				foreach ( $profile->proposals as $proposal ) {

					$profile_row[] = $proposal->title;
					$profile_row[] = $proposal->format_name;
					$profile_row[] = $proposal->proposal_status;
					$profile_row[] = $proposal->feedback;
					$profile_row[] = $helper->get_proposal_confirmation_url( $proposal->ID, $profile->ID );

				}
			}

			$profiles_csv[] = $profile_row;

		}

		return $profiles_csv;
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
	 * Statuses:
	 *
	 * confirmed (has to be for public view)
	 * declined
	 * selected
	 * submitted or NULL
	 */
	public function get_proposal_status_pieces( $pieces, $proposal_status ) {
		global $wpdb;

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

		return $pieces;
	}

	/**
	 * Filter post queries before they're run.
	 *
	 * @param   WP_Query - $query - The WP_Query instance (passed by reference).
	 */
	public function filter_post_query( $query ) {

		// Our custom session pages are single pages.
		$wpc_review = $query->get( 'wpc_review' );
		$wpc_proposal = $query->get( 'wpc_proposal' );
		if ( ! empty( $wpc_review ) || ! empty( $wpc_proposal ) ) {

			$query->set( 'post_type', 'proposal' );
			$query->is_single = true;
			$query->is_singular = true;
			$query->is_home = false;

		}
	}

	/**
	 *
	 */
	public function filter_posts_results( $posts, $query ) {
		return $posts;
	}

	/**
	 * Filter queries.
	 */
	public function filter_posts_clauses( $pieces, $query ) {
		global $wpdb;

		// Only need if querying a session.
		$wpc_review = $query->get( 'wpc_review' );
		$wpc_proposal = $query->get( 'wpc_proposal' );

		if ( ! empty( $wpc_review ) || ! empty( $wpc_proposal ) ) {

			$session_slug = $wpc_review;

			if ( empty( $session_slug ) ) {
				$session_slug = $wpc_proposal;
			}

			$pieces = array(
				'where'    => " AND {$wpdb->posts}.post_type = 'proposal' AND {$wpdb->posts}.post_status IN ('publish')",
				'groupby'  => '',
				'join'     => '',
				'orderby'  => "{$wpdb->posts}.post_title ASC",
				'distinct' => '',
				'fields'   => "{$wpdb->posts}.*",
				'limits'   => '',
			);

			/*
			 * Statuses:
			 *
			 * confirmed (has to be for public view)
			 * declined
			 * selected
			 * submitted or NULL
			 */
			if ( current_user_can( 'view_wpc_proposals' ) ) {
				$proposal_status = null;
			} elseif ( empty( $proposal_status ) ) {
				$proposal_status = 'confirmed';
			}

			if ( ! empty( $proposal_status ) ) {
				$pieces = $this->get_proposal_status_pieces( $pieces, $proposal_status );
			}

			// Make sure folks who don't have capability can't see.
			if ( ! current_user_can( 'view_wpc_proposals' ) ) {
				$pieces['where'] .= ' AND 0=1';
			}

			// Add specific session.
			$pieces['where'] .= $wpdb->prepare( " AND {$wpdb->posts}.post_name = %s", $session_slug );

			/*// Only if we're querying by the speaker.
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
			}*/

			return $pieces;
		}

		// What post type are we querying?
		$post_type = $query->get( 'post_type' );

		if ( 'profile' == $post_type ) {

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

			return $pieces;
		}

		if ( 'proposal' == $post_type ) {

			// Only if we're querying by the speaker.
			$proposal_speaker = $query->get( 'proposal_speaker' );
			if ( ! empty( $proposal_speaker ) && is_numeric( $proposal_speaker ) ) {

				// "Join" to get proposal status.
				$pieces['join'] .= " LEFT JOIN {$wpdb->postmeta} proposal_speaker ON proposal_speaker.post_id = {$wpdb->posts}.ID AND proposal_speaker.meta_key REGEXP '^speakers\_([0-9]+)\_speaker$'";
				$pieces['where'] .= $wpdb->prepare( ' AND proposal_speaker.meta_value = %s', $proposal_speaker );

			}

			/*
			 * Query against proposal status.
			 *
			 * confirmed (has to be for public view)
			 * declined
			 * selected
			 * submitted or NULL
			 */
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
				$pieces = $this->get_proposal_status_pieces( $pieces, $proposal_status );
			}

			return $pieces;
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
			'supports'            => array( 'title', 'editor', 'excerpt', 'revisions' ),
			'taxonomies'          => array( 'subjects' ),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'wpc-sessions',
			'menu_icon'           => 'dashicons-format-aside',
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => false,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => array( 'proposal', 'proposals' ),
			'rewrite'             => false,
			'show_in_rest'        => false,
		));

		/*[edit_post] => edit_proposal
            [read_post] => read_proposal
            [delete_post] => delete_proposal
            [edit_posts] => edit_proposals
            [edit_others_posts] => edit_others_proposals
            [publish_posts] => publish_proposals
            [read_private_posts] => read_private_proposals
            [create_posts] => edit_proposals*/

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
			'show_in_menu'        => 'wpc-sessions',
			'menu_icon'           => 'dashicons-admin-users',
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => false,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => array( 'profile', 'profiles' ),
			'rewrite'             => false,
			'show_in_rest'        => false,
		));

		/*[edit_post] => edit_profile
            [read_post] => read_profile
            [delete_post] => delete_profile
            [edit_posts] => edit_profiles
            [edit_others_posts] => edit_others_profiles
            [publish_posts] => publish_profiles
            [read_private_posts] => read_private_profiles
            [create_posts] => edit_profiles*/

		// @TODO Only be able to access with authentication?
		register_taxonomy( 'proposal_event', array( 'proposal' ), array(
			'labels'            => array(
				'name'                       => _x( 'Events', 'Taxonomy General Name', 'wpcampus' ),
				'singular_name'              => _x( 'Event', 'Taxonomy Singular Name', 'wpcampus' ),
				'menu_name'                  => __( 'Events', 'wpcampus' ),
				'all_items'                  => __( 'All Events', 'wpcampus' ),
				'new_item_name'              => __( 'New Event', 'wpcampus' ),
				'add_new_item'               => __( 'Add New Event', 'wpcampus' ),
				'edit_item'                  => __( 'Edit Event', 'wpcampus' ),
				'update_item'                => __( 'Update Event', 'wpcampus' ),
				'view_item'                  => __( 'View Event', 'wpcampus' ),
				'separate_items_with_commas' => __( 'Separate events with commas', 'wpcampus' ),
				'add_or_remove_items'        => __( 'Add or remove events', 'wpcampus' ),
				'choose_from_most_used'      => __( 'Choose from the most used events', 'wpcampus' ),
				'popular_items'              => __( 'Popular events', 'wpcampus' ),
				'search_items'               => __( 'Search Events', 'wpcampus' ),
				'not_found'                  => __( 'No events found.', 'wpcampus' ),
				'no_terms'                   => __( 'No events', 'wpcampus' ),
				'items_list'                 => __( 'Events list', 'wpcampus' ),
				'items_list_navigation'      => __( 'Events list navigation', 'wpcampus' ),
			),
			'hierarchical'      => false,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => false,
			'show_tagcloud'     => false,
			'show_in_rest'      => true,
		));

		register_taxonomy( 'preferred_session_format', array( 'proposal' ), array(
			'labels'            => array(
				'name'                       => _x( 'Preferred Session Formats', 'Taxonomy General Name', 'wpcampus' ),
				'singular_name'              => _x( 'Preferred Session Format', 'Taxonomy Singular Name', 'wpcampus' ),
				'menu_name'                  => __( 'Preferred Session Formats', 'wpcampus' ),
				'all_items'                  => __( 'All Session Formats', 'wpcampus' ),
				'new_item_name'              => __( 'New Session Format', 'wpcampus' ),
				'add_new_item'               => __( 'Add New Session Format', 'wpcampus' ),
				'edit_item'                  => __( 'Edit Session Format', 'wpcampus' ),
				'update_item'                => __( 'Update Session Format', 'wpcampus' ),
				'view_item'                  => __( 'View Session Format', 'wpcampus' ),
				'separate_items_with_commas' => __( 'Separate session formats with commas', 'wpcampus' ),
				'add_or_remove_items'        => __( 'Add or remove session formats', 'wpcampus' ),
				'choose_from_most_used'      => __( 'Choose from the most used session formats', 'wpcampus' ),
				'popular_items'              => __( 'Popular session formats', 'wpcampus' ),
				'search_items'               => __( 'Search Session Formats', 'wpcampus' ),
				'not_found'                  => __( 'No session formats found.', 'wpcampus' ),
				'no_terms'                   => __( 'No session formats', 'wpcampus' ),
				'items_list'                 => __( 'Session formats list', 'wpcampus' ),
				'items_list_navigation'      => __( 'Session formats list navigation', 'wpcampus' ),
			),
			'hierarchical'      => false,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => false,
			'show_in_nav_menus' => false,
			'show_tagcloud'     => false,
			// 'meta_box_cb'       => 'post_categories_meta_box', // Causes term ID string issues
			'show_in_rest'      => false,
		));

		register_taxonomy( 'session_format', array( 'proposal' ), array(
			'labels'            => array(
				'name'                       => _x( 'Session Formats', 'Taxonomy General Name', 'wpcampus' ),
				'singular_name'              => _x( 'Session Format', 'Taxonomy Singular Name', 'wpcampus' ),
				'menu_name'                  => __( 'Session Formats', 'wpcampus' ),
				'all_items'                  => __( 'All Session Formats', 'wpcampus' ),
				'new_item_name'              => __( 'New Session Format', 'wpcampus' ),
				'add_new_item'               => __( 'Add New Session Format', 'wpcampus' ),
				'edit_item'                  => __( 'Edit Session Format', 'wpcampus' ),
				'update_item'                => __( 'Update Session Format', 'wpcampus' ),
				'view_item'                  => __( 'View Session Format', 'wpcampus' ),
				'separate_items_with_commas' => __( 'Separate session formats with commas', 'wpcampus' ),
				'add_or_remove_items'        => __( 'Add or remove session formats', 'wpcampus' ),
				'choose_from_most_used'      => __( 'Choose from the most used session formats', 'wpcampus' ),
				'popular_items'              => __( 'Popular session formats', 'wpcampus' ),
				'search_items'               => __( 'Search Session Formats', 'wpcampus' ),
				'not_found'                  => __( 'No session formats found.', 'wpcampus' ),
				'no_terms'                   => __( 'No session formats', 'wpcampus' ),
				'items_list'                 => __( 'Session formats list', 'wpcampus' ),
				'items_list_navigation'      => __( 'Session formats list navigation', 'wpcampus' ),
			),
			'hierarchical'      => false,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => false,
			'show_in_nav_menus' => false,
			'show_tagcloud'     => false,
			// 'meta_box_cb'       => 'post_categories_meta_box', // Causes term ID string issues
			'show_in_rest'      => true,
		));

		// Register the session technical taxonomy.
		register_taxonomy( 'session_technical', array( 'proposal' ), array(
			'labels'             => array(
				'name'                       => _x( 'Technical Levels', 'Taxonomy General Name', 'wpcampus' ),
				'singular_name'              => _x( 'Technical Level', 'Taxonomy Singular Name', 'wpcampus' ),
				'menu_name'                  => __( 'Technical Levels', 'wpcampus' ),
				'all_items'                  => __( 'All Technical Levels', 'wpcampus' ),
				'new_item_name'              => __( 'New Technical Level', 'wpcampus' ),
				'add_new_item'               => __( 'Add New Technical Level', 'wpcampus' ),
				'edit_item'                  => __( 'Edit Technical Level', 'wpcampus' ),
				'update_item'                => __( 'Update Technical Level', 'wpcampus' ),
				'view_item'                  => __( 'View Technical Level', 'wpcampus' ),
				'separate_items_with_commas' => __( 'Separate technical levels with commas', 'wpcampus' ),
				'add_or_remove_items'        => __( 'Add or remove technical levels', 'wpcampus' ),
				'choose_from_most_used'      => __( 'Choose from the most used technical levels', 'wpcampus' ),
				'popular_items'              => __( 'Popular technical levels', 'wpcampus' ),
				'search_items'               => __( 'Search Technical Levels', 'wpcampus' ),
				'not_found'                  => __( 'No technical levels found.', 'wpcampus' ),
				'no_terms'                   => __( 'No technical levels', 'wpcampus' ),
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

		if ( is_array( $taxonomy ) ) {
			$taxonomy = array_shift( $taxonomy );
		}

		if ( $technical_tax != $taxonomy ) {
			return $terms;
		}

		// Only sort if WP_Term objects.
		foreach ( $terms as $term ) {
			if ( empty( $term->slug ) ) {
				break;
			}

			// Sort "Beginner", "Intermediate" then "Advanced" as first terms.
			usort( $terms, function( $a, $b ) {
				if ( 'beginner' == $a->slug && 'beginner' != $b->slug ) {
					return -1;
				} elseif ( 'beginner' == $b->slug && 'beginner' != $a->slug ) {
					return 1;
				}

				if ( 'intermediate' == $a->slug && ! in_array( $b->slug, array( 'beginner', 'intermediate' ) ) ) {
					return -1;
				} elseif ( 'intermediate' == $b->slug && ! in_array( $a->slug, array( 'beginner', 'intermediate' ) ) ) {
					return 1;
				}

				if ( 'advanced' == $a->slug && ! in_array( $b->slug, array( 'beginner', 'intermediate', 'advanced' ) ) ) {
					return -1;
				} elseif ( 'intermediate' == $b->slug && ! in_array( $a->slug, array( 'beginner', 'intermediate', 'advanced' ) ) ) {
					return 1;
				}

				return strcmp( $a->name, $b->name );
			});

			break;
		}

		return $terms;
	}

	/**
	 * Filter from main WPCampus theme to
	 * decided whether or not to show comments.
	 */
	public function filter_show_comments( $show ) {
		if ( is_singular( 'proposal' ) ) {
			if ( current_user_can( 'review_wpc_proposals' ) ) {
				return true;
			}
			return false;
		}
		return $show;
	}

	/**
	 * Filter whether or not comments are open for proposals.
	 */
	public function filter_comments_open( $open, $post_id ) {
		if ( 'proposal' == get_post_type( $post_id ) ) {
			if ( current_user_can( 'review_wpc_proposals' ) ) {
				return true;
			}
			return false;
		}
		return $open;
	}

	/**
	 * Add rewrite rules.
	 */
	public function add_rewrite_rules_tags() {
		add_rewrite_tag( '%wpc_review%', '([^\/]+)' );
		add_rewrite_tag( '%wpc_review_main%', '([^\/]+)' );
		add_rewrite_tag( '%wpc_proposal%', '([^\/]+)' );
		add_rewrite_rule( '^review/([^\/\s]+)/?', 'index.php?wpc_review=$matches[1]', 'top' );
		add_rewrite_rule( '^review/?', 'index.php?pagename=review&wpc_review_main=1', 'top' );
		add_rewrite_rule( '^session/([^\/\s]+)/?', 'index.php?wpc_proposal=$matches[1]', 'top' );
	}

	/**
	 *
	 */
	public function enqueue_styles_scripts() {
		$wpc_review_main = (bool) get_query_var( 'wpc_review_main' );
		if ( true === $wpc_review_main ) {
			wpcampus_speakers()->load_review_assets();
		}
	}

	/**
	 * Filter permalink(s).
	 *
	 * @access  public
	 * @param   $post_link - string - The post's permalink.
	 * @param   $post - WP_Post - The post in question.
	 * @return  string - the filtered permalink.
	 */
	public function filter_permalink( $post_link, $post ) {
		if ( 'proposal' != $post->post_type ) {
			return $post_link;
		}
		return wpcampus_speakers()->get_session_permalink( $post->ID, $post );
	}

	/**
	 * Filter post titles.
	 *
	 * @access  public
	 * @param   $post_title - string - the default post title.
	 * @param   $post_id - int - the post ID.
	 * @return  string - the filtered post title.
	 */
	public function filter_the_title( $post_title, $post_id ) {

		// TODO: Hard setting the ID keeps from running a DB query.
		if ( 21326 != $post_id ) {
			return $post_title;
		}

		if ( is_admin() ) {
			return $post_title;
		}

		// Returns the session slug.
		$session = wpcampus_speakers()->is_session_page();
		if ( empty( $session ) ) {
			return $post_title;
		}

		// Get proposal title.
		$proposal = wpcampus_network()->get_post_by_name( $session, 'proposal' );
		if ( empty( $proposal->post_title ) ) {
			return $post_title;
		}

		return $proposal->post_title;
	}

	/**
	 *
	 */
	public function filter_wpcampus_page_title( $title ) {
		return $title;
	}

	/**
	 * Filter the post content.
	 *
	 * @access  public
	 * @param   $content - string - the default post content.
	 * @return  string - the filtered content.
	 */
	public function filter_the_content( $content ) {
		$helper = wpcampus_speakers();

		// Returns the session slug.
		$session = $helper->is_session_page();
		if ( empty( $session ) ) {
			return $content;
		}

		// Get proposal title.
		$proposal = wpcampus_network()->get_post_by_name( $session, 'proposal' );
		if ( empty( $proposal->post_content ) ) {
			return $content;
		}

		$content = wpautop( $proposal->post_content );

		$video_html = '';

		// If URL is hard-set, return first.
		$video_url = get_post_meta( $proposal->ID, 'session_video_url', true );
		if ( ! empty( $video_url ) ) {

			// @TODO: Why wont this work with embeds? How does it work on WPCampus 2016 site?
			$video_html = '<iframe title="" src="' . $video_url . '" />';

		} else {

			$proposal_video_id  = $helper->get_proposal_video_id( $proposal->ID );

			// This means its one of our video posts so get its URL.
			if ( ! empty( $proposal_video_id ) ) {

				$video_url = $helper->get_video_url( $proposal_video_id );

				if ( ! empty( $video_url ) ) {
					$video_html = wp_oembed_get( $video_url, array(
						'height' => 450,
					));
				}
			}
		}

		if ( empty( $video_html ) ) {
			return $content;
		}

		return $content . '<h2>' . __( 'Session video', 'wpcampus' ) . '</h2>' . $video_html;
	}

	/**
	 * Get the proposals via an AJAX request.
	 */
	public function ajax_get_proposals() {

		$args = array();

		if ( ! empty( $_GET['orderby'] ) ) {
			$args['orderby'] = sanitize_text_field( $_GET['orderby'] );
		}

		if ( ! empty( $_GET['order'] ) ) {
			$args['order'] = sanitize_text_field( $_GET['order'] );
		}

		if ( ! empty( $_GET['getUserViewed'] ) ) {
			$args['get_user_viewed'] = sanitize_text_field( $_GET['getUserViewed'] );
		}

		if ( ! empty( $_GET['getUserRating'] ) ) {
			$args['get_user_rating'] = sanitize_text_field( $_GET['getUserRating'] );
		}

		if ( ! empty( $_GET['getAvgRating'] ) ) {
			$args['get_avg_rating'] = sanitize_text_field( $_GET['getAvgRating'] );
		}

		if ( ! empty( $_GET['proposalStatus'] ) ) {
			$args['proposal_status'] = sanitize_text_field( $_GET['proposalStatus'] );
		}

		if ( ! empty( $_GET['getProfiles'] ) ) {
			$args['get_profiles'] = (bool) sanitize_text_field( $_GET['getProfiles'] );
		}

		if ( ! empty( $_GET['getSubjects'] ) ) {
			$args['get_subjects'] = (bool) sanitize_text_field( $_GET['getSubjects'] );
		}

		if ( ! empty( $_GET['subjects'] ) ) {
			$args['subjects'] = sanitize_text_field( $_GET['subjects'] );
		}

		if ( ! empty( $_GET['byProfile'] ) ) {
			$args['by_profile'] = sanitize_text_field( $_GET['byProfile'] );
		}

		if ( ! empty( $_GET['proposalEvent'] ) ) {
			$args['proposal_event'] = sanitize_text_field( $_GET['proposalEvent'] );
		}

		echo json_encode( wpcampus_speakers()->get_proposals( $args ) );
		wp_die();
	}

	public function sort_by_post_date_gmt_desc( $a, $b ) {
		$t1 = strtotime( $a->post_date_gmt );
		$t2 = strtotime( $b->post_date_gmt );
		return $t2 - $t1;
	}

	public function get_proposal_excerpt( $excerpt, $post ) {
		if ( 'proposal' != $post->post_type ) {
			return $excerpt;
		}

		if ( ! empty( $post->post_excerpt ) ) {
			$proposal_excerpt = $post->post_excerpt;
		} else if ( ! empty( $post->excerpt ) ) {
			$proposal_excerpt = $post->excerpt;
		} else {
			$proposal_excerpt = null;
		}

		if ( empty( $proposal_excerpt ) ) {
			return $excerpt;
		}

		if ( ! empty( $proposal_excerpt['raw'] ) ) {
			return $proposal_excerpt['raw'];
		}

		if ( ! empty( $proposal_excerpt ) && is_string( $proposal_excerpt ) ) {
			return $proposal_excerpt;
		}

		return $excerpt;
	}

	public function add_sessions_to_contributors( $posts, $query ) {

		if ( ! $query->is_author() ) {
			return $posts;
		}

		$author_id = $query->get( 'author' );

		if ( empty( $author_id ) ) {
			return $posts;
		}

		$proposals = wpcampus_speakers()->get_proposals(
			array(
				'by_wp_user'   => (int) $author_id,
				'get_profiles' => false,
				'get_headshot' => false,
				'proposal_status' => 'confirmed',
			)
		);

		if ( empty( $proposals ) ) {
			return $posts;
		}

		$posts = array_merge( $posts, $proposals );

		usort( $posts, array( $this, 'sort_by_post_date_gmt_desc' ) );

		return $posts;
	}
}
WPCampus_Speakers_Global::register();
