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
		add_filter( 'manage_proposal_posts_columns', array( $plugin, 'add_proposal_columns' ) );
		add_action( 'manage_proposal_posts_custom_column', array( $plugin, 'populate_proposal_columns' ), 10, 2 );

		// Adds dropdown to filter the speaker status.
		add_action( 'restrict_manage_posts', array( $plugin, 'add_proposal_filters' ), 100 );

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
	 * Add custom admin columns for proposals.
	 */
	public function add_proposal_columns( $columns ) {

		// Columns to add after title.
		$add_columns_after_title = array(
			'proposal_status'   => __( 'Status', 'wpcampus' ),
			'proposal_speaker'  => __( 'Speaker', 'wpcampus' ),
		);

		// Store new columns.
		$new_columns = array();

		foreach ( $columns as $key => $value ) {

			// Add to new columns.
			$new_columns[ $key ] = $value;

			// Add custom columns after title.
			if ( 'title' == $key ) {
				foreach ( $add_columns_after_title as $column_key => $column_value ) {
					$new_columns[ $column_key ] = $column_value;
				}
			}
		}

		return $new_columns;
	}

	/**
	 * Populate our custom proposal columns.
	 */
	public function populate_proposal_columns( $column, $post_id ) {

		switch ( $column ) {

			case 'proposal_status':
				$proposal_status = get_post_meta( $post_id, 'proposal_status', true );
				switch ( $proposal_status ) {

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
				break;

			case 'proposal_speaker':

				$proposal_speaker_id = get_post_meta( $post_id, 'speaker_profile', true );
				if ( $proposal_speaker_id > 0 ) {

					$speaker_display_name = get_post_meta( $proposal_speaker_id, 'display_name', true );

					if ( ! empty( $speaker_display_name ) ) :
						?><a href="<?php echo get_edit_post_link( $proposal_speaker_id ); ?>"><?php echo $speaker_display_name; ?></a><?php
					endif;
				}
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
}
WPCampus_Speakers_Admin::register();
