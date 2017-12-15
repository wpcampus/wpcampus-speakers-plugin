<?php
/**
 * Plugin Name:     WPCampus: Speakers
 * Plugin URI:      https://wpcampus.org
 * Description:     Manages the speaker functionality for the main WPCampus website.
 * Version:         1.0.0
 * Author:          WPCampus
 * Author URI:      https://wpcampus.org
 * License:         GPL-2.0+
 * License URI:     http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:     wpc-speakers
 * Domain Path:     /languages
 *
 * @package         WPCampus Speakers
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// We only need you in the admin.
if ( is_admin() ) {
	require_once plugin_dir_path( __FILE__ ) . 'inc/wpcampus-speakers-admin.php';
}

/**
 * PHP class that holds the main/administrative
 * functionality for the plugin.
 *
 * @since       1.0.0
 * @category    Class
 * @package     WPCampus Speakers
 */
class WPCampus_Speakers {

	/**
	 * Warming up the engine.
	 */
	public function __construct() {}

	/**
	 * Registers all of our hooks and what not.
	 */
	public static function register() {
		$plugin = new self();

		// Register our post types.
		add_action( 'init', array( $plugin, 'register_custom_post_types' ) );

	}

	/**
	 * Registers all of our custom post types.
	 */
	public function register_custom_post_types() {

		// Define the labels for the post type.
		$proposal_labels = array(
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
		);

		// Define the arguments for the post type.
		$proposal_args = array(
			'label'                 => __( 'Proposals', 'wpcampus' ),
			'labels'                => $proposal_labels,
			'supports'              => array( 'title', 'editor', 'author', 'revisions' ),
			'taxonomies'            => array( 'subjects' ),
			'hierarchical'          => false,
			'public'                => false,
			'show_ui'               => true,
			'show_in_menu'          => 'wpc-speakers',
			'show_in_admin_bar'     => true,
			'show_in_nav_menus'     => false,
			'can_export'            => true,
			'has_archive'           => false,
			'exclude_from_search'   => true,
			'publicly_queryable'    => false,
			'capability_type'       => array( 'proposal', 'proposals' ),
		);

		register_post_type( 'proposal', $proposal_args );
	}
}
WPCampus_Speakers::register();
