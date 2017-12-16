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
}
WPCampus_Speakers_Admin::register();
