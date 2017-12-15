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

	}

	/**
	 * Add pages to the admin.
	 */
	public function add_menu_pages() {
		$plugin = new self();

		// Add menu section to manage all of the speaker information.
		add_menu_page( __( 'Speakers', 'wpcampus' ), __( 'Speakers', 'wpcampus' ), 'manage_wpc_speakers', 'wpc-speakers', array( $plugin, 'print_speakers_main_page' ), 'dashicons-megaphone', 10.111 );

	}

	/**
	 * Print the main page of the speakers section.
	 */
	public function print_speakers_main_page() {}
}
WPCampus_Speakers_Admin::register();
