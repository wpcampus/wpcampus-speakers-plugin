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
}
