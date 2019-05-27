<?php
/**
 * PHP class that holds the admin
 * functionality for the plugin.
 *
 * @category    Class
 * @package     WPCampus Speakers
 */
final class WPCampus_Speakers_Admin {

	/**
	 * We don't need to instantiate this class.
	 */
	protected function __construct() {}

	/**
	 * Registers all of our hooks and what not.
	 */
	public static function register() {
		$plugin = new self();

		// Enqueue admin styles and scripts.
		add_action( 'admin_enqueue_scripts', array( $plugin, 'enqueue_styles_scripts' ) );

		// Print styles in the admin <head>.
		add_action( 'admin_print_styles-edit.php', array( $plugin, 'print_styles' ) );

		// Add items to the admin menu.
		add_action( 'admin_menu', array( $plugin, 'add_menu_pages' ), 5 );
		add_action( 'admin_menu', array( $plugin, 'add_menu_subpages' ), 10 );
		add_action( 'parent_file', array( $plugin, 'filter_submenu_parent' ) );

		// Add and populate custom columns.
		add_filter( 'manage_profile_posts_columns', array( $plugin, 'add_profile_columns' ) );
		add_filter( 'manage_proposal_posts_columns', array( $plugin, 'add_proposal_columns' ) );
		add_action( 'manage_profile_posts_custom_column', array( $plugin, 'populate_profile_columns' ), 10, 2 );
		add_action( 'manage_proposal_posts_custom_column', array( $plugin, 'populate_proposal_columns' ), 10, 2 );

		// Disable months dropdown.
		add_filter( 'disable_months_dropdown', array( $plugin, 'disable_months_dropdown' ), 100, 2 );

		// Adds dropdown to filter the speaker status.
		add_action( 'restrict_manage_posts', array( $plugin, 'add_proposal_filters' ), 100 );

		// Filter post row actions.
		add_filter( 'post_row_actions', array( $plugin, 'filter_post_row_actions' ), 10, 2 );

		// Add/remove meta boxes.
		add_action( 'add_meta_boxes', array( $plugin, 'add_meta_boxes' ), 10, 2 );
		add_action( 'admin_menu', array( $plugin, 'remove_meta_boxes' ) );

		// Add instructions to thumbnail admin meta box.
		add_filter( 'admin_post_thumbnail_html', array( $plugin, 'filter_admin_post_thumbnail_html' ), 100, 2 );

		// Filter queries.
		//add_filter( 'posts_clauses', array( $plugin, 'filter_posts_clauses' ), 100, 2 );

	}

	/**
	 * Filter the queries to "join" and order schedule information.
	 *
	 * @TODO:
	 * - Copied over from sessions plugin.
	 * - Was used to filter speakers in the admin to only show confirmed
	 *   and to order by status but we don't really want that anymore.
	 *   Keeping code for now just in case.

	public function filter_posts_clauses( $pieces, $query ) {
		global $wpdb;

		// Get the post type.
		$post_type = $query->get( 'post_type' );

		// For speakers...
		if ( 'speakers' == $post_type ) {

			// "Join" to get speaker status.
	        // @TODO: Now uses "proposal_status" for proposal post type.
			$pieces['join'] .= " LEFT JOIN {$wpdb->postmeta} speaker_status ON speaker_status.post_id = {$wpdb->posts}.ID AND speaker_status.meta_key = 'conf_sch_speaker_status'";

			// Order by status.
			$pieces['orderby'] = "IF ( 'selected' = speaker_status.meta_value, 3, IF ( 'declined' = speaker_status.meta_value, 2, IF ( 'confirmed' = speaker_status.meta_value, 1, 0 ) ) ) DESC";

			// Don't filter in the trash.
			if ( ! ( ! empty( $_REQUEST['post_status'] ) && 'trash' == $_REQUEST['post_status'] ) ) {

				// Confirmed is the default status.
				$status = 'confirmed';

				// Are we viewing a specific status?
				if ( ! empty( $_GET['status'] ) ) {
					$status = $_GET['status'];
				}

				// If supposed to get empty status...
				if ( isset( $_GET['status'] ) && ! $_GET['status'] ) {
					$status = 'none';
				}

				// Are we filtering by status?
				if ( $status ) {
					if ( 'none' == $status ) {
						$pieces['where'] .= ' AND speaker_status.post_id IS NULL';
					} else {
						$pieces['where'] .= $wpdb->prepare( ' AND speaker_status.meta_value = %s', $status );
					}
				}
			}
		}

		return $pieces;
	}*/

	/**
	 * Enqueue admin styles and scripts.
	 */
	public function enqueue_styles_scripts( $hook_suffix ) {

		// Only for the proposal review page
		if ( in_array( $hook_suffix, array( 'sessions_page_wpc-proposal-select', 'sessions_page_wpc-proposal-review' ) ) ) {
			wpcampus_speakers()->load_review_assets();
		}
	}

	/**
	 * Print custom styles in the admin <head>.
	 */
	public function print_styles() {
		global $post_type;
		switch ( $post_type ) {

			case 'profile':
				?>
				<style type="text/css">
					.wp-list-table .column-profile_thumb {
						width: 55px;
					}
					.wp-list-table img.wpc-profile-thumb {
						width: 55px;
						height: auto;
						margin: 0;
						border: 0;
					}
					.wp-list-table .wpc-profile-thumb-default {
						background: #2e3641;
						width: 55px;
						height: 55px;
					}
				</style>
				<?php
				break;
		}
	}

