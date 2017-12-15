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
		add_action( 'init', array( $plugin, 'register_custom_post_types_taxonomies' ) );

	}

	/**
	 * Registers all of our custom
	 * post types and taxonomies.
	 */
	public function register_custom_post_types_taxonomies() {

		// @TODO Only be able to access with authentication.
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
			'show_in_rest'          => true,
		));

		// @TODO Only be able to access with authentication.
		register_post_type( 'profile', array(
			'label'                 => __( 'Profiles', 'wpcampus' ),
			'labels'                => array(
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
			'supports'              => array( 'title', 'editor', 'excerpt', 'author', 'thumbnail', 'revisions' ),
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
			'capability_type'       => array( 'profile', 'profiles' ),
			'show_in_rest'          => true,
		));

		// @TODO Only be able to access with authentication?
		register_taxonomy( 'event', array( 'proposal' ), array(
			'labels' => array(
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
	}
}
WPCampus_Speakers::register();
