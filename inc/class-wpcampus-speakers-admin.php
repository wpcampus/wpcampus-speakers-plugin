<?php
/**
 * PHP class that holds the admin
 * functionality for the plugin.
 *
 * @category    Class
 * @package     WPCampus Speakers
 */
class WPCampus_Speakers_Admin {

	/**
	 * We don't need to instantiate this class.
	 */
	protected function __construct() {}

	/**
	 * Registers all of our hooks and what not.
	 */
	public static function register() {
		$plugin = new self();

		// Print styles in the admin <head>.
		add_action( 'admin_print_styles-edit.php', array( $plugin, 'print_styles' ) );

		// Add items to the admin menu.
		add_action( 'admin_menu', array( $plugin, 'add_menu_pages' ) );
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
	 * Add pages to the admin.
	 */
	public function add_menu_pages() {
		$plugin = new self();

		// Add menu section to manage all of the speaker information.
		add_menu_page( __( 'Speakers', 'wpcampus' ), __( 'Speakers', 'wpcampus' ), 'manage_wpc_speakers', 'wpc-speakers', array( $plugin, 'print_speakers_main_page' ), 'dashicons-megaphone', 22 );

		// Add taxonomy pages under speakers.
		add_submenu_page( 'wpc-speakers', __( 'Events', 'wpcampus' ), __( 'Events', 'wpcampus' ), 'manage_categories', 'edit-tags.php?taxonomy=proposal_event&section=wpc-speakers' );
		add_submenu_page( 'wpc-speakers', __( 'Session Types', 'wpcampus' ), __( 'Session Types', 'wpcampus' ), 'manage_categories', 'edit-tags.php?taxonomy=session_type&section=wpc-speakers' );
		add_submenu_page( 'wpc-speakers', __( 'Subjects', 'wpcampus' ), __( 'Subjects', 'wpcampus' ), 'manage_categories', 'edit-tags.php?taxonomy=subjects&section=wpc-speakers' );
		add_submenu_page( 'wpc-speakers', __( 'Technical Levels', 'wpcampus' ), __( 'Technical Levels', 'wpcampus' ), 'manage_categories', 'edit-tags.php?taxonomy=session_technical&section=wpc-speakers' );

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
		if ( ! empty( $current_screen->taxonomy ) && in_array( $current_screen->taxonomy, array( 'proposal_event', 'session_type', 'subjects' ) ) ) {
			if ( isset( $_GET['section'] ) && 'wpc-speakers' == $_GET['section'] ) {
				return $_GET['section'];
			}
		}

		return $parent_file;
	}

	/**
	 * Print the main page of the speakers section.
	 */
	public function print_speakers_main_page() {}

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
			'profile_user'      => __( 'User', 'wpcampus' ),
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
			'proposal_video'   => __( 'Video', 'wpcampus' ),
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
					?><img class="wpc-profile-thumb" src="<?php echo $headshot; ?>" alt="<?php printf( esc_attr__( 'Headshot for %s', 'wpcampus' ), $display_name ); ?>" /><?php
				else :
					?><div class="wpc-profile-thumb-default"></div><?php
				endif;
				break;

			case 'profile_name':
				echo get_post_meta( $post_id, 'display_name', true );
				break;

			case 'profile_user':
				$profile_user_id = get_post_meta( $post_id, 'wordpress_user', true );
				if ( $profile_user_id > 0 ) :
					$user = get_userdata( $profile_user_id );
					if ( false !== $user ) :

						// Setup the filter URL.
						$filters = $_GET;
						$filters['profile_user'] = $profile_user_id;
						$filter_url = add_query_arg( $filters, 'edit.php' );

						?><a href="<?php echo $filter_url; ?>"><?php echo $user->display_name; ?></a><?php
					endif;
				endif;
				break;

			case 'profile_email':
				$email = get_post_meta( $post_id, 'email', true );
				if ( ! empty( $email ) ) :
					?><a href="mailto:<?php echo $email; ?>"><?php echo $email; ?></a><?php
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

					?><a href="<?php echo $filter_url; ?>"><?php printf( _n( '%s proposal', '%s proposals', $proposals_count, 'wpcampus' ), $proposals_count ); ?></a><?php

				endif;
				break;
		}
	}

	/**
	 * Populate our custom proposal columns.
	 */
	public function populate_proposal_columns( $column, $post_id ) {

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
				$proposal_status = wpcampus_speakers()->get_proposal_status( $post_id );

				// Print status.
				wpcampus_speakers()->print_proposal_status_label( $post_id, $proposal_status );

				// Add link to confirmation form if not confirmed by speaker.
				if ( ! wpcampus_speakers()->has_proposal_been_confirmed( $post_id, $proposal_status ) ) :

					$proposal_confirmation_url = wpcampus_speakers()->get_proposal_confirmation_url( $post_id, true );
					if ( ! empty( $proposal_confirmation_url ) ) :

						?>
						<br><a style="text-decoration:underline;" href="<?php echo $proposal_confirmation_url; ?>" target="_blank"><?php _e( 'Confirmation', 'wpcampus-speakers' ); ?></a><br>
						<?php
					endif;
				endif;

				break;

			case 'proposal_speaker':

				$speaker_ids = wpcampus_speakers()->get_proposal_speaker_ids( $post_id );

				if ( ! empty( $speaker_ids ) ) :

					$speaker_count = 0;

					foreach( $speaker_ids as $speaker_id ) :

						$speaker_id = ! empty( $speaker_id ) && is_numeric( $speaker_id ) ? (int) $speaker_id : 0;
						if ( $speaker_id > 0 ) :

							$speaker_display_name = get_post_meta( $speaker_id, 'display_name', true );
							if ( ! empty( $speaker_display_name ) ) :

								// Setup the filter URL.
								$filters = $_GET;
								$filters['proposal_speaker'] = $speaker_id;
								$filter_url = add_query_arg( $filters, 'edit.php' );

								echo $speaker_count > 0 ? '<br />' : null;

								?>
								<a href="<?php echo $filter_url; ?>"><?php echo $speaker_display_name; ?></a> (<a href="<?php echo get_edit_post_link( $speaker_id ); ?>"><?php _e( 'Edit', 'wpcampus' ); ?></a>)
								<?php

							endif;
						endif;

						$speaker_count++;

					endforeach;
				endif;
				break;

			case 'proposal_video':

				// Get the video.
				$session_video_id = wpcampus_speakers()->get_session_video( $post_id );

				if ( ! $session_video_id ) :
					?><em><?php _e( 'No video', 'wpcampus' ); ?></em><?php
				else :

					// Get URLs.
					$video_url = wpcampus_speakers()->get_session_video_url( $post_id, $session_video_id );
					$edit_video_url = get_edit_post_link( $session_video_id );

					?><a href="<?php echo $video_url; ?>" target="_blank"><?php _e( 'View video', 'wpcampus' ); ?></a> (<a href="<?php echo $edit_video_url; ?>"><?php _e( 'Edit', 'wpcampus' ); ?></a>)<?php
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

				break;
		}
	}

	/**
	 * Add the proposal status filter.
	 *
	 * @args    $post_status - string - the current post status.
	 */
	public function add_proposal_status_filter( $post_status ) {
		global $wpdb;

		// If a proposal event is selected, add hidden input for filters.
		if ( ! empty( $_GET['proposal_event'] ) ) :
			?>
			<input type="hidden" name="proposal_event" value="<?php echo esc_attr( $_GET['proposal_event'] ); ?>" />
			<?php
		endif;

		$proposal_status_choices = array(
			'confirmed' => array(
				'count' => 0,
				'label' => __( 'Confirmed', 'wpcampus' ),
			),
			'declined'  => array(
				'count' => 0,
				'label' => __( 'Declined', 'wpcampus' ),
			),
			'selected'  => array(
				'count' => 0,
				'label' => __( 'Selected', 'wpcampus' ),
			),
			'submitted' => array(
				'count' => 0,
				'label' => __( 'Submitted', 'wpcampus' ),
			),
		);

		$db_counts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT meta_keys.meta_value AS status, COUNT(*) AS count
				FROM {$wpdb->postmeta} meta_keys
				INNER JOIN {$wpdb->posts} posts ON posts.ID = meta_keys.post_id AND posts.post_type = 'proposal' AND IF ( %s != '', posts.post_status = %s, true )
				WHERE meta_keys.meta_key = 'proposal_status' GROUP BY meta_keys.meta_value",
				$post_status, $post_status
			)
		);

		if ( ! empty( $db_counts ) ) {
			foreach( $db_counts as $count ) {
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
		}

		$selected_proposal_status = ! empty( $_GET['proposal_status'] ) ? $_GET['proposal_status'] : null;

		?>
		<select name="proposal_status">
			<option value=""><?php _e( 'Sort by status', 'wpcampus' ); ?></option>
			<?php

			foreach ( $proposal_status_choices as $value => $choice ) :
				?>
				<option value="<?php echo $value; ?>"<?php selected( $selected_proposal_status, $value ); ?>><?php echo $choice['label']; ?> (<?php echo $choice['count']; ?>)</option>
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

		// Help clean up the admin.
		remove_meta_box( 'slugdiv', 'profile', 'normal' );
		remove_meta_box( 'slugdiv', 'proposal', 'normal' );

	}

	/**
	 * Prints the content in our custom admin meta boxes.
	 */
	public function print_meta_boxes( $post, $metabox ) {

		switch ( $metabox['id'] ) {

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
	 * Print a table of info for a speaker's proposal(s).
	 */
	public function print_speaker_proposals_table( $speaker_id ) {
		global $wpdb;

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

							wpcampus_speakers()->print_proposal_status_label( $proposal->ID, $proposal->proposal_status );

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
							$proposal_speaker_ids = wpcampus_speakers()->get_proposal_speaker_ids( $proposal->ID );

							if ( ! empty( $proposal_speaker_ids ) ) :

								$speaker_count = 0;

								foreach( $proposal_speaker_ids as $this_speaker_id ) :

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

			$proposal_status = wpcampus_speakers()->get_proposal_status( $post_id );

			// Print the proposal's status.
			wpcampus_speakers()->print_proposal_status_label( $post_id, $proposal_status );

			// Print confirmation URL no matter what for admin sake.
			$proposal_confirmation_url = wpcampus_speakers()->get_proposal_confirmation_url( $post_id, true );
			if ( ! empty( $proposal_confirmation_url ) ) :

				?>
				<br><a href="<?php echo $proposal_confirmation_url; ?>" target="_blank"><?php _e( 'Confirmation form', 'wpcampus-speakers' ); ?></a>
				<?php
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
		$entry_id = (int) get_post_meta( $post_id, 'gf_entry_id', true );

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
	 * Creates CSV of speakers information.
	 *
	 * @TODO:
	 * - Update to work with new system.

	public function create_speakers_csv() {

		// Get the speakers.
		$speakers = get_posts( array(
			'posts_per_page'    => -1,
			'orderby'           => 'title',
			'order'             => 'ASC',
			'post_type'         => 'speakers',
			'post_status'       => 'publish',
			'suppress_filters'  => false,
		));

		// Create array for CSV.
		$speakers_csv = array();

		foreach( $speakers as $speaker ) {

			// Will hold feedback URL(s).
			$session_titles = array();
			$feedback_urls = array();

			// Get the speaker.
			$the_speaker = class_exists( 'Conference_Schedule_Speaker' ) ? new Conference_Schedule_Speaker( $speaker->ID ) : null;
			if ( $the_speaker ) {

				// Get speaker events.
				$speaker_events = $the_speaker->get_events();
				if ( ! empty( $speaker_events ) ) {

					foreach( $speaker_events as $event ) {

						// Add the title.
						$session_titles[] = $event->post_title;

						// Get the feedback URL.
						$feedback_url = get_post_meta( $event->ID, 'conf_sch_event_feedback_url', true );

						// Filter the feedback URL.
						$feedback_url = apply_filters( 'conf_sch_feedback_url', $feedback_url, $event );

						if ( ! empty( $feedback_url ) ) {
							$feedback_urls[] = $feedback_url;
						}
					}
				}
			}

			$speakers_csv[] = array(
				$speaker->post_title,
				implode( ', ', $session_titles ),
				$speaker->post_content,
				implode( ', ', $feedback_urls ),
			);
		}

		// Create temporary CSV file for the complete photo list.
		$csv_speakers_filename = 'wpcampus-2017-speakers.csv';
		$csv_speakers_file_path = "/tmp/{$csv_speakers_filename}";
		$csv_speakers_file = fopen( $csv_speakers_file_path, 'w' );

		// Add headers.
		fputcsv( $csv_speakers_file, array( 'Name', 'Session', 'Intro', 'Feedback' ) );

		// Write image info to the file.
		foreach ( $speakers_csv as $speaker ) {
			fputcsv( $csv_speakers_file, $speaker );
		}

		// Close the file.
		fclose( $csv_speakers_file );

		// Output headers so that the file is downloaded rather than displayed.
		header( 'Content-type: text/csv' );
		header( "Content-disposition: attachment; filename = {$csv_speakers_filename}" );
		header( 'Content-Length: ' . filesize( $csv_speakers_file_path ) );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Read the file.
		readfile( $csv_speakers_file_path );

		exit;
	}*/
}
WPCampus_Speakers_Admin::register();