	/**
	 * Add the main speaker page to the admin.
	 *
	 * Set as priority 9 or higher so our speaker and profile
	 * post types can be added as subpages to this page.
	 */
	public function add_menu_pages() {
		$plugin = new self();

		// Add menu section to manage all of the speaker information.
		add_menu_page( sprintf( __( '%s: Sessions', 'wpcampus' ), 'WPCampus' ), __( 'Sessions', 'wpcampus' ), 'view_wpc_proposals', 'wpc-sessions', array( $plugin, 'print_main_sessions_page' ), 'dashicons-megaphone', 22 );

		add_submenu_page( 'wpc-sessions', sprintf( __( '%s: Speakers', 'wpcampus' ), 'WPCampus' ), __( 'Speakers', 'wpcampus' ), 'view_wpc_profiles', 'wpc-speakers', array( $plugin, 'print_main_speakers_page' ) );
		add_submenu_page( 'wpc-sessions', sprintf( __( '%s: Review Proposals', 'wpcampus' ), 'WPCampus' ), __( 'Review Proposals', 'wpcampus' ), 'review_wpc_proposals', 'wpc-proposal-review', array( $plugin, 'print_proposals_review_page' ) );
		add_submenu_page( 'wpc-sessions', sprintf( __( '%s: Select Proposals', 'wpcampus' ), 'WPCampus' ), __( 'Select Proposals', 'wpcampus' ), 'review_wpc_proposals', 'wpc-proposal-select', array( $plugin, 'print_proposals_select_page' ) );

	}

	/**
	 * Add subpages to our speakers admin menu.
	 *
	 * Separating out subpages to lower priority so
	 * speakers and profiles are listed higher in the menu.
	 */
	public function add_menu_subpages() {

		// Add taxonomy pages under speakers.
		add_submenu_page( 'wpc-sessions', __( 'Events', 'wpcampus' ), __( 'Events', 'wpcampus' ), 'manage_wpc_speakers', 'edit-tags.php?taxonomy=proposal_event&section=wpc-sessions' );
		add_submenu_page( 'wpc-sessions', __( 'Session Formats', 'wpcampus' ), __( 'Session Formats', 'wpcampus' ), 'manage_wpc_speakers', 'edit-tags.php?taxonomy=session_format&section=wpc-sessions' );
		add_submenu_page( 'wpc-sessions', __( 'Subjects', 'wpcampus' ), __( 'Subjects', 'wpcampus' ), 'manage_wpc_speakers', 'edit-tags.php?taxonomy=subjects&section=wpc-sessions' );
		add_submenu_page( 'wpc-sessions', __( 'Technical Levels', 'wpcampus' ), __( 'Technical Levels', 'wpcampus' ), 'manage_wpc_speakers', 'edit-tags.php?taxonomy=session_technical&section=wpc-sessions' );

		if ( function_exists( 'acf_add_options_page' ) ) {
			acf_add_options_page( array(
				'page_title'  => 'Settings',
				'menu_title'  => 'Settings',
				'parent_slug' => 'wpc-sessions',
				'menu_slug'   => 'wpc-sessions-settings',
				'capability'  => 'manage_options',
			));
		}
	}

	/**
	 * Filters the parent file of
	 * an admin menu sub-menu item.
	 *
	 * Allows us to set the speakers
	 * section when viewing speaker taxonomies.
	 */
	public function filter_submenu_parent( $parent_file ) {

		// Get current screen.
		$current_screen = get_current_screen();

		// Show taxonomies under "Speakers" menu.
		if ( ! empty( $current_screen->taxonomy ) && in_array( $current_screen->taxonomy, array( 'proposal_event', 'session_format', 'subjects' ) ) ) {
			if ( isset( $_GET['section'] ) && 'wpc-sessions' == $_GET['section'] ) {
				return $_GET['section'];
			}
		}

		return $parent_file;
	}

	/**
	 * Used in admin to add custom columns.
	 *
	 * If $before is true, will add columns
	 * before the title. Otherwise, will add
	 * after the title.
	 */
	public function add_admin_columns( $columns, $columns_to_add, $before = false ) {

		// Store new columns.
		$new_columns = array();

		foreach ( $columns as $key => $value ) {

			// Add new columns after the title.
			if ( ! $before ) {
				$new_columns[ $key ] = $value;
			}

			// Add custom columns after title.
			if ( 'title' == $key ) {
				foreach ( $columns_to_add as $column_key => $column_value ) {
					$new_columns[ $column_key ] = $column_value;
				}
			}

			// Add new columns before the title.
			if ( $before ) {
				$new_columns[ $key ] = $value;
			}
		}

		return $new_columns;
	}

	/**
	 * Add custom admin columns for profiles.
	 */
	public function add_profile_columns( $columns ) {

		$columns = $this->add_admin_columns( $columns, array(
			'profile_thumb' => __( 'Image', 'wpcampus' ),
		), true );

		return $this->add_admin_columns( $columns, array(
			'profile_name'      => __( 'Speaker', 'wpcampus' ),
			'profile_email'     => __( 'Email', 'wpcampus' ),
			'profile_proposals' => __( 'Proposals', 'wpcampus' ),
		));
	}

	/**
	 * Add custom admin columns for proposals.
	 */
	public function add_proposal_columns( $columns ) {
		return $this->add_admin_columns( $columns, array(
			'proposal_status'  => __( 'Status', 'wpcampus' ),
			'proposal_speaker' => __( 'Speaker(s)', 'wpcampus' ),
			'proposal_format'  => __( 'Format(s)', 'wpcampus' ),
			'proposal_assets'  => __( 'Asset(s)', 'wpcampus' ),
		));
	}

