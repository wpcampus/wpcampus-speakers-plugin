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

/**
 * @TODO:
 * - Setup confirmation form in new setup (move from sessions plugin).
 * - Remove the dropdown selection status filter from the session plugin.
 * - Move "WPCampus Speaker Information" meta box in sessions plugin to live elsewhere?
 * - Move "Technical Levels" to proposal?
 *
 * - Update confirmation forms to have a proposal ID and a user ID (as opposed to speaker ID).
 */

defined( 'ABSPATH' ) or die();

require_once wpcampus_speakers()->get_plugin_dir() . 'inc/wpcampus-speakers-fields.php';
require_once wpcampus_speakers()->get_plugin_dir() . 'inc/class-wpcampus-speakers-forms.php';
require_once wpcampus_speakers()->get_plugin_dir() . 'inc/class-wpcampus-speakers-global.php';

if ( is_admin() ) {
	require_once wpcampus_speakers()->get_plugin_dir() . 'inc/class-wpcampus-speakers-admin.php';
}

/**
 * Class that manages and returns plugin data.
 *
 * @class       WPCampus_Speakers
 * @package     WPCampus Speakers
 */
final class WPCampus_Speakers {

	/**
	 * Holds the plugin version.
	 *
	 * @var     string
	 */
	private $version = '1.0.0';

	/**
	 * Holds the absolute URL to
	 * the main plugin directory.
	 *
	 * @var     string
	 */
	private $plugin_url;

	/**
	 * Holds the directory path
	 * to the main plugin directory.
	 *
	 * @var     string
	 */
	private $plugin_dir;

	/**
	 * Holds the class instance.
	 *
	 * @var     WPCampus_Speakers
	 */
	private static $instance;

	/**
	 * Returns the instance of this class.
	 *
	 * @return  WPCampus_Speakers
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			$class_name = __CLASS__;
			self::$instance = new $class_name;
		}
		return self::$instance;
	}

	/**
	 * Magic method to output a string if
	 * trying to use the object as a string.
	 *
	 * @return  string
	 */
	public function __toString() {
		return 'WPCampus_Speakers';
	}

	/**
	 * Method to keep our instance
	 * from being cloned or unserialized
	 * and to prevent a fatal error when
	 * calling a method that doesn't exist.
	 *
	 * @return  void
	 */
	public function __clone() {}
	public function __wakeup() {}
	public function __call( $method = '', $args = array() ) {}

	/**
	 * Start your engines.
	 */
	protected function __construct() {

		// Store the plugin URL and DIR.
		$this->plugin_url = plugin_dir_url( __FILE__ );
		$this->plugin_dir = plugin_dir_path( __FILE__ );

	}

	/**
	 * Returns the plugin version.
	 *
	 * @return  string
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Returns the absolute URL to
	 * the main plugin directory.
	 *
	 * @return  string
	 */
	public function get_plugin_url() {
		return $this->plugin_url;
	}

	/**
	 * Returns the directory path
	 * to the main plugin directory.
	 *
	 * @return  string
	 */
	public function get_plugin_dir() {
		return $this->plugin_dir;
	}

	/**
	 * Build and return a video's YouTube
	 * watch URL based on the video ID.
	 */
	public function get_youtube_url( $youtube_id ) {
		$youtube_watch_url = 'https://www.youtube.com/watch';
		return add_query_arg( 'v', $youtube_id, $youtube_watch_url );
	}

	/**
	 * Get the URL for a video post.
	 */
	public function get_video_url( $post_id ) {

		// Get YouTube ID from the video post.
		$youtube_id = $this->get_video_youtube_id( $post_id );
		if ( empty( $youtube_id ) ) {
			return '';
		}

		// Return the YouTube URL.
		return $this->get_youtube_url( $youtube_id );
	}

	/**
	 * Build and return a video's YouTube
	 * watch URL based on the video ID.
	 */
	public function get_video_youtube_id( $post_id ) {
		return get_post_meta( $post_id, 'wpc_youtube_video_id', true );
	}

	/**
	 * Get the proposal's session video post ID.
	 */
	public function get_proposal_video_id( $post_id ) {
		return get_post_meta( $post_id, 'session_video', true );
	}

	/**
	 * Get the proposal's session video URL.
	 */
	public function get_proposal_video_url( $post_id, $proposal_video_id = null ) {

		// If URL is hard-set, return first.
		$video_url = get_post_meta( $post_id, 'session_video_url', true );
		if ( ! empty( $video_url ) ) {
			return $video_url;
		}

		// See if a video is assigned to the post.
		if ( ! isset( $proposal_video_id ) ) {
			$proposal_video_id  = $this->get_proposal_video_id( $post_id );
		}

		// This means its one of our video posts so get its URL.
		if ( ! empty( $proposal_video_id ) ) {
			return $this->get_video_url( $proposal_video_id );
		}

		return '';
	}

	/**
	 * Get the slides URL for a proposal.
	 */
	public function get_session_slides_url( $post_id ) {
		return get_post_meta( $post_id, 'session_slides_url', true );
	}

