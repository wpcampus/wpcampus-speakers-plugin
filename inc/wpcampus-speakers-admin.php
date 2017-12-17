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
	 * Constructing the class object.
	 *
	 * @access  public
	 */
	public function __construct() {}

	/**
	 * Registers all of our hooks and what not.
	 */
	public static function register() {
		$plugin = new self();

		// Add items to the admin menu.
		add_action( 'admin_menu', array( $plugin, 'add_menu_pages' ) );
		add_action( 'parent_file', array( $plugin, 'filter_submenu_parent' ) );

		// Add and populate custom columns.
		add_filter( 'manage_profile_posts_columns', array( $plugin, 'add_profile_columns' ) );
		add_filter( 'manage_proposal_posts_columns', array( $plugin, 'add_proposal_columns' ) );
		add_action( 'manage_profile_posts_custom_column', array( $plugin, 'populate_profile_columns' ), 10, 2 );
		add_action( 'manage_proposal_posts_custom_column', array( $plugin, 'populate_proposal_columns' ), 10, 2 );

		// Adds dropdown to filter the speaker status.
		add_action( 'restrict_manage_posts', array( $plugin, 'add_proposal_filters' ), 100 );

		// Add meta boxes.
		add_action( 'add_meta_boxes', array( $plugin, 'add_meta_boxes' ), 1, 2 );

	}

	/**
	 * Add pages to the admin.
	 */
	public function add_menu_pages() {
		$plugin = new self();

		// Add menu section to manage all of the speaker information.
		add_menu_page( __( 'Speakers', 'wpcampus' ), __( 'Speakers', 'wpcampus' ), 'manage_wpc_speakers', 'wpc-speakers', array( $plugin, 'print_speakers_main_page' ), 'dashicons-megaphone', 22 );

		// Add taxonomy pages under speakers.
		add_submenu_page( 'wpc-speakers', __( 'Events', 'wpcampus' ), __( 'Events', 'wpcampus' ), 'manage_categories', 'edit-tags.php?taxonomy=event&section=wpc-speakers' );
		add_submenu_page( 'wpc-speakers', __( 'Subjects', 'wpcampus' ), __( 'Subjects', 'wpcampus' ), 'manage_categories', 'edit-tags.php?taxonomy=subjects&section=wpc-speakers' );

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
		if ( ! empty( $current_screen->taxonomy ) && in_array( $current_screen->taxonomy, array( 'event', 'subjects' ) ) ) {
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
	 * Used in admin to add custom
	 * columns after the title.
	 */
	public function add_columns_after_title( $columns, $add_after_title ) {

		// Store new columns.
		$new_columns = array();

		foreach ( $columns as $key => $value ) {

			// Add to new columns.
			$new_columns[ $key ] = $value;

			// Add custom columns after title.
			if ( 'title' == $key ) {
				foreach ( $add_after_title as $column_key => $column_value ) {
					$new_columns[ $column_key ] = $column_value;
				}
			}
		}

		return $new_columns;
	}

	/**
	 * Add custom admin columns for profiles.
	 */
	public function add_profile_columns( $columns ) {
		return $this->add_columns_after_title( $columns, array(
			'profile_name'  => __( 'Speaker', 'wpcampus' ),
			'profile_user'  => __( 'User', 'wpcampus' ),
			'profile_email' => __( 'Email', 'wpcampus' ),
		));
	}

	/**
	 * Add custom admin columns for proposals.
	 */
	public function add_proposal_columns( $columns ) {
		return $this->add_columns_after_title( $columns, array(
			'proposal_status'   => __( 'Status', 'wpcampus' ),
			'proposal_speaker'  => __( 'Speaker(s)', 'wpcampus' ),
		));
	}

	/**
	 * Populate our custom profile columns.
	 */
	public function populate_profile_columns( $column, $post_id ) {

		switch ( $column ) {

			case 'profile_name':
				echo get_post_meta( $post_id, 'display_name', true );
				break;

			case 'profile_user':
				$profile_user_id = get_post_meta( $post_id, 'wordpress_user', true );
				if ( $profile_user_id > 0 ) {
					$user = get_userdata( $profile_user_id );
					if ( false !== $user ) :

						// Setup the filter URL.
						$filters = $_GET;
						$filters['profile_user'] = $profile_user_id;
						$filter_url = add_query_arg( $filters, 'edit.php' );

						?><a href="<?php echo $filter_url; ?>"><?php echo $user->display_name; ?></a><?php
					endif;
				}
				break;

			case 'profile_email':
				$email = get_post_meta( $post_id, 'email', true );
				if ( ! empty( $email ) ) :
					?><a href="mailto:<?php echo $email; ?>"><?php echo $email; ?></a><?php
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
				$this->print_proposal_status_label( get_post_meta( $post_id, 'proposal_status', true ) );
				break;

			case 'proposal_speaker':
				if ( function_exists( 'have_rows' ) && have_rows( 'speakers', $post_id ) ) :
					$speaker_count = 0;
					while ( have_rows( 'speakers', $post_id ) ) :
						the_row();

						$speaker_id = get_sub_field( 'speaker' );
						if ( $speaker_id > 0 ) :

							$speaker_display_name = get_post_meta( $speaker_id, 'display_name', true );
							if ( ! empty( $speaker_display_name ) ) :

								// Setup the filter URL.
								$filters = $_GET;
								$filters['proposal_speaker'] = $speaker_id;
								$filter_url = add_query_arg( $filters, 'edit.php' );

								echo $speaker_count > 0 ? '<br />' : null;
								?><a href="<?php echo $filter_url; ?>"><?php echo $speaker_display_name; ?></a> (<a href="<?php echo get_edit_post_link( $speaker_id ); ?>"><?php _e( 'Edit', 'wpcampus' ); ?></a>)<?php

							endif;
						endif;
						$speaker_count++;
					endwhile;
				endif;
				break;
		}
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

				$proposal_status_choices = array(
					'confirmed' => __( 'Confirmed', 'wpcampus' ),
					'declined'  => __( 'Declined', 'wpcampus' ),
					'selected'  => __( 'Selected', 'wpcampus' ),
					'submitted' => __( 'Submitted', 'wpcampus' ),
				);

				$selected_proposal_status = ! empty( $_GET['proposal_status'] ) ? $_GET['proposal_status'] : null;

				?>
				<select name="proposal_status">
					<option value=""><?php _e( 'Sort by status', 'wpcampus' ); ?></option>
					<?php

					foreach ( $proposal_status_choices as $value => $label ) :
						?>
						<option value="<?php echo $value; ?>"<?php selected( $selected_proposal_status, $value ); ?>><?php echo $label; ?></option>
						<?php
					endforeach;

					?>
				</select>
				<?php

				break;
		}
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
		}
	}

	/**
	 * Prints the content in our custom admin meta boxes.
	 */
	public function print_meta_boxes( $post, $metabox ) {

		switch ( $metabox['id'] ) {

			case 'wpcampus-speakers-proposal':
				$this->print_speaker_proposals_table( $post->ID );
				break;
		}
	}

	/**
	 * Print a table of info for a speaker's proposal(s).
	 */
	public function print_speaker_proposals_table( $speaker_id ) {
		global $wpdb;

		$proposals = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title,
			proposalm2.meta_value AS proposal_status,
			eventm.meta_value AS event_id, eventt.name AS event_name, eventt.slug AS event_slug
			FROM {$wpdb->posts} proposal
			INNER JOIN {$wpdb->postmeta} proposalm1 ON proposalm1.post_id = proposal.ID AND proposalm1.meta_key REGEXP '^speakers\_([0-9]+)\_speaker$' AND proposalm1.meta_value = %s
			LEFT JOIN {$wpdb->postmeta} proposalm2 ON proposalm2.post_id = proposal.ID AND proposalm2.meta_key = 'proposal_status'
			LEFT JOIN {$wpdb->postmeta} eventm ON eventm.post_id = proposal.ID AND eventm.meta_key = 'event'
			LEFT JOIN {$wpdb->terms} eventt ON eventt.term_id = eventm.meta_value
			WHERE proposal.post_type = 'proposal' AND proposal.post_status = 'publish'", $speaker_id
		));

		if ( empty( $proposals ) ) :
			?><p><em><?php _e( 'This speaker has no proposals.', 'wpcampus' ); ?></em></p><?php
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
						<td><?php $this->print_proposal_status_label( $proposal->proposal_status ); ?></td>
						<td><?php

						if ( ! empty( $proposal->event_name ) ) :

							if ( ! empty( $proposal->event_slug ) ) :

								$filter_url = add_query_arg( array(
									'post_type' => 'proposal',
									'event'     => $proposal->event_slug,
								), admin_url( 'edit.php' ) );

								?><a href="<?php echo $filter_url; ?>"><?php echo $proposal->event_name; ?></a><?php
							else :
								echo $proposal->event_name;
							endif;
						endif;

						?></td>
						<td><?php

						if ( function_exists( 'have_rows' ) && have_rows( 'speakers', $proposal->ID ) ) :
							$speaker_count = 0;
							while ( have_rows( 'speakers', $proposal->ID ) ) :
								the_row();

								$this_speaker_id = get_sub_field( 'speaker' );
								if ( $this_speaker_id > 0 ) :

									$speaker_display_name = get_post_meta( $this_speaker_id, 'display_name', true );
									if ( ! empty( $speaker_display_name ) ) :

										echo $speaker_count > 0 ? '<br />' : null;

										if ( $this_speaker_id != $speaker_id ) :
											?><a href="<?php echo get_edit_post_link( $this_speaker_id ); ?>"><?php echo $speaker_display_name; ?></a><?php
										else :
											echo $speaker_display_name;
										endif;
									endif;
								endif;
								$speaker_count++;
							endwhile;
						endif;

						?></td>
					</tr>
					<?php
				endforeach;

				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Print the label for a proposal status.
	 */
	public function print_proposal_status_label( $status ) {
		switch ( $status ) {
			case 'confirmed':
				?><span style="color:green;"><?php _e( 'Confirmed', 'wpcampus' ); ?></span><?php
				break;

			case 'declined':
				?><span style="color:red;"><?php _e( 'Declined', 'wpcampus' ); ?></span><?php
				break;

			case 'selected':
				?><strong><?php _e( 'Pending', 'wpcampus' ); ?></strong><?php
				break;

			default:
				?><em><?php _e( 'Submitted', 'wpcampus' ); ?></em><?php
				break;
		}
	}
}
WPCampus_Speakers_Admin::register();