	/**
	 * Populate our custom profile columns.
	 */
	public function populate_profile_columns( $column, $post_id ) {
		global $wpdb;

		switch ( $column ) {

			case 'profile_thumb':
				$headshot = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
				if ( ! empty( $headshot ) ) :
					$display_name = sanitize_text_field( get_post_meta( $post_id, 'display_name', true ) );
					?>
					<img class="wpc-profile-thumb" src="<?php echo $headshot; ?>" alt="<?php printf( esc_attr__( 'Headshot for %s', 'wpcampus' ), $display_name ); ?>" />
					<?php
				else :
					?>
					<div class="wpc-profile-thumb-default"></div>
					<?php
				endif;
				break;

			case 'profile_name':

				// Print display name.
				$display_name = get_post_meta( $post_id, 'display_name', true );
				echo $display_name;

				// Print link to user.
				$profile_user_id = get_post_meta( $post_id, 'wordpress_user', true );
				if ( $profile_user_id > 0 ) :
					$user = get_userdata( $profile_user_id );
					if ( false !== $user ) :

						// Setup the filter URL.
						// @TODO: Not sanitized.
						$filters = $_GET;
						$filters['profile_user'] = $profile_user_id;
						$filter_url = add_query_arg( $filters, 'edit.php' );

						if ( ! empty( $display_name ) ) {
							echo '<br>';
						}

						?>
						<a href="<?php echo $filter_url; ?>"><?php echo $user->display_name; ?></a>
						<?php
					endif;
				endif;
				break;

			case 'profile_email':
				$email = get_post_meta( $post_id, 'email', true );
				if ( ! empty( $email ) ) :
					?>
					<a href="mailto:<?php echo $email; ?>"><?php echo $email; ?></a>
					<?php
				endif;
				break;

			case 'profile_proposals':

				// Get number of proposals where speaker is attached.
				$proposals_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} proposals
					INNER JOIN {$wpdb->postmeta} meta ON meta.post_id = proposals.ID AND meta.meta_key REGEXP '^speakers\_([0-9]+)\_speaker$' AND meta.meta_value = %s
					WHERE proposals.post_type = 'proposal'", $post_id ));

				if ( ! $proposals_count ) :
					echo '-';
				else :

					$filter_url = add_query_arg( array(
						'post_type'         => 'proposal',
						'proposal_speaker'  => $post_id,
					), admin_url( 'edit.php' ) );

					?>
					<a href="<?php echo $filter_url; ?>"><?php printf( _n( '%s proposal', '%s proposals', $proposals_count, 'wpcampus' ), $proposals_count ); ?></a>
					<?php
				endif;
				break;
		}
	}

	/**
	 * Populate our custom proposal columns.
	 */
	public function populate_proposal_columns( $column, $post_id ) {
		$helper = wpcampus_speakers();

		switch ( $column ) {

			case 'proposal_status':

				/*
				 * Get status.
				 *
				 * Options:
				 *  - confirmed
				 *  - declined
				 *  - selected
				 *  - submitted (default, if blank)
				 */
				$proposal_status = $helper->get_proposal_status( $post_id );

				// Print status.
				$helper->print_proposal_status_label( $post_id, $proposal_status );

				break;

			case 'proposal_speaker':

				// Get for each speaker.
				$profiles = $helper->get_profiles(array(
					'by_proposal' => $post_id,
				));

				$proposal_status = $helper->get_proposal_status( $post_id );

				if ( ! empty( $profiles ) ) :

					$speaker_count = 0;

					foreach ( $profiles as $profile ) :

						// Setup the filter URL.
						// @TODO: Not sanitized.
						$filters = $_GET;
						$filters['proposal_speaker'] = $profile->ID;
						$filter_url = add_query_arg( $filters, 'edit.php' );

						echo $speaker_count > 0 ? '<br />' : null;

						?>
						<a href="<?php echo $filter_url; ?>"><?php echo $profile->display_name; ?></a><br><a style="text-decoration:underline;" href="<?php echo get_edit_post_link( $profile->ID ); ?>"><?php _e( 'Edit', 'wpcampus' ); ?></a>
						<?php

						if ( in_array( $proposal_status, array( 'selected' ) ) ) {
							$proposal_confirmation_url = $helper->get_proposal_confirmation_url( $post_id, $profile->ID );
							if ( ! empty( $proposal_confirmation_url ) ) :
								?>/ <a style="text-decoration:underline;" href="<?php echo $proposal_confirmation_url; ?>" target="_blank"><strong><?php _e( 'Confirm', 'wpcampus-speakers' ); ?></strong></a><?php
							endif;
						}

						$speaker_count++;

					endforeach;
				endif;
				break;

			case 'proposal_format':

				$is_selected = false;

				$formats = get_the_terms( $post_id, 'session_format' );
				if ( ! empty( $formats ) ) {
					$is_selected = true;
				} else {
					$formats = get_the_terms( $post_id, 'preferred_session_format' );
				}

				if ( ! empty( $formats ) ) {

					$format_str = array_map( function($a) {
						return $a->name;
					}, $formats );

					if ( ! $is_selected ) {
						echo '<em>' . implode( '<br>', $format_str ) . '</em>';
					} else {
						echo implode( '<br>', $format_str );
					}

					break;
				}

				break;

			case 'proposal_assets':

				// Get the video.
				$proposal_video_id  = $helper->get_proposal_video_id( $post_id );
				$proposal_video_url = $helper->get_proposal_video_url( $post_id, $proposal_video_id );

				if ( empty( $proposal_video_id ) && empty( $proposal_video_url ) ) :
					?>
					<em><?php _e( 'No video', 'wpcampus' ); ?></em>
					<?php
				else :

					if ( ! empty( $proposal_video_url ) ) :
						?>
						<a href="<?php echo $proposal_video_url; ?>" target="_blank"><?php _e( 'View video', 'wpcampus' ); ?></a>
						<?php
					endif;

					if ( ! empty( $proposal_video_id ) ) :

						// Get URLs.
						$edit_video_url = get_edit_post_link( $proposal_video_id );

						if ( ! empty( $proposal_video_url ) ) {
							echo '<br>';
						}

						?>
					    <a href="<?php echo $edit_video_url; ?>" target="_blank"><?php _e( 'Edit video', 'wpcampus' ); ?></a>
						<?php
					endif;
				endif;

				echo '<br>';

				// Get the slides.
				$session_slides_url = $helper->get_proposal_slides_url( $post_id );

				if ( empty( $session_slides_url ) ) :
					?>
					<em><?php _e( 'No slides', 'wpcampus' ); ?></em>
					<?php
				else :
					?>
					<a href="<?php echo $session_slides_url; ?>" target="_blank"><?php _e( 'View slides', 'wpcampus' ); ?></a>
					<?php
				endif;

				break;
		}
	}

	/**
	 * We can disable the months dropdown for posts table.
	 *
	 * @args    $disable - bool - Whether to disable the drop-down. Default false.
	 * @args    $post_type - string - The post type.
	 */
	public function disable_months_dropdown( $disable, $post_type ) {

		// Disable for specific post types.
		if ( in_array( $post_type, array( 'profile', 'proposal' ) ) ) {
			return true;
		}

		return $disable;
	}

	/**
	 * Adds a dropdown to filter proposals.
	 */
	public function add_proposal_filters( $post_type ) {

		switch ( $post_type ) {

			case 'proposal':

				$post_status = ! empty( $_REQUEST['post_status'] ) ? $_REQUEST['post_status'] : '';

				// Don't filter in the trash.
				if ( 'trash' == $post_status ) {
					break;
				}

				$this->add_proposal_status_filter( $post_status );
				$this->add_proposal_event_filter( $post_status );

				break;
		}
	}

	/**
	 * Filter the post row actions.
	 */
	public function filter_post_row_actions( $actions, $post ) {

		// Only for proposals.
		if ( 'proposal' != $post->post_type ) {
			return $actions;
		}

		// @TODO: Only add for confirmed proposals who have been selected?
		/*$proposal_status = wpcampus_speakers()->get_proposal_status( $post->ID );
		if ( 'confirmed' != $proposal_status ) {
			return $actions;
		}*/

		// Only for proposals assigned to an event.
		/*$events = wp_get_object_terms( $post->ID, 'proposal_event', array( 'fields' => 'ids' ) );
		if ( empty( $events ) ) {
			return $actions;
		}*/

		$permalink = get_permalink( $post->ID );
		if ( empty( $permalink ) ) {
			return $actions;
		}

		$actions['view'] = '<a href="' . $permalink . '" target="_blank">' . __( 'View', 'wpcampus' ) . '</a>';

		return $actions;
	}

	/**
	 * Add the proposal status filter.
	 *
	 * @args    $post_status - string - the current post status.
	 */
	public function add_proposal_status_filter( $post_status ) {

		$proposal_status_choices = array(
			'confirmed' => array(
				//'count' => 0,
				'label' => __( 'Confirmed', 'wpcampus' ),
			),
			'declined'  => array(
				//'count' => 0,
				'label' => __( 'Declined', 'wpcampus' ),
			),
			'selected'  => array(
				//'count' => 0,
				'label' => __( 'Selected', 'wpcampus' ),
			),
			'backup'  => array(
				//'count' => 0,
				'label' => __( 'Backup', 'wpcampus' ),
			),
			'maybe'  => array(
				//'count' => 0,
				'label' => __( 'Maybe', 'wpcampus' ),
			),
			'no'  => array(
				//'count' => 0,
				'label' => __( 'No', 'wpcampus' ),
			),
			'submitted' => array(
				//'count' => 0,
				'label' => __( 'Submitted', 'wpcampus' ),
			),
		);

		/*$db_counts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT meta_keys.meta_value AS status, COUNT(*) AS count
				FROM {$wpdb->postmeta} meta_keys
				INNER JOIN {$wpdb->posts} posts ON posts.ID = meta_keys.post_id AND posts.post_type = 'proposal' AND IF ( %s != '', posts.post_status = %s, true )
				WHERE meta_keys.meta_key = 'proposal_status' GROUP BY meta_keys.meta_value",
				$post_status, $post_status
			)
		);

		if ( ! empty( $db_counts ) ) {
			foreach ( $db_counts as $count ) {
				if ( empty( $count->status ) ) {
					continue;
				}
				if ( ! isset( $proposal_status_choices[ $count->status ] ) ) {
					continue;
				}
				if ( empty( $count->count ) ) {
					continue;
				}
				$proposal_status_choices[ $count->status ]['count'] = $count->count;
			}
		}*/

		$selected_proposal_status = ! empty( $_GET['proposal_status'] ) ? sanitize_text_field( $_GET['proposal_status'] ) : null;

		?>
		<select name="proposal_status">
			<option value=""><?php _e( 'Sort by status', 'wpcampus' ); ?></option>
			<?php

			foreach ( $proposal_status_choices as $value => $choice ) :
				?>
				<option value="<?php echo $value; ?>"<?php selected( $selected_proposal_status, $value ); ?>><?php echo $choice['label']; ?></option>
				<?php
			endforeach;

			?>
		</select>
		<?php
	}

	/**
	 * Add the proposal event filter.
	 *
	 * @args    $post_status - string - the current post status.
	 */
	public function add_proposal_event_filter( $post_status ) {

		$proposal_events = get_terms( array(
			'taxonomy'   => 'proposal_event',
			'hide_empty' => true,
		));

		$selected_proposal_event = ! empty( $_GET['proposal_event'] ) ? sanitize_text_field( $_GET['proposal_event'] ) : null;

		?>
		<select name="proposal_event">
			<option value=""><?php _e( 'Sort by event', 'wpcampus' ); ?></option>
			<?php

			foreach ( $proposal_events as $term ) :
				?>
				<option value="<?php echo $term->slug; ?>"<?php selected( $selected_proposal_event, $term->slug ); ?>><?php echo $term->name; ?></option>
				<?php
			endforeach;

			?>
		</select>
		<?php
	}

	/**
	 * Add our custom admin meta boxes.
	 */
	public function add_meta_boxes( $post_type, $post ) {

		switch ( $post_type ) {

			case 'profile':

				add_meta_box(
					'wpcampus-speakers-proposal',
					__( 'Speaker Proposal(s)', 'wpcampus' ),
					array( $this, 'print_meta_boxes' ),
					$post_type,
					'normal',
					'high'
				);
				break;

			case 'proposal':

				add_meta_box(
					'wpcampus-proposal-slug',
					sprintf( __( '%s: Session URL Slug', 'wpcampus' ), 'WPCampus' ),
					array( $this, 'print_meta_boxes' ),
					$post_type,
					'wpc_after_title',
					'high'
				);

				// Add a meta box to link to the session info.
				$speaker_ids = wpcampus_speakers()->get_proposal_speaker_ids( $post->ID );
				if ( ! empty( $speaker_ids ) ) {
					add_meta_box(
						'wpcampus-proposal-actions',
						sprintf( __( '%s: Actions', 'wpcampus' ), 'WPCampus' ),
						array( $this, 'print_meta_boxes' ),
						$post_type,
						'side',
						'high',
						array(
							'profiles' => $speaker_ids,
						)
					);
				}

				// WPCampus Speaker Information.
				add_meta_box(
					'wpcampus-speakers-details',
					__( 'Proposal: Speaker(s) Details', 'wpcampus' ),
					array( $this, 'print_meta_boxes' ),
					$post_type,
					'normal',
					'high'
				);

				// Other information from form submission.
				add_meta_box(
					'wpcampus-proposal-submission',
					__( 'Proposal: Submission Details', 'wpcampus' ),
					array( $this, 'print_meta_boxes' ),
					$post_type,
					'normal',
					'high'
				);

				break;
		}
	}

	/**
	 * Remove meta boxes.
	 */
	public function remove_meta_boxes() {

		// We use ACF to manage events.
		remove_meta_box( 'tagsdiv-proposal_event', 'proposal', 'side' );

		// We don't need the profile slug.
		remove_meta_box( 'slugdiv', 'profile', 'normal' );

		// We add our own.
		remove_meta_box( 'slugdiv', 'proposal', 'normal' );

	}

	/**
	 * Prints the content in our custom admin meta boxes.
	 */
	public function print_meta_boxes( $post, $metabox ) {

		switch ( $metabox['id'] ) {

			case 'wpcampus-proposal-instructions':
				$this->print_proposal_instructions();
				break;

			case 'wpcampus-proposal-slug':
				$this->print_proposal_slug_mb( $post );
				break;

			case 'wpcampus-proposal-actions':
				$this->print_proposal_actions_mb( $post->ID, $metabox['args'] );
				break;

			case 'wpcampus-speakers-proposal':
				$this->print_speaker_proposals_table( $post->ID );
				break;

			case 'wpcampus-speakers-details':
				$this->print_proposal_speaker_details( $post->ID );
				break;

			case 'wpcampus-proposal-submission':
				$this->print_proposal_submission_details( $post->ID );
				break;
		}
	}

	/**
	 * Print instructions to manage proposals.
	 */
	public function print_proposal_instructions() {

		$edit_url = admin_url( 'edit.php' );

		$view_proposal_url = add_query_arg( 'post_type', 'proposal', $edit_url );
		$view_profile_url  = add_query_arg( 'post_type', 'profile', $edit_url );

		?>
		<style type="text/css">
			#wpcampus-proposal-instructions { margin: 15px 0 0px 0; }
			#wpcampus-proposal-instructions ul { list-style: disc; margin: 0 0 0 20px; }
			.wpc-inside-mb {
				background: rgba(0,115,170,0.07);
				padding: 18px;
				color: #000;
				margin: -6px -12px -12px -12px;
			}
		</style>
		<div class="wpc-inside-mb">
			<ul>
				<li>All of our session information is managed on our main site as <a href="<?php echo $view_proposal_url; ?>" target="_blank">proposals</a> and <a href="<?php echo $view_profile_url; ?>" target="_blank">speaker profiles</a>.</li>
				<li>Use "The Speaker(s)" meta box to select the <a href="<?php echo $view_profile_url; ?>" target="_blank">speaker profiles</a> for this proposal.</li>
				<li>To make this proposal available for an event, select the event and mark the "Proposal Status" as "Confirmed".</li>
				<li>When assigned to a session on an event website, the proposal information will auto-populate the schedule where needed, including speaker information.</li>
				<li>When the session's video is available, you can assign it to the proposal in "The Asset(s)" meta box.</li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Add our own metabox to manage the proposal slug.
	 */
	public function print_proposal_slug_mb( $post ) {

		// Check to make sure proposal has a slug.
		if ( empty( $post->post_name ) && ! empty( $post->post_title ) ) {

			$post_name = sanitize_title_with_dashes( $post->post_title );
			if ( ! empty( $post_name ) ) {

				$post_name = wp_unique_post_slug( $post_name, $post->ID, $post->post_status, $post->post_type, $post->post_parent );

				if ( ! empty( $post_name ) ) {

					$update = wp_update_post( array(
						'ID'        => $post->ID,
						'post_name' => $post_name,
					));

					if ( $update == $post->ID ) {
						$post->post_name = $post_name;
					}
				}
			}
		}

		?>
		<style type="text/css">
			#wpcampus-proposal-slug { margin: 15px 0 0px 0; }
			#post_name { min-width: 300px; }
		</style>
		<?php

		echo get_bloginfo( 'url' ) . '/session/ ';

		post_slug_meta_box( $post );

	}

	/**
	 * Print the proposal actions.
	 */
	public function print_proposal_actions_mb( $proposal_id, $args = array() ) {
		$profiles = ! empty( $args['profiles'] ) ? $args['profiles'] : array();
		if ( ! empty( $profiles ) ) :
			$view_url = get_permalink( $proposal_id );
			?>
			<div style="background:rgba(0,115,170,0.07);padding:13px 18px 15px;color:#000;margin:-6px -12px -12px -12px;">
				<a class="button button-primary button-large" style="display:block;text-align:center;margin:5px 0;" href="<?php echo $view_url; ?>" target="_blank"><?php _e( 'View session', 'wpcampus-speakers' ); ?></a>
				<?php

				foreach ( $profiles as $profile_id ) :

					$edit_url = get_edit_post_link( $profile_id );
					$display_name = get_post_meta( $profile_id, 'display_name', true );

					?>
					<a class="button button-secondary button-large" style="display:block;text-align:center;margin:5px 0;" href="<?php echo $edit_url; ?>" target="_blank"><?php printf( __( 'Edit %s', 'wpcampus-speakers' ), $display_name ); ?></a>
					<?php
				endforeach;

				?>
			</div>
			<?php
		endif;
	}

	/**
	 * Print a table of info for a speaker's proposal(s).
	 */
	public function print_speaker_proposals_table( $speaker_id ) {
		global $wpdb;

		$helper = wpcampus_speakers();

		$proposals = $wpdb->get_results(
			$wpdb->prepare( "SELECT ID, post_title, post_status,
				proposalm2.meta_value AS proposal_status,
				eventm.meta_value AS event_id, eventt.name AS event_name, eventt.slug AS event_slug
				FROM {$wpdb->posts} proposal
				INNER JOIN {$wpdb->postmeta} proposalm1 ON proposalm1.post_id = proposal.ID AND proposalm1.meta_key REGEXP '^speakers\_([0-9]+)\_speaker$' AND proposalm1.meta_value = %s
				LEFT JOIN {$wpdb->postmeta} proposalm2 ON proposalm2.post_id = proposal.ID AND proposalm2.meta_key = 'proposal_status'
				LEFT JOIN {$wpdb->postmeta} eventm ON eventm.post_id = proposal.ID AND eventm.meta_key = 'proposal_event'
				LEFT JOIN {$wpdb->terms} eventt ON eventt.term_id = eventm.meta_value
				WHERE proposal.post_type = 'proposal' AND proposal.post_status != 'trash'",
				$speaker_id
			)
		);

		if ( empty( $proposals ) ) :
			?>
			<p><em><?php _e( 'This speaker has no proposals.', 'wpcampus' ); ?></em></p>
			<?php
			return;
		endif;

		?>
		<style type="text/css">
			table.wpc-speaker-proposals {
				width: 100%;
				text-align: left;
				margin: 0;
				border: 0;
				padding: 0;
			}
			table.wpc-speaker-proposals th,
			table.wpc-speaker-proposals td {
				padding: 0 10px 0 0;
			}
		</style>
		<table class="wpc-speaker-proposals">
			<thead>
				<tr>
					<th><?php _e( 'Title', 'wpcampus' ); ?></th>
					<th><?php _e( 'Status', 'wpcampus' ); ?></th>
					<th><?php _e( 'Event', 'wpcampus' ); ?></th>
					<th><?php _e( 'Speaker', 'wpcampus' ); ?>(s)</th>
				</tr>
			</thead>
			<tbody>
				<?php

				foreach ( $proposals as $proposal ) :

					?>
					<tr>
						<td><a href="<?php echo get_edit_post_link( $proposal->ID ); ?>"><?php echo $proposal->post_title; ?></a></td>
						<td>
							<?php

							$helper->print_proposal_status_label( $proposal->ID, $proposal->proposal_status );

							if ( 'publish' != $proposal->post_status ) {
								echo " <strong>({$proposal->post_status})</strong>";
							}

							?>
						</td>
						<td>
							<?php

							if ( ! empty( $proposal->event_name ) ) :

								if ( ! empty( $proposal->event_slug ) ) :

									$filter_url = add_query_arg( array(
										'post_type'      => 'proposal',
										'proposal_event' => $proposal->event_slug,
									), admin_url( 'edit.php' ) );

									?>
									<a href="<?php echo $filter_url; ?>"><?php echo $proposal->event_name; ?></a>
									<?php
								else :
									echo $proposal->event_name;
								endif;
							endif;

							?>
						</td>
						<td>
							<?php

							// Gets this proposal's speaker IDs.
							$proposal_speaker_ids = $helper->get_proposal_speaker_ids( $proposal->ID );

							if ( ! empty( $proposal_speaker_ids ) ) :

								$speaker_count = 0;

								foreach ( $proposal_speaker_ids as $this_speaker_id ) :

									$this_speaker_id = ! empty( $this_speaker_id ) && is_numeric( $this_speaker_id ) ? (int) $this_speaker_id : 0;
									if ( $this_speaker_id > 0 ) :

										$speaker_display_name = get_post_meta( $this_speaker_id, 'display_name', true );
										if ( ! empty( $speaker_display_name ) ) :

											echo $speaker_count > 0 ? '<br />' : null;

											if ( $this_speaker_id != $speaker_id ) :
												?>
												<a href="<?php echo get_edit_post_link( $this_speaker_id ); ?>"><?php echo $speaker_display_name; ?></a>
												<?php
											else :
												echo $speaker_display_name;
											endif;
										endif;
									endif;

									$speaker_count++;

								endforeach;
							endif;

							?>
						</td>
					</tr>
					<?php
				endforeach;

				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Adds instructions to the admin thumbnail meta box.
	 */
	public function filter_admin_post_thumbnail_html( $content, $post_id ) {

		// Show instructions for speaker photo.
		if ( 'profile' == get_post_type( $post_id ) ) {
			$content .= '<div class="wp-ui-highlight" style="padding:10px;margin:15px 0 5px 0;">' . __( "Please load the speaker's photo as a featured image. The image needs to be at least 200px wide.", 'wpcampus' ) . '</div>';
		}

		return $content;
	}

	/**
	 * Print the WPCampus speaker information,
	 * which is specific to a proposal and event.
	 *
	 * @TODO: Update to work with new system
	 * AND to work with multiple speakers.
	 *
	 * @access  public
	 * @param   int - $post_id - the ID of the event
	 * @return  void
	 */
	public function print_proposal_speaker_details( $post_id ) {

		$helper = wpcampus_speakers();

		// Get the information.
		$technology = get_post_meta( $post_id, 'wpc_speaker_technology', true );
		$video_release = get_post_meta( $post_id, 'wpc_speaker_video_release', true );
		$unavailability = get_post_meta( $post_id, 'wpc_speaker_unavailability', true );
		$arrival = get_post_meta( $post_id, 'wpc_speaker_arrival', true );
		$special_requests = get_post_meta( $post_id, 'wpc_speaker_special_requests', true );

		?>
		<p>
			<strong><?php _e( 'Status:', 'wpcampus' ); ?></strong><br />
			<?php

			$proposal_status = $helper->get_proposal_status( $post_id );

			// Print the proposal's status.
			$helper->print_proposal_status_label( $post_id, $proposal_status );

			// Print confirmation URL no matter what for admin sake.
			if ( in_array( $proposal_status, array( 'selected', 'declined', 'confirmed' ) ) ) :

				// Get for each speaker.
				$profiles = $helper->get_profiles(array(
					'by_proposal' => $post_id,
				));

				if ( ! empty( $profiles ) ) :
					foreach ( $profiles as $profile ) :

						$proposal_confirmation_url = $helper->get_proposal_confirmation_url( $post_id, $profile->ID );
						if ( ! empty( $proposal_confirmation_url ) ) :

							?>
							<br><a href="<?php echo $proposal_confirmation_url; ?>" target="_blank"><?php printf( __( 'Confirmation form for %s', 'wpcampus-speakers' ), $profile->title ); ?></a>
							<?php
						endif;
					endforeach;
				endif;
			endif;

			?>
		</p>
		<p><strong><?php _e( 'Technology:', 'wpcampus-speakers' ); ?></strong><br /><?php echo $technology ?: '<em>' . __( "This speaker did not specify which technology they'll use.", 'wpcampus-speakers' ) . '</em>'; ?></p>
		<p><strong><?php _e( 'Video Release:', 'wpcampus-speakers' ); ?></strong><br /><?php echo $video_release ?: '<em>' . __( 'This speaker did not specify their video release agreement.', 'wpcampus-speakers' ) . '</em>'; ?></p>
		<p><strong><?php _e( 'Unavailability:', 'wpcampus-speakers' ); ?></strong><br /><?php echo $unavailability ?: '<em>' . __( 'This speaker did not specify any unavailability.', 'wpcampus-speakers' ) . '</em>'; ?></p>
		<p><strong><?php _e( 'Arrival:', 'wpcampus-speakers' ); ?></strong><br /><?php echo $arrival ?: '<em>' . __( 'This speaker did not specify their arrival time.', 'wpcampus-speakers' ) . '</em>'; ?></p>
		<p><strong><?php _e( 'Special Requests:', 'wpcampus-speakers' ); ?></strong><br /><?php echo $special_requests ?: '<em>' . __( 'This speaker did not specify any special requests.', 'wpcampus-speakers' ) . '</em>'; ?></p>
		<?php
	}

	/**
	 * Print information from the submission
	 * form entry for this proposal.
	 *
	 * @param   int - $post_id - the ID of the proposal.
	 * @return  void
	 */
	public function print_proposal_submission_details( $post_id ) {

		// Get the information.
		$entry_id = wpcampus_speakers()->get_proposal_gf_entry_id( $post_id );

		if ( empty( $entry_id ) ) :
			?>
			<p><em><?php _e( 'This proposal is not tied to a form submission.', 'wpcampus-speakers' ); ?></em></p>
			<?php
		else :

			$entry_url = add_query_arg(
				array(
					'page' => 'gf_entries',
					'view' => 'entry',
					'id'   => 30,
					'lid'  => $entry_id,
				),
				admin_url( 'admin.php' )
			);

			?>
			<p><strong><?php _e( 'Form ID:', 'wpcampus-speakers' ); ?></strong> <a href="<?php echo $entry_url; ?>"><?php echo $entry_id; ?></a></p>
			<?php
		endif;
	}

	/**
	 * Print the main page of the speakers section.
	 */
	public function print_main_sessions_page() {
		?>
		<div class="wrap">
			<h1><?php echo get_admin_page_title(); ?></h1>
			<p><em>Coming soon.</em></p>
		</div>
		<?php
	}

	/**
	 *
	 */
	public function print_main_speakers_page() {
		$helper = wpcampus_speakers();

		$event_id = $helper->get_proposal_event();
		$event = $event_id > 0 ? get_term( $event_id, 'proposal_event' ) : null;

		if ( $event_id > 0 ) {
			$event = get_term( $event_id, 'proposal_event' );
		}

		?>
		<div class="wrap">
			<h1><?php echo get_admin_page_title(); ?></h1>
			<?php

			if ( ! empty( $event->name ) ) :
				?>
				<h2><?php echo $event->name; ?></h2>
				<?php
			endif;

			if ( current_user_can( 'download_wpc_profile_csv' ) ) :

				$admin_url = add_query_arg( 'page', 'wpc-speakers', admin_url( 'admin.php' ) );

				$all_confirmed_speakers_mailchimp_url = add_query_arg( array(
					'proposal_status'    => 'confirmed',
					'proposal_event'     => 'all',
					'confirmation_email' => 1,
				), $admin_url );

				$confirmed_speakers_mailchimp_url = add_query_arg( array(
					'proposal_status'    => 'confirmed',
					'confirmation_email' => 1,
				), $admin_url );

				$selected_speakers_mailchimp_url = add_query_arg( array(
					'proposal_status'    => 'selected,declined,confirmed',
					'confirmation_email' => 1,
				), $admin_url );

				$selected_speakers_only_mailchimp_url = add_query_arg( array(
					'proposal_status'    => 'selected',
					'confirmation_email' => 1,
				), $admin_url );

				$declined_speakers_mailchimp_url = add_query_arg( array(
					'proposal_status'    => 'not_selected',
					'confirmation_email' => 1,
				), $admin_url );

				$all_confirmed_speakers_mailchimp_csv = wp_nonce_url( $all_confirmed_speakers_mailchimp_url, 'download_profile_csv', 'download_profile_csv_nonce' );
				$confirmed_speakers_mailchimp_csv = wp_nonce_url( $confirmed_speakers_mailchimp_url, 'download_profile_csv', 'download_profile_csv_nonce' );
				$selected_speakers_mailchimp_csv = wp_nonce_url( $selected_speakers_mailchimp_url, 'download_profile_csv', 'download_profile_csv_nonce' );
				$selected_speakers_only_mailchimp_csv = wp_nonce_url( $selected_speakers_only_mailchimp_url, 'download_profile_csv', 'download_profile_csv_nonce' );
				$declined_speakers_mailchimp_csv = wp_nonce_url( $declined_speakers_mailchimp_url, 'download_profile_csv', 'download_profile_csv_nonce' );

				$download_profile_headshots_url = add_query_arg( array(
					'proposal_status'            => 'selected,confirmed',
					'download_profile_headshots' => 1,
				), $admin_url );

				$download_profile_headshots_csv = wp_nonce_url( $download_profile_headshots_url, 'download_profile_headshots', 'download_profile_headshots_nonce' );

				?>
				<h3>Download Speaker Information</h3>
				<ul class="wpc-list">
					<li><a href="<?php echo $all_confirmed_speakers_mailchimp_csv; ?>">Download speaker information for confirmed proposals for ALL events</a></li>
					<li><a href="<?php echo $confirmed_speakers_mailchimp_csv; ?>">Download speaker information for confirmed proposals</a></li>
					<li><a href="<?php echo $selected_speakers_mailchimp_csv; ?>">Download speaker information for selected, confirmed, and declined proposals</a></li>
					<li><a href="<?php echo $selected_speakers_only_mailchimp_csv; ?>">Download speaker information for selected proposals</a></li>
					<li><a href="<?php echo $declined_speakers_mailchimp_csv; ?>">Download speaker information for proposals that were not selected (and where speakers were not selected at all)</a></li>
					<li><a href="<?php echo $download_profile_headshots_csv; ?>">Download speaker headshots</a></li>
				</ul>
				<?php /*<ul class="wpc-list">
					<li><a href="<?php echo $confirmed_speakers_csv; ?>">Download confirmed speaker information</a></li>
					<li><a href="<?php echo $selected_speakers_csv; ?>">Download selected speaker information</a></li>
				</ul>
				<ul class="wpc-list">
					<li><a href="<?php echo $declined_speakers_csv; ?>">Download declined speaker information</a></li>
					<li><a href="<?php echo $backup_speakers_csv; ?>">Download backup speaker information</a></li>
					<li><a href="<?php echo $maybe_speakers_csv; ?>">Download maybe speaker information</a></li>
					<li><a href="<?php echo $no_speakers_csv; ?>">Download no speaker information</a></li>
				</ul>
				<ul class="wpc-list">
					<li><a href="<?php echo $all_speakers_csv; ?>">Download all speaker information</a></li>
				</ul>*/ ?>
				<style type="text/css">
					ul.wpc-list {
						list-style: disc;
						margin: 0 0 1.5em 2em;
					}
					ul.wpc-list li {
						margin: 2px 0;
					}
				</style>
				<?php
			endif;

			if ( current_user_can( 'view_wpc_profile_emails' ) ) :

				$confirmed_emails = $helper->get_profile_emails( array( 'proposal_status' => 'confirmed' ) );

				?>
				<h3>Email address(es) for selected speakers who <strong>have</strong> confirmed (<?php echo count( $confirmed_emails ); ?>)</h3>
				<p style="width:100%;word-wrap:break-word;"><?php echo implode( ',', $confirmed_emails ); ?></p>
				<?php

				$not_confirmed_emails = $helper->get_profile_emails( array( 'proposal_status' => 'selected' ) );

				?>
				<h3>Email address(es) for selected speakers who <strong>have not</strong> confirmed (<?php echo count( $not_confirmed_emails ); ?>)</h3>
				<p style="width:100%;word-wrap:break-word;"><?php echo implode( ',', $not_confirmed_emails ); ?></p>
				<?php

				$not_selected_emails = $helper->get_profile_emails( array( 'proposal_status' => 'not_selected' ) );

				?>
				<h3>Email address(es) for speakers who were not selected (<?php echo count( $not_selected_emails ); ?>)</h3>
				<p style="width:100%;word-wrap:break-word;"><?php echo implode( ',', $not_selected_emails ); ?></p>
				<?php

				$submitted_emails = $helper->get_profile_emails();

				?>
				<h3>Email address(es) for people who submitted proposals (<?php echo count( $submitted_emails ); ?>)</h3>
				<p style="width:100%;word-wrap:break-word;"><?php echo implode( ',', $submitted_emails ); ?></p>
				<?php
			endif;

			?>
		</div>
		<?php
	}

	/**
	 *
	 */
	public function print_proposals_review_page() {

		$event_id = wpcampus_speakers()->get_proposal_event();
		$event = $event_id > 0 ? get_term( $event_id, 'proposal_event' ) : null;

		?>
		<div class="wrap">
			<div class="wpc-proposals-table-header">
				<h1><?php echo get_admin_page_title(); ?></h1>
				<button class="button button-primary wpc-proposals-table-update wpc-proposals-table-update-all">Update all tables</button>
			</div>
			<?php

			if ( ! empty( $event->name ) ) :
				?>
				<h2><?php echo $event->name; ?></h2>
				<?php
			endif;

			wpcampus_speakers()->print_proposals_review_table();

			?>
		</div>
		<?php
	}

	/**
	 *
	 */
	public function print_proposals_select_page() {
		?>
		<div class="wrap">
			<div class="wpc-proposals-table-header">
				<h1><?php echo get_admin_page_title(); ?></h1>
				<button class="button button-primary wpc-proposals-table-update wpc-proposals-table-update-all">Update all tables</button>
			</div>
			<?php wpcampus_speakers()->print_proposals_select_table(); ?>
		</div>
		<?php
	}
}
WPCampus_Speakers_Admin::register();