	/**
	 * Get the speaker IDs for a proposal.
	 *
	 * @args    $proposal_id - int - the proposal ID.
	 * @return  array - the speaker IDs.
	 */
	public function get_proposal_speaker_ids( $proposal_id ) {
		global $wpdb;
		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key REGEXP '^speakers\_[0-9]+\_speaker$'",
				$proposal_id
			)
		);
	}

	/**
	 * Get a proposal's status.
	 *
	 * @TODO:
	 * - Update to use "proposal_status"
	 * where old system used "conf_sch_speaker_status".
	 *
	 * Options:
	 *   - confirmed
	 *   - declined
	 *   - selected
	 *   - submitted (default, if blank)
	 */
	public function get_proposal_status( $proposal_id ) {
		return get_post_meta( $proposal_id, 'proposal_status', true );
	}

	/**
	 * Will return true if proposal has either
	 * been "confirmed" or "declined", which means
	 * the speaker has confirmed their status.
	 */
	public function has_proposal_been_confirmed( $proposal_id, $proposal_status = '' ) {
		if ( ! $proposal_status ) {
			$proposal_status = $this->get_proposal_status( $proposal_id );
		}
		return in_array( $proposal_status, array( 'confirmed', 'declined' ) );
	}

	/**
	 * Return the label for a proposal status.
	 */
	public function print_proposal_status_label( $proposal_id, $proposal_status = '' ) {
		if ( ! $proposal_status ) {
			$proposal_status = $this->get_proposal_status( $proposal_id );
		}
		switch ( $proposal_status ) {
			case 'confirmed':
				?>
				<span style="color:green;"><?php _e( 'Confirmed', 'wpcampus' ); ?></span>
				<?php
				break;

			case 'declined':
				?>
				<span style="color:#900;"><?php _e( 'Declined', 'wpcampus' ); ?></span>
				<?php
				break;

			case 'selected':
				?>
				<strong><?php _e( 'Pending', 'wpcampus' ); ?></strong>
				<?php
				break;

			default:
				?>
				<em><?php _e( 'Submitted', 'wpcampus' ); ?></em>
				<?php
				break;
		}
	}

	/**
	 * Get a proposal's confirmation ID.
	 *
	 * @TODO
	 * - Update to use "proposal_confirmation_id" where old system used "conf_sch_confirmation_id".
	 * - Instead assign to users/speakers?
	 */
	public function get_proposal_confirmation_id( $proposal_id, $create = false ) {

		// Get proposal's confirmation id.
		$proposal_confirmation_id = get_post_meta( $proposal_id, 'proposal_confirmation_id', true );

		// If no confirmation ID, create one.
		if ( ! $proposal_confirmation_id && $create ) {
			$proposal_confirmation_id = $this->create_proposal_confirmation_id( $proposal_id );
		}

		return ! empty( $proposal_confirmation_id ) ? $proposal_confirmation_id : false;
	}

	/**
	 * Create a proposal's confirmation ID.
	 *
	 * @TODO
	 * - Update to use "proposal_confirmation_id" where old system used "conf_sch_confirmation_id".
	 * - Instead assign to users/speakers?
	 */
	public function create_proposal_confirmation_id( $proposal_id ) {
		global $wpdb;
		$new_id = $wpdb->get_var( "SELECT SUBSTRING(MD5(RAND()),16)" );
		if ( ! empty( $new_id ) ) {
			update_post_meta( $proposal_id, 'proposal_confirmation_id', $new_id );
		}
		return $new_id;
	}

	/**
	 * Get a proposal confirmation's URL.
	 *
	 * @TODO:
	 * - Instead assign to users/speakers?
	 */
	public function get_proposal_confirmation_url( $proposal_id, $create = false ) {

		// @TODO: Setup
		return '';

		// Get proposal confirmation ID.
		$proposal_confirmation_id = $this->get_proposal_confirmation_id( $proposal_id, $create );

		/*
		 * Build confirmation URL.
		 *
		 * TODO:
		 * - We used to pass speaker and
		 * session ID and now its just proposal
		 * ID and then the speaker must be logged in
		 * so we can know who confirmed.
		 *
		 * What to do when multiple speakers?
		 */
		return add_query_arg( array(
			'proposal'  => $proposal_id,
			//'session' => $event->ID,
			'c'         => $proposal_confirmation_id,
		), get_bloginfo( 'url' ) . '/speaker-confirmation/' );
	}

	/**
	 * Properly strip HTML tags including script and style.
	 *
	 * Adapted from wp_strip_all_tags().
	 *
	 * @param   $string - string - String containing HTML tags.
	 * @param   $allowed_tags - string - the tags we don't want to strip.
	 * @return  string - The processed string.
	 */
	public function strip_content_tags( $string, $allowed_tags = '<a><ul><ol><li><em><strong>' ) {

		// Remove <script> and <style> tags.
		$string = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $string );

		// Remove all but allowed tags.
		$string = strip_tags( $string, $allowed_tags );

		// Remove line breaks.
		$string = preg_replace( '/[\r\n\t ]+/', ' ', $string );

		return trim( $string );
	}
}

/**
 * Returns the instance of our WPCampus_Speakers class.
 *
 * Use this function and class methods
 * to retrieve plugin data.
 *
 * @return  WPCampus_Speakers
 */
function wpcampus_speakers() {
	return WPCampus_Speakers::instance();
}
